<?php

namespace DancyCodes\FlashHalt\Exceptions;

use Exception;

/**
 * FlashHaltException - Base Exception for All FlashHALT Errors
 * 
 * This base exception class demonstrates how to design exception hierarchies
 * that provide both common functionality and specialized behavior. It serves
 * as the foundation for all FlashHALT-specific exceptions, ensuring consistent
 * error handling and rich context information throughout the package.
 * 
 * The base exception provides several key features:
 * - Structured error codes for programmatic error handling
 * - Rich context information for debugging and troubleshooting
 * - Educational error messages that help developers learn FlashHALT patterns
 * - Integration hooks for logging, monitoring, and error reporting systems
 * - Environment-aware error formatting for development vs production
 * 
 * This design demonstrates several important exception handling principles:
 * - Exceptions should provide actionable information, not just error notifications
 * - Error codes should be meaningful and consistent across the application
 * - Context information should help developers understand both what went wrong and why
 * - Exception design should consider both human developers and automated systems
 */
class FlashHaltException extends Exception
{
    /**
     * Structured error code that provides programmatic access to error types.
     * This enables automated error handling, monitoring systems, and detailed
     * error categorization that goes beyond simple exception class checking.
     * 
     * Error codes follow a consistent pattern:
     * - SECURITY_* for security-related errors
     * - RESOLUTION_* for controller resolution errors  
     * - CONFIGURATION_* for configuration-related errors
     * - VALIDATION_* for input validation errors
     */
    protected string $errorCode;

    /**
     * Additional context information that helps with debugging and troubleshooting.
     * This might include attempted class names, configuration values, request
     * details, or any other information that helps developers understand what
     * the system was trying to do when the error occurred.
     */
    protected array $context = [];

    /**
     * Suggestions for how developers can fix or avoid this error.
     * This educational approach transforms errors from frustrating roadblocks
     * into learning opportunities that help developers become more effective.
     */
    protected array $suggestions = [];

    /**
     * Documentation references for this type of error.
     * Providing links to relevant documentation helps developers get deeper
     * understanding and context about the error and related concepts.
     */
    protected array $documentationLinks = [];

    /**
     * Create a new FlashHALT exception with rich context information.
     * 
     * This constructor demonstrates how to design exception creation that
     * encourages providing comprehensive error information while maintaining
     * backward compatibility with standard exception usage patterns.
     *
     * @param string $message Human-readable error message
     * @param string $errorCode Structured error code for programmatic handling
     * @param array $context Additional context information for debugging
     * @param Exception|null $previous Previous exception for error chaining
     * @param int $code Numeric error code (for compatibility with base Exception)
     */
    public function __construct(
        string $message,
        string $errorCode = 'UNKNOWN_ERROR',
        array $context = [],
        ?Exception $previous = null,
        int $code = 0
    ) {
        // Call the parent constructor with the basic exception information
        parent::__construct($message, $code, $previous);

        // Store our enhanced error information
        $this->errorCode = $errorCode;
        $this->context = $context;

        // Initialize suggestions and documentation links based on error code
        $this->initializeErrorDetails();
    }

    /**
     * Get the structured error code for programmatic error handling.
     * 
     * This method provides a reliable way for code to determine the specific
     * type of error that occurred, enabling sophisticated error handling logic
     * that goes beyond simple exception type checking.
     *
     * @return string The structured error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get additional context information about the error.
     * 
     * Context information helps developers understand what the system was
     * attempting to do when the error occurred, providing debugging insights
     * that go far beyond the basic error message.
     *
     * @return array Context information array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Add context information to the exception.
     * 
     * This method allows code that catches and re-throws exceptions to add
     * additional context information, building up a rich picture of what
     * happened as the error propagates through the system.
     *
     * @param string $key Context key
     * @param mixed $value Context value
     * @return self Returns self for method chaining
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get suggestions for resolving this error.
     * 
     * Suggestions transform errors from frustrating roadblocks into educational
     * opportunities by providing concrete steps developers can take to fix
     * the problem and avoid similar issues in the future.
     *
     * @return array Array of suggestion strings
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * Add a suggestion for resolving this error.
     * 
     * This method allows specialized exception subclasses to provide specific
     * suggestions based on the error context and type.
     *
     * @param string $suggestion A helpful suggestion for resolving the error
     * @return self Returns self for method chaining
     */
    public function addSuggestion(string $suggestion): self
    {
        $this->suggestions[] = $suggestion;
        return $this;
    }

    /**
     * Get documentation links related to this error.
     * 
     * Documentation links help developers get deeper understanding about
     * the concepts and patterns related to the error, turning error handling
     * into a learning opportunity.
     *
     * @return array Array of documentation URLs
     */
    public function getDocumentationLinks(): array
    {
        return $this->documentationLinks;
    }

    /**
     * Add a documentation link related to this error.
     *
     * @param string $title Link title or description
     * @param string $url Documentation URL
     * @return self Returns self for method chaining
     */
    public function addDocumentationLink(string $title, string $url): self
    {
        $this->documentationLinks[$title] = $url;
        return $this;
    }

    /**
     * Generate a comprehensive error report for debugging purposes.
     * 
     * This method demonstrates how to provide rich error information that
     * helps both human developers and automated systems understand and
     * respond to errors effectively.
     *
     * @param bool $includeTrace Whether to include the stack trace
     * @return array Comprehensive error report
     */
    // In src/Exceptions/FlashHaltException.php
    // Replace the toArray method with this corrected version:

    public function toArray(bool $includeTrace = false): array
    {
        $report = [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'suggestions' => $this->suggestions,
            'documentation_links' => $this->documentationLinks,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'timestamp' => now()->format(\DateTime::ATOM),
        ];

        if ($includeTrace) {
            $report['trace'] = $this->getTrace();
        }

        // Include previous exception information if available
        if ($this->getPrevious()) {
            $report['previous_exception'] = [
                'class' => get_class($this->getPrevious()),
                'message' => $this->getPrevious()->getMessage(),
                'file' => $this->getPrevious()->getFile(),
                'line' => $this->getPrevious()->getLine(),
            ];
        }

        return $report;
    }

    /**
     * Generate a JSON representation of the error for API responses.
     * 
     * This method provides a standardized way to serialize error information
     * for API responses, logging systems, and other automated consumers.
     *
     * @param bool $includeTrace Whether to include the stack trace
     * @return string JSON representation of the error
     */
    public function toJson(bool $includeTrace = false): string
    {
        return json_encode($this->toArray($includeTrace), JSON_PRETTY_PRINT);
    }

    /**
     * Generate a human-friendly error message for development environments.
     * 
     * This method creates rich, educational error messages that help developers
     * understand what went wrong and how to fix it. The formatting is optimized
     * for readability in development tools and debug output.
     *
     * @return string Formatted development error message
     */
    public function toDevelopmentString(): string
    {
        $output = [];
        
        // Start with the basic error information
        $output[] = "FlashHALT Error: {$this->getMessage()}";
        $output[] = "Error Code: {$this->errorCode}";
        $output[] = "Location: {$this->getFile()}:{$this->getLine()}";
        $output[] = "";

        // Add context information if available
        if (!empty($this->context)) {
            $output[] = "Context Information:";
            foreach ($this->context as $key => $value) {
                $valueStr = is_string($value) ? $value : json_encode($value);
                $output[] = "  {$key}: {$valueStr}";
            }
            $output[] = "";
        }

        // Add suggestions if available
        if (!empty($this->suggestions)) {
            $output[] = "Suggestions to resolve this error:";
            foreach ($this->suggestions as $index => $suggestion) {
                $output[] = "  " . ($index + 1) . ". {$suggestion}";
            }
            $output[] = "";
        }

        // Add documentation links if available
        if (!empty($this->documentationLinks)) {
            $output[] = "Related Documentation:";
            foreach ($this->documentationLinks as $title => $url) {
                $output[] = "  {$title}: {$url}";
            }
            $output[] = "";
        }

        return implode("\n", $output);
    }

    /**
     * Generate a production-safe error message.
     * 
     * This method creates minimal error messages appropriate for production
     * environments where detailed error information might pose security risks
     * or create poor user experiences.
     *
     * @return string Safe production error message
     */
    public function toProductionString(): string
    {
        // In production, provide minimal information to avoid information disclosure
        return "An error occurred while processing your request. Please try again or contact support if the problem persists.";
    }

    /**
     * Initialize error-specific details based on the error code.
     * 
     * This method sets up suggestions and documentation links based on the
     * specific type of error, providing targeted help for different error
     * scenarios. Subclasses can override this method to provide specialized
     * error handling for their specific error types.
     */
    protected function initializeErrorDetails(): void
    {
        // Add general suggestions that apply to most FlashHALT errors
        $this->addSuggestion('Check that your controller and method names follow Laravel naming conventions');
        $this->addSuggestion('Verify that the controller exists and is in the expected namespace');
        $this->addSuggestion('Ensure your route pattern follows the format: controller@method or namespace.controller@method');

        // Add documentation links for general FlashHALT concepts
        $this->addDocumentationLink('FlashHALT Documentation', 'https://flashhalt.dev/docs');
        $this->addDocumentationLink('Laravel Controller Documentation', 'https://laravel.com/docs/controllers');

        // Subclasses can override this method to provide error-specific suggestions
        $this->initializeSpecificErrorDetails();
    }

    /**
     * Initialize error details specific to this exception type.
     * 
     * This method is designed to be overridden by subclasses to provide
     * specialized suggestions and documentation links for specific error types.
     * The base implementation is empty, allowing subclasses to add their
     * specific error handling without calling parent methods.
     */
    protected function initializeSpecificErrorDetails(): void
    {
        // Base implementation is empty - subclasses override this to provide
        // specific error details, suggestions, and documentation links
    }

    /**
     * Create a standardized error response for HTTP contexts.
     * 
     * This method demonstrates how exceptions can provide functionality that
     * goes beyond simple error reporting, creating structured responses that
     * work well with web applications and APIs.
     *
     * @param bool $isDevelopment Whether to include development details
     * @return array HTTP response data
     */
    public function toHttpResponse(bool $isDevelopment = false): array
    {
        $response = [
            'error' => true,
            'error_code' => $this->errorCode,
            'message' => $isDevelopment ? $this->getMessage() : $this->toProductionString(),
        ];

        if ($isDevelopment) {
            $response['context'] = $this->context;
            $response['suggestions'] = $this->suggestions;
            $response['documentation_links'] = $this->documentationLinks;
            $response['file'] = $this->getFile();
            $response['line'] = $this->getLine();
        }

        return $response;
    }

    /**
     * Determine if this exception should be reported to error tracking systems.
     * 
     * This method allows different types of exceptions to control whether they
     * should be reported to external error tracking systems, preventing spam
     * from expected errors while ensuring that genuine problems are tracked.
     *
     * @return bool True if this exception should be reported
     */
    public function shouldReport(): bool
    {
        // Most FlashHALT exceptions should be reported for monitoring and debugging
        // Subclasses can override this to prevent reporting of expected errors
        return true;
    }

    /**
     * Get the HTTP status code appropriate for this exception.
     * 
     * This method provides a standardized way for exceptions to specify
     * appropriate HTTP status codes, enabling consistent error responses
     * across the application.
     *
     * @return int HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        // Default to 500 Internal Server Error
        // Subclasses should override this to provide more specific status codes
        return 500;
    }
}