# Real-time Subscriptions

Velo allows you to build reactive applications by providing real-time updates for your records via WebSockets. Integration with Laravel Reverb ensures high-performance broadcasting of events whenever data changes.

## How it Works

When a record is created, updated, or deleted, Velo automatically broadcasts a message to the relevant collection channel. Clients can subscribe to these channels to receive live updates without polling the API.

## Subscribing to Updates

To start receiving updates, you must first subscribe to a collection using the API.

### Subscribe Endpoint
`POST /api/collections/{collection}/subscribe`

**Optional Filter:**
You can provide a `filter` expression in the request body to only receive updates for records that match specific criteria (e.g., `status = "active"`).

**Response:**
The API returns a `channel` name that you should use with a WebSocket client (like Laravel Echo).

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

Now that you've covered all the core features, check the [API Reference](api.md) for a complete technical deep dive.
