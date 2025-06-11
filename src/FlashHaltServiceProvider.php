<?php

namespace DancyCodes\FlashHalt;

use DancyCodes\FlashHalt\Console\Commands\CompileCommand;
use DancyCodes\FlashHalt\Console\Commands\ClearCommand;
use DancyCodes\FlashHalt\Http\Middleware\FlashHaltMiddleware;
use DancyCodes\FlashHalt\Services\ControllerResolver;
use DancyCodes\FlashHalt\Services\RouteCompiler;
use DancyCodes\FlashHalt\Services\SecurityValidator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FlashHaltServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * 
     * This method is called first during Laravel's bootstrap process.
     * Here we bind our services to the container and merge configuration,
     * but we don't interact with other Laravel services yet.
     */
    public function register(): void
    {
        // Merge our package configuration with the application's configuration
        // This allows users to override our defaults while providing sensible defaults
        $this->mergeConfigFrom(
            __DIR__ . '/../config/flashhalt.php',
            'flashhalt'
        );

        // Register our core services as singletons in the service container
        // Singletons ensure that the same instance is used throughout the request lifecycle,
        // which is important for caching and performance optimization
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
     * This method is called after all service providers have been registered.
     * Here we can safely interact with other Laravel services and register
     * our middleware, commands, and routes.
     */
    public function boot(): void
    {
        // Register our middleware with Laravel's router
        $this->registerMiddleware();

        // Register our Artisan commands for compilation and management
        $this->registerCommands();

        // Set up route registration based on current mode
        $this->registerRoutes();

        // Publish configuration and assets for user customization
        $this->registerPublishing();

        // Register any additional services that need the full Laravel application
        $this->registerAdditionalServices();
    }

    /**
     * Register FlashHalt middleware with Laravel's routing system.
     * 
     * We register our middleware with an alias so it can be easily
     * referenced in route definitions and middleware groups.
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('flashhalt', FlashHaltMiddleware::class);
    }

    /**
     * Register Artisan commands for FlashHalt management.
     * 
     * These commands will be available when the package is installed,
     * allowing users to compile routes and manage FlashHalt features.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CompileCommand::class,
                ClearCommand::class,
            ]);
        }
    }

    /**
     * Register routes based on the current operating mode.
     * 
     * This is where FlashHalt's dual-mode architecture comes into play.
     * We determine whether to use dynamic resolution or compiled routes
     * based on the environment and configuration.
     */
    protected function registerRoutes(): void
    {
        $mode = $this->determineOperatingMode();

        if ($mode === 'development') {
            $this->registerDevelopmentRoutes();
        } elseif ($mode === 'production') {
            $this->registerProductionRoutes();
        }
    }

    /**
     * Determine the current operating mode for FlashHalt.
     * 
     * This method implements the intelligent mode detection logic
     * that makes FlashHalt automatically adapt to different environments.
     */
    protected function determineOperatingMode(): string
    {
        $config = $this->app['config']->get('flashhalt');

        // Check for explicit mode configuration first
        if (isset($config['mode']) && in_array($config['mode'], ['development', 'production'])) {
            return $config['mode'];
        }

        // Auto-detect based on environment and compiled routes existence
        if ($this->app->environment(['production', 'staging'])) {
            $compiledRoutesPath = $config['production']['compiled_routes_path'] ?? 
                base_path('routes/flashhalt-compiled.php');
            
            if (file_exists($compiledRoutesPath)) {
                return 'production';
            } else {
                // In production without compiled routes - this should trigger a warning
                $this->handleProductionWithoutCompilation();
                return 'production'; // Fail safe to production mode
            }
        }

        // Default to development mode for local/testing environments
        return 'development';
    }

    /**
     * Register routes for development mode.
     * 
     * In development mode, we register a catch-all route that will
     * be processed by our middleware for dynamic controller resolution.
     */
    protected function registerDevelopmentRoutes(): void
    {
        Route::middleware(['web', 'flashhalt'])
            ->prefix('hx')
            ->where(['route' => '.*@.*']) // Only match routes containing @ symbol (corrected syntax)
            ->group(function () {
                // This catch-all route will be processed by FlashHaltMiddleware
                Route::any('{route}', function () {
                    // The middleware will handle this before it reaches here
                    abort(404, 'FlashHalt route not properly processed');
                });
            });
    }

    /**
     * Register routes for production mode.
     * 
     * In production mode, we include the compiled routes file
     * which contains static route definitions for optimal performance.
     */
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

    /**
     * Handle the situation where we're in production but compiled routes don't exist.
     * 
     * This is a critical error that needs clear guidance for developers.
     */
    protected function handleProductionWithoutCompilation(): void
    {
        if ($this->app['config']->get('flashhalt.production.verification_required', true)) {
            throw new \RuntimeException(
                "🚫 FlashHalt is running in production mode but compiled routes are missing!\n" .
                "Please run: php artisan flashhalt:compile\n" .
                "For more information: https://flashhalt.dev/production"
            );
        }

        // Log a warning if verification is disabled
        if ($this->app->bound('log')) {
            $this->app['log']->warning(
                'FlashHalt is running in production without compiled routes. ' .
                'This may impact performance and security.'
            );
        }
    }

    /**
     * Register publishing options for configuration and assets.
     * 
     * This allows users to customize FlashHalt's behavior and
     * integrate it deeply with their applications.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish the configuration file
            $this->publishes([
                __DIR__ . '/../config/flashhalt.php' => config_path('flashhalt.php'),
            ], 'flashhalt-config');

            // Publish JavaScript assets for HTMX integration
            $this->publishes([
                __DIR__ . '/../resources/js' => public_path('vendor/flashhalt/js'),
            ], 'flashhalt-assets');

            // Publish views for error pages and debugging
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/flashhalt'),
            ], 'flashhalt-views');
        }
    }

    /**
     * Register additional services that require the full Laravel application.
     * 
     * Some services need to interact with other Laravel components
     * that may not be available during the register phase.
     */
    protected function registerAdditionalServices(): void
    {
        // Register view composers for debugging information in development
        if ($this->app->environment('local', 'development') && 
            $this->app['config']->get('flashhalt.development.debug_mode', true)) {
            
            $this->registerDebugServices();
        }

        // Register monitoring services if enabled
        if ($this->app['config']->get('flashhalt.monitoring.enabled', false)) {
            $this->registerMonitoringServices();
        }
    }

    /**
     * Register debugging services for development environments.
     */
    protected function registerDebugServices(): void
    {
        // Register view composers that add debugging information to views
        // This will be useful for developers to understand how FlashHalt is processing requests
        
        if ($this->app->bound('view')) {
            $this->app['view']->composer('*', function ($view) {
                if (request()->header('HX-Request') && 
                    request()->is('hx/*') && 
                    str_contains(request()->path(), '@')) {
                    
                    $view->with('flashhalt_debug', [
                        'route_pattern' => request()->route('route'),
                        'resolved_at' => now(),
                        'mode' => 'development'
                    ]);
                }
            });
        }
    }

    /**
     * Register monitoring and analytics services.
     */
    protected function registerMonitoringServices(): void
    {
        // Integration points for monitoring services like Telescope, Sentry, etc.
        // This provides observability into FlashHalt's operation in production
        
        // Future implementation will integrate with Laravel's event system
        // to provide metrics on route resolution performance, error rates, etc.
    }
}