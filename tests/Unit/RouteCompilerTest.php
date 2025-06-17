<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Exceptions\RouteCompilerException;
use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Services\RouteCompiler;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use DancyCodes\FlashHalt\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Mockery;

/**
 * RouteCompiler Unit Tests
 * 
 * These tests verify that the RouteCompiler service correctly analyzes Blade
 * templates to discover HTMX patterns and generates optimized route definitions
 * for production deployment. This is FlashHALT's most sophisticated feature,
 * transforming it from a development convenience to a production-ready system.
 * 
 * The testing strategy covers:
 * - Template file discovery and filtering
 * - HTMX pattern extraction from Blade templates
 * - Route validation and controller existence checking
 * - Code generation for optimized route definitions
 * - File writing with atomic operations
 * - Error handling for various compilation scenarios
 * - Performance optimization and caching
 * - Integration with other FlashHALT services
 */
class RouteCompilerTest extends TestCase
{
    private RouteCompiler $compiler;
    private ControllerResolver $controllerResolver;
    private SecurityValidator $securityValidator;
    private Filesystem $filesystem;
    private string $tempViewsPath;

    /**
     * Set up the RouteCompiler with mocked dependencies and a temporary
     * views directory for testing template scanning functionality.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary views directory for testing
        $this->tempViewsPath = sys_get_temp_dir() . '/flashhalt-test-views-' . uniqid();
        mkdir($this->tempViewsPath, 0755, true);
        
        // Mock dependencies to isolate RouteCompiler behavior
        $this->controllerResolver = Mockery::mock(ControllerResolver::class);
        $this->securityValidator = Mockery::mock(SecurityValidator::class);
        $this->filesystem = new Filesystem();
        
        // Configure test environment to use temporary views path
        $this->withFlashHaltConfig([
            'compilation' => [
                'template_directories' => [$this->tempViewsPath],
                'template_patterns' => ['*.blade.php'],
                'exclude_patterns' => ['vendor/*', 'node_modules/*'],
                'validation_level' => 'strict',
            ],
            'production' => [
                'compiled_routes_path' => $this->tempViewsPath . '/compiled-routes.php',
            ],
        ]);
        
        // Create the RouteCompiler instance
        $this->compiler = new RouteCompiler(
            $this->controllerResolver,
            $this->securityValidator,
            $this->filesystem,
            $this->app['config']->get('flashhalt', [])
        );
    }

    /** @test */
    public function it_discovers_template_files_in_configured_directories()
    {
        // Create test template files
        $this->createTestTemplate('users/index', '<div>User list</div>');
        $this->createTestTemplate('posts/show', '<div>Post content</div>');
        $this->createTestTemplate('admin/dashboard', '<div>Admin dashboard</div>');
        
        // Also create non-template files that should be ignored
        file_put_contents($this->tempViewsPath . '/readme.txt', 'Not a template');
        file_put_contents($this->tempViewsPath . '/config.json', '{}');
        
        $result = $this->compiler->compile();
        
        // Should have discovered the Blade templates but ignored other files
        $this->assertArrayHasKey('statistics', $result);
        $this->assertEquals(3, $result['statistics']['templates_scanned']);
    }

    /** @test */
    public function it_extracts_htmx_get_patterns_from_templates()
    {
        $templateContent = '
            <div>
                <button hx-get="hx/users@index" hx-target="#content">Load Users</button>
                <a hx-get="hx/posts@show" hx-target="#main">Show Post</a>
            </div>
        ';
        
        $this->createTestTemplate('test-get', $templateContent);
        
        // Mock controller resolution for discovered routes
        $this->mockControllerResolution('users@index', 'App\\Http\\Controllers\\UsersController', 'index');
        $this->mockControllerResolution('posts@show', 'App\\Http\\Controllers\\PostsController', 'show');
        
        $result = $this->compiler->compile();
        
        $this->assertEquals(2, $result['statistics']['routes_discovered']);
        $this->assertEquals(2, $result['statistics']['routes_compiled']);
    }

    /** @test */
    public function it_extracts_htmx_post_patterns_from_templates()
    {
        $templateContent = '
            <form hx-post="hx/users@store" hx-target="#users">
                <input name="name" type="text">
                <button type="submit">Create User</button>
            </form>
            <button hx-post="hx/posts@create" hx-target="#posts">New Post</button>
        ';
        
        $this->createTestTemplate('test-post', $templateContent);
        
        $this->mockControllerResolution('users@store', 'App\\Http\\Controllers\\UsersController', 'store');
        $this->mockControllerResolution('posts@create', 'App\\Http\\Controllers\\PostsController', 'create');
        
        $result = $this->compiler->compile();
        
        $this->assertEquals(2, $result['statistics']['routes_discovered']);
    }

    /** @test */
    public function it_extracts_all_http_method_patterns()
    {
        $templateContent = '
            <div>
                <button hx-get="hx/users@index">GET</button>
                <button hx-post="hx/users@store">POST</button>
                <button hx-put="hx/users@update">PUT</button>
                <button hx-patch="hx/users@patch">PATCH</button>
                <button hx-delete="hx/users@destroy">DELETE</button>
            </div>
        ';
        
        $this->createTestTemplate('test-methods', $templateContent);
        
        // Mock all the different method resolutions
        $this->mockControllerResolution('users@index', 'App\\Http\\Controllers\\UsersController', 'index');
        $this->mockControllerResolution('users@store', 'App\\Http\\Controllers\\UsersController', 'store');
        $this->mockControllerResolution('users@update', 'App\\Http\\Controllers\\UsersController', 'update');
        $this->mockControllerResolution('users@patch', 'App\\Http\\Controllers\\UsersController', 'patch');
        $this->mockControllerResolution('users@destroy', 'App\\Http\\Controllers\\UsersController', 'destroy');
        
        $result = $this->compiler->compile();
        
        $this->assertEquals(5, $result['statistics']['routes_discovered']);
        $this->assertEquals(5, $result['statistics']['routes_compiled']);
    }

    /** @test */
    public function it_handles_namespaced_controller_patterns()
    {
        $templateContent = '
            <div>
                <button hx-get="hx/admin.users@index">Admin Users</button>
                <button hx-post="hx/api.v1.posts@store">API Create Post</button>
                <button hx-delete="hx/billing.invoices@destroy">Delete Invoice</button>
            </div>
        ';
        
        $this->createTestTemplate('test-namespaced', $templateContent);
        
        $this->mockControllerResolution('admin.users@index', 'App\\Http\\Controllers\\Admin\\UsersController', 'index');
        $this->mockControllerResolution('api.v1.posts@store', 'App\\Http\\Controllers\\Api\\V1\\PostsController', 'store');
        $this->mockControllerResolution('billing.invoices@destroy', 'App\\Http\\Controllers\\Billing\\InvoicesController', 'destroy');
        
        $result = $this->compiler->compile();
        
        $this->assertEquals(3, $result['statistics']['routes_discovered']);
    }

    /** @test */
    public function it_deduplicates_identical_route_patterns()
    {
        $templateContent = '
            <div>
                <button hx-get="hx/users@index" hx-target="#content">Load Users</button>
                <a hx-get="hx/users@index" hx-target="#sidebar">Load Users Again</a>
                <button hx-get="hx/users@index" hx-target="#modal">Load Users Modal</button>
            </div>
        ';
        
        $this->createTestTemplate('test-duplicate', $templateContent);
        
        $this->mockControllerResolution('users@index', 'App\\Http\\Controllers\\UsersController', 'index');
        
        $result = $this->compiler->compile();
        
        // Should only have 1 unique route despite 3 occurrences
        $this->assertEquals(1, $result['statistics']['routes_discovered']);
        $this->assertEquals(1, $result['statistics']['routes_compiled']);
    }

    /** @test */
    public function it_validates_discovered_routes_against_controllers()
    {
        $templateContent = '
            <div>
                <button hx-get="hx/existing@method">Valid Route</button>
                <button hx-post="hx/missing@method">Invalid Route</button>
            </div>
        ';
        
        $this->createTestTemplate('test-validation', $templateContent);
        
        // Mock one successful resolution and one failure
        $this->mockControllerResolution('existing@method', 'App\\Http\\Controllers\\ExistingController', 'method');
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('missing@method', 'POST')
            ->andThrow(new \Exception('Controller not found'));
        
        // Configure for strict validation
        $this->withFlashHaltConfig([
            'compilation' => ['validation_level' => 'strict']
        ]);
        
        $this->expectException(RouteCompilerException::class);
        $this->expectExceptionMessage('Route validation failed');
        
        $this->compiler->compile();
    }

    /** @test */
    public function it_handles_validation_errors_in_warning_mode()
    {
        $templateContent = '
            <div>
                <button hx-get="hx/existing@method">Valid Route</button>
                <button hx-post="hx/missing@method">Invalid Route</button>
            </div>
        ';
        
        $this->createTestTemplate('test-warning', $templateContent);
        
        // Mock one successful resolution and one failure
        $this->mockControllerResolution('existing@method', 'App\\Http\\Controllers\\ExistingController', 'method');
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with('missing@method', 'POST')
            ->andThrow(new \Exception('Controller not found'));
        
        // Configure for warning mode
        $this->withFlashHaltConfig([
            'compilation' => ['validation_level' => 'warning']
        ]);
        
        $result = $this->compiler->compile();
        
        // Should complete compilation but report errors
        $this->assertEquals(2, $result['statistics']['routes_discovered']);
        $this->assertEquals(1, $result['statistics']['routes_compiled']);
        $this->assertGreaterThan(0, count($result['errors']));
    }

    /** @test */
    public function it_generates_optimized_route_definitions()
    {
        $templateContent = '
            <button hx-post="hx/users@store">Create User</button>
            <button hx-patch="hx/users@update">Update User</button>
        ';
        
        $this->createTestTemplate('test-generation', $templateContent);
        
        $this->mockControllerResolution('users@store', 'App\\Http\\Controllers\\UsersController', 'store');
        $this->mockControllerResolution('users@update', 'App\\Http\\Controllers\\UsersController', 'update');
        
        $result = $this->compiler->compile();
        
        // Check that routes file was generated
        $routesPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        $this->assertFileExists($routesPath);
        
        $routesContent = file_get_contents($routesPath);
        
        // Verify generated content structure
        $this->assertStringContainsString('<?php', $routesContent);
        $this->assertStringContainsString('FlashHALT Compiled Routes', $routesContent);
        $this->assertStringContainsString('use Illuminate\\Support\\Facades\\Route;', $routesContent);
        $this->assertStringContainsString('Route::prefix(\'hx\')', $routesContent);
        $this->assertStringContainsString('Route::post(\'users@store\'', $routesContent);
        $this->assertStringContainsString('Route::patch(\'users@update\'', $routesContent);
        $this->assertStringContainsString('[App\\Http\\Controllers\\UsersController::class, \'store\']', $routesContent);
        $this->assertStringContainsString('[App\\Http\\Controllers\\UsersController::class, \'update\']', $routesContent);
    }

    /** @test */
    public function it_includes_route_names_when_configured()
    {
        $templateContent = '<button hx-get="hx/users@index">Users</button>';
        $this->createTestTemplate('test-names', $templateContent);
        
        $this->mockControllerResolution('users@index', 'App\\Http\\Controllers\\UsersController', 'index');
        
        // Configure to generate route names
        $this->withFlashHaltConfig([
            'compilation' => ['generate_route_names' => true]
        ]);
        
        $result = $this->compiler->compile();
        
        $routesPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        $routesContent = file_get_contents($routesPath);
        
        $this->assertStringContainsString('->name(\'flashhalt.users@index\')', $routesContent);
    }

    /** @test */
    public function it_includes_middleware_when_configured()
    {
        $templateContent = '<button hx-post="hx/users@store">Create</button>';
        $this->createTestTemplate('test-middleware', $templateContent);
        
        $this->mockControllerResolution('users@store', 'App\\Http\\Controllers\\UsersController', 'store');
        
        // Configure to detect middleware
        $this->withFlashHaltConfig([
            'compilation' => ['detect_middleware' => true]
        ]);
        
        $result = $this->compiler->compile();
        
        $routesPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        $routesContent = file_get_contents($routesPath);
        
        $this->assertStringContainsString('->middleware([\'web\'])', $routesContent);
    }

    /** @test */
    public function it_excludes_files_matching_exclude_patterns()
    {
        // Create files that should be excluded
        mkdir($this->tempViewsPath . '/vendor', 0755, true);
        file_put_contents($this->tempViewsPath . '/vendor/package.blade.php', '<div>Should be excluded</div>');
        
        mkdir($this->tempViewsPath . '/node_modules', 0755, true);
        file_put_contents($this->tempViewsPath . '/node_modules/component.blade.php', '<div>Should be excluded</div>');
        
        // Create file that should be included
        $this->createTestTemplate('included', '<button hx-get="hx/users@index">Include Me</button>');
        
        $this->mockControllerResolution('users@index', 'App\\Http\\Controllers\\UsersController', 'index');
        
        $result = $this->compiler->compile();
        
        // Should only scan the included file
        $this->assertEquals(1, $result['statistics']['templates_scanned']);
        $this->assertEquals(1, $result['statistics']['routes_discovered']);
    }

    /** @test */
    public function it_handles_empty_template_directories()
    {
        // Create empty directory
        $emptyDir = $this->tempViewsPath . '/empty';
        mkdir($emptyDir, 0755, true);
        
        $this->withFlashHaltConfig([
            'compilation' => ['template_directories' => [$emptyDir]]
        ]);
        
        $result = $this->compiler->compile();
        
        $this->assertEquals(0, $result['statistics']['templates_scanned']);
        $this->assertEquals(0, $result['statistics']['routes_discovered']);
        
        // Should still generate a valid routes file
        $routesPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        $this->assertFileExists($routesPath);
        
        $routesContent = file_get_contents($routesPath);
        $this->assertStringContainsString('No FlashHALT routes were discovered', $routesContent);
    }

    /** @test */
    public function it_uses_atomic_file_writing_to_prevent_corruption()
    {
        $templateContent = '<button hx-get="hx/users@index">Users</button>';
        $this->createTestTemplate('test-atomic', $templateContent);
        
        $this->mockControllerResolution('users@index', 'App\\Http\\Controllers\\UsersController', 'index');
        
        $routesPath = $this->app['config']->get('flashhalt.production.compiled_routes_path');
        
        // Simulate existing file
        file_put_contents($routesPath, 'existing content');
        
        $result = $this->compiler->compile();
        
        // File should be completely replaced with new content
        $routesContent = file_get_contents($routesPath);
        $this->assertStringNotContains('existing content', $routesContent);
        $this->assertStringContainsString('FlashHALT Compiled Routes', $routesContent);
        
        // Verify no temporary files are left behind
        $this->assertFileDoesNotExist($routesPath . '.tmp');
    }

    /** @test */
    public function it_throws_exception_when_output_directory_is_not_writable()
    {
        $templateContent = '<button hx-get="hx/users@index">Users</button>';
        $this->createTestTemplate('test-permissions', $templateContent);
        
        $this->mockControllerResolution('users@index', 'App\\Http\\Controllers\\UsersController', 'index');
        
        // Configure output path to non-writable directory
        $nonWritableDir = $this->tempViewsPath . '/readonly';
        mkdir($nonWritableDir, 0444, true); // Read-only directory
        
        $this->withFlashHaltConfig([
            'production' => ['compiled_routes_path' => $nonWritableDir . '/routes.php']
        ]);
        
        $this->expectException(RouteCompilerException::class);
        $this->expectExceptionMessage('Failed to write compiled routes');
        
        $this->compiler->compile();
    }

    /** @test */
    public function it_provides_comprehensive_compilation_statistics()
    {
        $templateContent = '
            <button hx-get="hx/users@index">Users</button>
            <button hx-post="hx/posts@store">Create Post</button>
        ';
        
        $this->createTestTemplate('test-stats', $templateContent);
        
        $this->mockControllerResolution('users@index', 'App\\Http\\Controllers\\UsersController', 'index');
        $this->mockControllerResolution('posts@store', 'App\\Http\\Controllers\\PostsController', 'store');
        
        $result = $this->compiler->compile();
        
        $this->assertArrayHasKey('statistics', $result);
        $stats = $result['statistics'];
        
        $this->assertArrayHasKey('templates_scanned', $stats);
        $this->assertArrayHasKey('routes_discovered', $stats);
        $this->assertArrayHasKey('routes_validated', $stats);
        $this->assertArrayHasKey('routes_compiled', $stats);
        $this->assertArrayHasKey('compilation_time', $stats);
        $this->assertArrayHasKey('errors_encountered', $stats);
        
        $this->assertEquals(1, $stats['templates_scanned']);
        $this->assertEquals(2, $stats['routes_discovered']);
        $this->assertEquals(2, $stats['routes_compiled']);
        $this->assertIsFloat($stats['compilation_time']);
        $this->assertIsInt($stats['errors_encountered']);
    }

    /** @test */
    public function it_generates_recommendations_based_on_compilation_results()
    {
        // Test with no discovered routes
        $this->createTestTemplate('empty', '<div>No FlashHALT routes here</div>');
        
        $result = $this->compiler->compile();
        
        $this->assertArrayHasKey('recommendations', $result);
        $recommendations = $result['recommendations'];
        
        $this->assertContains(
            'No FlashHALT routes were discovered. Verify that your templates contain HTMX patterns with hx/ prefixes',
            $recommendations
        );
    }

    /** @test */
    public function it_handles_template_file_read_errors_gracefully()
    {
        // Create a file and then make it unreadable
        $unreadableFile = $this->tempViewsPath . '/unreadable.blade.php';
        file_put_contents($unreadableFile, '<div>content</div>');
        chmod($unreadableFile, 0000); // No read permissions
        
        $this->expectException(RouteCompilerException::class);
        $this->expectExceptionMessage('Failed to read template file');
        
        $this->compiler->compile();
    }

    /**
     * Helper method to mock controller resolution for testing.
     * This simplifies test setup by providing a consistent way to mock
     * the complex controller resolution process.
     */
    private function mockControllerResolution(string $pattern, string $controllerClass, string $method): void
    {
        $httpMethod = $this->extractHttpMethodFromPattern($pattern);
        
        $this->controllerResolver->shouldReceive('resolveController')
            ->with($pattern, $httpMethod)
            ->andReturn([
                'controller' => Mockery::mock($controllerClass),
                'method' => $method,
                'class' => $controllerClass,
                'pattern' => $pattern,
            ]);
    }

    /**
     * Extract HTTP method from test context.
     * In real templates, this would be extracted from the hx-* attribute,
     * but for testing we'll use a simple default.
     */
    private function extractHttpMethodFromPattern(string $pattern): string
    {
        // Simple mapping for test purposes
        if (str_contains($pattern, 'store') || str_contains($pattern, 'create')) {
            return 'POST';
        }
        if (str_contains($pattern, 'update')) {
            return 'PATCH';
        }
        if (str_contains($pattern, 'destroy')) {
            return 'DELETE';
        }
        return 'GET';
    }

    /**
     * Helper method to create test template files with proper paths.
     */
    protected function createTestTemplate(string $name, string $content): string
    {
        $templatePath = $this->tempViewsPath . '/' . $name . '.blade.php';
        $directory = dirname($templatePath);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        file_put_contents($templatePath, $content);
        return $templatePath;
    }

    /**
     * Clean up test files and directories after each test.
     */
    protected function tearDown(): void
    {
        // Clean up temporary views directory
        if (is_dir($this->tempViewsPath)) {
            $this->filesystem->deleteDirectory($this->tempViewsPath);
        }
        
        Mockery::close();
        parent::tearDown();
    }
}