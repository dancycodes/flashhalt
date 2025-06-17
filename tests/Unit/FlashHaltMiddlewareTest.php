<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Exceptions\ControllerResolutionException;
use DancyCodes\FlashHalt\Exceptions\SecurityValidationException;
use DancyCodes\FlashHalt\Http\Middleware\FlashHaltMiddleware;
use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Mockery;

/**
 * FlashHaltMiddleware Unit Tests
 * 
 * These tests verify that the FlashHaltMiddleware correctly orchestrates
 * the FlashHALT request processing pipeline. The middleware is responsible
 * for identifying FlashHALT requests, delegating to the appropriate services,
 * and formatting responses for optimal HTMX compatibility.
 * 
 * The testing strategy covers:
 * - Request analysis and FlashHALT route detection
 * - Service orchestration and error handling
 * - Response processing and HTMX optimization
 * - Performance monitoring and debugging features
 * - Error handling with environment-aware messaging
 * - Integration with Laravel's middleware pipeline
 */
class FlashHaltMiddlewareTest extends TestCase
{
    private FlashHaltMiddleware $middleware;
    private ControllerResolver $controllerResolver;

    /**
     * Set up the FlashHaltMiddleware with mocked dependencies for each test.
     * We mock the ControllerResolver to isolate middleware behavior from
     * the complex controller resolution logic.
     */
    protected function setUp(): void
{
    parent::setUp();
    
    // Set up session for CSRF tests
    $this->startSession();
    
    // Create a fresh cache instance for each test
    $this->cache = new Repository(new ArrayStore());
    
    // Mock the ControllerResolver to isolate middleware behavior
    $this->controllerResolver = Mockery::mock(ControllerResolver::class);
    
    // Create the FlashHaltMiddleware instance
    $this->middleware = new FlashHaltMiddleware($this->controllerResolver);
}

    /** @test */
    public function it_passes_non_flashhalt_requests_to_next_middleware()
    {
        $request = Request::create('/normal-route', 'GET');
        $nextCalled = false;
        
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('next middleware response');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertTrue($nextCalled);
        $this->assertEquals('next middleware response', $response->getContent());
    }

    /** @test */
    public function it_processes_valid_flashhalt_get_requests()
    {
        // Create a test controller to resolve to
        $testController = $this->createTestController('Test', ['index' => 'test response']);
        
        // Create a FlashHALT request
        $request = $this->createFlashHaltRequest('test@index', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'test@index');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        // Mock successful controller resolution
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('test@index', 'GET')
            ->andReturn([
                'controller' => new \App\Http\Controllers\TestController(),
                'method' => 'index',
                'class' => 'App\\Http\\Controllers\\TestController',
                'pattern' => 'test@index',
            ]);
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('test response', $response->getContent());
        $this->assertEquals('true', $response->headers->get('X-FlashHALT-Processed'));
    }

    /** @test */
    public function it_processes_valid_flashhalt_post_requests()
    {
        $this->createTestController('Test', ['store' => 'created']);
        
        $request = $this->createFlashHaltRequest('test@store', 'POST', ['name' => 'John']);
        $route = new Route(['POST'], 'hx/{route}', []);
        $route->setParameter('route', 'test@store');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('test@store', 'POST')
            ->andReturn([
                'controller' => new \App\Http\Controllers\TestController(),
                'method' => 'store',
                'class' => 'App\\Http\\Controllers\\TestController',
                'pattern' => 'test@store',
            ]);
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('created', $response->getContent());
    }

    /** @test */
    public function it_handles_controller_resolution_exceptions_in_development()
    {
        $request = $this->createFlashHaltRequest('nonexistent@method', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'nonexistent@method');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        // Configure for development mode to get detailed errors
        $this->withFlashHaltConfig([
            'development' => ['debug_mode' => true]
        ]);
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('nonexistent@method', 'GET')
            ->andThrow(new ControllerResolutionException(
                'Controller not found', 
                'CONTROLLER_NOT_FOUND',
                'nonexistent@method'
            ));
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Controller Resolution Failed', $response->getContent());
        $this->assertStringContainsString('Controller not found', $response->getContent());
        $this->assertEquals('text/html', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function it_handles_security_validation_exceptions_in_development()
    {
        $request = $this->createFlashHaltRequest('test@dangerous', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'test@dangerous');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        $this->withFlashHaltConfig([
            'development' => ['debug_mode' => true]
        ]);
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('test@dangerous', 'GET')
            ->andThrow(new SecurityValidationException(
                'Method is blacklisted',
                'METHOD_BLACKLISTED'
            ));
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Security Validation Failed', $response->getContent());
        $this->assertStringContainsString('Method is blacklisted', $response->getContent());
    }

    /** @test */
    public function it_provides_minimal_error_information_in_production()
    {
        $request = $this->createFlashHaltRequest('test@dangerous', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'test@dangerous');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        // Configure for production mode
        $this->withFlashHaltConfig([
            'development' => ['debug_mode' => false]
        ]);
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('test@dangerous', 'GET')
            ->andThrow(new SecurityValidationException(
                'Method is blacklisted',
                'METHOD_BLACKLISTED'
            ));
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Forbidden', $response->getContent());
        $this->assertStringNotContains('Method is blacklisted', $response->getContent());
    }

    /** @test */
    public function it_handles_unexpected_exceptions_gracefully()
    {
        $request = $this->createFlashHaltRequest('test@method', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'test@method');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        $this->withFlashHaltConfig([
            'development' => ['debug_mode' => true]
        ]);
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('test@method', 'GET')
            ->andThrow(new \RuntimeException('Unexpected error occurred'));
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('Unexpected Error', $response->getContent());
        $this->assertStringContainsString('Unexpected error occurred', $response->getContent());
    }

    /** @test */
    public function it_adds_htmx_optimized_headers_for_htmx_requests()
    {
        $this->createTestController('Test', ['index' => 'response']);
        
        $request = $this->createFlashHaltRequest('test@index', 'GET');
        $request->headers->set('HX-Request', 'true'); // Mark as HTMX request
        
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'test@index');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('test@index', 'GET')
            ->andReturn([
                'controller' => new \App\Http\Controllers\TestController(),
                'method' => 'index',
                'class' => 'App\\Http\\Controllers\\TestController',
                'pattern' => 'test@index',
            ]);
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals('no-cache, no-store, must-revalidate', $response->headers->get('Cache-Control'));
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
        $this->assertEquals('0', $response->headers->get('Expires'));
        $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function it_adds_debug_headers_in_development_mode()
    {
        $this->createTestController('Test', ['index' => 'response']);
        
        $request = $this->createFlashHaltRequest('test@index', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'test@index');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        $this->withFlashHaltConfig([
            'development' => ['debug_mode' => true],
            'monitoring' => ['enabled' => true]
        ]);
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('test@index', 'GET')
            ->andReturn([
                'controller' => new \App\Http\Controllers\TestController(),
                'method' => 'index',
                'class' => 'App\\Http\\Controllers\\TestController',
                'pattern' => 'test@index',
            ]);
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals('App\\Http\\Controllers\\TestController', $response->headers->get('X-FlashHALT-Controller'));
        $this->assertEquals('index', $response->headers->get('X-FlashHALT-Method'));
        $this->assertEquals('test@index', $response->headers->get('X-FlashHALT-Pattern'));
        $this->assertNotNull($response->headers->get('X-FlashHALT-Processing-Time'));
    }

    /** @test */
    public function it_handles_view_responses_correctly()
    {
        // Create a controller that returns a view
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class ViewTestController extends Controller {
                public function index() {
                    return view("test-view", ["message" => "Hello from view"]);
                }
            }
        ');
        
        // Create a simple test view
        $viewsPath = resource_path('views');
        if (!is_dir($viewsPath)) {
            mkdir($viewsPath, 0755, true);
        }
        file_put_contents($viewsPath . '/test-view.blade.php', '<div>{{ $message }}</div>');
        
        $request = $this->createFlashHaltRequest('view-test@index', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'view-test@index');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('view-test@index', 'GET')
            ->andReturn([
                'controller' => new \App\Http\Controllers\ViewTestController(),
                'method' => 'index',
                'class' => 'App\\Http\\Controllers\\ViewTestController',
                'pattern' => 'view-test@index',
            ]);
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Hello from view', $response->getContent());
    }

    /** @test */
    public function it_handles_json_responses_correctly()
    {
        eval('
            namespace App\\Http\\Controllers;
            use Illuminate\\Routing\\Controller;
            
            class JsonTestController extends Controller {
                public function index() {
                    return response()->json(["status" => "success", "data" => ["id" => 1]]);
                }
            }
        ');
        
        $request = $this->createFlashHaltRequest('json-test@index', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // $route->setParameter('route', 'json-test@index');
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('json-test@index', 'GET')
            ->andReturn([
                'controller' => new \App\Http\Controllers\JsonTestController(),
                'method' => 'index',
                'class' => 'App\\Http\\Controllers\\JsonTestController',
                'pattern' => 'json-test@index',
            ]);
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        
        $jsonData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $jsonData['status']);
        $this->assertEquals(1, $jsonData['data']['id']);
    }

    /** @test */
public function it_handles_redirect_responses_correctly()
{
    eval('
        namespace App\\Http\\Controllers;
        use Illuminate\\Routing\\Controller;
        
        class RedirectTestController extends Controller {
            public function index() {
                return redirect("/dashboard");
            }
        }
    ');
    
    $request = $this->createFlashHaltRequest('redirect-test@index', 'GET');
    
    $this->controllerResolver->shouldReceive('resolveController')
        ->with('redirect-test@index', 'GET')
        ->andReturn([
            'controller' => new \App\Http\Controllers\RedirectTestController(),
            'method' => 'index',
            'class' => 'App\\Http\\Controllers\\RedirectTestController',
            'pattern' => 'redirect-test@index',
        ]);
    
    $next = function () {
        return new Response('should not be called');
    };
    
    try {
        $response = $this->middleware->handle($request, $next);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->headers->get('Location'));
    } catch (\Exception $e) {
        // Temporary debug - remove after we identify the issue
        $this->fail("Exception thrown: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    }
}

    /** @test */
    public function it_rejects_invalid_route_patterns()
    {
        $invalidPatterns = [
            'no-at-symbol',
            'double@@symbol',
            '',
            'controller@',
            '@method',
        ];
        
        foreach ($invalidPatterns as $pattern) {
            $request = $this->createFlashHaltRequest($pattern, 'GET');
            // $route = new Route(['GET'], 'hx/{route}', []);
            // $route->setParameter('route', $pattern);
            // $request->setRouteResolver(function () use ($route) {
            //     return $route;
            // });
            
            $next = function () {
                return new Response('should not be called');
            };
            
            $response = $this->middleware->handle($request, $next);
            
            $this->assertEquals(404, $response->getStatusCode(), "Pattern '{$pattern}' should be rejected");
        }
    }

    /** @test */
    public function it_handles_requests_without_route_parameters()
    {
        $request = Request::create('/hx/invalid', 'GET');
        // $route = new Route(['GET'], 'hx/{route}', []);
        // // Don't set route parameter to simulate missing parameter
        // $request->setRouteResolver(function () use ($route) {
        //     return $route;
        // });
        
        $next = function () {
            return new Response('next middleware');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        // Should pass to next middleware since it's not a valid FlashHALT request
        $this->assertEquals('next middleware', $response->getContent());
    }

    /** @test */
    public function it_adds_csrf_token_header_for_non_get_requests()
    {
        // Set up session for CSRF token
        $this->app['config']->set('session.driver', 'array');
        $this->startSession();

        $this->createTestController('Test', ['store' => 'created']);
        
        $request = $this->createFlashHaltRequest('test@store', 'POST');
        $request->headers->set('HX-Request', 'true');
        
        // Add CSRF token to session
        $request->session()->put('_token', 'test-csrf-token');
        $request->session()->regenerateToken();
        
        $route = new Route(['POST'], 'hx/{route}', []);
        $route->setParameter('route', 'test@store');
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('test@store', 'POST')
            ->andReturn([
                'controller' => new \App\Http\Controllers\TestController(),
                'method' => 'store',
                'class' => 'App\\Http\\Controllers\\TestController',
                'pattern' => 'test@store',
            ]);
        
        $next = function () {
            return new Response('should not be called');
        };
        
        $response = $this->middleware->handle($request, $next);
        
        $this->assertNotNull($response->headers->get('X-CSRF-Token'));
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