# Working with Records

Records are the data entries within your collections. This guide covers how to interact with records using the Veloquent SDKs and REST API.

## Overview

Each record is a JSON object that contains:
- All fields defined in your collection schema
- System metadata: `id` (ULID), `created_at`, `updated_at`
- Automatically validated against your collection's field definitions
- Secured by your collection's API rules

## List Records

Retrieve a paginated list of records from a collection with optional filtering, sorting, and expansion.

**JavaScript SDK:**
```javascript
const records = await sdk.records.list('posts', {
  filter: 'status = "published"',
  sort: '-created_at',
  per_page: 10,
  expand: 'author'
});

// Access records and pagination metadata
records.forEach(record => {
  console.log(record.id, record.title);
});
console.log(records.meta); // { current_page, last_page, total, ... }
```

**Dart SDK:**
```dart
final result = await sdk.records.list('posts',
  filter: 'status = "published"',
  sort: '-created_at',
  perPage: 10,
  expand: 'author'
);

// Access records and metadata
for (var record in result.data) {
  print('${record.id}: ${record.get('title')}');
}
print(result.meta); // pagination metadata
```

**REST API:**
```http
GET /api/collections/posts/records?filter=status="published"&sort=-created_at&per_page=10&expand=author
Authorization: Bearer <token>
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `filter` | string | Filter expression using rule syntax (e.g., `status = "published"`) |
| `sort` | string | Comma-separated fields to sort by; prefix with `-` for descending (e.g., `-created_at,name`) |
| `per_page` | integer | Records per page (default: 15) |
| `page` | integer | Page number (default: 1) |
| `expand` | string | Comma-separated relation fields to expand (e.g., `author,tags`) |

**Response:**
```json
{
  "message": "Success",
  "data": [
    {
      "id": "01JABCDEF123456789",
      "title": "First Post",
      "status": "published",
      "author": "01JABCDEF987654321",
      "expand": {
        "author": {
          "id": "01JABCDEF987654321",
          "name": "John Doe"
        }
      },
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 42,
    "from": 1,
    "to": 10,
    "has_more_pages": true
  }
}
```

## Get Single Record

Retrieve a specific record by its ID.

**JavaScript SDK:**
```javascript
const record = await sdk.records.get('posts', '01JABCDEF123456789', {
  expand: 'author'
});

console.log(record.title);
console.log(record.expand.author); // Expanded relation
```

**Dart SDK:**
```dart
final record = await sdk.records.get('posts', '01JABCDEF123456789',
  expand: 'author'
);

print(record.get('title'));
print(record.get('expand')); // Expanded relation
```

**REST API:**
```http
GET /api/collections/posts/records/01JABCDEF123456789?expand=author
Authorization: Bearer <token>
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "id": "01JABCDEF123456789",
    "title": "First Post",
    "status": "published",
    "author": "01JABCDEF987654321",
    "expand": {
      "author": {
        "id": "01JABCDEF987654321",
        "name": "John Doe"
      }
    },
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  }
}
```

## Create Record

Create a new record in a collection. Field validation and casting are applied automatically based on your collection schema.

**JavaScript SDK:**
```javascript
const newRecord = await sdk.records.create('posts', {
  title: 'My New Post',
  content: 'This is the content of my post.',
  status: 'draft',
  author: 'user-id-here'
});

console.log(newRecord.id); // Newly assigned ULID
console.log(newRecord.created_at);
```

**Dart SDK:**
```dart
final newRecord = await sdk.records.create('posts', {
  'title': 'My New Post',
  'content': 'This is the content of my post.',
  'status': 'draft',
  'author': 'user-id-here'
});

print(newRecord.id); // Newly assigned ULID
print(newRecord.get('created_at'));
```

**REST API:**
```http
POST /api/collections/posts/records
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "My New Post",
  "content": "This is the content of my post.",
  "status": "draft",
  "author": "user-id-here"
}
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "id": "01JABCDEF123456789",
    "title": "My New Post",
    "content": "This is the content of my post.",
    "status": "draft",
    "author": "user-id-here",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  }
}
```

## Update Record

Update specific fields of an existing record. Only provided fields are modified.

**JavaScript SDK:**
```javascript
const updated = await sdk.records.update('posts', '01JABCDEF123456789', {
  status: 'published',
  updated_by: 'user-id-here'
});

console.log(updated.status); // 'published'
console.log(updated.title); // Unchanged fields remain
```

**Dart SDK:**
```dart
final updated = await sdk.records.update('posts', '01JABCDEF123456789', {
  'status': 'published',
  'updated_by': 'user-id-here'
});

print(updated.get('status')); // 'published'
print(updated.get('title')); // Unchanged fields remain
```

**REST API:**
```http
PATCH /api/collections/posts/records/01JABCDEF123456789
Authorization: Bearer <token>
Content-Type: application/json

{
  "status": "published",
  "updated_by": "user-id-here"
}
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "id": "01JABCDEF123456789",
    "title": "My New Post",
    "content": "This is the content of my post.",
    "status": "published",
    "author": "user-id-here",
    "updated_by": "user-id-here",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T11:45:00Z"
  }
}
```

## Delete Record

Permanently delete a record from a collection.

**JavaScript SDK:**
```javascript
await sdk.records.delete('posts', '01JABCDEF123456789');
console.log('Record deleted successfully');
```

**Dart SDK:**
```dart
await sdk.records.delete('posts', '01JABCDEF123456789');
print('Record deleted successfully');
```

**REST API:**
```http
DELETE /api/collections/posts/records/01JABCDEF123456789
Authorization: Bearer <token>
```

**Response:**
```json
{
  "message": "Success",
  "data": null
}
```

## Advanced Features

### Filtering

The `filter` parameter uses Veloquent's expression-based rule syntax to narrow down results. For detailed information on filter syntax, see [API Rules](../security/api-rules.md).

**Examples:**
- `status = "published"` - Exact match
- `created_at > "2024-01-01"` - Date comparison
- `active = true AND verified = true` - Logical operators
- `NOT deleted` - Negation
- `email LIKE "%@example.com"` - Pattern matching

### Sorting

Results can be sorted by any field in your collection, as well as system fields (`id`, `created_at`, `updated_at`). Prefix field names with `-` for descending order.

**Examples:**
- `sort: '-created_at'` - Newest first
- `sort: 'name,-age'` - By name ascending, then age descending

### Field Expansion

Relation fields can be expanded to include the full related record data in a nested `expand` object, while the foreign key ID remains at the top level of the record.

**Expansion Behavior:**
- **Foreign Key at Top Level**: The original relation ID (foreign key) remains at the top level of the record (e.g., `\"author\": \"01JBC...\"`)
- **Expanded Data in Object**: When you pass `expand=author`, the full record is included in the `expand` object (e.g., `\"expand\": {\"author\": {...}}`)
- **Security Applied**: The target collection's `view` API rules are applied to expanded records
- **Null on Denied**: If access is denied, the expanded record is `null` but the FK remains visible at the top level
- **Missing Records**: If the related record doesn't exist, the expanded entry is `null` in the expand object
- **Maximum Expansions**: Up to 10 relation expansions per request

**Example Response with Expansion:**
```json
{
  "id": "01JAB...",
  "title": "Post Title",
  "author": "01JBC...",
  "expand": {
    "author": {
      "id": "01JBC...",
      "name": "John Doe",
      "email": "john@example.com"
    }
  },
  "created_at": "2024-01-15T10:30:00Z"
}
```

### File Fields in Record Responses

File fields are normalized in API responses.

- Single file field: returns one object or `null`.
- Multiple file field: returns an array of file objects.

Each file object has this shape:

```json
{
  "name": "avatar.png",
  "path": "uploads/users/01J.../avatar.png",
  "size": 25123,
  "extension": "png",
  "mime": "image/png",
  "protected": true,
  "url": "https://example.test/api/collections/users/records/01J.../files/avatar?path=uploads%2Fusers%2F01J...%2Favatar.png"
}
```

### Accessing Protected Files

When a file field is configured with `protected: true`, the `url` points to an authenticated proxy endpoint:

```http
GET /api/collections/{collection}/records/{record}/files/{field}?path={encoded-storage-path}
Authorization: Bearer <token>
```

Behavior:

- Requires a valid bearer token (`auth:api`).
- Enforces the collection `view` API rule for the target record.
- Ensures the field exists, is type `file`, and is marked `protected`.
- Ensures the requested `path` belongs to the file metadata stored on that record.

Use the `url` returned by the records API instead of building the proxy URL manually.

If you render files in browser contexts where headers are not automatically sent (for example plain `<img src>` to external URLs), fetch the file with `Authorization: Bearer <token>` and then render the resulting blob/object URL.

## Error Handling

### SDK Error Handling

**JavaScript:**
```javascript
import { SdkError } from '@veloquent/sdk';

try {
  const record = await sdk.records.get('posts', 'invalid-id');
} catch (error) {
  if (error instanceof SdkError) {
    console.error('Status:', error.status); // e.g., 404, 403
    console.error('Message:', error.message);
    console.error('Errors:', error.errors); // Validation errors
  }
}
```

**Dart:**
```dart
import 'package:veloquent_sdk/veloquent_sdk.dart';

try {
  final record = await sdk.records.get('posts', 'invalid-id');
} catch (error) {
  if (error is SdkError) {
    print('Status: ${error.status}'); // e.g., 404, 403
    print('Message: ${error.message}');
    print('Errors: ${error.errors}'); // Validation errors
  }
}
```

### Common Status Codes

| Status | Meaning |
|--------|---------|
| `200` | Success |
| `201` | Created |
| `400` | Bad request / Invalid filter syntax |
| `401` | Unauthenticated / Invalid token |
| `403` | Forbidden / API rules deny access |
| `404` | Record or collection not found |
| `422` | Validation failed |

## Next Steps

- Learn about [Real-time Subscriptions](../realtime/realtime.md) to receive live updates
- Secure your data with [API Rules](../security/api-rules.md)
- Understand [Authentication](../security/authentication.md) for user management
