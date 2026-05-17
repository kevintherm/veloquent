# Extending Veloquent

Veloquent is designed as a streamlined, self-contained Backend-as-a-Service (BaaS) framework built on top of Laravel. Because it is standard Laravel at its core, extending Veloquent's capabilities with custom endpoints, custom logic, and lifecycle hooks is clean, fast, and familiar. 

This guide details how to extend Veloquent using custom routes and event hooks.

---

## Defining Custom Routes

If you want to build custom endpoints or API actions beyond the standard REST API generated for your collections, you can easily define new routes inside your own project's route files.

Laravel provides route files located in the `routes/` directory of your own project:

- **API Routes**: `routes/api.php`
- **Web Routes**: `routes/web.php`

---

## Route Scoping: Landlord vs. Tenant

Veloquent operates on a **Landlord/Tenant** database architecture. When defining custom routes, you must declare whether they operate in a tenant-specific context or the global landlord context.

### 1. Tenant-Scoped Routes (Needs Tenant)

If your custom endpoint needs to interact with a tenant's database, query tenant collections, or retrieve tenant-specific records, you **MUST** apply the `needs.tenant` middleware to your route or route group.

The `needs.tenant` middleware automatically handles:
- Identifying the tenant context from the incoming request domain or host.
- Switching the active database connection to the target tenant's database.
- Prefixing Redis and system caches to prevent tenant cross-contamination.

#### Example:
```php
use Illuminate\Support\Facades\Route;
use Veloquent\Core\Support\Models\Tenant;

Route::middleware(['needs.tenant'])->group(function() {
    Route::get('/my-custom-endpoint', function() {
        // This closure runs inside the context of the active tenant database.
        $tenantId = Tenant::current()?->id;
        
        return response()->json([
            'status' => 'success',
            'tenant_id' => $tenantId,
        ]);
    });
});
```

> [!IMPORTANT]
> If a route uses the `needs.tenant` middleware but is accessed from a domain that does not map to a registered tenant, the request will fail with a `404 Not Found` error.

### 2. Landlord-Scoped Routes (Global Scope)

If you define a route **without** the `needs.tenant` middleware, it will run inside the landlord context. The landlord scope interacts with the central database managing the entire platform (e.g., managing tenants, system utilities, etc.).

#### Example:
```php
use Illuminate\Support\Facades\Route;

Route::get('/system-status', function() {
    // This route does NOT switch database contexts;
    // it runs on the main landlord database.
    return response()->json([
        'system' => 'online',
    ]);
});
```

---

## Authenticating Users

If your custom route requires user authentication, you can apply the `auth:api` middleware. This middleware enforces Veloquent's standard token authentication.

Once authenticated, the resolved user record is bound to the request. You can access the authenticated user via `Auth::user()` or `$request->user()`. The returned object is an instance of `Veloquent\Core\Domain\Records\Models\Record` representing the authenticated user. This record contains:
- `collection_id`: The ID of the authentication collection the user belongs to.
- `collection_name`: The name of the authentication collection (e.g., `users`).
- The user record attributes (e.g., `email`, `name`, `id`, and any custom fields defined on that auth collection).

#### Example:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['needs.tenant', 'auth:api'])->group(function() {
    Route::get('/my-profile', function(Request $request) {
        $user = $request->user(); // Or Auth::user()
        
        return response()->json([
            'id' => $user->id,
            'collection' => $user->collection_name, // e.g., 'users'
            'email' => $user->email,
            'name' => $user->name,
        ]);
    });
});
```

## Extending with Hooks

If you need to intercept database operations, inject custom validation, or trigger side effects (such as sending notifications or syncing with external APIs) during record operations or authentication flows, you should use the Veloquent **Hooks** system.

Hooks use Laravel's Pipeline design pattern to intercept actions:
- **Before Hooks**: Intercept payloads before they hit the database, allowing you to validate or modify data within the transaction.
- **After Hooks**: Run outside the database transaction after the action successfully completes, perfect for asynchronous tasks and notifications.

For detailed information on how to register and implement custom hook pipes, see the [Hooks Documentation](./hooks.md).

---

## Current Architecture & Future Plans

Beyond custom routes, custom middleware, and lifecycle hooks, Veloquent provides everything you need in a cohesive package out of the box, meaning there are few other areas that require manual extension. 

As Veloquent continues to grow, we plan to expand the extensibility model to support more sophisticated plug-and-play extensions. Keep an eye on our roadmap and future updates for new ways to extend the Veloquent core!
