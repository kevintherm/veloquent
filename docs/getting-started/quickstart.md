# Installation

This guide will walk you through setting up Veloquent for development and production. Veloquent is a Laravel-based project, so it follows standard Laravel installation practices.

## Prerequisites

To run Veloquent, you need the following installed:

- **PHP 8.3** or higher
- **Composer** for dependency management
- **Docker** (Recommended for Sail) or a local web server with MariaDB/MySQL

---

## Getting Started with Laravel Sail

The easiest way to get started is by using Laravel Sail, a light-weight command-line interface for interacting with Veloquent's default Docker development environment.

### 1. Install Dependencies
Run the following command to install the required PHP packages:
```bash
composer install
```

### 2. Configure Environment
Copy the example environment file and generate an application key:
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Start the Environment
Launch the Docker containers in the background:
```bash
./vendor/bin/sail up -d
```

### 4. Database Setup
Run migrations and seed the database with initial data:
```bash
./vendor/bin/sail artisan migrate --seed
```

---

## Core Background Workers

Veloquent relies on long-running processes to handle real-time events and asynchronous tasks. Ensure these are running to use all features:

### 1. Real-time Worker
The `realtime:worker` handles subscription management and broadcasting. Without it, real-time updates won't function.
```bash
./vendor/bin/sail artisan realtime:worker
```
*Note: In production environments, it is recommended to run this command under a process monitor like Supervisor.*

### 2. Queue Worker
Standard Laravel queues are used for emails and other tasks.
```bash
./vendor/bin/sail artisan queue:work
```

---

## Manual Installation (No Docker)

If you prefer not to use Docker, follow these steps:

1. **Serve the Application**: Run `php artisan serve` or configure Nginx/Apache.
2. **Database**: Create a database and update the `DB_*` variables in your `.env` file.
3. **Migrations**: Run `php artisan migrate --seed`.
4. **Environment**: Ensure you have PHP 8.3+ with the necessary extensions.
