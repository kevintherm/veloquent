## TODO

- Setup system variables
    - Rate limit (defer)
    - Trust proxies (defer)
    - Mail settings (encrypted) (defer)
    - Storage settings (defer)
    - Backups (Full metadata n values)
        - Collections, tables, records
    - Export n imports
        - Collections metadata only
- feat: add multi tenant support stancl/tenant
- feat: add octane support
- Testing
- Documentation
- Ship it into a package
    - Hooks system?

- Circular relation detection for nested expand queries is not yet implemented. @RecordExpansionService
- feat: Revamp entire QueryFilter and RuleEngine system
    - unify into single system
    - fix: relation values rule are somehow failing: `parent_comment.post = post` // failed
    - determine if null checking is reliable or should use "" empty string checking
    - removes complex unecessary modules, just adapters, tokenizer, and parser
