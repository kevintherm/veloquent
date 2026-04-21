## TODO

- Revamp error handling. 
    - Send explicit error code and message to client.
    - Map error code to a message string.
- Fix: Dart SDK was sending a Dart List for json field.
- Add fields configuration to admin panel
    - Number: min, max, allow_decimals
    - Text: min, max
    - Default values for text, number, boolean
- App settings
- Add deployment documentation
- Battle Testing
- Ship

## Roadmap

- Add expand option to realtime
- Add Select field type
- Add support for indexing json and file field
- Bypass rule for superuser in realtime worker
- Add octane support
- Hooks system

## Issues

- Admin panel: recreating a field will add "expand" object to the datatable columns