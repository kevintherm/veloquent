
## TODO

- Apply api_rule to create record action
- Revamp fields handling
    - No silent ignore all properties
    - Save order
    - Include reserved fields on metadata
- Automatically handle auth collection's reserved fields (email, password, token_key, verified, email_visibility)
- Prevent modifying reserved fields on auth collection
    - e.g modifying/deleting email, email_visibility, verified, and password field
- Indexing system
    - Single field index
    - Composite index
    - Unique index
- UI Dashboard
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
- Testing
- Documentation

- Migrate away from JWT to Sessions
- Inconsistent RecordResource usage
- QueryFilter validation against allowed fields will check for both FIELD and VALUE
    - e.g id = id // pass id on the right will be parsed as string
    - e.g id = foo // failed, foo is not on the context/allowed fields

## Known Issue

- Possibility of partial updating table if later statements fails. (MYSQL)
    - Solution: 
        - Recreate entire table