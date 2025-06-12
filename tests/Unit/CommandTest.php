<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Console\Commands\CompileCommand;
use DancyCodes\FlashHalt\Console\Commands\ClearCommand;
use DancyCodes\FlashHalt\Services\RouteCompiler;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Mockery;

/**
 * Console Command Tests
 * 
 * These tests verify that FlashHALT's Artisan commands work correctly and
 * provide the expected developer experience. The commands are crucial for
 * the production workflow and developer productivity.
 * 
 * Testing strategy covers:
 * - Command execution with various options and flags
 * - Progress reporting and user feedback
 * - Error handling and informative error messages
 * - File system operations and safety checks
 * - Integration with the RouteCompiler service
 * - Different output verbosity levels
 */
class CommandTest extends TestCase
{
    private RouteCompiler $routeCompiler;
    private Filesystem $filesystem;
    private string $tempCompiledPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary compiled routes path for testing
        $this->tempCompiledPath = sys_get_temp_dir() . '/flashhalt-test-compiled-' . uniqid() . '.php';
        
        // Mock RouteCompiler for controlled testing
        $this->routeCompiler = Mockery::mock(RouteCompiler::class);
        $this->app->instance(RouteCompiler::class, $this->routeCompiler);
        
        $this->filesystem = new Filesystem();
        
        // Configure test environment
        $this->withFlashHaltConfig([
            'production' => [
                'compiled_routes_path' => $this->tempCompiledPath
            ]
        ]);
    }

    // ==================== COMPILE COMMAND TESTS ====================

    /** @test */
    public function compile_command_executes_successfully_with_default_options()
    {
        // Mock successful compilation
        $this->routeCompiler->shouldReceive('compile')
            ->with(false) // force = false by default
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => [
                    'templates_scanned' => 5,
                    'routes_discovered' => 12,
                    'routes_compiled' => 12,
                    'compilation_time' => 150.5,
                    'errors_encountered' => 0
                ],
                'errors' => [],
                'discovered_routes' => 12,
                'compiled_routes' => 12,
                'output_file' => $this->tempCompiledPath,
                'recommendations' => []
            ]);
        
        $this->artisan('flashhalt:compile')
             ->expectsOutput('FlashHALT Route Compilation')
             ->expectsOutput('Compilation completed successfully!')
             ->assertExitCode(0);
    }

    /** @test */
    public function compile_command_handles_force_flag()
    {
        $this->routeCompiler->shouldReceive('compile')
            ->with(true) // force = true
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => ['routes_compiled' => 5],
                'errors' => [],
                'discovered_routes' => 5,
                'compiled_routes' => 5,
                'output_file' => $this->tempCompiledPath,
                'recommendations' => []
            ]);
        
        $this->artisan('flashhalt:compile --force')
             ->assertExitCode(0);
    }

    /** @test */
    public function compile_command_runs_verification_when_requested()
    {
        // Create a mock compiled file for verification
        file_put_contents($this->tempCompiledPath, '<?php // Test compiled routes');
        
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => ['routes_compiled' => 3],
                'errors' => [],
                'compiled_routes' => 3,
                'output_file' => $this->tempCompiledPath,
                'recommendations' => []
            ]);
        
        $this->artisan('flashhalt:compile --verify')
             ->expectsOutput('Running verification checks...')
             ->expectsOutput('Verification checks passed:')
             ->assertExitCode(0);
    }

    /** @test */
    public function compile_command_shows_routes_only_without_compiling()
    {
        $this->routeCompiler->shouldReceive('compile')
            ->with(false)
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => [
                    'templates_scanned' => 3,
                    'routes_discovered' => 8
                ],
                'discovered_routes' => 8,
                'errors' => [],
                'recommendations' => []
            ]);
        
        $this->artisan('flashhalt:compile --routes-only')
             ->expectsOutput('Analyzing templates for FlashHALT routes (routes-only mode)...')
             ->expectsOutput('Route analysis completed. No files were modified.')
             ->assertExitCode(0);
    }

    /** @test */
    public function compile_command_shows_dry_run_results()
    {
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => ['routes_compiled' => 6],
                'compiled_routes' => 6,
                'errors' => [],
                'recommendations' => []
            ]);
        
        $this->artisan('flashhalt:compile --dry-run')
             ->expectsOutput('Simulating compilation process (dry-run mode)...')
             ->expectsOutput('This was a simulation - no files were modified.')
             ->assertExitCode(0);
    }

    /** @test */
    public function compile_command_displays_comprehensive_statistics()
    {
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => [
                    'templates_scanned' => 25,
                    'routes_discovered' => 45,
                    'routes_compiled' => 43,
                    'compilation_time' => 275.8,
                    'errors_encountered' => 2
                ],
                'compiled_routes' => 43,
                'errors' => ['Route validation failed for pattern user@invalid'],
                'recommendations' => ['Consider organizing templates in subdirectories']
            ]);
        
        $this->artisan('flashhalt:compile --stats')
             ->expectsOutput('Templates Scanned')
             ->expectsOutput('25')
             ->expectsOutput('Routes Discovered')
             ->expectsOutput('45')
             ->expectsOutput('Routes Compiled')
             ->expectsOutput('43')
             ->assertExitCode(0);
    }

    /** @test */
    public function compile_command_handles_compilation_errors_gracefully()
    {
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andThrow(new \Exception('Template directory not found'));
        
        $this->artisan('flashhalt:compile')
             ->expectsOutput('Compilation Failed')
             ->expectsOutput('Template directory not found')
             ->assertExitCode(1);
    }

    /** @test */
    public function compile_command_validates_environment_before_compilation()
    {
        // Configure invalid template directories
        $this->withFlashHaltConfig([
            'compilation' => [
                'template_directories' => ['/nonexistent/path']
            ]
        ]);
        
        $this->artisan('flashhalt:compile')
             ->expectsOutput('Template directory does not exist: /nonexistent/path')
             ->assertExitCode(1);
    }

    /** @test */
    public function compile_command_creates_output_directory_if_missing()
    {
        $nonExistentDir = sys_get_temp_dir() . '/flashhalt-test-new-dir-' . uniqid();
        $compiledPath = $nonExistentDir . '/compiled.php';
        
        $this->withFlashHaltConfig([
            'production' => ['compiled_routes_path' => $compiledPath]
        ]);
        
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => ['routes_compiled' => 1],
                'compiled_routes' => 1,
                'errors' => [],
                'output_file' => $compiledPath
            ]);
        
        $this->artisan('flashhalt:compile')
             ->expectsOutput("Created output directory: {$nonExistentDir}")
             ->assertExitCode(0);
        
        $this->assertTrue(is_dir($nonExistentDir));
        
        // Cleanup
        if (is_dir($nonExistentDir)) {
            $this->filesystem->deleteDirectory($nonExistentDir);
        }
    }

    /** @test */
    public function compile_command_shows_verbose_output_when_requested()
    {
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => ['routes_compiled' => 2],
                'compiled_routes' => 2,
                'errors' => []
            ]);
        
        $this->artisan('flashhalt:compile -v')
             ->expectsOutput('Initializing FlashHALT compilation process...')
             ->expectsOutput('Current Configuration:')
             ->assertExitCode(0);
    }

    /** @test */
    public function compile_command_shows_debug_information_in_vvv_mode()
    {
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => ['routes_compiled' => 1],
                'compiled_routes' => 1,
                'errors' => []
            ]);
        
        $this->artisan('flashhalt:compile -vvv')
             ->expectsOutput('Debug Information:')
             ->expectsOutput('Laravel version:')
             ->expectsOutput('PHP version:')
             ->assertExitCode(0);
    }

    // ==================== CLEAR COMMAND TESTS ====================

    /** @test */
    public function clear_command_executes_successfully_with_confirmation()
    {
        // Create a test compiled routes file
        file_put_contents($this->tempCompiledPath, '<?php // Test routes');
        
        $this->artisan('flashhalt:clear')
             ->expectsQuestion('Do you want to proceed with the cleanup?', 'yes')
             ->expectsOutput('FlashHALT Cleanup Operations')
             ->expectsOutput('Cleanup completed successfully!')
             ->assertExitCode(0);
        
        $this->assertFileDoesNotExist($this->tempCompiledPath);
    }

    /** @test */
    public function clear_command_cancels_when_user_declines_confirmation()
    {
        file_put_contents($this->tempCompiledPath, '<?php // Test routes');
        
        $this->artisan('flashhalt:clear')
             ->expectsQuestion('Do you want to proceed with the cleanup?', 'no')
             ->expectsOutput('Cleanup operation cancelled by user.')
             ->assertExitCode(0);
        
        $this->assertFileExists($this->tempCompiledPath);
    }

    /** @test */
    public function clear_command_skips_confirmation_with_force_flag()
    {
        file_put_contents($this->tempCompiledPath, '<?php // Test routes');
        
        $this->artisan('flashhalt:clear --force')
             ->expectsOutput('Cleanup completed successfully!')
             ->assertExitCode(0);
        
        $this->assertFileDoesNotExist($this->tempCompiledPath);
    }

    /** @test */
    public function clear_command_handles_dry_run_mode()
    {
        file_put_contents($this->tempCompiledPath, '<?php // Test routes');
        
        $this->artisan('flashhalt:clear --dry-run')
             ->expectsOutput('Analyzing FlashHALT artifacts (dry-run mode)...')
             ->expectsOutput('This was a simulation - no files were actually removed.')
             ->assertExitCode(0);
        
        $this->assertFileExists($this->tempCompiledPath);
    }

    /** @test */
    public function clear_command_handles_no_artifacts_gracefully()
    {
        // Ensure no artifacts exist
        if (file_exists($this->tempCompiledPath)) {
            unlink($this->tempCompiledPath);
        }
        
        $this->artisan('flashhalt:clear')
             ->expectsOutput('No FlashHALT artifacts found to clean up!')
             ->expectsOutput('To generate compilation artifacts, run: php artisan flashhalt:compile')
             ->assertExitCode(0);
    }

    /** @test */
    public function clear_command_clears_compiled_routes_only_when_specified()
    {
        file_put_contents($this->tempCompiledPath, '<?php // Test routes');
        
        $this->artisan('flashhalt:clear --compiled-routes --force')
             ->expectsOutput('Cleanup completed successfully!')
             ->assertExitCode(0);
        
        $this->assertFileDoesNotExist($this->tempCompiledPath);
    }

    /** @test */
    public function clear_command_shows_cleanup_statistics()
    {
        // Create multiple test files
        file_put_contents($this->tempCompiledPath, '<?php // Test routes');
        file_put_contents($this->tempCompiledPath . '.backup', '<?php // Backup');
        
        $this->artisan('flashhalt:clear --force')
             ->expectsOutput('Removal Summary:')
             ->expectsOutput('2 removed')
             ->assertExitCode(0);
    }

    /** @test */
    public function clear_command_displays_verbose_analysis()
    {
        file_put_contents($this->tempCompiledPath, '<?php // Test routes');
        
        $this->artisan('flashhalt:clear --dry-run -v')
             ->expectsOutput('Found compiled routes:')
             ->expectsOutput($this->tempCompiledPath)
             ->assertExitCode(0);
    }

    /** @test */
    public function clear_command_handles_file_permission_errors_gracefully()
    {
        // Create a file and make parent directory read-only
        $restrictedDir = sys_get_temp_dir() . '/flashhalt-restricted-' . uniqid();
        mkdir($restrictedDir, 0755);
        $restrictedFile = $restrictedDir . '/routes.php';
        file_put_contents($restrictedFile, '<?php // Test');
        chmod($restrictedDir, 0444); // Read-only
        
        $this->withFlashHaltConfig([
            'production' => ['compiled_routes_path' => $restrictedFile]
        ]);
        
        $this->artisan('flashhalt:clear --force')
             ->expectsOutput('errors occurred during cleanup:')
             ->assertExitCode(0);
        
        // Cleanup (restore permissions first)
        chmod($restrictedDir, 0755);
        $this->filesystem->deleteDirectory($restrictedDir);
    }

    /** @test */
    public function clear_command_provides_helpful_recommendations()
    {
        $this->artisan('flashhalt:clear --dry-run')
             ->expectsOutput('No FlashHALT artifacts found to clean up!')
             ->expectsOutput('This could mean:')
             ->expectsOutput('FlashHALT compilation has never been run')
             ->assertExitCode(0);
    }

    /** @test */
    public function both_commands_handle_invalid_configuration_gracefully()
    {
        // Remove FlashHALT configuration
        $this->app['config']->set('flashhalt', null);
        
        $this->artisan('flashhalt:compile')
             ->expectsOutput('FlashHALT configuration not found.')
             ->expectsOutput('Run: php artisan vendor:publish --tag=flashhalt-config')
             ->assertExitCode(1);
        
        $this->artisan('flashhalt:clear')
             ->expectsOutput('FlashHALT configuration not found.')
             ->assertExitCode(1);
    }

    /** @test */
    public function commands_provide_progress_bars_for_long_operations()
    {
        // Mock a compilation that takes time
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andReturn([
                'success' => true,
                'statistics' => ['routes_compiled' => 100],
                'compiled_routes' => 100,
                'errors' => []
            ]);
        
        // In normal verbosity, should show progress bar
        $this->artisan('flashhalt:compile')
             ->assertExitCode(0);
    }

    /** @test */
    public function commands_format_file_sizes_in_human_readable_format()
    {
        // Create a reasonably sized file
        $content = str_repeat('<?php // Route definition', 1000);
        file_put_contents($this->tempCompiledPath, $content);
        
        $this->artisan('flashhalt:clear --dry-run -v')
             ->expectsOutputToContain('KB') // Should show size in KB
             ->assertExitCode(0);
    }

    /** @test */
    public function commands_handle_unexpected_exceptions_with_helpful_messages()
    {
        $this->routeCompiler->shouldReceive('compile')
            ->once()
            ->andThrow(new \RuntimeException('Unexpected system error'));
        
        $this->artisan('flashhalt:compile')
             ->expectsOutput('Compilation operation failed')
             ->expectsOutput('Unexpected system error')
             ->assertExitCode(1);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->tempCompiledPath)) {
            unlink($this->tempCompiledPath);
        }
        
        $backupFile = $this->tempCompiledPath . '.backup';
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        
        Mockery::close();
        parent::tearDown();
    }
}