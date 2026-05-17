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
