<?php

namespace DancyCodes\FlashHalt\Tests\Feature;

use DancyCodes\FlashHalt\Services\RouteCompiler;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Support\Facades\File;

/**
 * Production Mode Tests
 * 
 * These tests verify that FlashHALT works correctly in production mode
 * with compiled routes and optimized performance settings.
 */
class ProductionModeTest extends TestCase
{
    /** @test */
    public function compiled_routes_are_loaded_in_production_mode()
    {
        $this->withFlashHaltConfig([
            'mode' => 'production'
        ]);

        // Create compiled routes file
        $compiledPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        $compiledContent = '<?php
            Route::get("hx/users@index", [App\\Http\\Controllers\\UsersController::class, "index"]);
            Route::post("hx/users@store", [App\\Http\\Controllers\\UsersController::class, "store"]);
        ';
        
        File::ensureDirectoryExists(dirname($compiledPath));
        File::put($compiledPath, $compiledContent);

        // Create the controller
        $this->createTestController('Users', [
            'index' => 'User list',
            'store' => 'User stored'
        ]);

        // Test that compiled routes work
        $response = $this->get('/hx/users@index');
        $response->assertStatus(200);
        $response->assertSee('User list');

        $response = $this->post('/hx/users@store');
        $response->assertStatus(200);
        $response->assertSee('User stored');
    }

    /** @test */
    public function production_mode_disables_dynamic_resolution()
    {
        $this->withFlashHaltConfig([
            'mode' => 'production',
            'production' => [
                'disable_dynamic_resolution' => true
            ]
        ]);

        // Create controller but don't compile it
        $this->createTestController('Dynamic', ['test' => 'dynamic response']);

        // Request should fail because dynamic resolution is disabled
        $response = $this->get('/hx/dynamic@test');
        $response->assertStatus(404);
    }

    /** @test */
    public function cache_configuration_is_optimized_for_production()
    {
        $this->withFlashHaltConfig([
            'mode' => 'production'
        ]);

        $resolver = $this->app->make(\DancyCodes\FlashHalt\Services\ControllerResolver::class);
        $validator = $this->app->make(\DancyCodes\FlashHalt\Services\SecurityValidator::class);

        // In production, cache TTL should be longer
        $stats = $validator->getValidationStats();
        $this->assertGreaterThan(3600, $stats['cache_ttl']); // At least 1 hour
    }

    /** @test */
    public function error_reporting_is_configured_for_production()
    {
        $this->withFlashHaltConfig([
            'mode' => 'production'
        ]);

        $response = $this->get('/hx/nonexistent@method');
        
        // In production, error details should be limited
        $response->assertStatus(404);
        $responseData = $response->json();
        
        $this->assertArrayNotHasKey('file', $responseData);
        $this->assertArrayNotHasKey('line', $responseData);
        $this->assertArrayNotHasKey('trace', $responseData);
    }
}