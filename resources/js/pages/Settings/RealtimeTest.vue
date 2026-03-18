<script setup>
import axios from "axios";
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label } from "@/components/ui";
import { useAuth } from "@/lib/auth";

const { state: authState, fetchUser } = useAuth();
const testCollection = ref("users");
const testFilter = ref("");
const realtimeStatus = ref("idle");
const realtimeEvents = ref([]);

let realtimeChannel = null;
let realtimeHeartbeatTimer = null;
const realtimeHeartbeatMs = 30000;

const privateChannelName = computed(() => {
    const userId = authState.user?.id;

    if (!userId) {
        return null;
    }

    return `superusers.${userId}`;
});

const pushRealtimeEvent = (label, payload = null) => {
    realtimeEvents.value.unshift({
        id: `${Date.now()}-${Math.random()}`,
        label,
        payload,
        at: new Date().toLocaleTimeString(),
    });

    realtimeEvents.value = realtimeEvents.value.slice(0, 20);
};

const detachRealtimeChannel = () => {
    if (realtimeHeartbeatTimer) {
        clearInterval(realtimeHeartbeatTimer);
        realtimeHeartbeatTimer = null;
    }

    if (window.Echo && privateChannelName.value) {
        window.Echo.leave(privateChannelName.value);
    }

    realtimeChannel = null;
};

const attachRealtimeChannel = () => {
    if (!window.Echo || !privateChannelName.value) {
        realtimeStatus.value = "echo-unavailable";
        return;
    }

    detachRealtimeChannel();

    realtimeChannel = window.Echo.private(privateChannelName.value);

    ["created", "updated", "deleted"].forEach((eventName) => {
        realtimeChannel.listen(`.record.${eventName}`, (payload) => {
            pushRealtimeEvent(`record.${eventName}`, payload);
        });
    });

    realtimeStatus.value = "listening";
};

const startRealtimeHeartbeat = () => {
    if (realtimeHeartbeatTimer) {
        clearInterval(realtimeHeartbeatTimer);
    }

    realtimeHeartbeatTimer = setInterval(async () => {
        try {
            await axios.post(`/api/collections/${testCollection.value}/subscribe`, {
                filter: testFilter.value || null,
            });

            pushRealtimeEvent("heartbeat-ok", {
                collection: testCollection.value,
            });
        } catch (error) {
            realtimeStatus.value = "heartbeat-error";
            pushRealtimeEvent("heartbeat-error", error?.response?.data || { message: error.message });
        }
    }, realtimeHeartbeatMs);
};

const subscribeRealtime = async () => {
    if (!authState.user?.id) {
        await fetchUser();
    }

    if (!authState.user?.id) {
        realtimeStatus.value = "missing-auth-user";
        pushRealtimeEvent("subscribe-failed", { reason: "Missing authenticated superuser" });
        return;
    }

    try {
        await axios.post(`/api/collections/${testCollection.value}/subscribe`, {
            filter: testFilter.value || null,
        });

        attachRealtimeChannel();
        startRealtimeHeartbeat();
        pushRealtimeEvent("subscribe-ok", {
            collection: testCollection.value,
            channel: privateChannelName.value,
            filter: testFilter.value,
        });
    } catch (error) {
        realtimeStatus.value = "subscribe-error";
        pushRealtimeEvent("subscribe-error", error?.response?.data || { message: error.message });
    }
};

const unsubscribeRealtime = async () => {
    try {
        await axios.delete(`/api/collections/${testCollection.value}/subscribe`);
        detachRealtimeChannel();
        realtimeStatus.value = "unsubscribed";
        pushRealtimeEvent("unsubscribe-ok", {
            collection: testCollection.value,
            channel: privateChannelName.value,
        });
    } catch (error) {
        realtimeStatus.value = "unsubscribe-error";
        pushRealtimeEvent("unsubscribe-error", error?.response?.data || { message: error.message });
    }
};

onMounted(async () => {
    await fetchUser();
});

onUnmounted(() => {
    detachRealtimeChannel();
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Realtime Test</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="rt-collection">Collection to watch</Label>
                    <Input id="rt-collection" v-model="testCollection" placeholder="users" />
                </div>
                <div class="space-y-2">
                    <Label for="rt-filter">Filter (optional)</Label>
                    <Input id="rt-filter" v-model="testFilter" placeholder='name = "John"' />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted-foreground">Auth collection:</span>
                <span class="font-medium">superusers</span>
                <span class="text-muted-foreground">User ID:</span>
                <span class="font-medium">{{ authState.user?.id ?? "n/a" }}</span>
                <span class="text-muted-foreground">Channel:</span>
                <span class="font-medium">{{ privateChannelName ?? "n/a" }}</span>
                <span class="text-muted-foreground">Status:</span>
                <span class="font-medium">{{ realtimeStatus }}</span>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button type="button" @click="subscribeRealtime">Subscribe & Listen</Button>
                <Button type="button" variant="outline" @click="unsubscribeRealtime">Unsubscribe</Button>
            </div>

            <div class="rounded-md border border-border bg-muted/30 p-3">
                <p class="mb-2 text-xs text-muted-foreground">Latest events (debug output)</p>
                <div class="max-h-56 space-y-2 overflow-auto text-xs">
                    <div
                        v-for="event in realtimeEvents"
                        :key="event.id"
                        class="rounded bg-background p-2 font-mono"
                    >
                        <div class="mb-1 text-[11px] text-muted-foreground">{{ event.at }} - {{ event.label }}</div>
                        <pre class="whitespace-pre-wrap break-all">{{ JSON.stringify(event.payload, null, 2) }}</pre>
                    </div>
                    <p v-if="!realtimeEvents.length" class="text-muted-foreground">
                        No events yet.
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
