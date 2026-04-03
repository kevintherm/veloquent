## TODO

- Testing
- Documentation
    - Quickstart
    - Separate internal implementation with usage
    - Remove redundant docs e.g Records API and API
- Ship it into a package

## Roadmap

- Add multi tenant support stancl/tenant
- Add octane support
- Circular relation detection for nested expand queries is not yet implemented. @RecordExpansionService
- Revamp entire QueryFilter and RuleEngine system
    - unify into single system
    - fix: relation values rule are somehow failing: `parent_comment.post = post` // failed
    - determine if null checking is reliable or should use "" empty string checking
    - removes complex unecessary modules, just adapters, tokenizer, and parser
- Hooks system?
