# Velo Revamp API

Base URL: `/api`  
Auth: Bearer token (opaque) via `Authorization: Bearer <token>`

---

## Common Response Envelope

All endpoints (except `GET /user`) wrap their payload in:

```json
{
  "message": "Success",
  "data": { },
  "meta": { }
}
```

`meta` is only present on paginated responses.

**Error shape:**
```json
{
  "message": "Validation error",
  "errors": { }
}
```

---

## Pagination Meta

Returned in `meta` on list endpoints.

| Field | Type | Description |
|---|---|---|
| `current_page` | integer | |
| `last_page` | integer | |
| `per_page` | integer | |
| `total` | integer | |
| `from` | integer\|null | |
| `to` | integer\|null | |
| `has_more_pages` | boolean | |

---

## Status Codes

| Code | Meaning |
|---|---|
| `200` | OK |
| `201` | Created |
| `400` | Bad request |
| `401` | Unauthenticated / token doesn't match collection |
| `403` | Forbidden |
| `404` | Not found |
| `409` | Conflict (resource already exists) |
| `422` | Validation failed |

---

## Collections

### `GET /collections`

List all collections.

**Query params:**

| Param | Type | Required | Description |
|---|---|---|---|
| `filter` | string | no | Filter expression |

**Response `data`:** array of [Collection](#collection-object)

---

### `POST /collections`

Create a new collection.

**Body:** [CollectionCreateRequest](#collectioncreaterequest)

**Response `data`:** [Collection](#collection-object)

---

### `GET /collections/{collection}`

Get a single collection by ULID or name.

**Response `data`:** [Collection](#collection-object)

---

### `PUT /collections/{collection}`
### `PATCH /collections/{collection}`

Update a collection (both verbs have identical behavior).

**Body:** [CollectionUpdateRequest](#collectionupdaterequest)

**Response `data`:** [Collection](#collection-object)

---

### `DELETE /collections/{collection}`

Delete a collection. Cannot delete the default auth collection.

**Response `data`:** `[]`

---

### `DELETE /collections/{collection}/truncate`

Delete all records in a collection. For auth collections, also revokes all associated tokens.

**Response `data`:**
```json
{ "deleted": 42 }
```

---

## Records

### `GET /collections/{collection}/records`

List paginated records for a collection.

**Query params:**

| Param | Type | Required | Default | Description |
|---|---|---|---|---|
| `filter` | string | no | — | Filter expression |
| `per_page` | integer | no | 15 | Records per page (min: 1, max default: 500) |

**Response `data`:** array of [Record](#record-object)  
**Response `meta`:** [PaginationMeta](#pagination-meta)

---

### `POST /collections/{collection}/records`

Create a record. Fields are validated against the collection schema at runtime.

**Body:** [RecordInput](#recordinput)

**Response `data`:** [Record](#record-object)

---

### `GET /collections/{collection}/records/{record}`

Get a single record by ULID.

**Response `data`:** [Record](#record-object)

---

### `PUT /collections/{collection}/records/{record}`
### `PATCH /collections/{collection}/records/{record}`

Update a record (both verbs have identical behavior).

**Body:** [RecordInput](#recordinput)

**Response `data`:** [Record](#record-object)

---

### `DELETE /collections/{collection}/records/{record}`

Delete a record.

**Response `data`:** `[]`

---

## Authentication

Auth endpoints are scoped to a collection. The collection must be of type `auth`.

### `POST /collections/{collection}/auth/login`

Login and issue an opaque bearer token. No auth required.

**Body:**

| Field | Type | Required |
|---|---|---|
| `email` | string (email) | yes |
| `password` | string | yes |

**Response `data`:**

| Field | Type | Description |
|---|---|---|
| `token` | string | Opaque bearer token |
| `expires_in` | integer | Lifetime in seconds |
| `collection_name` | string | |

---

### `DELETE /collections/{collection}/auth/logout`

Revoke the current token. Requires auth.

**Response `data`:** `[]`

---

### `DELETE /collections/{collection}/auth/logout-all`

Revoke all tokens for the currently authenticated record. Requires auth.

**Response `data`:** `[]`

---

### `GET /collections/{collection}/auth/me`

Get the currently authenticated record scoped to the given collection. Returns `401` if the token belongs to a different collection.

**Response `data`:** [Record](#record-object)

---

### `GET /user`

Returns the raw authenticated user model. **Does not use the common success envelope.**

**Response:** [UserPayload](#userpayload) (arbitrary object, fields vary)

---

## Onboarding

### `POST /onboarding/initialized`

Check whether the superuser account has been created. No auth required.

**Response `data`:** `true` | `false`

---

### `POST /onboarding/superuser`

Create the initial superuser. No auth required. Returns `409` if superuser already exists.

**Body:**

| Field | Type | Required | Constraints |
|---|---|---|---|
| `name` | string | yes | max 255 |
| `email` | string (email) | yes | max 255 |
| `password` | string | yes | min 8 chars |

**Response `data`:**

| Field | Type |
|---|---|
| `id` | string |
| `name` | string |
| `email` | string |
| `created_at` | datetime\|null |

---

## Realtime

### `POST /collections/{collection}/subscribe`

Subscribe the authenticated user to realtime updates for a collection.

**Body (optional):**

| Field | Type | Required | Description |
|---|---|---|---|
| `filter` | string\|null | no | Filter expression stored with subscription (max 500 chars) |

**Response `data`:**

| Field | Type | Example |
|---|---|---|
| `status` | string | `subscribed` |
| `channel` | string | `private-superusers.01JAB...` |
| `expires_at` | datetime | |

---

### `DELETE /collections/{collection}/subscribe`

Unsubscribe the authenticated user from realtime updates.

**Response `data`:**

| Field | Type | Example |
|---|---|---|
| `status` | string | `unsubscribed` |

---

## Schemas

### Collection Object

| Field | Type | Notes |
|---|---|---|
| `id` | string | ULID |
| `type` | `base` \| `auth` | |
| `is_system` | boolean | |
| `name` | string | |
| `description` | string\|null | |
| `fields` | array of [CollectionField](#collectionfield) | |
| `api_rules` | object\|null | Keys: `list`, `view`, `create`, `update`, `delete` — values are filter expression strings |
| `indexes` | array\|null | |
| `schema_updated_at` | datetime\|null | |
| `created_at` | datetime\|null | |
| `updated_at` | datetime\|null | |

### CollectionField

| Field | Type | Notes |
|---|---|---|
| `id` | string\|null | |
| `name` | string | |
| `type` | string | See field types below |
| `order` | integer | |
| `nullable` | boolean | |
| `unique` | boolean | |
| `default` | any\|null | |
| `min` | any\|null | |
| `max` | any\|null | |
| `collectionId` | any\|null | For relation fields |
| `collection` | any\|null | For relation fields |
| `cascadeOnDelete` | boolean\|null | For relation fields |
| `maxSelect` | integer\|null | For relation fields |

**Field types:** `text`, `longtext`, `number`, `boolean`, `timestamp`, `email`, `url`, `json`, `relation`

**Index types:** `index`, `unique`

---

### CollectionCreateRequest

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | yes | `^[a-zA-Z_]+$`, max 255 |
| `type` | `base` \| `auth` | yes | |
| `description` | string\|null | no | |
| `api_rules` | object\|null | no | Keys: `list`, `view`, `create`, `update`, `delete` |
| `fields` | array | yes | Min 1 item. See [CollectionFieldRequest](#collectionfieldrequest) |
| `indexes` | array\|null | no | See [CollectionIndexRequest](#collectionindexrequest) |

### CollectionUpdateRequest

Same fields as create, all optional. No additional properties allowed.

---

### CollectionFieldRequest

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | string | no | Used to match existing fields on update |
| `name` | string | yes | `^[a-zA-Z_]+$` |
| `type` | string | yes | See field types |
| `nullable` | boolean | no | |
| `unique` | boolean | no | |
| `default` | any\|null | no | |
| `min` | any\|null | no | |
| `max` | any\|null | no | |
| `collection` | string\|null | no | Related collection name, for `relation` fields |
| `cascadeOnDelete` | boolean\|null | no | For `relation` fields |
| `maxSelect` | integer\|null | no | For `relation` fields |

### CollectionIndexRequest

| Field | Type | Required | Notes |
|---|---|---|---|
| `columns` | string[] | yes | Min 1, each `^[a-zA-Z_]+$` |
| `type` | `index` \| `unique` | yes | |

---

### Record Object

Base fields always present:

| Field | Type |
|---|---|
| `id` | string (ULID) |
| `collection_id` | string |
| `collection_name` | string |
| `created_at` | datetime\|null |
| `updated_at` | datetime\|null |

Additional fields are dynamic and depend on the collection schema.

### RecordInput

Arbitrary key-value object. Exact fields are validated against the collection schema at runtime.

### UserPayload

Arbitrary object — fields depend on the superuser model.