## TODO

- Add support for :changed, :isset, :length for api rules
- Battle Testing
- Ship

## Roadmap

- Validate json field strictly
- Add expand option to realtime
- Bypass rule for superuser in realtime worker
- Hooks system
- Schema SDK sync (Typed)
- Add support for indexing json and file field
- Add octane support

## Issues

- Admin panel: switching collections very quickly can trigger race conditions causing correct data to missplaced
- Admin panel: recreating a field will add "expand" object to the datatable columns