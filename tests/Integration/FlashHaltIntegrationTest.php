<?php

namespace DancyCodes\FlashHalt\Tests\Integration;

use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use DancyCodes\FlashHalt\Services\RouteCompiler;
use DancyCodes\FlashHalt\Http\Middleware\FlashHaltMiddleware;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;

/**
 * Integration Tests
 * 
 * These tests verify that all FlashHALT components work together seamlessly
 * to provide the complete developer experience. Integration tests focus on
 * the interactions between services rather than testing individual components
 * in isolation.
 * 
 * Testing strategy covers:
 * - Complete request-response cycles through the middleware
 * - Service interactions and data flow
 * - Caching coordination between components
 * - Error propagation and handling across services
 * - Configuration consistency across the system
 * - Performance optimization effectiveness
 * - Development-to-production workflow transitions
 */
class FlashHaltIntegrationTest extends TestCase
{
    // ==================== FULL SYSTEM INTEGRATION TESTS ====================

    protected function setUp(): void
{
    parent::setUp();
    
    // Create controllers that might be referenced in templates during compilation tests
    $this->createTestController('Test', ['method' => 'test response']);
    $this->createTestController('Nonexistent', ['method' => 'nonexistent response']);
}

    /** @test */
    public function complete_request_cycle_works_with_all_components()
    {
        // Create a realistic controller scenario
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            use Illuminate\\Http\\Request;
            
            class IntegrationTestController extends Controller {
                public function createUser(Request $request) {
                    $validated = $request->validate([
                        "name" => "required|string|min:2",
                        "email" => "required|email"
                    ]);
                    
                    return response()->json([
                        "success" => true,
                        "user" => $validated,
                        "timestamp" => now()->toISOString()
                    ]);
                }
            }
        ');
        
        // Make a complete request through the entire FlashHALT pipeline
        $response = $this->withHeaders([
            'HX-Request' => 'true',
            'X-CSRF-TOKEN' => csrf_token(),
            'Accept' => 'application/json'
        ])->post('/hx/integration-test@createUser', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'user' => ['name', 'email'],
            'timestamp'
        ]);
        
        // Verify FlashHALT headers were added
        $response->assertHeader('X-FlashHALT-Processed', 'true');
    }

    /** @test */
    public function services_coordinate_caching_correctly()
    {
        $this->createTestController('CacheTest', ['method' => 'cached response']);
        
        // Get service instances
        $resolver = $this->app->make(ControllerResolver::class);
        $validator = $this->app->make(SecurityValidator::class);
        
        // First resolution should populate caches
        $result1 = $resolver->resolveController('cache-test@method', 'GET');
        
        // Second resolution should use cached results
        $result2 = $resolver->resolveController('cache-test@method', 'GET');
        
        $this->assertEquals($result1['class'], $result2['class']);
        $this->assertEquals($result1['method'], $result2['method']);
        
        // Verify cache statistics
        $resolverStats = $resolver->getResolutionStats();
        $validatorStats = $validator->getValidationStats();
        
        $this->assertGreaterThan(0, $resolverStats['cache_hits'] + $resolverStats['memory_hits']);
        $this->assertGreaterThan(0, $validatorStats['memory_cache_size']);
    }

    /** @test */
    public function security_validation_integrates_with_controller_resolution()
    {
        // Create a controller with both safe and dangerous methods
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class SecurityIntegrationController extends Controller {
                public function safeMethod() {
                    return "This method is safe";
                }
                
                public function __construct() {
                    // This should be blocked by security validation
                }
            }
        ');
        
        $resolver = $this->app->make(ControllerResolver::class);
        
        // Safe method should resolve successfully
        $safeResult = $resolver->resolveController('security-integration@safeMethod', 'GET');
        $this->assertEquals('safeMethod', $safeResult['method']);
        
        // Dangerous method should be blocked by security validation
        $this->expectException(\Exception::class);
        $resolver->resolveController('security-integration@__construct', 'GET');
    }

    /** @test */
    public function middleware_coordinates_with_all_services_properly()
    {
        $this->createTestController('MiddlewareIntegration', [
            'index' => 'middleware integration test'
        ]);
        
        // Create a request that will go through the middleware
        $request = Request::create('/hx/middleware-integration@index', 'GET');
        $request->headers->set('HX-Request', 'true');
        
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'middleware-integration@index');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        // Get middleware instance
        $middleware = $this->app->make(FlashHaltMiddleware::class);
        
        $response = $middleware->handle($request, function () {
            return new Response('This should not be called');
        });
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('middleware integration test', $response->getContent());
        $this->assertEquals('true', $response->headers->get('X-FlashHALT-Processed'));
    }

    /** @test */
    public function compilation_integrates_with_controller_resolution_and_validation()
    {
        // Create test templates with various patterns
        $this->createTestTemplate('integration/users', '
            <div>
                <button hx-get="hx/users@index">List Users</button>
                <button hx-post="hx/users@store">Create User</button>
                <button hx-delete="hx/users@destroy">Delete User</button>
            </div>
        ');
        
        $this->createTestTemplate('integration/admin', '
            <div>
                <button hx-get="hx/admin.dashboard@show">Admin Dashboard</button>
                <button hx-post="hx/admin.users@create">Create Admin User</button>
            </div>
        ');
        
        // Create the controllers that these templates reference
        $this->createTestController('Users', [
            'index' => 'user list',
            'store' => 'user created',
            'destroy' => 'user deleted'
        ]);
        
        eval('
            namespace App\\Http\\Controllers\\Admin;
            use Illuminate\\Routing\\Controller;
            
            class DashboardController extends Controller {
                public function show() { return "admin dashboard"; }
            }
            
            class UsersController extends Controller {
                public function create() { return "admin user created"; }
            }
        ');
        
        // Run compilation
        $compiler = $this->app->make(RouteCompiler::class);
        $result = $compiler->compile();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['statistics']['routes_discovered']);
        $this->assertEquals(5, $result['statistics']['routes_compiled']);
        
        // Verify compiled routes file was created
        $compiledPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        $this->assertFileExists($compiledPath);
        
        $compiledContent = file_get_contents($compiledPath);
        $this->assertStringContainsString('Route::get(\'users@index\'', $compiledContent);
        $this->assertStringContainsString('Route::post(\'users@store\'', $compiledContent);
        $this->assertStringContainsString('Route::delete(\'users@destroy\'', $compiledContent);
        $this->assertStringContainsString('Route::get(\'admin.dashboard@show\'', $compiledContent);
        $this->assertStringContainsString('Route::post(\'admin.users@create\'', $compiledContent);
    }

    // ==================== ERROR HANDLING INTEGRATION TESTS ====================

   /** @test */
public function error_handling_coordinates_across_all_components()
{
    // Test error propagation from security validator through resolver to middleware
    $this->createTestController('ErrorTest', ['dangerousMethod' => 'should not execute']);
    
    // Create a request to a method that will be blocked
    $request = Request::create('/hx/error-test@__construct', 'GET');
    $request->headers->set('HX-Request', 'true');
    
    // Properly set up the route with parameter binding
    $route = new Route(['GET'], 'hx/{route}', []);
    $route->bind($request);  // This is the key fix - bind the route to the request
    $route->setParameter('route', 'error-test@__construct');
    
    $request->setRouteResolver(function () use ($route) {
        return $route;
    });
    
    $middleware = $this->app->make(FlashHaltMiddleware::class);
    
    $response = $middleware->handle($request, function () {
        return new Response('Should not reach next middleware');
    });
    
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertStringNotContains('Should not reach next middleware', $response->getContent());
}

    /** @test */
    public function compilation_errors_provide_comprehensive_context()
    {
        // Create templates that reference non-existent controllers
        $this->createTestTemplate('error-test', '
            <button hx-get="hx/nonexistent@method">This will fail</button>
            <button hx-post="hx/another-missing@action">This too</button>
        ');
        
        $compiler = $this->app->make(RouteCompiler::class);
        
        try {
            $compiler->compile();
            $this->fail('Expected compilation to fail');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Route validation failed', $e->getMessage());
        }
    }

    // ==================== CONFIGURATION INTEGRATION TESTS ====================

    /** @test */
public function configuration_changes_affect_all_components_consistently()
{
    // Change security configuration
    $this->withFlashHaltConfig([
        'security' => [
            'method_blacklist' => ['customBlacklisted'],
            'enforce_http_method_semantics' => false
        ]
    ]);
    
    // Force re-binding of services to pick up new configuration
    $this->app->forgetInstance(SecurityValidator::class);
    $this->app->forgetInstance(ControllerResolver::class);
    
    // Create new service instances with updated configuration
    $newValidator = $this->app->make(SecurityValidator::class);
    $newResolver = $this->app->make(ControllerResolver::class);
    
    // Create a controller with the custom blacklisted method
    eval('
        namespace App\\Http\\Controllers;
        use Illuminate\\Routing\\Controller;
        
        class ConfigTestController extends Controller {
            public function customBlacklisted() {
                return "should be blocked";
            }
            
            public function allowedMethod() {
                return "should be allowed";
            }
        }
    ');
    
    // The custom blacklisted method should be blocked
    $this->expectException(\Exception::class);
    $newResolver->resolveController('config-test@customBlacklisted', 'GET');
}

    /** @test */
    public function cache_configuration_affects_all_caching_components()
    {
        // Configure short cache TTL
        $this->withFlashHaltConfig([
            'development' => ['cache_ttl' => 1] // 1 second
        ]);
        
        $this->createTestController('CacheConfig', ['method' => 'cached']);
        
        $resolver = $this->app->make(ControllerResolver::class);
        
        // First resolution
        $result1 = $resolver->resolveController('cache-config@method', 'GET');
        
        // Wait for cache to expire
        sleep(2);
        
        // Second resolution should not use cache due to expiration
        $result2 = $resolver->resolveController('cache-config@method', 'GET');
        
        $stats = $resolver->getResolutionStats();
        $this->assertGreaterThan(0, $stats['cache_misses']);
    }

    // ==================== PERFORMANCE INTEGRATION TESTS ====================

    /** @test */
    public function caching_improves_performance_across_multiple_requests()
    {
        $this->createTestController('Performance', ['test' => 'performance test']);
        
        $resolver = $this->app->make(ControllerResolver::class);
        
        // Measure first resolution (should be slower due to cache miss)
        $start1 = microtime(true);
        $resolver->resolveController('performance@test', 'GET');
        $time1 = microtime(true) - $start1;
        
        // Measure second resolution (should be faster due to cache hit)
        $start2 = microtime(true);
        $resolver->resolveController('performance@test', 'GET');
        $time2 = microtime(true) - $start2;
        
        // Second resolution should be significantly faster
        $this->assertLessThan($time1, $time2);
        
        $stats = $resolver->getResolutionStats();
        $this->assertGreaterThan(0, $stats['cache_hits'] + $stats['memory_hits']);
    }

    /** @test */
    public function memory_cache_coordinates_with_persistent_cache()
    {
        $this->createTestController('MemoryCache', ['method' => 'memory test']);
        
        $resolver = $this->app->make(ControllerResolver::class);
        
        // First resolution populates both caches
        $resolver->resolveController('memory-cache@method', 'GET');
        
        // Second resolution should use memory cache
        $resolver->resolveController('memory-cache@method', 'GET');
        
        // Third resolution should also use memory cache
        $resolver->resolveController('memory-cache@method', 'GET');
        
        $stats = $resolver->getResolutionStats();
        $this->assertGreaterThan(0, $stats['memory_hits']);
        $this->assertGreaterThan(0, $stats['memory_cache_size']);
    }

    // ==================== DEVELOPMENT TO PRODUCTION WORKFLOW TESTS ====================

    /** @test */
    public function development_to_production_workflow_maintains_functionality()
    {
        // Step 1: Create a working development setup
        $this->createTestTemplate('workflow-test', '
            <button hx-get="hx/workflow@index">Development Route</button>
            <button hx-post="hx/workflow@store">Create Item</button>
        ');
        
        $this->createTestController('Workflow', [
            'index' => 'development response',
            'store' => 'item created'
        ]);
        
        // Step 2: Test development mode functionality
        $this->withFlashHaltConfig(['mode' => 'development']);
        
        $devResponse = $this->withHeaders(['HX-Request' => 'true'])
                            ->get('/hx/workflow@index');
        
        $devResponse->assertStatus(200);
        $devResponse->assertSee('development response');
        
        // Step 3: Compile for production
        $compiler = $this->app->make(RouteCompiler::class);
        $compilationResult = $compiler->compile();
        
        $this->assertTrue($compilationResult['success']);
        $this->assertEquals(2, $compilationResult['statistics']['routes_compiled']);
        
        // Step 4: Switch to production mode and test same functionality
        $this->withFlashHaltConfig(['mode' => 'production']);
        
        // Re-register routes in production mode
        $this->app->make(\DancyCodes\FlashHalt\FlashHaltServiceProvider::class)->boot();
        
        $prodResponse = $this->withHeaders(['HX-Request' => 'true'])
                             ->get('/hx/workflow@index');
        
        $prodResponse->assertStatus(200);
        $prodResponse->assertSee('development response');
    }

    /** @test */
    public function route_compilation_maintains_all_controller_features()
    {
        // Create a controller with various Laravel features
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            use Illuminate\\Http\\Request;
            
            class FeatureTestController extends Controller {
                public function __construct() {
                    $this->middleware("web");
                }
                
                public function withValidation(Request $request) {
                    $validated = $request->validate(["name" => "required"]);
                    return response()->json($validated);
                }
                
                public function withDependencyInjection(\Illuminate\Cache\CacheManager $cache) {
                    $cache->put("di_test", "success", 60);
                    return "DI works";
                }
                
                public function withAuthorization() {
                    $this->authorize("admin");
                    return "authorized";
                }
            }
        ');
        
        $this->createTestTemplate('feature-test', '
            <button hx-post="hx/feature-test@withValidation">Validation Test</button>
            <button hx-get="hx/feature-test@withDependencyInjection">DI Test</button>
        ');
        
        // Compile routes
        $compiler = $this->app->make(RouteCompiler::class);
        $result = $compiler->compile();
        
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['statistics']['routes_compiled']);
        
        // Test that compiled routes maintain Laravel features
        $compiledPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        $compiledContent = file_get_contents($compiledPath);
        
        $this->assertStringContainsString('FeatureTestController::class', $compiledContent);
        $this->assertStringContainsString('withValidation', $compiledContent);
        $this->assertStringContainsString('withDependencyInjection', $compiledContent);
    }

    // ==================== ADVANCED INTEGRATION SCENARIOS ====================

    /** @test */
    public function nested_namespace_resolution_works_with_compilation()
    {
        // Create deeply nested controllers
        eval('
            namespace App\\Http\\Controllers\\Api\\V2\\Admin;
            use Illuminate\\Routing\\Controller;
            
            class ReportsController extends Controller {
                public function analytics() { return "v2 analytics"; }
                public function metrics() { return "v2 metrics"; }
            }
        ');
        
        $this->createTestTemplate('nested-test', '
            <button hx-get="hx/api.v2.admin.reports@analytics">Analytics</button>
            <button hx-post="hx/api.v2.admin.reports@metrics">Metrics</button>
        ');
        
        // Test resolution works
        $resolver = $this->app->make(ControllerResolver::class);
        $result = $resolver->resolveController('api.v2.admin.reports@analytics', 'GET');
        
        $this->assertEquals('App\\Http\\Controllers\\Api\\V2\\Admin\\ReportsController', $result['class']);
        $this->assertEquals('analytics', $result['method']);
        
        // Test compilation preserves nested structure
        $compiler = $this->app->make(RouteCompiler::class);
        $compilationResult = $compiler->compile();
        
        $this->assertTrue($compilationResult['success']);
        
        $compiledContent = file_get_contents($this->app['config']->get('flashhalt.production.compiled_routes_path'));
        $this->assertStringContainsString('api.v2.admin.reports@analytics', $compiledContent);
        $this->assertStringContainsString('App\\Http\\Controllers\\Api\\V2\\Admin\\ReportsController', $compiledContent);
    }

    /** @test */
    public function error_context_flows_through_entire_system()
    {
        // Create a scenario that will generate detailed error context
        $this->createTestTemplate('error-context-test', '
            <button hx-get="hx/complex.namespace.controller@nonExistentMethod">Will Fail</button>
        ');
        
        eval('
            namespace App\\Http\\Controllers\\Complex\\Namespace;
            use Illuminate\\Routing\\Controller;
            
            class ControllerController extends Controller {
                public function existingMethod() { return "exists"; }
                // Note: nonExistentMethod is not defined
            }
        ');
        
        // Test that error context includes comprehensive information
        $resolver = $this->app->make(ControllerResolver::class);
        
        try {
            $resolver->resolveController('complex.namespace.controller@nonExistentMethod', 'GET');
            $this->fail('Expected resolution to fail');
        } catch (\Exception $e) {
            $this->assertStringContainsString('nonExistentMethod', $e->getMessage());
            $this->assertStringContainsString('Complex\\Namespace\\ControllerController', $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files and cache
        $compiledPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        if (file_exists($compiledPath)) {
            unlink($compiledPath);
        }
        
        // Clear caches to prevent test interference
        $this->app['cache']->flush();
        
        parent::tearDown();
    }
}