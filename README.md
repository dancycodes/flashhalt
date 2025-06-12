# FlashHALT

> **FlashHALT eliminates route definition overhead for HTMX applications, enabling direct controller method access through intuitive URL conventions - delivering 10x faster development velocity while maintaining Laravel's architectural integrity.**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dancycodes/flashhalt.svg?style=flat-square)](https://packagist.org/packages/dancycodes/flashhalt)
[![Total Downloads](https://img.shields.io/packagist/dt/dancycodes/flashhalt.svg?style=flat-square)](https://packagist.org/packages/dancycodes/flashhalt)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

FlashHALT is a Laravel package that transforms how you build HTMX applications by introducing convention-based routing patterns. Instead of defining individual routes for every HTMX interaction, you can access controller methods directly through intuitive URL patterns.

## Table of Contents

- [What Makes FlashHALT Different](#what-makes-flashhalt-different)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Request Parameters](#request-parameters)
- [Blade Directives](#blade-directives)
- [Configuration](#configuration)
- [Console Commands](#console-commands)
- [Security](#security)
- [Performance](#performance)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Development Status](#development-status)
- [Contributing](#contributing)
- [License](#license)

## What Makes FlashHALT Different

Traditional Laravel HTMX development requires you to define a route for every endpoint:

```php
// Traditional approach - verbose and repetitive
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{user}', [UserController::class, 'show']);
Route::put('/users/{user}', [UserController::class, 'update']);
Route::delete('/users/{user}', [UserController::class, 'destroy']);
Route::get('/admin/users', [Admin\UserController::class, 'index']);
// ... dozens more routes
```

With FlashHALT, you replace all of this with convention-based URLs:

```html
<!-- FlashHALT approach - intuitive and streamlined -->
<button hx-get="hx/users@index" hx-target="#content">Load Users</button>
<form hx-post="hx/users@store" hx-target="#users-list">
  <!-- form fields -->
</form>
<button hx-put="hx/users@update" hx-target="#user-123">Update User</button>
<button hx-delete="hx/users@destroy" hx-target="#user-123">Delete User</button>
<div hx-get="hx/admin.users@index" hx-trigger="load">Admin Users</div>
```

FlashHALT's intelligent controller resolution system dynamically maps these patterns to your existing Laravel controllers while maintaining full security, performance, and Laravel ecosystem compatibility.

## Installation

Install FlashHALT via Composer:

```bash
composer require dancycodes/flashhalt
```

The package will be auto-discovered by Laravel. Publish the configuration file:

```bash
php artisan vendor:publish --provider="DancyCodes\FlashHalt\FlashHaltServiceProvider" --tag="config"
```

This creates `config/flashhalt.php` where you can customize FlashHALT's behavior.

### HTMX Integration

FlashHALT is designed to work seamlessly with HTMX. Install HTMX in your Laravel application:

```bash
npm install htmx.org
```

Add HTMX to your `resources/js/app.js`:

```javascript
import "htmx.org";
window.htmx = require("htmx.org");
```

Or include it via CDN in your layout:

```html
<script src="https://unpkg.com/htmx.org@1.9.8"></script>
```

### Recommended: Maurizio Laravel HTMX Package

For enhanced HTMX functionality, we strongly recommend installing the excellent Maurizio Laravel HTMX package alongside FlashHALT:

```bash
composer require mauricius/laravel-htmx
```

This package provides powerful HTMX request and response helpers that complement FlashHALT perfectly:

```php
use Mauricius\LaravelHtmx\Http\HtmxRequest;
use Mauricius\LaravelHtmx\Http\HtmxResponse;

class UserController extends Controller
{
    public function index(HtmxRequest $request)
    {
        $users = User::all();

        if ($request->isHtmxRequest()) {
            return view('users.index-partial', compact('users'));
        }

        return view('users.index', compact('users'));
    }

    public function store(HtmxRequest $request)
    {
        // Handle user creation...

        return with(new HtmxResponse())
            ->addTrigger("userCreated")
            ->pushUrl("/users");
    }
}
```

## Quick Start

After installation, FlashHALT works immediately with zero configuration. Create a simple controller:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $user = User::create($request->validated());
        return view('users.show', compact('user'));
    }

    public function show(User $user)
    {
        return view('users.show', compact('user'));
    }
}
```

Now use FlashHALT in your Blade templates:

```html
<!DOCTYPE html>
<html>
  <head>
    <title>FlashHALT Demo</title>
    <script src="https://unpkg.com/htmx.org@1.9.8"></script>
    @flashhaltScripts
  </head>
  <body>
    <div id="content">
      <!-- Load users list on page load -->
      <div
        hx-get="hx/users@index"
        hx-trigger="load"
        hx-target="#users-container"
      >
        Loading users...
      </div>

      <!-- Create new user form -->
      <form hx-post="hx/users@store" hx-target="#users-container">
        @csrf
        <input type="text" name="name" placeholder="User name" required />
        <input type="email" name="email" placeholder="Email" required />
        <button type="submit">Create User</button>
      </form>

      <div id="users-container"></div>
    </div>
  </body>
</html>
```

That's it! FlashHALT automatically handles the routing, security validation, and controller method execution.

## Core Concepts

### Convention-Based URL Patterns

FlashHALT uses intuitive URL patterns that map directly to your Laravel controllers:

**Basic Pattern:** `hx/controller@method`

```html
<button hx-get="hx/users@index">Load Users</button>
<form hx-post="hx/users@store">Create User</form>
<button hx-put="hx/users@update">Update User</button>
<button hx-delete="hx/users@destroy">Delete User</button>
```

**Namespaced Controllers:** `hx/namespace.controller@method`

```html
<div hx-get="hx/admin.users@index">Admin Users</div>
<button hx-post="hx/api.v1.posts@store">Create Post</button>
<div hx-get="hx/billing.invoices@show">Show Invoice</div>
```

**Deep Namespaces:** Support for multiple namespace levels

```html
<div hx-get="hx/api.v2.admin.reports@generate">Generate Report</div>
<button hx-post="hx/dashboard.analytics.metrics@calculate">
  Calculate Metrics
</button>
```

### Controller Resolution Process

FlashHALT's controller resolution follows a sophisticated multi-step process:

1. **Pattern Analysis**: Parses the URL pattern to extract controller and method information
2. **Namespace Resolution**: Converts dot notation to proper PHP namespaces (admin.users → Admin\UsersController)
3. **Security Validation**: Ensures the target controller and method are safe to access
4. **Controller Instantiation**: Creates controller instances through Laravel's service container
5. **Method Execution**: Calls the controller method with proper parameter binding
6. **Response Processing**: Optimizes the response for HTMX compatibility

### Operating Modes

FlashHALT operates in two distinct modes:

**Development Mode** (default in local environments):

- Dynamic controller resolution for maximum flexibility
- Real-time route pattern validation
- Comprehensive error reporting with suggestions
- Performance monitoring and debugging tools

**Production Mode** (automatically activated in production):

- Pre-compiled static routes for maximum performance
- Enhanced security through static analysis
- Optimized caching and minimal overhead
- Robust error handling

## Request Parameters

FlashHALT seamlessly integrates with HTMX's parameter passing mechanisms. All request parameters should be sent using standard HTMX attributes.

### Using hx-vals for Simple Data

Pass simple key-value data using `hx-vals`:

```html
<!-- Static values -->
<button
  hx-post="hx/users@updateStatus"
  hx-vals='{"status": "active", "notify": true}'
  hx-target="#user-status"
>
  Activate User
</button>

<!-- Dynamic values with JavaScript -->
<button
  hx-post="hx/posts@toggleFavorite"
  hx-vals="js:{postId: getPostId(), timestamp: Date.now()}"
  hx-target="#favorite-btn"
>
  Toggle Favorite
</button>
```

```php
// Controller receives values normally
public function updateStatus(Request $request, User $user)
{
    $status = $request->input('status'); // 'active'
    $notify = $request->boolean('notify'); // true

    $user->update(['status' => $status]);

    if ($notify) {
        // Send notification...
    }

    return view('users.status', compact('user'));
}
```

### Using hx-include for Form Data

Include form data using `hx-include`:

```html
<form id="user-form">
  <input type="text" name="name" value="John Doe" />
  <input type="email" name="email" value="john@example.com" />
  <input type="hidden" name="department_id" value="5" />
</form>

<!-- Include entire form -->
<button
  hx-put="hx/users@update"
  hx-include="#user-form"
  hx-target="#user-details"
>
  Update User
</button>

<!-- Include closest form -->
<form>
  <input type="text" name="title" placeholder="Post title" />
  <textarea name="content" placeholder="Post content"></textarea>
  <button
    hx-post="hx/posts@store"
    hx-include="closest form"
    hx-target="#posts-list"
  >
    Create Post
  </button>
</form>
```

### Using hx-params for Parameter Control

Control which parameters are sent using `hx-params`:

```html
<!-- Send all parameters (default) -->
<form hx-post="hx/users@store" hx-params="*">
  <input type="text" name="name" />
  <input type="email" name="email" />
  <input type="password" name="password" />
</form>

<!-- Send only specific parameters -->
<form hx-patch="hx/users@updateEmail" hx-params="email">
  <input type="text" name="name" value="John Doe" />
  <input type="email" name="email" value="new@example.com" />
  <!-- Only email will be sent -->
</form>
```

### CSRF Protection

FlashHALT automatically handles Laravel's CSRF protection when you use the included JavaScript:

```html
<meta name="csrf-token" content="{{ csrf_token() }}" />
@flashhaltScripts

<!-- Or include in individual forms -->
<form hx-post="hx/users@store">
  @csrf
  <input type="text" name="name" required />
  <button type="submit">Create User</button>
</form>
```

## Blade Directives

FlashHALT provides several Blade directives to enhance your development experience:

### @flashhaltScripts

Includes the FlashHALT JavaScript integration automatically:

```html
<!DOCTYPE html>
<html>
  <head>
    <title>My App</title>
    <script src="https://unpkg.com/htmx.org@1.9.8"></script>
    @flashhaltScripts
  </head>
  <body>
    <!-- Your content -->
  </body>
</html>
```

This directive automatically:

- Includes the FlashHALT JavaScript file
- Configures CSRF token handling
- Sets up request interception for FlashHALT routes
- Provides debugging information in development mode

### @flashhaltEnabled / @endflashhalt

Create conditional blocks that only appear when FlashHALT is available:

```html
@flashhaltEnabled
<button hx-get="hx/users@index" hx-target="#content">
  Load Users with FlashHALT
</button>
@endflashhalt

<noscript>
  <a href="/users">Load Users (fallback)</a>
</noscript>
```

### @flashhaltCsrf

A convenient way to include the CSRF meta tag:

```html
<head>
  <title>My App</title>
  @flashhaltCsrf
  <!-- Equivalent to: <meta name="csrf-token" content="{{ csrf_token() }}"> -->
</head>
```

## Configuration

FlashHALT provides extensive configuration options through `config/flashhalt.php`:

### Operating Mode

```php
'mode' => env('FLASHHALT_MODE', 'development'),
```

- `'development'` - Dynamic resolution with helpful debugging
- `'production'` - Pre-compiled routes for maximum performance

### Development Configuration

```php
'development' => [
    // Cache TTL for controller resolution (seconds)
    'cache_ttl' => env('FLASHHALT_DEV_CACHE_TTL', 3600),

    // Enable debug mode with detailed error reporting
    'debug_mode' => env('FLASHHALT_DEBUG', env('APP_DEBUG', false)),

    // Rate limiting (requests per minute per IP)
    'rate_limit' => env('FLASHHALT_DEV_RATE_LIMIT', 120),

    // Whitelist specific controllers (empty allows all)
    'allowed_controllers' => [],
],
```

### Security Configuration

```php
'security' => [
    // Allowed controller namespaces
    'allowed_namespaces' => [
        'App\\Http\\Controllers\\*',
    ],

    // Blocked method names for security
    'blocked_methods' => [
        '__construct', '__destruct', '__call', '__callStatic',
        'middleware', 'getMiddleware', 'callAction'
    ],

    // Maximum route pattern length
    'max_pattern_length' => 100,

    // Maximum namespace depth
    'max_namespace_depth' => 5,
],
```

### Performance Configuration

```php
'performance' => [
    // Cache store for FlashHALT operations
    'cache_store' => env('FLASHHALT_CACHE_STORE', env('CACHE_DRIVER', 'file')),

    // Enable memory caching for single request
    'enable_memory_cache' => true,

    // Memory cache limit in MB
    'memory_cache_limit' => 10,
],
```

## Console Commands

FlashHALT provides Artisan commands for development and deployment workflows.

### flashhalt:compile

Compile FlashHALT routes for production deployment:

```bash
# Basic compilation
php artisan flashhalt:compile

# Available options:
--force          # Force compilation even if no changes detected
--verify         # Run verification checks after compilation
--dry-run        # Analyze without writing files
--stats          # Display comprehensive statistics
--routes-only    # Show discovered routes without compiling
-v, -vv, -vvv    # Increase verbosity for debugging
```

### flashhalt:clear

Clear FlashHALT compilation artifacts and caches:

```bash
# Clear all FlashHALT artifacts
php artisan flashhalt:clear

# Available options:
--compiled-only  # Only remove compiled routes file
--cache-only     # Only clear FlashHALT caches
--dry-run        # Show what would be cleared without removing
--force          # Skip confirmation prompts
```

## Security

FlashHALT implements comprehensive security measures to protect your application.

### Controller Namespace Restrictions

By default, FlashHALT only allows access to controllers in approved namespaces:

```php
// config/flashhalt.php
'security' => [
    'allowed_namespaces' => [
        'App\\Http\\Controllers\\*',
        'App\\Http\\Controllers\\Api\\*',
        'App\\Http\\Controllers\\Admin\\*',
    ],
],
```

### Method Security Validation

FlashHALT automatically blocks access to dangerous methods:

```php
// Blocked by default
'blocked_methods' => [
    '__construct', '__destruct', '__call', '__callStatic',
    'middleware', 'getMiddleware', 'callAction',
],
```

**Additional Security Checks:**

- Only public methods are accessible
- Methods must exist and be callable
- Laravel controller methods are protected
- Magic methods are blocked
- Static methods are blocked
- Methods with security annotations (`@internal`, `@private`) are blocked

### Laravel Integration

FlashHALT respects all Laravel security features:

```php
class UserController extends Controller
{
    public function __construct()
    {
        // Constructor middleware applies to all FlashHALT routes
        $this->middleware('auth');
        $this->middleware('verified');
    }

    public function index()
    {
        // Method-level authorization
        $this->authorize('viewAny', User::class);

        return view('users.index', ['users' => User::all()]);
    }

    public function update(Request $request, User $user)
    {
        // Per-resource authorization
        $this->authorize('update', $user);

        $user->update($request->validated());
        return view('users.updated', compact('user'));
    }
}
```

## Performance

FlashHALT is designed for exceptional performance in both development and production environments.

### Development Performance

**Intelligent Caching:**

```php
'development' => [
    'cache_ttl' => 3600, // Cache controller resolution for 1 hour
    'enable_memory_cache' => true, // Single-request memory cache
    'memory_cache_limit' => 10, // 10MB memory limit
],
```

**Resolution Optimization:**

- Controller class existence is cached after first check
- Method reflection results are cached across requests
- Security validation results are memoized
- Namespace mapping is optimized for common patterns

### Production Performance

**Route Compilation Benefits:**

- Eliminates dynamic controller resolution entirely
- Generates optimized Laravel route definitions
- Leverages Laravel's built-in route caching
- Reduces memory usage and CPU overhead

## Testing

FlashHALT integrates seamlessly with Laravel's testing ecosystem.

### Feature Testing

Test FlashHALT routes using Laravel's testing tools:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashHaltTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_listing_via_flashhalt()
    {
        User::factory()->count(3)->create();

        $response = $this->get('/hx/users@index', [
            'HX-Request' => 'true'
        ]);

        $response->assertStatus(200)
                 ->assertViewIs('users.index')
                 ->assertViewHas('users');
    }

    public function test_user_creation_via_flashhalt()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $response = $this->post('/hx/users@store', $userData, [
            'HX-Request' => 'true'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
    }
}
```

### Testing HTMX Responses

Test HTMX-specific response features:

```php
public function test_htmx_response_includes_correct_headers()
{
    $response = $this->post('/hx/users@store', [
        'name' => 'Test User',
        'email' => 'test@example.com'
    ], [
        'HX-Request' => 'true'
    ]);

    $response->assertStatus(200)
             ->assertHeader('X-FlashHALT-Processed', 'true');
}
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Routes Not Working

**Problem:** FlashHALT routes return 404 errors

**Solutions:**

```bash
# Check if FlashHALT is properly installed
composer show dancycodes/flashhalt

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Verify FlashHALT routes are registered
php artisan route:list | grep hx/
```

#### 2. Controller Not Found

**Problem:** "Controller class does not exist" errors

**Solutions:**

```bash
# Regenerate autoloader
composer dump-autoload

# Check namespace configuration
# config/flashhalt.php
'security' => [
    'allowed_namespaces' => [
        'App\\Http\\Controllers\\*',
        'App\\Http\\Controllers\\Admin\\*', // Add your namespaces
    ],
],
```

#### 3. CSRF Token Issues

**Problem:** 419 CSRF token mismatch errors

**Solutions:**

```html
<!-- Ensure @flashhaltScripts is included -->
@flashhaltScripts

<!-- Or manually configure CSRF handling -->
<meta name="csrf-token" content="{{ csrf_token() }}" />
<script>
  document.body.addEventListener("htmx:configRequest", function (evt) {
    evt.detail.headers["X-CSRF-TOKEN"] = document.querySelector(
      'meta[name="csrf-token"]'
    ).content;
  });
</script>
```

### Getting Help

- **GitHub Issues:** [https://github.com/dancycodes/flashhalt/issues](https://github.com/dancycodes/flashhalt/issues)
- **Laravel Community:** [Laravel.io](https://laravel.io)
- **HTMX Community:** [HTMX Discord](https://discord.gg/htmx)

## Development Status

FlashHALT is actively under development.

## Contributing

We welcome contributions to FlashHALT! Whether you're fixing bugs, adding features, or improving documentation, your help makes FlashHALT better for everyone.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/dancycodes/flashhalt.git
cd flashhalt

# Install dependencies
composer install

# Run tests
composer test
```

### Pull Request Process

1. **Fork the repository**
2. **Create a feature branch:** `git checkout -b feature/amazing-feature`
3. **Write tests:** Ensure new code is tested
4. **Update documentation:** Document new features and changes
5. **Commit changes:** Use conventional commit messages
6. **Push to branch:** `git push origin feature/amazing-feature`
7. **Open Pull Request:** Include detailed description and motivation

## License

FlashHALT is open-sourced software licensed under the [MIT license](LICENSE.md).

---

**FlashHALT** - Revolutionizing Laravel HTMX development through convention over configuration.

Built with ❤️ by [DancyCodes](https://github.com/dancycodes) and the open-source community.
