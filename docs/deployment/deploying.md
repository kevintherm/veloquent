# Deploying Veloquent

Deploying Veloquent is essentially the same as deploying any other Laravel application. Since Veloquent is built on top of Laravel, it follows all the standard production practices.

## Laravel Deployment Documentation

For the general deployment process, server configuration, and optimization tips, please refer to the official [Laravel Deployment Documentation](https://laravel.com/docs/12.x/deployment).

## Veloquent Specifics

While the core deployment is standard, Veloquent has a few specific components that must be configured for its real-time and multi-tenant features to function correctly.

### 1. Database Configuration

Veloquent uses a "Landlord" database to manage tenants and system-wide settings. Ensure your hosting environment is capable of hosting multiple databases, as Veloquent creates a dedicated database for each tenant.

### 2. Real-time Infrastructure

Veloquent relies on Laravel realtime provider for real-time broadcasting. Meaning you can bring any of [Laravel's supported real-time drivers](https://laravel.com/docs/13.x/broadcasting#supported-drivers).

If you do use Laravel Reverb:
- Ensure `BROADCAST_CONNECTION=reverb` is set in your `.env`.
- Refer to the [Reverb Deployment Guide](https://laravel.com/docs/12.x/reverb#deployment) for information on running the Reverb server and configuring SSL/Nginx proxies.

### 3. Background Workers

In addition to the standard Laravel queue worker, Veloquent requires a dedicated worker for handling real-time subscription fan-out.

#### Standard Queue Worker
Run the standard Laravel queue worker to process background jobs (emails, etc.):
```bash
php artisan queue:work
```

#### Real-time Fan-out Worker
Run the Veloquent real-time worker to handle live updates to connected clients:
```bash
php artisan realtime:worker
```

### 4. Supervisor Configuration

In production, you should use a process monitor like **Supervisor** to ensure these processes stay running. Below is an example configuration for the Veloquent-specific workers.

```ini
[program:veloquent-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/forge/app.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=8
redirect_stderr=true
stdout_logfile=/home/forge/app.com/storage/logs/worker.log
stopwaitsecs=3600

[program:veloquent-realtime]
process_name=%(program_name)s_%(process_num)02d
command=php /home/forge/app.com/artisan realtime:worker
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=1
redirect_stderr=true
stdout_logfile=/home/forge/app.com/storage/logs/realtime.log

[program:veloquent-reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /home/forge/app.com/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=1
redirect_stderr=true
stdout_logfile=/home/forge/app.com/storage/logs/reverb.log
```

## Summary Checklist

- [ ] Standard Laravel optimization (`config:cache`, `route:cache`, `view:cache`).
- [ ] Run landlord migrations: `php artisan migrate:fresh --path=database/migrations/landlord --force`.
- [ ] Ensure Redis is running (Recommended, used for caching and the real-time bus).
- [ ] Configure Supervisor for `reverb:start`, `queue:work`, and `realtime:worker`.