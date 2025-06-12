<?php

namespace DancyCodes\FlashHalt\Tests\Unit;

use DancyCodes\FlashHalt\Exceptions\FlashHaltException;
use DancyCodes\FlashHalt\Exceptions\ControllerResolutionException;
use DancyCodes\FlashHalt\Exceptions\SecurityValidationException;
use DancyCodes\FlashHalt\Exceptions\RouteCompilerException;
use DancyCodes\FlashHalt\Tests\TestCase;

/**
 * Exception Tests
 * 
 * These tests verify FlashHALT's sophisticated exception handling system.
 * The package includes a rich hierarchy of exceptions that provide detailed
 * context, suggestions, and educational information to help developers
 * understand and resolve issues quickly.
 * 
 * Testing strategy covers:
 * - Exception hierarchy and inheritance
 * - Rich context information and error codes
 * - Educational suggestions and documentation links
 * - Environment-aware error messaging
 * - Integration with monitoring and logging systems
 * - HTTP status code mapping
 * - Serialization for API responses
 */
class ExceptionTest extends TestCase
{
    // ==================== BASE EXCEPTION TESTS ====================

    /** @test */
    public function flashhalt_exception_stores_structured_error_codes()
    {
        $exception = new FlashHaltException(
            'Test error message',
            'TEST_ERROR_CODE',
            ['context_key' => 'context_value']
        );
        
        $this->assertEquals('TEST_ERROR_CODE', $exception->getErrorCode());
        $this->assertEquals('Test error message', $exception->getMessage());
        $this->assertEquals(['context_key' => 'context_value'], $exception->getContext());
    }

    /** @test */
    public function flashhalt_exception_supports_method_chaining_for_context()
    {
        $exception = new FlashHaltException('Test message')
            ->addContext('key1', 'value1')
            ->addContext('key2', 'value2')
            ->addSuggestion('First suggestion')
            ->addSuggestion('Second suggestion')
            ->addDocumentationLink('Guide', 'https://example.com/guide');
        
        $context = $exception->getContext();
        $this->assertEquals('value1', $context['key1']);
        $this->assertEquals('value2', $context['key2']);
        
        $suggestions = $exception->getSuggestions();
        $this->assertCount(3, $suggestions); // 2 + 1 from base initialization
        $this->assertStringContains('First suggestion', implode(' ', $suggestions));
        $this->assertStringContains('Second suggestion', implode(' ', $suggestions));
        
        $links = $exception->getDocumentationLinks();
        $this->assertEquals('https://example.com/guide', $links['Guide']);
    }

    /** @test */
    public function flashhalt_exception_generates_comprehensive_array_representation()
    {
        $exception = new FlashHaltException(
            'Test error',
            'TEST_CODE',
            ['test_context' => 'test_value']
        );
        
        $array = $exception->toArray();
        
        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('suggestions', $array);
        $this->assertArrayHasKey('documentation_links', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('timestamp', $array);
        
        $this->assertEquals('TEST_CODE', $array['error_code']);
        $this->assertEquals('Test error', $array['message']);
        $this->assertEquals(['test_context' => 'test_value'], $array['context']);
    }

    /** @test */
    public function flashhalt_exception_includes_stack_trace_when_requested()
    {
        $exception = new FlashHaltException('Test with trace');
        
        $arrayWithTrace = $exception->toArray(true);
        $arrayWithoutTrace = $exception->toArray(false);
        
        $this->assertArrayHasKey('stack_trace', $arrayWithTrace);
        $this->assertArrayNotHasKey('stack_trace', $arrayWithoutTrace);
        $this->assertIsString($arrayWithTrace['stack_trace']);
    }

    /** @test */
    public function flashhalt_exception_generates_valid_json_representation()
    {
        $exception = new FlashHaltException('JSON test', 'JSON_CODE');
        
        $json = $exception->toJson();
        $decoded = json_decode($json, true);
        
        $this->assertIsArray($decoded);
        $this->assertEquals('JSON_CODE', $decoded['error_code']);
        $this->assertEquals('JSON test', $decoded['message']);
    }

    /** @test */
    public function flashhalt_exception_creates_development_friendly_string()
    {
        $exception = new FlashHaltException(
            'Development error test',
            'DEV_ERROR',
            ['debug_info' => 'useful for developers']
        );
        
        $devString = $exception->toDevelopmentString();
        
        $this->assertStringContains('FlashHALT Error: Development error test', $devString);
        $this->assertStringContains('Error Code: DEV_ERROR', $devString);
        $this->assertStringContains('Context Information:', $devString);
        $this->assertStringContains('debug_info: useful for developers', $devString);
        $this->assertStringContains('Suggestions to resolve this error:', $devString);
    }

    /** @test */
    public function flashhalt_exception_creates_production_safe_string()
    {
        $exception = new FlashHaltException(
            'Sensitive error information that should not be shown',
            'SENSITIVE_ERROR'
        );
        
        $prodString = $exception->toProductionString();
        
        $this->assertStringNotContains('Sensitive error information', $prodString);
        $this->assertStringContains('An error occurred while processing', $prodString);
    }

    /** @test */
    public function flashhalt_exception_creates_appropriate_http_responses()
    {
        $exception = new FlashHaltException('HTTP test error', 'HTTP_ERROR');
        
        $devResponse = $exception->toHttpResponse(true);
        $prodResponse = $exception->toHttpResponse(false);
        
        // Development response should include details
        $this->assertArrayHasKey('error', $devResponse);
        $this->assertArrayHasKey('error_code', $devResponse);
        $this->assertArrayHasKey('context', $devResponse);
        $this->assertArrayHasKey('suggestions', $devResponse);
        $this->assertTrue($devResponse['error']);
        $this->assertEquals('HTTP_ERROR', $devResponse['error_code']);
        
        // Production response should be minimal
        $this->assertArrayHasKey('error', $prodResponse);
        $this->assertArrayHasKey('message', $prodResponse);
        $this->assertArrayNotHasKey('context', $prodResponse);
        $this->assertArrayNotHasKey('suggestions', $prodResponse);
    }

    // ==================== CONTROLLER RESOLUTION EXCEPTION TESTS ====================

    /** @test */
    public function controller_resolution_exception_stores_resolution_context()
    {
        $exception = new ControllerResolutionException(
            'Controller not found',
            'CONTROLLER_NOT_FOUND',
            'users@index',
            'namespace_resolution',
            ['App\\Http\\Controllers\\UsersController', 'App\\Controllers\\UsersController']
        );
        
        $this->assertEquals('users@index', $exception->getRoutePattern());
        $this->assertEquals('namespace_resolution', $exception->getResolutionStep());
        $this->assertCount(2, $exception->getAttemptedClasses());
        $this->assertContains('App\\Http\\Controllers\\UsersController', $exception->getAttemptedClasses());
    }

    /** @test */
    public function controller_resolution_exception_supports_adding_attempted_classes()
    {
        $exception = new ControllerResolutionException(
            'Resolution failed',
            'RESOLUTION_FAILED',
            'test@method'
        );
        
        $exception->addAttemptedClass('FirstClass')
                  ->addAttemptedClass('SecondClass')
                  ->addAttemptedNamespace('App\\Controllers');
        
        $attemptedClasses = $exception->getAttemptedClasses();
        $attemptedNamespaces = $exception->getAttemptedNamespaces();
        
        $this->assertCount(2, $attemptedClasses);
        $this->assertContains('FirstClass', $attemptedClasses);
        $this->assertContains('SecondClass', $attemptedClasses);
        $this->assertContains('App\\Controllers', $attemptedNamespaces);
    }

    /** @test */
    public function controller_resolution_exception_provides_appropriate_http_status_codes()
    {
        $notFoundException = new ControllerResolutionException(
            'Controller not found',
            'CONTROLLER_NOT_FOUND'
        );
        
        $badRequestException = new ControllerResolutionException(
            'Invalid pattern',
            'INVALID_PATTERN_FORMAT'
        );
        
        $serverErrorException = new ControllerResolutionException(
            'Reflection failed',
            'CONTROLLER_REFLECTION_FAILED'
        );
        
        $this->assertEquals(404, $notFoundException->getHttpStatusCode());
        $this->assertEquals(400, $badRequestException->getHttpStatusCode());
        $this->assertEquals(500, $serverErrorException->getHttpStatusCode());
    }

    /** @test */
    public function controller_resolution_exception_determines_reporting_appropriately()
    {
        // Development errors shouldn't be reported in development environment
        $this->app['env'] = 'development';
        
        $devException = new ControllerResolutionException(
            'Controller not found',
            'CONTROLLER_NOT_FOUND'
        );
        
        $this->assertFalse($devException->shouldReport());
        
        // Server errors should always be reported
        $serverException = new ControllerResolutionException(
            'Reflection failed',
            'CONTROLLER_REFLECTION_FAILED'
        );
        
        $this->assertTrue($serverException->shouldReport());
    }

    /** @test */
    public function controller_resolution_exception_generates_comprehensive_resolution_report()
    {
        $exception = new ControllerResolutionException(
            'Resolution failed',
            'RESOLUTION_FAILED',
            'admin.users@create',
            'class_validation',
            ['App\\Http\\Controllers\\Admin\\UsersController']
        );
        
        $report = $exception->toResolutionReport();
        
        $this->assertArrayHasKey('route_pattern', $report);
        $this->assertArrayHasKey('resolution_step', $report);
        $this->assertArrayHasKey('attempted_classes', $report);
        $this->assertArrayHasKey('pattern_analysis', $report);
        $this->assertArrayHasKey('suggestions_summary', $report);
        
        $this->assertEquals('admin.users@create', $report['route_pattern']);
        $this->assertEquals('class_validation', $report['resolution_step']);
    }

    /** @test */
    public function controller_resolution_exception_analyzes_route_patterns()
    {
        $exception = new ControllerResolutionException(
            'Pattern analysis test',
            'TEST_CODE',
            'admin.users@create'
        );
        
        $report = $exception->toResolutionReport();
        $analysis = $report['pattern_analysis'];
        
        $this->assertEquals('admin.users@create', $analysis['pattern']);
        $this->assertTrue($analysis['contains_at']);
        $this->assertEquals(1, $analysis['at_count']);
        $this->assertTrue($analysis['contains_dots']);
        $this->assertEquals('admin.users', $analysis['controller_part']);
        $this->assertEquals('create', $analysis['method_part']);
        $this->assertEquals(['admin'], $analysis['namespace_segments']);
        $this->assertEquals('users', $analysis['controller_name']);
    }

    // ==================== SECURITY VALIDATION EXCEPTION TESTS ====================

    /** @test */
    public function security_validation_exception_stores_security_context()
    {
        $exception = new SecurityValidationException(
            'Method is blacklisted',
            'METHOD_BLACKLISTED',
            'METHOD_BLACKLIST_CHECK',
            'high',
            ['method_name' => '__construct'],
            ['violation_type' => 'blacklist_match']
        );
        
        $this->assertEquals('METHOD_BLACKLIST_CHECK', $exception->getSecurityRule());
        $this->assertEquals('high', $exception->getSeverity());
        $this->assertEquals(['violation_type' => 'blacklist_match'], $exception->getSecurityContext());
    }

    /** @test */
    public function security_validation_exception_validates_severity_levels()
    {
        $validException = new SecurityValidationException(
            'Valid severity',
            'TEST_CODE',
            'TEST_RULE',
            'critical'
        );
        
        $invalidException = new SecurityValidationException(
            'Invalid severity',
            'TEST_CODE',
            'TEST_RULE',
            'invalid_level'
        );
        
        $this->assertEquals('critical', $validException->getSeverity());
        $this->assertEquals('medium', $invalidException->getSeverity()); // Should default to medium
    }

    /** @test */
    public function security_validation_exception_determines_reporting_based_on_severity()
    {
        $lowSeverity = new SecurityValidationException(
            'Low severity issue',
            'LOW_ISSUE',
            'TEST_RULE',
            'low'
        );
        
        $criticalSeverity = new SecurityValidationException(
            'Critical security issue',
            'CRITICAL_ISSUE',
            'TEST_RULE',
            'critical'
        );
        
        // In development, low severity shouldn't be reported
        $this->app['env'] = 'development';
        $this->assertFalse($lowSeverity->shouldReport());
        
        // Critical severity should always be reported
        $this->assertTrue($criticalSeverity->shouldReport());
    }

    /** @test */
    public function security_validation_exception_provides_security_specific_http_codes()
    {
        $blacklistedMethod = new SecurityValidationException(
            'Blacklisted method',
            'METHOD_BLACKLISTED'
        );
        
        $inappropriateMethod = new SecurityValidationException(
            'Wrong HTTP method',
            'INAPPROPRIATE_HTTP_METHOD'
        );
        
        $invalidMethodName = new SecurityValidationException(
            'Invalid method name',
            'INVALID_METHOD_NAME'
        );
        
        $this->assertEquals(403, $blacklistedMethod->getHttpStatusCode());
        $this->assertEquals(405, $inappropriateMethod->getHttpStatusCode());
        $this->assertEquals(400, $invalidMethodName->getHttpStatusCode());
    }

    /** @test */
    public function security_validation_exception_sanitizes_sensitive_context()
    {
        $exception = new SecurityValidationException(
            'Security test',
            'TEST_SECURITY',
            'TEST_RULE',
            'medium',
            [
                'safe_value' => 'this is safe',
                'password' => 'secret123',
                'api_key' => 'sk_live_12345'
            ]
        );
        
        $devString = $exception->toDevelopmentString();
        
        $this->assertStringContains('safe_value: this is safe', $devString);
        $this->assertStringContains('[REDACTED FOR SECURITY]', $devString);
        $this->assertStringNotContains('secret123', $devString);
        $this->assertStringNotContains('sk_live_12345', $devString);
    }

    /** @test */
    public function security_validation_exception_creates_security_reports()
    {
        $exception = new SecurityValidationException(
            'Security violation',
            'METHOD_BLACKLISTED',
            'BLACKLIST_CHECK',
            'high'
        );
        
        // Mock a request for context
        $request = $this->app['request'];
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Test Browser');
        
        $report = $exception->toSecurityReport();
        
        $this->assertArrayHasKey('security_rule', $report);
        $this->assertArrayHasKey('severity', $report);
        $this->assertArrayHasKey('should_report', $report);
        $this->assertArrayHasKey('http_status_code', $report);
        $this->assertArrayHasKey('request_info', $report);
        
        $this->assertEquals('BLACKLIST_CHECK', $report['security_rule']);
        $this->assertEquals('high', $report['severity']);
    }

    // ==================== ROUTE COMPILER EXCEPTION TESTS ====================

    /** @test */
    public function route_compiler_exception_stores_compilation_context()
    {
        $exception = new RouteCompilerException(
            'Compilation failed',
            'COMPILATION_FAILED',
            'users@index',
            'route_validation',
            ['template1.blade.php', 'template2.blade.php'],
            [
                'discovered_routes' => [
                    ['pattern' => 'users@index', 'source' => 'template1.blade.php']
                ],
                'failed_routes' => [
                    ['pattern' => 'users@invalid', 'error' => 'Controller not found']
                ],
                'compilation_progress' => [
                    'templates_scanned' => 2,
                    'routes_discovered' => 1
                ]
            ]
        );
        
        $this->assertEquals('route_validation', $exception->getCompilationStage());
        $this->assertEquals('users@index', $exception->getRoutePattern());
        $this->assertCount(2, $exception->getTemplateFiles());
        $this->assertCount(1, $exception->getDiscoveredRoutes());
        $this->assertCount(1, $exception->getFailedRoutes());
    }

    /** @test */
    public function route_compiler_exception_supports_adding_compilation_details()
    {
        $exception = new RouteCompilerException(
            'Compilation error',
            'COMPILATION_ERROR'
        );
        
        $exception->addFailedRoute('test@method', 'Controller not found', ['source' => 'test.blade.php'])
                  ->addDetailedError('template_error', 'Template parsing failed', ['line' => 15])
                  ->updateCompilationProgress(['templates_scanned' => 5]);
        
        $failedRoutes = $exception->getFailedRoutes();
        $detailedErrors = $exception->getDetailedErrors();
        $progress = $exception->getCompilationProgress();
        
        $this->assertCount(1, $failedRoutes);
        $this->assertEquals('test@method', $failedRoutes[0]['pattern']);
        $this->assertEquals('Controller not found', $failedRoutes[0]['error']);
        
        $this->assertCount(1, $detailedErrors);
        $this->assertEquals('template_error', $detailedErrors[0]['type']);
        
        $this->assertEquals(5, $progress['templates_scanned']);
    }

    /** @test */
    public function route_compiler_exception_provides_compilation_specific_http_codes()
    {
        $configError = new RouteCompilerException(
            'Missing template directories',
            'MISSING_TEMPLATE_DIRECTORIES'
        );
        
        $validationError = new RouteCompilerException(
            'Route validation failed',
            'ROUTE_VALIDATION_FAILED'
        );
        
        $fileError = new RouteCompilerException(
            'Failed to write routes',
            'ROUTES_FILE_WRITE_FAILED'
        );
        
        $this->assertEquals(500, $configError->getHttpStatusCode());
        $this->assertEquals(422, $validationError->getHttpStatusCode());
        $this->assertEquals(500, $fileError->getHttpStatusCode());
    }

    /** @test */
    public function route_compiler_exception_determines_reporting_based_on_environment()
    {
        $validationError = new RouteCompilerException(
            'Route validation failed',
            'ROUTE_VALIDATION_FAILED'
        );
        
        $criticalError = new RouteCompilerException(
            'Output directory not writable',
            'OUTPUT_DIRECTORY_NOT_WRITABLE'
        );
        
        // In development, validation errors might not be reported
        $this->app['env'] = 'development';
        $this->assertFalse($validationError->shouldReport());
        
        // Critical errors should always be reported
        $this->assertTrue($criticalError->shouldReport());
        
        // In production, most compilation errors should be reported
        $this->app['env'] = 'production';
        $this->assertTrue($validationError->shouldReport());
    }

    /** @test */
    public function route_compiler_exception_generates_comprehensive_compilation_reports()
    {
        $exception = new RouteCompilerException(
            'Complex compilation failure',
            'COMPILATION_FAILED',
            'admin.users@create',
            'route_validation',
            ['admin/users/index.blade.php', 'admin/users/create.blade.php'],
            [
                'discovered_routes' => [
                    ['pattern' => 'admin.users@index'],
                    ['pattern' => 'admin.users@create']
                ],
                'failed_routes' => [
                    ['pattern' => 'admin.users@invalid', 'error' => 'Method not found']
                ],
                'compilation_progress' => [
                    'templates_scanned' => 2,
                    'routes_discovered' => 3,
                    'compilation_time' => 150.5
                ]
            ]
        );
        
        $report = $exception->toCompilationReport();
        
        $this->assertArrayHasKey('compilation_stage', $report);
        $this->assertArrayHasKey('route_pattern', $report);
        $this->assertArrayHasKey('template_files', $report);
        $this->assertArrayHasKey('discovered_routes', $report);
        $this->assertArrayHasKey('failed_routes', $report);
        $this->assertArrayHasKey('compilation_progress', $report);
        $this->assertArrayHasKey('failure_analysis', $report);
        $this->assertArrayHasKey('recommendations', $report);
        
        $analysis = $report['failure_analysis'];
        $this->assertEquals('route_validation', $analysis['failure_stage']);
        $this->assertEquals(2, $analysis['total_templates']);
        $this->assertEquals(2, $analysis['total_discovered_routes']);
        $this->assertEquals(1, $analysis['total_failed_routes']);
    }

    /** @test */
    public function exceptions_handle_previous_exception_chaining()
    {
        $originalException = new \RuntimeException('Original error');
        
        $flashhaltException = new FlashHaltException(
            'FlashHALT error with previous',
            'CHAINED_ERROR',
            [],
            $originalException
        );
        
        $this->assertSame($originalException, $flashhaltException->getPrevious());
        
        $array = $flashhaltException->toArray();
        $this->assertArrayHasKey('previous_exception', $array);
        $this->assertEquals('RuntimeException', $array['previous_exception']['class']);
        $this->assertEquals('Original error', $array['previous_exception']['message']);
    }

    /** @test */
    public function exceptions_provide_consistent_timestamp_formatting()
    {
        $exception = new FlashHaltException('Timestamp test');
        
        $array = $exception->toArray();
        
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['timestamp']);
    }
}