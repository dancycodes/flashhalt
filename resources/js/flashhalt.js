/**
 * FlashHALT Frontend Integration
 *
 * This script provides automatic CSRF protection and debugging capabilities
 * for FlashHALT dynamic routes. It integrates seamlessly with Laravel's
 * existing security infrastructure and HTMX's event system.
 *
 * Architecture Overview:
 * - Uses HTMX's htmx:configRequest event for request interception
 * - Leverages Laravel's standard CSRF token mechanisms
 * - Provides development debugging without production overhead
 * - Implements enterprise-grade error handling and logging
 */

(function (window, document) {
  "use strict";

  /**
   * FlashHALT class encapsulates all frontend integration functionality.
   * Using a class pattern provides clean encapsulation and prevents
   * global namespace pollution while making the code testable and maintainable.
   */
  class FlashHALT {
    constructor() {
      // Configuration will be injected by Laravel during asset publishing
      this.config = window.FlashHALTConfig || this.getDefaultConfig();

      // Performance and debugging metrics
      this.stats = {
        requestsProcessed: 0,
        csrfTokensInjected: 0,
        errors: 0,
        startTime: Date.now(),
      };

      // Cache for frequently accessed DOM elements and tokens
      this.cache = {
        csrfToken: null,
        csrfMeta: null,
        lastTokenRefresh: 0,
      };

      // Initialize the integration
      this.initialize();
    }

    /**
     * Get default configuration when Laravel config isn't available.
     * This ensures the script works even if configuration injection fails.
     */
    getDefaultConfig() {
      return {
        enabled: true,
        debug: false,
        csrfTokenRefreshInterval: 300000, // 5 minutes
        routePattern: /^hx\/.*@.*$/,
        logLevel: "error",
      };
    }

    /**
     * Initialize the FlashHALT frontend integration.
     * This method sets up all event listeners and prepares the system
     * for handling HTMX requests automatically.
     */
    initialize() {
      if (!this.config.enabled) {
        this.log("FlashHALT frontend integration is disabled", "info");
        return;
      }

      // Verify HTMX is available before proceeding
      if (typeof htmx === "undefined") {
        this.log(
          "HTMX not found - FlashHALT integration requires HTMX",
          "warn"
        );
        return;
      }

      this.log("Initializing FlashHALT frontend integration", "info");

      // Set up the main request interception handler
      this.setupRequestInterception();

      // Set up debugging features if enabled
      if (this.config.debug) {
        this.setupDebugFeatures();
      }

      // Set up error handling and monitoring
      this.setupErrorHandling();

      // Cache the initial CSRF token for performance
      this.refreshCsrfToken();

      this.log(
        "FlashHALT frontend integration initialized successfully",
        "info"
      );
    }

    /**
     * Set up HTMX request interception for automatic CSRF token injection.
     * This is the core functionality that makes FlashHALT routes work
     * seamlessly with Laravel's CSRF protection.
     */
    setupRequestInterception() {
      // Use HTMX's configRequest event to intercept requests before they're sent
      document.body.addEventListener("htmx:configRequest", (event) => {
        try {
          this.handleConfigRequest(event);
        } catch (error) {
          this.handleError("Request configuration failed", error);
        }
      });

      // Also listen for response events to update debugging information
      if (this.config.debug) {
        document.body.addEventListener("htmx:responseError", (event) => {
          this.handleResponseError(event);
        });
      }
    }

    /**
     * Handle HTMX request configuration to inject CSRF tokens for FlashHALT routes.
     * This method implements the core security integration with surgical precision.
     */
    handleConfigRequest(event) {
      const detail = event.detail;
      const path = detail.path;

      // Update request processing statistics
      this.stats.requestsProcessed++;

      // Only process requests that match FlashHALT's route pattern
      if (!this.isFlashHALTRoute(path)) {
        return; // Exit early for non-FlashHALT requests
      }

      this.log(`Processing FlashHALT route: ${path}`, "debug");

      // Only inject CSRF tokens for state-changing HTTP methods
      const verb = detail.verb.toLowerCase();
      if (verb === "get") {
        this.log(`Skipping CSRF injection for GET request: ${path}`, "debug");
        return;
      }

      // Get the current CSRF token
      const csrfToken = this.getCsrfToken();
      if (!csrfToken) {
        this.log(
          "CSRF token not available - request will proceed without token",
          "warn"
        );
        return;
      }

      // Inject the CSRF token using Laravel's standard header
      detail.headers["X-CSRF-TOKEN"] = csrfToken;
      this.stats.csrfTokensInjected++;

      this.log(
        `CSRF token injected for ${verb.toUpperCase()} request to ${path}`,
        "debug"
      );

      // Add debugging information in development mode
      if (this.config.debug) {
        detail.headers["X-FlashHALT-Debug"] = "true";
        detail.headers["X-FlashHALT-Version"] = this.getVersion();
      }
    }

    /**
     * Determine if a request path matches FlashHALT's routing pattern.
     * This method implements precise pattern matching to ensure we only
     * process actual FlashHALT routes.
     */
    isFlashHALTRoute(path) {
      // Remove leading slash and query parameters for clean matching
      const cleanPath = path.replace(/^\/+/, "").split("?")[0];

      // Match against FlashHALT's specific pattern: hx/controller@method
      return this.config.routePattern.test(cleanPath);
    }

    /**
     * Get the current CSRF token using Laravel's standard mechanisms.
     * This method implements intelligent caching and refresh logic for optimal performance.
     */
    getCsrfToken() {
      const now = Date.now();
      console.log("run");

      // Return cached token if it's still fresh
      if (
        this.cache.csrfToken &&
        now - this.cache.lastTokenRefresh < this.config.csrfTokenRefreshInterval
      ) {
        return this.cache.csrfToken;
      }

      // Refresh the token from DOM
      this.refreshCsrfToken();
      return this.cache.csrfToken;
    }

    /**
     * Refresh the CSRF token from Laravel's standard meta tag.
     * This method implements Laravel's recommended CSRF token access pattern.
     */
    refreshCsrfToken() {
      try {
        // Use cached meta element or find it in DOM
        if (!this.cache.csrfMeta) {
          this.cache.csrfMeta = document.querySelector(
            'meta[name="csrf-token"]'
          );
        }

        if (this.cache.csrfMeta) {
          this.cache.csrfToken = this.cache.csrfMeta.getAttribute("content");
          this.cache.lastTokenRefresh = Date.now();
          this.log("CSRF token refreshed from meta tag", "debug");
        } else {
          this.cache.csrfToken = null;
          this.log(
            'CSRF meta tag not found - ensure <meta name="csrf-token"> is present',
            "warn"
          );
        }
      } catch (error) {
        this.cache.csrfToken = null;
        this.log("Failed to refresh CSRF token", "error", error);
      }
    }

    /**
     * Set up debugging features for development environments.
     * These features provide detailed insights into FlashHALT's operation
     * without impacting production performance.
     */
    setupDebugFeatures() {
      // Add FlashHALT debug panel to DOM
      this.createDebugPanel();

      // Set up console logging for FlashHALT events
      this.setupConsoleLogging();

      // Add global debugging helpers
      window.FlashHALTDebug = {
        getStats: () => this.getStats(),
        getConfig: () => this.config,
        refreshToken: () => this.refreshCsrfToken(),
        testRoute: (path) => this.isFlashHALTRoute(path),
      };

      this.log(
        "Debug features enabled - use window.FlashHALTDebug for debugging",
        "info"
      );
    }

    /**
     * Create a debugging panel for development environments.
     * This provides real-time visibility into FlashHALT's operation.
     */
    createDebugPanel() {
      // Only create panel if it doesn't already exist
      if (document.getElementById("flashhalt-debug-panel")) {
        return;
      }

      const panel = document.createElement("div");
      panel.id = "flashhalt-debug-panel";
      panel.style.cssText = `
                position: fixed;
                top: 10px;
                right: 10px;
                width: 300px;
                max-height: 400px;
                background: rgba(0, 0, 0, 0.9);
                color: #00ff00;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                padding: 10px;
                border-radius: 5px;
                z-index: 10000;
                overflow-y: auto;
                display: none;
            `;

      panel.innerHTML = `
                <div style="font-weight: bold; margin-bottom: 10px;">
                    FlashHALT Debug Panel
                    <button onclick="this.parentElement.parentElement.style.display='none'" style="float: right; background: red; color: white; border: none; padding: 2px 5px; cursor: pointer;">×</button>
                </div>
                <div id="flashhalt-debug-content"></div>
            `;

      document.body.appendChild(panel);

      // Add keyboard shortcut to toggle panel (Ctrl+Shift+F)
      document.addEventListener("keydown", (event) => {
        if (event.ctrlKey && event.shiftKey && event.key === "F") {
          const panel = document.getElementById("flashhalt-debug-panel");
          if (panel) {
            panel.style.display =
              panel.style.display === "none" ? "block" : "none";
            if (panel.style.display === "block") {
              this.updateDebugPanel();
            }
          }
        }
      });
    }

    /**
     * Update the debug panel with current statistics and information.
     */
    updateDebugPanel() {
      const content = document.getElementById("flashhalt-debug-content");
      if (!content) return;

      const stats = this.getStats();
      const uptime = Math.floor((Date.now() - this.stats.startTime) / 1000);

      content.innerHTML = `
                <div><strong>Status:</strong> Active</div>
                <div><strong>Uptime:</strong> ${uptime}s</div>
                <div><strong>Requests Processed:</strong> ${
                  stats.requestsProcessed
                }</div>
                <div><strong>CSRF Tokens Injected:</strong> ${
                  stats.csrfTokensInjected
                }</div>
                <div><strong>Errors:</strong> ${stats.errors}</div>
                <div><strong>Current CSRF Token:</strong> ${
                  this.cache.csrfToken ? "✓ Available" : "✗ Missing"
                }</div>
                <div style="margin-top: 10px; font-size: 10px;">
                    Press Ctrl+Shift+F to toggle this panel
                </div>
            `;
    }

    /**
     * Set up console logging for debugging purposes.
     */
    setupConsoleLogging() {
      // Override the log method to also output to console in debug mode
      const originalLog = this.log.bind(this);
      this.log = (message, level = "info", error = null) => {
        originalLog(message, level, error);

        if (this.config.debug && console) {
          const timestamp = new Date().toISOString();
          const prefix = `[FlashHALT ${timestamp}]`;

          switch (level) {
            case "error":
              console.error(prefix, message, error);
              break;
            case "warn":
              console.warn(prefix, message);
              break;
            case "debug":
              console.debug(prefix, message);
              break;
            default:
              console.log(prefix, message);
          }
        }
      };
    }

    /**
     * Set up error handling and monitoring for the integration.
     */
    setupErrorHandling() {
      // Global error handler for FlashHALT-related errors
      window.addEventListener("error", (event) => {
        if (event.filename && event.filename.includes("flashhalt")) {
          this.handleError("Global JavaScript error in FlashHALT", event.error);
        }
      });

      // Unhandled promise rejection handler
      window.addEventListener("unhandledrejection", (event) => {
        if (
          event.reason &&
          event.reason.message &&
          event.reason.message.includes("FlashHALT")
        ) {
          this.handleError(
            "Unhandled promise rejection in FlashHALT",
            event.reason
          );
        }
      });
    }

    /**
     * Handle HTMX response errors to provide better debugging information.
     */
    handleResponseError(event) {
      const detail = event.detail;

      // Check if this was a FlashHALT route that failed
      if (this.isFlashHALTRoute(detail.pathInfo.requestPath)) {
        this.log(
          `FlashHALT route error: ${detail.pathInfo.requestPath} returned ${detail.xhr.status}`,
          "error"
        );

        // Check for CSRF-related errors
        if (detail.xhr.status === 419 || detail.xhr.status === 403) {
          this.log("Possible CSRF token issue - check token validity", "warn");
          this.refreshCsrfToken(); // Refresh token for next request
        }
      }
    }

    /**
     * Generic error handler for FlashHALT operations.
     */
    handleError(message, error) {
      this.stats.errors++;
      this.log(message, "error", error);

      // In production, send errors to monitoring service
      if (!this.config.debug && this.config.errorReporting) {
        this.reportError(message, error);
      }
    }

    /**
     * Report errors to external monitoring service in production.
     */
    reportError(message, error) {
      // This would integrate with services like Sentry, Bugsnag, etc.
      // Implementation depends on the monitoring service being used
      try {
        if (window.Sentry) {
          window.Sentry.captureException(error, {
            tags: { component: "FlashHALT" },
            extra: { message },
          });
        }
      } catch (reportingError) {
        // Silently fail error reporting to avoid cascading errors
      }
    }

    /**
     * Internal logging method with level filtering.
     */
    log(message, level = "info", error = null) {
      // Implement log level filtering based on configuration
      const levels = ["debug", "info", "warn", "error"];
      const configLevel = levels.indexOf(this.config.logLevel);
      const messageLevel = levels.indexOf(level);

      if (messageLevel >= configLevel) {
        // Store log message for debugging panel
        if (!this.logBuffer) this.logBuffer = [];
        this.logBuffer.push({ timestamp: Date.now(), message, level, error });

        // Keep only last 100 log entries
        if (this.logBuffer.length > 100) {
          this.logBuffer.shift();
        }
      }
    }

    /**
     * Get current performance and usage statistics.
     */
    getStats() {
      return {
        ...this.stats,
        uptime: Date.now() - this.stats.startTime,
        csrfTokenCached: !!this.cache.csrfToken,
        lastTokenRefresh: this.cache.lastTokenRefresh,
      };
    }

    /**
     * Get FlashHALT integration version.
     */
    getVersion() {
      return this.config.version || "1.0.0";
    }
  }

  /**
   * Initialize FlashHALT when DOM is ready.
   * We use different initialization strategies based on document ready state
   * to ensure the integration works regardless of when the script loads.
   */
  function initializeFlashHALT() {
    // Ensure we only initialize once
    if (window.FlashHALTInstance) {
      return;
    }

    window.FlashHALTInstance = new FlashHALT();
  }

  // Initialize based on current document state
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeFlashHALT);
  } else {
    // DOM is already loaded
    initializeFlashHALT();
  }

  // Export FlashHALT class for advanced usage
  window.FlashHALT = FlashHALT;
})(window, document);
