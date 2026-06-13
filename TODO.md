## TODO

- Fix: Cannot update collection name nor collection description
- Fix: Updating ai settings twice in admin panel sets the api key to null since the second time the api key is masked 
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
