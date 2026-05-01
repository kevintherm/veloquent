## TODO

- Fix: after running `artisan optimize` the app will somehow fail because sessions table not exists even though app does not use session
- Add support for :changed, :isset, :length for api rules
- Update filter parser to support date functions
- Battle Testing
- Ship

## Roadmap

- Dark mode to admin panel
- Schema SDK sync (Typed)
- Add expand option to realtime
- Add Select field type
- Add support for indexing json and file field
- Bypass rule for superuser in realtime worker
- Add octane support
- Hooks system

## Issues

- Admin panel: recreating a field will add "expand" object to the datatable columns