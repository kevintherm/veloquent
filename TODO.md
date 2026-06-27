## TODO

- Support strongly typed argument for actions e.g UpdateCOllectionAction fields is accepting array instead of Field vo
- PATCH supposed to modify only the field sent in the payload, while PUT is supposed to replace the entire resource. 
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
