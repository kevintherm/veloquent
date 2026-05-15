## TODO

- Battle Testing
- Ship

## Roadmap

- Hooks system
- Optimize Realtime worker
    - set memory limit
- Manage .env.example structure
- Add support for :changed, :isset, :length for api rules
- Add expand option to realtime
- Add support for indexing json and file field
- Add octane support?
- Full app backup?
- Add oauth link setting for auth collection (per-user)
- Schema SDK sync (Typed)

## Issues

- Admin panel: Implement AbortController to prevent race conditions when switching collections
- Admin panel: fix recreating a field adds "expand" object to columns

## Testing
- Implement load testing to simulate high subscriber/tenant volume.
- Tenant leak tests: verify cross-tenant event isolation at the worker level.
- Parallel worker execution tests.