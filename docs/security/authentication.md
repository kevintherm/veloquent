# Authentication & User Management

Veloquent provides a complete suite of authentication and user management features built-in to your auth collections. You can easily manage users, secure your API, and handle common auth flows like email verification and password reset.

## Auth Collections

An auth collection is a specialized type of collection that includes built-in fields for managing users, such as `email`, `password`, `verified`, and more.

## Standard Login

Veloquentsupports standard email and password authentication. When a user logs in, the system issues a persisted, stateful opaque bearer token (a 64-character hex string). This token must be included in the `Authorization` header for subsequent requests.

**JavaScript SDK:**
```javascript
const authData = await sdk.auth.login('users', 'user@example.com', 'password123');
console.log(authData.token); // Automatically stored
console.log(authData.expires_in);
```

**Dart SDK:**
```dart
final authData = await sdk.auth.login('users', 'user@example.com', 'password123');
print(authData['token']); // Automatically stored
print(authData['expires_in']);
```

**REST API:**
```http
POST /api/collections/users/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6",
    "expires_in": 3600,
    "collection_name": "users"
  }
}
```

### Bearer Token Authentication
Include the token in the `Authorization` header for subsequent requests:

```http
Authorization: Bearer a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6
```

### Configuration

Customize token behavior in your `.env` file:

- **`TOKEN_AUTH_TTL`**: Lifetime of tokens in minutes (default: `60`).
- **`TOKEN_AUTH_MAX_ACTIVE`**: Limit the number of active tokens per record. When a new token is issued, older ones are revoked (default: `0`, meaning no limit).

### Protected File Requests

Protected file fields are served by an authenticated proxy route and require the same bearer token flow.

```http
GET /api/collections/{collection}/records/{record}/files/{field}?path={encoded-storage-path}
Authorization: Bearer <token>
```

Use the `url` returned in record file metadata and send the token in the `Authorization` header.

#### Browser Note

When the browser cannot attach custom headers to a direct media URL, request the file using `fetch` with `Authorization: Bearer <token>`, then render/open the returned blob URL.

---

## Superusers

Superusers are administrative accounts created during the onboarding process. They have the following capabilities:

- **Bypass API Rules**: Superusers can access, create, update, and delete any record or collection regardless of defined `api_rules`.
- **Administrative Access**: Access to system logs, schema management, and email template configuration.
- **Impersonation**: Authenticate as any record in any collection.

### Impersonate a User

Superusers can authenticate as any record in the system by using the impersonate endpoint. This is useful for testing, user support, or administrative debugging.

**JavaScript SDK:**
```javascript
const authData = await sdk.auth.impersonate('users', '01JABCDEF123456789');
console.log(authData.token); // Superuser now authenticated as the target user
```

**Dart SDK:**
```dart
final authData = await sdk.auth.impersonate('users', '01JABCDEF123456789');
print(authData['token']); // Superuser now authenticated as the target user
```

**REST API:**
```http
POST /api/collections/users/auth/impersonate/01JABCDEF123456789
Authorization: Bearer <superuser-token>
```

---

## Get Current User

Retrieve the currently authenticated user. You can call this without a collection parameter to get user data without collection-specific verification.

**JavaScript SDK:**
```javascript
// Without collection (calls /api/user)
const user = await sdk.auth.me();
console.log(user.id);
console.log(user.email);

// With collection (verifies token belongs to that collection)
const userInCollection = await sdk.auth.me('users');
console.log(userInCollection.id);
```

**Dart SDK:**
```dart
// Without collection (calls /api/user)
final user = await sdk.auth.me();
print(user['id']);
print(user['email']);

// With collection (verifies token belongs to that collection)
final userInCollection = await sdk.auth.me('users');
print(userInCollection['id']);
```

**REST API:**
```http
GET /api/user
Authorization: Bearer <token>
```

Or with collection verification:
```http
GET /api/collections/users/auth/me
Authorization: Bearer <token>
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "id": "01JABCDEF987654321",
    "email": "user@example.com",
    "verified": true,
    "created_at": "2024-01-10T15:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  }
}
```

## Logout

Revoke the current authentication token.

**JavaScript SDK:**
```javascript
await sdk.auth.logout('users');
console.log('Logged out successfully');
```

**Dart SDK:**
```dart
await sdk.auth.logout('users');
print('Logged out successfully');
```

**REST API:**
```http
DELETE /api/collections/users/auth/logout
Authorization: Bearer <token>
```

**Response:**
```json
{
  "message": "Success",
  "data": null
}
```

## Logout All Sessions

Revoke all active tokens for the authenticated user. This is useful for security-sensitive operations like password changes.

**JavaScript SDK:**
```javascript
await sdk.auth.logoutAll('users');
console.log('All sessions logged out');
```

**Dart SDK:**
```dart
await sdk.auth.logoutAll('users');
print('All sessions logged out');
```

**REST API:**
```http
DELETE /api/collections/users/auth/logout-all
Authorization: Bearer <token>
```

## User Management

Veloquentincludes built-in support for common user management tasks:

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

Veloquent integrates with Laravel Socialite to provide easy social logins (e.g., Google, GitHub). OAuth flows are managed via the `/api/oauth2` endpoints, allowing you to redirect users to a provider and exchange the callback for a Veloquentauth token. Providers can be configured via the `OAuthProviderController` and associated endpoints.

## Next Steps

After managing your users, you can build reactive applications using [Real-time Subscriptions](../realtime/realtime.md).
