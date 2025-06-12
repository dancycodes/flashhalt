# FlashHALT

> **FlashHALT eliminates route definition overhead for HTMX applications, enabling direct controller method access through intuitive URL conventions - delivering 10x faster development velocity while maintaining Laravel's architectural integrity.**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dancycodes/flashhalt.svg?style=flat-square)](https://packagist.org/packages/dancycodes/flashhalt)
[![Total Downloads](https://img.shields.io/packagist/dt/dancycodes/flashhalt.svg?style=flat-square)](https://packagist.org/packages/dancycodes/flashhalt)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

FlashHALT is a Laravel package that transforms how you build HTMX applications by introducing convention-based routing patterns. Instead of defining individual routes for every HTMX interaction, you can access controller methods directly through intuitive URL patterns.

## The Problem That FlashHALT Solves

When building HTMX applications in Laravel, you face two major friction points that slow down development and create maintenance headaches.

**First, the Route Definition Tax.** Every single HTMX interaction requires you to stop what you're doing, switch to your routes file, and manually define a route. Want to add a simple "mark as complete" button to your todo app? You need to write a route. Want to load user details in a modal? Another route. Need to update a comment inline? Yet another route. This constant context switching destroys your flow state and turns simple features into multi-file modifications.

**Second, the CSRF Token Nightmare.** Laravel's CSRF protection is essential for security, but it creates a painful developer experience in HTMX applications. You must remember to add `@csrf` to every form, manually include `_token` in `hx-vals` for button clicks, or configure `X-CSRF-TOKEN` headers. Miss any one of these, and your request fails with a cryptic 419 error. This isn't just annoying - it's error-prone and creates security vulnerabilities when developers skip CSRF protection out of frustration.

Here's what typical HTMX development looks like in Laravel:

```php
// routes/web.php - You have to define every single endpoint
Route::get('/tasks', [TaskController::class, 'index']);
Route::post('/tasks', [TaskController::class, 'store']);
Route::patch('/tasks/{task}/complete', [TaskController::class, 'markComplete']);
Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
Route::get('/tasks/{task}/edit', [TaskController::class, 'edit']);
Route::patch('/tasks/{task}', [TaskController::class, 'update']);
// And this is just for ONE resource! Multiply by every feature...
```

```html
<!-- Then in your templates, CSRF management becomes your responsibility -->
<form hx-post="/tasks">
  @csrf
  <!-- Must remember this in every form -->
  <input type="text" name="title" placeholder="New task" />
  <button type="submit">Add Task</button>
</form>

<button
  hx-patch="/tasks/{{ $task->id }}/complete"
  hx-vals='{"_token": "{{ csrf_token() }}"}'
>
  <!-- Manual token management -->
  Mark Complete
</button>

<button
  hx-delete="/tasks/{{ $task->id }}"
  hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
>
  <!-- Or header approach -->
  Delete
</button>
```

This approach has several problems. You're constantly switching between files, manually managing CSRF tokens, and the route definitions don't provide any meaningful organization - they're just a flat list of endpoints that grows unwieldy over time.

## How FlashHALT Transforms Your Workflow

FlashHALT introduces a fundamentally different approach that eliminates both friction points simultaneously. Instead of defining routes, you use intuitive URL patterns that directly reference your controller methods. Instead of manually managing CSRF tokens, FlashHALT automatically handles them for you.

Here's the same functionality with FlashHALT:

```php
// routes/web.php - Completely empty! No routes needed.
```

```html
<!-- Include FlashHALT once in your layout -->
<!DOCTYPE html>
<html>
  <head>
    <script src="https://unpkg.com/htmx.org@1.9.8"></script>
    @flashhaltCsrf
    <!-- This adds the csrf meta tag.-->
    @flashhaltScripts
    <!-- This single line handles ALL CSRF automatically -->
  </head>
  <body>
    <!-- Now your templates become incredibly clean -->
    <form hx-post="hx/tasks@store">
      <!-- No @csrf needed! FlashHALT handles it automatically -->
      <input type="text" name="title" placeholder="New task" />
      <button type="submit">Add Task</button>
    </form>

    <button
      hx-patch="hx/tasks@markComplete"
      hx-vals='{"task": {{ $task->id }}}'
    >
      <!-- No manual _token needed! Automatic CSRF injection -->
      Mark Complete
    </button>

    <button hx-delete="hx/tasks@destroy" hx-vals='{"task": {{ $task->id }}}'>
      <!-- Every request gets CSRF protection automatically -->
      Delete
    </button>
  </body>
</html>
```

Notice what just happened. We eliminated the entire routes file and removed all manual CSRF token management. The URL patterns like `hx/tasks@store` directly reference your controller methods, making the code self-documenting. Most importantly, you never have to think about CSRF tokens again - FlashHALT automatically injects them into every non-GET request.

## Understanding FlashHALT's URL Convention

FlashHALT uses a simple but powerful URL pattern that maps directly to Laravel's controller structure. Let me walk you through how this works, because understanding this pattern is key to using FlashHALT effectively.

The basic pattern is `hx/{controller}@{method}`. Think of the `hx/` prefix as FlashHALT's namespace - it tells the system "this request should be handled by FlashHALT's dynamic routing." The `@` symbol separates the controller from the method, making it clear which method you're calling.

**Simple Controller Mapping:**

```
hx/task@index     → App\Http\Controllers\TaskController::index()
hx/user@show      → App\Http\Controllers\UserController::show()
hx/post@destroy   → App\Http\Controllers\PostController::destroy()
```

FlashHALT automatically handles Laravel's controller naming conventions. Whether your controller is named `Task`, `TaskController`, `Tasks`, or `TasksController`, FlashHALT will find it. This flexibility means you don't need to remember exact naming - just use what feels natural.

**Namespace Support with Dots:**
For controllers in subdirectories, use dots to represent folder separators:

```
hx/admin.users@index     → App\Http\Controllers\Admin\UserController::index()
hx/api.v1.posts@show     → App\Http\Controllers\Api\V1\PostController::show()
hx/billing.invoices@pdf  → App\Http\Controllers\Billing\InvoiceController::pdf()
```

This dot notation makes your template code highly readable. When you see `hx/admin.users@index`, you immediately know you're calling the index method on the UserController in the Admin namespace.

**Parameter Passing:**
FlashHALT works seamlessly with Laravel's route model binding and parameter injection:

```html
<!-- Pass parameters via hx-vals (FlashHALT automatically includes CSRF) -->
<button hx-delete="hx/task@destroy" hx-vals='{"task": {{ $task->id }}}'>
  Delete Task
</button>

<!-- Or via form fields -->
<form hx-patch="hx/users@update">
  <input type="hidden" name="user" value="{{ $user->id }}" />
  <input type="text" name="name" value="{{ $user->name }}" />
  <button type="submit">Update User</button>
</form>
```

Your controller methods receive these parameters exactly as they would with traditional routes:

```php
public function destroy(Task $task)  // Automatic model binding works perfectly
{
    $this->authorize('delete', $task);  // Authorization works normally
    $task->delete();
    return '';  // Return empty string to remove the element
}

public function update(Request $request, User $user)  // Multiple parameters work fine
{
    $user->update($request->validated());
    return view('users.profile', compact('user'));
}
```

## The CSRF Magic: Never Think About Tokens Again

This is where FlashHALT truly shines and saves you countless hours of frustration. Traditional HTMX development requires you to manually manage CSRF tokens for every single non-GET request. FlashHALT completely eliminates this burden.

**The Traditional CSRF Pain:**

```html
<!-- Traditional approach - error-prone and tedious -->
<form hx-post="/users">
  @csrf
  <!-- Must remember this in every form -->
  <input type="text" name="name" />
</form>

<button hx-delete="/users/123" hx-vals='{"_token": "{{ csrf_token() }}"}'>
  <!-- Manual token in every button -->
  Delete
</button>

<div
  hx-patch="/users/123/favorite"
  hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
>
  <!-- Or header approach -->
  Add to Favorites
</div>
```

**The FlashHALT Way - Completely Automatic:**

```html
<!DOCTYPE html>
<html>
  <head>
    @flashhaltCsrf
    <!-- This adds the csrf meta tag.-->
    @flashhaltScripts
    <!-- This single line enables automatic CSRF for everything -->
  </head>
  <body>
    <!-- Now every FlashHALT request gets CSRF protection automatically -->
    <form hx-post="hx/users@store">
      <!-- No @csrf needed! -->
      <input type="text" name="name" />
    </form>

    <button hx-delete="hx/users@destroy" hx-vals='{"user": 123}'>
      <!-- No _token needed! -->
      Delete
    </button>

    <div hx-patch="hx/users@toggleFavorite" hx-vals='{"user": 123}'>
      <!-- No X-CSRF-TOKEN needed! -->
      Add to Favorites
    </div>
  </body>
</html>
```

**How the CSRF Magic Works:**
When you include `@flashhaltCsrf` and `@flashhaltScripts`, FlashHALT installs a JavaScript interceptor that automatically detects FlashHALT requests (those starting with `hx/`) and intelligently injects CSRF tokens. For form submissions, it adds the token as a form field. For button clicks and other requests, it includes the token in the request headers. This happens completely transparently - you never see it, never think about it, but you're always protected.

This automatic CSRF injection means you can focus entirely on building features instead of wrestling with security boilerplate. It also eliminates a major source of bugs - how many times have you spent minutes debugging a 419 error only to realize you forgot a CSRF token?

## Installation and Immediate Setup

Getting started with FlashHALT takes less than two minutes. Let me walk you through each step so you can start building immediately.

**Step 1: Install the Package**

```bash
composer require dancycodes/flashhalt
```

Laravel's auto-discovery automatically registers FlashHALT's service provider, so no manual configuration is needed.

**Step 2: Add HTMX and FlashHALT to Your Layout**

```html
<!DOCTYPE html>
<html>
  <head>
    <title>Your App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Include HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.8"></script>

    <!-- This adds the csrf meta tag.-->
    @flashhaltCsrf

    <!-- This single line enables FlashHALT with automatic CSRF -->
    @flashhaltScripts
  </head>
  <body>
    @yield('content')
  </body>
</html>
```

That's it! FlashHALT is now active and every FlashHALT request will automatically include CSRF protection.

**Step 3 (Optional): Publish Configuration**

```bash
php artisan vendor:publish --provider="DancyCodes\FlashHalt\FlashHaltServiceProvider" --tag="config"
```

This creates `config/flashhalt.php` where you can customize security settings, but the defaults work perfectly for most applications.

## Your First FlashHALT Feature: A Complete Example

Let me show you how to build a complete feature using FlashHALT. We'll create a simple task manager that demonstrates all the key concepts. This example will help you understand how FlashHALT transforms your development workflow.

**Create the Controller (Standard Laravel)**

```php
<?php
// app/Http/Controllers/TaskController.php
namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        // Load all tasks and return the list view
        $tasks = Task::orderBy('created_at', 'desc')->get();
        return view('tasks.index', compact('tasks'));
    }

    public function store(Request $request)
    {
        // Validate and create a new task
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $task = Task::create($validated);

        // Return just the new task HTML to be inserted
        return view('tasks.task-item', compact('task'));
    }

    public function toggle(Task $task)
    {
        // Toggle completion status
        $task->update(['completed' => !$task->completed]);

        // Return the updated task HTML
        return view('tasks.task-item', compact('task'));
    }

    public function destroy(Task $task)
    {
        $task->delete();

        // Return empty string to remove the element
        return '';
    }
}
```

**Create the Views**

```html
<!-- resources/views/tasks/index.blade.php -->
@extends('layouts.app') @section('content')
<div class="container mx-auto p-6">
  <h1 class="text-2xl font-bold mb-6">Task Manager</h1>

  <!-- Add new task form -->
  <div class="mb-8 p-4 bg-gray-50 rounded">
    <form
      hx-post="hx/task@store"
      hx-target="#task-list"
      hx-swap="afterbegin"
      hx-reset="true"
    >
      <!-- Notice: No @csrf needed! FlashHALT handles it automatically -->

      <div class="mb-4">
        <input
          type="text"
          name="title"
          placeholder="What needs to be done?"
          required
          class="w-full p-2 border rounded"
        />
      </div>

      <div class="mb-4">
        <textarea
          name="description"
          placeholder="Description (optional)"
          class="w-full p-2 border rounded"
        ></textarea>
      </div>

      <button
        type="submit"
        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
      >
        Add Task
      </button>
    </form>
  </div>

  <!-- Task list -->
  <div id="task-list" class="space-y-2">
    @foreach($tasks as $task) @include('tasks.task-item', ['task' => $task])
    @endforeach
  </div>
</div>
@endsection
```

```html
<!-- resources/views/tasks/task-item.blade.php -->
<div
  class="task-item p-4 border rounded {{ $task->completed ? 'bg-green-50' : 'bg-white' }}"
  id="task-{{ $task->id }}"
>
  <div class="flex items-center justify-between">
    <div class="flex-1">
      <h3
        class="font-semibold {{ $task->completed ? 'line-through text-gray-500' : '' }}"
      >
        {{ $task->title }}
      </h3>

      @if($task->description)
      <p
        class="text-gray-600 mt-1 {{ $task->completed ? 'line-through' : '' }}"
      >
        {{ $task->description }}
      </p>
      @endif
    </div>

    <div class="flex gap-2 ml-4">
      <!-- Toggle completion button -->
      <button
        hx-patch="hx/task@toggle"
        hx-vals='{"task": {{ $task->id }}}'
        hx-target="#task-{{ $task->id }}"
        hx-swap="outerHTML"
        class="px-3 py-1 rounded text-sm {{ $task->completed ? 'bg-yellow-500 text-white' : 'bg-green-500 text-white' }}"
      >
        <!-- No CSRF token needed! FlashHALT handles it automatically -->
        {{ $task->completed ? 'Undo' : 'Complete' }}
      </button>

      <!-- Delete button -->
      <button
        hx-delete="hx/task@destroy"
        hx-vals='{"task": {{ $task->id }}}'
        hx-target="#task-{{ $task->id }}"
        hx-swap="outerHTML"
        hx-confirm="Are you sure you want to delete this task?"
        class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600"
      >
        <!-- No CSRF token needed! FlashHALT handles it automatically -->
        Delete
      </button>
    </div>
  </div>
</div>
```

**Load the Task List in Your Main Page**

```html
<!-- In any view where you want to show tasks -->
<div
  hx-get="hx/task@index"
  hx-trigger="load"
  hx-target="this"
  hx-swap="innerHTML"
>
  <div class="text-center p-8">
    <div
      class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"
    ></div>
    <p class="mt-2 text-gray-600">Loading tasks...</p>
  </div>
</div>
```

**That's It - No Routes Needed!**

Notice what we didn't have to do. We never touched the routes file. We never manually added CSRF tokens. FlashHALT automatically resolved our URL patterns to the controller methods and handled all the security concerns transparently.

The URLs `hx/@index`, `hx/task@store`, `hx/task@toggle`, and `hx/task@destroy` automatically map to the corresponding methods in `TaskController`. The CSRF protection is automatically applied to all the POST, PATCH, and DELETE requests.

## Understanding FlashHALT's Two Operating Modes

FlashHALT is designed to optimize your workflow in both development and production environments. Understanding these two modes will help you get the maximum benefit from the package.

**Development Mode: Instant Feedback and Maximum Flexibility**

In development mode (which is the default), FlashHALT uses dynamic routing. When a request comes in with the `hx/` pattern, FlashHALT's middleware intercepts it, analyzes the URL pattern, and dynamically resolves it to the appropriate controller method. This happens in real-time for every request.

The beauty of development mode is that changes are instant. You can add a new method to your controller, reference it in your template with `hx/controller@newMethod`, and it works immediately - no route definitions, no cache clearing, no build steps. This instant feedback loop keeps you in a flow state and eliminates the friction that normally slows down HTMX development.

Development mode also provides detailed error messages when something goes wrong. If you reference a controller that doesn't exist, or a method that's not allowed, FlashHALT gives you clear, actionable error messages that help you fix the problem quickly.

**Production Mode: Maximum Performance and Security**

In production mode, FlashHALT takes a completely different approach optimized for performance and security. Instead of dynamically resolving routes at runtime, FlashHALT analyzes all your Blade templates during deployment and generates traditional Laravel route definitions for every FlashHALT pattern it finds.

This compilation process means that in production, your FlashHALT routes perform exactly the same as manually defined routes - there's zero runtime overhead, zero dynamic resolution, and zero performance penalty. Your application runs at full speed while still giving you the development experience benefits.

The compilation process also serves as a security validation step. FlashHALT checks every route it finds against your security configuration, ensuring that only safe controller methods are exposed. If it finds any problematic patterns, it fails the compilation and alerts you to the issue.

**Switching Between Modes**

To compile for production:

```bash
# Analyze your templates and generate static routes
php artisan flashhalt:compile

# Switch to production mode
echo "FLASHHALT_MODE=production" >> .env
```

To return to development:

```bash
# Clear compiled routes
php artisan flashhalt:clear

# Switch back to development mode
echo "FLASHHALT_MODE=development" >> .env
```

The key insight is that you get the best of both worlds: the incredible developer experience of dynamic routing in development, and the performance and security of static routes in production.

## Security: Designed to Be Secure by Default

Security in FlashHALT isn't an afterthought - it's built into every layer of the system. Let me explain how FlashHALT protects your application without limiting your flexibility.

**Controller Whitelisting: Only What You Allow**

By default, FlashHALT only allows access to controllers in your main `App\Http\Controllers` namespace. This means that even if someone discovers FlashHALT is running on your site, they can't arbitrarily call methods on internal classes or framework components.

```php
// config/flashhalt.php
'allowed_controllers' => [
    'App\Http\Controllers\*',        // Allow all controllers in main namespace
    'App\Http\Controllers\Api\*',    // Allow API controllers
    'UserController',                // Allow specific controller by name
    'Admin\UserController',          // Allow specific namespaced controller
],
```

This whitelist approach means you're secure by default, but you can easily expand access as needed. If you have controllers in other namespaces that should be accessible via FlashHALT, simply add them to the whitelist.

**Method Validation: Preventing Dangerous Operations**

FlashHALT validates every method call against both a whitelist of allowed methods and a blacklist of dangerous patterns. The default configuration allows standard RESTful methods while blocking potentially dangerous operations.

```php
'method_whitelist' => [
    'index', 'show', 'create', 'store', 'edit', 'update', 'destroy'
],

'method_pattern_blacklist' => [
    '/^_.*/',           // Block any method starting with underscore
    '/.*[Pp]assword.*/', // Block methods containing "password"
    '/.*[Tt]oken.*/',   // Block methods containing "token"
    '/.*[Ss]ecret.*/',  // Block methods containing "secret"
],
```

This dual-layer approach ensures that sensitive methods are never accidentally exposed while still giving you the flexibility to add custom methods to the whitelist as needed.

**Automatic CSRF Protection: Always On, Never Forgotten**

The automatic CSRF protection in FlashHALT isn't just convenient - it actually improves your application's security posture. Because CSRF protection is automatic and invisible, there's no temptation to skip it or disable it for "just this one request." Every state-changing request is protected, always.

**Integration with Laravel's Authorization System**

FlashHALT doesn't replace Laravel's authorization system - it enhances it. Your existing authorization policies, gates, and middleware work exactly as they would with traditional routes.

```php
class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');  // Authentication works normally
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);  // Authorization works normally
        $task->delete();
        return '';
    }
}
```

FlashHALT operates at the routing layer, so all of Laravel's security features continue to work exactly as expected.

## Configuration: Customizing FlashHALT for Your Needs

While FlashHALT works great with the default configuration, understanding the available options helps you tailor it to your specific requirements.

**Basic Mode Configuration**

```php
// config/flashhalt.php
return [
    // Controls whether FlashHALT uses dynamic or static routing
    'mode' => env('FLASHHALT_MODE', 'development'),

    // Development mode settings
    'development' => [
        'enabled' => env('FLASHHALT_DEV_ENABLED', true),
        'cache_resolution' => true,  // Cache controller resolution for performance
        'debug_mode' => env('APP_DEBUG', false),  // Detailed error messages
    ],

    // Production mode settings
    'production' => [
        'compiled_routes_path' => base_path('routes/flashhalt-compiled.php'),
        'cache_compiled_routes' => true,
        'fallback_to_dynamic' => false,  // Fail fast if compiled routes missing
    ],
];
```

**Security Configuration**

```php
'security' => [
    // Controllers that can be accessed via FlashHALT
    'allowed_controllers' => [
        'App\Http\Controllers\*',
        // Add your additional namespaces here
    ],

    // Methods that are allowed to be called
    'method_whitelist' => [
        'index', 'show', 'create', 'store', 'edit', 'update', 'destroy',
        // Add your custom methods here
    ],

    // Patterns that should never be allowed
    'method_pattern_blacklist' => [
        '/^_.*/',           // Private methods
        '/.*[Pp]assword.*/', // Password operations
        '/.*[Tt]oken.*/',   // Token operations
        // Add your patterns here
    ],

    // Enable automatic CSRF protection
    'csrf_protection' => true,

    // Ensure destructive operations can't be called via GET
    'enforce_http_method_semantics' => true,
],
```

## Advanced Features: Going Beyond the Basics

**Parameter Binding and Model Injection**

FlashHALT works seamlessly with Laravel's powerful parameter binding system. You can use route model binding, custom resolution logic, and dependency injection exactly as you would with traditional routes.

```php
class UserController extends Controller
{
    public function show(User $user)
    {
        // $user is automatically resolved from the request parameter
        return view('users.profile', compact('user'));
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        // Multiple parameters and service injection work perfectly
        $user->update($request->validated());
        $audit->log('user.updated', $user);

        return view('users.profile', compact('user'));
    }
}
```

```html
<!-- Pass parameters via hx-vals or form fields -->
<button hx-get="hx/users@show" hx-vals='{"user": {{ $user->id }}}'>
  View Profile
</button>

<form hx-patch="hx/users@update">
  <input type="hidden" name="user" value="{{ $user->id }}" />
  <input type="text" name="name" value="{{ $user->name }}" />
  <button type="submit">Update</button>
</form>
```

**Custom Response Headers for HTMX**

FlashHALT doesn't interfere with HTMX's response header system. You can use all of HTMX's powerful response headers to create sophisticated interactions.

```php
class NotificationController extends Controller
{
    public function markAsRead(Notification $notification)
    {
        $notification->markAsRead();

        return response()
            ->view('notifications.notification', compact('notification'))
            ->header('HX-Trigger', 'notificationRead')  // Trigger client-side events
            ->header('HX-Push-Url', '/notifications');   // Update browser URL
    }
}
```

**Middleware Integration**

FlashHALT routes respect all your existing middleware. Authentication, authorization, rate limiting, and custom middleware all work exactly as expected.

```php
class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:access-admin');
        $this->middleware('throttle:60,1');  // Rate limiting works normally
    }
}
```

## Console Commands: Managing Your FlashHALT Installation

FlashHALT provides two console commands that help you manage the compilation process and troubleshoot issues.

**The Compile Command: Preparing for Production**

The `flashhalt:compile` command analyzes all your Blade templates, finds FlashHALT patterns, validates them against your security configuration, and generates optimized Laravel route definitions.

```bash
# Basic compilation
php artisan flashhalt:compile

# See detailed output about what's being compiled
php artisan flashhalt:compile --verbose

# See what would be compiled without actually generating routes
php artisan flashhalt:compile --dry-run

# Force compilation even if routes already exist
php artisan flashhalt:compile --force
```

The compile command provides detailed feedback about what it finds:

```
🔍 Analyzing Blade templates in /resources/views...
✅ Found 24 FlashHALT routes in 18 templates

📝 Validating routes against security configuration...
✅ All routes passed security validation

⚡ Generating optimized route definitions...
✅ Created routes/flashhalt-compiled.php (156 lines, 24 routes)

🎯 Compilation complete! Your application is production-ready.
   • 24 dynamic routes converted to static routes
   • 0 security violations found
   • 156 lines of optimized code generated
```

**The Clear Command: Cleaning Up**

The `flashhalt:clear` command removes compilation artifacts and cache entries, which is useful when switching between modes or troubleshooting issues.

```bash
# Clear all FlashHALT artifacts
php artisan flashhalt:clear

# Clear only compiled routes
php artisan flashhalt:clear --compiled-routes

# Clear only cache entries
php artisan flashhalt:clear --cache

# See what would be cleared without actually removing anything
php artisan flashhalt:clear --dry-run
```

## Troubleshooting: Solving Common Issues

**"Controller not whitelisted" Error**

This error means you're trying to access a controller that's not in your `allowed_controllers` configuration. This is a security feature - FlashHALT only allows access to explicitly permitted controllers.

**Solution:** Add your controller to the whitelist in `config/flashhalt.php`:

```php
'allowed_controllers' => [
    'App\Http\Controllers\*',
    'YourNamespace\YourController',  // Add this line
],
```

**CSRF Token Mismatch (419 Error)**

If you're getting 419 errors, it usually means the automatic CSRF injection isn't working properly.

**Solution:** Ensure you've included `@flashhaltCsrf` and `@flashhaltScripts` in your layout:

```html
<head>
  <script src="https://unpkg.com/htmx.org@1.9.8"></script>
  @flashhaltCsrf @flashhaltScripts
</head>
```

**Method Not Found or Not Allowed**

This error occurs when you reference a method that doesn't exist or isn't in the method whitelist.

**Solution:** Check that your method exists and add it to the whitelist if needed:

```php
'method_whitelist' => [
    'index', 'show', 'store', 'update', 'destroy',
    'yourCustomMethod',  // Add your method here
],
```

**Routes Not Working in Production**

If FlashHALT routes stop working after deployment, you likely forgot to compile the routes.

**Solution:** Compile routes as part of your deployment process:

```bash
php artisan flashhalt:compile
```

**Getting Detailed Debug Information**

Enable debug mode for detailed error messages and logging:

```php
// config/flashhalt.php
'development' => [
    'debug_mode' => true,
],
```

With debug mode enabled, FlashHALT logs detailed information about route resolution attempts, which you can find in your Laravel logs.

## Testing Your FlashHALT Applications

FlashHALT routes can be tested exactly like traditional Laravel routes. Here are some patterns that work well:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;

class TaskControllerTest extends TestCase
{
    /** @test */
    public function it_can_load_task_index_via_flashhalt()
    {
        $response = $this->get('hx/task@index');

        $response->assertOk();
        $response->assertViewIs('tasks.index');
    }

    /** @test */
    public function it_can_create_tasks_with_csrf_protection()
    {
        $response = $this->post('hx/task@store', [
            'title' => 'Test Task',
            'description' => 'Test Description',
            '_token' => csrf_token(),  // Include CSRF token in tests
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('tasks', ['title' => 'Test Task']);
    }

    /** @test */
    public function it_respects_authorization_policies()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser);

        $response = $this->delete('hx/task@destroy', [
            'task' => $task->id,
            '_token' => csrf_token(),
        ]);

        $response->assertForbidden();  // Should be blocked by authorization
    }
}
```

## Best Practices: Getting the Most from FlashHALT

**Organize Controllers by Feature, Not by HTTP Method**

Since FlashHALT eliminates route files, organize your controllers around business logic rather than HTTP semantics:

```php
// Good: Feature-focused controller
class TaskController extends Controller
{
    public function index() { /* list tasks */ }
    public function store() { /* create task */ }
    public function toggle() { /* toggle completion */ }
    public function archive() { /* archive task */ }
    public function assignTo() { /* assign to user */ }
}

// Also good: Focused single-purpose controller
class TaskArchiveController extends Controller
{
    public function archive() { /* archive logic */ }
    public function restore() { /* restore logic */ }
    public function purge() { /* permanent deletion */ }
}
```

**Use Descriptive Method Names**

Since your method names appear in URLs, make them descriptive and intention-revealing:

```html
<!-- Clear and intention-revealing -->
<button hx-post="hx/task@markComplete">Mark Complete</button>
<button hx-patch="hx/user@updatePassword">Change Password</button>
<button hx-delete="hx/project@archiveProject">Archive Project</button>
```

**Return Partial Views for HTMX**

Structure your views to support partial updates:

```php
public function store(Request $request)
{
    $task = Task::create($request->validated());

    // Return just the new task HTML, not the full page
    return view('tasks.task-item', compact('task'));
}

public function index()
{
    $tasks = Task::all();

    if (request()->header('HX-Request')) {
        // Return partial for HTMX requests
        return view('tasks.task-list', compact('tasks'));
    }

    // Return full page for direct navigation
    return view('tasks.index', compact('tasks'));
}
```

**Leverage HTMX Response Headers**

Use HTMX's response headers to create sophisticated interactions:

```php
public function store(Request $request)
{
    $task = Task::create($request->validated());

    return response()
        ->view('tasks.task-item', compact('task'))
        ->header('HX-Trigger', 'taskCreated')  // Trigger client-side events
        ->header('HX-Push-Url', '/tasks')      // Update browser URL
        ->header('HX-Redirect', '/tasks');     // Redirect after action
}
```

## Performance: Making FlashHALT Fast

**Development Mode Optimizations**

FlashHALT caches controller resolution results to minimize filesystem operations:

```php
// config/flashhalt.php
'development' => [
    'cache_resolution' => true,  // Cache controller lookups
],
```

**Production Mode: Zero Overhead**

In production mode, FlashHALT-generated routes perform identically to hand-written routes. There's no runtime overhead, no dynamic resolution, and no performance penalty.

**Compilation Best Practices**

Include compilation in your deployment pipeline:

```bash
# In your deployment script
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan flashhalt:compile  # Compile FlashHALT routes
php artisan view:cache
```

## Contributing and Getting Help

FlashHALT is actively maintained and we welcome contributions. Whether you've found a bug, have a feature request, or want to contribute code, we'd love to hear from you.

**Reporting Issues**

When reporting issues, please include:

- Laravel version
- FlashHALT version
- Configuration settings (without sensitive data)
- Steps to reproduce the issue
- Expected vs actual behavior

**Contributing Code**

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass
5. Submit a pull request

**Getting Help**

- Check the troubleshooting section above
- Review the configuration options
- Enable debug mode for detailed error messages
- Check the GitHub issues for similar problems

## License

FlashHALT is open-sourced software licensed under the [MIT license](LICENSE.md).

---

**Stop writing routes. Start building features.**

FlashHALT eliminates the friction that slows down HTMX development in Laravel. With automatic CSRF protection, intuitive URL patterns, and production-ready compilation, you can focus on building great user experiences instead of wrestling with routing boilerplate.

Built with ❤️ by [DancyCodes](https://github.com/dancycodes) and the open-source community.
