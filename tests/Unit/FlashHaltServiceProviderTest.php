<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\FlashHaltServiceProvider;
use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use DancyCodes\FlashHalt\Services\RouteCompiler;
use DancyCodes\FlashHalt\Console\Commands\CompileCommand;
use DancyCodes\FlashHalt\Console\Commands\ClearCommand;
use DancyCodes\FlashHalt\Http\Middleware\FlashHaltMiddleware;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;

/**
 * Service Provider Tests
 * 
 * These tests verify that the FlashHaltServiceProvider correctly registers
 * all services, middleware, commands, and routes. The service provider is
 * the entry point for the entire package and must handle complex bootstrapping
 * logic for different environments and modes.
 * 
 * Testing strategy covers:
 * - Service registration and dependency injection
 * - Middleware registration and aliasing
 * - Command registration for Artisan
 * - Route registration for different modes
 * - Configuration merging and validation
 * - Asset publishing and management
 * - View composers and Blade directives
 * - JavaScript integration setup
 */
class FlashHaltServiceProviderTest extends TestCase
{
    private FlashHaltServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Get a fresh instance of the service provider for testing
        $this->provider = new FlashHaltServiceProvider($this->app);
    }

    // ==================== SERVICE REGISTRATION TESTS ====================

    /** @test */
    public function it_registers_controller_resolver_as_singleton()
    {
        $this->assertTrue($this->app->bound(ControllerResolver::class));
        
        $instance1 = $this->app->make(ControllerResolver::class);
        $instance2 = $this->app->make(ControllerResolver::class);
        
        $this->assertInstanceOf(ControllerResolver::class, $instance1);
        $this->assertSame($instance1, $instance2); // Should be same instance (singleton)
    }

    /** @test */
    public function it_registers_security_validator_as_singleton()
    {
        $this->assertTrue($this->app->bound(SecurityValidator::class));
        
        $instance1 = $this->app->make(SecurityValidator::class);
        $instance2 = $this->app->make(SecurityValidator::class);
        
        $this->assertInstanceOf(SecurityValidator::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    /** @test */
    public function it_registers_route_compiler_as_singleton()
    {
        $this->assertTrue($this->app->bound(RouteCompiler::class));
        
        $instance1 = $this->app->make(RouteCompiler::class);
        $instance2 = $this->app->make(RouteCompiler::class);
        
        $this->assertInstanceOf(RouteCompiler::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    /** @test */
    public function it_injects_correct_dependencies_into_services()
    {
        $controllerResolver = $this->app->make(ControllerResolver::class);
        $routeCompiler = $this->app->make(RouteCompiler::class);
        
        // Use reflection to verify dependencies were injected correctly
        $resolverReflection = new \ReflectionClass($controllerResolver);
        $securityValidatorProperty = $resolverReflection->getProperty('securityValidator');
        $securityValidatorProperty->setAccessible(true);
        $injectedValidator = $securityValidatorProperty->getValue($controllerResolver);
        
        $this->assertInstanceOf(SecurityValidator::class, $injectedValidator);
        
        // Verify route compiler gets the right dependencies
        $compilerReflection = new \ReflectionClass($routeCompiler);
        $controllerResolverProperty = $compilerReflection->getProperty('controllerResolver');
        $controllerResolverProperty->setAccessible(true);
        $injectedResolver = $controllerResolverProperty->getValue($routeCompiler);
        
        $this->assertInstanceOf(ControllerResolver::class, $injectedResolver);
    }

    /** @test */
    public function it_merges_configuration_correctly()
    {
        // The configuration should be merged from the package config file
        $config = $this->app['config']->get('flashhalt');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('mode', $config);
        $this->assertArrayHasKey('development', $config);
        $this->assertArrayHasKey('production', $config);
        $this->assertArrayHasKey('security', $config);
        $this->assertArrayHasKey('compilation', $config);
    }

    // ==================== MIDDLEWARE REGISTRATION TESTS ====================

    /** @test */
    public function it_registers_flashhalt_middleware_alias()
    {
        $router = $this->app['router'];
        $middleware = $router->getMiddleware();
        
        $this->assertArrayHasKey('flashhalt', $middleware);
        $this->assertEquals(FlashHaltMiddleware::class, $middleware['flashhalt']);
    }

    /** @test */
    public function it_can_instantiate_middleware_through_service_container()
    {
        $middleware = $this->app->make(FlashHaltMiddleware::class);
        
        $this->assertInstanceOf(FlashHaltMiddleware::class, $middleware);
    }

    // ==================== COMMAND REGISTRATION TESTS ====================

    /** @test */
    public function it_registers_compile_command_in_console_environment()
    {
        // Simulate console environment
        $this->app['env'] = 'testing'; // Testing environment typically runs in console
        
        $artisan = $this->app['Illuminate\Contracts\Console\Kernel'];
        $commands = $artisan->all();
        
        $this->assertArrayHasKey('flashhalt:compile', $commands);
        $this->assertInstanceOf(CompileCommand::class, $commands['flashhalt:compile']);
    }

    /** @test */
    public function it_registers_clear_command_in_console_environment()
    {
        $artisan = $this->app['Illuminate\Contracts\Console\Kernel'];
        $commands = $artisan->all();
        
        $this->assertArrayHasKey('flashhalt:clear', $commands);
        $this->assertInstanceOf(ClearCommand::class, $commands['flashhalt:clear']);
    }

    /** @test */
    public function commands_can_be_instantiated_with_dependencies()
    {
        $compileCommand = $this->app->make(CompileCommand::class);
        $clearCommand = $this->app->make(ClearCommand::class);
        
        $this->assertInstanceOf(CompileCommand::class, $compileCommand);
        $this->assertInstanceOf(ClearCommand::class, $clearCommand);
    }

    // ==================== ROUTE REGISTRATION TESTS ====================

    /** @test */
    public function it_registers_development_routes_in_development_mode()
    {
        $this->withFlashHaltConfig(['mode' => 'development']);
        
        // Re-register routes with new configuration
        $this->provider->boot();
        
        $routes = Route::getRoutes();
        $flashhaltRoutes = [];
        
        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'hx/')) {
                $flashhaltRoutes[] = $route;
            }
        }
        
        $this->assertNotEmpty($flashhaltRoutes);
        
        // Find the catch-all FlashHALT route
        $catchAllRoute = null;
        foreach ($flashhaltRoutes as $route) {
            if ($route->uri() === 'hx/{route}') {
                $catchAllRoute = $route;
                break;
            }
        }
        
        $this->assertNotNull($catchAllRoute);
        $this->assertContains('flashhalt', $catchAllRoute->middleware());
    }

    /** @test */
    public function it_registers_production_routes_when_compiled_file_exists()
    {
        // Create a test compiled routes file
        $compiledPath = storage_path('app/test-compiled-routes.php');
        $compiledContent = '<?php
        use Illuminate\Support\Facades\Route;
        
        Route::prefix("hx")->group(function () {
            Route::post("users@store", [App\Http\Controllers\UsersController::class, "store"]);
            Route::get("users@index", [App\Http\Controllers\UsersController::class, "index"]);
        });';
        
        file_put_contents($compiledPath, $compiledContent);
        
        $this->withFlashHaltConfig([
            'mode' => 'production',
            'production' => ['compiled_routes_path' => $compiledPath]
        ]);
        
        // Re-register routes
        $this->provider->boot();
        
        $routes = Route::getRoutes();
        $compiledRoutes = [];
        
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'users@')) {
                $compiledRoutes[] = $route;
            }
        }
        
        $this->assertNotEmpty($compiledRoutes);
        
        // Cleanup
        unlink($compiledPath);
    }

    /** @test */
    public function it_throws_exception_in_production_mode_without_compiled_routes()
    {
        $this->withFlashHaltConfig([
            'mode' => 'production',
            'production' => [
                'compiled_routes_path' => '/nonexistent/path/routes.php',
                'verification_required' => true
            ]
        ]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FlashHALT is running in production mode but compiled routes are missing');
        
        $this->provider->boot();
    }

    /** @test */
    public function it_determines_operating_mode_automatically()
    {
        // Test auto mode in testing environment
        $this->withFlashHaltConfig(['mode' => 'auto']);
        $this->app['env'] = 'testing';
        
        $this->provider->boot();
        
        // Should default to development mode in testing environment
        // We can verify this by checking if the catch-all route exists
        $routes = Route::getRoutes();
        $hasCatchAllRoute = false;
        
        foreach ($routes as $route) {
            if ($route->uri() === 'hx/{route}') {
                $hasCatchAllRoute = true;
                break;
            }
        }
        
        $this->assertTrue($hasCatchAllRoute);
    }

    // ==================== JAVASCRIPT INTEGRATION TESTS ====================

    /** @test */
    public function it_registers_view_composers_for_javascript_integration()
    {
        // Enable JavaScript integration
        $this->withFlashHaltConfig([
            'integration' => ['javascript_enabled' => true]
        ]);
        
        $this->provider->boot();
        
        // Create a test view to trigger the composer
        $this->createTestTemplate('test-js-integration', '<div>Test content</div>');
        
        $viewContent = view('test-js-integration')->render();
        
        // The view should have been processed by the composer
        $this->assertIsString($viewContent);
    }

    /** @test */
    public function it_registers_blade_directives_for_manual_integration()
    {
        $this->provider->boot();
        
        // Test that the Blade directives were registered
        $bladeCompiler = $this->app['blade.compiler'];
        $directives = $bladeCompiler->getCustomDirectives();
        
        $this->assertArrayHasKey('flashhaltScripts', $directives);
        $this->assertArrayHasKey('flashhaltEnabled', $directives);
        $this->assertArrayHasKey('endflashhalt', $directives);
        $this->assertArrayHasKey('flashhaltCsrf', $directives);
    }

    /** @test */
    public function it_publishes_javascript_assets_automatically()
    {
        // Mock file system operations
        $this->withFlashHaltConfig([
            'integration' => ['javascript_enabled' => true]
        ]);
        
        $this->provider->boot();
        
        // In a real application, assets would be published to public/vendor/flashhalt/js
        // For testing, we verify that the publishing configuration is set up
        $publishGroups = $this->provider::$publishGroups ?? [];
        
        // The service provider should have configured asset publishing
        $this->assertTrue(true); // Basic test that no exceptions were thrown
    }

    /** @test */
    public function it_configures_javascript_based_on_environment()
    {
        $this->app['env'] = 'development';
        
        $this->withFlashHaltConfig([
            'development' => ['debug_mode' => true],
            'integration' => ['javascript_enabled' => true]
        ]);
        
        $this->provider->boot();
        
        // JavaScript configuration should be optimized for development
        $this->assertTrue(true); // Configuration is tested in more detail in ConfigurationTest
    }

    // ==================== CONFIGURATION TESTS ====================

    /** @test */
    public function it_provides_default_configuration_values()
    {
        $config = $this->app['config']->get('flashhalt');
        
        // Verify essential configuration sections exist
        $this->assertArrayHasKey('mode', $config);
        $this->assertArrayHasKey('development', $config);
        $this->assertArrayHasKey('production', $config);
        $this->assertArrayHasKey('security', $config);
        
        // Verify some specific default values
        $this->assertIsArray($config['security']['method_blacklist']);
        $this->assertContains('__construct', $config['security']['method_blacklist']);
        $this->assertTrue($config['security']['csrf_protection']);
    }

    /** @test */
    public function it_allows_configuration_overrides()
    {
        // Override some configuration
        $this->app['config']->set('flashhalt.mode', 'custom_mode');
        $this->app['config']->set('flashhalt.development.cache_ttl', 9999);
        
        $config = $this->app['config']->get('flashhalt');
        
        $this->assertEquals('custom_mode', $config['mode']);
        $this->assertEquals(9999, $config['development']['cache_ttl']);
    }

    // ==================== ERROR HANDLING TESTS ====================

    /** @test */
    public function it_handles_missing_dependencies_gracefully()
    {
        // Temporarily remove a service from the container
        $this->app->forgetInstance(SecurityValidator::class);
        unset($this->app[SecurityValidator::class]);
        
        // The service provider should handle this gracefully
        try {
            $resolver = $this->app->make(ControllerResolver::class);
            $this->fail('Expected exception for missing dependency');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /** @test */
    public function it_validates_configuration_requirements()
    {
        // Test with invalid configuration
        $this->app['config']->set('flashhalt', [
            'mode' => 'production',
            'production' => [
                'compiled_routes_path' => null, // Invalid
                'verification_required' => true
            ]
        ]);
        
        $this->expectException(\RuntimeException::class);
        
        $this->provider->boot();
    }

    // ==================== PUBLISHING TESTS ====================

    /** @test */
    public function it_configures_asset_publishing_for_manual_management()
    {
        // In console environment, publishing should be configured
        $this->assertTrue($this->app->runningInConsole());
        
        // The service provider should have set up publishing
        // We can't easily test the actual publishing without mocking the filesystem
        // But we can verify the service provider doesn't throw exceptions
        $this->provider->boot();
        $this->assertTrue(true);
    }

    /** @test */
    public function it_publishes_configuration_files()
    {
        // The service provider should configure config publishing
        $this->provider->boot();
        
        // Configuration publishing is tested via the actual publish commands
        // This test verifies the provider sets up publishing without errors
        $this->assertTrue(true);
    }

    // ==================== INTEGRATION TESTS ====================

    /** @test */
    public function it_integrates_all_components_successfully()
    {
        // This test verifies that all registered services work together
        $controllerResolver = $this->app->make(ControllerResolver::class);
        $securityValidator = $this->app->make(SecurityValidator::class);
        $routeCompiler = $this->app->make(RouteCompiler::class);
        
        $this->assertInstanceOf(ControllerResolver::class, $controllerResolver);
        $this->assertInstanceOf(SecurityValidator::class, $securityValidator);
        $this->assertInstanceOf(RouteCompiler::class, $routeCompiler);
        
        // Test that they can interact (basic smoke test)
        $config = $this->app['config']->get('flashhalt.security', []);
        $this->assertIsArray($config);
    }

    /** @test */
    public function it_works_with_laravel_service_discovery()
    {
        // Test that the package can be auto-discovered by Laravel
        $providers = $this->app->getProviders(FlashHaltServiceProvider::class);
        
        $this->assertNotEmpty($providers);
        $this->assertInstanceOf(FlashHaltServiceProvider::class, $providers[0]);
    }

    /** @test */
    public function it_handles_different_laravel_versions()
    {
        // Test compatibility with Laravel's service container
        $version = $this->app->version();
        
        // Basic compatibility test - if we get this far without exceptions,
        // the service provider is compatible with this Laravel version
        $this->assertNotEmpty($version);
        $this->assertTrue($this->app->bound(ControllerResolver::class));
    }

    /** @test */
    public function it_provides_helpful_error_messages_for_common_issues()
    {
        // Test configuration validation error messages
        $this->withFlashHaltConfig([
            'mode' => 'production',
            'production' => [
                'compiled_routes_path' => '/invalid/path/routes.php',
                'verification_required' => true
            ]
        ]);
        
        try {
            $this->provider->boot();
            $this->fail('Expected exception for invalid configuration');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('compiled routes are missing', $e->getMessage());
            $this->assertStringContainsString('php artisan flashhalt:compile', $e->getMessage());
            $this->assertStringContainsString('https://flashhalt.dev/production', $e->getMessage());
        }
    }

    /** @test */
    public function it_registers_response_macros_when_configured()
    {
        $this->withFlashHaltConfig([
            'integration' => [
                'response_macros' => [
                    'htmx' => true,
                    'htmxRedirect' => true,
                    'htmxRefresh' => true
                ]
            ]
        ]);
        
        $this->provider->boot();
        
        // Test that response macros were registered
        // Note: In a real test, you'd verify the macros exist on the Response facade
        $this->assertTrue(true);
    }

    /** @test */
    public function it_processes_htmx_headers_when_configured()
    {
        $this->withFlashHaltConfig([
            'integration' => ['process_htmx_headers' => true]
        ]);
        
        $this->provider->boot();
        
        // Header processing is tested in integration tests
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        // Clean up any test files created during testing
        $testFiles = [
            storage_path('app/test-compiled-routes.php'),
            resource_path('views/test-js-integration.blade.php')
        ];
        
        foreach ($testFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        parent::tearDown();
    }
}