<?php

namespace DancyCodes\FlashHalt\Services;

use DancyCodes\FlashHalt\Exceptions\ControllerResolutionException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;

/**
 * ControllerResolver Service
 * 
 * This service is the intelligent heart of FlashHALT's dynamic controller resolution.
 * It transforms human-friendly route patterns like "admin.users@create" into fully
 * qualified controller method calls that Laravel can execute. The service handles
 * complex namespace resolution, caching optimization, and integration with both
 * Laravel's service container and FlashHALT's security validation.
 * 
 * The resolution process involves several sophisticated steps:
 * 1. Pattern parsing - Breaking down route patterns into components
 * 2. Namespace resolution - Converting patterns to fully qualified class names
 * 3. Class validation - Ensuring controllers exist and are appropriate
 * 4. Method validation - Confirming methods exist and are safe to call
 * 5. Controller instantiation - Creating controller instances through Laravel's container
 * 6. Result caching - Storing resolution results for optimal performance
 */
class ControllerResolver
{
    /**
     * Security validator service for ensuring resolved methods are safe to call.
     * This integration demonstrates how well-designed services collaborate
     * without tight coupling, each handling their specific domain of expertise.
     */
    protected SecurityValidator $securityValidator;

    /**
     * Cache repository for storing resolution results and performance optimization.
     * Controller resolution involves expensive operations like class existence checks
     * and reflection analysis, so intelligent caching is crucial for production performance.
     */
    protected CacheRepository $cache;

    /**
     * Laravel's service container for proper dependency injection when instantiating controllers.
     * Using the container ensures that all Laravel features like middleware and dependency
     * injection continue to work correctly with dynamically resolved controllers.
     */
    protected Container $container;

    /**
     * Configuration settings that control resolution behavior and performance characteristics.
     * This demonstrates how configuration-driven services can adapt to different
     * environments and requirements without code changes.
     */
    protected array $config;

    /**
     * Cache TTL for resolution results in seconds.
     * This balances performance optimization with the need for fresh results
     * when controllers or methods change during development.
     */
    protected int $cacheTtl;

    /**
     * In-memory cache for resolution results within a single request.
     * This provides immediate performance benefits when the same controller
     * is resolved multiple times during a single request cycle.
     */
    protected array $memoryCache = [];

    /**
     * Pre-computed namespace patterns for common controller locations.
     * Laravel applications often follow predictable controller organization patterns,
     * so pre-computing these patterns improves resolution performance significantly.
     */
    protected array $namespacePatterns = [];

    /**
     * Statistics tracking for monitoring and debugging resolution performance.
     * This data helps identify optimization opportunities and troubleshoot
     * resolution issues in complex applications.
     */
    protected array $resolutionStats = [
        'cache_hits' => 0,
        'cache_misses' => 0,
        'memory_hits' => 0,
        'resolution_attempts' => 0,
        'successful_resolutions' => 0,
    ];

    public function __construct(SecurityValidator $securityValidator, CacheRepository $cache, array $config)
    {
        $this->securityValidator = $securityValidator;
        $this->cache = $cache;
        $this->container = App::getFacadeRoot();
        $this->config = $config;
        $this->cacheTtl = $config['development']['cache_ttl'] ?? 3600;

        // Initialize namespace patterns for efficient controller discovery
        $this->initializeNamespacePatterns();
    }

    /**
     * Resolve a FlashHALT route pattern to a controller instance and method name.
     * 
     * This is the main entry point for controller resolution. It takes a route pattern
     * like "admin.users@create" and returns everything needed to execute that controller
     * method, including a properly instantiated controller object and validated method name.
     * 
     * The resolution process is optimized for performance through multiple caching layers
     * while maintaining comprehensive validation to ensure security and reliability.
     *
     * @param string $routePattern The FlashHALT route pattern (e.g., "admin.users@create")
     * @param string $httpMethod The HTTP method for the request (affects security validation)
     * @return array Array containing 'controller' instance and 'method' name
     * @throws ControllerResolutionException If resolution fails at any step
     */
    public function resolveController(string $routePattern, string $httpMethod = 'GET'): array
    {
        // Increment resolution attempt counter for monitoring
        $this->resolutionStats['resolution_attempts']++;

        // Validate the route pattern format before attempting resolution
        $this->validateRoutePattern($routePattern);

        // Create a unique cache key that includes all factors affecting resolution
        $cacheKey = $this->createResolutionCacheKey($routePattern, $httpMethod);

        // Check memory cache first for immediate results within this request
        if (isset($this->memoryCache[$cacheKey])) {
            $this->resolutionStats['memory_hits']++;
            return $this->memoryCache[$cacheKey];
        }

        // Check persistent cache for previous resolution results
        $cachedResult = $this->cache->get($cacheKey);
        if ($cachedResult !== null) {
            $this->resolutionStats['cache_hits']++;
            $this->memoryCache[$cacheKey] = $cachedResult;
            return $cachedResult;
        }

        // No cached result available, perform full resolution process
        $this->resolutionStats['cache_misses']++;
        $result = $this->performControllerResolution($routePattern, $httpMethod);

        // Cache the successful result for future requests
        $this->cache->put($cacheKey, $result, $this->cacheTtl);
        $this->memoryCache[$cacheKey] = $result;

        $this->resolutionStats['successful_resolutions']++;
        return $result;
    }

    /**
     * Validate that a route pattern follows FlashHALT's expected format.
     * 
     * This early validation catches malformed patterns before expensive resolution
     * operations, providing clear error messages that help developers understand
     * the expected pattern format.
     *
     * @param string $routePattern The pattern to validate
     * @throws ControllerResolutionException If pattern format is invalid
     */
    protected function validateRoutePattern(string $routePattern): void
    {
        // Check for basic pattern requirements
        if (empty($routePattern) || !is_string($routePattern)) {
            throw new ControllerResolutionException(
                'Route pattern must be a non-empty string',
                'INVALID_PATTERN_FORMAT'
            );
        }

        // Ensure pattern contains the required @ separator
        if (!str_contains($routePattern, '@')) {
            throw new ControllerResolutionException(
                'Route pattern must contain @ separator between controller and method (e.g., "users@create")',
                'MISSING_METHOD_SEPARATOR'
            );
        }

        // Validate pattern length to prevent potential buffer overflow attempts
        if (strlen($routePattern) > 200) {
            throw new ControllerResolutionException(
                'Route pattern exceeds maximum allowed length',
                'PATTERN_TOO_LONG'
            );
        }

        // Check for suspicious characters that might indicate injection attempts
        if (!preg_match('/^[a-zA-Z0-9_\.\-@]+$/', $routePattern)) {
            throw new ControllerResolutionException(
                'Route pattern contains invalid characters. Only alphanumeric characters, dots, hyphens, underscores, and @ are allowed.',
                'INVALID_PATTERN_CHARACTERS'
            );
        }

        // Ensure pattern doesn't have multiple @ separators (which would be ambiguous)
        if (substr_count($routePattern, '@') > 1) {
            throw new ControllerResolutionException(
                'Route pattern can only contain one @ separator',
                'MULTIPLE_METHOD_SEPARATORS'
            );
        }
    }

    /**
     * Perform the complete controller resolution process.
     * 
     * This method orchestrates the complex process of transforming a route pattern
     * into an executable controller method call. It demonstrates how to break down
     * a complex operation into manageable steps while maintaining error handling
     * and performance optimization throughout.
     *
     * @param string $routePattern The route pattern to resolve
     * @param string $httpMethod HTTP method for security validation
     * @return array Resolution result with controller instance and method name
     * @throws ControllerResolutionException If any resolution step fails
     */
    protected function performControllerResolution(string $routePattern, string $httpMethod): array
    {
        // Step 1: Parse the route pattern into controller path and method name
        [$controllerPath, $methodName] = $this->parseRoutePattern($routePattern);

        // Step 2: Resolve the controller path to a fully qualified class name
        $controllerClass = $this->resolveControllerClass($controllerPath);

        // Step 3: Validate that the controller class exists and is appropriate
        $this->validateControllerClass($controllerClass);

        // Step 4: Validate that the method exists and is safe to call
        $this->validateControllerMethod($controllerClass, $methodName, $httpMethod);

        // Step 5: Instantiate the controller through Laravel's service container
        $controllerInstance = $this->instantiateController($controllerClass);

        // Return the resolved controller and method information
        return [
            'controller' => $controllerInstance,
            'method' => $methodName,
            'class' => $controllerClass,
            'pattern' => $routePattern,
        ];
    }

    /**
     * Parse a route pattern into controller path and method name components.
     * 
     * This method demonstrates how to carefully parse user input while handling
     * edge cases and providing clear error messages when parsing fails.
     *
     * @param string $routePattern The pattern to parse
     * @return array Array containing controller path and method name
     * @throws ControllerResolutionException If parsing fails
     */
    protected function parseRoutePattern(string $routePattern): array
    {
        // Split the pattern at the @ symbol
        $parts = explode('@', $routePattern, 2);

        if (count($parts) !== 2) {
            throw new ControllerResolutionException(
                'Route pattern must contain exactly one @ separator between controller and method',
                'INVALID_PATTERN_STRUCTURE'
            );
        }

        [$controllerPath, $methodName] = $parts;

        // Validate controller path component
        if (empty(trim($controllerPath))) {
            throw new ControllerResolutionException(
                'Controller path cannot be empty in route pattern',
                'EMPTY_CONTROLLER_PATH'
            );
        }

        // Validate method name component
        if (empty(trim($methodName))) {
            throw new ControllerResolutionException(
                'Method name cannot be empty in route pattern',
                'EMPTY_METHOD_NAME'
            );
        }

        return [trim($controllerPath), trim($methodName)];
    }

    /**
     * Resolve a controller path to a fully qualified class name.
     * 
     * This method demonstrates sophisticated namespace resolution that handles
     * Laravel's controller organization patterns while being flexible enough
     * to work with custom namespace structures.
     *
     * @param string $controllerPath The path component from the route pattern
     * @return string Fully qualified controller class name
     * @throws ControllerResolutionException If no valid controller class is found
     */
    protected function resolveControllerClass(string $controllerPath): string
    {
        // Try each namespace pattern until we find a class that exists
        foreach ($this->namespacePatterns as $pattern) {
            $candidateClass = $this->buildControllerClassName($controllerPath, $pattern);
            
            if (class_exists($candidateClass)) {
                return $candidateClass;
            }
        }

        // If no standard patterns worked, try some additional fallback strategies
        $fallbackClasses = $this->generateFallbackClassNames($controllerPath);
        
        foreach ($fallbackClasses as $candidateClass) {
            if (class_exists($candidateClass)) {
                return $candidateClass;
            }
        }

        // No valid controller class found, provide helpful error message
        throw new ControllerResolutionException(
            sprintf(
                'Could not resolve controller for path "%s". Tried these class names: %s',
                $controllerPath,
                implode(', ', array_merge(
                    array_map(fn($pattern) => $this->buildControllerClassName($controllerPath, $pattern), $this->namespacePatterns),
                    $fallbackClasses
                ))
            ),
            'CONTROLLER_NOT_FOUND'
        );
    }

    /**
     * Build a controller class name from a path and namespace pattern.
     * 
     * This method shows how to transform dotted path notation into proper
     * PHP namespace structures while handling naming conventions consistently.
     *
     * @param string $controllerPath The controller path from the route
     * @param array $pattern Namespace pattern configuration
     * @return string Fully qualified class name candidate
     */
    protected function buildControllerClassName(string $controllerPath, array $pattern): string
    {
        // Split the path on dots to handle nested namespaces
        $pathSegments = explode('.', $controllerPath);
        
        // The last segment is the controller name, earlier segments are namespace parts
        $controllerName = array_pop($pathSegments);
        $namespaceParts = $pathSegments;

        // Convert each namespace part to proper case (PascalCase)
        $namespaceParts = array_map(function ($part) {
            return Str::studly(str_replace(['-', '_'], ' ', $part));
        }, $namespaceParts);

        // Convert controller name to proper case and add suffix if needed
        $controllerName = Str::studly(str_replace(['-', '_'], ' ', $controllerName));
        
        if (!str_ends_with($controllerName, 'Controller') && $pattern['add_suffix']) {
            $controllerName .= 'Controller';
        }

        // Build the full namespace
        $fullNamespace = $pattern['base'];
        if (!empty($namespaceParts)) {
            $fullNamespace .= '\\' . implode('\\', $namespaceParts);
        }
        $fullNamespace .= '\\' . $controllerName;

        return $fullNamespace;
    }

    /**
 * Generate fallback class names for edge cases and custom patterns.
 * 
 * This method demonstrates how to handle edge cases gracefully by providing
 * additional resolution strategies when standard patterns don't work.
 *
 * @param string $controllerPath The controller path to generate fallbacks for
 * @return array Array of fallback class name candidates
 */
protected function generateFallbackClassNames(string $controllerPath): array
{
    $fallbacks = [];
    
    // Convert path segments to proper case for fallback attempts
    $pathSegments = explode('.', $controllerPath);
    $convertedSegments = array_map(function ($segment) {
        return Str::studly(str_replace(['-', '_'], ' ', $segment));
    }, $pathSegments);
    
    // Try the path with proper case conversion
    $fallbacks[] = 'App\\Http\\Controllers\\' . implode('\\', $convertedSegments);
    
    // Try with Controller suffix added to the final segment if it doesn't already end with Controller
    $lastSegment = end($convertedSegments);
    if (!str_ends_with($lastSegment, 'Controller')) {
        $segmentsWithSuffix = $convertedSegments;
        $segmentsWithSuffix[count($segmentsWithSuffix) - 1] = $lastSegment . 'Controller';
        $fallbacks[] = 'App\\Http\\Controllers\\' . implode('\\', $segmentsWithSuffix);
    }
    
    return array_unique($fallbacks);
}

    /**
     * Validate that a controller class is appropriate for FlashHALT usage.
     * 
     * This method ensures that resolved controller classes meet the requirements
     * for safe dynamic instantiation and execution through FlashHALT.
     *
     * @param string $controllerClass The class name to validate
     * @throws ControllerResolutionException If validation fails
     */
    protected function validateControllerClass(string $controllerClass): void
    {
        try {
            $reflection = new ReflectionClass($controllerClass);
        } catch (ReflectionException $e) {
            throw new ControllerResolutionException(
                sprintf('Controller class "%s" cannot be analyzed: %s', $controllerClass, $e->getMessage()),
                'CONTROLLER_REFLECTION_FAILED'
            );
        }

        // Ensure the class can be instantiated (not abstract or interface)
        if ($reflection->isAbstract()) {
            throw new ControllerResolutionException(
                sprintf('Controller class "%s" is abstract and cannot be instantiated', $controllerClass),
                'ABSTRACT_CONTROLLER'
            );
        }

        if ($reflection->isInterface()) {
            throw new ControllerResolutionException(
                sprintf('"%s" is an interface, not a controller class', $controllerClass),
                'INTERFACE_NOT_CONTROLLER'
            );
        }

        // Verify the class follows Laravel controller patterns
        $this->validateControllerInheritance($reflection);

        // Check if the class is in an allowed location (if whitelist is configured)
        $this->validateControllerLocation($controllerClass);
    }

    /**
     * Validate that a controller follows Laravel's controller inheritance patterns.
     * 
     * This ensures that dynamically resolved controllers behave consistently
     * with manually defined routes by requiring appropriate base class inheritance.
     *
     * @param ReflectionClass $reflection Controller class reflection
     * @throws ControllerResolutionException If inheritance is inappropriate
     */
    protected function validateControllerInheritance(ReflectionClass $reflection): void
    {
        $controllerClass = $reflection->getName();
        
        // Check if it inherits from Laravel's base Controller
        $expectedBaseClasses = [
            'Illuminate\\Routing\\Controller',
            'App\\Http\\Controllers\\Controller',
        ];

        $isValidController = false;
        foreach ($expectedBaseClasses as $baseClass) {
            if ($reflection->isSubclassOf($baseClass) || $reflection->getName() === $baseClass) {
                $isValidController = true;
                break;
            }
        }

        if (!$isValidController) {
            throw new ControllerResolutionException(
                sprintf(
                    'Controller "%s" does not inherit from Laravel\'s base Controller class. ' .
                    'Controllers accessed through FlashHALT must extend the base Controller for security and consistency.',
                    $controllerClass
                ),
                'INVALID_CONTROLLER_INHERITANCE'
            );
        }
    }

    /**
     * Validate that a controller is in an allowed location.
     * 
     * This method enforces whitelist restrictions when configured, providing
     * an additional security layer for applications that need strict control
     * over which controllers can be accessed dynamically.
     *
     * @param string $controllerClass Controller class name to validate
     * @throws ControllerResolutionException If controller is not in allowed list
     */
    protected function validateControllerLocation(string $controllerClass): void
    {
        $allowedControllers = $this->config['development']['allowed_controllers'] ?? [];
        
        // If no whitelist is configured, all controllers are allowed
        if (empty($allowedControllers)) {
            return;
        }

        // Extract the simple class name for comparison
        $simpleClassName = class_basename($controllerClass);
        $classNameWithoutSuffix = str_replace('Controller', '', $simpleClassName);

        // Check against whitelist using multiple matching strategies
        $isAllowed = false;
        foreach ($allowedControllers as $allowedPattern) {
            if ($this->matchesControllerPattern($controllerClass, $allowedPattern)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            throw new ControllerResolutionException(
                sprintf(
                    'Controller "%s" is not in the allowed controllers list. ' .
                    'Add it to the flashhalt.development.allowed_controllers configuration to enable access.',
                    $controllerClass
                ),
                'CONTROLLER_NOT_WHITELISTED'
            );
        }
    }

/**
 * Check if a controller class matches an allowed pattern.
 * 
 * This method provides flexible pattern matching for controller whitelisting,
 * supporting exact matches, simple class names, and namespace patterns.
 *
 * @param string $controllerClass Full controller class name
 * @param string $pattern Pattern to match against
 * @return bool True if the controller matches the pattern
 */
protected function matchesControllerPattern(string $controllerClass, string $pattern): bool
{
    // Exact class name match
    if ($controllerClass === $pattern) {
        return true;
    }

    // Simple class name match
    if (class_basename($controllerClass) === $pattern) {
        return true;
    }

    // Simple class name without "Controller" suffix
    $simpleClassName = str_replace('Controller', '', class_basename($controllerClass));
    if ($simpleClassName === $pattern) {
        return true;
    }

    // Namespace pattern match (using wildcards)
    if (str_contains($pattern, '*')) {
        $regexPattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        
        // Try matching against full class name
        if (preg_match("/^{$regexPattern}$/", $controllerClass)) {
            return true;
        }
        
        // Try matching against simple class name
        if (preg_match("/^{$regexPattern}$/", class_basename($controllerClass))) {
            return true;
        }
        
        // Try matching against simple class name without "Controller" suffix
        $simpleClassName = str_replace('Controller', '', class_basename($controllerClass));
        if (preg_match("/^{$regexPattern}$/", $simpleClassName)) {
            return true;
        }
    }

    return false;
}

    /**
     * Validate that a controller method is safe and appropriate for FlashHALT access.
     * 
     * This method integrates with our SecurityValidator service to ensure that
     * all security requirements are met before allowing dynamic method execution.
     *
     * @param string $controllerClass Controller class name
     * @param string $methodName Method name to validate
     * @param string $httpMethod HTTP method for the request
     * @throws ControllerResolutionException If validation fails
     */
    protected function validateControllerMethod(string $controllerClass, string $methodName, string $httpMethod): void
    {
        try {
            // Delegate security validation to our specialized security service
            $this->securityValidator->validateControllerMethod($controllerClass, $methodName, $httpMethod);
        } catch (\Exception $e) {
            // Convert security validation exceptions to resolution exceptions
            // This provides consistent error handling throughout the resolution process
            throw new ControllerResolutionException(
                sprintf('Security validation failed for method "%s::%s": %s', $controllerClass, $methodName, $e->getMessage()),
                'SECURITY_VALIDATION_FAILED',
                $e
            );
        }
    }

    /**
     * Instantiate a controller through Laravel's service container.
     * 
     * This method demonstrates proper integration with Laravel's dependency injection
     * system, ensuring that controllers receive all their required dependencies
     * just as they would with traditional route definitions.
     *
     * @param string $controllerClass Controller class to instantiate
     * @return object Controller instance with dependencies injected
     * @throws ControllerResolutionException If instantiation fails
     */
    protected function instantiateController(string $controllerClass): object
    {
        try {
            // Use Laravel's service container to instantiate the controller
            // This ensures that all dependencies are properly injected and
            // that any service provider bindings are respected
            return $this->container->make($controllerClass);
        } catch (\Exception $e) {
            throw new ControllerResolutionException(
                sprintf(
                    'Failed to instantiate controller "%s": %s. ' .
                    'This may indicate missing dependencies or configuration issues.',
                    $controllerClass,
                    $e->getMessage()
                ),
                'CONTROLLER_INSTANTIATION_FAILED',
                $e
            );
        }
    }

    /**
     * Initialize namespace patterns for efficient controller discovery.
     * 
     * This method sets up the patterns used to resolve controller paths to
     * class names, prioritizing common Laravel conventions while supporting
     * custom patterns that applications might use.
     */
    protected function initializeNamespacePatterns(): void
    {
        $this->namespacePatterns = [
            // Standard Laravel controller pattern with Controller suffix
            [
                'base' => 'App\\Http\\Controllers',
                'add_suffix' => true,
                'priority' => 1,
            ],
            // Alternative controller pattern without forcing suffix
            [
                'base' => 'App\\Http\\Controllers',
                'add_suffix' => false,
                'priority' => 2,
            ],
            // Alternative controller namespace that some applications use
            [
                'base' => 'App\\Controllers',
                'add_suffix' => true,
                'priority' => 3,
            ],
            // Direct app namespace (for custom controller organization)
            [
                'base' => 'App',
                'add_suffix' => true,
                'priority' => 4,
            ],
        ];

        // Sort patterns by priority for optimal resolution order
        usort($this->namespacePatterns, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Create a unique cache key for resolution results.
     * 
     * The cache key includes all factors that might affect resolution results,
     * ensuring that cached results are only used when appropriate and that
     * configuration changes properly invalidate cached data.
     *
     * @param string $routePattern Route pattern being resolved
     * @param string $httpMethod HTTP method for the request
     * @return string Unique cache key
     */
    protected function createResolutionCacheKey(string $routePattern, string $httpMethod): string
    {
        return sprintf(
            'flashhalt:resolution:%s:%s:%s',
            md5($routePattern),
            strtolower($httpMethod),
            md5(serialize($this->config)) // Include config hash for cache invalidation
        );
    }

    /**
     * Clear resolution cache for development and testing purposes.
     * 
     * This method provides cache management capabilities that are particularly
     * useful during development when controllers and methods are changing frequently.
     *
     * @param string|null $pattern Optional specific pattern to clear
     */
    public function clearResolutionCache(?string $pattern = null): void
    {
        if ($pattern === null) {
            // Clear all FlashHALT resolution cache
            $this->memoryCache = [];
            // Note: In a real implementation, you might want more targeted cache clearing
            // rather than flushing the entire cache, depending on your cache store
        } else {
            // Clear cache for specific pattern
            $this->memoryCache = array_filter(
                $this->memoryCache,
                fn($key) => !str_contains($key, md5($pattern)),
                ARRAY_FILTER_USE_KEY
            );
        }
    }

    /**
     * Get resolution statistics for monitoring and debugging.
     * 
     * This method provides insights into resolution performance and cache
     * effectiveness, helping identify optimization opportunities.
     *
     * @return array Resolution performance statistics
     */
    public function getResolutionStats(): array
    {
        $totalAttempts = $this->resolutionStats['resolution_attempts'];
        
        return array_merge($this->resolutionStats, [
            'cache_hit_ratio' => $totalAttempts > 0 ? 
                round($this->resolutionStats['cache_hits'] / $totalAttempts * 100, 2) : 0,
            'memory_cache_size' => count($this->memoryCache),
            'success_rate' => $totalAttempts > 0 ? 
                round($this->resolutionStats['successful_resolutions'] / $totalAttempts * 100, 2) : 0,
        ]);
    }
}