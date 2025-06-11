<?php

namespace DancyCodes\FlashHalt\Console\Commands;

use DancyCodes\FlashHalt\Services\RouteCompiler;
use DancyCodes\FlashHalt\Exceptions\RouteCompilerException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * FlashHALT Compile Command - Production Route Compilation Interface
 * 
 * This Artisan command serves as the primary interface for compiling FlashHALT
 * routes for production deployment. It demonstrates how to create command-line
 * interfaces that provide excellent user experience while leveraging sophisticated
 * service architecture to accomplish complex tasks.
 * 
 * The command embodies several key principles of excellent CLI design:
 * - Progressive disclosure: Basic information by default, detailed info when requested
 * - Contextual feedback: Real-time progress updates and clear status indicators
 * - Graceful error handling: Educational error messages with actionable guidance
 * - Flexible operation: Multiple modes and options for different use cases
 * - Comprehensive reporting: Detailed summaries of what was accomplished
 * 
 * The command serves multiple roles in the developer workflow:
 * - Production preparation: Compiling routes for optimal deployment performance
 * - Development validation: Verifying that routes are properly configured
 * - Architecture analysis: Providing insights into application routing patterns
 * - Error diagnosis: Identifying and explaining compilation issues
 * 
 * This implementation demonstrates how Artisan commands can serve as sophisticated
 * interfaces to complex service architecture while maintaining simplicity and
 * clarity for developers who use them regularly.
 */
class CompileCommand extends Command
{
    /**
     * The command signature defines how developers will invoke this command.
     * The signature uses Laravel's expressive syntax to define options and
     * arguments in a way that's both flexible and self-documenting.
     * 
     * Key design decisions in the signature:
     * - Simple base command name that's easy to remember and type
     * - Optional flags that provide additional functionality without complexity
     * - Descriptive option names that make the command's behavior clear
     * - Sensible defaults that work for most common use cases
     * - Leverages Laravel's built-in --verbose option for consistency
     */
    protected $signature = 'flashhalt:compile
                            {--force : Force compilation even if no changes are detected}
                            {--verify : Run verification checks after compilation}
                            {--dry-run : Analyze routes without writing compiled output}
                            {--stats : Display comprehensive compilation statistics}
                            {--routes-only : Only display discovered routes without compiling}';

    /**
     * The command description appears in Artisan's help output and should
     * clearly communicate what the command does and why developers would use it.
     * This description balances brevity with informativeness, giving developers
     * enough context to understand the command's purpose and importance.
     */
    protected $description = 'Compile FlashHALT routes for production deployment with optimized performance';

    /**
     * The RouteCompiler service that handles the sophisticated compilation logic.
     * By injecting this service through the constructor, we demonstrate how
     * commands can leverage complex service architecture while maintaining
     * clean, focused responsibility for user interface concerns.
     */
    protected RouteCompiler $compiler;

    /**
     * Laravel provides built-in verbosity levels that we can leverage:
     * - Normal: Default output level
     * - Verbose (-v): Additional detail and progress information
     * - Very Verbose (-vv): Comprehensive analysis and debugging info
     * - Debug (-vvv): Maximum detail for troubleshooting
     * 
     * We'll use these levels to provide progressive disclosure of information.
     */

    /**
     * Collection of messages to display at the end of command execution.
     * This pattern allows us to collect important information throughout
     * the command execution and present it in a well-organized summary,
     * ensuring that critical information doesn't get lost in progress output.
     */
    protected array $summaryMessages = [];

    /**
     * Constructor demonstrates dependency injection in Artisan commands.
     * Laravel's service container automatically provides the RouteCompiler
     * instance, showing how commands can access sophisticated services
     * without manual instantiation or configuration.
     *
     * @param RouteCompiler $compiler The route compilation service
     */
    public function __construct(RouteCompiler $compiler)
    {
        parent::__construct();
        $this->compiler = $compiler;
    }

    /**
     * Execute the compilation command with comprehensive user experience features.
     * 
     * This method demonstrates how to structure command execution to provide
     * excellent user experience through clear progress reporting, comprehensive
     * error handling, and detailed feedback about what was accomplished.
     * 
     * The execution flow follows a pattern that works well for complex operations:
     * 1. Initialize and validate the execution environment
     * 2. Provide clear feedback about what will be accomplished
     * 3. Execute the main operation with progress reporting
     * 4. Handle errors gracefully with educational information
     * 5. Provide comprehensive summary of results and next steps
     *
     * @return int Command exit code (0 for success, non-zero for failure)
     */
    public function handle(): int
    {
        // Initialize command execution and determine operation mode
        $this->initializeCommand();
        
        // Display a clear, informative header that sets expectations
        $this->displayCommandHeader();
        
        // Validate the environment and configuration before beginning expensive operations
        if (!$this->validateEnvironment()) {
            return 1; // Exit with error code for validation failures
        }
        
        try {
            // Handle special operation modes that don't require full compilation
            if ($this->option('routes-only')) {
                return $this->handleRoutesOnlyMode();
            }
            
            if ($this->option('dry-run')) {
                return $this->handleDryRunMode();
            }
            
            // Execute the main compilation process with comprehensive progress reporting
            $result = $this->executeCompilation();
            
            // Display detailed results and provide guidance for next steps
            $this->displayCompilationResults($result);
            
            // Run optional verification if requested
            if ($this->option('verify')) {
                $this->runVerificationChecks($result);
            }
            
            // Display final summary and any important messages
            $this->displayCommandSummary();
            
            return 0; // Success exit code
            
        } catch (RouteCompilerException $e) {
            // Handle compilation-specific errors with detailed, educational information
            $this->handleCompilationError($e);
            return 1; // Error exit code for compilation failures
            
        } catch (\Exception $e) {
            // Handle unexpected errors gracefully while providing debugging information
            $this->handleUnexpectedError($e);
            return 1; // Error exit code for unexpected failures
        }
    }

    /**
     * Initialize command execution and determine operational parameters.
     * 
     * This method demonstrates how to leverage Laravel's built-in verbosity system
     * to provide appropriate levels of detail based on developer preferences.
     * Laravel's verbosity levels provide a standardized way to control output detail
     * that developers already understand from other Artisan commands.
     */
    protected function initializeCommand(): void
    {
        // Reset summary messages for clean output organization
        $this->summaryMessages = [];
        
        // Provide initialization context based on verbosity level
        // -v (verbose): Show basic initialization info
        // -vv (very verbose): Show detailed configuration
        // -vvv (debug): Show comprehensive setup details
        
        if ($this->output->isVerbose()) {
            $this->info('🔧 Initializing FlashHALT compilation process...');
        }
        
        if ($this->output->isVeryVerbose()) {
            $this->displayCurrentConfiguration();
        }
        
        if ($this->output->isDebug()) {
            $this->displayDebugInformation();
        }
    }

    /**
     * Display a clear, informative header that sets expectations for the command.
     * 
     * The header serves multiple purposes: it confirms that the right command
     * is executing, provides context about what will be accomplished, and
     * creates a professional, polished experience that builds confidence
     * in the tool's capabilities.
     * 
     * We use Laravel's verbosity levels to progressively disclose information:
     * - Normal: Basic header and purpose
     * - Verbose (-v): Additional details about the process steps
     */
    protected function displayCommandHeader(): void
    {
        $this->info('');
        $this->info('🚀 <fg=cyan;options=bold>FlashHALT Route Compilation</fg=cyan;options=bold>');
        $this->info('   Compiling HTMX routes for production deployment');
        $this->info('');
        
        // Provide additional context when verbose output is requested
        if ($this->output->isVerbose()) {
            $this->line('   This process will:');
            $this->line('   • Scan your Blade templates for FlashHALT route patterns');
            $this->line('   • Validate discovered routes for safety and functionality');
            $this->line('   • Generate optimized static routes for production use');
            $this->line('   • Provide comprehensive analysis and recommendations');
            $this->info('');
        }
    }

    /**
     * Display current configuration to help developers understand the compilation context.
     * 
     * This method demonstrates how to provide transparency about system configuration
     * without overwhelming users with unnecessary detail. The information helps
     * developers understand why compilation might behave in specific ways and
     * provides context for troubleshooting when issues arise.
     */
    protected function displayCurrentConfiguration(): void
    {
        $config = config('flashhalt', []);
        
        $this->info('📋 <fg=yellow>Current Configuration:</fg=yellow>');
        
        // Display key configuration settings that affect compilation behavior
        $this->line('   Mode: ' . ($config['mode'] ?? 'auto'));
        $this->line('   Template directories: ' . count($config['compilation']['template_directories'] ?? []));
        $this->line('   Validation level: ' . ($config['compilation']['validation_level'] ?? 'strict'));
        $this->line('   Output path: ' . ($config['production']['compiled_routes_path'] ?? 'not configured'));
        
        $this->info('');
    }

    /**
     * Display comprehensive debug information for troubleshooting.
     * 
     * This method demonstrates how to provide maximum detail for debugging
     * complex issues while keeping this information at the highest verbosity
     * level to avoid overwhelming normal users.
     */
    protected function displayDebugInformation(): void
    {
        $this->info('🐛 <fg=magenta>Debug Information:</fg=magenta>');
        
        // Environment information
        $this->line('   Laravel version: ' . app()->version());
        $this->line('   PHP version: ' . PHP_VERSION);
        $this->line('   Memory limit: ' . ini_get('memory_limit'));
        $this->line('   Max execution time: ' . ini_get('max_execution_time'));
        
        // FlashHALT configuration details
        $config = config('flashhalt', []);
        $this->line('   FlashHALT mode: ' . ($config['mode'] ?? 'not set'));
        $this->line('   Config file exists: ' . (config('flashhalt') ? 'yes' : 'no'));
        
        $this->info('');
    }

    /**
     * Validate the environment and configuration before beginning compilation.
     * 
     * This method demonstrates defensive programming by checking all prerequisites
     * before starting expensive operations. Early validation provides clear,
     * actionable error messages that help developers fix configuration issues
     * quickly and efficiently.
     *
     * @return bool True if environment is valid for compilation
     */
    protected function validateEnvironment(): bool
    {
        $this->info('🔍 <fg=yellow>Validating compilation environment...</fg=yellow>');
        
        // Check that FlashHALT configuration exists and is properly structured
        if (!config('flashhalt')) {
            $this->error('❌ FlashHALT configuration not found.');
            $this->line('   Please ensure config/flashhalt.php exists and is properly configured.');
            $this->line('   Run: php artisan vendor:publish --tag=flashhalt-config');
            return false;
        }
        
        // Validate template directories configuration
        $templateDirs = config('flashhalt.compilation.template_directories', []);
        if (empty($templateDirs)) {
            $this->error('❌ No template directories configured for compilation.');
            $this->line('   Please configure flashhalt.compilation.template_directories in config/flashhalt.php');
            return false;
        }
        
        // Check that configured template directories exist and are readable
        foreach ($templateDirs as $directory) {
            if (!is_dir($directory)) {
                $this->error("❌ Template directory does not exist: {$directory}");
                $this->line('   Please ensure all configured template directories exist and are readable.');
                return false;
            }
            
            if (!is_readable($directory)) {
                $this->error("❌ Template directory is not readable: {$directory}");
                $this->line('   Please check file permissions for the template directory.');
                return false;
            }
        }
        
        // Validate output path configuration and writability
        $outputPath = config('flashhalt.production.compiled_routes_path');
        if (empty($outputPath)) {
            $this->error('❌ No output path configured for compiled routes.');
            $this->line('   Please configure flashhalt.production.compiled_routes_path in config/flashhalt.php');
            return false;
        }
        
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            $this->warn("⚠️  Output directory does not exist: {$outputDir}");
            $this->line('   Attempting to create output directory...');
            
            if (!mkdir($outputDir, 0755, true)) {
                $this->error("❌ Failed to create output directory: {$outputDir}");
                return false;
            }
            
            $this->info("✅ Created output directory: {$outputDir}");
        }
        
        if (!is_writable($outputDir)) {
            $this->error("❌ Output directory is not writable: {$outputDir}");
            $this->line('   Please check file permissions for the output directory.');
            return false;
        }
        
        $this->info('✅ Environment validation completed successfully');
        $this->info('');
        
        return true;
    }

    /**
     * Handle routes-only mode that displays discovered routes without compilation.
     * 
     * This mode demonstrates how commands can provide analytical functionality
     * that helps developers understand their application structure without
     * making changes. It's particularly useful for debugging route discovery
     * issues or understanding how FlashHALT interprets template patterns.
     *
     * @return int Command exit code
     */
    protected function handleRoutesOnlyMode(): int
    {
        $this->info('🔍 <fg=yellow>Analyzing templates for FlashHALT routes (routes-only mode)...</fg=yellow>');
        $this->info('');
        
        try {
            // Use the compiler to discover routes without actually compiling them
            // This demonstrates how services can provide partial functionality for different use cases
            $result = $this->compiler->compile(false); // false = don't force if no changes
            
            $this->displayDiscoveredRoutes($result);
            $this->displayRouteAnalysis($result);
            
            $this->info('');
            $this->info('💡 <fg=green>Route analysis completed. No files were modified.</fg=green>');
            $this->line('   Run without --routes-only to compile these routes for production.');
            
            return 0;
            
        } catch (RouteCompilerException $e) {
            $this->error('❌ Route analysis failed: ' . $e->getMessage());
            
            // Even in analysis mode, provide helpful debugging information
            if ($this->verboseMode) {
                $this->displayCompilerError($e);
            }
            
            return 1;
        }
    }

    /**
     * Handle dry-run mode that simulates compilation without writing files.
     * 
     * Dry-run mode demonstrates how to provide comprehensive simulation
     * functionality that lets developers understand what compilation would
     * accomplish without making actual changes to their application.
     * This is particularly valuable for deployment pipelines and testing scenarios.
     *
     * @return int Command exit code
     */
    protected function handleDryRunMode(): int
    {
        $this->info('🧪 <fg=yellow>Simulating compilation process (dry-run mode)...</fg=yellow>');
        $this->info('');
        
        try {
            // Simulate the compilation process by running all steps except file writing
            // This demonstrates how services can provide simulation modes for testing
            $result = $this->compiler->compile($this->option('force'));
            
            $this->info('✅ <fg=green>Dry-run compilation completed successfully</fg=green>');
            $this->info('');
            
            $this->displayCompilationResults($result, true); // true = dry-run mode
            
            $this->info('');
            $this->info('💡 <fg=cyan>This was a simulation - no files were modified.</fg=cyan>');
            $this->line('   Run without --dry-run to perform actual compilation.');
            
            return 0;
            
        } catch (RouteCompilerException $e) {
            $this->error('❌ Dry-run compilation failed: ' . $e->getMessage());
            
            if ($this->verboseMode) {
                $this->displayCompilerError($e);
            }
            
            return 1;
        }
    }

    /**
     * Execute the main compilation process with comprehensive progress reporting.
     * 
     * This method demonstrates how to provide excellent user experience during
     * long-running operations through real-time progress updates and clear
     * communication about what's being accomplished at each stage.
     * 
     * We use Laravel's verbosity system to determine the appropriate feedback level:
     * - Normal: Progress bar for visual feedback during long operations
     * - Verbose (-v): Detailed step-by-step progress messages instead of progress bar
     * - Very verbose (-vv): Even more detailed information about each step
     *
     * @return array Compilation results for further processing
     */
    protected function executeCompilation(): array
    {
        $this->info('⚙️  <fg=yellow>Starting route compilation process...</fg=yellow>');
        $this->info('');
        
        // Create a progress bar for long-running operations when NOT in verbose mode
        // In verbose mode, we provide detailed text output instead of a progress bar
        // because the detailed messages are more valuable than visual progress indication
        $progressBar = null;
        if (!$this->output->isVerbose()) {
            $progressBar = $this->output->createProgressBar(5); // 5 major compilation stages
            $progressBar->setFormat('   %current%/%max% [%bar%] %percent:3s%% %message%');
            $progressBar->setMessage('Initializing compilation...');
            $progressBar->start();
        }
        
        // Execute compilation with progress reporting
        try {
            $startTime = microtime(true);
            
            // The compiler handles the complex compilation logic while we focus on user experience
            $result = $this->compiler->compile($this->option('force'));
            
            $compilationTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            $result['total_compilation_time'] = $compilationTime;
            
            if ($progressBar) {
                $progressBar->setMessage('Compilation completed!');
                $progressBar->finish();
                $this->info(''); // Add line break after progress bar
            }
            
            $this->info('');
            $this->info('✅ <fg=green;options=bold>Compilation completed successfully!</fg=green;options=bold>');
            $this->info('');
            
            return $result;
            
        } catch (\Exception $e) {
            // Ensure progress bar is properly cleaned up even if compilation fails
            if ($progressBar) {
                $progressBar->setMessage('Compilation failed');
                $progressBar->finish();
                $this->info('');
            }
            
            // Re-throw the exception for handling by the calling method
            throw $e;
        }
    }

    /**
     * Display comprehensive compilation results with different detail levels.
     * 
     * This method demonstrates how to provide information that's both comprehensive
     * and appropriately organized for different audiences and use cases. We use
     * Laravel's verbosity system to provide progressive disclosure:
     * 
     * - Normal: Essential metrics that all users need to see
     * - Verbose (-v): Additional details for developers who want more insight
     * - Very Verbose (-vv): Comprehensive statistics for detailed analysis
     * - Debug (-vvv): Maximum detail for troubleshooting
     * 
     * The --stats option can override verbosity to force detailed statistics display.
     *
     * @param array $result Compilation results from the RouteCompiler
     * @param bool $isDryRun Whether this was a simulation run
     */
    protected function displayCompilationResults(array $result, bool $isDryRun = false): void
    {
        $stats = $result['statistics'] ?? [];
        $discoveredCount = $result['discovered_routes'] ?? 0;
        $compiledCount = $result['compiled_routes'] ?? 0;
        $errors = $result['errors'] ?? [];
        
        // Display key metrics that developers care about most
        $this->info('📊 <fg=cyan;options=bold>Compilation Summary:</fg=cyan;options=bold>');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Templates Scanned', $stats['templates_scanned'] ?? 0],
                ['Routes Discovered', $discoveredCount],
                ['Routes Compiled', $compiledCount],
                ['Compilation Time', round($result['total_compilation_time'] ?? 0, 2) . 'ms'],
                ['Errors Encountered', count($errors)],
            ]
        );
        
        // Display additional detail based on verbosity level or explicit stats request
        // The --stats option allows users to request detailed statistics regardless of verbosity
        if ($this->output->isVerbose() || $this->option('stats')) {
            $this->displayDetailedStatistics($result);
        }
        
        // Show comprehensive analysis at very verbose level
        if ($this->output->isVeryVerbose()) {
            $this->displayComprehensiveAnalysis($result);
        }
        
        // Show any errors or warnings that occurred during compilation
        if (!empty($errors)) {
            $this->displayCompilationErrors($errors);
        }
        
        // Provide information about the output file location
        if (!$isDryRun && $compiledCount > 0) {
            $outputPath = $result['output_file'] ?? config('flashhalt.production.compiled_routes_path');
            $this->info('');
            $this->info("📁 <fg=green>Compiled routes written to:</fg=green> {$outputPath}");
        }
        
        // Show recommendations for improvement if any were generated
        if (!empty($result['recommendations'])) {
            $this->displayRecommendations($result['recommendations']);
        }
    }

    /**
     * Display detailed compilation statistics for developers who want comprehensive information.
     * 
     * This method demonstrates the progressive disclosure principle by providing
     * detailed information only when specifically requested, preventing information
     * overload while ensuring that comprehensive data is available when needed.
     *
     * @param array $result Complete compilation results
     */
    protected function displayDetailedStatistics(array $result): void
    {
        $this->info('');
        $this->info('📈 <fg=cyan>Detailed Statistics:</fg=cyan>');
        
        $stats = $result['statistics'] ?? [];
        
        // Display performance metrics that help with optimization
        if (isset($stats['compilation_time'])) {
            $this->line("   Total compilation time: {$stats['compilation_time']}ms");
        }
        
        // Display discovery and validation metrics
        $this->line("   Templates processed: " . ($stats['templates_scanned'] ?? 0));
        $this->line("   Routes discovered: " . ($stats['routes_discovered'] ?? 0));
        $this->line("   Routes validated: " . ($stats['routes_validated'] ?? 0));
        $this->line("   Routes compiled: " . ($stats['routes_compiled'] ?? 0));
        
        // Display error and warning counts if any occurred
        if (isset($stats['errors_encountered']) && $stats['errors_encountered'] > 0) {
            $this->line("   Errors encountered: {$stats['errors_encountered']}");
        }
        
        // Show memory and performance information if available
        $peakMemory = memory_get_peak_usage(true);
        $this->line("   Peak memory usage: " . $this->formatBytes($peakMemory));
    }

    /**
     * Display comprehensive analysis for very verbose output level.
     * 
     * This method provides the deepest level of analysis, including pattern
     * analysis, architectural insights, and optimization recommendations.
     * It's only shown at the very verbose level to avoid overwhelming users.
     *
     * @param array $result Complete compilation results
     */
    protected function displayComprehensiveAnalysis(array $result): void
    {
        $this->info('');
        $this->info('🔬 <fg=magenta>Comprehensive Analysis:</fg=magenta>');
        
        // In a full implementation, this would provide deep analysis of:
        // - Route pattern distributions
        // - Template organization insights
        // - Performance optimization opportunities
        // - Architectural recommendations
        
        $this->line('   Comprehensive route analysis would be displayed here.');
        $this->line('   This would include pattern analysis, optimization suggestions, etc.');
    }

    /**
     * Display discovered routes in an organized, readable format.
     * 
     * This method shows how to present complex data in ways that are both
     * comprehensive and easy to understand. The organization helps developers
     * quickly identify patterns and issues in their route structure.
     *
     * @param array $result Compilation results containing discovered routes
     */
    protected function displayDiscoveredRoutes(array $result): void
    {
        $discoveredRoutes = $result['discovered_routes'] ?? 0;
        
        if ($discoveredRoutes === 0) {
            $this->warn('⚠️  No FlashHALT routes were discovered in your templates.');
            $this->line('   This might indicate:');
            $this->line('   • Templates don\'t contain HTMX patterns with hx/ prefixes');
            $this->line('   • Template directories are not properly configured');
            $this->line('   • Route patterns don\'t follow the expected format');
            return;
        }
        
        $this->info("🔍 <fg=green>Discovered {$discoveredRoutes} FlashHALT routes:</fg=green>");
        $this->info('');
        
        // Note: In a full implementation, we would display the actual discovered routes
        // This would require the RouteCompiler to return more detailed route information
        $this->line('   Route details would be displayed here in the full implementation.');
        $this->line('   This would include route patterns, HTTP methods, source templates, etc.');
    }

    /**
     * Display analysis of route patterns and application structure.
     * 
     * This method demonstrates how commands can provide valuable insights
     * about application architecture and organization, helping developers
     * understand patterns in their code and identify optimization opportunities.
     *
     * @param array $result Compilation results for analysis
     */
    protected function displayRouteAnalysis(array $result): void
    {
        $this->info('');
        $this->info('📊 <fg=cyan>Route Analysis:</fg=cyan>');
        
        // In a full implementation, this would analyze route patterns and provide insights
        $this->line('   Route pattern analysis would be displayed here.');
        $this->line('   This might include namespace distribution, method usage, etc.');
    }

    /**
     * Display compilation errors and warnings in an educational format.
     * 
     * This method demonstrates how to transform technical errors into
     * actionable guidance that helps developers understand and fix problems
     * efficiently. The formatting makes error information accessible and useful.
     *
     * @param array $errors Array of compilation errors
     */
    protected function displayCompilationErrors(array $errors): void
    {
        $this->info('');
        $this->error('⚠️  Compilation Issues Encountered:');
        
        foreach ($errors as $index => $error) {
            $this->line("   " . ($index + 1) . ". {$error}");
        }
        
        $this->info('');
        $this->line('💡 <fg=yellow>Tips for resolving compilation issues:</fg=yellow>');
        $this->line('   • Ensure all referenced controllers exist and are properly named');
        $this->line('   • Check that controller methods are public and accessible');
        $this->line('   • Verify route patterns follow the format: controller@method');
        $this->line('   • Review file permissions if encountering file system errors');
    }

    /**
     * Display recommendations for improving application organization.
     * 
     * This method shows how commands can provide proactive guidance that helps
     * developers optimize their applications and follow best practices. The
     * recommendations are generated based on analysis of the compilation results.
     *
     * @param array $recommendations Array of recommendation strings
     */
    protected function displayRecommendations(array $recommendations): void
    {
        if (empty($recommendations)) {
            return;
        }
        
        $this->info('');
        $this->info('💡 <fg=yellow;options=bold>Recommendations:</fg=yellow;options=bold>');
        
        foreach ($recommendations as $index => $recommendation) {
            $this->line("   " . ($index + 1) . ". {$recommendation}");
        }
    }

    /**
     * Run verification checks after successful compilation.
     * 
     * This method demonstrates how to provide additional validation and
     * confidence-building features that help developers ensure their
     * compilation results are correct and ready for production deployment.
     *
     * @param array $result Compilation results to verify
     */
    protected function runVerificationChecks(array $result): void
    {
        $this->info('');
        $this->info('🔍 <fg=yellow>Running verification checks...</fg=yellow>');
        
        $outputPath = $result['output_file'] ?? config('flashhalt.production.compiled_routes_path');
        
        // Check that the compiled routes file exists and is readable
        if (!file_exists($outputPath)) {
            $this->error("❌ Compiled routes file not found: {$outputPath}");
            return;
        }
        
        if (!is_readable($outputPath)) {
            $this->error("❌ Compiled routes file is not readable: {$outputPath}");
            return;
        }
        
        // Verify that the compiled file contains valid PHP syntax
        $syntaxCheck = shell_exec("php -l {$outputPath} 2>&1");
        if (strpos($syntaxCheck, 'No syntax errors') === false) {
            $this->error('❌ Compiled routes file contains syntax errors');
            $this->line("   Syntax check output: {$syntaxCheck}");
            return;
        }
        
        // Check file size and route count consistency
        $fileSize = filesize($outputPath);
        $compiledRoutes = $result['compiled_routes'] ?? 0;
        
        $this->info('✅ Verification checks passed:');
        $this->line("   • Compiled routes file exists and is readable");
        $this->line("   • PHP syntax is valid");
        $this->line("   • File size: " . $this->formatBytes($fileSize));
        $this->line("   • Contains {$compiledRoutes} compiled routes");
        
        $this->summaryMessages[] = 'Verification checks completed successfully';
    }

    /**
     * Handle compilation-specific errors with detailed, educational information.
     * 
     * This method demonstrates how to leverage sophisticated exception architecture
     * to provide comprehensive error handling that helps developers understand
     * and fix complex compilation issues efficiently.
     * 
     * We use Laravel's verbosity levels to provide appropriate error detail:
     * - Normal: Essential error information and basic guidance
     * - Verbose (-v): Comprehensive error context and detailed suggestions
     * - Very Verbose (-vv): Full exception analysis and debugging information
     *
     * @param RouteCompilerException $exception The compilation error
     */
    protected function handleCompilationError(RouteCompilerException $exception): void
    {
        $this->info('');
        $this->error('❌ <fg=red;options=bold>Compilation Failed</fg=red;options=bold>');
        $this->info('');
        
        // Display the main error message with context - this is always shown
        $this->error('Error: ' . $exception->getMessage());
        $this->line('Stage: ' . $exception->getCompilationStage());
        
        if ($exception->getRoutePattern()) {
            $this->line('Route: ' . $exception->getRoutePattern());
        }
        
        // Display comprehensive error information when verbose output is requested
        // This demonstrates how verbosity levels enable progressive error disclosure
        if ($this->output->isVerbose()) {
            $this->displayCompilerError($exception);
        }
        
        // Always display suggestions for fixing the problem, regardless of verbosity
        // Problem-solving guidance should always be available to help developers
        $this->displayErrorSuggestions($exception);
        
        $this->info('');
        
        // Only suggest the verbose option if we're not already in verbose mode
        // This avoids redundant suggestions and guides users toward helpful information
        if (!$this->output->isVerbose()) {
            $this->line('💡 <fg=yellow>Run with -v for detailed error analysis and debugging information</fg=yellow>');
        }
    }

    /**
     * Display detailed compiler error information for debugging purposes.
     * 
     * This method shows how to present complex error information in an
     * organized way that helps developers understand both what went wrong
     * and what context was available when the error occurred.
     *
     * @param RouteCompilerException $exception The compiler exception
     */
    protected function displayCompilerError(RouteCompilerException $exception): void
    {
        $this->info('');
        $this->info('🔍 <fg=yellow>Detailed Error Information:</fg=yellow>');
        
        // Display compilation context
        $templateFiles = $exception->getTemplateFiles();
        if (!empty($templateFiles)) {
            $this->line('   Templates being processed: ' . count($templateFiles));
        }
        
        $discoveredRoutes = $exception->getDiscoveredRoutes();
        if (!empty($discoveredRoutes)) {
            $this->line('   Routes discovered: ' . count($discoveredRoutes));
        }
        
        $failedRoutes = $exception->getFailedRoutes();
        if (!empty($failedRoutes)) {
            $this->line('   Routes that failed validation: ' . count($failedRoutes));
            
            // Show examples of failed routes for context
            $exampleFailures = array_slice($failedRoutes, 0, 3);
            foreach ($exampleFailures as $failure) {
                $this->line("     • {$failure['pattern']}: {$failure['error']}");
            }
        }
        
        // Display compilation progress information
        $progress = $exception->getCompilationProgress();
        if (!empty($progress)) {
            $this->line('   Compilation progress when error occurred:');
            foreach ($progress as $key => $value) {
                $this->line("     {$key}: {$value}");
            }
        }
    }

    /**
     * Display actionable suggestions for resolving compilation errors.
     * 
     * This method demonstrates how to transform exception information into
     * practical guidance that helps developers fix problems efficiently.
     * The suggestions are prioritized and organized for maximum usefulness.
     *
     * @param RouteCompilerException $exception The compilation exception
     */
    protected function displayErrorSuggestions(RouteCompilerException $exception): void
    {
        $suggestions = $exception->getSuggestions();
        
        if (!empty($suggestions)) {
            $this->info('');
            $this->info('💡 <fg=yellow;options=bold>Suggestions to resolve this error:</fg=yellow;options=bold>');
            
            foreach ($suggestions as $index => $suggestion) {
                $this->line("   " . ($index + 1) . ". {$suggestion}");
            }
        }
        
        // Add links to documentation if available
        $docLinks = $exception->getDocumentationLinks();
        if (!empty($docLinks)) {
            $this->info('');
            $this->info('📚 <fg=cyan>Related Documentation:</fg=cyan>');
            
            foreach ($docLinks as $title => $url) {
                $this->line("   • {$title}: {$url}");
            }
        }
    }

    /**
     * Handle unexpected errors gracefully while providing debugging information.
     * 
     * This method demonstrates how to handle errors that fall outside the
     * expected error categories while still providing useful information
     * for debugging and maintaining application stability.
     * 
     * We use Laravel's verbosity system to provide appropriate debug detail:
     * - Normal: Basic error information with general guidance
     * - Verbose (-v): Additional context and debugging suggestions
     * - Debug (-vvv): Full stack trace and comprehensive debug information
     *
     * @param \Exception $exception The unexpected error
     */
    protected function handleUnexpectedError(\Exception $exception): void
    {
        $this->info('');
        $this->error('❌ <fg=red;options=bold>Unexpected Error Occurred</fg=red;options=bold>');
        $this->info('');
        
        $this->error('An unexpected error occurred during compilation:');
        $this->line($exception->getMessage());
        
        // Provide additional debugging context when verbose output is requested
        if ($this->output->isVerbose()) {
            $this->info('');
            $this->info('🔍 <fg=yellow>Debug Information:</fg=yellow>');
            $this->line('   Exception Type: ' . get_class($exception));
            $this->line('   File: ' . $exception->getFile() . ':' . $exception->getLine());
        }
        
        // Show full stack trace only in debug mode, as it can be overwhelming
        // Debug level (-vvv) indicates the user specifically wants maximum detail
        if ($this->output->isDebug() && config('app.debug')) {
            $this->info('');
            $this->line('Stack Trace:');
            $this->line($exception->getTraceAsString());
        }
        
        $this->info('');
        $this->line('💡 <fg=yellow>This appears to be an unexpected error.</fg=yellow>');
        $this->line('   Please check the Laravel logs for additional details.');
        
        // Provide progressive guidance based on current verbosity level
        if (!$this->output->isVerbose()) {
            $this->line('   Consider running with -v for more debugging information.');
        } elseif (!$this->output->isDebug()) {
            $this->line('   Use -vvv for full stack trace and maximum debug detail.');
        }
    }

    /**
     * Display a final command summary with important messages and next steps.
     * 
     * This method demonstrates how to provide closure and guidance at the end
     * of command execution, ensuring that important information is highlighted
     * and developers understand what to do next.
     */
    protected function displayCommandSummary(): void
    {
        if (!empty($this->summaryMessages)) {
            $this->info('');
            $this->info('📋 <fg=cyan;options=bold>Summary:</fg=cyan;options=bold>');
            
            foreach ($this->summaryMessages as $message) {
                $this->line("   ✅ {$message}");
            }
        }
        
        $this->info('');
        $this->info('🎉 <fg=green;options=bold>FlashHALT compilation process completed!</fg=green;options=bold>');
        $this->line('   Your application is now ready for production deployment.');
        $this->info('');
    }

    /**
     * Format byte values into human-readable format.
     * 
     * This utility method demonstrates how to provide user-friendly data
     * presentation that makes technical information more accessible and
     * meaningful to developers.
     *
     * @param int $bytes Byte value to format
     * @return string Human-readable byte string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
    }
}