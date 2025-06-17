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
        
        // The directive compiles to PHP code that echoes a variable
        $this->assertStringContainsString('$flashhaltScripts', $compiled);
        $this->assertStringContainsString('echo', $compiled);
        $this->assertStringContainsString('isset', $compiled);
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
    
    // Check for the actual method name (with correct casing)
    $this->assertStringContainsString('FlashHALT', $compiled);
    $this->assertStringContainsString('This content is FlashHALT enabled', $compiled);
    $this->assertStringContainsString('if(', $compiled);
    $this->assertStringContainsString('endif', $compiled);
}

    /** @test */
    public function flashhalt_csrf_directive_includes_token()
    {
        $compiled = Blade::compileString('@flashhaltCsrf');
        
        $this->assertStringContainsString('csrf_token', $compiled);
        $this->assertStringContainsString('meta', $compiled);
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
        $this->assertStringNotContainsString('flashhalt.js', $compiled);
    }
}