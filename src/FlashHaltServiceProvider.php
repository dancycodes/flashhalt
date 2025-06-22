<?php

namespace DancyCodes\FlashHalt;

use DancyCodes\FlashHalt\Console\Commands\CompileCommand;
use DancyCodes\FlashHalt\Console\Commands\ClearCommand;
use DancyCodes\FlashHalt\Http\Middleware\FlashHaltMiddleware;
use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Services\RouteCompiler;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Routing\Controllers\HasMiddleware;

class FlashHaltServiceProvider extends ServiceProvider
{
    /**
     * Flag to track whether JavaScript assets have been published this request.
     * This prevents duplicate publishing and improves performance.
     */
    protected bool $assetsPublished = false;

    /**
     * Register any application services.
     * 
     * This method runs first during Laravel's bootstrap process.
     * We'll add JavaScript asset management services alongside your existing services.
     */
    public function register(): void
    {
        // Your existing service registrations remain unchanged
        $this->mergeConfigFrom(
            __DIR__ . '/../config/flashhalt.php',
            'flashhalt'
        );

        $this->app->singleton(ControllerResolver::class, function ($app) {
            return new ControllerResolver(
                $app[SecurityValidator::class],
                $app['cache.store'],
                $app['config']->get('flashhalt')
            );
        });

        $this->app->singleton(SecurityValidator::class, function ($app) {
            return new SecurityValidator(
                $app['config']->get('flashhalt.security', []),
                $app['cache.store']
            );
        });

        $this->app->singleton(RouteCompiler::class, function ($app) {
            return new RouteCompiler(
                $app[ControllerResolver::class],
                $app[SecurityValidator::class],
                $app['files'],
                $app['config']->get('flashhalt')
            );
        });
    }

    /**
     * Bootstrap any package services.
     * 
     * This method runs after all service providers are registered.
     * We'll add automatic JavaScript integration alongside your existing bootstrap logic.
     */
    public function boot(): void
    {
        // Your existing middleware registration
        $this->registerMiddleware();

        // Your existing command registration
        $this->registerCommands();

        // Your existing route registration
        $this->registerRoutes();

        // NEW: JavaScript asset management and integration
        $this->registerJavaScriptIntegration();

        // Your existing publishing capabilities
        $this->registerPublishing();

        // NEW: View composers for automatic script injection
        $this->registerViewComposers();

        // NEW: Blade directives for manual integration when needed
        $this->registerBladeDirectives();
    }

    /**
     * Register JavaScript integration capabilities.
     * 
     * This method sets up automatic asset publishing and configuration injection
     * that makes FlashHALT work seamlessly without developer intervention.
     */
    protected function registerJavaScriptIntegration(): void
    {
        // Only proceed if JavaScript integration is enabled
        if (!$this->isJavaScriptIntegrationEnabled()) {
            return;
        }

        // Automatically publish JavaScript assets if needed
        $this->ensureJavaScriptAssetsArePublished();

        // Set up automatic configuration injection
        $this->setupConfigurationInjection();

        // Register script inclusion detection
        $this->setupScriptInclusionLogic();
    }

    /**
     * Determine if JavaScript integration should be enabled.
     * 
     * This method demonstrates intelligent feature detection based on your
     * existing configuration system and application environment.
     */
    protected function isJavaScriptIntegrationEnabled(): bool
    {
        $config = $this->app['config']->get('flashhalt', []);

        // Check if JavaScript integration is explicitly disabled
        if (isset($config['integration']['javascript_enabled']) && 
            !$config['integration']['javascript_enabled']) {
            return false;
        }

        // Check if we're in an environment where JavaScript integration makes sense
        if ($this->app->runningInConsole() && !$this->app->runningUnitTests()) {
            return false; // Don't include JavaScript during console commands
        }

        // Enable JavaScript integration by default for web requests
        return true;
    }

    /**
     * Ensure JavaScript assets are published and available.
     * 
     * This method implements intelligent asset publishing that automatically
     * handles versioning, caching, and development vs production differences.
     */
    protected function ensureJavaScriptAssetsArePublished(): void
    {
        // Avoid duplicate publishing within the same request
        if ($this->assetsPublished) {
            return;
        }

        $publicPath = public_path('vendor/flashhalt/js');
        $sourcePath = __DIR__ . '/../resources/js';

        // Check if assets need to be published or updated
        if ($this->shouldPublishJavaScriptAssets($publicPath, $sourcePath)) {
            $this->publishJavaScriptAssets($sourcePath, $publicPath);
        }

        $this->assetsPublished = true;
    }

    /**
     * Determine if JavaScript assets need to be published.
     * 
     * This method implements intelligent detection that balances automatic updates
     * with performance considerations.
     */
    protected function shouldPublishJavaScriptAssets(string $publicPath, string $sourcePath): bool
    {
        // Always publish in development mode for hot reloading
        if ($this->app->environment('local', 'development')) {
            return !file_exists($publicPath . '/flashhalt.js') || 
                   $this->areSourceFilesNewer($sourcePath, $publicPath);
        }

        // In production, only publish if assets don't exist
        return !file_exists($publicPath . '/flashhalt.js') || 
               !file_exists($publicPath . '/flashhalt.min.js');
    }

    /**
     * Check if source files are newer than published assets.
     * 
     * This enables automatic updates during development when JavaScript files change.
     */
    protected function areSourceFilesNewer(string $sourcePath, string $publicPath): bool
    {
        if (!file_exists($sourcePath . '/flashhalt.js')) {
            return false;
        }

        $sourceTime = filemtime($sourcePath . '/flashhalt.js');
        $publicTime = file_exists($publicPath . '/flashhalt.js') ? 
                      filemtime($publicPath . '/flashhalt.js') : 0;

        return $sourceTime > $publicTime;
    }

    /**
     * Publish JavaScript assets to the application's public directory.
     * 
     * This method handles the actual file copying with error handling and
     * environment-specific optimizations.
     */
    protected function publishJavaScriptAssets(string $sourcePath, string $publicPath): void
    {
        try {
            // Ensure the destination directory exists
            if (!is_dir($publicPath)) {
                mkdir($publicPath, 0755, true);
            }

            // Copy the main JavaScript file
            if (file_exists($sourcePath . '/flashhalt.js')) {
                copy($sourcePath . '/flashhalt.js', $publicPath . '/flashhalt.js');
            }

            // Copy or generate the minified version for production
            if (file_exists($sourcePath . '/flashhalt.min.js')) {
                copy($sourcePath . '/flashhalt.min.js', $publicPath . '/flashhalt.min.js');
            } else {
                // In a real implementation, you might want to minify on-the-fly
                // For now, we'll copy the regular version as a fallback
                copy($sourcePath . '/flashhalt.js', $publicPath . '/flashhalt.min.js');
            }

        } catch (\Exception $e) {
            // Log asset publishing errors but don't break the application
            if ($this->app->bound('log')) {
                $this->app['log']->warning(
                    'FlashHALT failed to publish JavaScript assets: ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Set up automatic configuration injection into JavaScript.
     * 
     * This method creates the bridge between your PHP configuration system
     * and the JavaScript frontend integration.
     */
    protected function setupConfigurationInjection(): void
    {
        // Register a view composer that injects FlashHALT configuration
        View::composer('*', function ($view) {
            // Only inject configuration if FlashHALT JavaScript will be included
            if ($this->shouldIncludeJavaScript()) {
                $config = $this->generateJavaScriptConfiguration();
                $view->with('flashhaltJsConfig', $config);
            }
        });
    }

    /**
     * Generate configuration object for JavaScript consumption.
     * 
     * This method transforms your PHP configuration into a format suitable
     * for JavaScript while maintaining security boundaries.
     */
    protected function generateJavaScriptConfiguration(): array
    {
        $config = $this->app['config']->get('flashhalt', []);

        // Create a filtered configuration object for JavaScript
        return [
            'enabled' => true,
            'debug' => $this->app->environment('local', 'development') && 
                      ($config['development']['debug_mode'] ?? false),
            'version' => $this->getPackageVersion(),
            'csrfTokenRefreshInterval' => 300000, // 5 minutes
            'logLevel' => $this->getJavaScriptLogLevel($config),
            'errorReporting' => $config['monitoring']['enabled'] ?? false,
            'routePattern' => '^hx\/.*@.*$', // JavaScript-compatible regex
        ];
    }

    /**
     * Determine appropriate log level for JavaScript based on environment.
     */
    protected function getJavaScriptLogLevel(array $config): string
    {
        if ($this->app->environment('local', 'development')) {
            return $config['development']['debug_mode'] ?? false ? 'debug' : 'info';
        }

        return 'error'; // Production should only log errors
    }

    /**
     * Get the current package version for JavaScript integration.
     */
    protected function getPackageVersion(): string
    {
        // In a real implementation, this might read from composer.json
        return '1.0.0';
    }

    /**
     * Set up logic for determining when to include JavaScript.
     * 
     * This method implements intelligent detection of when FlashHALT JavaScript
     * is needed, avoiding unnecessary asset loading.
     */
    protected function setupScriptInclusionLogic(): void
    {
        // Register a macro on the view factory for checking FlashHALT usage
        View::macro('usesFlashHALT', function () {
            // This would ideally scan the view content for FlashHALT patterns
            // For now, we'll use a simple heuristic
            return request()->is('*') && $this->shouldIncludeJavaScript();
        });
    }

    /**
     * Determine if JavaScript should be included in the current response.
     * 
     * This method implements sophisticated logic for automatic script inclusion
     * based on request context and application state.
     */
    protected function shouldIncludeJavaScript(): bool
    {
        // Don't include JavaScript for non-web requests
        if (!request() || request()->wantsJson() || request()->expectsJson()) {
            return false;
        }

        // Always include in development mode for convenience
        if ($this->app->environment('local', 'development')) {
            return true;
        }

        // In production, be more selective
        // Check if the current request might involve FlashHALT routes
        return $this->requestMightUseFlashHALT();
    }

    /**
     * Determine if the current request context suggests FlashHALT usage.
     */
    protected function requestMightUseFlashHALT(): bool
    {
        $request = request();
        
        if (!$request) {
            return false;
        }

        // Check if this is an HTMX request to a FlashHALT route
        if ($request->header('HX-Request') && $request->is('hx/*')) {
            return true;
        }

        // Check if the current route might render views that use FlashHALT
        // This is a heuristic - in a real implementation, you might want
        // to scan view files or maintain a registry of FlashHALT-enabled routes
        return true; // Conservative approach - include when in doubt
    }

    /**
     * Register view composers for automatic script injection.
     * 
     * This method sets up automatic injection of FlashHALT JavaScript
     * into application layouts and views.
     */
    protected function registerViewComposers(): void
    {
        // Register a view composer for common layout files
        $layoutPatterns = [
            'layouts.app',
            'layouts.master', 
            'layouts.main',
            'app',
            'layout'
        ];

        View::composer($layoutPatterns, function ($view) {
            if ($this->shouldIncludeJavaScript()) {
                $this->injectJavaScriptIntoView($view);
            }
        });

        // Also register a global composer as a fallback
        View::composer('*', function ($view) {
            // Only inject if not already injected and JavaScript is needed
            if ($this->shouldIncludeJavaScript() && !$view->offsetExists('flashhaltScriptsInjected')) {
                $this->injectJavaScriptIntoView($view);
            }
        });
    }

    /**
     * Inject FlashHALT JavaScript into a view.
     * 
     * This method adds the necessary script tags and configuration
     * to make FlashHALT work automatically.
     */
    protected function injectJavaScriptIntoView($view): void
    {
        $config = $this->generateJavaScriptConfiguration();
        $scriptPath = $this->getJavaScriptAssetPath();

        // Generate the configuration injection script
        $configScript = '<script>window.FlashHALTConfig = ' . json_encode($config) . ';</script>';

        // Generate the main script inclusion
        $mainScript = '<script src="' . asset($scriptPath) . '"></script>';

        // Combine both scripts
        $fullScript = $configScript . "\n" . $mainScript;

        // Add to view data
        $view->with([
            'flashhaltScripts' => $fullScript,
            'flashhaltScriptsInjected' => true
        ]);
    }

    /**
     * Get the appropriate JavaScript asset path based on environment.
     */
    protected function getJavaScriptAssetPath(): string
    {
        $basePath = 'vendor/flashhalt/js/flashhalt';
        
        // Use minified version in production
        if ($this->app->environment('production')) {
            return $basePath . '.min.js';
        }

        return $basePath . '.js';
    }

    /**
     * Register Blade directives for manual JavaScript integration.
     * 
     * These directives allow developers to manually control FlashHALT JavaScript
     * inclusion when the automatic system doesn't meet their needs.
     */
    protected function registerBladeDirectives(): void
    {
        // Directive to include FlashHALT scripts manually
        Blade::directive('flashhaltScripts', function () {
            return '<?php if(isset($flashhaltScripts)) echo $flashhaltScripts; ?>';
        });

        // Directive to check if FlashHALT is available
        Blade::directive('flashhaltEnabled', function () {
            return '<?php if(' . static::class . '::isFlashHALTEnabled()): ?>';
        });

        Blade::directive('endflashhalt', function () {
            return '<?php endif; ?>';
        });

        // Directive for CSRF meta tag (ensures compatibility)
        Blade::directive('flashhaltCsrf', function () {
            return '<meta name="csrf-token" content="<?php echo csrf_token(); ?>">';
        });
    }

    /**
     * Static method for checking FlashHALT availability in Blade templates.
     */
    public static function isFlashHALTEnabled(): bool
    {
        return app()->bound('flashhalt.enabled') && app('flashhalt.enabled');
    }

    /**
     * Register publishing capabilities for manual asset management.
     * 
     * This extends your existing publishing system to include JavaScript assets.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration files (your existing functionality)
            $this->publishes([
                __DIR__ . '/../config/flashhalt.php' => config_path('flashhalt.php'),
            ], 'flashhalt-config');

            // NEW: Publish JavaScript assets for manual management
            $this->publishes([
                __DIR__ . '/../resources/js' => public_path('vendor/flashhalt/js'),
            ], 'flashhalt-assets');

            // NEW: Publish both config and assets together
            $this->publishes([
                __DIR__ . '/../config/flashhalt.php' => config_path('flashhalt.php'),
                __DIR__ . '/../resources/js' => public_path('vendor/flashhalt/js'),
            ], 'flashhalt');
        }
    }

    // Your existing methods remain unchanged
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('flashhalt', FlashHaltMiddleware::class);
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CompileCommand::class,
                ClearCommand::class,
            ]);
        }
    }

    protected function registerRoutes(): void
    {
        $mode = $this->determineOperatingMode();

        if ($mode === 'development') {
            $this->registerDevelopmentRoutes();
        } elseif ($mode === 'production') {
            $this->registerProductionRoutes();
        }
    }

    // Your existing route registration methods remain unchanged
    protected function determineOperatingMode(): string
    {
        $config = $this->app['config']->get('flashhalt');

        if (isset($config['mode']) && in_array($config['mode'], ['development', 'production'])) {
            return $config['mode'];
        }

        if ($this->app->environment(['production', 'staging'])) {
            $compiledRoutesPath = $config['production']['compiled_routes_path'] ?? 
                base_path('routes/flashhalt-compiled.php');
            
            if (file_exists($compiledRoutesPath)) {
                return 'production';
            } else {
                $this->handleProductionWithoutCompilation();
                return 'production';
            }
        }

        return 'development';
    }

    protected function registerDevelopmentRoutes(): void
    {
        // Route::middleware(['web', 'flashhalt'])
        //     ->prefix('hx')
        //     ->where(['route' => '.*@.*'])
        //     ->group(function () {
        //         Route::any('{route}', function () {
        //             abort(404, 'FlashHalt route not properly processed');
        //         });
        //     });

        // Register the base route (temporary closure)
        Route::middleware('web')->any('hx/{route}', function () {
            // This will never execute - we change the action in RouteMatched!
            throw new \Exception('FlashHalt route action should have been replaced');
        })->where('route', '.*@.*');
        
        // MAGIC: Change route action to proper controller action in RouteMatched event
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            $route = $event->route;
            
            // Only process FlashHalt routes
            if (!str_starts_with($route->uri(), 'hx/')) {
                return;
            }
            
            // Get the route parameter and parse it
            $routeParam = $route->parameter('route');
            if (!$routeParam || !str_contains($routeParam, '@')) {
                return;
            }
            
            [$controllerPath, $method] = explode('@', $routeParam);
            $controllerClass = $this->resolveControllerClass($controllerPath);
            
            // Verify controller exists
            if (!class_exists($controllerClass)) {
                return;
            }
            
            // HERE'S THE MAGIC: Change the route action to proper controller action!
            $currentAction = $route->getAction();
            $newAction = array_merge($currentAction, [
                'uses' => $controllerClass . '@' . $method,
                'controller' => $controllerClass . '@' . $method,
            ]);
            
            // THIS MAKES LARAVEL TREAT IT AS A NATIVE CONTROLLER ROUTE!
            $route->setAction($newAction);
        });
    }

    protected function resolveControllerClass(string $path): string
    {
        // Convert "admin.users" to "App\Http\Controllers\Admin\UsersController"
        $parts = explode('.', $path);
        $namespace = 'App\\Http\\Controllers';
        
        foreach ($parts as $part) {
            // Convert kebab-case to PascalCase: "user-profile" -> "UserProfile"
            $className = str_replace('-', '', ucwords($part, '-'));
            $namespace .= '\\' . $className;
        }
        
        return $namespace . 'Controller';
    }

    protected function registerProductionRoutes(): void
    {
        $compiledRoutesPath = $this->app['config']->get(
            'flashhalt.production.compiled_routes_path',
            base_path('routes/flashhalt-compiled.php')
        );

        if (file_exists($compiledRoutesPath)) {
            require $compiledRoutesPath;
        }
    }

    protected function handleProductionWithoutCompilation(): void
    {
        if ($this->app['config']->get('flashhalt.production.verification_required', true)) {
            throw new \RuntimeException(
                "🚫 FlashHALT is running in production mode but compiled routes are missing!\n" .
                "Please run: php artisan flashhalt:compile\n" .
                "For more information: https://flashhalt.dev/production"
            );
        }

        if ($this->app->bound('log')) {
            $this->app['log']->warning(
                'FlashHALT is running in production without compiled routes. ' .
                'This may impact performance and security.'
            );
        }
    }
}