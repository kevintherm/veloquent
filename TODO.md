## TODO

- Battle Testing
- Ship

## Roadmap

- Add support for :changed, :isset, :length for api rules
- Add expand option to realtime
- Hooks system
- Schema SDK sync (Typed)
- Add support for indexing json and file field
- Add oauth link setting for auth collection (per-user)
- Add octane support
- Full app backup?
- Optimize Realtime worker
    - in-memory caching for subscriber models
    - clear memory/efficient data structures
    - set memory limit
- Manage .env.example structure
- Add sync strategy for realtime worker?

## Issues

- Admin panel: Implement AbortController to prevent race conditions when switching collections
- Admin panel: fix recreating a field adds "expand" object to columns

## Testing
- Implement load testing to simulate high subscriber/tenant volume.
- Tenant leak tests: verify cross-tenant event isolation at the worker level.
- Parallel worker execution tests.