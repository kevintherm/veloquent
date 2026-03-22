## TODO

- Parse logs from unformatted log as message and context
    ```
    // [2025-03-20 12:34:56] production.ERROR: Something failed {"key":"val"} []
    $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/';

    if (preg_match($pattern, $line, $m)) {
        return [
            'timestamp'   => $m[1],
            'environment' => $m[2],
            'level'       => strtolower($m[3]),
            'message'     => $m[4],
        ];
    }
    ```
- UI Dashboard
    - Deleting existing fields improvements:
        - Mark field as deleted, when sending the payload strip the field from the payload
        - Field mark as deleted so user can revert the changes, before actually sending the request
    - Manage systems
        - Schema corrupt fix button
        - Setup system variables
            - Rate limit
            - Trust proxies
            - Mail settings (encrypted)
            - Storage settings
            - Backups
                - Collections, tables, records
            - Export n imports
                - Collections metadata only
    - View Logs
- fix: Truncating collection bypasses RelationIntegrityService
- fix: inconsistent validation and exception messages
- Unify errors
    - Creating/Updating uses ValidationException
    - Other errors use regular exception
- improv: refactor redundant extractIndexes() methods
- RecordExpansionService revise
    - resolveTargetCollection N+1 on collection lookup
    - No expand field count limit
    - expandedRelations as dynamic property 
    - Field|array type inconsistency
- Naming conventions
    - clarify in code/docs: `SchemaChangePlan::getAllReservedFields(false)` in unique sync intentionally means base reserved fields only
    - reason: auth reserved fields (for example `email`) must still sync `unique` from single-column unique indexes
- Store cached target_collection_name for field type relation
- Hooks system
- Optimize Record model
    - cache collections
- Add manage_rule to allow updating password field for auth collections
- Testing
- Documentation