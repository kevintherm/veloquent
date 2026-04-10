# Real-time Subscriptions

Veloquentallows you to build reactive applications by providing real-time updates for your records via WebSockets. Integration with Laravel Reverb ensures high-performance broadcasting of events whenever data changes.

## How it Works

When a record is created, updated, or deleted, Veloquentautomatically broadcasts a message to the relevant collection channel. Clients can subscribe to these channels to receive live updates without polling the API.

### Evaluation & Security

Realtime synchronization is governed by the same [API Rules](../security/api-rules.md) as the REST API:

- **Sub-second Fan-out**: The `php artisan realtime:worker` process listens for record change events and matches them against active subscriptions.
- **Dynamic Permission Check**: Every update is re-evaluated against the subscriber's `view` rules in real-time. If a subscriber no longer has access to a record, no broadcast is sent to their channel.
- **Requirement**: The `php artisan realtime:worker` command must be running (either via Supervisor or a persistent process) for real-time features to function.

## Subscribing to Updates

To start receiving updates, you must first subscribe to a collection. The SDK handles subscription management automatically, but you can also interact directly via the API.

### JavaScript SDK

```javascript
import { Veloquent } from '@veloquent/sdk';
import { createEchoAdapter } from '@veloquent/sdk/adapters/realtime/echo';
import Echo from 'laravel-echo';

// Configure Laravel Echo
const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https'
});

const sdk = new Veloquent({
  host: import.meta.env.VITE_API_URL,
  realtime: createEchoAdapter(echo)
});

// Subscribe to a collection and listen for updates
await sdk.realtime.subscribe('posts', { filter: 'status = "published"' }, (event, payload) => {
  if (event === 'record.created') {
    console.log('New record:', payload.record);
  }
});

// Stop listening when done
await sdk.realtime.unsubscribe('posts');
```

### Dart SDK

```dart
import 'package:veloquent_sdk/veloquent_sdk.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

// Initialize Pusher Channels Flutter instance
final pusher = PusherChannelsFlutter.getInstance();

final sdk = Veloquent(
  host: 'https://your-api.com',
  realtime: PusherChannelsAdapter(pusher),
);

// Subscribe to collection updates
await sdk.realtime.subscribe(
  'posts', 
  callback: (event, payload) {
    print('Event: $event');
    print('Record: ${payload['record']}');
  },
);

// Alternatively, subscribe with a filter
await sdk.realtime.subscribe(
  'posts', 
  callback: (event, payload) {
    print('Filtered event: $event');
  },
  filter: 'status = "published"',
);

// Unsubscribe when done
await sdk.realtime.unsubscribe('posts');
```

### REST API - Manual Subscription

If using plain REST API, first subscribe to get a channel name:

```http
POST /api/collections/posts/subscribe
Authorization: Bearer <token>
Content-Type: application/json

{
  "filter": "status = \"active\""
}
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "channel": "01JAB...private.posts",
    "expires_at": "2024-01-15T10:02:00Z"
  }
}
```

Then connect via WebSocket or Laravel Echo:

```javascript
window.Echo.private('01JAB...private.posts')
    .listen('.record.created', (e) => {
        console.log('New record:', e.record);
    })
    .listen('.record.updated', (e) => {
        console.log('Updated record:', e.record);
    })
    .listen('.record.deleted', (e) => {
        console.log('Deleted record:', e.record);
    });
```

**Subscription Metadata:**
- **TTL**: Subscriptions are temporary (default: 120 seconds).
- **Auto-Refresh**: Clients should periodically re-subscribe to maintain a continuous connection.
- **Garbage Collection**: Expired subscriptions are pruned automatically.
## Receiving Events

Once subscribed, your client receives real-time events for record changes. Typical events include:
- `record.created`: New record added.
- `record.updated`: Existing record modified.
- `record.deleted`: Record removed.

Event payloads include the full record data plus metadata:
```json
{
  "record": {
    "id": "01JABCDEF123456789",
    "title": "Updated Title",
    "status": "published",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:45:00Z"
  }
}
```

## Unsubscribing

To stop receiving updates, use the unsubscribe endpoint:
`DELETE /api/collections/{collection}/subscribe`

## Next Steps

Now that you've covered all the core features, explore [Records Management](../the-basics/records.md) or learn how to secure your data with [API Rules](../security/api-rules.md).
