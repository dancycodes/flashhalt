<?php

namespace DancyCodes\FlashHalt\Tests\Performance;

use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

/**
 * Cache Performance Tests
 * 
 * These tests verify that FlashHALT's caching mechanisms work efficiently
 * and provide the expected performance benefits for repeated operations.
 */
class CachePerformanceTest extends TestCase
{
    /** @test */
    public function controller_resolution_cache_improves_performance()
    {
        $this->createTestController('Performance', [
            'test' => 'cached response'
        ]);

        $resolver = $this->app->make(ControllerResolver::class);

        // Measure first resolution (cache miss)
        $startTime = microtime(true);
        $result1 = $resolver->resolveController('performance@test', 'GET');
        $firstResolutionTime = microtime(true) - $startTime;

        // Measure second resolution (cache hit)
        $startTime = microtime(true);
        $result2 = $resolver->resolveController('performance@test', 'GET');
        $secondResolutionTime = microtime(true) - $startTime;

        // Cache hit should be significantly faster
        $this->assertLessThan($firstResolutionTime * 0.5, $secondResolutionTime);
        $this->assertEquals($result1['class'], $result2['class']);
        $this->assertEquals($result1['method'], $result2['method']);
    }

    /** @test */
    public function security_validation_cache_reduces_repeated_checks()
    {
        $this->createTestController('Security', [
            'safeMethod' => 'validated response'
        ]);

        $validator = $this->app->make(SecurityValidator::class);

        // Perform multiple validations of the same method
        $times = [];
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            $validator->validateControllerMethod(
                'App\\Http\\Controllers\\SecurityController',
                'safeMethod',
                'GET'
            );
            $times[] = microtime(true) - $startTime;
        }

        // Later validations should be faster due to caching
        $averageEarly = array_sum(array_slice($times, 0, 3)) / 3;
        $averageLate = array_sum(array_slice($times, -3)) / 3;

        $this->assertLessThan($averageEarly, $averageLate);
    }

    /** @test */
    public function memory_usage_stays_within_reasonable_bounds()
    {
        $this->createMultipleTestControllers(50); // Create many controllers

        $resolver = $this->app->make(ControllerResolver::class);
        $initialMemory = memory_get_usage();

        // Resolve many different controllers
        for ($i = 1; $i <= 50; $i++) {
            $resolver->resolveController("test{$i}@index", 'GET');
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
    }

    /** @test */
    public function compilation_performance_scales_linearly()
    {
        $templateCounts = [10, 20, 30];
        $compilationTimes = [];

        foreach ($templateCounts as $count) {
            $this->createMultipleTestTemplates($count);
            
            $compiler = $this->app->make(\DancyCodes\FlashHalt\Services\RouteCompiler::class);
            
            $startTime = microtime(true);
            $compiler->compile();
            $compilationTimes[] = microtime(true) - $startTime;
            
            $this->cleanupTestTemplates();
        }

        // Compilation time should scale roughly linearly
        $ratio1 = $compilationTimes[1] / $compilationTimes[0];
        $ratio2 = $compilationTimes[2] / $compilationTimes[1];

        // Ratios should be close to the template count ratios
        $this->assertEqualsWithDelta(2.0, $ratio1, 0.5);
        $this->assertEqualsWithDelta(1.5, $ratio2, 0.3);
    }

    private function createMultipleTestControllers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->createTestController("Test{$i}", [
                'index' => "Response from Test{$i}"
            ]);
        }
    }

    private function createMultipleTestTemplates(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->createTestTemplate("template{$i}", "
                <div hx-get=\"hx/test{$i}@index\">Template {$i}</div>
                <button hx-post=\"hx/test{$i}@store\">Save</button>
            ");
        }
    }
}