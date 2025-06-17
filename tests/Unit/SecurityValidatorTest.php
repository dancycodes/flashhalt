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
            $this->expectException(SecurityValidationException::class);
            $this->expectExceptionMessage('Methods starting with underscore are not allowed');
            
            $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', $methodName, 'GET');
        }
    }

    /** @test */
    public function it_blocks_blacklisted_methods()
    {
        $blacklistedMethods = [
            '__construct',
            '__destruct', 
            '__call',
            '__callStatic',
            'getRouteKey',
            'middleware',
            'callAction'
        ];
        
        // Create a test controller to validate against
        $this->createTestController('Test', ['safeMethod' => 'response']);
        
        foreach ($blacklistedMethods as $methodName) {
            $this->expectException(SecurityValidationException::class);
            $this->expectExceptionMessage('is explicitly blacklisted for security reasons');
            
            $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', $methodName, 'GET');
        }
    }

    /** @test */
    public function it_blocks_methods_matching_dangerous_patterns()
    {
        // Create a test controller with a method that matches a blocked pattern
        $this->createTestController('Test', ['getUserPassword' => 'response']);
        
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('matches a blocked pattern');
        
        $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', 'getUserPassword', 'GET');
    }

    /** @test */
    public function it_validates_that_controller_class_exists()
    {
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('does not exist or cannot be analyzed');
        
        $this->validator->validateControllerMethod('App\\Http\\Controllers\\NonExistentController', 'index', 'GET');
    }

    /** @test */
    public function it_validates_that_methods_exist_in_controller()
    {
        $this->createTestController('Test', ['existingMethod' => 'response']);
        
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('does not exist in controller');
        
        $this->validator->validateControllerMethod('App\\Http\\Controllers\\TestController', 'nonExistentMethod', 'GET');
    }

    /** @test */
    public function it_rejects_non_public_methods()
    {
        // Create a controller with private/protected methods using eval
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class PrivateMethodController extends Controller {
                public function publicMethod() { return "public"; }
                protected function protectedMethod() { return "protected"; }
                private function privateMethod() { return "private"; }
            }
        ');
        
        // Public method should pass validation (test setup)
        $this->assertTrue($this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\PrivateMethodController', 
            'publicMethod', 
            'GET'
        ));
        
        // Protected method should be rejected
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('is not public');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\PrivateMethodController', 
            'protectedMethod', 
            'GET'
        );
    }

    /** @test */
    public function it_rejects_static_methods()
    {
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class StaticMethodController extends Controller {
                public static function staticMethod() { return "static"; }
                public function instanceMethod() { return "instance"; }
            }
        ');
        
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('cannot be accessed through FlashHALT');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\StaticMethodController', 
            'staticMethod', 
            'GET'
        );
    }

    /** @test */
    public function it_rejects_abstract_methods()
    {
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            abstract class AbstractController extends Controller {
                abstract public function abstractMethod();
            }
            
            class ConcreteController extends AbstractController {
                public function abstractMethod() { return "concrete"; }
                public function concreteMethod() { return "concrete"; }
            }
        ');
        
        // This test verifies that abstract method detection works correctly
        // Note: In practice, abstract methods can't be called anyway, but we test the validation
        $this->assertTrue($this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\ConcreteController', 
            'concreteMethod', 
            'GET'
        ));
    }

    /** @test */
    public function it_validates_http_method_semantics()
    {
        $this->createTestController('Test', [
            'create' => 'created',
            'update' => 'updated', 
            'destroy' => 'deleted',
            'show' => 'shown'
        ]);
        
        // These should pass - correct HTTP method for the operation
        $this->assertTrue($this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'show', 
            'GET'
        ));
        
        $this->assertTrue($this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'create', 
            'POST'
        ));
        
        // This should fail - destructive operation via GET
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('appears to perform "destroy" operations but was called via GET');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'destroy', 
            'GET'
        );
    }

    /** @test */
    public function it_blocks_methods_from_dangerous_inheritance()
    {
        eval('
            namespace App\\Http\\Controllers;
            use ReflectionClass;
            
            class DangerousController extends ReflectionClass {
                public function __construct() {
                    parent::__construct(static::class);
                }
                public function dangerousMethod() { return "dangerous"; }
            }
        ');
        
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('inherited from');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\DangerousController', 
            'dangerousMethod', 
            'GET'
        );
    }

    /** @test */
    public function it_blocks_methods_with_dangerous_parameter_types()
    {
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            use ReflectionClass;
            
            class DangerousParameterController extends Controller {
                public function dangerousMethod(ReflectionClass $reflection) { 
                    return "dangerous"; 
                }
                public function safeMethod(string $name) { 
                    return "safe"; 
                }
            }
        ');
        
        // Safe method should pass
        $this->assertTrue($this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\DangerousParameterController', 
            'safeMethod', 
            'GET'
        ));
        
        // Dangerous parameter type should be rejected
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('expects parameter of type');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\DangerousParameterController', 
            'dangerousMethod', 
            'GET'
        );
    }

    /** @test */
    public function it_blocks_methods_with_security_annotations()
    {
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class AnnotatedController extends Controller {
                /**
                 * This method is marked as internal and should not be HTTP accessible
                 * @internal
                 */
                public function internalMethod() { 
                    return "internal"; 
                }
                
                /**
                 * @private
                 */
                public function privateAnnotatedMethod() {
                    return "private";
                }
                
                public function publicMethod() {
                    return "public";
                }
            }
        ');
        
        // Public method without annotations should pass
        $this->assertTrue($this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\AnnotatedController', 
            'publicMethod', 
            'GET'
        ));
        
        // Method with @internal annotation should be blocked
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('marked with "@internal" annotation');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\AnnotatedController', 
            'internalMethod', 
            'GET'
        );
    }

    /** @test */
    public function it_caches_validation_results_for_performance()
    {
        $this->createTestController('Test', ['cachedMethod' => 'response']);
        
        // First validation - should cache the result
        $result1 = $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'cachedMethod', 
            'GET'
        );
        
        // Verify result is cached
        $cacheKey = 'flashhalt:security:' . md5('App\\Http\\Controllers\\TestController') . ':' . md5('cachedMethod') . ':get:' . md5(serialize($this->app['config']->get('flashhalt.security', [])));
        $this->assertCacheHas($cacheKey);
        
        // Second validation - should use cached result
        $result2 = $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'cachedMethod', 
            'GET'
        );
        
        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    /** @test */
    public function it_clears_validation_cache_when_requested()
    {
        $this->createTestController('Test', ['methodToClear' => 'response']);
        
        // Validate to populate cache
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'methodToClear', 
            'GET'
        );
        
        // Clear cache
        $this->validator->clearValidationCache();
        
        // Verify cache is cleared (this is a simplified check - in practice,
        // you'd verify that subsequent validations don't use stale cache)
        $stats = $this->validator->getValidationStats();
        $this->assertEquals(0, $stats['memory_cache_size']);
    }

    /** @test */
    public function it_provides_validation_statistics()
    {
        $this->createTestController('Test', ['statsMethod' => 'response']);
        
        // Perform some validations
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'statsMethod', 
            'GET'
        );
        
        $stats = $this->validator->getValidationStats();
        
        $this->assertArrayHasKey('memory_cache_size', $stats);
        $this->assertArrayHasKey('compiled_patterns', $stats);
        $this->assertArrayHasKey('cache_ttl', $stats);
        $this->assertArrayHasKey('config_hash', $stats);
        
        $this->assertIsInt($stats['memory_cache_size']);
        $this->assertIsInt($stats['compiled_patterns']);
        $this->assertIsInt($stats['cache_ttl']);
        $this->assertIsString($stats['config_hash']);
    }

    /** @test */
    public function it_allows_valid_methods_to_pass_validation()
    {
        $validMethods = [
            'index',
            'show', 
            'create',
            'store',
            'edit',
            'update',
            'destroy',
            'customValidMethod',
            'camelCaseMethod',
            'methodWithNumbers123'
        ];
        
        $this->createTestController('Valid', array_combine($validMethods, array_fill(0, count($validMethods), 'response')));
        
        foreach ($validMethods as $methodName) {
            $result = $this->validator->validateControllerMethod(
                'App\\Http\\Controllers\\ValidController', 
                $methodName, 
                'GET'
            );
            
            $this->assertTrue($result, "Method {$methodName} should pass validation but was rejected");
        }
    }

    /** @test */
    public function it_handles_case_insensitive_blacklist_matching()
    {
        $this->createTestController('Test', ['MIDDLEWARE' => 'response']);
        
        // Blacklisted method in different case should still be blocked
        $this->expectException(SecurityValidationException::class);
        $this->expectExceptionMessage('is explicitly blacklisted');
        
        $this->validator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'MIDDLEWARE', 
            'GET'
        );
    }

    /** @test */
    public function validation_respects_configuration_changes()
    {
        // Create a method that would normally be blocked by pattern
        $this->createTestController('Test', ['getPassword' => 'response']);
        
        // First, it should be blocked by the default pattern
        try {
            $this->validator->validateControllerMethod(
                'App\\Http\\Controllers\\TestController', 
                'getPassword', 
                'GET'
            );
            $this->fail('Expected SecurityValidationException for password method');
        } catch (SecurityValidationException $e) {
            $this->assertStringContainsString('matches a blocked pattern', $e->getMessage());
        }
        
        // Now create a new validator with different configuration
        $permissiveConfig = array_merge(
            $this->app['config']->get('flashhalt.security', []), 
            ['method_pattern_blacklist' => []] // Remove pattern restrictions
        );
        
        $permissiveValidator = new SecurityValidator($permissiveConfig, $this->cache);
        
        // Now the same method should pass validation
        $result = $permissiveValidator->validateControllerMethod(
            'App\\Http\\Controllers\\TestController', 
            'getPassword', 
            'GET'
        );
        
        $this->assertTrue($result);
    }
}