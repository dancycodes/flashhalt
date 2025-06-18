<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Exceptions\SecurityValidationException;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

/**
 * SecurityValidator Unit Tests
 * 
 * These tests verify that the SecurityValidator service correctly identifies
 * and blocks dangerous method calls while allowing safe ones. Security
 * validation is critical for FlashHALT because it enables dynamic method
 * calls while maintaining Laravel's security boundaries.
 * 
 * The tests cover multiple validation layers:
 * - Basic method name validation
 * - Method blacklist checking
 * - Pattern-based blocking
 * - Reflection-based analysis
 * - HTTP method semantics
 * - Authorization integration
 */
class SecurityValidatorTest extends TestCase
{
    private SecurityValidator $validator;
    private Repository $cache;

    /**
     * Set up the SecurityValidator with a clean cache for each test.
     * This ensures that tests don't interfere with each other through
     * cached validation results.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a fresh cache instance for each test
        $this->cache = new Repository(new ArrayStore());
        
        // Get the security configuration for testing
        $securityConfig = $this->app['config']->get('flashhalt.security', []);
        
        // Create the SecurityValidator instance
        $this->validator = new SecurityValidator($securityConfig, $this->cache);
    }

    // ==================== BASIC VALIDATION TESTS ====================

    /** @test */
    public function it_rejects_empty_method_names()
    {
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('Method name must be a non-empty string');
        
        $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', '', 'GET');
    }

    /** @test */
    public function it_rejects_methods_that_are_too_long()
    {
        $longMethodName = str_repeat('a', 150); // Exceeds 100 character limit
        
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('Method name exceeds maximum allowed length');
        
        $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', $longMethodName, 'GET');
    }

    /** @test */
    public function it_rejects_methods_with_invalid_characters()
    {
        $invalidMethods = [
            'method-with-dashes',
            'method with spaces',
            'method@with@symbols',
            'method.with.dots',
            'method/with/slashes',
        ];
        
        foreach ($invalidMethods as $methodName) {
            try {
                $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', $methodName, 'GET');
                $this->fail("Expected SecurityValidationException for method name: {$methodName}");
            } catch (SecurityValidationException $e) {
                $this->assertStringContainsString('invalid characters', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_rejects_methods_starting_with_underscore()
    {
        $underscoreMethods = ['_privateMethod', '__construct', '__toString'];
        
        foreach ($underscoreMethods as $methodName) {
            try {
                $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', $methodName, 'GET');
                $this->fail("Expected SecurityValidationException for method: {$methodName}");
            } catch (SecurityValidationException $e) {
                $this->assertStringContainsString('Methods starting with underscore are not allowed', $e->getMessage());
            }
        }
    }

    // ==================== BLACKLIST AND PATTERN TESTS ====================

    /** @test */
    public function it_blocks_blacklisted_methods()
    {
        $blacklistedMethods = [
            'getRouteKey',
            'middleware',
            'callAction',
            'authorize',
            'dispatch'
        ];
        
        // Create a test controller to validate against
        $this->createTestController('Test', ['safeMethod' => 'response']);
        
        foreach ($blacklistedMethods as $methodName) {
            try {
                $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', $methodName, 'GET');
                $this->fail("Expected SecurityValidationException for blacklisted method: {$methodName}");
            } catch (SecurityValidationException $e) {
                $this->assertStringContainsString('is explicitly blacklisted for security reasons', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_blocks_methods_matching_dangerous_patterns()
    {
        $dangerousMethods = [
            'getUserPassword',
            'deleteAllRecords',
            'dropDatabase',
            'executeRawQuery',
            'evalCode'
        ];

        foreach ($dangerousMethods as $methodName) {
            try {
                $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', $methodName, 'GET');
                $this->fail("Expected SecurityValidationException for dangerous method: {$methodName}");
            } catch (SecurityValidationException $e) {
                $this->assertStringContainsString('matches a blocked pattern', $e->getMessage());
            }
        }
    }

    // ==================== HTMX-SPECIFIC SECURITY TESTS ====================    /** @test */
    public function it_validates_htmx_request_headers()
    {
        $this->withFlashHaltConfig([
            'security' => [
                'require_htmx_headers' => true
            ]
        ]);

        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('Missing required HTMX headers');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'index', 
            'GET'
        );
    }

    /** @test */
    public function it_enforces_production_mode_restrictions()
    {
        $this->withFlashHaltConfig([
            'mode' => 'production'
        ]);

        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('Dynamic method resolution is disabled in production');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'dynamicMethod', 
            'GET'
        );
    }

    // ==================== NAMESPACE VALIDATION TESTS ====================

    /** @test */
    public function it_validates_allowed_namespaces()
    {
        $this->withFlashHaltConfig([
            'security' => [
                'allowed_namespaces' => ['App\\Http\\Controllers\\Safe\\']
            ]
        ]);

        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('Controller namespace is not allowed');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\Unsafe\\TestController', 
            'index', 
            'GET'
        );
    }

    /** @test */
    public function it_allows_methods_in_safe_namespaces()
    {
        $this->withFlashHaltConfig([
            'security' => [
                'allowed_namespaces' => ['App\\Http\\Controllers\\Safe\\']
            ]
        ]);

        $this->createTestController('Safe\\Test', ['index' => 'safe response']);

        $result = $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\Safe\\TestController', 
            'index', 
            'GET'
        );

        $this->assertTrue($result);
    }

    // ==================== CACHE AND PERFORMANCE TESTS ====================    /** @test */
    public function it_caches_validation_results()
    {
        // Create test controller
        $this->createTestController('CacheTest', ['index' => 'cached response']);

        // First call should validate and cache
        $result1 = $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\CacheTestController', 
            'index', 
            'GET'
        );

        // Mock the validator to verify it's not called again
        $mockValidator = $this->getMockBuilder(SecurityValidator::class)
            ->setConstructorArgs([$this->app['config']->get('flashhalt.security', []), $this->cache])
            ->onlyMethods(['performValidation'])
            ->getMock();
        
        // The performValidation method should not be called
        $mockValidator->expects($this->never())
            ->method('performValidation');

        // Second call should use cache
        $result2 = $mockValidator->validateControllerMethod(
            'App\\Http\\Controllers\\CacheTestController', 
            'index', 
            'GET'
        );

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    /** @test */
    public function it_clears_validation_cache()
    {
        // Create test controller and perform validation
        $this->createTestController('ClearCache', ['index' => 'response']);
        
        // First validation to populate cache
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\ClearCacheController', 
            'index', 
            'GET'
        );        // Clear the cache
        $this->validator->clearValidationCache();
        
        // Verify cache is cleared by checking that validation runs again
        $mockValidator = $this->getMockBuilder(SecurityValidator::class)
            ->setConstructorArgs([$this->app['config']->get('flashhalt.security', []), $this->cache])
            ->onlyMethods(['performValidation'])
            ->getMock();
        
        // The performValidation method should be called again
        $mockValidator->expects($this->once())
            ->method('performValidation')
            ->willReturn(true);

        $result = $mockValidator->validateControllerMethod(
            'App\\Http\\Controllers\\ClearCacheController', 
            'index', 
            'GET'
        );

        $this->assertTrue($result);
    }

    // ==================== HTTP METHOD VALIDATION TESTS ====================

    /** @test */
    public function it_validates_http_methods()
    {
        $invalidMethods = ['INVALID', 'CUSTOM', ''];
        
        foreach ($invalidMethods as $httpMethod) {
            try {
                $this->validator->validateControllerMethod(
                    'App\\Http\\Controllers\\TestController', 
                    'index', 
                    $httpMethod
                );
                $this->fail("Expected SecurityValidationException for HTTP method: {$httpMethod}");
            } catch (SecurityValidationException $e) {
                $this->assertStringContainsString('Invalid HTTP method', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_enforces_method_specific_restrictions()
    {
        // Configure methods that require POST
        $this->withFlashHaltConfig([
            'security' => [
                'post_required_patterns' => [
                    '/^(create|store|update|delete)/',
                ]
            ]
        ]);

        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('This action requires a POST request');
        
        // Try to access a create method with GET
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'createUser', 
            'GET'
        );
    }

    // ==================== REFLECTION VALIDATION TESTS ====================

    /** @test */
    public function it_validates_method_visibility()
    {
        // Create a test controller with a protected method
        $controller = new class extends \Illuminate\Routing\Controller {
            protected function protectedMethod() {}
        };

        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('Method must be public');
        
        $this->validator->validateControllerMethod(
            get_class($controller), 
            'protectedMethod', 
            'GET'
        );
    }

    /** @test */
    public function it_validates_parameter_requirements()
    {
        // Create a test controller with a method requiring parameters
        $controller = new class extends \Illuminate\Routing\Controller {
            public function methodWithParams(string $required) {}
        };

        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('Method has required parameters');
        
        $this->validator->validateControllerMethod(
            get_class($controller), 
            'methodWithParams', 
            'GET'
        );
    }
}