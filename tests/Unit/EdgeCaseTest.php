<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Exceptions\ControllerResolutionException;
use DancyCodes\FlashHalt\Exceptions\SecurityValidationException;
use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use DancyCodes\FlashHalt\Tests\TestCase;

/**
 * Edge Case Tests
 * 
 * These tests verify that FlashHALT handles unusual scenarios, boundary
 * conditions, and edge cases gracefully without breaking or exposing
 * security vulnerabilities.
 */
class EdgeCaseTest extends TestCase
{
    /** @test */
    public function it_handles_extremely_long_route_patterns()
    {
        $longPattern = str_repeat('very-long-controller-name-', 50) . '@' . str_repeat('very-long-method-name-', 50);
        
        $resolver = $this->app->make(ControllerResolver::class);
        
        $this->expectException(ControllerResolutionException::class);
        $resolver->resolveController($longPattern, 'GET');
    }

    /** @test */
    public function it_handles_unicode_characters_in_patterns()
    {
        $unicodePattern = 'тест@метод'; // Cyrillic characters
        
        $resolver = $this->app->make(ControllerResolver::class);
        
        $this->expectException(ControllerResolutionException::class);
        $resolver->resolveController($unicodePattern, 'GET');
    }

    /** @test */
    public function it_handles_deeply_nested_namespace_patterns()
    {
        $deepPattern = 'level1.level2.level3.level4.level5.controller@method';
        
        $resolver = $this->app->make(ControllerResolver::class);
        
        $this->expectException(ControllerResolutionException::class);
        $resolver->resolveController($deepPattern, 'GET');
    }

    /** @test */
    public function it_handles_malformed_patterns_gracefully()
    {
        $malformedPatterns = [
            '', // Empty pattern
            '@', // Missing controller and method
            'controller@', // Missing method
            '@method', // Missing controller
            'controller@@method', // Double separator
            'controller@method@extra', // Extra separator
            'controller.@method', // Malformed namespace
            'controller@.method', // Malformed method
        ];

        $resolver = $this->app->make(ControllerResolver::class);

        foreach ($malformedPatterns as $pattern) {
            try {
                $resolver->resolveController($pattern, 'GET');
                $this->fail("Expected exception for malformed pattern: {$pattern}");
            } catch (ControllerResolutionException $e) {
                $this->assertInstanceOf(ControllerResolutionException::class, $e);
            }
        }
    }

    /** @test */
    public function it_handles_unusual_http_methods()
    {
        $this->createTestController('Http', ['options' => 'OPTIONS response']);
        
        $unusualMethods = ['OPTIONS', 'HEAD', 'TRACE', 'CONNECT'];
        $resolver = $this->app->make(ControllerResolver::class);

        foreach ($unusualMethods as $method) {
            try {
                $result = $resolver->resolveController('http@options', $method);
                $this->assertArrayHasKey('controller', $result);
            } catch (\Exception $e) {
                // Some methods might be rejected by security validation
                $this->assertInstanceOf(SecurityValidationException::class, $e);
            }
        }
    }

    /** @test */
    public function it_handles_memory_pressure_gracefully()
    {
        $resolver = $this->app->make(ControllerResolver::class);
        
        // Create many unique patterns to fill up cache
        for ($i = 0; $i < 1000; $i++) {
            try {
                $resolver->resolveController("controller{$i}@method{$i}", 'GET');
            } catch (ControllerResolutionException $e) {
                // Expected - controllers don't exist
            }
        }

        // Memory usage should not be excessive
        $memoryUsage = memory_get_usage();
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsage); // Less than 50MB
    }

    /** @test */
    public function it_handles_concurrent_cache_access()
    {
        $this->createTestController('Concurrent', ['test' => 'response']);
        
        $resolver = $this->app->make(ControllerResolver::class);
        
        // Simulate concurrent access by clearing and accessing cache rapidly
        $results = [];
        for ($i = 0; $i < 100; $i++) {
            if ($i % 10 === 0) {
                // Occasionally clear cache to simulate cache invalidation
                $this->app['cache']->flush();
            }
            
            $results[] = $resolver->resolveController('concurrent@test', 'GET');
        }

        // All results should be consistent
        foreach ($results as $result) {
            $this->assertEquals('App\\Http\\Controllers\\ConcurrentController', $result['class']);
            $this->assertEquals('test', $result['method']);
        }
    }

    /** @test */
    public function it_handles_circular_dependency_scenarios()
    {
        // This test ensures that FlashHALT doesn't get stuck in infinite loops
        // when resolving complex patterns or validating security rules
        
        $resolver = $this->app->make(ControllerResolver::class);
        $validator = $this->app->make(SecurityValidator::class);

        // Attempt to create scenarios that might cause circular dependencies
        $complexPatterns = [
            'self.reference@self',
            'a.b.c.d.e.f.g.h@method',
            str_repeat('nested.', 100) . 'controller@method'
        ];

        foreach ($complexPatterns as $pattern) {
            $startTime = microtime(true);
            
            try {
                $resolver->resolveController($pattern, 'GET');
            } catch (\Exception $e) {
                // Expected to fail, but should fail quickly
            }
            
            $executionTime = microtime(true) - $startTime;
            $this->assertLessThan(1.0, $executionTime); // Should complete within 1 second
        }
    }
}