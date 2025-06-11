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
    protected string $securityRule;

    /**
     * The severity level of this security violation, which affects
     * how the error is logged, reported, and responded to. Different
     * severity levels enable appropriate responses ranging from
     * helpful warnings to immediate request termination.
     */
    protected string $severity;

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
        // Call the parent constructor to set up basic exception functionality
        parent::__construct($message, $errorCode, $context);

        // Store security-specific information
        $this->securityRule = $securityRule;
        $this->severity = strtolower($severity);
        $this->securityContext = $securityContext;

        // Validate severity level and default to medium if invalid
        if (!in_array($this->severity, ['low', 'medium', 'high', 'critical'])) {
            $this->severity = 'medium';
        }
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
     * @return array Sanitized security context
     */
    public function getSecurityContext(): array
    {
        return $this->securityContext;
    }

    /**
     * Determine if this exception should be reported to security monitoring systems.
     * 
     * This method allows fine-grained control over which security violations
     * are reported to external monitoring systems, preventing alert fatigue
     * while ensuring that serious violations are tracked appropriately.
     *
     * @return bool True if this violation should be reported
     */
    public function shouldReport(): bool
    {
        // High and critical severity violations should always be reported
        if (in_array($this->severity, ['high', 'critical'])) {
            return true;
        }

        // Medium severity violations should be reported in production
        if ($this->severity === 'medium' && app()->environment('production')) {
            return true;
        }

        // Low severity violations are typically not reported to avoid noise
        return false;
    }

    /**
     * Get the appropriate HTTP status code for this security violation.
     * 
     * Different types of security violations warrant different HTTP status
     * codes, helping clients understand the nature of the problem and
     * respond appropriately.
     *
     * @return int HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        // Map security violation types to appropriate HTTP status codes
        return match ($this->errorCode) {
            'METHOD_BLACKLISTED',
            'METHOD_PATTERN_BLOCKED',
            'DANGEROUS_INHERITED_METHOD',
            'DANGEROUS_PARAMETER_TYPE',
            'METHOD_ANNOTATION_BLOCKED' => 403, // Forbidden
            
            'METHOD_NOT_PUBLIC',
            'STATIC_METHOD_BLOCKED',
            'ABSTRACT_METHOD_BLOCKED' => 404, // Not Found (method effectively doesn't exist for HTTP access)
            
            'INAPPROPRIATE_HTTP_METHOD' => 405, // Method Not Allowed
            
            'INVALID_METHOD_NAME',
            'METHOD_NAME_TOO_LONG',
            'INVALID_METHOD_CHARACTERS',
            'UNDERSCORE_METHOD_BLOCKED' => 400, // Bad Request
            
            default => 403 // Default to Forbidden for security violations
        };
    }

    /**
     * Initialize security-specific error details and suggestions.
     * 
     * This method provides targeted guidance for security violations,
     * helping developers understand not just what went wrong, but why
     * the security control exists and how to work within security boundaries.
     */
    protected function initializeSpecificErrorDetails(): void
    {
        // Add security-specific suggestions based on the error code
        $this->addSecuritySpecificSuggestions();
        
        // Add security documentation links
        $this->addDocumentationLink('FlashHALT Security Guide', 'https://flashhalt.dev/docs/security');
        $this->addDocumentationLink('Laravel Security Best Practices', 'https://laravel.com/docs/security');
        
        // Add severity-specific guidance
        $this->addSeveritySpecificGuidance();
    }

    /**
     * Add suggestions specific to the security violation type.
     * 
     * This method provides targeted advice for different types of security
     * violations, helping developers understand both what went wrong and
     * how to fix it while maintaining security best practices.
     */
    protected function addSecuritySpecificSuggestions(): void
    {
        match ($this->errorCode) {
            'METHOD_BLACKLISTED' => $this->addSuggestion(
                'This method is blacklisted for security reasons. Consider creating a new public method that safely exposes the functionality you need.'
            ),
            
            'METHOD_PATTERN_BLOCKED' => $this->addSuggestion(
                'Method name matches a blocked pattern. Avoid method names containing sensitive keywords like "password", "token", or "secret".'
            ),
            
            'METHOD_NOT_PUBLIC' => $this->addSuggestion(
                'Only public methods can be accessed through FlashHALT. Make the method public or create a public wrapper method.'
            ),
            
            'STATIC_METHOD_BLOCKED' => $this->addSuggestion(
                'Static methods cannot be accessed through FlashHALT. Create an instance method or use traditional routing for static methods.'
            ),
            
            'INAPPROPRIATE_HTTP_METHOD' => $this->addSuggestion(
                'This method appears to perform destructive operations and should not be accessible via GET requests. Use POST, PUT, PATCH, or DELETE as appropriate.'
            ),
            
            'INVALID_METHOD_NAME' => $this->addSuggestion(
                'Method names should only contain alphanumeric characters and underscores, and should not start with underscore.'
            ),
            
            'DANGEROUS_INHERITED_METHOD' => $this->addSuggestion(
                'This method is inherited from a class that contains functionality not suitable for HTTP access. Override the method in your controller or use a different approach.'
            ),
            
            default => $this->addSuggestion(
                'Review the FlashHALT security documentation to understand which methods can be safely exposed through HTTP requests.'
            )
        };
    }

    /**
     * Add guidance specific to the violation severity level.
     * 
     * Different severity levels warrant different types of guidance,
     * from gentle suggestions for low-severity issues to urgent
     * warnings for critical violations.
     */
    protected function addSeveritySpecificGuidance(): void
    {
        match ($this->severity) {
            'low' => $this->addSuggestion(
                'This is a low-severity security issue that should be addressed when convenient.'
            ),
            
            'medium' => $this->addSuggestion(
                'This security violation should be addressed promptly to maintain application security.'
            ),
            
            'high' => $this->addSuggestion(
                'This is a high-severity security issue that should be addressed immediately.'
            ),
            
            'critical' => $this->addSuggestion(
                'CRITICAL SECURITY VIOLATION: This issue poses a serious security risk and must be fixed immediately.'
            )
        };
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
        if (request()) {
            $report['request_info'] = [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'url' => request()->fullUrl(),
                'timestamp' => now()->toISOString(),
            ];
            
            // Include user information if available (but not sensitive details)
            if (auth()->check()) {
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
     * Create a sanitized development error message that provides helpful
     * information without exposing sensitive security details.
     * 
     * Security error messages must balance helpfulness with security,
     * providing enough information for legitimate debugging while avoiding
     * information disclosure that could assist malicious actors.
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
     * @return string Sanitized value safe for display
     */
    protected function sanitizeContextValue(string $key, $value): string
    {
        // List of context keys that might contain sensitive information
        $sensitiveKeys = [
            'password', 'token', 'secret', 'key', 'hash', 'salt',
            'api_key', 'auth_token', 'session_id', 'csrf_token'
        ];

        // Check if this key might contain sensitive information
        $isSensitive = false;
        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains(strtolower($key), $sensitiveKey)) {
                $isSensitive = true;
                break;
            }
        }

        if ($isSensitive) {
            return '[REDACTED FOR SECURITY]';
        }

        // For non-sensitive values, convert to string safely
        if (is_string($value)) {
            // Truncate very long strings to prevent log bloat
            return strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;
        } elseif (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        } else {
            return (string) $value;
        }
    }

    /**
     * Create a production-safe error message that avoids information disclosure.
     * 
     * Production security error messages must be carefully designed to provide
     * appropriate feedback without revealing information that could be useful
     * to attackers or create poor user experiences.
     *
     * @return string Safe production error message
     */
    public function toProductionString(): string
    {
        // In production, security violations should provide minimal information
        // to avoid helping malicious actors understand the security controls
        return match ($this->severity) {
            'low', 'medium' => 'Access denied. The requested operation is not permitted.',
            'high', 'critical' => 'Access denied. This request has been logged for security review.',
            default => 'Access denied.'
        };
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