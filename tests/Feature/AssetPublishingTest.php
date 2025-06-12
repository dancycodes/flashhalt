<?php

namespace DancyCodes\FlashHalt\Tests\Feature;

use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Support\Facades\File;

/**
 * Asset Publishing Tests
 * 
 * These tests verify that FlashHALT's assets are published correctly
 * and can be accessed by the web application.
 */
class AssetPublishingTest extends TestCase
{
    /** @test */
    public function javascript_assets_are_published_to_correct_location()
    {
        $this->artisan('vendor:publish', [
            '--tag' => 'flashhalt-assets',
            '--force' => true
        ]);

        $expectedPath = public_path('vendor/flashhalt/js/flashhalt.js');
        $this->assertFileExists($expectedPath);

        $content = File::get($expectedPath);
        $this->assertStringContains('FlashHALTIntegration', $content);
        $this->assertStringContains('class FlashHALT', $content);
    }

    /** @test */
    public function config_files_are_published_correctly()
    {
        $this->artisan('vendor:publish', [
            '--tag' => 'flashhalt-config',
            '--force' => true
        ]);

        $expectedPath = config_path('flashhalt.php');
        $this->assertFileExists($expectedPath);

        $config = include $expectedPath;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('mode', $config);
    }

    /** @test */
    public function published_assets_have_correct_permissions()
    {
        $this->artisan('vendor:publish', [
            '--tag' => 'flashhalt-assets',
            '--force' => true
        ]);

        $jsPath = public_path('vendor/flashhalt/js/flashhalt.js');
        $this->assertFileExists($jsPath);
        $this->assertTrue(is_readable($jsPath));
    }
}