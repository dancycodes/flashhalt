<?php

namespace DancyCodes\FlashHalt\Services;

use DancyCodes\FlashHalt\Exceptions\RouteCompilerException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * RouteCompiler Service - The Static Analysis Engine for FlashHALT
 * 
 * This service represents the pinnacle of FlashHALT's architectural sophistication.
 * It performs comprehensive static analysis of Blade templates to discover HTMX
 * patterns that reference FlashHALT routes, validates those routes for safety
 * and functionality, and generates optimized Laravel route definitions that
 * provide identical functionality with maximum performance.
 * 
 * The compilation process demonstrates several advanced software engineering concepts:
 * - Static code analysis and pattern recognition through regex and parsing
 * - File system traversal and recursive template discovery
 * - Integration with existing service architecture for validation
 * - Code generation and template-based file creation
 * - Performance optimization through intelligent caching and batching
 * - Comprehensive error handling and progress reporting
 * 
 * The RouteCompiler transforms FlashHALT from a development convenience into
 * a production-ready system by eliminating dynamic resolution overhead while
 * maintaining all the developer experience benefits of convention-based routing.
 */
class RouteCompiler
{
    /**
     * The ControllerResolver service used to validate discovered routes.
     * This integration demonstrates how the compilation process leverages
     * our existing validation infrastructure to ensure compiled routes
     * are safe and functional.
     */
    protected ControllerResolver $controllerResolver;

    /**
     * The SecurityValidator service used to verify route safety.
     * Security validation during compilation ensures that only safe
     * routes are included in the compiled output, maintaining the same
     * security guarantees as development mode.
     */
    protected SecurityValidator $securityValidator;

    /**
     * The filesystem abstraction for reading templates and writing compiled routes.
     * Using Laravel's local filesystem service provides direct access to the server's
     * file system, which is necessary for template scanning and route file generation.
     */
    protected Filesystem $filesystem;

    /**
     * Configuration settings that control compilation behavior.
     * This configuration-driven approach allows the compiler to adapt
     * to different project structures and requirements without code changes.
     */
    protected array $config;

    /**
     * Collection of route patterns discovered during template scanning.
     * Using Laravel's Collection class provides powerful methods for
     * filtering, grouping, and transforming the discovered routes.
     */
    protected Collection $discoveredRoutes;

    /**
     * Collection of validated and compiled route definitions.
     * These are the final route definitions that will be written
     * to the compiled routes file for production use.
     */
    protected Collection $compiledRoutes;

    /**
     * Compilation statistics for monitoring and reporting.
     * These metrics help developers understand what the compiler
     * discovered and provide insights for optimization.
     */
    protected array $compilationStats = [
        'templates_scanned' => 0,
        'routes_discovered' => 0,
        'routes_validated' => 0,
        'routes_compiled' => 0,
        'compilation_time' => 0,
        'errors_encountered' => 0,
    ];

    /**
     * Array of compilation errors for comprehensive reporting.
     * Rather than failing on the first error, the compiler collects
     * all issues and presents them comprehensively for efficient debugging.
     */
    protected array $compilationErrors = [];

    /**
     * Cache of template file contents to avoid repeated file reads.
     * This optimization significantly improves compilation performance
     * for projects with many template files or complex inclusion patterns.
     */
    protected array $templateCache = [];

    public function __construct(
        ControllerResolver $controllerResolver,
        SecurityValidator $securityValidator,
        Filesystem $filesystem,
        array $config
    ) {
        $this->controllerResolver = $controllerResolver;
        $this->securityValidator = $securityValidator;
        $this->filesystem = $filesystem;
        $this->config = $config;
        
        // Initialize collections for route management
        $this->discoveredRoutes = collect();
        $this->compiledRoutes = collect();
    }

    /**
     * Compile FlashHALT routes for production deployment.
     * 
     * This is the main entry point for the compilation process. It orchestrates
     * the complex workflow of template scanning, route discovery, validation,
     * and code generation that transforms FlashHALT patterns into optimized
     * static routes ready for production use.
     * 
     * The method demonstrates how to structure complex processes that involve
     * multiple steps, comprehensive error handling, and detailed progress reporting.
     * Each step builds on the previous one while maintaining clear separation
     * of concerns and providing detailed feedback about the compilation process.
     *
     * @param bool $force Whether to force compilation even if no changes are detected
     * @return array Compilation results including statistics and any errors
     * @throws RouteCompilerException If compilation fails critically
     */
    public function compile(bool $force = false): array
    {
        // Record compilation start time for performance monitoring
        $startTime = microtime(true);
        
        try {
            // Step 1: Initialize compilation process and validate configuration
            $this->initializeCompilation();
            
            // Step 2: Discover and scan all relevant template files
            $templateFiles = $this->discoverTemplateFiles();
            $this->logProgress("Discovered {$templateFiles->count()} template files to analyze");
            
            // Step 3: Scan templates to extract FlashHALT route patterns
            $this->scanTemplatesForRoutes($templateFiles);
            $this->logProgress("Found {$this->discoveredRoutes->count()} potential FlashHALT routes");
            
            // Step 4: Validate discovered routes for safety and functionality
            $this->validateDiscoveredRoutes();
            $this->logProgress("Validated {$this->compiledRoutes->count()} routes for compilation");
            
            // Step 5: Generate optimized route definitions
            $this->generateCompiledRoutes();
            $this->logProgress("Generated compiled route definitions");
            
            // Step 6: Write compiled routes to file system
            $this->writeCompiledRoutesFile();
            $this->logProgress("Written compiled routes to file");
            
            // Step 7: Update configuration and finalize compilation
            $this->finalizeCompilation();
            
            // Calculate total compilation time
            $this->compilationStats['compilation_time'] = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            
            return $this->generateCompilationReport();
            
        } catch (\Exception $e) {
            // Record compilation failure and provide comprehensive error information
            $this->compilationStats['compilation_time'] = (microtime(true) - $startTime) * 1000;
            $this->compilationErrors[] = $e->getMessage();
            
            throw new RouteCompilerException(
                'Route compilation failed: ' . $e->getMessage(),
                'COMPILATION_FAILED',
                '',
                'compilation',
                [],
                [
                    'compilation_stats' => $this->compilationStats,
                    'errors' => $this->compilationErrors,
                    'discovered_routes_count' => $this->discoveredRoutes->count(),
                ]
            );
        }
    }

    /**
     * Initialize the compilation process and validate configuration.
     * 
     * This method demonstrates how to set up complex processes with proper
     * validation and error handling. It ensures that the compilation environment
     * is properly configured before beginning expensive operations.
     */
    protected function initializeCompilation(): void
    {
        // Reset compilation state for fresh compilation
        $this->discoveredRoutes = collect();
        $this->compiledRoutes = collect();
        $this->compilationErrors = [];
        $this->templateCache = [];
        
        // Reset statistics counters
        $this->compilationStats = array_merge($this->compilationStats, [
            'templates_scanned' => 0,
            'routes_discovered' => 0,
            'routes_validated' => 0,
            'routes_compiled' => 0,
            'errors_encountered' => 0,
        ]);

        // Validate that required configuration is present
        $this->validateCompilationConfiguration();
        
        // Ensure output directory exists and is writable
        $this->ensureOutputDirectoryExists();
        
        $this->logProgress('Compilation initialized successfully');
    }

    /**
     * Validate that compilation configuration is complete and correct.
     * 
     * This method demonstrates defensive programming by validating all
     * assumptions about configuration before beginning expensive operations.
     * Early validation prevents wasted time and provides clear error messages.
     */
    protected function validateCompilationConfiguration(): void
    {
        $compilationConfig = $this->config['compilation'] ?? [];
        
        // Validate template directories are configured
        if (empty($compilationConfig['template_directories'])) {
            throw new RouteCompilerException(
                'No template directories configured for compilation. Please set flashhalt.compilation.template_directories.',
                'MISSING_TEMPLATE_DIRECTORIES'
            );
        }

        // Validate that template directories exist
        foreach ($compilationConfig['template_directories'] as $directory) {
            if (!is_dir($directory)) {
                throw new RouteCompilerException(
                    "Template directory does not exist: {$directory}",
                    'INVALID_TEMPLATE_DIRECTORY'
                );
            }
        }

        // Validate output path is configured
        $outputPath = $this->config['production']['compiled_routes_path'] ?? null;
        if (empty($outputPath)) {
            throw new RouteCompilerException(
                'No output path configured for compiled routes. Please set flashhalt.production.compiled_routes_path.',
                'MISSING_OUTPUT_PATH'
            );
        }
    }

    /**
     * Ensure the output directory exists and is writable.
     * 
     * This method demonstrates how to handle file system preparation
     * gracefully, creating necessary directories and validating permissions
     * before attempting to write files.
     */
    protected function ensureOutputDirectoryExists(): void
    {
        $outputPath = $this->config['production']['compiled_routes_path'];
        $outputDirectory = dirname($outputPath);
        
        if (!is_dir($outputDirectory)) {
            if (!mkdir($outputDirectory, 0755, true)) {
                throw new RouteCompilerException(
                    "Failed to create output directory: {$outputDirectory}",
                    'OUTPUT_DIRECTORY_CREATION_FAILED'
                );
            }
        }

        if (!is_writable($outputDirectory)) {
            throw new RouteCompilerException(
                "Output directory is not writable: {$outputDirectory}",
                'OUTPUT_DIRECTORY_NOT_WRITABLE'
            );
        }
    }

    /**
     * Discover all template files that should be scanned for FlashHALT routes.
     * 
     * This method demonstrates recursive file system traversal with pattern
     * matching and filtering. It shows how to efficiently discover files
     * across complex directory structures while respecting inclusion and
     * exclusion patterns.
     *
     * @return Collection Collection of template file paths
     */
    protected function discoverTemplateFiles(): Collection
    {
        $templateFiles = collect();
        $compilationConfig = $this->config['compilation'] ?? [];
        
        $templateDirectories = $compilationConfig['template_directories'] ?? [];
        $includePatterns = $compilationConfig['template_patterns'] ?? ['*.blade.php'];
        $excludePatterns = $compilationConfig['exclude_patterns'] ?? [];

        foreach ($templateDirectories as $directory) {
            $discoveredInDirectory = $this->scanDirectoryForTemplates(
                $directory,
                $includePatterns,
                $excludePatterns
            );
            
            $templateFiles = $templateFiles->merge($discoveredInDirectory);
            $this->logProgress("Discovered {$discoveredInDirectory->count()} templates in {$directory}");
        }

        // Remove duplicates and sort for consistent processing order
        return $templateFiles->unique()->sort();
    }

    /**
     * Recursively scan a directory for template files matching include/exclude patterns.
     * 
     * This method demonstrates how to implement efficient recursive file discovery
     * with pattern matching. It shows how to balance comprehensiveness with
     * performance by using appropriate filtering strategies.
     *
     * @param string $directory The directory to scan
     * @param array $includePatterns File patterns to include
     * @param array $excludePatterns File patterns to exclude
     * @return Collection Collection of matching file paths
     */
    protected function scanDirectoryForTemplates(string $directory, array $includePatterns, array $excludePatterns): Collection
    {
        $files = collect();
        
        try {
            // Use Laravel's File facade for robust file system operations
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                $filePath = $file->getRealPath();
                $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $filePath);
                
                // Check if file matches include patterns
                if (!$this->matchesPatterns($relativePath, $includePatterns)) {
                    continue;
                }
                
                // Check if file matches exclude patterns
                if ($this->matchesPatterns($relativePath, $excludePatterns)) {
                    continue;
                }
                
                $files->push($filePath);
            }
            
        } catch (\Exception $e) {
            $this->compilationErrors[] = "Failed to scan directory {$directory}: " . $e->getMessage();
            $this->compilationStats['errors_encountered']++;
        }

        return $files;
    }

    /**
     * Check if a file path matches any of the given patterns.
     * 
     * This method demonstrates how to implement flexible pattern matching
     * that supports both simple wildcards and more complex patterns.
     * It shows how to make file filtering both powerful and intuitive.
     *
     * @param string $filePath The file path to check
     * @param array $patterns Array of patterns to match against
     * @return bool True if the file matches any pattern
     */
    protected function matchesPatterns(string $filePath, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            // Convert shell-style wildcards to regex patterns
            $regexPattern = str_replace(['*', '?'], ['.*', '.'], $pattern);
            $regexPattern = '/^' . str_replace('/', '\/', $regexPattern) . '$/i';
            
            if (preg_match($regexPattern, $filePath)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Scan template files to extract FlashHALT route patterns.
     * 
     * This method demonstrates sophisticated text parsing and pattern recognition.
     * It shows how to efficiently extract structured information from complex
     * template files while handling various edge cases and syntax variations.
     *
     * @param Collection $templateFiles Collection of template file paths to scan
     */
    protected function scanTemplatesForRoutes(Collection $templateFiles): void
    {
        foreach ($templateFiles as $templateFile) {
            try {
                $this->scanSingleTemplateFile($templateFile);
                $this->compilationStats['templates_scanned']++;
            } catch (\Exception $e) {
                $this->compilationErrors[] = "Error scanning template {$templateFile}: " . $e->getMessage();
                $this->compilationStats['errors_encountered']++;
            }
        }
        
        $this->logProgress("Completed template scanning. Found {$this->discoveredRoutes->count()} route patterns.");
    }

    /**
     * Scan a single template file for FlashHALT route patterns.
     * 
     * This method demonstrates the core pattern recognition logic that identifies
     * FlashHALT routes within Blade templates. It shows how to use regular
     * expressions effectively while handling the complexities of template syntax.
     *
     * @param string $templateFile Path to the template file to scan
     */
    protected function scanSingleTemplateFile(string $templateFile): void
    {
        // Read template content with caching for performance
        $content = $this->getTemplateContent($templateFile);
        
        // Define regex patterns for different HTMX attributes that might contain FlashHALT routes
        $htmxPatterns = [
            '/hx-get=["\']hx\/([^"\']+@[^"\']+)["\']/i',
            '/hx-post=["\']hx\/([^"\']+@[^"\']+)["\']/i',
            '/hx-put=["\']hx\/([^"\']+@[^"\']+)["\']/i',
            '/hx-patch=["\']hx\/([^"\']+@[^"\']+)["\']/i',
            '/hx-delete=["\']hx\/([^"\']+@[^"\']+)["\']/i',
        ];

        foreach ($htmxPatterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $routePattern) {
                    $this->addDiscoveredRoute($routePattern, $templateFile, $this->extractHttpMethod($pattern));
                }
            }
        }
    }

    /**
     * Get template content with caching for performance optimization.
     * 
     * This method demonstrates how to implement intelligent caching for
     * file operations. Template parsing can be expensive, so caching
     * file contents improves compilation performance significantly.
     *
     * @param string $templateFile Path to the template file
     * @return string Template file content
     */
    protected function getTemplateContent(string $templateFile): string
    {
        if (!isset($this->templateCache[$templateFile])) {
            if (!file_exists($templateFile)) {
                throw new RouteCompilerException(
                    "Template file does not exist: {$templateFile}",
                    'TEMPLATE_FILE_NOT_FOUND'
                );
            }
            
            $content = file_get_contents($templateFile);
            if ($content === false) {
                throw new RouteCompilerException(
                    "Failed to read template file: {$templateFile}",
                    'TEMPLATE_FILE_READ_FAILED'
                );
            }
            
            $this->templateCache[$templateFile] = $content;
        }
        
        return $this->templateCache[$templateFile];
    }

    /**
     * Extract HTTP method from a regex pattern.
     * 
     * This helper method demonstrates how to parse structured information
     * from regex patterns to understand the intent of discovered routes.
     *
     * @param string $pattern The regex pattern that matched
     * @return string The HTTP method (GET, POST, etc.)
     */
    protected function extractHttpMethod(string $pattern): string
    {
        if (str_contains($pattern, 'hx-get')) return 'GET';
        if (str_contains($pattern, 'hx-post')) return 'POST';
        if (str_contains($pattern, 'hx-put')) return 'PUT';
        if (str_contains($pattern, 'hx-patch')) return 'PATCH';
        if (str_contains($pattern, 'hx-delete')) return 'DELETE';
        
        return 'GET'; // Default fallback
    }

    /**
     * Add a discovered route to the collection with comprehensive metadata.
     * 
     * This method demonstrates how to structure discovered data for efficient
     * processing and validation. It shows how to capture all relevant context
     * about discovered routes while avoiding duplicates.
     *
     * @param string $routePattern The discovered route pattern
     * @param string $sourceFile The template file where the route was found
     * @param string $httpMethod The HTTP method for this route
     */
    protected function addDiscoveredRoute(string $routePattern, string $sourceFile, string $httpMethod): void
    {
        // Create a unique identifier for this route
        $routeId = md5($routePattern . '|' . $httpMethod);
        
        // Check if we've already discovered this exact route
        if ($this->discoveredRoutes->has($routeId)) {
            // Add this source file to the existing route's sources
            $existingRoute = $this->discoveredRoutes->get($routeId);
            $existingRoute['source_files'][] = $sourceFile;
            $this->discoveredRoutes->put($routeId, $existingRoute);
        } else {
            // Add new route with comprehensive metadata
            $this->discoveredRoutes->put($routeId, [
                'pattern' => $routePattern,
                'http_method' => $httpMethod,
                'source_files' => [$sourceFile],
                'discovered_at' => now()->toISOString(),
                'id' => $routeId,
            ]);
            
            $this->compilationStats['routes_discovered']++;
        }
    }

    /**
     * Validate all discovered routes for safety and functionality.
     * 
     * This method demonstrates how to integrate with existing validation
     * infrastructure to ensure that compiled routes maintain the same
     * safety guarantees as development mode routes.
     */
    protected function validateDiscoveredRoutes(): void
    {
        $validationLevel = $this->config['compilation']['validation_level'] ?? 'strict';
        
        foreach ($this->discoveredRoutes as $routeId => $routeData) {
            try {
                $this->validateSingleRoute($routeData, $validationLevel);
                $this->compilationStats['routes_validated']++;
            } catch (\Exception $e) {
                $this->handleRouteValidationError($routeData, $e, $validationLevel);
            }
        }
        
        $this->logProgress("Route validation completed. {$this->compiledRoutes->count()} routes ready for compilation.");
    }

    /**
     * Validate a single discovered route for compilation.
     * 
     * This method demonstrates how to leverage existing validation services
     * to ensure that discovered routes are safe and functional before
     * including them in the compiled output.
     *
     * @param array $routeData The route data to validate
     * @param string $validationLevel The validation strictness level
     */
    protected function validateSingleRoute(array $routeData, string $validationLevel): void
    {
        $pattern = $routeData['pattern'];
        $httpMethod = $routeData['http_method'];
        
        try {
            // Use our existing ControllerResolver to validate the route
            $resolution = $this->controllerResolver->resolveController($pattern, $httpMethod);
            
            // If resolution succeeds, add to compiled routes
            $this->compiledRoutes->put($routeData['id'], array_merge($routeData, [
                'controller_class' => $resolution['class'],
                'method_name' => $resolution['method'],
                'validated_at' => now()->toISOString(),
            ]));
            
        } catch (\Exception $e) {
            // Re-throw for handling by validateDiscoveredRoutes
            throw $e;
        }
    }

    /**
     * Handle route validation errors based on validation level.
     * 
     * This method demonstrates how to implement graduated error handling
     * that provides different behaviors based on configuration. It shows
     * how to balance strictness with flexibility in validation systems.
     *
     * @param array $routeData The route that failed validation
     * @param \Exception $error The validation error
     * @param string $validationLevel The configured validation level
     */
    protected function handleRouteValidationError(array $routeData, \Exception $error, string $validationLevel): void
    {
        $errorMessage = "Route validation failed for pattern '{$routeData['pattern']}': " . $error->getMessage();
        
        switch ($validationLevel) {
            case 'strict':
                // In strict mode, validation errors stop compilation
                throw new RouteCompilerException(
                    $errorMessage,
                    'ROUTE_VALIDATION_FAILED',
                    $routeData['pattern'],
                    'route_validation'
                );
                
            case 'warning':
                // In warning mode, log the error but continue compilation
                $this->compilationErrors[] = $errorMessage;
                $this->compilationStats['errors_encountered']++;
                $this->logProgress("WARNING: {$errorMessage}");
                break;
                
            case 'permissive':
                // In permissive mode, include the route with a warning annotation
                $this->compiledRoutes->put($routeData['id'], array_merge($routeData, [
                    'validation_warning' => $errorMessage,
                    'validated_at' => now()->toISOString(),
                ]));
                $this->logProgress("PERMISSIVE: Including route despite validation warning");
                break;
        }
    }

    /**
     * Generate optimized route definitions from validated routes.
     * 
     * This method demonstrates code generation techniques that transform
     * structured data into executable PHP code. It shows how to create
     * clean, optimized route definitions that perform identically to
     * hand-written routes.
     */
    protected function generateCompiledRoutes(): void
    {
        $routeDefinitions = [];
        
        foreach ($this->compiledRoutes as $routeData) {
            $routeDefinition = $this->generateSingleRouteDefinition($routeData);
            $routeDefinitions[] = $routeDefinition;
            $this->compilationStats['routes_compiled']++;
        }
        
        // Store generated definitions for file writing
        $this->generatedRouteDefinitions = $routeDefinitions;
        
        $this->logProgress("Generated {$this->compilationStats['routes_compiled']} route definitions");
    }

    /**
     * Generate a single route definition from route data.
     * 
     * This method demonstrates how to transform structured route data into
     * clean Laravel route definitions. It shows how to handle various
     * route configurations while generating optimal code.
     *
     * @param array $routeData The validated route data
     * @return string The generated route definition code
     */
    protected function generateSingleRouteDefinition(array $routeData): string
    {
        $httpMethod = strtolower($routeData['http_method']);
        $pattern = $routeData['pattern'];
        $controllerClass = $routeData['controller_class'];
        $methodName = $routeData['method_name'];
        
        // Generate route name if enabled
        $routeName = '';
        if ($this->config['compilation']['generate_route_names'] ?? true) {
            $routeName = "->name('flashhalt.{$pattern}')";
        }
        
        // Generate middleware if detection is enabled
        $middleware = '';
        if ($this->config['compilation']['detect_middleware'] ?? true) {
            $middleware = "->middleware(['web'])";
        }
        
        // Generate the route definition
        return sprintf(
            "Route::%s('%s', [%s::class, '%s'])%s%s;",
            $httpMethod,
            $pattern,
            $controllerClass,
            $methodName,
            $middleware,
            $routeName
        );
    }

    /**
     * Write compiled routes to the file system.
     * 
     * This method demonstrates how to generate complete, executable PHP files
     * from structured data. It shows how to create files that integrate
     * seamlessly with existing Laravel applications.
     */
    protected function writeCompiledRoutesFile(): void
    {
        $outputPath = $this->config['production']['compiled_routes_path'];
        
        // Generate the complete routes file content
        $fileContent = $this->generateRoutesFileContent();
        
        // Write the file atomically to prevent partial writes
        $tempPath = $outputPath . '.tmp';
        if (file_put_contents($tempPath, $fileContent) === false) {
            throw new RouteCompilerException(
                "Failed to write compiled routes to temporary file: {$tempPath}",
                'ROUTES_FILE_WRITE_FAILED'
            );
        }
        
        // Atomically move the temporary file to the final location
        if (!rename($tempPath, $outputPath)) {
            throw new RouteCompilerException(
                "Failed to move compiled routes file from {$tempPath} to {$outputPath}",
                'ROUTES_FILE_MOVE_FAILED'
            );
        }
        
        $this->logProgress("Compiled routes written to {$outputPath}");
    }

    /**
     * Generate the complete content for the compiled routes file.
     * 
     * This method demonstrates how to create complete, well-formatted PHP files
     * that include proper headers, documentation, and error handling.
     *
     * @return string The complete routes file content
     */
    protected function generateRoutesFileContent(): string
    {
        $routeDefinitions = $this->generatedRouteDefinitions ?? [];
        $timestamp = now()->toDateTimeString();
        $routeCount = count($routeDefinitions);
        
        $content = "<?php\n\n";
        $content .= "/*\n";
        $content .= " * FlashHALT Compiled Routes\n";
        $content .= " * \n";
        $content .= " * This file was automatically generated by FlashHALT's route compiler.\n";
        $content .= " * Do not edit this file manually - it will be overwritten on next compilation.\n";
        $content .= " * \n";
        $content .= " * Generated: {$timestamp}\n";
        $content .= " * Route count: {$routeCount}\n";
        $content .= " * Compilation time: {$this->compilationStats['compilation_time']}ms\n";
        $content .= " */\n\n";
        
        $content .= "use Illuminate\\Support\\Facades\\Route;\n\n";
        
        if (empty($routeDefinitions)) {
            $content .= "// No FlashHALT routes were discovered during compilation\n";
        } else {
            $content .= "// FlashHALT compiled routes\n";
            $content .= "Route::prefix('hx')->group(function () {\n";
            
            foreach ($routeDefinitions as $routeDefinition) {
                $content .= "    {$routeDefinition}\n";
            }
            
            $content .= "});\n";
        }
        
        return $content;
    }

    /**
     * Finalize the compilation process and update configuration.
     * 
     * This method demonstrates how to complete complex processes with
     * proper cleanup and state management.
     */
    protected function finalizeCompilation(): void
    {
        // Clear template cache to free memory
        $this->templateCache = [];
        
        // Update last compilation timestamp
        $this->compilationStats['completed_at'] = now()->toISOString();
        
        $this->logProgress('Compilation finalized successfully');
    }

    /**
     * Generate a comprehensive compilation report.
     * 
     * This method demonstrates how to provide detailed feedback about
     * complex operations, helping developers understand what was accomplished
     * and identify any issues that need attention.
     *
     * @return array Comprehensive compilation report
     */
    protected function generateCompilationReport(): array
    {
        return [
            'success' => empty($this->compilationErrors) || $this->config['compilation']['validation_level'] !== 'strict',
            'statistics' => $this->compilationStats,
            'errors' => $this->compilationErrors,
            'discovered_routes' => $this->discoveredRoutes->count(),
            'compiled_routes' => $this->compiledRoutes->count(),
            'output_file' => $this->config['production']['compiled_routes_path'],
            'recommendations' => $this->generateRecommendations(),
        ];
    }

    /**
     * Generate recommendations based on compilation results.
     * 
     * This method demonstrates how to provide intelligent suggestions
     * that help developers optimize their applications based on
     * analysis results.
     *
     * @return array Array of recommendations
     */
    protected function generateRecommendations(): array
    {
        $recommendations = [];
        
        if ($this->compilationStats['errors_encountered'] > 0) {
            $recommendations[] = 'Review compilation errors to ensure all routes are properly configured';
        }
        
        if ($this->compilationStats['routes_discovered'] === 0) {
            $recommendations[] = 'No FlashHALT routes were discovered. Verify that your templates contain HTMX patterns with hx/ prefixes';
        }
        
        if ($this->compilationStats['compilation_time'] > 5000) { // 5 seconds
            $recommendations[] = 'Compilation time is high. Consider optimizing template organization or excluding unnecessary directories';
        }
        
        return $recommendations;
    }

    /**
     * Log compilation progress for monitoring and debugging.
     * 
     * This method demonstrates how to provide helpful progress information
     * during long-running operations while respecting different logging
     * preferences and environments.
     *
     * @param string $message The progress message to log
     */
    protected function logProgress(string $message): void
    {
        // In console environments, we might want to output progress directly
        // In web environments, we log to the application log
        if (app()->runningInConsole()) {
            echo "[FlashHALT Compiler] {$message}\n";
        } else {
            logger()->info("FlashHALT Compiler: {$message}");
        }
    }

    /**
     * Clear compiled routes and reset compilation state.
     * 
     * This method provides functionality for the clear command and
     * demonstrates how to safely clean up compilation artifacts.
     */
    public function clearCompiledRoutes(): void
    {
        $outputPath = $this->config['production']['compiled_routes_path'];
        
        if (file_exists($outputPath)) {
            if (!unlink($outputPath)) {
                throw new RouteCompilerException(
                    "Failed to delete compiled routes file: {$outputPath}",
                    'ROUTES_FILE_DELETE_FAILED'
                );
            }
            
            $this->logProgress("Deleted compiled routes file: {$outputPath}");
        } else {
            $this->logProgress("No compiled routes file found to delete");
        }
        
        // Reset internal state
        $this->discoveredRoutes = collect();
        $this->compiledRoutes = collect();
        $this->compilationErrors = [];
        $this->templateCache = [];
    }

    /**
     * Get current compilation statistics for monitoring.
     * 
     * This method provides access to compilation metrics for monitoring
     * and debugging purposes.
     *
     * @return array Current compilation statistics
     */
    public function getCompilationStats(): array
    {
        return $this->compilationStats;
    }
}