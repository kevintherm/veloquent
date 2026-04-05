<script setup>
import axios from "axios";
import { ref, computed } from "vue";
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label, Separator } from "@/components/ui";
import { Timer, Key, Package } from "lucide-vue-next";

const methods = ["GET", "POST", "PUT", "PATCH", "DELETE"];
const selectedMethod = ref("GET");
const apiUrl = ref("/api/collections");
const requestBody = ref("");
const customToken = ref("");
const useCustomToken = ref(false);
const responseStatus = ref(null);
const responseData = ref(null);
const responseSize = ref(null);
const responseTime = ref(null);
const loading = ref(false);
const bodyError = ref(null);

const hasPayload = computed(() => ["POST", "PUT", "PATCH", "DELETE"].includes(selectedMethod.value));

const formatSize = (bytes) => {
    if (bytes === 0) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

const sendRequest = async () => {
    loading.value = true;
    responseStatus.value = null;
    responseData.value = null;
    responseSize.value = null;
    responseTime.value = null;
    bodyError.value = null;

    const startTime = performance.now();

    try {
        let payload = null;
        if (hasPayload.value && requestBody.value.trim()) {
            try {
                payload = JSON.parse(requestBody.value);
            } catch (e) {
                loading.value = false;
                bodyError.value = "Invalid JSON: " + e.message;
                return;
            }
        }

        const headers = {};
        if (useCustomToken.value && customToken.value.trim()) {
            headers["Authorization"] = `Bearer ${customToken.value.trim()}`;
        }

        const config = {
            method: selectedMethod.value,
            url: apiUrl.value,
            data: payload,
            headers,
        };

        const response = await axios(config);
        responseStatus.value = `${response.status} ${response.statusText}`;
        responseData.value = response.data;

        const content = JSON.stringify(response.data);
        responseSize.value = formatSize(new Blob([content]).size);
    } catch (error) {
        responseStatus.value = error.response
            ? `${error.response.status} ${error.response.statusText}`
            : "Request Failed";
        responseData.value = error.response?.data || { message: error.message };
        
        const content = JSON.stringify(responseData.value);
        responseSize.value = formatSize(new Blob([content]).size);
    } finally {
        responseTime.value = Math.round(performance.now() - startTime);
        loading.value = false;
    }
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>API Request Test</CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
            <div class="space-y-4">
                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Key class="h-4 w-4" />
                    <span>Authentication</span>
                </div>
                <div class="space-y-3 rounded-lg border border-border bg-muted/20 p-4">
                    <div class="flex items-center space-x-2">
                        <input
                            id="use-custom-token"
                            v-model="useCustomToken"
                            type="checkbox"
                            class="h-4 w-4 rounded border-input bg-background"
                        />
                        <Label for="use-custom-token" class="cursor-pointer font-normal">Use custom Bearer token</Label>
                    </div>
                    <div v-if="useCustomToken" class="space-y-2">
                        <Label for="custom-token" class="text-xs uppercase text-muted-foreground">Bearer Token</Label>
                        <Input
                            id="custom-token"
                            v-model="customToken"
                            placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                            type="password"
                        />
                    </div>
                </div>
            </div>

            <Separator />

            <div class="space-y-4">
                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Package class="h-4 w-4" />
                    <span>Request Details</span>
                </div>
                <div class="flex gap-3">
                    <div class="w-32 space-y-2">
                        <Label for="api-method">Method</Label>
                        <select
                            id="api-method"
                            v-model="selectedMethod"
                            class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option v-for="method in methods" :key="method" :value="method">
                                {{ method }}
                            </option>
                        </select>
                    </div>
                    <div class="flex-1 space-y-2">
                        <Label for="api-url">API URL (relative or absolute)</Label>
                        <Input id="api-url" v-model="apiUrl" placeholder="/api/collections" />
                    </div>
                </div>

                <div v-if="hasPayload" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="api-body">JSON Request Body</Label>
                        <span v-if="bodyError" class="text-xs text-destructive">{{ bodyError }}</span>
                    </div>
                    <textarea
                        id="api-body"
                        v-model="requestBody"
                        rows="5"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 font-mono"
                        :class="{ 'border-destructive': bodyError }"
                        placeholder='{ "name": "new_collection", "type": "base" }'
                    ></textarea>
                </div>
            </div>

            <Button type="button" :disabled="loading" class="w-full sm:w-auto" @click="sendRequest">
                {{ loading ? 'Sending...' : 'Send Request' }}
            </Button>

            <Separator />

            <div v-if="responseStatus" class="space-y-2">
                <div class="flex items-center gap-4 text-sm font-medium">
                    <div class="flex items-center gap-2">
                        <span>Status:</span>
                        <span :class="responseStatus.startsWith('2') ? 'text-green-600' : 'text-destructive'">
                            {{ responseStatus }}
                        </span>
                    </div>
                    <div v-if="responseSize" class="flex items-center gap-2">
                        <span class="text-muted-foreground">Size:</span>
                        <span>{{ responseSize }}</span>
                    </div>
                    <div v-if="responseTime !== null" class="flex items-center gap-1.5 ml-auto">
                        <Timer class="h-3.5 w-3.5 text-muted-foreground" />
                        <span class="text-muted-foreground">Time:</span>
                        <span>{{ responseTime }}ms</span>
                    </div>
                </div>
                <div class="rounded-md border border-border bg-muted/30 p-3">
                    <p class="mb-2 text-xs text-muted-foreground">Response Body</p>
                    <pre class="max-h-96 overflow-auto text-xs font-mono whitespace-pre-wrap break-all">{{ JSON.stringify(responseData, null, 2) }}</pre>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
