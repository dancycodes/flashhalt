<?php

namespace DancyCodes\FlashHalt\Tests\Feature;

use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Support\Facades\File;

/**
 * JavaScript Integration Tests
 * 
 * These tests verify that the JavaScript component of FlashHALT integrates
 * correctly with the Laravel backend and provides the expected functionality.
 */
class JavaScriptIntegrationTest extends TestCase
{
    /** @test */
    public function javascript_assets_are_published_correctly()
    {
        $this->withFlashHaltConfig([
            'integration' => ['javascript_enabled' => true]
        ]);

        $this->artisan('vendor:publish', [
            '--tag' => 'flashhalt-assets',
            '--force' => true
        ]);

        $expectedPath = public_path('vendor/flashhalt/js/flashhalt.js');
        $this->assertFileExists($expectedPath);

        $content = File::get($expectedPath);
        $this->assertStringContains('class FlashHALTIntegration', $content);
        $this->assertStringContains('setupHTMXIntegration', $content);
    }

    /** @test */
    public function blade_directives_generate_correct_javascript()
    {
        $this->withFlashHaltConfig([
            'integration' => ['javascript_enabled' => true]
        ]);

        $template = '
            @flashhaltScripts
            <div>Content</div>
            @flashhaltEnabled
                <button hx-get="hx/test@method">Click</button>
            @endflashhalt
        ';

        $compiled = $this->compileBladeTemplate($template);

        $this->assertStringContains('<script src="/vendor/flashhalt/js/flashhalt.js">', $compiled);
        $this->assertStringContains('FlashHALTIntegration', $compiled);
        $this->assertStringContains('csrf_token', $compiled);
    }

    /** @test */
    public function view_composers_inject_scripts_automatically()
    {
        $this->withFlashHaltConfig([
            'integration' => [
                'javascript_enabled' => true,
                'auto_inject' => true
            ]
        ]);

        $this->createTestTemplate('auto-inject-test', '
            <html>
                <head><title>Test</title></head>
                <body>
                    <div hx-get="hx/test@method">Content</div>
                </body>
            </html>
        ');

        $rendered = view('auto-inject-test')->render();

        $this->assertStringContains('flashhalt.js', $rendered);
        $this->assertStringContains('FlashHALTIntegration', $rendered);
    }

    /** @test */
    public function csrf_token_integration_works_correctly()
    {
        $template = '@flashhaltCsrf';
        $compiled = $this->compileBladeTemplate($template);

        $this->assertStringContains('csrf_token', $compiled);
        $this->assertStringContains(csrf_token(), $compiled);
    }

    private function compileBladeTemplate(string $template): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'blade_test');
        file_put_contents($tempFile, $template);

        $compiled = $this->app['blade.compiler']->compileString($template);
        
        unlink($tempFile);
        
        return $compiled;
    }
}