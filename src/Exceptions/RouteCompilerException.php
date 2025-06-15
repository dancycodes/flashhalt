<?php

namespace DancyCodes\FlashHalt\Exceptions;

/**
 * RouteCompilerException - Specialized Exception for Route Compilation Failures
 * 
 * This exception class handles the complex error scenarios that arise during
 * FlashHALT's route compilation process. Unlike simple runtime errors that
 * involve single requests or operations, compilation errors involve analyzing
 * large amounts of data across multiple files and can fail at various stages
 * of a sophisticated analysis and code generation process.
 * 
 * Compilation error handling presents several unique challenges:
 * - Multiple failure points across different files and compilation stages
 * - Need for comprehensive context about the entire compilation process
 * - Aggregated reporting of multiple errors rather than immediate failure
 * - Complex relationships between templates, routes, controllers, and methods
 * - Code generation failures that require understanding of both input and output
 * 
 * This exception class addresses these challenges by providing:
 * - Comprehensive compilation context and progress information
 * - Categorized error reporting that groups related failures together
 * - Detailed analysis of discovered routes and validation results
 * - Actionable guidance for fixing template organization and controller issues
 * - Integration with the broader FlashHALT error handling architecture
 * 
 * The design demonstrates how to create exception classes that can handle
 * complex, multi-stage processes while maintaining clarity and providing
 * educational value to developers debugging compilation issues.
 */
class RouteCompilerException extends FlashHaltException
{
    /**
     * The compilation stage where the error occurred.
     * This helps developers understand which part of the compilation process
     * failed, enabling them to focus their debugging efforts appropriately.
     * 
     * Possible stages include:
     * - initialization: Configuration validation and setup
     * - template_discovery: Finding and cataloging template files
     * - route_extraction: Parsing templates to discover FlashHALT routes
     * - route_validation: Validating discovered routes for safety and functionality
     * - code_generation: Creating optimized route definitions
     * - file_writing: Writing compiled routes to the filesystem
     */
    protected string $compilationStage = 'unknown';

    /**
     * The route pattern that was being processed when the error occurred.
     * For errors that occur while processing specific routes, this provides
     * crucial context about which route caused the problem, enabling
     * developers to locate and fix the problematic template code.
     */
    protected string $routePattern = '';

    /**
     * Array of template files that were being processed during the compilation.
     * This provides context about which templates were involved in the
     * compilation process and where problems might be located.
     */
    protected array $templateFiles = [];

    /**
     * Array of routes that were discovered during the compilation process.
     * This shows the progress of route discovery and can help identify
     * which routes were successfully discovered before the failure occurred.
     */
    protected array $discoveredRoutes = [];

    /**
     * Array of routes that failed validation during the compilation process.
     * This provides specific information about which routes have problems
     * and what types of validation failures occurred.
     */
    protected array $failedRoutes = [];

    /**
     * Compilation statistics and progress information.
     * This includes metrics about the compilation process such as number
     * of files processed, routes discovered, validation success rates, etc.
     */
    protected array $compilationStats = [];

    /**
     * Additional compilation context that might help with debugging.
     * This could include configuration values, filesystem information,
     * or other details that affect the compilation process.
     */
    protected array $compilationContext = [];

    /**
     * Create a new route compilation exception with comprehensive debugging information.
     * 
     * This constructor demonstrates how to design exception creation for complex,
     * multi-stage processes that can fail at various points. The rich context
     * provided helps developers understand not just what failed, but where in
     * the process it failed and what context led to the failure.
     *
     * @param string $message Human-readable error description
     * @param string $errorCode Structured error code for programmatic handling
     * @param string $compilationStage The compilation stage where failure occurred
     * @param string $routePattern The route pattern being processed (if applicable)
     * @param array $context Additional context information
     */
    public function __construct(
        string $message,
        string $errorCode = 'COMPILATION_FAILED',
        string $compilationStage = 'unknown',
        string $routePattern = '',
        array $context = []
    ) {
        // Store compilation-specific information BEFORE calling parent constructor
        // This is crucial because the parent constructor might call methods that access these properties
        $this->compilationStage = $compilationStage;
        $this->routePattern = $routePattern;

        // Extract compilation context from the provided context array
        $this->templateFiles = $context['template_files'] ?? [];
        $this->discoveredRoutes = $context['discovered_routes'] ?? [];
        $this->failedRoutes = $context['failed_routes'] ?? [];
        $this->compilationStats = $context['compilation_stats'] ?? [];
        $this->compilationContext = $context['compilation_context'] ?? [];

        // Call the parent constructor to set up basic exception functionality
        parent::__construct($message, $errorCode, $context);
    }

    /**
     * Get the compilation stage where the error occurred.
     * 
     * Understanding which stage failed helps developers focus their debugging
     * efforts on the right area and understand which aspect of their project
     * organization might need adjustment.
     *
     * @return string The compilation stage where failure occurred
     */
    public function getCompilationStage(): string
    {
        return $this->compilationStage;
    }

    /**
     * Get the route pattern that was being processed when the error occurred.
     * 
     * For errors that occur during route-specific processing, this provides
     * crucial context about which specific route pattern caused the problem.
     *
     * @return string The problematic route pattern
     */
    public function getRoutePattern(): string
    {
        return $this->routePattern;
    }

    /**
     * Get the array of template files that were involved in the compilation.
     * 
     * This information helps developers understand which templates were
     * being processed and can guide them to the source of compilation problems.
     *
     * @return array Array of template file paths
     */
    public function getTemplateFiles(): array
    {
        return $this->templateFiles;
    }

    /**
     * Add a template file to the list of files being processed.
     * 
     * This method allows the compilation process to build up a comprehensive
     * list of involved files as compilation progresses, providing rich
     * debugging information when compilation ultimately fails.
     *
     * @param string $filePath The template file path
     * @return self Returns self for method chaining
     */
    public function addTemplateFile(string $filePath): self
    {
        if (!in_array($filePath, $this->templateFiles)) {
            $this->templateFiles[] = $filePath;
        }
        return $this;
    }

    /**
     * Get the array of routes that were discovered during compilation.
     * 
     * This shows the progress of route discovery and can help identify
     * patterns in discovered routes or understand compilation scope.
     *
     * @return array Array of discovered route patterns
     */
    public function getDiscoveredRoutes(): array
    {
        return $this->discoveredRoutes;
    }

    /**
     * Add a discovered route to the compilation context.
     * 
     * This method allows tracking of all routes discovered during the
     * compilation process, providing comprehensive debugging information.
     *
     * @param string $routePattern The route pattern that was discovered
     * @param array $routeContext Additional context about the route
     * @return self Returns self for method chaining
     */
    public function addDiscoveredRoute(string $routePattern, array $routeContext = []): self
    {
        $this->discoveredRoutes[] = [
            'pattern' => $routePattern,
            'context' => $routeContext,
            'discovered_at' => microtime(true),
        ];
        return $this;
    }

    /**
     * Get the array of routes that failed validation during compilation.
     * 
     * Failed routes provide specific information about which routes have
     * problems and what types of validation failures occurred.
     *
     * @return array Array of failed route information
     */
    public function getFailedRoutes(): array
    {
        return $this->failedRoutes;
    }

    /**
     * Add a failed route to the compilation context.
     * 
     * This method allows tracking of all routes that failed validation
     * during compilation, providing detailed error information for debugging.
     *
     * @param string $routePattern The route pattern that failed
     * @param string $errorMessage The specific error message
     * @param array $errorContext Additional error context
     * @return self Returns self for method chaining
     */
    public function addFailedRoute(string $routePattern, string $errorMessage, array $errorContext = []): self
    {
        $this->failedRoutes[] = [
            'pattern' => $routePattern,
            'error' => $errorMessage,
            'context' => $errorContext,
            'failed_at' => microtime(true),
        ];
        return $this;
    }

    /**
     * Get compilation statistics and progress information.
     * 
     * Statistics provide quantitative information about the compilation
     * process and can help identify performance issues or scope problems.
     *
     * @return array Compilation statistics
     */
    public function getCompilationStats(): array
    {
        return $this->compilationStats;
    }

    /**
     * Update compilation statistics with new information.
     * 
     * This method allows the compilation process to build up comprehensive
     * statistics about its progress and performance.
     *
     * @param array $stats Statistics to add or update
     * @return self Returns self for method chaining
     */
    public function updateCompilationStats(array $stats): self
    {
        $this->compilationStats = array_merge($this->compilationStats, $stats);
        return $this;
    }

    /**
     * Get additional compilation context information.
     * 
     * Compilation context includes configuration values, environment
     * information, and other details that might affect compilation.
     *
     * @return array Compilation context information
     */
    public function getCompilationContext(): array
    {
        return $this->compilationContext;
    }

    /**
     * Get the appropriate HTTP status code for this compilation error.
     * 
     * Compilation errors typically occur during development or deployment
     * processes rather than runtime request handling, but they may need
     * to provide HTTP responses in certain contexts.
     *
     * @return int Appropriate HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            'MISSING_TEMPLATE_DIRECTORIES' => 404, // Not Found
            'TEMPLATE_FILE_READ_FAILED' => 403, // Forbidden
            'ROUTE_VALIDATION_FAILED' => 422, // Unprocessable Entity
            'OUTPUT_DIRECTORY_CREATION_FAILED' => 500, // Internal Server Error
            'ROUTES_FILE_WRITE_FAILED' => 500, // Internal Server Error
            default => 500 // Default to Internal Server Error for compilation issues
        };
    }

    /**
     * Determine whether this compilation exception should be reported to monitoring systems.
     * 
     * Compilation errors generally indicate application configuration or
     * development issues that should be reported and addressed, but the
     * reporting behavior can be tuned based on error type and environment.
     *
     * @return bool Whether this exception should be reported
     */
    public function shouldReport(): bool
    {
        // Always report file system errors as they indicate configuration problems
        if (in_array($this->errorCode, [
            'OUTPUT_DIRECTORY_CREATION_FAILED',
            'ROUTES_FILE_WRITE_FAILED',
            'TEMPLATE_FILE_READ_FAILED'
        ])) {
            return true;
        }

        // Report route validation failures in production but not necessarily in development
        if ($this->errorCode === 'ROUTE_VALIDATION_FAILED') {
            return app()->environment('production', 'staging');
        }

        // Report missing template directories as configuration issues
        if ($this->errorCode === 'MISSING_TEMPLATE_DIRECTORIES') {
            return true;
        }

        // Default to reporting compilation failures
        return true;
    }

    /**
     * Initialize error-specific details based on the compilation stage and error code.
     * 
     * This method sets up suggestions and documentation links that are specifically
     * relevant to compilation failures, providing educational guidance that helps
     * developers understand and fix compilation problems.
     */
    protected function initializeSpecificErrorDetails(): void
    {
        // Add stage-specific suggestions based on where compilation failed
        match ($this->compilationStage) {
            'initialization' => $this->addInitializationSuggestions(),
            'template_discovery' => $this->addTemplateDiscoverySuggestions(),
            'route_extraction' => $this->addRouteExtractionSuggestions(),
            'route_validation' => $this->addRouteValidationSuggestions(),
            'code_generation' => $this->addCodeGenerationSuggestions(),
            'file_writing' => $this->addFileWritingSuggestions(),
            default => $this->addGeneralCompilationSuggestions()
        };

        // Add error-code-specific suggestions
        $this->addCompilationSpecificSuggestions();
    }

    /**
     * Add suggestions specific to compilation initialization failures.
     */
    protected function addInitializationSuggestions(): void
    {
        $this->addSuggestion('Check FlashHALT configuration in config/flashhalt.php');
        $this->addSuggestion('Verify that all required directories exist and are readable');
        $this->addSuggestion('Ensure the compilation configuration is valid and complete');
        $this->addSuggestion('Check that the application environment supports compilation operations');
    }

    /**
     * Add suggestions specific to template discovery failures.
     */
    protected function addTemplateDiscoverySuggestions(): void
    {
        $this->addSuggestion('Verify that template directories exist and are readable');
        $this->addSuggestion('Check the template_directories configuration setting');
        $this->addSuggestion('Ensure template files have the correct extensions (.blade.php)');
        $this->addSuggestion('Verify filesystem permissions for template directories');
    }

    /**
     * Add suggestions specific to route extraction failures.
     */
    protected function addRouteExtractionSuggestions(): void
    {
        $this->addSuggestion('Check that FlashHALT route patterns in templates are properly formatted');
        $this->addSuggestion('Verify that HTMX attributes use correct FlashHALT syntax');
        $this->addSuggestion('Ensure route patterns follow the controller@method or namespace.controller@method format');
        $this->addSuggestion('Review template files for syntax errors that might affect parsing');
    }

    /**
     * Add suggestions specific to route validation failures.
     */
    protected function addRouteValidationSuggestions(): void
    {
        $this->addSuggestion('Review failed routes and ensure referenced controllers exist in the expected locations');
        $this->addSuggestion('Check that controller methods are public and follow Laravel naming conventions');
        $this->addSuggestion('Verify that route patterns in templates follow the format: controller@method or namespace.controller@method');
        $this->addSuggestion('Consider adjusting the validation level in config/flashhalt.php if using experimental patterns');
        
        // Add specific information about failed routes if available
        if (!empty($this->failedRoutes)) {
            $failedCount = count($this->failedRoutes);
            $this->addSuggestion("Review the {$failedCount} failed routes listed in the detailed error information");
            
            // Show examples of failed routes for context
            $exampleFailures = array_slice($this->failedRoutes, 0, 3);
            foreach ($exampleFailures as $failure) {
                $this->addSuggestion("Failed route example: {$failure['pattern']} - {$failure['error']}");
            }
        }
    }

    /**
     * Add suggestions specific to code generation failures.
     */
    protected function addCodeGenerationSuggestions(): void
    {
        $this->addSuggestion('Check that the output directory exists and is writable');
        $this->addSuggestion('Verify that there is sufficient disk space for generated files');
        $this->addSuggestion('Ensure that the compilation template files are valid and accessible');
        $this->addSuggestion('Check for any syntax errors in the code generation templates');
    }

    /**
     * Add suggestions specific to file writing failures.
     */
    protected function addFileWritingSuggestions(): void
    {
        $this->addSuggestion('Verify that the output directory exists and is writable');
        $this->addSuggestion('Check filesystem permissions for the routes output location');
        $this->addSuggestion('Ensure that there is sufficient disk space available');
        $this->addSuggestion('Verify that no other process is locking the output files');
    }

    /**
     * Add general compilation suggestions that apply to multiple failure types.
     */
    protected function addGeneralCompilationSuggestions(): void
    {
        $this->addSuggestion('Check the compilation configuration in config/flashhalt.php');
        $this->addSuggestion('Verify that all dependencies are properly installed and configured');
        $this->addSuggestion('Consider running the compilation with debug mode enabled for more detailed output');
        $this->addSuggestion('Review the FlashHALT documentation for compilation best practices');
    }

    /**
     * Add specific suggestions for different compilation error types.
     * 
     * This method provides targeted advice for different categories of
     * compilation failures, helping developers understand both what went
     * wrong and how to fix their application organization to work better
     * with FlashHALT's compilation system.
     */
    protected function addCompilationSpecificSuggestions(): void
    {
        match ($this->errorCode) {
            'MISSING_TEMPLATE_DIRECTORIES' => $this->addTemplateDirectorySuggestions(),
            'ROUTE_VALIDATION_FAILED' => $this->addRouteValidationSuggestions(),
            'TEMPLATE_FILE_READ_FAILED' => $this->addTemplateReadSuggestions(),
            'OUTPUT_DIRECTORY_CREATION_FAILED' => $this->addOutputDirectorySuggestions(),
            'ROUTES_FILE_WRITE_FAILED' => $this->addFileWriteSuggestions(),
            'COMPILATION_FAILED' => $this->addGeneralCompilationSuggestions(),
            default => $this->addGeneralCompilationSuggestions()
        };
    }

    /**
     * Add specific suggestions for template directory configuration issues.
     */
    protected function addTemplateDirectorySuggestions(): void
    {
        $this->addSuggestion('Configure template directories in config/flashhalt.php under compilation.template_directories');
        $this->addSuggestion('Ensure all configured template directories exist and are readable');
        $this->addSuggestion('Use absolute paths for template directories to avoid path resolution issues');
        $this->addSuggestion('Consider organizing templates in a consistent directory structure for easier compilation');
    }

    /**
     * Add specific suggestions for template file reading issues.
     */
    protected function addTemplateReadSuggestions(): void
    {
        $this->addSuggestion('Check filesystem permissions for template files');
        $this->addSuggestion('Verify that template files are not corrupted or locked by other processes');
        $this->addSuggestion('Ensure template files contain valid Blade syntax');
        $this->addSuggestion('Consider checking for special characters or encoding issues in template files');
    }

    /**
     * Add specific suggestions for output directory issues.
     */
    protected function addOutputDirectorySuggestions(): void
    {
        $this->addSuggestion('Create the output directory manually and set appropriate permissions');
        $this->addSuggestion('Check that the parent directory is writable');
        $this->addSuggestion('Verify that the configured output path is valid and accessible');
        $this->addSuggestion('Consider using a different output location if the current one has permission issues');
    }

    /**
     * Add specific suggestions for file writing issues.
     */
    protected function addFileWriteSuggestions(): void
    {
        $this->addSuggestion('Check that the output directory has write permissions');
        $this->addSuggestion('Verify that there is sufficient disk space available');
        $this->addSuggestion('Ensure that the output file is not locked by other processes');
        $this->addSuggestion('Consider running the compilation with elevated permissions if necessary');
    }

    /**
     * Create a comprehensive compilation report for debugging purposes.
     * 
     * This method generates detailed reports that include all the information
     * developers need to understand and fix compilation problems, making
     * debugging sessions more efficient and educational.
     *
     * @param bool $includeTrace Whether to include stack trace information
     * @return array Comprehensive compilation report
     */
    public function toCompilationReport(bool $includeTrace = false): array
    {
        $report = parent::toArray($includeTrace);
        
        // Add compilation-specific information
        $report['compilation_stage'] = $this->compilationStage;
        $report['route_pattern'] = $this->routePattern;
        $report['template_files'] = $this->templateFiles;
        $report['discovered_routes'] = $this->discoveredRoutes;
        $report['failed_routes'] = $this->failedRoutes;
        $report['compilation_stats'] = $this->compilationStats;
        $report['compilation_context'] = $this->compilationContext;
        
        // Add analysis and summary information
        $report['compilation_summary'] = $this->getCompilationSummary();
        $report['failure_analysis'] = $this->analyzeCompilationFailure();
        
        return $report;
    }

    /**
     * Get a summary of the compilation process and its results.
     * 
     * This method provides a high-level overview of what the compilation
     * process accomplished before failing, helping developers understand
     * the scope and progress of compilation.
     *
     * @return array Compilation summary information
     */
    protected function getCompilationSummary(): array
    {
        return [
            'stage_reached' => $this->compilationStage,
            'templates_processed' => count($this->templateFiles),
            'routes_discovered' => count($this->discoveredRoutes),
            'routes_failed' => count($this->failedRoutes),
            'success_rate' => $this->calculateSuccessRate(),
            'compilation_time' => $this->compilationStats['compilation_time'] ?? 'unknown',
        ];
    }

    /**
     * Analyze the compilation failure to provide insights about what went wrong.
     * 
     * This method examines the compilation context and provides intelligent
     * analysis about the likely causes of the failure and what developers
     * can do to address them.
     *
     * @return array Failure analysis results
     */
    protected function analyzeCompilationFailure(): array
    {
        $analysis = [];
        
        // Analyze the failure stage
        $analysis['failure_stage'] = $this->compilationStage;
        $analysis['stage_description'] = $this->getStageDescription($this->compilationStage);
        
        // Analyze route failures if applicable
        if (!empty($this->failedRoutes)) {
            $analysis['failed_route_count'] = count($this->failedRoutes);
            $analysis['common_failure_patterns'] = $this->identifyCommonFailurePatterns();
        }
        
        // Analyze template involvement
        if (!empty($this->templateFiles)) {
            $analysis['template_count'] = count($this->templateFiles);
            $analysis['template_analysis'] = $this->analyzeTemplateFiles();
        }
        
        return $analysis;
    }

    /**
     * Calculate the success rate of route processing during compilation.
     * 
     * @return float Success rate as a percentage
     */
    protected function calculateSuccessRate(): float
    {
        $total = count($this->discoveredRoutes);
        $failed = count($this->failedRoutes);
        
        if ($total === 0) {
            return 0.0;
        }
        
        return (($total - $failed) / $total) * 100;
    }

    /**
     * Get a description of what happens during a specific compilation stage.
     * 
     * @param string $stage The compilation stage
     * @return string Description of the stage
     */
    protected function getStageDescription(string $stage): string
    {
        return match ($stage) {
            'initialization' => 'Setting up compilation environment and validating configuration',
            'template_discovery' => 'Scanning directories to find and catalog template files',
            'route_extraction' => 'Parsing templates to extract FlashHALT route patterns',
            'route_validation' => 'Validating discovered routes for safety and functionality',
            'code_generation' => 'Generating optimized route definitions from validated routes',
            'file_writing' => 'Writing compiled route definitions to the filesystem',
            default => 'Unknown compilation stage'
        };
    }

    /**
     * Identify common patterns in route failures to provide targeted guidance.
     * 
     * @return array Common failure patterns and their frequencies
     */
    protected function identifyCommonFailurePatterns(): array
    {
        $patterns = [];
        
        foreach ($this->failedRoutes as $failure) {
            $error = $failure['error'] ?? 'Unknown error';
            
            // Categorize errors into common patterns
            if (str_contains($error, 'not found')) {
                $patterns['not_found'] = ($patterns['not_found'] ?? 0) + 1;
            } elseif (str_contains($error, 'not accessible')) {
                $patterns['not_accessible'] = ($patterns['not_accessible'] ?? 0) + 1;
            } elseif (str_contains($error, 'blacklisted')) {
                $patterns['security_blocked'] = ($patterns['security_blocked'] ?? 0) + 1;
            } else {
                $patterns['other'] = ($patterns['other'] ?? 0) + 1;
            }
        }
        
        return $patterns;
    }

    /**
     * Analyze template files for common issues or patterns.
     * 
     * @return array Template file analysis results
     */
    protected function analyzeTemplateFiles(): array
    {
        $analysis = [
            'total_files' => count($this->templateFiles),
            'file_extensions' => [],
            'directory_distribution' => [],
        ];
        
        foreach ($this->templateFiles as $file) {
            // Analyze file extensions
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $analysis['file_extensions'][$extension] = ($analysis['file_extensions'][$extension] ?? 0) + 1;
            
            // Analyze directory distribution
            $directory = dirname($file);
            $analysis['directory_distribution'][$directory] = ($analysis['directory_distribution'][$directory] ?? 0) + 1;
        }
        
        return $analysis;
    }
}