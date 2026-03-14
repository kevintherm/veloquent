
## TODO

- Add API rules
    - Use symfony/expression-language [Deprecated]
    - Fix bug: lint api rules on create n update
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
- Hide fields e.g password, token_key from responses
- Add manage_rule to allow updating password field for auth collections
- Make jwt:secret command to generate jwt secret