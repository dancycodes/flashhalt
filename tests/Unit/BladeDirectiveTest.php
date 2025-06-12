<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

/**
 * Blade Directive Tests
 * 
 * These tests verify that FlashHALT's custom Blade directives work correctly
 * and generate the expected output for JavaScript integration.
 */
class BladeDirectiveTest extends TestCase
{
    /** @test */
    public function flashhalt_scripts_directive_generates_correct_output()
    {
        $compiled = Blade::compileString('@flashhaltScripts');
        
        $this->assertStringContains('flashhalt.js', $compiled);
        $this->assertStringContains('<script', $compiled);
    }

    /** @test */
    public function flashhalt_enabled_directive_creates_conditional_wrapper()
    {
        $template = '
            @flashhaltEnabled
                <div>This content is FlashHALT enabled</div>
            @endflashhalt
        ';
        
        $compiled = Blade::compileString($template);
        
        $this->assertStringContains('flashhalt', $compiled);
        $this->assertStringContains('This content is FlashHALT enabled', $compiled);
    }

    /** @test */
    public function flashhalt_csrf_directive_includes_token()
    {
        $compiled = Blade::compileString('@flashhaltCsrf');
        
        $this->assertStringContains('csrf_token', $compiled);
        $this->assertStringContains('meta', $compiled);
    }

    /** @test */
    public function directives_respect_configuration_settings()
    {
        $this->withFlashHaltConfig([
            'integration' => [
                'javascript_enabled' => false
            ]
        ]);

        $compiled = Blade::compileString('@flashhaltScripts');
        
        // When disabled, should not include scripts
        $this->assertStringNotContains('flashhalt.js', $compiled);
    }
}