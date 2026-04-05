# Getting Started

This guide will walk you through setting up the Veloquentbackend and integrating with your client application.

## Server Setup

### Prerequisites

To run Velo, you need the following installed:

- **PHP 8.3+**
- **Composer**
- **Docker** (Optional)

### Getting Started with Laravel Sail

The easiest way to get started is by using Laravel Sail, a light-weight command-line interface for interacting with Veloquent's default Docker development environment.

#### 0. Install Veloquent

Using composer:
```bash
composer create-project veloquent/veloquent app-name
```

Or, git clone the repository:
```bash
git clone https://github.com/kevintherm/veloquent
```

#### 1. Install Dependencies
Run the following command to install the required PHP packages:
```bash
composer install
```

#### 2. Configure Environment
Copy the example environment file and generate an application key:
```bash
cp .env.example .env
php artisan key:generate
```

#### 3. Start the Environment
Launch the Docker containers in the background:
```bash
./vendor/bin/sail up -d
```

#### 4. Database Setup
Run migrations and seed the database with initial data:
```bash
./vendor/bin/sail artisan migrate --seed
```

### Core Background Workers

Veloquentrelies on long-running processes to handle real-time events and asynchronous tasks. Ensure these are running to use all features:

#### 1. Real-time Worker
The `realtime:worker` handles subscription management and broadcasting. Without it, real-time updates won't function.
```bash
./vendor/bin/sail artisan realtime:worker
```
*Note: In production environments, it is recommended to run this command under a process monitor like Supervisor.*

#### 2. Queue Worker
Standard Laravel queues are used for emails and other tasks.
```bash
./vendor/bin/sail artisan queue:work
```

### Manual Installation (No Docker)

If you prefer not to use Docker, follow these steps:

1. **Serve the Application**: Run `php artisan serve` or configure Nginx/Apache.
2. **Database**: Create a database and update the `DB_*` variables in your `.env` file.
3. **Migrations**: Run `php artisan migrate --seed`.
4. **Environment**: Ensure you have PHP with the necessary extensions.

---

## Client Setup

### Install the SDK

Install the Veloquent SDK for your platform:

**JavaScript/TypeScript (npm):**
```bash
npm install @veloquent/sdk
# or
yarn add @veloquent/sdk
```

**Dart/Flutter (pub):**
```bash
flutter pub add veloquent_sdk
```

### Initialize the SDK

**JavaScript:**
```javascript
import Veloquent from '@veloquent/sdk';
import { createFetchAdapter } from '@veloquent/sdk/adapters/http';
import { createLocalStorageAdapter } from '@veloquent/sdk/adapters/storage';

const sdk = new Veloquent({
  apiUrl: 'http://localhost:8000',
  http: createFetchAdapter(),
  storage: createLocalStorageAdapter()
});
```

**Dart/Flutter:**
```dart
import 'package:veloquent_sdk/veloquent_sdk.dart';

final sdk = Veloquent(
  apiUrl: Uri.parse('http://localhost:8000'),
  http: createFetchAdapter(),
  storage: await createLocalStorageAdapter(),
);
```

### Authenticate

Log in a user to get an authentication token:

**JavaScript:**
```javascript
try {
  const { token } = await sdk.auth.login('users', 'user@example.com', 'password');
  console.log('Logged in successfully');
} catch (error) {
  console.error('Login failed:', error.message);
}
```

**Dart/Flutter:**
```dart
try {
  final authData = await sdk.auth.login('users', 'user@example.com', 'password');
  print('Logged in successfully');
} catch (error) {
  print('Login failed: $error');
}
```

### Perform CRUD Operations

Now you can start working with your data. See the [Records guide](../the-basics/records.md) for detailed examples of listing, creating, updating, and deleting records.

**JavaScript:**
```javascript
// List records
const posts = await sdk.records.list('posts', { sort: '-created_at' });

// Create a record
const newPost = await sdk.records.create('posts', {
  title: 'My First Post',
  content: 'Hello, World!'
});

// Get a specific record
const post = await sdk.records.get('posts', newPost.id);

// Update a record
const updated = await sdk.records.update('posts', newPost.id, {
  status: 'published'
});

// Delete a record
await sdk.records.delete('posts', newPost.id);
```

**Dart/Flutter:**
```dart
// List records
final posts = await sdk.records.list('posts', sort: '-created_at');

// Create a record
final newPost = await sdk.records.create('posts', {
  'title': 'My First Post',
  'content': 'Hello, World!'
});

// Get a specific record
final post = await sdk.records.get('posts', newPost.id);

// Update a record
final updated = await sdk.records.update('posts', newPost.id, {
  'status': 'published'
});

// Delete a record
await sdk.records.delete('posts', newPost.id);
```

---

## Next Steps

- Explore the [Records API](../the-basics/records.md) for advanced querying, filtering, and sorting
- Learn about [Authentication](../security/authentication.md) and user management
- Set up [Real-time Subscriptions](../realtime/realtime.md) for live data updates
- Secure your data with [API Rules](../security/api-rules.md)
