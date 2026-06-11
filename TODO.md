## TODO

- Offline supports for SDK
- Convert all datetime to UTC on before request and after response SDKs (date field type unaffected)
- Battle Testing

## Roadmap

- Implement model observer on user records to invalidate cached tokens on profile/attribute updates
- Implement explicit database check for sensitive operations (bypassing the token cache)
- Add support for :changed, :isset, :length for api rules
- Add expand option to realtime
- Add support for indexing json and file field
- Add oauth link setting for auth collection (per-user)
- Add octane support?
- Full db backup?
- Schema SDK sync (Typed)?
