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
    protected string $compilationStage;

    /**
     * The route pattern that was being processed when the error occurred.
     * For errors that occur while processing specific routes, this provides
     * crucial context about which route caused the problem, enabling
     * developers to locate and fix the problematic template code.
     */
    protected string $routePattern;

    /**
     * Array of template files that were being processed during the compilation.
     * This information helps developers understand the scope of the compilation
     * that was attempted and can guide debugging efforts when errors occur
     * across multiple templates.
     */
    protected array $templateFiles = [];

    /**
     * Array of routes that were successfully discovered during compilation.
     * Even when compilation fails, this information is valuable because it
     * shows developers which routes were found and processed successfully,
     * helping them understand what worked and what didn't.
     */
    protected array $discoveredRoutes = [];

    /**
     * Array of routes that failed validation during compilation.
     * This provides detailed information about which specific routes
     * encountered problems and what those problems were, enabling
     * targeted fixes rather than general debugging.
     */
    protected array $failedRoutes = [];

    /**
     * Comprehensive compilation statistics and progress information.
     * These metrics help developers understand how far the compilation
     * process progressed before failing and what was accomplished
     * before the error occurred.
     */
    protected array $compilationProgress = [];

    /**
     * Array of detailed error information for complex failure scenarios.
     * When compilation involves multiple types of errors or failures
     * across multiple files, this array provides comprehensive details
     * about each individual problem encountered.
     */
    protected array $detailedErrors = [];

    /**
     * Configuration settings that were active during compilation.
     * Understanding the configuration context is often crucial for
     * debugging compilation issues, especially when errors relate to
     * validation levels, template directories, or output paths.
     */
    protected array $compilationConfig = [];

    /**
     * Create a new route compilation exception with comprehensive context.
     * 
     * This constructor demonstrates how to design exception creation for
     * complex processes that involve multiple types of context information.
     * The extensive parameter list reflects the complexity of compilation
     * processes while providing sensible defaults for simpler error scenarios.
     *
     * @param string $message Human-readable error description
     * @param string $errorCode Structured error code for programmatic handling
     * @param string $routePattern The route pattern being processed (if applicable)
     * @param string $compilationStage The stage where compilation failed
     * @param array $templateFiles Array of template files being processed
     * @param array $context Additional context information for debugging
     */
    public function __construct(
        string $message,
        string $errorCode = 'COMPILATION_FAILED',
        string $routePattern = '',
        string $compilationStage = 'unknown',
        array $templateFiles = [],
        array $context = []
    ) {
        // Call the parent constructor to establish basic exception functionality
        parent::__construct($message, $errorCode, $context);

        // Store compilation-specific information for detailed error reporting
        $this->routePattern = $routePattern;
        $this->compilationStage = $compilationStage;
        $this->templateFiles = $templateFiles;

        // Extract detailed compilation information from context if provided
        $this->discoveredRoutes = $context['discovered_routes'] ?? [];
        $this->failedRoutes = $context['failed_routes'] ?? [];
        $this->compilationProgress = $context['compilation_progress'] ?? [];
        $this->detailedErrors = $context['detailed_errors'] ?? [];
        $this->compilationConfig = $context['compilation_config'] ?? [];
    }

    /**
     * Get the compilation stage where the failure occurred.
     * 
     * Understanding which stage failed helps developers focus their
     * debugging efforts on the right aspect of the compilation process,
     * whether that's template organization, route patterns, controller
     * validation, or code generation issues.
     *
     * @return string The compilation stage identifier
     */
    public function getCompilationStage(): string
    {
        return $this->compilationStage;
    }

    /**
     * Get the route pattern that was being processed when the error occurred.
     * 
     * For errors that occur during route-specific processing, this provides
     * the exact route pattern that caused the problem, enabling developers
     * to locate the problematic code in their templates quickly.
     *
     * @return string The problematic route pattern
     */
    public function getRoutePattern(): string
    {
        return $this->routePattern;
    }

    /**
     * Get the template files that were being processed during compilation.
     * 
     * This information helps developers understand the scope of the
     * compilation attempt and can guide debugging when errors occur
     * across multiple templates or when template discovery itself fails.
     *
     * @return array Array of template file paths
     */
    public function getTemplateFiles(): array
    {
        return $this->templateFiles;
    }

    /**
     * Get the routes that were successfully discovered during compilation.
     * 
     * Even when compilation fails, knowing which routes were discovered
     * successfully helps developers understand what parts of their
     * application are working correctly and focus debugging efforts
     * on the problematic areas.
     *
     * @return array Array of successfully discovered route data
     */
    public function getDiscoveredRoutes(): array
    {
        return $this->discoveredRoutes;
    }

    /**
     * Get the routes that failed validation during compilation.
     * 
     * This provides detailed information about specific validation
     * failures, enabling developers to understand exactly which
     * routes need attention and what problems were detected.
     *
     * @return array Array of failed route data with error details
     */
    public function getFailedRoutes(): array
    {
        return $this->failedRoutes;
    }

    /**
     * Get comprehensive compilation progress and statistics.
     * 
     * Progress information helps developers understand how far
     * the compilation process advanced before failing and what
     * was accomplished successfully before the error occurred.
     *
     * @return array Compilation progress and statistics
     */
    public function getCompilationProgress(): array
    {
        return $this->compilationProgress;
    }

    /**
     * Get detailed error information for complex failure scenarios.
     * 
     * When compilation involves multiple errors or complex failure
     * patterns, this method provides comprehensive details about
     * each individual problem, enabling systematic debugging.
     *
     * @return array Array of detailed error information
     */
    public function getDetailedErrors(): array
    {
        return $this->detailedErrors;
    }

    /**
     * Add information about a failed route to the exception context.
     * 
     * This method allows the compilation process to build up comprehensive
     * information about multiple route failures, creating a complete picture
     * of what went wrong during compilation for more effective debugging.
     *
     * @param string $routePattern The route pattern that failed
     * @param string $errorMessage Description of the validation failure
     * @param array $routeContext Additional context about the failed route
     * @return self Returns self for method chaining
     */
    public function addFailedRoute(string $routePattern, string $errorMessage, array $routeContext = []): self
    {
        $this->failedRoutes[] = [
            'pattern' => $routePattern,
            'error' => $errorMessage,
            'context' => $routeContext,
            'timestamp' => now()->toISOString(),
        ];
        
        return $this;
    }

    /**
     * Add detailed error information to the exception context.
     * 
     * This method enables the compilation process to collect comprehensive
     * information about multiple types of errors, creating detailed reports
     * that help developers understand complex failure scenarios.
     *
     * @param string $errorType The category or type of error encountered
     * @param string $errorMessage Detailed description of the error
     * @param array $errorContext Additional context information for debugging
     * @return self Returns self for method chaining
     */
    public function addDetailedError(string $errorType, string $errorMessage, array $errorContext = []): self
    {
        $this->detailedErrors[] = [
            'type' => $errorType,
            'message' => $errorMessage,
            'context' => $errorContext,
            'stage' => $this->compilationStage,
            'timestamp' => now()->toISOString(),
        ];
        
        return $this;
    }

    /**
     * Update compilation progress information in the exception context.
     * 
     * This method allows the compilation process to provide detailed
     * information about progress and statistics, helping developers
     * understand what was accomplished before the failure occurred.
     *
     * @param array $progressData Updated compilation progress information
     * @return self Returns self for method chaining
     */
    public function updateCompilationProgress(array $progressData): self
    {
        $this->compilationProgress = array_merge($this->compilationProgress, $progressData);
        return $this;
    }

    /**
     * Get the appropriate HTTP status code for compilation failures.
     * 
     * Compilation failures typically indicate server-side processing issues
     * rather than client request problems, so most compilation errors
     * should return 500-level status codes that indicate server errors.
     *
     * @return int HTTP status code appropriate for this compilation failure
     */
    public function getHttpStatusCode(): int
    {
        // Map compilation failure types to appropriate HTTP status codes
        return match ($this->errorCode) {
            'MISSING_TEMPLATE_DIRECTORIES',
            'INVALID_TEMPLATE_DIRECTORY',
            'MISSING_OUTPUT_PATH',
            'OUTPUT_DIRECTORY_CREATION_FAILED',
            'OUTPUT_DIRECTORY_NOT_WRITABLE' => 500, // Internal Server Error - configuration issues
            
            'TEMPLATE_FILE_NOT_FOUND',
            'TEMPLATE_FILE_READ_FAILED' => 500, // Internal Server Error - file system issues
            
            'ROUTE_VALIDATION_FAILED' => 422, // Unprocessable Entity - validation issues
            
            'ROUTES_FILE_WRITE_FAILED',
            'ROUTES_FILE_MOVE_FAILED',
            'ROUTES_FILE_DELETE_FAILED' => 500, // Internal Server Error - file system issues
            
            'COMPILATION_FAILED' => 500, // Internal Server Error - general compilation failure
            
            default => 500 // Default to Internal Server Error for compilation issues
        };
    }

    /**
     * Determine if this compilation failure should be reported to monitoring systems.
     * 
     * Compilation failures are typically important enough to warrant reporting
     * because they indicate issues with application deployment or configuration
     * that could affect production deployments. However, some development-time
     * compilation issues might not need external reporting.
     *
     * @return bool True if this compilation failure should be reported
     */
    public function shouldReport(): bool
    {
        // Development-time compilation issues that might not warrant external reporting
        $developmentErrors = [
            'ROUTE_VALIDATION_FAILED',
            'TEMPLATE_FILE_NOT_FOUND'
        ];

        // In development environments, some compilation failures are expected during debugging
        if (app()->environment(['local', 'development']) && in_array($this->errorCode, $developmentErrors)) {
            return false;
        }

        // Configuration and file system errors should always be reported
        $criticalErrors = [
            'OUTPUT_DIRECTORY_CREATION_FAILED',
            'OUTPUT_DIRECTORY_NOT_WRITABLE',
            'ROUTES_FILE_WRITE_FAILED',
            'ROUTES_FILE_MOVE_FAILED'
        ];

        if (in_array($this->errorCode, $criticalErrors)) {
            return true;
        }

        // Report compilation failures in production environments
        return app()->environment('production');
    }

    /**
     * Initialize compilation-specific error details and suggestions.
     * 
     * This method provides targeted guidance for different types of compilation
     * failures, helping developers understand not just what went wrong during
     * compilation, but how to organize their templates and controllers to
     * work effectively with FlashHALT's compilation system.
     */
    protected function initializeSpecificErrorDetails(): void
    {
        // Add compilation-specific suggestions based on the error code and context
        $this->addCompilationSpecificSuggestions();
        
        // Add documentation links relevant to route compilation
        $this->addDocumentationLink('FlashHALT Compilation Guide', 'https://flashhalt.dev/docs/compilation');
        $this->addDocumentationLink('Template Organization Best Practices', 'https://flashhalt.dev/docs/templates');
        $this->addDocumentationLink('Production Deployment Guide', 'https://flashhalt.dev/docs/deployment');
        
        // Add stage-specific guidance based on where compilation failed
        $this->addStageSpecificGuidance();
        
        // Add analysis of compilation context if available
        $this->addCompilationAnalysis();
    }

    /**
     * Add suggestions specific to the compilation failure type.
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
     * Add specific suggestions for route validation failures during compilation.
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
     * Add specific suggestions for template file reading issues.
     */
    protected function addTemplateReadSuggestions(): void
    {
        $this->addSuggestion('Check file permissions on template directories and files');
        $this->addSuggestion('Ensure template files are not corrupted or locked by other processes');
        $this->addSuggestion('Verify that the web server process has read access to template directories');
        $this->addSuggestion('Consider excluding problematic files using exclude_patterns in compilation configuration');
    }

    /**
     * Add specific suggestions for output directory and file writing issues.
     */
    protected function addOutputDirectorySuggestions(): void
    {
        $this->addSuggestion('Ensure the output directory for compiled routes exists and is writable');
        $this->addSuggestion('Check file permissions on the routes directory and parent directories');
        $this->addSuggestion('Verify that the web server process has write access to the output location');
        $this->addSuggestion('Consider using a different output path if the current location has permission restrictions');
    }

    /**
     * Add specific suggestions for file writing failures.
     */
    protected function addFileWriteSuggestions(): void
    {
        $this->addSuggestion('Check available disk space on the server');
        $this->addSuggestion('Verify file permissions on the target directory');
        $this->addSuggestion('Ensure no other processes are locking the output file');
        $this->addSuggestion('Consider running compilation with elevated permissions if necessary');
    }

    /**
     * Add general suggestions for unspecified compilation failures.
     */
    protected function addGeneralCompilationSuggestions(): void
    {
        $this->addSuggestion('Review the FlashHALT compilation documentation for troubleshooting guidance');
        $this->addSuggestion('Check the Laravel log files for additional error details');
        $this->addSuggestion('Verify that your application follows Laravel controller organization conventions');
        $this->addSuggestion('Consider running compilation with debug mode enabled for more detailed error information');
    }

    /**
     * Add guidance specific to the compilation stage where the failure occurred.
     * 
     * Different compilation stages have different types of issues and require
     * different debugging approaches. This method provides stage-specific
     * guidance that helps developers focus their efforts appropriately.
     */
    protected function addStageSpecificGuidance(): void
    {
        match ($this->compilationStage) {
            'initialization' => $this->addSuggestion(
                'The compilation failed during initialization. Check your FlashHALT configuration in config/flashhalt.php'
            ),
            'template_discovery' => $this->addSuggestion(
                'The compilation failed while discovering template files. Check your template directory configuration and file permissions'
            ),
            'route_extraction' => $this->addSuggestion(
                'The compilation failed while parsing templates for routes. Check for syntax errors in your Blade templates'
            ),
            'route_validation' => $this->addSuggestion(
                'The compilation failed during route validation. Check that referenced controllers and methods exist and are accessible'
            ),
            'code_generation' => $this->addSuggestion(
                'The compilation failed while generating route code. This may indicate issues with route patterns or controller resolution'
            ),
            'file_writing' => $this->addSuggestion(
                'The compilation failed while writing the compiled routes file. Check file permissions and available disk space'
            ),
            default => $this->addSuggestion(
                'The compilation failed at an unknown stage. Review the error details and check your application configuration'
            )
        };
    }

    /**
     * Add analysis of compilation context to provide insights for debugging.
     * 
     * This method analyzes the available compilation context to provide
     * intelligent suggestions and insights that help developers understand
     * the broader context of the compilation failure.
     */
    protected function addCompilationAnalysis(): void
    {
        // Analyze template file context
        if (!empty($this->templateFiles)) {
            $templateCount = count($this->templateFiles);
            $this->addSuggestion("Compilation was processing {$templateCount} template files when the error occurred");
            
            // Provide insights about template organization
            if ($templateCount > 100) {
                $this->addSuggestion('Large number of templates detected. Consider using exclude_patterns to optimize compilation performance');
            }
        }

        // Analyze discovered routes context
        if (!empty($this->discoveredRoutes)) {
            $routeCount = count($this->discoveredRoutes);
            $this->addSuggestion("Successfully discovered {$routeCount} routes before the failure occurred");
        }

        // Analyze failed routes patterns
        if (!empty($this->failedRoutes)) {
            $this->analyzeFailedRoutePatterns();
        }

        // Analyze compilation progress
        if (!empty($this->compilationProgress)) {
            $this->analyzeCompilationProgress();
        }
    }

    /**
     * Analyze patterns in failed routes to provide targeted suggestions.
     * 
     * This method looks for common patterns in route validation failures
     * to provide more specific guidance about what might be causing
     * systematic issues in the application's route organization.
     */
    protected function analyzeFailedRoutePatterns(): void
    {
        $failureReasons = array_column($this->failedRoutes, 'error');
        $reasonCounts = array_count_values($failureReasons);
        
        // Identify the most common failure reasons
        arsort($reasonCounts);
        $mostCommonReason = array_key_first($reasonCounts);
        $mostCommonCount = $reasonCounts[$mostCommonReason];
        
        if ($mostCommonCount > 1) {
            $this->addSuggestion("Most common failure reason: '{$mostCommonReason}' ({$mostCommonCount} routes affected)");
            
            // Provide specific guidance based on common failure patterns
            if (str_contains($mostCommonReason, 'Controller not found')) {
                $this->addSuggestion('Multiple controllers not found suggests possible namespace or naming convention issues');
            } elseif (str_contains($mostCommonReason, 'Method not found')) {
                $this->addSuggestion('Multiple missing methods suggests possible method naming or visibility issues');
            }
        }
    }

    /**
     * Analyze compilation progress to provide insights about the failure.
     * 
     * This method examines compilation statistics and progress information
     * to help developers understand how far the compilation advanced and
     * what might have caused it to fail.
     */
    protected function analyzeCompilationProgress(): void
    {
        $progress = $this->compilationProgress;
        
        if (isset($progress['templates_scanned'], $progress['routes_discovered'])) {
            $templatesScanned = $progress['templates_scanned'];
            $routesDiscovered = $progress['routes_discovered'];
            
            if ($templatesScanned > 0 && $routesDiscovered === 0) {
                $this->addSuggestion('No routes were discovered despite scanning templates. Check that templates contain HTMX patterns with hx/ prefixes');
            } elseif ($routesDiscovered > 0) {
                $this->addSuggestion("Progress: scanned {$templatesScanned} templates and discovered {$routesDiscovered} routes before failure");
            }
        }

        if (isset($progress['compilation_time'])) {
            $compilationTime = $progress['compilation_time'];
            if ($compilationTime > 10000) { // 10 seconds
                $this->addSuggestion('Compilation was taking a long time before failing. Consider optimizing template organization or using exclude patterns');
            }
        }
    }

    /**
     * Create a comprehensive compilation failure report for debugging.
     * 
     * This method generates detailed reports that include all available
     * information about the compilation failure, organized in a way that
     * makes debugging efficient and systematic.
     *
     * @param bool $includeTrace Whether to include stack trace information
     * @return array Comprehensive compilation failure report
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
        $report['compilation_progress'] = $this->compilationProgress;
        $report['detailed_errors'] = $this->detailedErrors;
        $report['compilation_config'] = $this->compilationConfig;
        
        // Add analysis and insights
        $report['failure_analysis'] = $this->generateFailureAnalysis();
        $report['recommendations'] = $this->generateRecommendations();
        
        return $report;
    }

    /**
     * Generate analysis of the compilation failure for debugging insights.
     * 
     * This method creates intelligent analysis of the failure context to
     * help developers understand not just what went wrong, but why it
     * might have happened and what patterns in their application might
     * be contributing to the problem.
     *
     * @return array Analysis results and insights
     */
    protected function generateFailureAnalysis(): array
    {
        $analysis = [
            'failure_stage' => $this->compilationStage,
            'total_templates' => count($this->templateFiles),
            'total_discovered_routes' => count($this->discoveredRoutes),
            'total_failed_routes' => count($this->failedRoutes),
            'total_detailed_errors' => count($this->detailedErrors),
        ];

        // Analyze failure patterns
        if (!empty($this->failedRoutes)) {
            $analysis['failure_patterns'] = $this->analyzeFailurePatterns();
        }

        // Analyze template distribution
        if (!empty($this->templateFiles)) {
            $analysis['template_analysis'] = $this->analyzeTemplateDistribution();
        }

        return $analysis;
    }

    /**
     * Analyze patterns in compilation failures to identify systematic issues.
     */
    protected function analyzeFailurePatterns(): array
    {
        $patterns = [
            'error_types' => [],
            'affected_namespaces' => [],
            'common_issues' => []
        ];

        foreach ($this->failedRoutes as $failure) {
            // Categorize error types
            $errorType = $this->categorizeError($failure['error']);
            $patterns['error_types'][$errorType] = ($patterns['error_types'][$errorType] ?? 0) + 1;

            // Analyze affected namespaces
            if (str_contains($failure['pattern'], '.')) {
                $namespaceParts = explode('.', $failure['pattern']);
                $namespace = $namespaceParts[0];
                $patterns['affected_namespaces'][$namespace] = ($patterns['affected_namespaces'][$namespace] ?? 0) + 1;
            }
        }

        return $patterns;
    }

    /**
     * Categorize error messages into general types for pattern analysis.
     */
    protected function categorizeError(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'Controller not found')) {
            return 'missing_controller';
        } elseif (str_contains($errorMessage, 'Method not found')) {
            return 'missing_method';
        } elseif (str_contains($errorMessage, 'Security validation')) {
            return 'security_violation';
        } elseif (str_contains($errorMessage, 'Pattern')) {
            return 'pattern_error';
        } else {
            return 'other';
        }
    }

    /**
     * Analyze template file distribution to understand application structure.
     */
    protected function analyzeTemplateDistribution(): array
    {
        $distribution = [
            'total_files' => count($this->templateFiles),
            'directory_distribution' => [],
            'file_types' => []
        ];

        foreach ($this->templateFiles as $templateFile) {
            // Analyze directory distribution
            $directory = dirname($templateFile);
            $distribution['directory_distribution'][$directory] = 
                ($distribution['directory_distribution'][$directory] ?? 0) + 1;

            // Analyze file types
            $extension = pathinfo($templateFile, PATHINFO_EXTENSION);
            $distribution['file_types'][$extension] = 
                ($distribution['file_types'][$extension] ?? 0) + 1;
        }

        return $distribution;
    }

    /**
     * Generate actionable recommendations based on the compilation failure.
     * 
     * This method creates specific, actionable recommendations that help
     * developers fix the immediate problem and improve their application
     * organization to prevent similar issues in the future.
     *
     * @return array Array of specific recommendations
     */
    protected function generateRecommendations(): array
    {
        $recommendations = [];

        // Stage-specific recommendations
        match ($this->compilationStage) {
            'initialization' => $recommendations[] = 'Review and validate your FlashHALT configuration settings',
            'template_discovery' => $recommendations[] = 'Check template directory configuration and file permissions',
            'route_extraction' => $recommendations[] = 'Validate Blade template syntax and HTMX pattern format',
            'route_validation' => $recommendations[] = 'Ensure all referenced controllers and methods exist and are accessible',
            'code_generation' => $recommendations[] = 'Review route patterns for compatibility with Laravel routing conventions',
            'file_writing' => $recommendations[] = 'Check file system permissions and available disk space'
        };

        // Failure-specific recommendations
        if (!empty($this->failedRoutes)) {
            $recommendations[] = 'Focus on fixing the failed routes before attempting compilation again';
            $recommendations[] = 'Consider using a less strict validation level during development';
        }

        if (count($this->templateFiles) > 50) {
            $recommendations[] = 'Consider using exclude_patterns to optimize compilation performance';
        }

        return array_unique($recommendations);
    }
}