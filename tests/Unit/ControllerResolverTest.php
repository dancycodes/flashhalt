<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Exceptions\ControllerResolutionException;
use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Mockery;

/**
 * ControllerResolver Unit Tests
 * 
 * These tests verify that the ControllerResolver service correctly maps
 * FlashHALT route patterns to Laravel controller classes and methods.
 * The resolver handles complex namespace resolution, controller instantiation
 * through Laravel's service container, and caching for performance.
 * 
 * The testing strategy covers:
 * - Route pattern parsing and validation
 * - Namespace resolution with various patterns
 * - Controller class discovery and validation
 * - Method existence and accessibility checking
 * - Service container integration for dependency injection
 * - Caching behavior and performance optimization
 * - Error handling for various failure scenarios
 */
class ControllerResolverTest extends TestCase
{
    private ControllerResolver $resolver;
    private SecurityValidator $securityValidator;
    private Repository $cache;

    /**
     * Set up the ControllerResolver with mocked dependencies for each test.
     * We use mocking for SecurityValidator to isolate the ControllerResolver's
     * behavior from security validation logic.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a fresh cache instance for each test
        $this->cache = new Repository(new ArrayStore());
        
        // Mock the SecurityValidator to focus on resolution logic
        $this->securityValidator = Mockery::mock(SecurityValidator::class);
        
        // Create the ControllerResolver instance
        $this->resolver = new ControllerResolver(
            $this->securityValidator, 
            $this->cache, 
            $this->app['config']->get('flashhalt', [])
        );
    }

    /** @test */
    public function it_resolves_simple_controller_patterns()
    {
        // Create a test controller that can be resolved
        $this->createTestController('Users', ['index' => 'user list']);
        
        // Mock security validation to pass
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\UsersController', 'index', 'GET')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('users@index', 'GET');
        
        $this->assertArrayHasKey('controller', $result);
        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('class', $result);
        $this->assertArrayHasKey('pattern', $result);
        
        $this->assertInstanceOf('App\\Http\\Controllers\\UsersController', $result['controller']);
        $this->assertEquals('index', $result['method']);
        $this->assertEquals('App\\Http\\Controllers\\UsersController', $result['class']);
        $this->assertEquals('users@index', $result['pattern']);
    }

    /** @test */
    public function it_resolves_namespaced_controller_patterns()
    {
        // Create a namespaced test controller
        eval('
            namespace App\\Http\\Controllers\\Admin;
            use Illuminate\\Routing\\Controller;
            
            class UsersController extends Controller {
                public function index() { return "admin users"; }
            }
        ');
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\Admin\\UsersController', 'index', 'GET')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('admin.users@index', 'GET');
        
        $this->assertInstanceOf('App\\Http\\Controllers\\Admin\\UsersController', $result['controller']);
        $this->assertEquals('App\\Http\\Controllers\\Admin\\UsersController', $result['class']);
    }

    /** @test */
    public function it_resolves_deeply_nested_namespaces()
    {
        // Create a deeply nested controller
        eval('
            namespace App\\Http\\Controllers\\Api\\V1\\Admin;
            use Illuminate\\Routing\\Controller;
            
            class ReportsController extends Controller {
                public function generate() { return "report"; }
            }
        ');
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\Api\\V1\\Admin\\ReportsController', 'generate', 'POST')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('api.v1.admin.reports@generate', 'POST');
        
        $this->assertEquals('App\\Http\\Controllers\\Api\\V1\\Admin\\ReportsController', $result['class']);
        $this->assertEquals('generate', $result['method']);
    }

    /** @test */
    public function it_handles_controllers_without_controller_suffix()
    {
        // Create a controller without "Controller" suffix
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class Dashboard extends Controller {
                public function show() { return "dashboard"; }
            }
        ');
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\Dashboard', 'show', 'GET')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('dashboard@show', 'GET');
        
        $this->assertEquals('App\\Http\\Controllers\\Dashboard', $result['class']);
    }

    /** @test */
    public function it_prefers_controller_suffix_when_both_exist()
    {
        // Create both versions to test precedence
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class Post extends Controller {
                public function show() { return "post without suffix"; }
            }
            
            class PostController extends Controller {
                public function show() { return "post with suffix"; }
            }
        ');
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\PostController', 'show', 'GET')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('post@show', 'GET');
        
        // Should prefer the Controller-suffixed version
        $this->assertEquals('App\\Http\\Controllers\\PostController', $result['class']);
    }

    /** @test */
    public function it_converts_kebab_case_to_pascal_case()
    {
        // Create controller with PascalCase name
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class UserProfileController extends Controller {
                public function edit() { return "edit profile"; }
            }
        ');
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\UserProfileController', 'edit', 'GET')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('user-profile@edit', 'GET');
        
        $this->assertEquals('App\\Http\\Controllers\\UserProfileController', $result['class']);
    }

    /** @test */
    public function it_handles_snake_case_to_pascal_case_conversion()
    {
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class ApiTokenController extends Controller {
                public function refresh() { return "token refreshed"; }
            }
        ');
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\ApiTokenController', 'refresh', 'POST')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('api_token@refresh', 'POST');
        
        $this->assertEquals('App\\Http\\Controllers\\ApiTokenController', $result['class']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_route_patterns()
    {
        $invalidPatterns = [
            '',
            'controller-without-method',
            'controller@',
            '@method-without-controller',
            'controller@@double-at',
            'controller@method@extra',
        ];
        
        foreach ($invalidPatterns as $pattern) {
            $this->expectException(ControllerResolutionException::class);
            $this->resolver->resolveController($pattern, 'GET');
        }
    }

    /** @test */
    public function it_throws_exception_for_overly_long_patterns()
    {
        $longPattern = str_repeat('a', 150) . '@' . str_repeat('b', 150);
        
        $this->expectException(ControllerResolutionException::class);
        $this->expectExceptionMessage('exceeds maximum allowed length');
        
        $this->resolver->resolveController($longPattern, 'GET');
    }

    /** @test */
    public function it_throws_exception_for_patterns_with_invalid_characters()
    {
        $invalidPatterns = [
            'controller/with/slashes@method',
            'controller with spaces@method',
            'controller@method/with/slashes',
            'controller@method with spaces',
            'controller$@method',
            'controller@method%',
        ];
        
        foreach ($invalidPatterns as $pattern) {
            $this->expectException(ControllerResolutionException::class);
            $this->expectExceptionMessage('contains invalid characters');
            
            $this->resolver->resolveController($pattern, 'GET');
        }
    }

    /** @test */
    public function it_throws_exception_when_controller_not_found()
    {
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->never(); // Security validation shouldn't be called if controller doesn't exist
        
        $this->expectException(ControllerResolutionException::class);
        $this->expectExceptionMessage('Could not resolve controller for path "nonexistent"');
        
        $this->resolver->resolveController('nonexistent@method', 'GET');
    }

    /** @test */
    public function it_provides_helpful_error_messages_with_attempted_class_names()
    {
        try {
            $this->resolver->resolveController('missing@method', 'GET');
            $this->fail('Expected ControllerResolutionException');
        } catch (ControllerResolutionException $e) {
            $this->assertStringContainsString('Tried these class names:', $e->getMessage());
            $this->assertStringContainsString('App\\Http\\Controllers\\MissingController', $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_controller_inheritance()
    {
        // Create a class that doesn't extend Controller
        eval('
            namespace App\\Http\\Controllers;
            
            class NotAController {
                public function index() { return "not a controller"; }
            }
        ');
        
        $this->expectException(ControllerResolutionException::class);
        $this->expectExceptionMessage('does not inherit from Laravel\'s base Controller class');
        
        $this->resolver->resolveController('not-a@index', 'GET');
    }

    /** @test */
public function it_handles_controller_instantiation_through_service_container()
{
    // Create a controller that requires dependency injection
    eval('
        namespace App\\Http\\Controllers;
        use Illuminate\\Routing\\Controller;
        use Illuminate\\Http\\Request;
        
        class DependencyController extends Controller {
            public $request; // Changed from private to public
            
            public function __construct(Request $request) {
                $this->request = $request;
            }
            
            public function show() { 
                return "dependency injected"; 
            }
        }
    ');
    
    $this->securityValidator->shouldReceive('validateControllerMethod')
        ->with('App\\Http\\Controllers\\DependencyController', 'show', 'GET')
        ->andReturn(true);
    
    $result = $this->resolver->resolveController('dependency@show', 'GET');
    
    $this->assertInstanceOf('App\\Http\\Controllers\\DependencyController', $result['controller']);
    // Verify that the controller was instantiated with proper dependency injection
    $this->assertInstanceOf('Illuminate\\Http\\Request', $result['controller']->request ?? null);
}

    /** @test */
    public function it_throws_exception_when_controller_instantiation_fails()
    {
        // Create a controller with unresolvable dependencies
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class UnresolvableController extends Controller {
                public function __construct(NonExistentDependency $dependency) {
                    // This will fail during instantiation
                }
                
                public function index() { return "unreachable"; }
            }
        ');
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\UnresolvableController', 'index', 'GET')
            ->andReturn(true);
        
        $this->expectException(ControllerResolutionException::class);
        $this->expectExceptionMessage('Failed to instantiate controller');
        
        $this->resolver->resolveController('unresolvable@index', 'GET');
    }

    /** @test */
    public function it_caches_resolution_results_for_performance()
    {
        $this->createTestController('Cached', ['method' => 'response']);
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\CachedController', 'method', 'GET')
            ->once() // Should only be called once due to caching
            ->andReturn(true);
        
        // First resolution - should cache the result
        $result1 = $this->resolver->resolveController('cached@method', 'GET');
        
        // Second resolution - should use cached result
        $result2 = $this->resolver->resolveController('cached@method', 'GET');
        
        $this->assertEquals($result1['class'], $result2['class']);
        $this->assertEquals($result1['method'], $result2['method']);
        
        // Verify cache statistics
        $stats = $this->resolver->getResolutionStats();
        $this->assertGreaterThan(0, $stats['cache_hits'] + $stats['memory_hits']);
    }

    /** @test */
    public function it_respects_controller_whitelist_when_configured()
    {
        $this->createTestController('Allowed', ['index' => 'allowed']);
        $this->createTestController('Blocked', ['index' => 'blocked']);
        
        // Configure whitelist
        $this->withFlashHaltConfig([
            'development' => [
                'allowed_controllers' => ['AllowedController']
            ]
        ]);
        
        // Recreate resolver with new configuration
        $this->resolver = new ControllerResolver(
            $this->securityValidator, 
            $this->cache, 
            $this->app['config']->get('flashhalt', [])
        );
        
        // Allowed controller should work
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\AllowedController', 'index', 'GET')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('allowed@index', 'GET');
        $this->assertEquals('App\\Http\\Controllers\\AllowedController', $result['class']);
        
        // Blocked controller should be rejected
        $this->expectException(ControllerResolutionException::class);
        $this->expectExceptionMessage('is not in the allowed controllers list');
        
        $this->resolver->resolveController('blocked@index', 'GET');
    }

    /** @test */
    public function it_handles_security_validation_failures()
    {
        $this->createTestController('Insecure', ['dangerousMethod' => 'danger']);
        
        // Mock security validation to fail
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\InsecureController', 'dangerousMethod', 'GET')
            ->andThrow(new \Exception('Security validation failed'));
        
        $this->expectException(ControllerResolutionException::class);
        $this->expectExceptionMessage('Security validation failed');
        
        $this->resolver->resolveController('insecure@dangerousMethod', 'GET');
    }

    /** @test */
    public function it_clears_resolution_cache_when_requested()
    {
        $this->createTestController('Clear', ['method' => 'response']);
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\ClearController', 'method', 'GET')
            ->andReturn(true);
        
        // Perform resolution to populate cache
        $this->resolver->resolveController('clear@method', 'GET');
        
        // Clear cache
        $this->resolver->clearResolutionCache();
        
        // Verify cache is cleared
        $stats = $this->resolver->getResolutionStats();
        $this->assertEquals(0, $stats['memory_cache_size']);
    }

    /** @test */
    public function it_provides_comprehensive_resolution_statistics()
    {
        $this->createTestController('Stats', ['method' => 'response']);
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\StatsController', 'method', 'GET')
            ->andReturn(true);
        
        // Perform some resolutions
        $this->resolver->resolveController('stats@method', 'GET');
        $this->resolver->resolveController('stats@method', 'GET'); // Cache hit
        
        $stats = $this->resolver->getResolutionStats();
        
        $this->assertArrayHasKey('cache_hits', $stats);
        $this->assertArrayHasKey('cache_misses', $stats);
        $this->assertArrayHasKey('memory_hits', $stats);
        $this->assertArrayHasKey('resolution_attempts', $stats);
        $this->assertArrayHasKey('successful_resolutions', $stats);
        $this->assertArrayHasKey('cache_hit_ratio', $stats);
        $this->assertArrayHasKey('memory_cache_size', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        
        $this->assertGreaterThan(0, $stats['resolution_attempts']);
        $this->assertGreaterThan(0, $stats['successful_resolutions']);
        $this->assertIsFloat($stats['cache_hit_ratio']);
        $this->assertIsFloat($stats['success_rate']);
    }

    /** @test */
    public function it_handles_alternative_controller_namespaces()
    {
        // Create controller in alternative namespace
        eval('
            namespace App\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class AlternativeController extends Controller {
                public function index() { return "alternative namespace"; }
            }
        ');
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Controllers\\AlternativeController', 'index', 'GET')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('alternative@index', 'GET');
        
        $this->assertEquals('App\\Controllers\\AlternativeController', $result['class']);
    }

    /** @test */
    public function it_handles_pattern_matching_for_controller_whitelist()
    {
        $this->createTestController('Admin', ['index' => 'admin']);
        
        // Configure whitelist with wildcard pattern
        $this->withFlashHaltConfig([
            'development' => [
                'allowed_controllers' => ['Admin*']
            ]
        ]);
        
        $this->resolver = new ControllerResolver(
            $this->securityValidator, 
            $this->cache, 
            $this->app['config']->get('flashhalt', [])
        );
        
        $this->securityValidator->shouldReceive('validateControllerMethod')
            ->with('App\\Http\\Controllers\\AdminController', 'index', 'GET')
            ->andReturn(true);
        
        $result = $this->resolver->resolveController('admin@index', 'GET');
        
        $this->assertEquals('App\\Http\\Controllers\\AdminController', $result['class']);
    }

    /**
     * Clean up Mockery after each test to prevent memory leaks.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}