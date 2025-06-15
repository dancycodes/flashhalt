<?php

namespace DancyCodes\FlashHalt\Exceptions;

/**
 * SecurityValidationException - Specialized Exception for Security Violations
 * 
 * This exception class handles all security-related errors in FlashHALT's
 * dynamic controller resolution system. It demonstrates how to design
 * security-focused error handling that provides helpful information to
 * developers while maintaining appropriate security boundaries.
 * 
 * Security exceptions require special consideration because they must balance
 * several competing needs:
 * - Providing enough information for legitimate developers to fix problems
 * - Avoiding information disclosure that could help malicious actors
 * - Educating developers about security best practices and requirements
 * - Maintaining detailed logs for security monitoring and analysis
 * 
 * This class extends FlashHaltException to provide specialized functionality
 * for security violations while maintaining consistency with the overall
 * FlashHALT error handling architecture.
 */
class SecurityValidationException extends FlashHaltException
{
    /**
     * The security rule that was violated, providing specific context
     * about which security control detected the problem. This information
     * helps developers understand which specific security requirement
     * their code violated, enabling targeted fixes.
     */
    protected string $securityRule = 'UNKNOWN_RULE';

    /**
     * The severity level of this security violation, which affects
     * how the error is logged, reported, and responded to. Different
     * severity levels enable appropriate responses ranging from
     * helpful warnings to immediate request termination.
     */
    protected string $severity = 'medium';

    /**
     * Additional security context that might be relevant for analysis
     * but should be carefully controlled to avoid information disclosure.
     * This might include sanitized request information, validation
     * details, or other security-relevant data.
     */
    protected array $securityContext = [];

    /**
     * Create a new security validation exception with comprehensive context.
     * 
     * This constructor demonstrates how to design security exception creation
     * that captures relevant information for debugging and monitoring while
     * maintaining appropriate security boundaries. The constructor encourages
     * providing detailed context while offering sensible defaults.
     *
     * @param string $message Human-readable error description
     * @param string $errorCode Structured error code for programmatic handling
     * @param string $securityRule The specific security rule that was violated
     * @param string $severity The severity level (low, medium, high, critical)
     * @param array $context Additional context information
     * @param array $securityContext Security-specific context information
     */
    public function __construct(
        string $message,
        string $errorCode = 'SECURITY_VIOLATION',
        string $securityRule = 'UNKNOWN_RULE',
        string $severity = 'medium',
        array $context = [],
        array $securityContext = []
    ) {
        // Store security-specific information BEFORE calling parent constructor
        // This is crucial because the parent constructor might call methods that access these properties
        $this->securityRule = $securityRule;
        $this->severity = strtolower($severity);
        $this->securityContext = $securityContext;

        // Validate severity level and default to medium if invalid
        if (!in_array($this->severity, ['low', 'medium', 'high', 'critical'])) {
            $this->severity = 'medium';
        }

        // Call the parent constructor to set up basic exception functionality
        parent::__construct($message, $errorCode, $context);
    }

    /**
     * Get the security rule that was violated.
     * 
     * This method provides access to the specific security rule that
     * detected the violation, enabling code to respond appropriately
     * to different types of security issues.
     *
     * @return string The violated security rule identifier
     */
    public function getSecurityRule(): string
    {
        return $this->securityRule;
    }

    /**
     * Get the severity level of this security violation.
     * 
     * Severity levels help determine appropriate responses to security
     * violations, from logging and warnings for low-severity issues
     * to immediate termination for critical violations.
     *
     * @return string The severity level (low, medium, high, critical)
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Get security-specific context information.
     * 
     * This method provides access to security context that has been
     * sanitized and validated to avoid information disclosure while
     * still providing useful debugging and analysis information.
     *
     * @return array Security context information
     */
    public function getSecurityContext(): array
    {
        return $this->securityContext;
    }

    /**
     * Add security context information to the exception.
     * 
     * This method allows security validation code to build up comprehensive
     * context about what was being validated when the violation occurred,
     * providing rich debugging information while maintaining security boundaries.
     *
     * @param string $key Context key
     * @param mixed $value Context value (will be sanitized)
     * @return self Returns self for method chaining
     */
    public function addSecurityContext(string $key, mixed $value): self
    {
        $this->securityContext[$key] = $this->sanitizeContextValue($key, $value);
        return $this;
    }

    /**
     * Get the appropriate HTTP status code for this security violation.
     * 
     * Different types of security violations should result in different
     * HTTP status codes to provide appropriate feedback while avoiding
     * information disclosure that could help attackers.
     *
     * @return int Appropriate HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            'METHOD_BLACKLISTED', 'CONTROLLER_BLACKLISTED' => 403, // Forbidden
            'INAPPROPRIATE_HTTP_METHOD' => 405, // Method Not Allowed
            'INVALID_METHOD_NAME', 'INVALID_CONTROLLER_NAME' => 400, // Bad Request
            'UNAUTHORIZED_ACCESS' => 401, // Unauthorized
            'INSUFFICIENT_PERMISSIONS' => 403, // Forbidden
            default => 403 // Default to Forbidden for security violations
        };
    }

    /**
     * Determine whether this security violation should be reported to monitoring systems.
     * 
     * Security violations should generally be reported for monitoring and analysis,
     * but the reporting behavior can be tuned based on severity level and
     * environment to avoid alert fatigue while maintaining security oversight.
     *
     * @return bool Whether this exception should be reported
     */
    public function shouldReport(): bool
    {
        // Always report high and critical severity violations
        if (in_array($this->severity, ['high', 'critical'])) {
            return true;
        }

        // In production, report medium severity violations
        if ($this->severity === 'medium' && app()->environment('production')) {
            return true;
        }

        // In development, be more selective to reduce noise
        if (app()->environment('local', 'development')) {
            // Only report medium+ severity issues in development
            return in_array($this->severity, ['medium', 'high', 'critical']);
        }

        // Default to reporting for security violations
        return true;
    }

    /**
     * Create a production-safe error message that avoids information disclosure.
     * 
     * Security error messages in production must be carefully crafted to provide
     * appropriate feedback without revealing information that could assist
     * malicious actors in understanding or exploiting the application.
     *
     * @return string Production-safe error message
     */
    public function toProductionString(): string
    {
        return match ($this->severity) {
            'low' => 'The requested operation could not be completed.',
            'medium' => 'Access denied. The requested operation is not permitted.',
            'high', 'critical' => 'Access denied. This request has been logged for security review.',
            default => 'Access denied.'
        };
    }

    /**
     * Create a development-friendly error message with detailed information.
     * 
     * Development error messages can include more detailed information to help
     * developers understand and fix security issues, but should still be
     * mindful of not exposing sensitive information in logs or error displays.
     *
     * @return string Sanitized development error message
     */
    public function toDevelopmentString(): string
    {
        $output = [];
        
        // Start with basic error information
        $output[] = "FlashHALT Security Violation: {$this->getMessage()}";
        $output[] = "Error Code: {$this->errorCode}";
        $output[] = "Security Rule: {$this->securityRule}";
        $output[] = "Severity: " . strtoupper($this->severity);
        $output[] = "Location: {$this->getFile()}:{$this->getLine()}";
        $output[] = "";

        // Add context information (sanitized for security)
        if (!empty($this->context)) {
            $output[] = "Context Information:";
            foreach ($this->context as $key => $value) {
                // Sanitize potentially sensitive context information
                $sanitizedValue = $this->sanitizeContextValue($key, $value);
                $output[] = "  {$key}: {$sanitizedValue}";
            }
            $output[] = "";
        }

        // Add security-specific context if available
        if (!empty($this->securityContext)) {
            $output[] = "Security Context:";
            foreach ($this->securityContext as $key => $value) {
                $sanitizedValue = $this->sanitizeContextValue($key, $value);
                $output[] = "  {$key}: {$sanitizedValue}";
            }
            $output[] = "";
        }

        // Add suggestions
        if (!empty($this->suggestions)) {
            $output[] = "Security Recommendations:";
            foreach ($this->suggestions as $index => $suggestion) {
                $output[] = "  " . ($index + 1) . ". {$suggestion}";
            }
            $output[] = "";
        }

        // Add documentation links
        if (!empty($this->documentationLinks)) {
            $output[] = "Security Documentation:";
            foreach ($this->documentationLinks as $title => $url) {
                $output[] = "  {$title}: {$url}";
            }
            $output[] = "";
        }

        // Add severity-specific warnings
        if (in_array($this->severity, ['high', 'critical'])) {
            $output[] = "⚠️  WARNING: This is a " . strtoupper($this->severity) . " severity security violation that requires immediate attention!";
            $output[] = "";
        }

        return implode("\n", $output);
    }

    /**
     * Sanitize context values to prevent information disclosure in error messages.
     * 
     * This method demonstrates how to handle potentially sensitive information
     * in error contexts while still providing useful debugging information.
     * It shows how to balance transparency with security considerations.
     *
     * @param string $key The context key
     * @param mixed $value The context value to sanitize
     * @return string Sanitized value safe for logging and display
     */
    protected function sanitizeContextValue(string $key, mixed $value): string
    {
        // Convert value to string for processing
        $stringValue = is_string($value) ? $value : json_encode($value);
        
        // List of sensitive key patterns that should be redacted
        $sensitivePatterns = [
            '/password/i',
            '/secret/i',
            '/token/i',
            '/key/i',
            '/credential/i',
            '/auth/i',
            '/session/i',
        ];
        
        // Check if the key contains sensitive information
        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return '[REDACTED FOR SECURITY]';
            }
        }
        
        // Check if the value contains sensitive patterns
        $sensitiveValuePatterns = [
            '/Bearer\s+[a-zA-Z0-9_-]+/i',  // Bearer tokens
            '/sk_[a-zA-Z0-9_-]+/i',        // Secret keys
            '/pk_[a-zA-Z0-9_-]+/i',        // Public keys
            '/[a-zA-Z0-9]{32,}/i',         // Long hex strings (potential hashes/tokens)
        ];
        
        foreach ($sensitiveValuePatterns as $pattern) {
            if (preg_match($pattern, $stringValue)) {
                return '[REDACTED FOR SECURITY]';
            }
        }
        
        // Truncate very long values to prevent log pollution
        if (strlen($stringValue) > 500) {
            return substr($stringValue, 0, 500) . '... [TRUNCATED]';
        }
        
        return $stringValue;
    }

    /**
     * Initialize error-specific details based on the security rule and severity.
     * 
     * This method sets up suggestions and documentation links that are specifically
     * relevant to security violations, providing educational guidance that helps
     * developers understand and implement proper security practices.
     */
    protected function initializeSpecificErrorDetails(): void
    {
        // Add security-rule-specific suggestions
        match ($this->securityRule) {
            'METHOD_BLACKLIST_CHECK' => $this->addMethodBlacklistSuggestions(),
            'METHOD_PATTERN_CHECK' => $this->addMethodPatternSuggestions(),
            'HTTP_METHOD_SEMANTICS' => $this->addHttpMethodSuggestions(),
            'CONTROLLER_WHITELIST_CHECK' => $this->addControllerWhitelistSuggestions(),
            'AUTHORIZATION_CHECK' => $this->addAuthorizationSuggestions(),
            default => $this->addGeneralSecuritySuggestions()
        };

        // Add severity-specific suggestions
        match ($this->severity) {
            'low' => $this->addSuggestion(
                'This is a low-severity security issue that should be addressed during regular maintenance.'
            ),
            
            'medium' => $this->addSuggestion(
                'This security issue should be addressed soon to maintain application security.'
            ),
            
            'high' => $this->addSuggestion(
                'HIGH PRIORITY: This security issue should be addressed immediately.'
            ),
            
            'critical' => $this->addSuggestion(
                'CRITICAL SECURITY VIOLATION: This issue poses a serious security risk and must be fixed immediately.'
            )
        };
    }

    /**
     * Add suggestions specific to method blacklist violations.
     */
    protected function addMethodBlacklistSuggestions(): void
    {
        $this->addSuggestion('Remove the method from your FlashHALT route pattern');
        $this->addSuggestion('Use a different method name that is not on the security blacklist');
        $this->addSuggestion('Check the method_blacklist configuration in config/flashhalt.php');
        $this->addSuggestion('Consider if this method should be accessible via HTTP requests');
    }

    /**
     * Add suggestions specific to method pattern violations.
     */
    protected function addMethodPatternSuggestions(): void
    {
        $this->addSuggestion('Choose a method name that does not match blocked security patterns');
        $this->addSuggestion('Avoid method names containing sensitive keywords like "password", "token", or "secret"');
        $this->addSuggestion('Review the method_pattern_blacklist configuration for blocked patterns');
        $this->addSuggestion('Consider renaming the method to something more appropriate for HTTP access');
    }

    /**
     * Add suggestions specific to HTTP method semantic violations.
     */
    protected function addHttpMethodSuggestions(): void
    {
        $this->addSuggestion('Use GET requests for read-only operations');
        $this->addSuggestion('Use POST requests for creating new resources');
        $this->addSuggestion('Use PUT/PATCH requests for updating existing resources');
        $this->addSuggestion('Use DELETE requests for removing resources');
        $this->addSuggestion('Ensure destructive operations cannot be accessed via GET requests');
    }

    /**
     * Add suggestions specific to controller whitelist violations.
     */
    protected function addControllerWhitelistSuggestions(): void
    {
        $this->addSuggestion('Add your controller namespace to allowed_namespaces in config/flashhalt.php');
        $this->addSuggestion('Move your controller to an allowed namespace like App\\Http\\Controllers');
        $this->addSuggestion('Verify that your controller is in the correct directory structure');
        $this->addSuggestion('Check that the namespace whitelist includes all necessary controller locations');
    }

    /**
     * Add suggestions specific to authorization violations.
     */
    protected function addAuthorizationSuggestions(): void
    {
        $this->addSuggestion('Add proper authorization checks to your controller method');
        $this->addSuggestion('Use Laravel\'s authorize() method or middleware for access control');
        $this->addSuggestion('Verify that the user has the required permissions for this action');
        $this->addSuggestion('Check your application\'s authorization policies and gates');
    }

    /**
     * Add general security suggestions that apply to multiple violation types.
     */
    protected function addGeneralSecuritySuggestions(): void
    {
        $this->addSuggestion('Review FlashHALT security configuration in config/flashhalt.php');
        $this->addSuggestion('Follow Laravel security best practices for controller methods');
        $this->addSuggestion('Ensure proper input validation and sanitization');
        $this->addSuggestion('Consider implementing additional security middleware');
        $this->addSuggestion('Review the FlashHALT security documentation');
    }

    /**
     * Generate a security-focused error report for logging and monitoring.
     * 
     * This method creates comprehensive error reports that include security-relevant
     * information while being careful to avoid including sensitive data that
     * could create additional security risks if the logs are compromised.
     *
     * @param bool $includeTrace Whether to include stack trace information
     * @return array Security-focused error report
     */
    public function toSecurityReport(bool $includeTrace = false): array
    {
        $report = parent::toArray($includeTrace);
        
        // Add security-specific information
        $report['security_rule'] = $this->securityRule;
        $report['severity'] = $this->severity;
        $report['security_context'] = $this->securityContext;
        $report['should_report'] = $this->shouldReport();
        $report['http_status_code'] = $this->getHttpStatusCode();
        
        // Add request information that's relevant for security analysis
        if (function_exists('request') && request()) {
            $report['request_info'] = [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'url' => request()->fullUrl(),
                'timestamp' => now()->toISOString(),
            ];
            
            // Include user information if available (but not sensitive details)
            if (function_exists('auth') && auth()->check()) {
                $report['user_info'] = [
                    'id' => auth()->id(),
                    'authenticated' => true,
                ];
            } else {
                $report['user_info'] = [
                    'authenticated' => false,
                ];
            }
        }
        
        return $report;
    }

    /**
     * Create a specialized HTTP response for security violations.
     * 
     * Security violations often require specialized HTTP responses that
     * include appropriate status codes, security headers, and minimal
     * information disclosure while still providing useful feedback.
     *
     * @param bool $isDevelopment Whether to include development details
     * @return array HTTP response data optimized for security violations
     */
    public function toHttpResponse(bool $isDevelopment = false): array
    {
        $response = parent::toHttpResponse($isDevelopment);
        
        // Override the message with security-appropriate content
        $response['message'] = $isDevelopment ? $this->getMessage() : $this->toProductionString();
        
        // Add security-specific response data
        $response['security_violation'] = true;
        $response['severity'] = $this->severity;
        
        if ($isDevelopment) {
            $response['security_rule'] = $this->securityRule;
            $response['security_context'] = $this->securityContext;
        }
        
        // Add security headers recommendations
        $response['recommended_headers'] = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
        ];
        
        return $response;
    }
}