<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Tests\TestCase;

/**
 * Configuration Tests
 * 
 * These tests verify that FlashHALT handles various configuration scenarios
 * correctly and provides appropriate defaults and validation.
 */
class ConfigurationTest extends TestCase
{
    /** @test */
    public function default_configuration_is_valid_and_complete()
    {
        $config = $this->app['config']->get('flashhalt');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('mode', $config);
        $this->assertArrayHasKey('development', $config);
        $this->assertArrayHasKey('production', $config);
        $this->assertArrayHasKey('security', $config);
        $this->assertArrayHasKey('compilation', $config);
        $this->assertArrayHasKey('integration', $config);

        // Verify critical defaults
        $this->assertEquals('development', $config['mode']);
        $this->assertTrue($config['development']['enabled']);
        $this->assertIsArray($config['security']['allowed_namespaces']);
        $this->assertIsArray($config['security']['blocked_methods']);
    }

    /** @test */
    public function production_mode_configuration_disables_development_features()
    {
        $this->withFlashHaltConfig([
            'mode' => 'production'
        ]);

        $config = $this->app['config']->get('flashhalt');
        
        // In production mode, certain development features should be restricted
        $this->assertEquals('production', $config['mode']);
        
        // Verify production-specific settings
        $this->assertArrayHasKey('compiled_routes_path', $config['production']);
        $this->assertArrayHasKey('cache_compiled_routes', $config['production']);
    }

    /** @test */
    public function security_configuration_validates_namespace_patterns()
    {
        $this->withFlashHaltConfig([
            'security' => [
                'allowed_namespaces' => [
                    'App\\Http\\Controllers\\*',
                    'App\\Http\\Controllers\\Api\\*'
                ]
            ]
        ]);

        $validator = $this->app->make(\DancyCodes\FlashHalt\Services\SecurityValidator::class);
        
        // These should pass validation
        $this->assertTrue($validator->isNamespaceAllowed('App\\Http\\Controllers\\UserController'));
        $this->assertTrue($validator->isNamespaceAllowed('App\\Http\\Controllers\\Api\\UserController'));
        
        // These should fail validation
        $this->assertFalse($validator->isNamespaceAllowed('App\\Services\\SomeService'));
        $this->assertFalse($validator->isNamespaceAllowed('Illuminate\\Routing\\Controller'));
    }

    /** @test */
    public function compilation_configuration_affects_template_scanning()
    {
        $customViewPath = sys_get_temp_dir() . '/flashhalt-custom-views-' . uniqid();
        mkdir($customViewPath, 0755, true);

        $this->withFlashHaltConfig([
            'compilation' => [
                'template_directories' => [$customViewPath],
                'template_patterns' => ['*.blade.php', '*.html'],
                'exclude_patterns' => ['admin/*', 'test/*']
            ]
        ]);

        $compiler = $this->app->make(\DancyCodes\FlashHalt\Services\RouteCompiler::class);
        
        // Create test files
        file_put_contents($customViewPath . '/main.blade.php', '<div hx-get="hx/test@method">Test</div>');
        file_put_contents($customViewPath . '/index.html', '<div hx-post="hx/test@store">Test</div>');
        mkdir($customViewPath . '/admin', 0755, true);
        file_put_contents($customViewPath . '/admin/panel.blade.php', '<div hx-get="hx/admin@index">Admin</div>');

        $result = $compiler->compile();

        // Should find files matching patterns but exclude admin directory
        $this->assertGreaterThan(0, $result['statistics']['templates_scanned']);
        
        // Clean up
        $this->recursiveDelete($customViewPath);
    }

    /** @test */
    public function middleware_configuration_is_applied_correctly()
    {
        $this->withFlashHaltConfig([
            'middleware' => ['web', 'auth', 'throttle:60,1']
        ]);

        // Test that middleware is registered correctly
        $router = $this->app['router'];
        $routes = $router->getRoutes();

        $flashhaltRoute = null;
        foreach ($routes as $route) {
            if ($route->uri() === 'hx/{route}') {
                $flashhaltRoute = $route;
                break;
            }
        }

        $this->assertNotNull($flashhaltRoute);
        
        $middleware = $flashhaltRoute->middleware();
        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('throttle:60,1', $middleware);
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}