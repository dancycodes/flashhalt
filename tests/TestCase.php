<?php

namespace DancyCodes\FlashHalt\Tests;

use DancyCodes\FlashHalt\FlashHaltServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for FlashHALT package tests.
 * 
 * This class provides the testing foundation for all FlashHALT tests by setting up
 * a minimal Laravel application through Orchestra Testbench. It handles service
 * provider registration, configuration setup, and provides helper methods that
 * make testing FlashHALT functionality easier and more consistent.
 */
class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Setup the test environment before each test.
     * This method runs before every single test method, ensuring each test
     * starts with a clean, predictable environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set up session for tests that need CSRF
        $this->startSession();

        // Set up default configuration that works for most tests
        $this->setUpDefaultConfiguration();
        
        // Create test routes and controllers for testing
        $this->setUpTestRoutes();
        
        // Clear any existing caches to ensure clean test state
        $this->artisan('cache:clear');
    }

    /**
     * Define which service providers are needed for testing.
     * Orchestra Testbench needs to know which service providers to load
     * to create a minimal Laravel application for testing.
     */
    protected function getPackageProviders($app): array
    {
        return [
            FlashHaltServiceProvider::class,
        ];
    }

    /**
     * Define the environment setup for tests.
     * This method configures the test application environment with settings
     * that are appropriate for testing FlashHALT functionality.
     */
    protected function defineEnvironment($app): void
    {
        // Configure basic Laravel settings for testing
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.debug', true);
        
        // Use in-memory database for fast testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Use array cache driver for predictable caching behavior
        $app['config']->set('cache.default', 'array');
        
        // Configure session for testing
        $app['config']->set('session.driver', 'array');
    }

    /**
     * Set up default FlashHALT configuration for testing.
     * This provides sensible defaults that work for most tests while allowing
     * individual tests to override specific settings when needed.
     */
    protected function setUpDefaultConfiguration(): void
    {
        $this->app['config']->set('flashhalt', [
            'mode' => 'development',
            'development' => [
                'cache_ttl' => 60, // Short TTL for testing
                'debug_mode' => true,
                'rate_limit' => 1000, // High limit to avoid issues in tests
                'allowed_controllers' => [], // Allow all controllers by default
                'enable_resolution_logging' => true,
                'auto_cache_invalidation' => true,
            ],
            'production' => [
                'compiled_routes_path' => storage_path('app/flashhalt-compiled.php'),
                'verification_required' => false, // Disable for testing
                'monitoring_enabled' => false,
                'enable_route_caching' => false,
            ],
            'security' => [
                'method_blacklist' => [
                    '__construct', '__destruct', '__call', '__callStatic',
                    'getRouteKey', 'getRouteKeyName', 'resolveRouteBinding',
                ],
                'method_pattern_blacklist' => ['/^_.*/', '/.*[Pp]assword.*/'],
                'require_authorization' => false,
                'csrf_protection' => true,
                'enforce_http_method_semantics' => true,
            ],
            'compilation' => [
                'template_directories' => [resource_path('views')],
                'template_patterns' => ['*.blade.php'],
                'exclude_patterns' => ['vendor/*', 'node_modules/*'],
                'optimize_routes' => true,
                'generate_route_names' => true,
                'detect_middleware' => true,
                'validation_level' => 'strict',
                'generate_reports' => true,
            ],
            'performance' => [
                'cache_store' => 'array',
                'enable_cache_warming' => false,
                'enable_memory_cache' => true,
                'memory_cache_limit' => 5,
            ],
        ]);
    }

    /**
     * Set up test routes for testing FlashHALT functionality.
     * These routes provide endpoints that tests can use to verify that
     * FlashHALT's dynamic routing works correctly.
     */
    protected function setUpTestRoutes(): void
    {
        $this->app['router']->middleware(['web', 'flashhalt'])
            ->prefix('hx')
            ->group(function () {
                $this->app['router']->any('{route}', function () {
                    return 'FlashHALT route not processed';
                })->where('route', '.*@.*');
            });
    }

    /**
     * Create a test controller class dynamically for testing purposes.
     * This helper method creates controller classes that can be used in tests
     * without requiring actual files in the filesystem.
     */
    protected function createTestController(string $name, array $methods = []): string
    {
        $className = "App\\Http\\Controllers\\{$name}Controller";
        
        if (!class_exists($className)) {
            $methodCode = '';
            foreach ($methods as $methodName => $returnValue) {
                $returnCode = is_string($returnValue) ? "'{$returnValue}'" : var_export($returnValue, true);
                $methodCode .= "
                    public function {$methodName}() {
                        return {$returnCode};
                    }
                ";
            }
            
            eval("
                namespace App\\Http\\Controllers;
                use Illuminate\\Http\\Request;
                use Illuminate\\Routing\\Controller;
                
                class {$name}Controller extends Controller {
                    {$methodCode}
                }
            ");
        }
        
        return $className;
    }

    /**
     * Create a temporary Blade template file for testing compilation.
     * This helper creates actual template files that the RouteCompiler
     * can analyze during testing.
     */
    protected function createTestTemplate(string $name, string $content): string
    {
        $viewsPath = resource_path('views');
        if (!is_dir($viewsPath)) {
            mkdir($viewsPath, 0755, true);
        }
        
        $templatePath = $viewsPath . '/' . $name . '.blade.php';
        file_put_contents($templatePath, $content);
        
        return $templatePath;
    }

    /**
     * Assert that a cache key exists with the expected value.
     * This helper makes it easier to test caching behavior in FlashHALT services.
     */
    protected function assertCacheHas(string $key, $expectedValue = null): void
    {
        $this->assertTrue(
            $this->app['cache']->has($key),
            "Expected cache key '{$key}' to exist but it was not found."
        );
        
        if ($expectedValue !== null) {
            $this->assertEquals(
                $expectedValue,
                $this->app['cache']->get($key),
                "Cache key '{$key}' exists but has unexpected value."
            );
        }
    }

    /**
     * Assert that a cache key does not exist.
     */
    protected function assertCacheDoesNotHave(string $key): void
    {
        $this->assertFalse(
            $this->app['cache']->has($key),
            "Expected cache key '{$key}' to not exist but it was found."
        );
    }

    /**
     * Create an HTTP request for testing FlashHALT routes.
     * This helper simplifies creating requests that match FlashHALT's
     * expected route pattern without complex route binding.
     */
    protected function createFlashHaltRequest(string $pattern, string $method = 'GET', array $data = []): \Illuminate\Http\Request
    {
        $uri = "/hx/{$pattern}";
        $request = Request::create($uri, $method, $data);
        $request->headers->set('HX-Request', 'true');
        
        // Create a simple route mock that provides the pattern
        $route = new class($pattern) {
            private $pattern;
            
            public function __construct($pattern) {
                $this->pattern = $pattern;
            }
            
            public function hasParameter($key) {
                return $key === 'route';
            }
            
            public function parameter($key, $default = null) {
                return $key === 'route' ? $this->pattern : $default;
            }
            
            public function parameters() {
                return ['route' => $this->pattern];
            }
        };
        
        // Set the route resolver to return our mock
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        
        return $request;
    }

    /**
     * Temporarily set FlashHALT configuration for a test.
     * This allows tests to modify configuration without affecting other tests.
     */
    protected function withFlashHaltConfig(array $config): self
    {
        $existingConfig = $this->app['config']->get('flashhalt', []);
        $mergedConfig = array_merge_recursive($existingConfig, $config);
        $this->app['config']->set('flashhalt', $mergedConfig);
        
        return $this;
    }

    /**
     * Clean up after each test.
     * This ensures that each test leaves the environment clean for the next test.
     */
    protected function tearDown(): void
    {
        // Clean up any test files that were created
        $this->cleanUpTestFiles();
        
        parent::tearDown();
    }

    /**
     * Remove any test files that were created during testing.
     */
    protected function cleanUpTestFiles(): void
    {
        $compiledRoutesPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        if ($compiledRoutesPath && file_exists($compiledRoutesPath)) {
            unlink($compiledRoutesPath);
        }
        
        // Clean up test view files
        $viewsPath = resource_path('views');
        if (is_dir($viewsPath)) {
            $files = glob($viewsPath . '/*.blade.php');
            foreach ($files as $file) {
                if (strpos($file, 'test-') !== false) {
                    unlink($file);
                }
            }
        }
    }
}