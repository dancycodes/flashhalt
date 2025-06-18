<?php

namespace DancyCodes\FlashHalt\Services;

use DancyCodes\FlashHalt\Exceptions\SecurityValidationException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;

/**
 * SecurityValidator Service
 * 
 * This service implements comprehensive security validation for FlashHALT's
 * dynamic controller resolution. It ensures that only safe methods can be
 * called through HTMX requests while maintaining excellent performance
 * through intelligent caching strategies.
 * 
 * The security model operates on multiple layers:
 * 1. Method blacklisting - Prevents access to dangerous methods
 * 2. Visibility validation - Ensures only public methods are accessible
 * 3. Pattern matching - Blocks methods matching dangerous patterns
 * 4. Authorization integration - Respects Laravel's authorization system
 * 5. HTTP method semantics - Enforces appropriate HTTP verb usage
 */
class SecurityValidator
{
    /**
     * Security configuration settings from flashhalt.php config file.
     * This drives all validation behavior and can be customized per environment.
     */
    protected array $config;

    /**
     * Cache repository for storing validation results.
     * Validation involves expensive reflection operations, so caching is crucial for performance.
     */
    protected CacheRepository $cache;

    /**
     * Cache TTL for validation results in seconds.
     * Longer TTL improves performance but may cache stale results longer.
     */
    protected int $cacheTtl;

    /**
     * In-memory cache for validation results within a single request.
     * This provides immediate performance benefits for repeated validations.
     */
    protected array $memoryCache = [];

    /**
     * Compiled pattern cache for regular expression operations.
     * Pre-compiling patterns avoids repeated regex compilation overhead.
     */
    protected array $compiledPatterns = [];

    public function __construct(array $config, CacheRepository $cache)
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->cacheTtl = $config['cache_ttl'] ?? 3600;

        // Pre-compile regex patterns for better performance
        $this->compileSecurityPatterns();
    }

    /**
     * Validate that a controller method is safe to call through FlashHALT.
     * 
     * This is the main entry point for security validation. It performs
     * comprehensive checks to ensure the method is safe to call dynamically
     * while providing helpful error messages in development environments.
     *
     * @param string $controllerClass Fully qualified controller class name
     * @param string $methodName Method name to validate
     * @param string $httpMethod HTTP method used for the request (GET, POST, etc.)
     * @return bool True if the method is safe to call
     * @throws SecurityValidationException If validation fails
     */
    public function validateControllerMethod(string $controllerClass, string $methodName, string $httpMethod = 'GET'): bool
    {
        // Create a unique cache key for this validation request
        $cacheKey = $this->createValidationCacheKey($controllerClass, $methodName, $httpMethod);

        // Check memory cache first for immediate results
        if (isset($this->memoryCache[$cacheKey])) {
            return $this->memoryCache[$cacheKey];
        }

        // Check persistent cache for previous validation results
        $cachedResult = $this->cache->get($cacheKey);
        if ($cachedResult !== null) {
            $this->memoryCache[$cacheKey] = $cachedResult;
            return $cachedResult;
        }

        // Perform comprehensive validation since we don't have cached results
        $isValid = $this->performComprehensiveValidation($controllerClass, $methodName, $httpMethod);

        // Cache the result for future requests (only cache successful validations)
        if ($isValid) {
            $this->cache->put($cacheKey, $isValid, $this->cacheTtl);
        }

        // Store in memory cache regardless of result for this request
        $this->memoryCache[$cacheKey] = $isValid;

        return $isValid;
    }

    /**
     * Perform comprehensive security validation of a controller method.
     * 
     * This method implements the core security validation logic that protects
     * against various attack vectors and ensures only appropriate methods
     * can be called through FlashHALT's dynamic resolution.
     *
     * @param string $controllerClass Controller class to validate
     * @param string $methodName Method name to validate
     * @param string $httpMethod HTTP method for the request
     * @return bool True if validation passes
     * @throws SecurityValidationException If any validation check fails
     */
    protected function performComprehensiveValidation(string $controllerClass, string $methodName, string $httpMethod): bool
    {
        // Layer 1: Basic method name validation
        // This catches the most obvious security issues before expensive reflection
        $this->validateMethodName($methodName);

        // Layer 2: Method blacklist validation
        // Check against our comprehensive list of dangerous methods
        $this->validateAgainstBlacklist($methodName);

        // Layer 3: Pattern-based validation
        // Use regular expressions to catch dangerous method patterns
        $this->validateAgainstPatterns($methodName);

        // Layer 4: Reflection-based validation
        // Use PHP reflection to analyze the actual method properties
        $reflectionMethod = $this->getMethodReflection($controllerClass, $methodName);
        $this->validateMethodProperties($reflectionMethod, $controllerClass);

        // Layer 5: HTTP method semantics validation
        // Ensure the HTTP method is appropriate for the controller method
        $this->validateHttpMethodSemantics($reflectionMethod, $httpMethod);

        // Layer 6: Authorization integration (if enabled)
        // Check Laravel's authorization system if configured
        if ($this->config['require_authorization'] ?? false) {
            $this->validateAuthorization($controllerClass, $methodName);
        }

        // All validation layers passed successfully
        return true;
    }

    /**
     * Validate basic method name characteristics.
     * 
     * This performs quick validation checks that don't require reflection,
     * catching obvious security issues early in the validation process.
     *
     * @param string $methodName Method name to validate
     * @throws SecurityValidationException If validation fails
     */
    protected function validateMethodName(string $methodName): void
    {
        // Reject empty or invalid method names
        if (empty($methodName) || !is_string($methodName)) {
            throw new SecurityValidationException(
                'Method name must be a non-empty string',
                'INVALID_METHOD_NAME'
            );
        }

        // Reject method names that are too long (potential buffer overflow attempts)
        if (strlen($methodName) > 100) {
            throw new SecurityValidationException(
                'Method name exceeds maximum allowed length',
                'METHOD_NAME_TOO_LONG'
            );
        }

        // Reject method names with suspicious characters
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $methodName)) {
            throw new SecurityValidationException(
                'Method name contains invalid characters. Only alphanumeric characters and underscores are allowed.',
                'INVALID_METHOD_CHARACTERS'
            );
        }

        // Reject method names starting with underscores (often indicate private/internal methods)
        if (str_starts_with($methodName, '_')) {
            throw new SecurityValidationException(
                'Methods starting with underscore are not allowed for security reasons',
                'UNDERSCORE_METHOD_BLOCKED'
            );
        }
    }

    /**
     * Validate method against the comprehensive blacklist.
     * 
     * The blacklist contains methods that should never be accessible through
     * HTTP requests, including PHP magic methods, Laravel internals, and
     * commonly dangerous method patterns.
     *
     * @param string $methodName Method name to check
     * @throws SecurityValidationException If method is blacklisted
     */
    protected function validateAgainstBlacklist(string $methodName): void
    {
        $blacklist = $this->config['method_blacklist'] ?? [];

        // Perform case-insensitive comparison for security
        $lowercaseMethod = strtolower($methodName);
        $lowercaseBlacklist = array_map('strtolower', $blacklist);

        if (in_array($lowercaseMethod, $lowercaseBlacklist, true)) {
            throw new SecurityValidationException(
                sprintf(
                    'Method "%s" is explicitly blacklisted for security reasons. ' .
                    'This method contains functionality that should not be accessible via HTTP requests.',
                    $methodName
                ),
                'METHOD_BLACKLISTED'
            );
        }
    }

    /**
     * Validate method against dangerous pattern expressions.
     * 
     * This uses regular expressions to catch method names that match
     * potentially dangerous patterns, providing flexible security controls
     * beyond simple blacklisting.
     *
     * @param string $methodName Method name to validate
     * @throws SecurityValidationException If method matches dangerous pattern
     */
    protected function validateAgainstPatterns(string $methodName): void
    {
        $patterns = $this->config['method_pattern_blacklist'] ?? [];

        foreach ($patterns as $pattern) {
            // Use pre-compiled patterns for better performance
            if (isset($this->compiledPatterns[$pattern])) {
                $compiledPattern = $this->compiledPatterns[$pattern];
            } else {
                $compiledPattern = $pattern;
                $this->compiledPatterns[$pattern] = $compiledPattern;
            }

            if (preg_match($compiledPattern, $methodName)) {
                throw new SecurityValidationException(
                    sprintf(
                        'Method "%s" matches a blocked pattern: %s. ' .
                        'This pattern is blocked to prevent access to potentially sensitive functionality.',
                        $methodName,
                        $pattern
                    ),
                    'METHOD_PATTERN_BLOCKED'
                );
            }
        }
    }

    /**
     * Get reflection information for a controller method.
     * 
     * This safely creates reflection objects while handling various error
     * conditions that might occur when analyzing classes and methods.
     *
     * @param string $controllerClass Controller class name
     * @param string $methodName Method name
     * @return ReflectionMethod Method reflection object
     * @throws SecurityValidationException If reflection fails
     */
    protected function getMethodReflection(string $controllerClass, string $methodName): ReflectionMethod
    {
        try {
            // First, ensure the controller class exists and can be reflected
            $classReflection = new ReflectionClass($controllerClass);
        } catch (ReflectionException $e) {
            throw new SecurityValidationException(
                sprintf('Controller class "%s" does not exist or cannot be analyzed', $controllerClass),
                'CONTROLLER_NOT_FOUND'
            );
        }

        try {
            // Then, get reflection for the specific method
            $methodReflection = $classReflection->getMethod($methodName);
        } catch (ReflectionException $e) {
            throw new SecurityValidationException(
                sprintf('Method "%s" does not exist in controller "%s"', $methodName, $controllerClass),
                'METHOD_NOT_FOUND'
            );
        }

        return $methodReflection;
    }

    /**
     * Validate method properties using reflection analysis.
     * 
     * This examines the actual method properties to ensure it's safe to call
     * through HTTP requests, checking visibility, static nature, and other
     * security-relevant characteristics.
     *
     * @param ReflectionMethod $method Method reflection to analyze
     * @param string $controllerClass The controller class being validated
     * @throws SecurityValidationException If method properties are invalid
     */
    protected function validateMethodProperties(ReflectionMethod $method, string $controllerClass): void
    {
        // Ensure method is public (private/protected methods should not be HTTP accessible)
        if (!$method->isPublic()) {
            throw new SecurityValidationException(
                sprintf(
                    'Method "%s" is not public. Only public methods can be accessed through FlashHALT for security reasons.',
                    $method->getName()
                ),
                'METHOD_NOT_PUBLIC'
            );
        }

        // Reject static methods (they don't follow controller patterns and may bypass middleware)
        if ($method->isStatic()) {
            throw new SecurityValidationException(
                sprintf(
                    'Static method "%s" cannot be accessed through FlashHALT. ' .
                    'Only instance methods are supported to ensure proper controller lifecycle.',
                    $method->getName()
                ),
                'STATIC_METHOD_BLOCKED'
            );
        }

        // Reject abstract methods (they can't be executed)
        if ($method->isAbstract()) {
            throw new SecurityValidationException(
                sprintf('Abstract method "%s" cannot be executed', $method->getName()),
                'ABSTRACT_METHOD_BLOCKED'
            );
        }

        // Additional security checks for method characteristics
        $this->validateMethodCharacteristics($method, $controllerClass);
    }

    /**
 * Validate additional method characteristics for security.
 * 
 * This performs deeper analysis of method characteristics that might
 * indicate security risks or inappropriate usage patterns.
 *
 * @param ReflectionMethod $method Method reflection to analyze
 * @param string $controllerClass The controller class being validated
 * @throws SecurityValidationException If characteristics are problematic
 */
protected function validateMethodCharacteristics(ReflectionMethod $method, string $controllerClass): void
{
    // Check if the controller class itself extends dangerous base classes
    $controllerReflection = new ReflectionClass($controllerClass);
    $this->validateControllerInheritance($controllerReflection);
    
    // Check if method is declared in the controller class or inherited
    $declaringClass = $method->getDeclaringClass();
    
    // If method is inherited from a parent class, ensure it's safe to call
    if ($declaringClass->getName() !== $controllerClass) {
        $this->validateInheritedMethod($method, $declaringClass);
    }

    // Analyze method parameters for potential security issues
    $this->validateMethodParameters($method);

    // Check method documentation for security annotations
    $this->validateMethodDocumentation($method);
}

/**
 * Validate that the controller class doesn't extend dangerous base classes.
 * 
 * Controllers that extend certain system classes should not be accessible
 * via HTTP requests as they expose dangerous functionality.
 *
 * @param ReflectionClass $controllerClass Controller class reflection
 * @throws SecurityValidationException If controller extends dangerous class
 */
protected function validateControllerInheritance(ReflectionClass $controllerClass): void
{
    $dangerousBaseClasses = [
        'ReflectionClass', 'ReflectionMethod', 'ReflectionProperty',
        'PDO', 'PDOStatement', 'mysqli', 'SQLite3',
        'DirectoryIterator', 'RecursiveDirectoryIterator',
        'SplFileObject', 'SplFileInfo'
    ];

    foreach ($dangerousBaseClasses as $dangerousClass) {
        if ($controllerClass->isSubclassOf($dangerousClass)) {
            throw new SecurityValidationException(
                sprintf(
                    'Controller class "%s" extends "%s", which contains functionality ' .
                    'that should not be accessible via HTTP requests',
                    $controllerClass->getName(),
                    $dangerousClass
                ),
                'DANGEROUS_CONTROLLER_INHERITANCE'
            );
        }
    }
}

    /**
     * Validate inherited methods for security concerns.
     * 
     * Methods inherited from parent classes might not be intended for HTTP access,
     * so we need additional validation to ensure they're safe to expose.
     *
     * @param ReflectionMethod $method Method reflection
     * @param ReflectionClass $declaringClass Class where method is declared
     * @throws SecurityValidationException If inherited method is unsafe
     */
    protected function validateInheritedMethod(ReflectionMethod $method, ReflectionClass $declaringClass): void
    {
        $declaringClassName = $declaringClass->getName();

        // Block methods inherited from base PHP classes (potential security risk)
        $dangerousBaseClasses = [
            'ReflectionClass', 'ReflectionMethod', 'ReflectionProperty',
            'PDO', 'PDOStatement', 'mysqli', 'SQLite3',
            'DirectoryIterator', 'RecursiveDirectoryIterator',
            'SplFileObject', 'SplFileInfo'
        ];

        foreach ($dangerousBaseClasses as $dangerousClass) {
            if ($declaringClass->isSubclassOf($dangerousClass) || $declaringClassName === $dangerousClass) {
                throw new SecurityValidationException(
                    sprintf(
                        'Method "%s" is inherited from "%s", which contains functionality ' .
                        'that should not be accessible via HTTP requests',
                        $method->getName(),
                        $declaringClassName
                    ),
                    'DANGEROUS_INHERITED_METHOD'
                );
            }
        }
    }

    /**
     * Validate method parameters for security concerns.
     * 
     * Certain parameter patterns might indicate methods that aren't suitable
     * for HTTP access or might have security implications.
     *
     * @param ReflectionMethod $method Method reflection to analyze
     */
    protected function validateMethodParameters(ReflectionMethod $method): void
    {
        $parameters = $method->getParameters();

        foreach ($parameters as $parameter) {
            $paramType = $parameter->getType();
            
            // If parameter has a type hint, validate it's appropriate for HTTP access
            if ($paramType && !$paramType->isBuiltin()) {
                $typeName = $paramType->getName();
                
                // Block methods that expect dangerous types as parameters
                $dangerousTypes = [
                    'ReflectionClass', 'ReflectionMethod', 'PDO', 'mysqli',
                    'SplFileObject', 'DirectoryIterator'
                ];

                if (in_array($typeName, $dangerousTypes)) {
                    throw new SecurityValidationException(
                        sprintf(
                            'Method "%s" expects parameter of type "%s", which is not safe for HTTP access',
                            $method->getName(),
                            $typeName
                        ),
                        'DANGEROUS_PARAMETER_TYPE'
                    );
                }
            }
        }
    }

    /**
     * Validate method documentation for security annotations.
     * 
     * Check method docblocks for security-related annotations that might
     * indicate whether the method is intended for HTTP access.
     *
     * @param ReflectionMethod $method Method reflection to analyze
     */
    protected function validateMethodDocumentation(ReflectionMethod $method): void
    {
        $docComment = $method->getDocComment();
        
        if ($docComment) {
            // Check for annotations that indicate the method should not be HTTP accessible
            $blockingAnnotations = [
                '@internal', '@private', '@restricted', '@nohttp', '@unsafe'
            ];

            foreach ($blockingAnnotations as $annotation) {
                if (str_contains($docComment, $annotation)) {
                    throw new SecurityValidationException(
                        sprintf(
                            'Method "%s" is marked with "%s" annotation, indicating it should not be HTTP accessible',
                            $method->getName(),
                            $annotation
                        ),
                        'METHOD_ANNOTATION_BLOCKED'
                    );
                }
            }
        }
    }

    /**
     * Validate HTTP method semantics for the controller method.
     * 
     * This ensures that destructive operations can't be performed via GET requests
     * and that HTTP method usage follows RESTful conventions for security.
     *
     * @param ReflectionMethod $method Controller method reflection
     * @param string $httpMethod HTTP method used for the request
     * @throws SecurityValidationException If HTTP method is inappropriate
     */
    protected function validateHttpMethodSemantics(ReflectionMethod $method, string $httpMethod): void
    {
        if (!($this->config['enforce_http_method_semantics'] ?? true)) {
            return; // Skip validation if disabled in configuration
        }

        $methodName = strtolower($method->getName());
        $httpMethod = strtoupper($httpMethod);

        // Define methods that should only be accessible via specific HTTP methods
        $restrictedMethods = [
            'create' => ['POST'],
            'store' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'destroy' => ['DELETE'],
            'delete' => ['DELETE'],
            'remove' => ['DELETE'],
        ];

        // Check if method name indicates it should be restricted to certain HTTP methods
        foreach ($restrictedMethods as $pattern => $allowedHttpMethods) {
            if (str_contains($methodName, $pattern)) {
                if (!in_array($httpMethod, $allowedHttpMethods)) {
                    throw new SecurityValidationException(
                        sprintf(
                            'Method "%s" appears to perform "%s" operations but was called via %s. ' .
                            'This method should only be accessible via: %s',
                            $method->getName(),
                            $pattern,
                            $httpMethod,
                            implode(', ', $allowedHttpMethods)
                        ),
                        'INAPPROPRIATE_HTTP_METHOD'
                    );
                }
            }
        }
    }

    /**
     * Validate authorization for the controller method.
     * 
     * Integration with Laravel's authorization system to ensure that
     * authorization policies are respected even with dynamic resolution.
     *
     * @param string $controllerClass Controller class name
     * @param string $methodName Method name
     * @throws SecurityValidationException If authorization fails
     */
    protected function validateAuthorization(string $controllerClass, string $methodName): void
    {
        // This would integrate with Laravel's Gate and Policy system
        // For now, we'll implement a basic check structure that can be extended
        
        // In a full implementation, this would:
        // 1. Instantiate the controller to check for authorization middleware
        // 2. Look for method-specific authorization attributes
        // 3. Check against Laravel's Gate system
        // 4. Validate user permissions for the specific action
        
        // Placeholder for authorization validation
        // Real implementation would depend on the application's authorization strategy
    }

    /**
     * Create a unique cache key for validation results.
     * 
     * The cache key includes all factors that might affect validation results,
     * ensuring that cached results are only used when appropriate.
     *
     * @param string $controllerClass Controller class name
     * @param string $methodName Method name
     * @param string $httpMethod HTTP method
     * @return string Unique cache key
     */
    protected function createValidationCacheKey(string $controllerClass, string $methodName, string $httpMethod): string
    {
        return sprintf(
            'flashhalt:security:%s:%s:%s:%s',
            md5($controllerClass),
            md5($methodName),
            strtolower($httpMethod),
            md5(serialize($this->config)) // Include config hash to invalidate cache when config changes
        );
    }

    /**
     * Pre-compile regular expression patterns for better performance.
     * 
     * Compiling regex patterns once during initialization rather than
     * on every validation improves performance significantly.
     */
    protected function compileSecurityPatterns(): void
    {
        $patterns = $this->config['method_pattern_blacklist'] ?? [];
        
        foreach ($patterns as $pattern) {
            // Validate that the pattern is a valid regex
            if (@preg_match($pattern, '') === false) {
                // Log invalid pattern but don't fail completely
                if (function_exists('logger')) {
                    logger()->warning("Invalid regex pattern in FlashHALT security config: {$pattern}");
                }
                continue;
            }
            
            $this->compiledPatterns[$pattern] = $pattern;
        }
    }

    /**
     * Clear validation cache for a specific controller or method.
     * 
     * This is useful during development when controller methods change
     * and cached validation results need to be refreshed.
     *
     * @param string|null $controllerClass Optional controller class to clear
     * @param string|null $methodName Optional method name to clear
     */
    public function clearValidationCache(?string $controllerClass = null, ?string $methodName = null): void
    {
        if ($controllerClass === null && $methodName === null) {
            // Clear all FlashHALT security cache
            $this->cache->flush(); // This might be too aggressive in shared cache scenarios
            $this->memoryCache = [];
        } else {
            // Clear specific cache entries (would require more sophisticated cache key management)
            $this->memoryCache = [];
        }
    }

    /**
     * Get validation statistics for monitoring and debugging.
     * 
     * This provides insights into validation performance and can help
     * identify optimization opportunities.
     *
     * @return array Validation statistics
     */
    public function getValidationStats(): array
    {
        return [
            'memory_cache_size' => count($this->memoryCache),
            'compiled_patterns' => count($this->compiledPatterns),
            'cache_ttl' => $this->cacheTtl,
            'config_hash' => md5(serialize($this->config)),
        ];
    }

    /**
 * Check if a namespace is allowed based on configuration.
 * 
 * @param string $namespace The namespace to check
 * @return bool Whether the namespace is allowed
 */
public function isNamespaceAllowed(string $namespace): bool
{
    $allowedNamespaces = $this->config['allowed_namespaces'] ?? ['App\\Http\\Controllers\\*'];
    
    foreach ($allowedNamespaces as $allowedPattern) {
        if (str_ends_with($allowedPattern, '*')) {
            $prefix = substr($allowedPattern, 0, -1);
            if (str_starts_with($namespace, $prefix)) {
                return true;
            }
        } elseif ($namespace === $allowedPattern) {
            return true;
        }
    }
    
    return false;
}


}