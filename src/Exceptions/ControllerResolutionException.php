<?php

namespace DancyCodes\FlashHalt\Exceptions;

/**
 * ControllerResolutionException - Specialized Exception for Controller Resolution Failures
 * 
 * This exception class handles all errors related to resolving FlashHALT route patterns
 * into executable controller methods. It demonstrates how to design error handling that
 * transforms frustrating "not found" errors into educational experiences that help
 * developers understand and work with FlashHALT's controller resolution patterns.
 * 
 * Controller resolution is one of the most complex aspects of FlashHALT because it
 * involves multiple layers of analysis:
 * - Pattern parsing and validation
 * - Namespace resolution and class discovery
 * - Controller validation and verification
 * - Method existence and accessibility checking
 * - Laravel service container integration
 * 
 * When any of these steps fails, developers need comprehensive information about
 * what was attempted, what went wrong, and how to fix the problem. This exception
 * class provides that information in a structured, actionable way that turns
 * debugging sessions into learning opportunities.
 */
class ControllerResolutionException extends FlashHaltException
{
    /**
     * The route pattern that FlashHALT was attempting to resolve when the error occurred.
     * This provides crucial context for understanding what the system was trying to
     * accomplish and helps developers identify patterns that might not match their
     * application's controller organization.
     */
    protected string $routePattern;

    /**
     * The resolution step where the error occurred, helping developers understand
     * which part of the resolution process failed. This enables targeted debugging
     * and helps identify whether the issue is with pattern format, controller
     * location, method naming, or other resolution aspects.
     */
    protected string $resolutionStep;

    /**
     * Array of class names that were attempted during controller resolution.
     * This information is invaluable for debugging because it shows developers
     * exactly what the resolution system was looking for, making it easy to
     * identify naming convention mismatches or missing controllers.
     */
    protected array $attemptedClasses = [];

    /**
     * Array of namespace patterns that were tried during resolution.
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
     * @return string The resolution step identifier
     */
    public function getResolutionStep(): string
    {
        return $this->resolutionStep;
    }

    /**
     * Get the array of class names that were attempted during resolution.
     * 
     * This information is often the key to solving resolution problems because
     * it shows developers exactly what FlashHALT was looking for, making it
     * easy to identify the mismatch between expectation and reality.
     *
     * @return array Array of attempted class names
     */
    public function getAttemptedClasses(): array
    {
        return $this->attemptedClasses;
    }

    /**
     * Get the namespace patterns that were tried during resolution.
     * 
     * This helps developers understand FlashHALT's namespace resolution
     * strategy and can guide them toward controller organization patterns
     * that work well with FlashHALT's conventions.
     *
     * @return array Array of attempted namespace patterns
     */
    public function getAttemptedNamespaces(): array
    {
        return $this->attemptedNamespaces;
    }

    /**
     * Get additional resolution context information.
     * 
     * Resolution context provides insights into the environment and
     * configuration that affected the resolution attempt, helping
     * developers understand why certain approaches were tried.
     *
     * @return array Resolution context information
     */
    public function getResolutionContext(): array
    {
        return $this->resolutionContext;
    }

    /**
     * Add an attempted class name to the exception context.
     * 
     * This method allows the resolution system to build up a comprehensive
     * list of what was attempted as resolution proceeds through different
     * strategies, creating a complete picture of the resolution process.
     *
     * @param string $className The class name that was attempted
     * @return self Returns self for method chaining
     */
    public function addAttemptedClass(string $className): self
    {
        $this->attemptedClasses[] = $className;
        return $this;
    }

    /**
     * Add a namespace pattern to the exception context.
     * 
     * This builds up information about which namespace resolution strategies
     * were attempted, helping developers understand the full scope of what
     * FlashHALT tried before giving up.
     *
     * @param string $namespace The namespace pattern that was attempted
     * @return self Returns self for method chaining
     */
    public function addAttemptedNamespace(string $namespace): self
    {
        $this->attemptedNamespaces[] = $namespace;
        return $this;
    }

    /**
     * Get the appropriate HTTP status code for this resolution failure.
     * 
     * Different types of resolution failures warrant different HTTP status
     * codes, helping clients understand whether the problem is with their
     * request format or with the server's controller organization.
     *
     * @return int HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        // Map resolution failure types to appropriate HTTP status codes
        return match ($this->errorCode) {
            'INVALID_PATTERN_FORMAT',
            'MISSING_METHOD_SEPARATOR',
            'PATTERN_TOO_LONG',
            'INVALID_PATTERN_CHARACTERS',
            'MULTIPLE_METHOD_SEPARATORS',
            'INVALID_PATTERN_STRUCTURE',
            'EMPTY_CONTROLLER_PATH',
            'EMPTY_METHOD_NAME' => 400, // Bad Request - client error in pattern format
            
            'CONTROLLER_NOT_FOUND',
            'METHOD_NOT_FOUND',
            'ABSTRACT_CONTROLLER',
            'INTERFACE_NOT_CONTROLLER' => 404, // Not Found - requested resource doesn't exist
            
            'CONTROLLER_REFLECTION_FAILED',
            'CONTROLLER_INSTANTIATION_FAILED',
            'INVALID_CONTROLLER_INHERITANCE' => 500, // Internal Server Error - server-side issue
            
            'CONTROLLER_NOT_WHITELISTED' => 403, // Forbidden - exists but not allowed
            
            default => 404 // Default to Not Found for resolution failures
        };
    }

    /**
     * Determine if this exception should be reported to monitoring systems.
     * 
     * Controller resolution failures are often legitimate debugging situations
     * rather than serious errors, so we need intelligent reporting logic that
     * captures important issues without creating noise from normal development activity.
     *
     * @return bool True if this exception should be reported
     */
    public function shouldReport(): bool
    {
        // Don't report common development/debugging scenarios
        $developmentErrors = [
            'CONTROLLER_NOT_FOUND',
            'METHOD_NOT_FOUND',
            'INVALID_PATTERN_FORMAT',
            'EMPTY_CONTROLLER_PATH',
            'EMPTY_METHOD_NAME'
        ];

        // In development environments, these errors are expected during debugging
        if (app()->environment(['local', 'development']) && in_array($this->errorCode, $developmentErrors)) {
            return false;
        }

        // Server-side issues should always be reported
        $serverErrors = [
            'CONTROLLER_REFLECTION_FAILED',
            'CONTROLLER_INSTANTIATION_FAILED'
        ];

        if (in_array($this->errorCode, $serverErrors)) {
            return true;
        }

        // Report other errors in production environments
        return app()->environment('production');
    }

    /**
     * Initialize resolution-specific error details and suggestions.
     * 
     * This method provides targeted guidance for different types of resolution
     * failures, helping developers understand not just what went wrong, but
     * how to organize their controllers and structure their patterns to work
     * effectively with FlashHALT.
     */
    protected function initializeSpecificErrorDetails(): void
    {
        // Add resolution-specific suggestions based on the error code
        $this->addResolutionSpecificSuggestions();
        
        // Add documentation links relevant to controller resolution
        $this->addDocumentationLink('FlashHALT Controller Resolution Guide', 'https://flashhalt.dev/docs/controllers');
        $this->addDocumentationLink('Laravel Controller Documentation', 'https://laravel.com/docs/controllers');
        $this->addDocumentationLink('PSR-4 Autoloading Standard', 'https://www.php-fig.org/psr/psr-4/');
        
        // Add step-specific guidance
        $this->addStepSpecificGuidance();
    }

    /**
     * Add suggestions specific to the resolution failure type.
     * 
     * This method provides targeted advice for different types of resolution
     * failures, helping developers understand both what went wrong and how
     * to fix it while following Laravel and FlashHALT best practices.
     */
    protected function addResolutionSpecificSuggestions(): void
    {
        match ($this->errorCode) {
            'CONTROLLER_NOT_FOUND' => $this->addControllerNotFoundSuggestions(),
            'METHOD_NOT_FOUND' => $this->addMethodNotFoundSuggestions(),
            'INVALID_PATTERN_FORMAT' => $this->addPatternFormatSuggestions(),
            'CONTROLLER_INSTANTIATION_FAILED' => $this->addInstantiationSuggestions(),
            'INVALID_CONTROLLER_INHERITANCE' => $this->addInheritanceSuggestions(),
            'CONTROLLER_NOT_WHITELISTED' => $this->addWhitelistSuggestions(),
            default => $this->addGeneralResolutionSuggestions()
        };
    }

    /**
     * Add specific suggestions for controller not found errors.
     * 
     * This method demonstrates how to provide comprehensive, actionable guidance
     * for one of the most common resolution failures developers encounter.
     */
    protected function addControllerNotFoundSuggestions(): void
    {
        $this->addSuggestion('Check that your controller exists in the expected location');
        $this->addSuggestion('Verify that your controller follows Laravel naming conventions (PascalCase with "Controller" suffix)');
        $this->addSuggestion('Ensure your controller is in the App\\Http\\Controllers namespace or a sub-namespace');
        
        if (!empty($this->attemptedClasses)) {
            $this->addSuggestion('The system tried these class names: ' . implode(', ', $this->attemptedClasses));
            $this->addSuggestion('Create one of these classes or adjust your route pattern to match an existing controller');
        }
        
        $this->addSuggestion('Run "composer dump-autoload" to ensure your controller can be autoloaded');
        $this->addSuggestion('Check for typos in your route pattern, especially in namespace and controller names');
    }

    /**
     * Add specific suggestions for method not found errors.
     */
    protected function addMethodNotFoundSuggestions(): void
    {
        $this->addSuggestion('Verify that the method exists in your controller and is spelled correctly');
        $this->addSuggestion('Ensure the method is public (private and protected methods cannot be accessed via HTTP)');
        $this->addSuggestion('Check that the method is not static (FlashHALT only supports instance methods)');
        $this->addSuggestion('Method names should use camelCase (e.g., "createUser" not "create_user")');
    }

    /**
     * Add specific suggestions for pattern format errors.
     */
    protected function addPatternFormatSuggestions(): void
    {
        $this->addSuggestion('Route patterns must follow the format: controller@method or namespace.controller@method');
        $this->addSuggestion('Use dots (.) to separate namespace segments: admin.users@create');
        $this->addSuggestion('Use exactly one @ symbol to separate controller from method');
        $this->addSuggestion('Only use alphanumeric characters, dots, hyphens, underscores, and @ in patterns');
        
        if (!empty($this->routePattern)) {
            $this->addSuggestion("Your pattern was: \"{$this->routePattern}\"");
        }
    }

    /**
     * Add specific suggestions for controller instantiation failures.
     */
    protected function addInstantiationSuggestions(): void
    {
        $this->addSuggestion('Check that your controller constructor dependencies can be resolved by Laravel\'s service container');
        $this->addSuggestion('Ensure all constructor parameters have appropriate type hints or default values');
        $this->addSuggestion('Verify that any custom services your controller depends on are properly bound in service providers');
        $this->addSuggestion('Check for circular dependencies in your service registrations');
    }

    /**
     * Add specific suggestions for controller inheritance issues.
     */
    protected function addInheritanceSuggestions(): void
    {
        $this->addSuggestion('Controllers accessed through FlashHALT must extend Laravel\'s base Controller class');
        $this->addSuggestion('Ensure your controller extends App\\Http\\Controllers\\Controller or Illuminate\\Routing\\Controller');
        $this->addSuggestion('Check that you haven\'t accidentally created a plain PHP class instead of a Laravel controller');
    }

    /**
     * Add specific suggestions for whitelist restriction errors.
     */
    protected function addWhitelistSuggestions(): void
    {
        $this->addSuggestion('This controller is not in the allowed controllers list');
        $this->addSuggestion('Add your controller to the flashhalt.development.allowed_controllers configuration');
        $this->addSuggestion('Controller whitelisting is configured for enhanced security - only listed controllers can be accessed');
    }

    /**
     * Add general resolution suggestions for uncommon error types.
     */
    protected function addGeneralResolutionSuggestions(): void
    {
        $this->addSuggestion('Review the FlashHALT controller resolution documentation');
        $this->addSuggestion('Check that your application follows Laravel\'s standard controller organization patterns');
        $this->addSuggestion('Verify that your controller and method names follow PHP and Laravel naming conventions');
    }

    /**
     * Add guidance specific to the resolution step where the failure occurred.
     */
    protected function addStepSpecificGuidance(): void
    {
        match ($this->resolutionStep) {
            'pattern_parsing' => $this->addSuggestion(
                'The error occurred while parsing your route pattern. Check the pattern format and syntax.'
            ),
            'namespace_resolution' => $this->addSuggestion(
                'The error occurred while resolving the controller namespace. Check your controller location and naming.'
            ),
            'class_validation' => $this->addSuggestion(
                'The error occurred while validating the controller class. Check that it\'s a proper Laravel controller.'
            ),
            'method_validation' => $this->addSuggestion(
                'The error occurred while validating the controller method. Check that the method exists and is accessible.'
            ),
            'controller_instantiation' => $this->addSuggestion(
                'The error occurred while creating the controller instance. Check constructor dependencies and service bindings.'
            )
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
        $analysis['potential_issues'] = [];
        
        if (!$analysis['contains_at']) {
            $analysis['potential_issues'][] = 'Missing @ separator between controller and method';
        }
        
        if ($analysis['at_count'] > 1) {
            $analysis['potential_issues'][] = 'Multiple @ symbols found (only one is allowed)';
        }
        
        if ($analysis['length'] > 200) {
            $analysis['potential_issues'][] = 'Pattern is unusually long (potential security issue)';
        }
        
        return $analysis;
    }

    /**
     * Generate a summary of suggestions organized by category.
     * 
     * This method organizes suggestions into logical categories that help
     * developers prioritize their debugging efforts and understand the
     * different aspects of controller resolution they might need to address.
     *
     * @return array Categorized suggestions summary
     */
    protected function getSuggestionsSummary(): array
    {
        $summary = [
            'immediate_actions' => [],
            'verification_steps' => [],
            'configuration_checks' => [],
            'learning_resources' => []
        ];
        
        foreach ($this->suggestions as $suggestion) {
            // Categorize suggestions based on their content
            if (str_contains(strtolower($suggestion), 'check') || str_contains(strtolower($suggestion), 'verify')) {
                $summary['verification_steps'][] = $suggestion;
            } elseif (str_contains(strtolower($suggestion), 'configuration') || str_contains(strtolower($suggestion), 'config')) {
                $summary['configuration_checks'][] = $suggestion;
            } elseif (str_contains(strtolower($suggestion), 'documentation') || str_contains(strtolower($suggestion), 'guide')) {
                $summary['learning_resources'][] = $suggestion;
            } else {
                $summary['immediate_actions'][] = $suggestion;
            }
        }
        
        return $summary;
    }
}