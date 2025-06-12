<?php

namespace DancyCodes\FlashHalt\Tests\Feature;

use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * HTTP Feature Tests
 * 
 * These tests verify that FlashHALT works correctly with real HTTP requests,
 * testing the complete request-response cycle including HTMX integration,
 * CSRF protection, and response formatting.
 */
class FlashHaltHttpTest extends TestCase
{
    /** @test */
    public function it_handles_basic_htmx_requests_end_to_end()
    {
        $this->createTestController('Users', [
            'index' => '<div>User list</div>'
        ]);

        $response = $this->get('/hx/users@index', [
            'HX-Request' => 'true'
        ]);

        $response->assertStatus(200);
        $response->assertSee('User list');
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /** @test */
    public function it_handles_post_requests_with_csrf_protection()
    {
        $this->createTestController('Users', [
            'store' => 'User created successfully'
        ]);

        // First request should fail without CSRF token
        $response = $this->post('/hx/users@store', [
            'name' => 'John Doe'
        ], [
            'HX-Request' => 'true'
        ]);

        $response->assertStatus(419); // CSRF token mismatch

        // Second request should succeed with CSRF token
        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->post('/hx/users@store', [
                'name' => 'John Doe'
            ], [
                'HX-Request' => 'true'
            ]);

        $response->assertStatus(200);
        $response->assertSee('User created successfully');
    }

    /** @test */
    public function it_returns_404_for_non_existent_controllers()
    {
        $response = $this->get('/hx/nonexistent@index', [
            'HX-Request' => 'true'
        ]);

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'error',
            'error_code',
            'message'
        ]);
    }

    /** @test */
    public function it_handles_method_parameters_correctly()
    {
        $this->createTestController('Users', [
            'show' => function($id) {
                return "<div>User {$id}</div>";
            }
        ]);

        $response = $this->get('/hx/users@show/123', [
            'HX-Request' => 'true'
        ]);

        $response->assertStatus(200);
        $response->assertSee('User 123');
    }

    /** @test */
    public function it_respects_middleware_configuration()
    {
        $this->withFlashHaltConfig([
            'middleware' => ['auth', 'throttle:60,1']
        ]);

        $response = $this->get('/hx/users@index', [
            'HX-Request' => 'true'
        ]);

        // Should be redirected to login (assuming auth middleware)
        $response->assertStatus(302);
    }
}