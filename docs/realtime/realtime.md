# Real-time Subscriptions

Velo allows you to build reactive applications by providing real-time updates for your records via WebSockets. Integration with Laravel Reverb ensures high-performance broadcasting of events whenever data changes.

## How it Works

When a record is created, updated, or deleted, Velo automatically broadcasts a message to the relevant collection channel. Clients can subscribe to these channels to receive live updates without polling the API.

### Evaluation & Security

Realtime synchronization is governed by the same [API Rules](../security/api-rules.md) as the REST API:

- **Sub-second Fan-out**: The `php artisan realtime:worker` process listens for record change events and matches them against active subscriptions.
- **Dynamic Permission Check**: Every update is re-evaluated against the subscriber's `view` rules in real-time. If a subscriber no longer has access to a record, no broadcast is sent to their channel.
- **Requirement**: The `php artisan realtime:worker` command must be running (either via Supervisor or a persistent process) for real-time features to function.

## Subscribing to Updates

To start receiving updates, you must first subscribe to a collection using the API.

### Subscribe Endpoint
`POST /api/collections/{collection}/subscribe`

**Optional Filter:**
You can provide a `filter` expression in the request body to only receive updates for records that match specific criteria (e.g., `status = "active"`).

**Response:**
The API returns a unique `channel` name (ULID-based) and an `expires_at` timestamp.

- **TTL**: Subscriptions are temporary (default: 120 seconds).
- **Auto-Refresh**: Clients should periodically re-subscribe to maintain a continuous connection.
- **Garbage Collection**: Expired subscriptions are pruned automatically via the `realtime:prune` scheduler command.

## Receiving Events

Once subscribed, your client will receive events for record changes in the collection. Typical events include:
- `create`: New record added.
- `update`: Existing record modified.
- `delete`: Record removed.

## Client Integration

Velo is designed to work seamlessly with **Laravel Echo**. You can listen for events on the provided channel:

```javascript
window.Echo.private(subscription.channel)
    .listen('.record.created', (e) => {
        console.log('New record:', e.record);
    })
    .listen('.record.updated', (e) => {
        console.log('Updated record:', e.record);
    });
```

## Unsubscribing

To stop receiving updates, use the unsubscribe endpoint:
`DELETE /api/collections/{collection}/subscribe`

## Next Steps

Now that you've covered all the core features, check the [API Reference](../api-documentation/api.md) for a complete technical deep dive.
