# Authentication & User Management

Velo provides a complete suite of authentication and user management features built-in to your auth collections. You can easily manage users, secure your API, and handle common auth flows like email verification and password reset.

## Auth Collections

An auth collection is a specialized type of collection that includes built-in fields for managing users, such as `email`, `password`, `verified`, and more.

### Standard Login

Velo supports standard email and password authentication. When a user logs in, the system issues a persisted, stateful opaque bearer token (a 64-character hex string). This token must be included in the `Authorization` header for subsequent requests.

### Header Authentication
`Authorization: Bearer <token>`

### Configuration

Customize token behavior in your `.env` file:

- **`TOKEN_AUTH_TTL`**: Lifetime of guest tokens in minutes (default: `60`).
- **`TOKEN_AUTH_MAX_ACTIVE`**: Limit the number of active tokens per record. When a new token is issued, older ones are revoked (default: `0`, meaning no limit).

---

## Superusers

Superusers are administrative accounts created during the onboarding process. They have the following capabilities:

- **Bypass API Rules**: Superusers can access, create, update, and delete any record or collection regardless of defined `api_rules`.
- **Administrative Access**: Access to system logs, schema management, and email template configuration.
- **Impersonation**: Authenticate as any record in any collection.

---

## Auth Endpoints

All auth endpoints are scoped to an auth collection:
- `POST /api/collections/{collection}/auth/login`: Login and issue a token.
- `POST /api/collections/{collection}/auth/impersonate/{recordId}`: Authenticate as the given record. (Superusers only)
- `DELETE /api/collections/{collection}/auth/logout`: Revoke the current token.
- `DELETE /api/collections/{collection}/auth/logout-all`: Revoke all tokens for the user.
- `GET /api/collections/{collection}/auth/me`: Get the current authenticated record.

## User Management

Velo includes built-in support for common user management tasks:

### OTP Verification

All authentication flows (Email Verification, Password Reset, Email Change) utilize one-time password (OTP) codes:

1.  **Request Flow**: Dispatch a queued job (`SendOtpJob`) to send the code (e.g., `123456`) via email templates.
2.  **Confirmation Flow**: Consume the code via a `/confirm` endpoint.
3.  **Invalidation**: Previous unused codes for the same action are automatically invalidated when a new code is issued.

### Email Verification
You can request an email verification code and confirm it to mark a user as verified.

### Password Reset
Securely handle password resets via email verification codes.

### Email Change
A secure, two-step process for updating a user's email address with verification at the new address.

## OAuth (Social Login)

Velo integrates with Laravel Socialite to provide easy social logins (e.g., Google, GitHub). OAuth flows are managed via the `/api/oauth2` endpoints, allowing you to redirect users to a provider and exchange the callback for a Velo auth token. Providers can be configured via the `OAuthProviderController` and associated endpoints.

## Next Steps

After managing your users, you can build reactive applications using [Real-time Subscriptions](../realtime/realtime.md).
