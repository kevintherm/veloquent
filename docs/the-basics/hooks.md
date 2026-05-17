# Hooks

Hooks allow you to intercept and modify the behavior of Veloquent actions at key points in their lifecycle. They use Laravel's Pipeline system to provide a clean, extensible way to add custom logic without modifying the core framework.

## Registration

Hooks are typically registered in the `hooks/hooks.php`. This file is automatically loaded by the Veloquent Service Provider.

```php
use Veloquent\Core\Domain\Hooks\Facades\Hooks;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;

Hooks::before('record.create', function (HookPayload $payload, Closure $next) {
    // Modify data before it hits the database
    $payload->data['status'] = 'pending';
    
    // IMPORTANT: You must return the result of $next($payload)
    return $next($payload);
});
```

> Every hook pipe **MUST** return the result of `$next($payload)`. Failure to do so will stop the pipeline and may result in the core action receiving an empty or incomplete payload. If you want to abort an operation explicitly use the `HookAbortException` exception.

## The Hook Payload

Every hook closure or class-based pipe receives a `Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload` object as its first argument. This payload encapsulates all relevant context about the event and the request, providing a unified, secure, and clean interface to inspect or mutate execution state.

### Payload Structure

The `HookPayload` object has the following properties:

| Property | Type | Access | Description |
| :--- | :--- | :--- | :--- |
| `event` | `string` | Read-Only | The specific hook event currently executing (e.g., `'record.creating'`, `'auth.logged_in'`). |
| `collection` | `Veloquent\Core\Domain\Collections\Models\Collection` | Read-Only | The collection model instance detailing the database schema and configurations. |
| `record` | `Veloquent\Core\Domain\Records\Models\Record\|null` | Read-Only | The active database record instance. This is `null` in `creating` and `logging_in` phases. |
| `data` | `array` | Read/Write | The array of inputs/parameters for the operation. **Mutating this array alters the data stored or processed.** |
| `request` | `Illuminate\Http\Request\|null` | Read-Only | The active HTTP request instance triggering this action, allowing headers, parameters, IP, or user agents to be queried. |
| `actor` | `Veloquent\Core\Domain\Records\Models\Record\|null` | Read-Only | The authenticated user (`Record` instance) performing the action, or `null` if unauthenticated. |

---

### Property Availability by Event

The properties populated within `HookPayload` differ depending on the lifecycle stage. The following table defines what to expect inside each hook:

| Lifecycle Event | `event` Value | `collection` | `record` | `data` | `request` | `actor` |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Before Create** | `'record.creating'` | ✅ Current Collection | ❌ `null` | ✅ Input Data (Mutable) | ✅ Request | ✅ Auth User / `null` |
| **After Create** | `'record.created'` | ✅ Current Collection | ✅ Created `Record` | ✅ Original Input Data | ✅ Request | ✅ Auth User / `null` |
| **Before Update** | `'record.updating'` | ✅ Current Collection | ✅ Existing `Record` | ✅ Changed Fields (Mutable) | ✅ Request | ✅ Auth User / `null` |
| **After Update** | `'record.updated'` | ✅ Current Collection | ✅ Updated `Record` | ✅ Update Payload | ✅ Request | ✅ Auth User / `null` |
| **Before Delete** | `'record.deleting'` | ✅ Current Collection | ✅ Target `Record` | ❌ Empty `[]` | ✅ Request | ✅ Auth User / `null` |
| **After Delete** | `'record.deleted'` | ✅ Current Collection | ✅ Deleted `Record` | ❌ Empty `[]` | ✅ Request | ✅ Auth User / `null` |
| **Before Login** | `'auth.logging_in'` | ✅ Auth Collection | ❌ `null` | ✅ Credentials (Mutable) | ✅ Request | ❌ `null` |
| **After Login** | `'auth.logged_in'` | ✅ Auth Collection | ✅ Logged-in User `Record` | ✅ Credentials Payload | ✅ Request | ❌ `null` |
| **Before Logout** | `'auth.logging_out'` | ✅ Auth Collection | ✅ Logged-out User `Record` | ❌ Empty `[]` | ✅ Request | ✅ Auth User `Record` |
| **After Logout** | `'auth.logged_out'` | ✅ Auth Collection | ✅ Logged-out User `Record` | ❌ Empty `[]` | ✅ Request | ✅ Auth User `Record` |
| **Before Password Reset** | `'auth.password_resetting'` | ✅ Auth Collection | ✅ Target User `Record` | ✅ Reset Data (Mutable) | ✅ Request | ❌ `null` |
| **After Password Reset** | `'auth.password_reset'` | ✅ Auth Collection | ✅ Target User `Record` | ✅ Reset Data | ✅ Request | ❌ `null` |
| **Before Email Verify** | `'auth.email_verifying'` | ✅ Auth Collection | ✅ Target User `Record` | ✅ Verify Data (Mutable) | ✅ Request | ❌ `null` |
| **After Email Verify** | `'auth.email_verified'` | ✅ Auth Collection | ✅ Target User `Record` | ✅ Verify Data | ✅ Request | ❌ `null` |
| **Before Email Change** | `'auth.email_changing'` | ✅ Auth Collection | ✅ Target User `Record` | ✅ Change Data (Mutable) | ✅ Request | ❌ `null` |
| **After Email Change** | `'auth.email_changed'` | ✅ Auth Collection | ✅ Target User `Record` | ✅ Change Data | ✅ Request | ❌ `null` |

---

### Practical Payload Usage Examples

#### 1. Mutating Input Data in "Before" Hooks

In `before` hooks (like `'record.creating'` or `'record.updating'`), modifying the `$payload->data` array directly mutates what is saved to the database. You can do this in-place:

```php
use Veloquent\Core\Domain\Hooks\Facades\Hooks;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Illuminate\Support\Str;

Hooks::before('record.create', function (HookPayload $payload, Closure $next) {
    // 1. Set default status in-place
    if (!isset($payload->data['status'])) {
        $payload->data['status'] = 'draft';
    }

    // 2. Automatically generate a unique slug
    if (isset($payload->data['title'])) {
        $payload->data['slug'] = Str::slug($payload->data['title']);
    }

    return $next($payload);
});
```

Alternatively, you can return a completely new immutable copy using the `$payload->withData(array $data)` helper method:

```php
Hooks::before('record.create', function (HookPayload $payload, Closure $next) {
    $mergedData = array_merge($payload->data, [
        'is_approved' => false,
        'approved_at' => null,
    ]);

    // Return next with a cloned, modified payload
    return $next($payload->withData($mergedData));
});
```

#### 2. Auditing and Ownership via the `actor` Property

Use the `actor` property to restrict actions or perform ownership checks based on the currently authenticated user:

```php
use Veloquent\Core\Domain\Hooks\Exceptions\HookAbortException;

Hooks::before('record.update', function (HookPayload $payload, Closure $next) {
    $actor = $payload->actor;

    // Check if record has a creator and actor is NOT the creator or superuser
    if ($actor && !$actor->isSuperuser()) {
        $ownerId = $payload->record->created_by;
        if ($ownerId && $ownerId !== $actor->id) {
            throw new HookAbortException("Unauthorized: You do not own this record.");
        }
    }

    return $next($payload);
});
```

#### 3. Accessing Request Headers, IPs, or User-Agents

Use the `request` property to query environmental parameters, request metadata, or geo/browser details during action lifecycle events:

```php
Hooks::after('auth.login', function (HookPayload $payload, Closure $next) {
    $user = $payload->record;
    $request = $payload->request;

    if ($request) {
        // Log the successful login audit trail
        activity()
            ->performedOn($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log('User logged in successfully');
    }

    return $next($payload);
});
```

---

## Before vs After Hooks

### Before Hooks
- Run **inside** the database transaction.
- Can modify the `$payload->data` before the operation executes.
- If a "before" hook throws an exception, the entire operation (including any DB changes made by previous hooks) is **rolled back**.
- Used for validation, sanitization, and complex authorization.

### After Hooks
- Run **outside** the database transaction.
- Run after the primary operation has succeeded.
- Exceptions in "after" hooks are **silenced and logged** by default to prevent side-effects from failing the main request.
- Used for notifications, external API syncs, or logging.

## Class-based Hooks

For complex logic, you can use classes that implement the `HookPipe` contract:

```php
namespace App\Hooks;

use Veloquent\Core\Domain\Hooks\Contracts\HookPipe;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Closure;

class SanitizePost implements HookPipe
{
    public function handle(HookPayload $payload, Closure $next): mixed
    {
        $payload->data['title'] = strip_tags($payload->data['title']);
        
        return $next($payload);
    }
}
```

Register the class in `hooks.php`:

```php
Hooks::before('record.create', \App\Hooks\SanitizePost::class);
```

## Aborting Operations

To halt an operation from a "before" hook, throw the `HookAbortException`:

```php
use Veloquent\Core\Domain\Hooks\Exceptions\HookAbortException;

Hooks::before('record.delete', function ($payload, $next) {
    if ($payload->record->is_protected) {
        throw new HookAbortException("This record cannot be deleted.");
    }
    return $next($payload);
});
```

## Supported Events

| Alias | Before Event | After Event | Description |
|---|---|---|---|
| `record.create` | `record.creating` | `record.created` | Record creation |
| `record.update` | `record.updating` | `record.updated` | Record update |
| `record.delete` | `record.deleting` | `record.deleted` | Record deletion |
| `auth.login` | `auth.logging_in` | `auth.logged_in` | User login |
| `auth.logout` | `auth.logging_out` | `auth.logged_out` | User logout |
| `auth.password_reset` | `auth.password_resetting` | `auth.password_reset` | Password reset confirmation |
| `auth.email_verify` | `auth.email_verifying` | `auth.email_verified` | Email verification confirmation |
| `auth.email_change` | `auth.email_changing` | `auth.email_changed` | Email change confirmation |
