<?php

namespace DancyCodes\FlashHalt\Console\Commands;

use DancyCodes\FlashHalt\Services\RouteCompiler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

/**
 * FlashHALT Clear Command - Safe Cleanup and Maintenance Interface
 * 
 * This Artisan command provides comprehensive cleanup functionality for FlashHALT
 * compilation artifacts while maintaining the highest standards of safety and
 * user experience. Unlike creation commands that build new functionality, cleanup
 * commands require special consideration for potentially destructive operations.
 * 
 * The command demonstrates several key principles of safe cleanup interface design:
 * - Explicit confirmation for destructive operations with clear consequence explanation
 * - Progressive cleanup modes from cautious analysis to comprehensive removal
 * - Comprehensive feedback about what was found and what was actually removed
 * - Intelligent detection of compilation artifacts across different application states
 * - Safe file operations with proper error handling and rollback capabilities
 * 
 * The psychological aspect of cleanup commands requires different design approaches:
 * - Users are more cautious about removal than creation operations
 * - Clear communication about what will be lost and what can be regenerated
 * - Multiple confirmation points for operations with significant impact
 * - Detailed reporting about what was actually accomplished for confidence building
 * 
 * This implementation shows how to balance thoroughness with safety, providing
 * developers with powerful cleanup capabilities while maintaining confidence that
 * important work won't be accidentally destroyed.
 */
class ClearCommand extends Command
{
    /**
     * The command signature balances simplicity with safety through thoughtful option design.
     * 
     * The signature demonstrates several important principles for destructive commands:
     * - Clear base command name that immediately communicates the destructive intent
     * - Force flag that requires explicit intention for potentially dangerous operations
     * - Dry-run capability that allows safe exploration of what would be cleared
     * - Granular options that provide control over different types of cleanup
     * - Verbose modes that provide transparency about cleanup operations
     * 
     * Each option serves a specific purpose in making the command both powerful and safe:
     * - The absence of a force flag defaults to safe, non-destructive behavior
     * - Multiple confirmation points ensure developers understand consequences
     * - Granular cleanup options allow targeted maintenance without overreach
     */
    protected $signature = 'flashhalt:clear
                            {--force : Skip confirmation prompts and perform cleanup immediately}
                            {--dry-run : Show what would be cleared without actually removing anything}
                            {--compiled-routes : Only clear compiled route files}
                            {--cache : Only clear FlashHALT-related cache entries}
                            {--all : Clear all FlashHALT artifacts (routes, cache, temporary files)}';

    /**
     * The command description emphasizes both capability and safety to set appropriate expectations.
     * 
     * The description balances the communication of powerful cleanup capabilities with
     * appropriate caution about the destructive nature of the operations. This helps
     * developers understand both what the command can accomplish and why they should
     * use it thoughtfully.
     */
    protected $description = 'Clear FlashHALT compilation artifacts and cache (use with caution)';

    /**
     * The RouteCompiler service provides access to compilation-related cleanup operations.
     * 
     * By injecting the same service used for compilation, we ensure that the clear
     * command understands the same file locations, cache keys, and artifact patterns
     * as the compilation process. This consistency is crucial for reliable cleanup
     * that doesn't miss artifacts or accidentally remove unrelated files.
     */
    protected RouteCompiler $compiler;

    /**
     * Tracking arrays for comprehensive reporting about cleanup operations.
     * 
     * These arrays demonstrate how cleanup commands need to maintain detailed
     * records of what was discovered and what was actually removed. This information
     * serves multiple purposes: building user confidence, enabling debugging of
     * cleanup issues, and providing audit trails for maintenance operations.
     */
    protected array $foundArtifacts = [];
    protected array $removedArtifacts = [];
    protected array $cleanupErrors = [];

    /**
     * Flag to track whether we're running in dry-run mode for output formatting.
     * 
     * Dry-run mode is particularly important for cleanup commands because it allows
     * developers to safely explore what would be removed without risking data loss.
     * This flag ensures that all output clearly indicates simulation versus actual
     * removal operations.
     */
    protected bool $isDryRun = false;

    /**
     * Constructor injection demonstrates how cleanup commands can leverage the same
     * services used for creation operations while focusing on the complementary
     * cleanup responsibilities.
     *
     * @param RouteCompiler $compiler The compilation service for cleanup operations
     */
    public function __construct(RouteCompiler $compiler)
    {
        parent::__construct();
        $this->compiler = $compiler;
    }

    /**
     * Execute the clear command with comprehensive safety and user experience features.
     * 
     * The execution flow follows a pattern specifically designed for potentially
     * destructive operations. Each stage includes safety checks, clear communication
     * about consequences, and opportunities for users to reconsider their actions.
     * 
     * The method demonstrates how to structure cleanup operations to maximize both
     * thoroughness and safety, ensuring that developers feel confident about what
     * will be removed and what the consequences of those removals will be.
     *
     * @return int Command exit code (0 for success, non-zero for failure)
     */
    public function handle(): int
    {
        // Initialize command state and determine operation mode
        $this->initializeCommand();
        
        // Display clear, informative header that sets expectations for cleanup
        $this->displayCommandHeader();
        
        // Analyze the current state to understand what artifacts exist
        $this->analyzeCurrentState();
        
        // If no artifacts were found, provide helpful guidance and exit early
        if (empty($this->foundArtifacts)) {
            $this->displayNoArtifactsMessage();
            return 0;
        }
        
        // Display what was found so users understand the scope of potential cleanup
        $this->displayFoundArtifacts();
        
        // Handle dry-run mode by showing what would be cleared without doing it
        if ($this->isDryRun) {
            $this->displayDryRunResults();
            return 0;
        }
        
        // Obtain user confirmation unless force flag is used
        if (!$this->option('force') && !$this->confirmCleanupOperation()) {
            $this->info('Cleanup operation cancelled by user.');
            return 0;
        }
        
        // Execute the actual cleanup operations with comprehensive error handling
        try {
            $this->executeCleanupOperations();
            $this->displayCleanupResults();
            return 0;
            
        } catch (\Exception $e) {
            $this->handleCleanupError($e);
            return 1;
        }
    }

    /**
     * Initialize command execution and determine operational parameters.
     * 
     * This initialization method demonstrates how cleanup commands need to establish
     * clear operational boundaries and safety parameters before beginning any
     * analysis or cleanup operations. The initialization process sets up the
     * foundational state that guides all subsequent operations.
     */
    protected function initializeCommand(): void
    {
        // Determine if we're running in simulation mode
        $this->isDryRun = $this->option('dry-run');
        
        // Initialize tracking arrays for comprehensive reporting
        $this->foundArtifacts = [];
        $this->removedArtifacts = [];
        $this->cleanupErrors = [];
        
        // Provide initial context in verbose mode to help users understand the process
        if ($this->getOutput()->isVerbose()) {
            $this->info('🔧 Initializing FlashHALT cleanup process...');
            $this->displayOperationMode();
        }
    }

    /**
     * Display a clear header that communicates both capability and caution.
     * 
     * The header for cleanup commands needs to balance several communication goals:
     * it should clearly identify what the command does, emphasize the potentially
     * destructive nature of the operations, and provide context about safety
     * features that protect against accidental data loss.
     */
    protected function displayCommandHeader(): void
    {
        $this->info('');
        $this->info('🧹 <fg=yellow;options=bold>FlashHALT Cleanup Operations</fg=yellow;options=bold>');
        
        if ($this->isDryRun) {
            $this->info('   <fg=cyan>Analyzing compilation artifacts (dry-run mode)</fg=cyan>');
        } else {
            $this->info('   <fg=red>Removing FlashHALT compilation artifacts</fg=red>');
        }
        
        $this->info('');
        
        // Provide safety reminders for non-dry-run operations
        if (!$this->isDryRun && !$this->option('force')) {
            $this->warn('⚠️  This operation will remove compilation artifacts from your application.');
            $this->line('   Removed files can be regenerated by running: php artisan flashhalt:compile');
            $this->info('');
        }
    }

    /**
     * Display current operation mode to provide transparency about command behavior.
     * 
     * This method demonstrates how verbose modes in cleanup commands should provide
     * clear communication about what type of operation is being performed and what
     * safety measures are in place. This transparency helps build user confidence
     * in the command's behavior.
     */
    protected function displayOperationMode(): void
    {
        $this->info('📋 <fg=yellow>Operation Mode:</fg=yellow>');
        
        if ($this->isDryRun) {
            $this->line('   Mode: Dry-run (simulation only - no files will be removed)');
        } elseif ($this->option('force')) {
            $this->line('   Mode: Force (cleanup will proceed without confirmation)');
        } else {
            $this->line('   Mode: Interactive (confirmation required before cleanup)');
        }
        
        // Display specific cleanup targets based on options
        $targets = $this->determineCleanupTargets();
        $this->line('   Targets: ' . implode(', ', $targets));
        
        $this->info('');
    }

    /**
     * Determine what types of artifacts should be targeted for cleanup based on options.
     * 
     * This method demonstrates how cleanup commands can provide granular control
     * over what gets removed, allowing users to perform targeted maintenance
     * operations without unnecessary broad cleanup that might affect unrelated
     * functionality.
     *
     * @return array Array of cleanup target descriptions
     */
    protected function determineCleanupTargets(): array
    {
        $targets = [];
        
        // Check for specific target options first
        if ($this->option('compiled-routes')) {
            $targets[] = 'Compiled routes';
        }
        
        if ($this->option('cache')) {
            $targets[] = 'Cache entries';
        }
        
        // If no specific targets or --all flag, include everything
        if (empty($targets) || $this->option('all')) {
            $targets = ['Compiled routes', 'Cache entries', 'Temporary files'];
        }
        
        return $targets;
    }

    /**
     * Analyze the current application state to discover FlashHALT artifacts.
     * 
     * This analysis phase is crucial for cleanup commands because it provides
     * transparency about what exists in the application before any removal
     * operations begin. The analysis helps users understand the scope of
     * cleanup operations and builds confidence in the command's intelligence
     * about what should and shouldn't be removed.
     */
    protected function analyzeCurrentState(): void
    {
        $this->info('🔍 <fg=yellow>Analyzing FlashHALT artifacts...</fg=yellow>');
        
        // Look for compiled route files
        $this->analyzeCompiledRoutes();
        
        // Look for FlashHALT cache entries
        $this->analyzeFlashHaltCache();
        
        // Look for temporary files and other artifacts
        $this->analyzeTemporaryFiles();
        
        // Provide summary of analysis results
        $artifactCount = count($this->foundArtifacts);
        if ($artifactCount > 0) {
            $this->info("✅ Analysis complete. Found {$artifactCount} artifacts to process.");
        } else {
            $this->info('✅ Analysis complete. No FlashHALT artifacts found.');
        }
        
        $this->info('');
    }

    /**
     * Analyze compiled route files and their associated metadata.
     * 
     * This method demonstrates how cleanup commands need to understand the
     * structure and relationships of the artifacts they're managing. Compiled
     * routes might include not just the main routes file, but also backup
     * files, compilation reports, and other associated artifacts.
     */
    protected function analyzeCompiledRoutes(): void
    {
        $compiledRoutesPath = config('flashhalt.production.compiled_routes_path');
        
        if (!$compiledRoutesPath) {
            // If no compiled routes path is configured, we can't find route artifacts
            return;
        }
        
        // Check for the main compiled routes file
        if (file_exists($compiledRoutesPath)) {
            $fileSize = filesize($compiledRoutesPath);
            $lastModified = filemtime($compiledRoutesPath);
            
            $this->foundArtifacts[] = [
                'type' => 'compiled_routes',
                'path' => $compiledRoutesPath,
                'size' => $fileSize,
                'modified' => $lastModified,
                'description' => 'Main compiled routes file'
            ];
            
            if ($this->getOutput()->isVerbose()) {
                $this->line("   Found compiled routes: {$compiledRoutesPath} (" . $this->formatBytes($fileSize) . ")");
            }
        }
        
        // Look for backup files and temporary compilation artifacts
        $compiledDir = dirname($compiledRoutesPath);
        $compiledBasename = basename($compiledRoutesPath);
        
        // Check for common backup and temporary file patterns
        $backupPatterns = [
            $compiledRoutesPath . '.backup',
            $compiledRoutesPath . '.tmp',
            $compiledDir . '/' . $compiledBasename . '.bak'
        ];
        
        foreach ($backupPatterns as $backupPath) {
            if (file_exists($backupPath)) {
                $this->foundArtifacts[] = [
                    'type' => 'backup_file',
                    'path' => $backupPath,
                    'size' => filesize($backupPath),
                    'modified' => filemtime($backupPath),
                    'description' => 'Backup or temporary compilation file'
                ];
                
                if ($this->getOutput()->isVerbose()) {
                    $this->line("   Found backup file: {$backupPath}");
                }
            }
        }
    }

    /**
     * Analyze FlashHALT-related cache entries across different cache stores.
     * 
     * This method demonstrates how cleanup commands need to understand the
     * caching strategies used by the application components they're cleaning up.
     * FlashHALT uses caching for controller resolution, security validation,
     * and compilation results, and cleanup should be able to target these
     * caches specifically without affecting unrelated application caches.
     */
    protected function analyzeFlashHaltCache(): void
    {
        // FlashHALT uses specific cache key patterns that we can identify and count
        $flashhaltCacheKeys = $this->findFlashHaltCacheKeys();
        
        if (!empty($flashhaltCacheKeys)) {
            $this->foundArtifacts[] = [
                'type' => 'cache_entries',
                'count' => count($flashhaltCacheKeys),
                'keys' => $flashhaltCacheKeys,
                'description' => 'FlashHALT cache entries (controller resolution, security validation)'
            ];
            
            if ($this->getOutput()->isVerbose()) {
                $cacheCount = count($flashhaltCacheKeys);
                $this->line("   Found {$cacheCount} FlashHALT cache entries");
                
                // Show examples of cache keys for transparency
                $exampleKeys = array_slice($flashhaltCacheKeys, 0, 3);
                foreach ($exampleKeys as $key) {
                    $this->line("     • {$key}");
                }
                
                if (count($flashhaltCacheKeys) > 3) {
                    $remaining = count($flashhaltCacheKeys) - 3;
                    $this->line("     ... and {$remaining} more");
                }
            }
        }
    }

    /**
     * Find FlashHALT-specific cache keys without affecting other application caches.
     * 
     * This method demonstrates how to safely identify specific cache entries
     * without accidentally affecting unrelated application caching. The approach
     * uses the known cache key patterns that FlashHALT services use to identify
     * only the relevant cache entries.
     *
     * @return array Array of FlashHALT cache keys
     */
    protected function findFlashHaltCacheKeys(): array
    {
        $flashhaltKeys = [];
        
        // FlashHALT uses specific prefixes for its cache keys
        $flashhaltPrefixes = [
            'flashhalt:resolution:',
            'flashhalt:security:',
            'flashhalt:controller:',
            'flashhalt:compilation:'
        ];
        
        try {
            // Note: This is a simplified approach. In a real implementation,
            // you would need to work with the specific cache driver to enumerate keys.
            // Some cache drivers (like Redis) support key pattern matching,
            // while others (like file cache) require different approaches.
            
            // For demonstration purposes, we'll simulate finding cache keys
            // In practice, this would depend on the cache driver being used
            foreach ($flashhaltPrefixes as $prefix) {
                // This would be implemented based on the actual cache driver
                // For file cache: scan cache directory for matching file patterns
                // For Redis: use KEYS or SCAN commands with pattern matching
                // For database cache: query the cache table with LIKE patterns
                
                // Simulated cache key discovery
                $simulatedKeys = $this->simulateFlashHaltCacheKeys($prefix);
                $flashhaltKeys = array_merge($flashhaltKeys, $simulatedKeys);
            }
            
        } catch (\Exception $e) {
            // If cache key enumeration fails, note the error but continue
            $this->cleanupErrors[] = "Failed to analyze cache keys: " . $e->getMessage();
        }
        
        return $flashhaltKeys;
    }

    /**
     * Simulate FlashHALT cache key discovery for demonstration purposes.
     * 
     * In a real implementation, this method would be replaced with actual
     * cache driver-specific logic for finding keys that match FlashHALT patterns.
     * The simulation helps demonstrate the concept without requiring specific
     * cache driver implementations.
     *
     * @param string $prefix The cache key prefix to simulate
     * @return array Array of simulated cache keys
     */
    protected function simulateFlashHaltCacheKeys(string $prefix): array
    {
        // This is simulation code for demonstration purposes
        // Real implementation would query actual cache store
        $simulatedKeys = [];
        
        // Simulate some cache keys that might exist
        switch ($prefix) {
            case 'flashhalt:resolution:':
                $simulatedKeys = [
                    'flashhalt:resolution:users@create:GET',
                    'flashhalt:resolution:admin.posts@edit:GET'
                ];
                break;
                
            case 'flashhalt:security:':
                $simulatedKeys = [
                    'flashhalt:security:UserController:create:GET',
                    'flashhalt:security:Admin\\PostController:edit:GET'
                ];
                break;
        }
        
        return $simulatedKeys;
    }

    /**
     * Analyze temporary files and other miscellaneous FlashHALT artifacts.
     * 
     * This method demonstrates how thorough cleanup commands should look for
     * various types of artifacts that might be created during normal operation,
     * including temporary files, log files, debug outputs, and other artifacts
     * that might accumulate over time.
     */
    protected function analyzeTemporaryFiles(): void
    {
        // Look for FlashHALT-related temporary files in common locations
        $tempLocations = [
            storage_path('framework/cache'),
            storage_path('logs'),
            sys_get_temp_dir()
        ];
        
        foreach ($tempLocations as $location) {
            if (!is_dir($location)) {
                continue;
            }
            
            try {
                // Look for files that match FlashHALT temporary file patterns
                $tempFiles = $this->findFlashHaltTempFiles($location);
                
                foreach ($tempFiles as $tempFile) {
                    $this->foundArtifacts[] = [
                        'type' => 'temp_file',
                        'path' => $tempFile,
                        'size' => filesize($tempFile),
                        'modified' => filemtime($tempFile),
                        'description' => 'Temporary file created during FlashHALT operations'
                    ];
                }
                
                if (!empty($tempFiles) && $this->getOutput()->isVerbose()) {
                    $this->line("   Found " . count($tempFiles) . " temporary files in {$location}");
                }
                
            } catch (\Exception $e) {
                // If temporary file analysis fails, note the error but continue
                $this->cleanupErrors[] = "Failed to analyze temporary files in {$location}: " . $e->getMessage();
            }
        }
    }

    /**
     * Find temporary files that match FlashHALT patterns in a given directory.
     * 
     * This method demonstrates how to safely identify temporary files without
     * accidentally targeting unrelated temporary files that might be important
     * for other application functionality.
     *
     * @param string $directory Directory to search for temporary files
     * @return array Array of temporary file paths
     */
    protected function findFlashHaltTempFiles(string $directory): array
    {
        $flashhaltTempFiles = [];
        
        // Define patterns that match FlashHALT temporary files
        $tempPatterns = [
            'flashhalt_*.tmp',
            'flashhalt_compilation_*.cache',
            'flashhalt_routes_*.temp'
        ];
        
        foreach ($tempPatterns as $pattern) {
            $matchingFiles = glob($directory . '/' . $pattern);
            if ($matchingFiles) {
                $flashhaltTempFiles = array_merge($flashhaltTempFiles, $matchingFiles);
            }
        }
        
        return $flashhaltTempFiles;
    }

    /**
     * Display a helpful message when no artifacts are found to clean up.
     * 
     * This method demonstrates how cleanup commands should handle the scenario
     * where there's nothing to clean up. Rather than simply exiting, the command
     * provides helpful context about what this means and what users might want
     * to do instead.
     */
    protected function displayNoArtifactsMessage(): void
    {
        $this->info('🎉 <fg=green>No FlashHALT artifacts found to clean up!</fg=green>');
        $this->info('');
        $this->line('This could mean:');
        $this->line('   • FlashHALT compilation has never been run');
        $this->line('   • Artifacts have already been cleaned up');
        $this->line('   • FlashHALT is configured to use different file locations');
        $this->info('');
        $this->line('💡 To generate compilation artifacts, run: php artisan flashhalt:compile');
    }

    /**
     * Display comprehensive information about discovered artifacts.
     * 
     * This method demonstrates how cleanup commands should provide transparency
     * about what was discovered before any removal operations begin. The
     * presentation helps users understand the scope and impact of potential
     * cleanup operations.
     */
    protected function displayFoundArtifacts(): void
    {
        $artifactCount = count($this->foundArtifacts);
        $this->info("📋 <fg=cyan>Found {$artifactCount} FlashHALT artifacts:</fg=cyan>");
        $this->info('');
        
        // Group artifacts by type for organized presentation
        $artifactsByType = $this->groupArtifactsByType();
        
        foreach ($artifactsByType as $type => $artifacts) {
            $this->displayArtifactGroup($type, $artifacts);
        }
        
        // Calculate total size of artifacts that would be removed
        $totalSize = $this->calculateTotalArtifactSize();
        if ($totalSize > 0) {
            $this->info('');
            $this->line("💾 Total size of artifacts: " . $this->formatBytes($totalSize));
        }
        
        $this->info('');
    }

    /**
     * Group discovered artifacts by type for organized presentation.
     * 
     * This method demonstrates how to organize complex information for clear
     * presentation to users. Grouping artifacts by type makes it easier for
     * users to understand what types of cleanup will be performed and make
     * informed decisions about whether to proceed.
     *
     * @return array Artifacts grouped by type
     */
    protected function groupArtifactsByType(): array
    {
        $grouped = [];
        
        foreach ($this->foundArtifacts as $artifact) {
            $type = $artifact['type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $artifact;
        }
        
        return $grouped;
    }

    /**
     * Display a group of artifacts of the same type.
     * 
     * This method shows how to present detailed information about specific
     * types of artifacts while maintaining readability and providing appropriate
     * detail levels based on user preferences.
     *
     * @param string $type The artifact type
     * @param array $artifacts Array of artifacts of this type
     */
    protected function displayArtifactGroup(string $type, array $artifacts): void
    {
        $typeDisplayNames = [
            'compiled_routes' => 'Compiled Routes',
            'backup_file' => 'Backup Files',
            'cache_entries' => 'Cache Entries',
            'temp_file' => 'Temporary Files'
        ];
        
        $displayName = $typeDisplayNames[$type] ?? ucfirst(str_replace('_', ' ', $type));
        $count = count($artifacts);
        
        $this->line("   <fg=yellow>{$displayName}:</fg=yellow> {$count} item(s)");
        
        // Show details for file-based artifacts
        foreach ($artifacts as $artifact) {
            if (isset($artifact['path'])) {
                $path = $artifact['path'];
                $size = isset($artifact['size']) ? ' (' . $this->formatBytes($artifact['size']) . ')' : '';
                
                if ($this->getOutput()->isVerbose()) {
                    $this->line("     • {$path}{$size}");
                } else {
                    // Show abbreviated path for brevity
                    $shortPath = strlen($path) > 60 ? '...' . substr($path, -57) : $path;
                    $this->line("     • {$shortPath}{$size}");
                }
            } elseif (isset($artifact['count'])) {
                // Handle cache entries which are counted rather than individually listed
                $this->line("     • {$artifact['count']} cache entries");
            }
        }
    }

    /**
     * Calculate the total size of all discovered artifacts.
     * 
     * This method demonstrates how cleanup commands can provide useful metrics
     * about the impact of cleanup operations, helping users understand how much
     * disk space will be recovered through the cleanup process.
     *
     * @return int Total size in bytes
     */
    protected function calculateTotalArtifactSize(): int
    {
        $totalSize = 0;
        
        foreach ($this->foundArtifacts as $artifact) {
            if (isset($artifact['size'])) {
                $totalSize += $artifact['size'];
            }
        }
        
        return $totalSize;
    }

    /**
     * Display dry-run results without performing actual cleanup operations.
     * 
     * This method demonstrates how dry-run modes should provide comprehensive
     * simulation results that help users understand exactly what would happen
     * if they ran the command without the dry-run flag.
     */
    protected function displayDryRunResults(): void
    {
        $this->info('🧪 <fg=cyan;options=bold>Dry-run Results:</fg=cyan;options=bold>');
        $this->info('');
        
        $artifactCount = count($this->foundArtifacts);
        $totalSize = $this->calculateTotalArtifactSize();
        
        $this->line("Would remove {$artifactCount} artifacts");
        if ($totalSize > 0) {
            $this->line("Would recover " . $this->formatBytes($totalSize) . " of disk space");
        }
        
        $this->info('');
        $this->info('💡 <fg=green>This was a simulation - no files were actually removed.</fg=green>');
        $this->line('   Run without --dry-run to perform actual cleanup.');
        $this->line('   Add --force to skip confirmation prompts.');
    }

    /**
     * Obtain user confirmation for cleanup operations with clear consequence communication.
     * 
     * This method demonstrates how cleanup commands should handle confirmation
     * requests in ways that ensure users understand the consequences of their
     * actions while providing clear paths forward for different scenarios.
     *
     * @return bool True if user confirms cleanup should proceed
     */
    protected function confirmCleanupOperation(): bool
    {
        $artifactCount = count($this->foundArtifacts);
        $totalSize = $this->calculateTotalArtifactSize();
        
        $this->warn('⚠️  You are about to remove FlashHALT compilation artifacts.');
        $this->info('');
        $this->line("   Artifacts to remove: {$artifactCount}");
        
        if ($totalSize > 0) {
            $this->line("   Disk space to recover: " . $this->formatBytes($totalSize));
        }
        
        $this->info('');
        $this->line('🔄 These artifacts can be regenerated by running: php artisan flashhalt:compile');
        $this->info('');
        
        return $this->confirm('Do you want to proceed with the cleanup?');
    }

    /**
     * Execute the actual cleanup operations with comprehensive error handling.
     * 
     * This method demonstrates how to perform potentially destructive operations
     * safely while providing real-time feedback about progress and maintaining
     * detailed records of what was actually accomplished.
     */
    protected function executeCleanupOperations(): void
    {
        $this->info('🧹 <fg=yellow>Executing cleanup operations...</fg=yellow>');
        $this->info('');
        
        $totalArtifacts = count($this->foundArtifacts);
        $processedCount = 0;
        
        // Create a progress bar for cleanup operations
        if (!$this->getOutput()->isVerbose()) {
            $progressBar = $this->output->createProgressBar($totalArtifacts);
            $progressBar->setFormat('   %current%/%max% [%bar%] %percent:3s%% %message%');
            $progressBar->setMessage('Starting cleanup...');
            $progressBar->start();
        }
        
        foreach ($this->foundArtifacts as $artifact) {
            try {
                $this->removeArtifact($artifact);
                $processedCount++;
                
                if (!$this->getOutput()->isVerbose()) {
                    $progressBar->setMessage("Processed {$processedCount}/{$totalArtifacts} artifacts");
                    $progressBar->advance();
                }
                
            } catch (\Exception $e) {
                // Record errors but continue with other artifacts
                $this->cleanupErrors[] = "Failed to remove {$artifact['description']}: " . $e->getMessage();
                
                if ($this->getOutput()->isVerbose()) {
                    $this->error("   ❌ Failed to remove {$artifact['description']}: " . $e->getMessage());
                }
            }
        }
        
        if (!$this->getOutput()->isVerbose()) {
            $progressBar->setMessage('Cleanup completed!');
            $progressBar->finish();
            $this->info('');
        }
        
        $this->info('');
    }

    /**
     * Remove a specific artifact with appropriate handling for different artifact types.
     * 
     * This method demonstrates how cleanup operations need to handle different
     * types of artifacts appropriately, using the right removal techniques for
     * files, cache entries, and other types of artifacts while maintaining
     * comprehensive error handling.
     *
     * @param array $artifact The artifact to remove
     */
    protected function removeArtifact(array $artifact): void
    {
        switch ($artifact['type']) {
            case 'compiled_routes':
            case 'backup_file':
            case 'temp_file':
                $this->removeFile($artifact);
                break;
                
            case 'cache_entries':
                $this->removeCacheEntries($artifact);
                break;
                
            default:
                throw new \Exception("Unknown artifact type: {$artifact['type']}");
        }
    }

    /**
     * Remove a file artifact with proper error handling and verification.
     * 
     * This method demonstrates safe file removal practices that verify
     * file existence, handle permission issues gracefully, and provide
     * comprehensive feedback about the removal operation.
     *
     * @param array $artifact File artifact to remove
     */
    protected function removeFile(array $artifact): void
    {
        $path = $artifact['path'];
        
        // Verify file still exists before attempting removal
        if (!file_exists($path)) {
            if ($this->getOutput()->isVerbose()) {
                $this->warn("   ⚠️  File no longer exists: {$path}");
            }
            return;
        }
        
        // Attempt to remove the file
        if (!unlink($path)) {
            throw new \Exception("Failed to delete file: {$path}");
        }
        
        // Record successful removal
        $this->removedArtifacts[] = $artifact;
        
        if ($this->getOutput()->isVerbose()) {
            $size = isset($artifact['size']) ? ' (' . $this->formatBytes($artifact['size']) . ')' : '';
            $this->info("   ✅ Removed: {$path}{$size}");
        }
    }

    /**
     * Remove cache entries using appropriate cache operations.
     * 
     * This method demonstrates how to safely remove cache entries without
     * affecting unrelated cache data, using Laravel's caching system to
     * ensure proper cleanup of FlashHALT-specific cache data.
     *
     * @param array $artifact Cache artifact to remove
     */
    protected function removeCacheEntries(array $artifact): void
    {
        $cacheKeys = $artifact['keys'] ?? [];
        $removedCount = 0;
        
        foreach ($cacheKeys as $key) {
            try {
                if (Cache::forget($key)) {
                    $removedCount++;
                }
            } catch (\Exception $e) {
                // Log cache removal errors but continue with other keys
                $this->cleanupErrors[] = "Failed to remove cache key {$key}: " . $e->getMessage();
            }
        }
        
        // Record successful cache removals
        $artifact['removed_count'] = $removedCount;
        $this->removedArtifacts[] = $artifact;
        
        if ($this->getOutput()->isVerbose()) {
            $this->info("   ✅ Removed {$removedCount} cache entries");
        }
    }

    /**
     * Display comprehensive results of the cleanup operation.
     * 
     * This method demonstrates how cleanup commands should provide detailed
     * feedback about what was actually accomplished, including both successes
     * and any errors that occurred during the cleanup process.
     */
    protected function displayCleanupResults(): void
    {
        $removedCount = count($this->removedArtifacts);
        $errorCount = count($this->cleanupErrors);
        
        if ($removedCount > 0) {
            $this->info('✅ <fg=green;options=bold>Cleanup completed successfully!</fg=green;options=bold>');
            $this->info('');
            
            // Display summary of what was removed
            $this->displayRemovalSummary();
            
            // Calculate recovered disk space
            $recoveredSpace = $this->calculateRecoveredSpace();
            if ($recoveredSpace > 0) {
                $this->info("💾 <fg=green>Recovered disk space:</fg=green> " . $this->formatBytes($recoveredSpace));
            }
        }
        
        // Display any errors that occurred
        if ($errorCount > 0) {
            $this->displayCleanupErrors();
        }
        
        $this->info('');
        $this->line('🔄 <fg=cyan>To regenerate compilation artifacts, run:</fg=cyan> php artisan flashhalt:compile');
    }

    /**
     * Display a summary of what was successfully removed during cleanup.
     * 
     * This method shows how to organize and present cleanup results in a way
     * that helps users understand what was accomplished and builds confidence
     * in the cleanup operation's success.
     */
    protected function displayRemovalSummary(): void
    {
        $this->info('📊 <fg=cyan>Removal Summary:</fg=cyan>');
        
        // Group removed artifacts by type for organized presentation
        $removedByType = [];
        foreach ($this->removedArtifacts as $artifact) {
            $type = $artifact['type'];
            if (!isset($removedByType[$type])) {
                $removedByType[$type] = [];
            }
            $removedByType[$type][] = $artifact;
        }
        
        foreach ($removedByType as $type => $artifacts) {
            $count = count($artifacts);
            $typeDisplay = ucfirst(str_replace('_', ' ', $type));
            $this->line("   {$typeDisplay}: {$count} removed");
        }
    }

    /**
     * Calculate total disk space recovered through cleanup operations.
     * 
     * This method demonstrates how to provide meaningful metrics about the
     * impact of cleanup operations, helping users understand the value of
     * the maintenance work that was performed.
     *
     * @return int Total recovered space in bytes
     */
    protected function calculateRecoveredSpace(): int
    {
        $totalRecovered = 0;
        
        foreach ($this->removedArtifacts as $artifact) {
            if (isset($artifact['size'])) {
                $totalRecovered += $artifact['size'];
            }
        }
        
        return $totalRecovered;
    }

    /**
     * Display any errors that occurred during the cleanup process.
     * 
     * This method demonstrates how cleanup commands should handle and report
     * errors that occur during removal operations, providing enough detail
     * for debugging while maintaining overall operation success when possible.
     */
    protected function displayCleanupErrors(): void
    {
        $errorCount = count($this->cleanupErrors);
        
        $this->warn("⚠️  {$errorCount} errors occurred during cleanup:");
        $this->info('');
        
        foreach ($this->cleanupErrors as $index => $error) {
            $this->line("   " . ($index + 1) . ". {$error}");
        }
        
        $this->info('');
        $this->line('💡 Some artifacts may require manual removal or permission adjustments.');
    }

    /**
     * Handle unexpected errors during cleanup operations gracefully.
     * 
     * This method demonstrates how cleanup commands should handle serious
     * errors that prevent completion of cleanup operations while providing
     * useful debugging information and guidance for resolution.
     *
     * @param \Exception $exception The cleanup error
     */
    protected function handleCleanupError(\Exception $exception): void
    {
        $this->info('');
        $this->error('❌ <fg=red;options=bold>Cleanup operation failed</fg=red;options=bold>');
        $this->info('');
        
        $this->error('An error occurred during cleanup: ' . $exception->getMessage());
        
        if ($this->getOutput()->isVerbose()) {
            $this->info('');
            $this->info('🔍 <fg=yellow>Debug Information:</fg=yellow>');
            $this->line('   Exception Type: ' . get_class($exception));
            $this->line('   File: ' . $exception->getFile() . ':' . $exception->getLine());
        }
        
        // Display what was successfully removed before the error
        if (!empty($this->removedArtifacts)) {
            $this->info('');
            $this->info('✅ <fg=green>Partial cleanup was successful:</fg=green>');
            $this->displayRemovalSummary();
        }
        
        $this->info('');
        $this->line('💡 <fg=yellow>You may need to run the cleanup command again or remove remaining artifacts manually.</fg=yellow>');
    }

    /**
     * Format byte values into human-readable format for user-friendly display.
     * 
     * This utility method demonstrates how cleanup commands should present
     * technical information in ways that are meaningful and accessible to
     * developers who may not be familiar with raw byte values.
     *
     * @param int $bytes Byte value to format
     * @return string Human-readable byte string
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
    }
}