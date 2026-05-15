## TODO

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

## Testing
- Implement load testing to simulate high subscriber/tenant volume.
- Tenant leak tests: verify cross-tenant event isolation at the worker level.
- Parallel worker execution tests.