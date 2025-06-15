<?php

namespace DancyCodes\FlashHalt\Exceptions;

/**
 * ControllerResolutionException - Specialized Exception for Controller Resolution Failures
 * 
 * This exception class handles the complex error scenarios that arise when FlashHALT
 * attempts to resolve route patterns into controller method calls. Controller resolution
 * is a multi-step process involving pattern parsing, namespace resolution, class
 * discovery, method validation, and service container instantiation - any of which
 * can fail for various reasons.
 * 
 * The resolution process demonstrates several sophisticated programming concepts:
 * - Dynamic class loading and namespace resolution
 * - Laravel service container integration for dependency injection
 * - Multi-layer validation and security checking
 * - Comprehensive error context collection for debugging
 * - Performance optimization through intelligent caching
 * 
 * This exception class provides detailed context about resolution failures, making
 * it possible for developers to understand not just what went wrong, but why it
 * went wrong and how to fix their controller organization to work better with
 * FlashHALT's conventions. The rich context also enables FlashHALT to provide
 * intelligent suggestions and educational guidance.
 */
class ControllerResolutionException extends FlashHaltException
{
    /**
     * The route pattern that failed to resolve.
     * This provides crucial context about the original input that caused
     * the resolution failure, enabling pattern analysis and suggestion generation.
     */
    protected string $routePattern = '';

    /**
     * The step in the resolution process where the failure occurred.
     * This helps developers understand which part of the resolution pipeline
     * failed, focusing their debugging efforts on the right area.
     * 
     * Possible values include:
     * - pattern_parsing: Failed to parse the route pattern format
     * - namespace_resolution: Failed to resolve controller namespace
     * - class_discovery: Failed to find the controller class
     * - method_validation: Failed to validate the controller method
     * - security_validation: Failed security validation checks
     * - controller_instantiation: Failed to create controller instance
     */
    protected string $resolutionStep = 'unknown';

    /**
     * Array of controller class names that were attempted during resolution.
     * This shows the progression of resolution attempts, helping developers
     * understand how FlashHALT's namespace resolution algorithm works and
     * where it's looking for their controllers.
     */
    protected array $attemptedClasses = [];

    /**
     * Array of namespaces that were attempted during controller resolution.
     * This helps developers understand how FlashHALT's namespace resolution
     * works and can guide them toward organizing their controllers in ways
     * that work well with FlashHALT's conventions.
     */
    protected array $attemptedNamespaces = [];

    /**
     * Additional resolution context that might help with debugging.
     * This could include configuration values, application environment
     * information, or other details that affect controller resolution.
     */
    protected array $resolutionContext = [];

    /**
     * Create a new controller resolution exception with comprehensive debugging information.
     * 
     * This constructor demonstrates how to design exception creation that captures
     * the maximum amount of useful debugging information while maintaining clear,
     * understandable error messages. The rich context provided by this constructor
     * transforms vague "controller not found" errors into specific, actionable guidance.
     *
     * @param string $message Human-readable error description
     * @param string $errorCode Structured error code for programmatic handling
     * @param string $routePattern The route pattern that failed to resolve
     * @param string $resolutionStep The step where resolution failed
     * @param array $attemptedClasses Array of class names that were tried
     * @param array $context Additional context information
     */
    public function __construct(
        string $message,
        string $errorCode = 'RESOLUTION_FAILED',
        string $routePattern = '',
        string $resolutionStep = 'unknown',
        array $attemptedClasses = [],
        array $context = []
    ) {
        // Call the parent constructor to set up basic exception functionality
        parent::__construct($message, $errorCode, $context);

        // Store resolution-specific information
        $this->routePattern = $routePattern;
        $this->resolutionStep = $resolutionStep;
        $this->attemptedClasses = $attemptedClasses;

        // Extract additional information from context if provided
        $this->attemptedNamespaces = $context['attempted_namespaces'] ?? [];
        $this->resolutionContext = $context['resolution_context'] ?? [];
    }

    /**
     * Get the route pattern that failed to resolve.
     * 
     * This method provides access to the original route pattern that caused
     * the resolution failure, enabling code to analyze patterns for common
     * issues or to provide pattern-specific guidance to developers.
     *
     * @return string The problematic route pattern
     */
    public function getRoutePattern(): string
    {
        return $this->routePattern;
    }

    /**
     * Get the resolution step where the failure occurred.
     * 
     * Understanding which step failed helps developers focus their debugging
     * efforts on the right area and understand which aspect of their
     * controller organization might need adjustment.
     *
     * @return string The resolution step where failure occurred
     */
    public function getResolutionStep(): string
    {
        return $this->resolutionStep;
    }

    /**
     * Get the array of controller classes that were attempted during resolution.
     * 
     * This information shows developers exactly which class names FlashHALT
     * tried to locate, helping them understand how the namespace resolution
     * algorithm works and where their controllers should be placed.
     *
     * @return array Array of attempted controller class names
     */
    public function getAttemptedClasses(): array
    {
        return $this->attemptedClasses;
    }

    /**
     * Add a controller class name to the list of attempted classes.
     * 
     * This method allows the resolution process to build up a comprehensive
     * list of attempted classes as resolution progresses, providing rich
     * debugging information when resolution ultimately fails.
     *
     * @param string $className The controller class name that was attempted
     * @return self Returns self for method chaining
     */
    public function addAttemptedClass(string $className): self
    {
        if (!in_array($className, $this->attemptedClasses)) {
            $this->attemptedClasses[] = $className;
        }
        return $this;
    }

    /**
     * Get the array of namespaces that were attempted during resolution.
     * 
     * Namespace information helps developers understand how FlashHALT's
     * namespace resolution works and can guide controller organization.
     *
     * @return array Array of attempted namespace patterns
     */
    public function getAttemptedNamespaces(): array
    {
        return $this->attemptedNamespaces;
    }

    /**
     * Add a namespace to the list of attempted namespaces.
     * 
     * This method allows tracking of all namespaces that were tried during
     * the resolution process, providing comprehensive debugging information.
     *
     * @param string $namespace The namespace that was attempted
     * @return self Returns self for method chaining
     */
    public function addAttemptedNamespace(string $namespace): self
    {
        if (!in_array($namespace, $this->attemptedNamespaces)) {
            $this->attemptedNamespaces[] = $namespace;
        }
        return $this;
    }

    /**
     * Get additional resolution context information.
     * 
     * Resolution context includes configuration values, environment
     * information, and other details that might affect resolution.
     *
     * @return array Resolution context information
     */
    public function getResolutionContext(): array
    {
        return $this->resolutionContext;
    }

    /**
     * Get the appropriate HTTP status code for this resolution error.
     * 
     * Different types of resolution failures should result in different
     * HTTP status codes to provide appropriate feedback to clients
     * and debugging tools.
     *
     * @return int Appropriate HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            'PATTERN_INVALID', 'INVALID_ROUTE_FORMAT' => 400, // Bad Request
            'CONTROLLER_NOT_FOUND', 'METHOD_NOT_FOUND' => 404, // Not Found
            'CONTROLLER_NOT_ACCESSIBLE', 'METHOD_NOT_ACCESSIBLE' => 403, // Forbidden
            'CONTROLLER_INSTANTIATION_FAILED' => 500, // Internal Server Error
            default => 500 // Default to Internal Server Error
        };
    }

    /**
     * Determine whether this exception should be reported to monitoring systems.
     * 
     * Resolution exceptions in development should generally be reported to help
     * developers fix their applications, while in production they should be
     * logged but might not need immediate alerting depending on the error type.
     *
     * @return bool Whether this exception should be reported
     */
    public function shouldReport(): bool
    {
        // Don't report obvious client errors like invalid patterns
        if (in_array($this->errorCode, ['PATTERN_INVALID', 'INVALID_ROUTE_FORMAT'])) {
            return false;
        }

        // Report server-side issues that indicate application problems
        return match ($this->resolutionStep) {
            'pattern_parsing' => false, // Client error
            'namespace_resolution', 'class_discovery', 'method_validation' => true, // Application issues
            'controller_instantiation' => true, // Server configuration issues
            default => true
        };
    }

    /**
     * Initialize error-specific details based on the resolution step and error code.
     * 
     * This method sets up suggestions and documentation links that are specifically
     * relevant to controller resolution failures, providing educational guidance
     * that helps developers understand and fix resolution problems.
     */
    protected function initializeSpecificErrorDetails(): void
    {
        // Add resolution-specific suggestions based on the resolution step
        match ($this->resolutionStep) {
            'pattern_parsing' => $this->addPatternParsingSuggestions(),
            'namespace_resolution' => $this->addNamespaceResolutionSuggestions(),
            'class_discovery' => $this->addClassDiscoverySuggestions(),
            'method_validation' => $this->addMethodValidationSuggestions(),
            'security_validation' => $this->addSecurityValidationSuggestions(),
            'controller_instantiation' => $this->addControllerInstantiationSuggestions(),
            default => $this->addGeneralResolutionSuggestions()
        };

        // Add error-code-specific suggestions
        match ($this->errorCode) {
            'PATTERN_INVALID' => $this->addSuggestion(
                'Check that your route pattern follows the format: controller@method or namespace.controller@method'
            ),
            'CONTROLLER_NOT_FOUND' => $this->addSuggestion(
                'Verify that your controller exists in the expected location and follows Laravel naming conventions'
            ),
            'METHOD_NOT_FOUND' => $this->addSuggestion(
                'Ensure the method exists, is public, and follows Laravel method naming conventions'
            ),
            'CONTROLLER_INSTANTIATION_FAILED' => $this->addSuggestion(
                'Check constructor dependencies and service container bindings for the controller'
            ),
            // ADD THESE LINES:
            'RESOLUTION_FAILED', 'TEST_CODE' => $this->addSuggestion(
                'Check the resolution context and verify your route pattern and controller organization'
            ),
            // ADD THIS LINE:
            default => null
        };
    }

    /**
     * Add suggestions specific to pattern parsing failures.
     */
    protected function addPatternParsingSuggestions(): void
    {
        $this->addSuggestion('Ensure your route pattern contains exactly one @ symbol separating controller and method');
        $this->addSuggestion('Use dots (.) to separate namespace segments: admin.users@create');
        $this->addSuggestion('Avoid special characters other than dots, hyphens, and underscores in route patterns');
        $this->addSuggestion('Check that both controller and method parts are present and non-empty');
    }

    /**
     * Add suggestions specific to namespace resolution failures.
     */
    protected function addNamespaceResolutionSuggestions(): void
    {
        $this->addSuggestion('Verify that your controllers are in the App\\Http\\Controllers namespace');
        $this->addSuggestion('Check that namespace segments match your actual directory structure');
        $this->addSuggestion('Ensure controller class names are properly capitalized (PascalCase)');
        $this->addSuggestion('Consider adding your namespace to the allowed_namespaces configuration');
    }

    /**
     * Add suggestions specific to class discovery failures.
     */
    protected function addClassDiscoverySuggestions(): void
    {
        $this->addSuggestion('Check that the controller file exists in the expected directory');
        $this->addSuggestion('Verify that the controller class name matches the filename');
        $this->addSuggestion('Ensure the controller extends Illuminate\\Routing\\Controller');
        $this->addSuggestion('Run composer dump-autoload to refresh the autoloader');
        
        if (!empty($this->attemptedClasses)) {
            $this->addSuggestion('Attempted classes: ' . implode(', ', array_slice($this->attemptedClasses, 0, 3)));
        }
    }

    /**
     * Add suggestions specific to method validation failures.
     */
    protected function addMethodValidationSuggestions(): void
    {
        $this->addSuggestion('Ensure the method is public and not static');
        $this->addSuggestion('Check that the method name follows Laravel conventions (camelCase)');
        $this->addSuggestion('Verify that the method is not a magic method or constructor');
        $this->addSuggestion('Consider if the method should be in the method_whitelist configuration');
    }

    /**
     * Add suggestions specific to security validation failures.
     */
    protected function addSecurityValidationSuggestions(): void
    {
        $this->addSuggestion('Check that the method is not in the security blacklist');
        $this->addSuggestion('Verify that the method name doesn\'t match blocked patterns');
        $this->addSuggestion('Ensure the method is appropriate for HTTP access');
        $this->addSuggestion('Review the security configuration in config/flashhalt.php');
    }

    /**
     * Add suggestions specific to controller instantiation failures.
     */
    protected function addControllerInstantiationSuggestions(): void
    {
        $this->addSuggestion('Check that all constructor dependencies are properly bound in the service container');
        $this->addSuggestion('Verify that constructor parameters have appropriate type hints');
        $this->addSuggestion('Ensure any required services are available and properly configured');
        $this->addSuggestion('Check Laravel logs for detailed dependency injection errors');
    }

    /**
     * Add general resolution suggestions that apply to multiple failure types.
     */
    protected function addGeneralResolutionSuggestions(): void
    {
        $this->addSuggestion('Check the FlashHALT configuration in config/flashhalt.php');
        $this->addSuggestion('Verify that FlashHALT is enabled and properly configured');
        $this->addSuggestion('Consider using php artisan flashhalt:compile to check for issues');
        $this->addSuggestion('Review the FlashHALT documentation for controller organization best practices');
    }

    /**
     * Add specific suggestions for controller instantiation errors.
     */
    protected function addInstantiationSuggestions(): void
    {
        $this->addSuggestion('Check that all constructor dependencies are properly bound in the service container');
        $this->addSuggestion('Verify that constructor parameters have appropriate type hints');
        $this->addSuggestion('Ensure any required services are available and properly configured');
        $this->addSuggestion('Check Laravel logs for detailed dependency injection errors');
    }

    /**
     * Add specific suggestions for controller inheritance errors.
     */
    protected function addInheritanceSuggestions(): void
    {
        $this->addSuggestion('Ensure your controller extends Illuminate\\Routing\\Controller');
        $this->addSuggestion('Check that the controller is not abstract');
        $this->addSuggestion('Verify that the controller is a concrete class, not an interface or trait');
    }

    /**
     * Add specific suggestions for controller whitelist errors.
     */
    protected function addWhitelistSuggestions(): void
    {
        $this->addSuggestion('Add your controller namespace to allowed_namespaces in config/flashhalt.php');
        $this->addSuggestion('Move your controller to an allowed namespace like App\\Http\\Controllers');
        $this->addSuggestion('Verify that your controller is in the correct directory structure');
        $this->addSuggestion('Check that the namespace whitelist includes all necessary controller locations');
    }

    /**
     * Add guidance specific to the resolution step where the failure occurred.
     */
    protected function addStepSpecificGuidance(): void
    {
        match ($this->resolutionStep) {
            'pattern_parsing' => $this->addSuggestion('Check your route pattern format and ensure it follows FlashHALT conventions'),
            'namespace_resolution' => $this->addSuggestion('Verify that your controller namespace matches your directory structure'),
            'class_discovery' => $this->addSuggestion('Ensure your controller file exists and can be autoloaded'),
            'method_validation' => $this->addSuggestion('Check that the method exists, is public, and follows naming conventions'),
            'security_validation' => $this->addSuggestion('Review security settings and ensure the method is allowed'),
            'controller_instantiation' => $this->addSuggestion('Check constructor dependencies and service container bindings'),
            default => $this->addSuggestion('Review the resolution context for clues about what went wrong')
        };
    }

    /**
     * Create a comprehensive resolution report for debugging purposes.
     * 
     * This method generates detailed reports that include all the information
     * developers need to understand and fix resolution problems, making
     * debugging sessions more efficient and educational.
     *
     * @param bool $includeTrace Whether to include stack trace information
     * @return array Comprehensive resolution report
     */
    public function toResolutionReport(bool $includeTrace = false): array
    {
        $report = parent::toArray($includeTrace);
        
        // Add resolution-specific information
        $report['route_pattern'] = $this->routePattern;
        $report['resolution_step'] = $this->resolutionStep;
        $report['attempted_classes'] = $this->attemptedClasses;
        $report['attempted_namespaces'] = $this->attemptedNamespaces;
        $report['resolution_context'] = $this->resolutionContext;
        
        // Add analysis of common issues
        $report['pattern_analysis'] = $this->analyzeRoutePattern();
        $report['suggestions_summary'] = $this->getSuggestionsSummary();
        
        return $report;
    }

    /**
     * Analyze the route pattern for common issues and provide insights.
     * 
     * This method demonstrates how exceptions can provide intelligent analysis
     * of the input that caused the error, helping developers understand not
     * just what went wrong, but why it might have gone wrong.
     *
     * @return array Pattern analysis results
     */
    protected function analyzeRoutePattern(): array
    {
        $analysis = [];
        
        if (empty($this->routePattern)) {
            $analysis['issue'] = 'Empty route pattern';
            return $analysis;
        }
        
        $analysis['pattern'] = $this->routePattern;
        $analysis['length'] = strlen($this->routePattern);
        $analysis['contains_at'] = str_contains($this->routePattern, '@');
        $analysis['at_count'] = substr_count($this->routePattern, '@');
        $analysis['contains_dots'] = str_contains($this->routePattern, '.');
        $analysis['dot_count'] = substr_count($this->routePattern, '.');
        
        // Analyze pattern structure
        if ($analysis['contains_at']) {
            $parts = explode('@', $this->routePattern);
            $analysis['controller_part'] = $parts[0] ?? '';
            $analysis['method_part'] = $parts[1] ?? '';
            
            if ($analysis['contains_dots'] && !empty($analysis['controller_part'])) {
                $controllerParts = explode('.', $analysis['controller_part']);
                $analysis['namespace_segments'] = array_slice($controllerParts, 0, -1);
                $analysis['controller_name'] = end($controllerParts);
            } else {
                $analysis['namespace_segments'] = [];
                $analysis['controller_name'] = $analysis['controller_part'];
            }
        }
        
        // Identify potential issues
        if ($analysis['at_count'] !== 1) {
            $analysis['issues'][] = 'Pattern should contain exactly one @ symbol';
        }
        
        if (empty($analysis['controller_part'] ?? '')) {
            $analysis['issues'][] = 'Missing controller part before @';
        }
        
        if (empty($analysis['method_part'] ?? '')) {
            $analysis['issues'][] = 'Missing method part after @';
        }
        
        return $analysis;
    }

    /**
     * Get a summary of all suggestions for inclusion in reports.
     * 
     * This method provides a structured summary of suggestions that can be
     * included in various types of output without duplicating the suggestion
     * generation logic.
     *
     * @return array Summary of suggestions organized by category
     */
    protected function getSuggestionsSummary(): array
    {
        return [
            'step_specific' => "Suggestions for {$this->resolutionStep} failures",
            'error_specific' => "Suggestions for {$this->errorCode} errors",
            'total_suggestions' => count($this->suggestions),
            'pattern_length' => strlen($this->routePattern),
            'classes_attempted' => count($this->attemptedClasses),
        ];
    }
}