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
 * The testing strategy covers:
 * - Exception creation and context management
 * - Error code and message handling
 * - Development vs production error formatting
 * - HTTP status code mapping for different error types
 * - Exception chaining and context propagation
 * - Specialized exception behavior for different failure types
 * - Integration with Laravel's error handling system
 */
class ExceptionTest extends TestCase
{
    // ==================== BASE FLASHHALT EXCEPTION TESTS ====================

    /** @test */
    public function flashhalt_exception_stores_structured_error_codes()
    {
        $exception = new FlashHaltException(
            'Test error message',
            'TEST_ERROR_CODE',
            ['key' => 'value']
        );

        $this->assertEquals('TEST_ERROR_CODE', $exception->getErrorCode());
        $this->assertEquals(['key' => 'value'], $exception->getContext());
    }

    /** @test */
    public function flashhalt_exception_supports_method_chaining_for_context()
    {
        $exception = new FlashHaltException('Test message', 'TEST_CODE');
        
        $result = $exception
            ->addContext('first', 'value1')
            ->addContext('second', 'value2')
            ->addSuggestion('Try this approach');

        $this->assertSame($exception, $result);
        
        $context = $exception->getContext();
        // The context should contain exactly the items we added
        // (The base exception might add additional items)
        $this->assertArrayHasKey('first', $context);
        $this->assertArrayHasKey('second', $context);
        $this->assertEquals('value1', $context['first']);
        $this->assertEquals('value2', $context['second']);
    }

    /** @test */
    public function exceptions_handle_previous_exception_chaining()
    {
        $originalException = new \Exception('Original error');
        $flashhaltException = new FlashHaltException(
            'FlashHALT error',
            'CHAINED_ERROR',
            [],
            $originalException
        );

        $this->assertSame($originalException, $flashhaltException->getPrevious());
        $this->assertEquals('Original error', $flashhaltException->getPrevious()->getMessage());
    }

    /** @test */
    public function flashhalt_exception_includes_stack_trace_when_requested()
    {
        $exception = new FlashHaltException('Test message', 'TEST_CODE');
        
        $arrayWithTrace = $exception->toArray(true);
        $arrayWithoutTrace = $exception->toArray(false);
        
        $this->assertArrayHasKey('trace', $arrayWithTrace);
        $this->assertArrayNotHasKey('trace', $arrayWithoutTrace);
        $this->assertIsArray($arrayWithTrace['trace']);
    }

    /** @test */
    public function flashhalt_exception_generates_comprehensive_array_representation()
    {
        $exception = new FlashHaltException(
            'Test message',
            'TEST_CODE',
            ['context_key' => 'context_value']
        );
        
        $exception->addSuggestion('Test suggestion');
        
        $array = $exception->toArray();
        
        $this->assertEquals('Test message', $array['message']);
        $this->assertEquals('TEST_CODE', $array['error_code']);
        $this->assertEquals(['context_key' => 'context_value'], $array['context']);
        $this->assertContains('Test suggestion', $array['suggestions']);
        $this->assertArrayHasKey('timestamp', $array);
    }

    /** @test */
    public function flashhalt_exception_creates_development_friendly_string()
    {
        $exception = new FlashHaltException(
            'Test error message',
            'TEST_CODE',
            ['debug_info' => 'helpful information']
        );
        
        $exception->addSuggestion('Try this fix');
        
        $devString = $exception->toDevelopmentString();
        
        $this->assertStringContainsString('Test error message', $devString);
        $this->assertStringContainsString('TEST_CODE', $devString);
        $this->assertStringContainsString('debug_info: helpful information', $devString);
        $this->assertStringContainsString('Try this fix', $devString);
    }

    /** @test */
    public function flashhalt_exception_creates_production_safe_string()
    {
        $exception = new FlashHaltException(
            'Internal error with sensitive details',
            'INTERNAL_ERROR',
            ['sensitive_data' => 'should not be exposed']
        );
        
        $prodString = $exception->toProductionString();
        
        $this->assertStringNotContainsString('sensitive_data', $prodString);
        $this->assertStringNotContainsString('should not be exposed', $prodString);
        $this->assertStringContainsString('Please try again', $prodString);
    }

    /** @test */
    public function flashhalt_exception_creates_appropriate_http_responses()
    {
        $exception = new FlashHaltException('Test error', 'TEST_ERROR');
        
        $devResponse = $exception->toHttpResponse(true);
        $prodResponse = $exception->toHttpResponse(false);
        
        // Development response should include detailed information
        $this->assertTrue($devResponse['error']);
        $this->assertEquals('TEST_ERROR', $devResponse['error_code']);
        $this->assertEquals('Test error', $devResponse['message']);
        
        // Production response should be sanitized
        $this->assertTrue($prodResponse['error']);
        $this->assertEquals('TEST_ERROR', $prodResponse['error_code']);
        $this->assertStringNotContainsString('Test error', $prodResponse['message']);
    }

    /** @test */
    public function flashhalt_exception_generates_valid_json_representation()
    {
        $exception = new FlashHaltException('Test message', 'TEST_CODE');
        
        $json = $exception->toJson();
        $decoded = json_decode($json, true);
        
        $this->assertIsArray($decoded);
        $this->assertEquals('Test message', $decoded['message']);
        $this->assertEquals('TEST_CODE', $decoded['error_code']);
        $this->assertJson($json);
    }

    /** @test */
    public function exceptions_provide_consistent_timestamp_formatting()
    {
        $exception = new FlashHaltException('Test message', 'TEST_CODE');
        
        $array = $exception->toArray();
        $timestamp = $array['timestamp'];
        
        // Should be in ISO format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);
        
        // Should be parseable as a date
        $date = \DateTime::createFromFormat(\DateTime::ATOM, $timestamp);
        $this->assertInstanceOf(\DateTime::class, $date);
    }

    // ==================== CONTROLLER RESOLUTION EXCEPTION TESTS ====================

    /** @test */
    public function controller_resolution_exception_stores_resolution_context()
    {
        $exception = new ControllerResolutionException(
            'Controller not found',
            'CONTROLLER_NOT_FOUND',
            'admin.users@create',
            'class_validation',
            ['App\\Http\\Controllers\\Admin\\UsersController']
        );
        
        $this->assertEquals('admin.users@create', $exception->getRoutePattern());
        $this->assertEquals('class_validation', $exception->getResolutionStep());
        $this->assertContains('App\\Http\\Controllers\\Admin\\UsersController', $exception->getAttemptedClasses());
    }

    /** @test */
    public function controller_resolution_exception_supports_adding_attempted_classes()
    {
        $exception = new ControllerResolutionException(
            'Resolution failed',
            'RESOLUTION_FAILED',
            'users@index'
        );
        
        $result = $exception
            ->addAttemptedClass('App\\Http\\Controllers\\UsersController')
            ->addAttemptedClass('App\\Http\\Controllers\\UserController');
        
        $this->assertSame($exception, $result);
        $this->assertContains('App\\Http\\Controllers\\UsersController', $exception->getAttemptedClasses());
        $this->assertContains('App\\Http\\Controllers\\UserController', $exception->getAttemptedClasses());
    }

    /** @test */
    public function controller_resolution_exception_provides_appropriate_http_status_codes()
    {
        $notFoundException = new ControllerResolutionException(
            'Controller not found',
            'CONTROLLER_NOT_FOUND'
        );
        
        $invalidPatternException = new ControllerResolutionException(
            'Invalid pattern',
            'PATTERN_INVALID'
        );
        
        $instantiationException = new ControllerResolutionException(
            'Cannot instantiate',
            'CONTROLLER_INSTANTIATION_FAILED'
        );
        
        $this->assertEquals(404, $notFoundException->getHttpStatusCode());
        $this->assertEquals(400, $invalidPatternException->getHttpStatusCode());
        $this->assertEquals(500, $instantiationException->getHttpStatusCode());
    }

    /** @test */
    public function controller_resolution_exception_determines_reporting_appropriately()
    {
        $clientErrorException = new ControllerResolutionException(
            'Invalid pattern',
            'PATTERN_INVALID',
            'invalid-pattern'
        );
        
        $serverErrorException = new ControllerResolutionException(
            'Controller not found',
            'CONTROLLER_NOT_FOUND',
            'valid@pattern'
        );
        
        $this->assertFalse($clientErrorException->shouldReport());
        $this->assertTrue($serverErrorException->shouldReport());
    }

    /** @test */
    public function controller_resolution_exception_generates_comprehensive_resolution_report()
    {
        $exception = new ControllerResolutionException(
            'Resolution failed',
            'CONTROLLER_NOT_FOUND',
            'admin.users@create',
            'class_validation'
        );
        
        $report = $exception->toResolutionReport();
        
        $this->assertEquals('admin.users@create', $report['route_pattern']);
        $this->assertEquals('class_validation', $report['resolution_step']);
        $this->assertArrayHasKey('pattern_analysis', $report);
        $this->assertArrayHasKey('suggestions_summary', $report);
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
        
        $this->assertStringContainsString('safe_value: this is safe', $devString);
        $this->assertStringContainsString('[REDACTED FOR SECURITY]', $devString);
        $this->assertStringNotContainsString('secret123', $devString);
        $this->assertStringNotContainsString('sk_live_12345', $devString);
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
        $this->withoutMix(); // Disable Laravel Mix to avoid asset issues in tests
        
        $report = $exception->toSecurityReport();
        
        $this->assertEquals('BLACKLIST_CHECK', $report['security_rule']);
        $this->assertEquals('high', $report['severity']);
        $this->assertTrue($report['should_report']);
        $this->assertEquals(403, $report['http_status_code']);
    }

    // ==================== ROUTE COMPILER EXCEPTION TESTS ====================

    /** @test */
    public function route_compiler_exception_stores_compilation_context()
    {
        $exception = new RouteCompilerException(
            'Compilation failed',
            'TEMPLATE_DISCOVERY_FAILED',
            'template_discovery',
            'users@index',
            [
                'template_files' => ['/path/to/template.blade.php'],
                'compilation_stats' => ['files_processed' => 5]
            ]
        );
        
        $this->assertEquals('template_discovery', $exception->getCompilationStage());
        $this->assertEquals('users@index', $exception->getRoutePattern());
        $this->assertContains('/path/to/template.blade.php', $exception->getTemplateFiles());
        $this->assertEquals(['files_processed' => 5], $exception->getCompilationStats());
    }

    /** @test */
    public function route_compiler_exception_supports_adding_compilation_details()
    {
        $exception = new RouteCompilerException(
            'Compilation failed',
            'COMPILATION_FAILED',
            'route_validation'
        );
        
        $result = $exception
            ->addTemplateFile('/path/to/template1.blade.php')
            ->addDiscoveredRoute('users@index')
            ->addFailedRoute('admin@invalid', 'Controller not found');
        
        $this->assertSame($exception, $result);
        $this->assertContains('/path/to/template1.blade.php', $exception->getTemplateFiles());
        $this->assertNotEmpty($exception->getDiscoveredRoutes());
        $this->assertNotEmpty($exception->getFailedRoutes());
    }

    /** @test */
    public function route_compiler_exception_provides_compilation_specific_http_codes()
    {
        $missingTemplates = new RouteCompilerException(
            'Templates not found',
            'MISSING_TEMPLATE_DIRECTORIES'
        );
        
        $validationFailed = new RouteCompilerException(
            'Route validation failed',
            'ROUTE_VALIDATION_FAILED'
        );
        
        $writeFailed = new RouteCompilerException(
            'Cannot write routes',
            'ROUTES_FILE_WRITE_FAILED'
        );
        
        $this->assertEquals(404, $missingTemplates->getHttpStatusCode());
        $this->assertEquals(422, $validationFailed->getHttpStatusCode());
        $this->assertEquals(500, $writeFailed->getHttpStatusCode());
    }

    /** @test */
    public function route_compiler_exception_determines_reporting_based_on_environment()
    {
        $validationException = new RouteCompilerException(
            'Route validation failed',
            'ROUTE_VALIDATION_FAILED'
        );
        
        $fileSystemException = new RouteCompilerException(
            'Cannot write file',
            'ROUTES_FILE_WRITE_FAILED'
        );
        
        // Mock production environment
        $this->app['env'] = 'production';
        $this->assertTrue($validationException->shouldReport());
        $this->assertTrue($fileSystemException->shouldReport());
        
        // Mock development environment
        $this->app['env'] = 'development';
        $this->assertFalse($validationException->shouldReport());
        $this->assertTrue($fileSystemException->shouldReport());
    }

    /** @test */
    public function route_compiler_exception_generates_comprehensive_compilation_reports()
    {
        $exception = new RouteCompilerException(
            'Compilation failed with multiple issues',
            'COMPILATION_FAILED',
            'route_validation',
            'users@index',
            [
                'template_files' => ['/path/to/template1.blade.php', '/path/to/template2.blade.php'],
                'discovered_routes' => [
                    ['pattern' => 'users@index', 'context' => []],
                    ['pattern' => 'admin@dashboard', 'context' => []]
                ],
                'failed_routes' => [
                    ['pattern' => 'invalid@route', 'error' => 'Controller not found']
                ],
                'compilation_stats' => [
                    'files_processed' => 2,
                    'routes_discovered' => 2,
                    'routes_failed' => 1
                ]
            ]
        );
        
        $report = $exception->toCompilationReport();
        
        $this->assertEquals('route_validation', $report['compilation_stage']);
        $this->assertEquals('users@index', $report['route_pattern']);
        $this->assertCount(2, $report['template_files']);
        $this->assertCount(2, $report['discovered_routes']);
        $this->assertCount(1, $report['failed_routes']);
        $this->assertArrayHasKey('compilation_summary', $report);
        $this->assertArrayHasKey('failure_analysis', $report);
        
        // Check compilation summary
        $summary = $report['compilation_summary'];
        $this->assertEquals('route_validation', $summary['stage_reached']);
        $this->assertEquals(2, $summary['templates_processed']);
        $this->assertEquals(2, $summary['routes_discovered']);
        $this->assertEquals(1, $summary['routes_failed']);
        $this->assertEquals(50.0, $summary['success_rate']); // 1 failed out of 2 total = 50% success
    }
}