# Multi-Tenancy

Veloquent is built from the ground up as a multi-tenant platform in mind. This means you can host multiple separate organizations (tenants) on a single Veloquent installation, ensuring complete data isolation and performance for each one.

## What is Multi-Tenancy?

Multi-tenancy allows you to serve multiple "tenants" from a single application instance. In Veloquent, each tenant is a completely isolated environment with its own:

- Each tenant has its own dedicated **database**.
- Uploads and assets are stored in tenant-specific **directories**.
- **Cache** keys are automatically prefixed to prevent collisions.
- **Logs** are separated by tenant for easier debugging.
- Each tenant is identified by a unique domain or subdomain.

## How it Works

Veloquent uses a **Landlord/Tenant** architecture:

- **Landlord**: A central database that manages the global state, including the list of tenants and their configuration.
- **Tenant**: An isolated environment that contains the actual application data (collections, records, schema).

### Automatic Context Switching

When a request enters Veloquent, the system automatically identifies the tenant based on the request's hostname (e.g., `acme.velophp.com`). Once identified, Veloquent performs a "context switch" that seamlessly reconfigures the underlying services:

### Performance & Caching

To ensure near-zero overhead, Veloquent caches tenant resolution. This means the system doesn't need to query the Landlord database on every request to find out who the current tenant is, keeping the request lifecycle extremely fast.

## Using Multi-Tenancy

Tenant management is primarily handled via Artisan commands.

### Creating a Tenant

To create a new tenant, use the `tenants:create` command:

```bash
php artisan tenants:create "Acme Corp" --domain="acme.example.com"
```

Arguments:
- `name`: The display name of the tenant.

Options:
- `--domain`: The domain assigned to the tenant. If omitted, a slug-based domain will be generated.
- `--database`: The name of the database for this tenant. Defaults to a prefixed version of the name.

When you create a tenant, Veloquent automatically:
1. Provisions a new database.
2. Runs all tenant migrations.
3. Sets up the initial directory structure.

### Deleting a Tenant

To remove a tenant and its associated data:

```bash
sail artisan tenants:delete {id|domain|database}
```

> [!CAUTION]
> This command will permanently drop the tenant's database and remove their records. Use with extreme caution.

### Purging All Tenants

For development or reset purposes, you can purge all tenants at once:

```bash
sail artisan tenants:purge
```
