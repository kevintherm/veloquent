
## TODO

- Prevent modifying reserved fields on auth collection
    - e.g modifying/deleting email, email_visibility, verified, and password field
- Indexing system
    - Single field index
    - Composite index
    - Unique index
- Handle field editing
    - Rename field as old field
    - Creates new field
    - Backfill data
    - Delete old field
- Handle schema jobs failure
    - Rebuild associated table
    - Add routes for managing failed schema jobs
- Optimize Record model
    - cache collections
- Add manage_rule to allow updating password field for auth collections
- Make jwt:secret command to generate jwt secret

- Inconsistent RecordResource usage
- QueryFilter validation against allowed fields will check for both FIELD and VALUE
    - e.g id = id // pass id on the right will be parsed as string
    - e.g id = foo // failed, foo is not on the context/allowed fields