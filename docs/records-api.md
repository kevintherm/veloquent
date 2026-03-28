# Records API Reference

Once you've defined your collections and secured them with API rules, you can start interacting with your data through the Velo Records API.

## Base URL

All records API endpoints are prefixed with `/api/collections/{collection}/records`.

## Dynamic Records

Velo uses a custom `Record` model that automatically adapts based on your collection schema. When you interact with the API, Velo handles field validation, data casting, and relation integrity for you.

## Endpoints

### List Records
`GET /api/collections/{collection}/records`

Returns a paginated list of records that match the applied API rules and query parameters.

**Query Parameters:**
- `filter`: A filter expression to narrow down the results (e.g., `active = true`).
- `sort`: A comma-separated list of fields for sorting (e.g., `-created_at,name`).
- `per_page`: Number of records per page (default: 15).
- `expand`: A comma-separated list of relation fields to expand (e.g., `userId`).

### Create Record
`POST /api/collections/{collection}/records`

Creates a new record in the collection. The request body should contain a JSON object with the record data.

### View Record
`GET /api/collections/{collection}/records/{record}`

Returns a single record by its ULID.

### Update Record
`PATCH /api/collections/{collection}/records/{record}`

Updates an existing record. The request body should contain a JSON object with the fields to update.

### Delete Record
`DELETE /api/collections/{collection}/records/{record}`

Deletes a record from the collection.

## Advanced Features

### Filtering
The `filter` query parameter allows you to narrow down your results using Velo's rule expression language. See [API Rules](api-rules.md) for more details.

### Sorting
Sorting is supported on any field defined in the collection, as well as on system fields like `id`, `created_at`, and `updated_at`. Use a hyphen (`-`) for descending order.

### Field Expansion
Relation fields can be expanded to include the related record data in the response. For example:
- `expand=userId`: Includes the related user record in the `expandedRelations` property.
- `expand=userId,profileId`: Expands multiple relations.

## Next Steps

For a detailed technical reference of all API endpoints, see the [API Reference](api.md).
