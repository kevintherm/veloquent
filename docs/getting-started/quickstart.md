# Getting Started

This guide will walk you through setting up the Veloquent and integrating with your client application.

## Server Setup

### Prerequisites

To run Veloquent, you need the following installed:

- **PHP 8.3+**
- **Composer**
- **Docker** (Optional)

### Getting Started with Composer

The easiest way to get started is to create a new Veloquent project:

1. **Create a new Veloquent project**
```bash
composer create-project veloquent/veloquent app-name
# The composer post install script will run the migrations and seed the database automatically.
# By default, the script will instantiate a tenant with the domain localhost, and it is recommended to use localhost in your browser as 127.0.0.1 can be unreliable.
```

2. **Start the server**
```bash
php artisan serve
```

The server is now available at [http://localhost:8000](http://localhost:8000).

### Getting Started with Git Clone

1. **Clone the repository**
```bash
git clone https://github.com/kevintherm/veloquent
rm -rf .git # Remove upstream git folder
```

2. **Configure and Install dependencies**
```bash
cp .env.example .env
php artisan key:generate
composer install
```

3. **Instantiate the first tenant**
```bash
php artisan tenant:create my-app --domain=localhost --database=myapp
```

4. **Start the server**
```bash
php artisan serve
```

The server is now available at [http://localhost:8000](http://localhost:8000).

### Core Background Workers

Veloquent relies on long-running processes to handle real-time events and asynchronous tasks. Ensure these are running to use all features:

```bash
php artisan realtime:worker
```

*Note: In production environments, it is recommended to run this command under a process monitor like Supervisor.*

---

## Client Setup

### Install the SDK

Install the Veloquent SDK for your platform:

**JavaScript/TypeScript (npm):**
```bash
npm install @veloquent/sdk
```

**Dart/Flutter (pub):**
```bash
flutter pub add veloquent_sdk
```

### Initialize the SDK

**JavaScript:**
```javascript
import { Veloquent, createFetchAdapter, createLocalStorageAdapter } from '@veloquent/sdk'

const sdk = new Veloquent({
  apiUrl: 'https://api.example.com',
  http: createFetchAdapter(),
  storage: createLocalStorageAdapter()
})
```

**Dart/Flutter:**
```dart
import 'package:veloquent_sdk/veloquent_sdk.dart';

final sdk = Veloquent(
  apiUrl: 'https://your-api.com',
  http: createFetchAdapter(),
  storage: createSecureStorageAdapter(/* SecureStorage */),
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
