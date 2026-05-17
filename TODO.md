## TODO

- Add support for schema transfer to export relation many field types 
- Battle Testing
- Ship

## Roadmap

- Add support for :changed, :isset, :length for api rules
- Add expand option to realtime
- Add support for indexing json and file field
- Add oauth link setting for auth collection (per-user)
- Add octane support?
- Full app backup?
- Schema SDK sync (Typed)?

## Issues

- Admin panel: Implement AbortController to prevent race conditions when switching collections
- Admin panel: fix recreating a field adds "expand" object to columns

## SDK Compatibility

- Unify 422 response shape: Laravel FormRequest failures emit `{"message","errors"}` 
      while DomainValidationException emits `{"code","message","errors"}`. After SDK update 
      to handle the `code` key on all 422s, consider overriding FormRequest's 
      `failedValidation()` globally to inject `code: "VALIDATION_FAILED"` into the response.

## Docs

- RelationMany field type
- Extending