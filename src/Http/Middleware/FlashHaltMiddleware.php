<?php

namespace DancyCodes\FlashHalt\Http\Middleware;

use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Exceptions\ControllerResolutionException;
use DancyCodes\FlashHalt\Exceptions\SecurityValidationException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * FlashHaltMiddleware - The Request Processing Orchestrator
 * 
 * This middleware is the conductor that transforms FlashHALT route patterns into
 * executable controller method calls. It represents the culmination of our
 * sophisticated service architecture, demonstrating how well-designed components
 * can work together to create functionality that feels magical to users while
 * maintaining enterprise-grade reliability and security.
 * 
 * The middleware operates through a carefully orchestrated process:
 * 1. Request analysis to determine if FlashHALT should handle this request
 * 2. Route pattern extraction and validation from the URL path
 * 3. Controller resolution through our ControllerResolver service
 * 4. Method execution with proper parameter binding and error handling
 * 5. Response processing optimized for HTMX compatibility
 * 6. Performance monitoring and debugging information collection
 * 
 * This middleware demonstrates several advanced patterns:
 * - Service orchestration and dependency coordination
 * - Comprehensive error handling with environment-aware messaging
 * - Performance optimization through intelligent caching integration
 * - HTMX-specific response processing and header management
 * - Laravel integration that preserves all framework capabilities
 */
class FlashHaltMiddleware
{
    /**
     * The ControllerResolver service that handles the sophisticated logic
     * of transforming route patterns into executable controller method calls.
     * This demonstrates how middleware can leverage specialized services
     * rather than implementing complex logic directly.
     */
    protected ControllerResolver $controllerResolver;

    /**
     * Configuration settings that control middleware behavior.
     * This configuration-driven approach allows the middleware to adapt
     * to different environments and requirements without code changes.
     */
    protected array $config;

    /**
     * Performance monitoring data collected during request processing.
     * This information helps identify optimization opportunities and
     * provides insights into how FlashHALT is performing in production.
     */
    protected array $performanceMetrics = [];

    public function __construct(ControllerResolver $controllerResolver)
    {
        $this->controllerResolver = $controllerResolver;
        $this->config = config('flashhalt', []);
        
        // Initialize performance monitoring if enabled
        if ($this->isPerformanceMonitoringEnabled()) {
            $this->initializePerformanceMonitoring();
        }
    }

    /**
     * Handle an incoming request through FlashHALT's dynamic resolution system.
     * 
     * This method demonstrates the classic middleware pattern where we examine
     * the incoming request, determine if we should process it, and either
     * handle it ourselves or pass it to the next middleware in the chain.
     * 
     * The method also shows how to implement comprehensive error handling
     * that provides helpful information in development while maintaining
     * security in production environments.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the processing chain
     * @return BaseResponse The processed response
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        // Start performance monitoring for this request
        $this->startPerformanceTimer('total_processing');

        try {
            // Step a: Check if this looks like a FlashHALT request
            if (!$request->is('hx/*')) {
                // Not a FlashHALT request at all, pass it through
                return $next($request);
            }

            // Step 1: This is a FlashHALT request, validate it properly
            if (!$this->shouldProcessRequest($request)) {
                // This is a malformed FlashHALT request, reject it explicitly
                return $this->handleInvalidFlashHaltRequest($request);
            }

            // Step 2: Extract and validate the route pattern from the request
            $routePattern = $this->extractRoutePattern($request);
            $this->startPerformanceTimer('controller_resolution');

            // Step 3: Resolve the controller and method through our service architecture
            $resolution = $this->controllerResolver->resolveController(
                $routePattern,
                $request->getMethod()
            );
            $this->stopPerformanceTimer('controller_resolution');

            // Step 4: Execute the resolved controller method with proper parameter binding
            $this->startPerformanceTimer('method_execution');
            $response = $this->executeControllerMethod($request, $resolution);
            $this->stopPerformanceTimer('method_execution');

            // Step 5: Process the response for HTMX compatibility and optimization
            $this->startPerformanceTimer('response_processing');
            $processedResponse = $this->processResponse($request, $response);
            $this->stopPerformanceTimer('response_processing');

            // Step 6: Add debugging information in development environments
            $this->addDebugInformation($request, $processedResponse, $resolution);

            $this->stopPerformanceTimer('total_processing');
            
            // Log performance metrics if monitoring is enabled
            $this->logPerformanceMetrics($routePattern);

            return $processedResponse;

        } catch (SecurityValidationException $e) {
            // Handle security validation errors with appropriate logging and response
            return $this->handleSecurityError($request, $e);
        } catch (ControllerResolutionException $e) {
            // Handle controller resolution errors with helpful debugging information
            return $this->handleResolutionError($request, $e);
        } catch (\Exception $e) {
            // Handle unexpected errors gracefully while providing debugging information
            return $this->handleUnexpectedError($request, $e);
        }
    }


    /**
     * Handle invalid FlashHALT requests with appropriate error responses.
     * 
     * This method handles requests that match the FlashHALT URL pattern
     * but have invalid or malformed route patterns.
     *
     * @param Request $request The invalid request
     * @return BaseResponse An appropriate error response
     */
    protected function handleInvalidFlashHaltRequest(Request $request): BaseResponse
{
    try {
        if ($this->isDebugMode()) {
            // In development, provide helpful error information
            $route = $request->route();
            $pattern = 'unknown';
            
            try {
                $pattern = $route ? $route->parameter('route', 'unknown') : 'unknown';
            } catch (\Exception $e) {
                // Ignore route access errors
            }
            
            $exception = new ControllerResolutionException(
                "Invalid route pattern: '{$pattern}'. FlashHALT routes must follow the pattern 'controller@method' or 'namespace.controller@method'.",
                'INVALID_ROUTE_PATTERN'
            );
            
            return new Response(
                $this->formatDevelopmentError('Invalid FlashHALT Route Pattern', $exception),
                404,
                ['Content-Type' => 'text/html']
            );
        } else {
            // In production, provide minimal error information
            return new Response('Not Found', 404);
        }
    } catch (\Exception $e) {
        // Fallback in case anything goes wrong
        return new Response('Not Found', 404);
    }
}


    /**
     * Determine if this request should be processed by FlashHALT.
     * 
     * This method implements the intelligent request filtering that allows
     * FlashHALT to coexist with other routing mechanisms in Laravel applications.
     * It demonstrates how to analyze HTTP requests to determine processing
     * responsibility without interfering with other application functionality.
     *
     * @param Request $request The request to analyze
     * @return bool True if FlashHALT should process this request
     */
    protected function shouldProcessRequest(Request $request): bool
    {
        // At this point we know it's an hx/* request
        
        // Extract the route parameter that contains our pattern
        $route = $request->route();
        if (!$route) {
            return false;
        }
    
        // Handle cases where route parameters aren't available (e.g., in tests)
        try {
            if (!$route->hasParameter('route')) {
                return false;
            }
            $routePattern = $route->parameter('route');
        } catch (\Exception $e) {
            // Route not properly bound, reject this request
            return false;
        }
        
        // Verify the pattern contains our required @ separator
        if (!is_string($routePattern) || !str_contains($routePattern, '@')) {
            return false;
        }
    
        // Additional validation for malformed or suspicious patterns
        if (strlen($routePattern) > 200 || !preg_match('/^[a-zA-Z0-9_\.\-@]+$/', $routePattern)) {
            return false;
        }
    
        return true;
    }

    /**
     * Extract the route pattern from the request safely.
     * 
     * This method demonstrates careful input extraction with validation
     * to ensure that we're working with clean, safe data throughout
     * the processing pipeline.
     *
     * @param Request $request The request to extract the pattern from
     * @return string The validated route pattern
     * @throws ControllerResolutionException If pattern extraction fails
     */
    protected function extractRoutePattern(Request $request): string
{
    $route = $request->route();
    
    if (!$route) {
        throw new ControllerResolutionException(
            'Route not available: request must have a bound route',
            'ROUTE_NOT_BOUND'
        );
    }

    try {
        $routePattern = $route->parameter('route');
    } catch (\Exception $e) {
        throw new ControllerResolutionException(
            'Failed to extract route pattern: ' . $e->getMessage(),
            'ROUTE_PARAMETER_ERROR'
        );
    }

    if (!is_string($routePattern) || empty(trim($routePattern))) {
        throw new ControllerResolutionException(
            'Invalid route pattern: pattern must be a non-empty string',
            'INVALID_ROUTE_PATTERN'
        );
    }

    return trim($routePattern);
}

    /**
     * Execute a resolved controller method with proper parameter binding.
     * 
     * This method demonstrates how to integrate with Laravel's parameter
     * binding system while adding FlashHALT-specific functionality. It shows
     * how to call controller methods dynamically while preserving all of
     * Laravel's built-in capabilities like dependency injection and validation.
     *
     * @param Request $request The HTTP request containing parameters
     * @param array $resolution Controller resolution results
     * @return mixed The controller method's return value
     * @throws \Exception If method execution fails
     */
    protected function executeControllerMethod(Request $request, array $resolution)
    {
        $controller = $resolution['controller'];
        $methodName = $resolution['method'];

        try {
            // Use Laravel's service container to call the method with proper dependency injection
            // This ensures that method parameters are resolved correctly and that all
            // Laravel features like form request validation continue to work
            return app()->call([$controller, $methodName], $this->buildMethodParameters($request));

        } catch (\Exception $e) {
            // Enhance error messages with context about what method was being called
            throw new \Exception(
                sprintf(
                    'Failed to execute method "%s::%s": %s',
                    get_class($controller),
                    $methodName,
                    $e->getMessage()
                ),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Build parameters for controller method execution.
     * 
     * This method demonstrates how to prepare request data for Laravel's
     * parameter binding system while preserving all the framework's
     * built-in capabilities like validation and model binding.
     *
     * @param Request $request The HTTP request
     * @return array Parameters for method execution
     */
    protected function buildMethodParameters(Request $request): array
    {
        // Start with all request data (query parameters, form data, JSON, etc.)
        $parameters = $request->all();

        // Add the request itself so methods can access it directly if needed
        $parameters['request'] = $request;

        // Add any route parameters that Laravel might have bound
        if ($route = $request->route()) {
            $parameters = array_merge($parameters, $route->parameters());
        }

        return $parameters;
    }

    /**
     * Process the controller response for HTMX compatibility and optimization.
     * 
     * This method demonstrates how to handle different types of controller responses
     * and optimize them for HTMX usage while maintaining compatibility with
     * traditional web requests. It shows how to add HTMX-specific headers and
     * handle response formatting intelligently.
     *
     * @param Request $request The original request
     * @param mixed $response The controller's response
     * @return BaseResponse The processed response
     */
    protected function processResponse(Request $request, $response): BaseResponse
    {
        // Convert the controller response to a proper HTTP response
        $httpResponse = $this->convertToHttpResponse($response);

        // Add HTMX-specific headers and optimizations if this is an HTMX request
        if ($this->isHtmxRequest($request)) {
            $this->addHtmxHeaders($httpResponse, $request);
            $this->optimizeForHtmx($httpResponse, $request);
        }

        // Add FlashHALT identification header for debugging
        $httpResponse->headers->set('X-FlashHALT-Processed', 'true');

        // Add performance headers in development mode
        if ($this->isDebugMode()) {
            $this->addPerformanceHeaders($httpResponse);
        }

        return $httpResponse;
    }

    /**
     * Convert various controller response types to proper HTTP responses.
     * 
     * This method demonstrates how to handle the variety of response types
     * that Laravel controllers can return, ensuring that all work correctly
     * with FlashHALT's dynamic resolution system.
     *
     * @param mixed $response The controller's response
     * @return BaseResponse A proper HTTP response
     */
    protected function convertToHttpResponse($response): BaseResponse
    {
        // If it's already a Response object, use it directly
        if ($response instanceof BaseResponse) {
            return $response;
        }

        // Handle view responses (the most common case for HTMX)
        if ($response instanceof \Illuminate\Contracts\View\View) {
            return new Response($response->render());
        }

        // Handle string responses
        if (is_string($response)) {
            return new Response($response);
        }

        // Handle array/object responses (convert to JSON)
        if (is_array($response) || is_object($response)) {
            return new Response(
                json_encode($response),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        // Handle null responses
        if ($response === null) {
            return new Response('', 204); // No Content
        }

        // Fallback for other types - convert to string
        return new Response((string) $response);
    }

    /**
     * Determine if this is an HTMX request.
     * 
     * HTMX requests include specific headers that allow us to optimize
     * responses and provide enhanced functionality.
     *
     * @param Request $request The request to check
     * @return bool True if this is an HTMX request
     */
    protected function isHtmxRequest(Request $request): bool
    {
        return $request->header('HX-Request') === 'true';
    }

    /**
     * Add HTMX-specific headers to enhance client-side functionality.
     * 
     * This method demonstrates how to provide rich HTMX functionality
     * through response headers while maintaining compatibility with
     * standard HTTP requests.
     *
     * @param BaseResponse $response The response to enhance
     * @param Request $request The original request
     */
    protected function addHtmxHeaders(BaseResponse $response, Request $request): void
    {
        // Add trigger headers if the controller set any
        // This would typically be done through a response macro or helper
        if ($triggers = $request->attributes->get('htmx_triggers')) {
            $response->headers->set('HX-Trigger', json_encode($triggers));
        }

        // Add push URL if the controller specified one
        if ($pushUrl = $request->attributes->get('htmx_push_url')) {
            $response->headers->set('HX-Push-Url', $pushUrl);
        }

        // Add any other HTMX headers that might have been set
        $htmxHeaders = [
            'HX-Redirect', 'HX-Refresh', 'HX-Replace-Url', 'HX-Reswap', 'HX-Retarget'
        ];

        foreach ($htmxHeaders as $header) {
            if ($value = $request->attributes->get(strtolower(str_replace('-', '_', $header)))) {
                $response->headers->set($header, $value);
            }
        }
    }

    /**
     * Optimize response for HTMX usage patterns.
     * 
     * This method demonstrates how to automatically optimize responses
     * for HTMX's specific requirements and usage patterns.
     *
     * @param BaseResponse $response The response to optimize
     * @param Request $request The original request
     */
    protected function optimizeForHtmx(BaseResponse $response, Request $request): void
    {
        // Set appropriate cache headers for HTMX requests
        // HTMX requests are often dynamic, so we typically want to prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        // Add CSRF token header for JavaScript access if needed
        if (config('session.driver') && !$request->isMethod('GET')) {
            $response->headers->set('X-CSRF-Token', csrf_token());
        }

        // Ensure proper content type for HTML fragments
        if ($response->headers->get('Content-Type') === null) {
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    /**
     * Add debugging information to responses in development environments.
     * 
     * This method demonstrates how to provide rich debugging information
     * that helps developers understand how FlashHALT processed their requests
     * without impacting production performance or security.
     *
     * @param Request $request The original request
     * @param BaseResponse $response The response to enhance
     * @param array $resolution The controller resolution results
     */
    protected function addDebugInformation(Request $request, BaseResponse $response, array $resolution): void
    {
        if (!$this->isDebugMode()) {
            return;
        }

        // Add debug headers that provide insight into processing
        $response->headers->set('X-FlashHALT-Controller', $resolution['class']);
        $response->headers->set('X-FlashHALT-Method', $resolution['method']);
        $response->headers->set('X-FlashHALT-Pattern', $resolution['pattern']);
        
        // Add timing information
        if (isset($this->performanceMetrics['total_processing'])) {
            $totalTime = $this->performanceMetrics['total_processing']['duration'] ?? 0;
            $response->headers->set('X-FlashHALT-Processing-Time', round($totalTime, 4) . 'ms');
        }

        // Add resolver statistics
        $stats = $this->controllerResolver->getResolutionStats();
        $response->headers->set('X-FlashHALT-Cache-Hit-Ratio', $stats['cache_hit_ratio'] . '%');
    }

    /**
     * Handle security validation errors with appropriate logging and responses.
     * 
     * This method demonstrates how to handle security errors in a way that
     * provides helpful information to developers while maintaining security
     * boundaries in production environments.
     *
     * @param Request $request The request that caused the error
     * @param SecurityValidationException $exception The security error
     * @return BaseResponse An appropriate error response
     */
    protected function handleSecurityError(Request $request, SecurityValidationException $exception): BaseResponse
    {
        // Log security violations for monitoring and analysis
        Log::warning('FlashHALT security validation failed', [
            'route_pattern' => $request->route('route'),
            'error_code' => $exception->getErrorCode(),
            'message' => $exception->getMessage(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($this->isDebugMode()) {
            // In development, provide detailed error information
            return new Response(
                $this->formatDevelopmentError('Security Validation Failed', $exception),
                403,
                ['Content-Type' => 'text/html']
            );
        } else {
            // In production, provide minimal error information
            return new Response('Forbidden', 403);
        }
    }

    /**
     * Handle controller resolution errors with helpful debugging information.
     * 
     * This method shows how to provide educational error messages that help
     * developers understand and fix resolution problems quickly.
     *
     * @param Request $request The request that caused the error
     * @param ControllerResolutionException $exception The resolution error
     * @return BaseResponse An appropriate error response
     */
    protected function handleResolutionError(Request $request, ControllerResolutionException $exception): BaseResponse
    {
        // Log resolution errors for debugging and monitoring
        Log::debug('FlashHALT controller resolution failed', [
            'route_pattern' => $request->route('route'),
            'error_code' => $exception->getErrorCode(),
            'message' => $exception->getMessage(),
        ]);

        if ($this->isDebugMode()) {
            // In development, provide detailed resolution debugging information
            return new Response(
                $this->formatDevelopmentError('Controller Resolution Failed', $exception),
                404,
                ['Content-Type' => 'text/html']
            );
        } else {
            // In production, return a simple 404 response
            return new Response('Not Found', 404);
        }
    }

    /**
     * Handle unexpected errors gracefully while providing debugging information.
     * 
     * This method demonstrates how to handle unexpected errors in a way that
     * maintains application stability while providing the information needed
     * for debugging and fixing issues.
     *
     * @param Request $request The request that caused the error
     * @param \Exception $exception The unexpected error
     * @return BaseResponse An appropriate error response
     */
    protected function handleUnexpectedError(Request $request, \Exception $exception): BaseResponse
    {
        // Log unexpected errors for investigation
        Log::error('FlashHALT unexpected error', [
            'route_pattern' => $request->route('route'),
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        if ($this->isDebugMode()) {
            // In development, provide full error details
            return new Response(
                $this->formatDevelopmentError('Unexpected Error', $exception),
                500,
                ['Content-Type' => 'text/html']
            );
        } else {
            // In production, return a generic error response
            return new Response('Internal Server Error', 500);
        }
    }

    /**
     * Format error information for development environments.
     * 
     * This method creates helpful error pages that provide developers with
     * the information they need to understand and fix problems quickly.
     *
     * @param string $title The error title
     * @param \Exception $exception The exception to format
     * @return string Formatted HTML error page
     */
    protected function formatDevelopmentError(string $title, \Exception $exception): string
    {
        return sprintf(
            '<div style="font-family: monospace; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6;">' .
            '<h2 style="color: #dc3545;">FlashHALT %s</h2>' .
            '<p><strong>Error Code:</strong> %s</p>' .
            '<p><strong>Message:</strong> %s</p>' .
            '<p><strong>Route Pattern:</strong> %s</p>' .
            '<details style="margin-top: 20px;"><summary>Stack Trace</summary><pre>%s</pre></details>' .
            '</div>',
            htmlspecialchars($title),
            htmlspecialchars(method_exists($exception, 'getErrorCode') ? $exception->getErrorCode() : 'UNKNOWN'),
            htmlspecialchars($exception->getMessage()),
            htmlspecialchars(request()->route('route') ?? 'Unknown'),
            htmlspecialchars($exception->getTraceAsString())
        );
    }

    /**
     * Performance monitoring helper methods.
     * These methods demonstrate how to build observability into middleware
     * without impacting performance significantly.
     */

    protected function isPerformanceMonitoringEnabled(): bool
    {
        return $this->config['monitoring']['enabled'] ?? false;
    }

    protected function isDebugMode(): bool
    {
        return $this->config['development']['debug_mode'] ?? false;
    }

    protected function initializePerformanceMonitoring(): void
    {
        $this->performanceMetrics = [];
    }

    protected function startPerformanceTimer(string $operation): void
    {
        if (!$this->isPerformanceMonitoringEnabled()) {
            return;
        }

        $this->performanceMetrics[$operation] = [
            'start' => microtime(true),
        ];
    }

    protected function stopPerformanceTimer(string $operation): void
    {
        if (!$this->isPerformanceMonitoringEnabled() || !isset($this->performanceMetrics[$operation])) {
            return;
        }

        $startTime = $this->performanceMetrics[$operation]['start'];
        $this->performanceMetrics[$operation]['duration'] = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
    }

    protected function addPerformanceHeaders(BaseResponse $response): void
    {
        foreach ($this->performanceMetrics as $operation => $metrics) {
            if (isset($metrics['duration'])) {
                $response->headers->set(
                    'X-FlashHALT-' . ucfirst(str_replace('_', '-', $operation)) . '-Time',
                    round($metrics['duration'], 4) . 'ms'
                );
            }
        }
    }

    protected function logPerformanceMetrics(string $routePattern): void
    {
        if (!$this->isPerformanceMonitoringEnabled()) {
            return;
        }

        Log::debug('FlashHALT performance metrics', [
            'route_pattern' => $routePattern,
            'metrics' => $this->performanceMetrics,
        ]);
    }

}