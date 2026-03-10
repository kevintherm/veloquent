
## TODO

- Handle truncation when changing field type
- Handle schema jobs failure
    - Rebuild associated table
    - Add routes for managing failed schema jobs
- Optimize Record model
    - from: 1. fetch collection, fetch record
    - to: fetch collection join record where collection name = ? and record id = ?
- Hide fields e.g password, token_key from responses
- Add manage_rule to allow updating password field for auth collections
- Make jwt:secret command to generate jwt secret