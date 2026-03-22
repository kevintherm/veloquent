<script setup>
import { ref, computed, onMounted, watch } from "vue";
import DashboardLayout from "@/layouts/DashboardLayout.vue";
import axios from "axios";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
    Button,
    Input,
    Card,
    CardContent,
    CardHeader,
    CardTitle
} from "@/components/ui";
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from "@/components/ui/pagination";
import { Copy, AlertCircle, Info, AlertTriangle, Search, Activity, Clock, Server, RefreshCw, Timer } from "lucide-vue-next";
import { toast } from "vue-sonner";

const dates = ref([]);
const selectedDate = ref("");
const selectedLevel = ref("");
const searchQuery = ref("");
const selectedHour = ref(null);
const logs = ref([]);
const stats = ref([]);
const totalResults = ref(0);
const isLoading = ref(false);

const currentPage = ref(1);
const itemsPerPage = ref(50);
const lastPage = ref(1);

const selectedLog = ref(null);
const isSheetOpen = ref(false);

const fetchDates = async () => {
    try {
        const res = await axios.get("/api/logs/dates");
        dates.value = res.data;
        if (dates.value.length > 0 && !selectedDate.value) {
            selectedDate.value = dates.value[0];
        }
    } catch (e) {
        console.error("Failed to fetch log dates", e);
        toast.error("Failed to fetch log dates");
    }
};

const fetchLogs = async () => {
    if (!selectedDate.value) return;
    isLoading.value = true;
    const toastId = toast.loading("Fetching logs for " + selectedDate.value + "...");

    try {
        const res = await axios.get("/api/logs", {
            params: {
                date: selectedDate.value,
                level: selectedLevel.value || undefined,
                query: searchQuery.value || undefined,
                hour: selectedHour.value !== null ? selectedHour.value : undefined,
                page: currentPage.value,
                per_page: itemsPerPage.value,
            },
        });
        logs.value = res.data.data;
        totalResults.value = res.data.meta.total;
        lastPage.value = res.data.meta.last_page;
        stats.value = res.data.stats;
        toast.dismiss(toastId)
    } catch (e) {
        console.error("Failed to fetch logs", e);
        const errorMsg = e.response?.data?.message || "Failed to fetch logs";
        toast.error(errorMsg, { id: toastId });
    } finally {
        isLoading.value = false;
    }
};

onMounted(async () => {
    await fetchDates();
});

// Watchers for filters - reset page and fetch
watch([selectedDate, selectedLevel, selectedHour], () => {
    currentPage.value = 1;
    fetchLogs();
});

// Watch page change
watch(currentPage, () => {
    fetchLogs();
});

// Debounced search
let searchTimeout;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchLogs();
    }, 400);
});

const chartData = computed(() => {
    if (!stats.value || Object.keys(stats.value).length === 0) {
        return Array.from({ length: 24 }, (_, i) => ({
            hour: i,
            count: 0,
            error: 0,
            warning: 0,
            info: 0,
            height: '0%',
            errorHeight: '0%',
            warningHeight: '0%'
        }));
    }

    const maxCount = Math.max(...Object.values(stats.value).map((h) => h.count), 1);

    return Object.entries(stats.value).map(([hour, h]) => ({
        ...h,
        hour: parseInt(hour),
        height: `${(h.count / maxCount) * 100}%`,
        errorHeight: `${(h.error / maxCount) * 100}%`,
        warningHeight: `${(h.warning / maxCount) * 100}%`,
    }));
});

const openLogDetails = (log) => {
    selectedLog.value = log;
    isSheetOpen.value = true;
};

const getLevelIcon = (level) => {
    const l = String(level).toUpperCase();
    if (l === "ERROR") return AlertCircle;
    if (l === "WARNING") return AlertTriangle;
    return Info;
};

const getLevelColor = (level) => {
    const l = String(level).toUpperCase();
    if (l === "ERROR") return "text-destructive";
    if (l === "WARNING") return "text-amber-500";
    return "text-blue-500";
};

const formatDate = (dateStr) => {
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`;
    } catch {
        return dateStr;
    }
};

const getUrlPath = (url) => {
    if (!url) return "";
    try {
        const urlObj = new URL(url);
        return urlObj.pathname + urlObj.search;
    } catch {
        // Fallback for relative URLs or invalid ones
        const match = url.match(/^https?:\/\/[^\/]+(\/.*)/);
        return match ? match[1] : url;
    }
};

const copyToClipboard = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
    }
};
</script>

<template>
    <DashboardLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">System Logs</h2>
                    <p class="text-muted-foreground mt-2">
                        Monitor and troubleshoot application events, errors, and slow queries.
                    </p>
                </div>
                <Button variant="outline" size="sm" @click="fetchLogs" :disabled="isLoading">
                    <RefreshCw :class="['h-4 w-4 mr-2', { 'animate-spin': isLoading }]" />
                    Refresh
                </Button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card class="flex items-center gap-4 p-6 shadow-sm">
                    <div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                        <Activity class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-3xl font-bold">{{ totalResults }}</h3>
                        <p class="text-sm text-muted-foreground font-medium">Total Events</p>
                    </div>
                </Card>
                <Card class="flex items-center gap-4 p-6 shadow-sm">
                    <div
                        class="h-12 w-12 rounded-full bg-destructive/10 flex items-center justify-center text-destructive">
                        <AlertCircle class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-3xl font-bold">{{ chartData.reduce((acc, h) => acc + h.error, 0) }}</h3>
                        <p class="text-sm text-muted-foreground font-medium">Errors</p>
                    </div>
                </Card>
                <Card class="flex items-center gap-4 p-6 shadow-sm">
                    <div class="h-12 w-12 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-500">
                        <AlertTriangle class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-3xl font-bold">{{ chartData.reduce((acc, h) => acc + h.warning, 0) }}</h3>
                        <p class="text-sm text-muted-foreground font-medium">Warnings</p>
                    </div>
                </Card>
            </div>

            <!-- Filters & Chart -->
            <div class="grid lg:grid-cols-4 gap-6">
                <div class="lg:col-span-1 space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Date</label>
                        <select v-model="selectedDate"
                            class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                            <option v-for="d in dates" :key="d" :value="d">{{ d }}</option>
                            <option value="" disabled v-if="dates.length === 0">No logs found</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Log Level</label>
                        <select v-model="selectedLevel"
                            class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                            <option value="">All Levels</option>
                            <option value="INFO">INFO</option>
                            <option value="WARNING">WARNING</option>
                            <option value="ERROR">ERROR</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Search Content</label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input v-model="searchQuery" placeholder="Message, Context..." class="pl-9 bg-background" />
                        </div>
                    </div>
                </div>

                <Card class="lg:col-span-3 shadow-sm relative">
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between">
                            <div>
                                <CardTitle class="text-lg font-semibold">Activity Chart ({{ selectedDate || 'Today' }})
                                </CardTitle>
                                <p class="text-sm text-muted-foreground">Click any bar to filter events by hour.</p>
                            </div>
                            <Button v-if="selectedHour !== null" variant="secondary" size="sm"
                                @click="selectedHour = null" class="text-xs h-8">
                                Clear {{ String(selectedHour).padStart(2, '0') }}:00 Filter
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="h-32 flex items-end gap-1 sm:gap-2 mt-auto">
                            <div v-for="h in chartData" :key="h.hour"
                                @click="h.count > 0 ? (selectedHour = selectedHour === h.hour ? null : h.hour) : null"
                                :class="[
                                    'flex-1 flex flex-col justify-end group relative h-full transition-all rounded-sm',
                                    h.count > 0 ? 'cursor-pointer' : 'cursor-default',
                                    selectedHour === h.hour ? 'ring-2 ring-primary ring-offset-1 ring-offset-background' : '',
                                    selectedHour !== null && selectedHour !== h.hour ? 'opacity-30' : 'hover:opacity-90'
                                ]">
                                <!-- Tooltip -->
                                <div
                                    class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block bg-background border text-foreground text-xs p-2 rounded shadow-xl z-50 w-max text-center pointer-events-none">
                                    <p class="font-bold mb-1">{{ String(h.hour).padStart(2, '0') }}:00</p>
                                    <p v-if="h.count === 0" class="text-muted-foreground">No events</p>
                                    <div v-else class="space-y-0.5 text-left">
                                        <p v-if="h.error > 0" class="text-destructive">{{ h.error }} Errors</p>
                                        <p v-if="h.warning > 0" class="text-amber-500">{{ h.warning }} Warnings</p>
                                        <p v-if="h.info > 0" class="text-blue-500">{{ h.info }} Info</p>
                                        <p class="text-muted-foreground border-t pt-0.5 mt-0.5">{{ h.count }} Total</p>
                                    </div>
                                    <p v-if="h.count > 0"
                                        class="text-muted-foreground border-t mt-1 pt-1 italic text-[10px]">
                                        {{ selectedHour === h.hour ? 'Click to deselect' : 'Click to filter' }}
                                    </p>
                                </div>

                                <!-- Bar -->
                                <div v-if="h.count > 0"
                                    class="w-full bg-primary/10 rounded-t-sm transition-all relative overflow-hidden"
                                    :style="{ height: h.height }">
                                    <div v-if="h.error > 0" class="absolute bottom-0 w-full bg-destructive/80"
                                        :style="{ height: h.errorHeight }"></div>
                                    <div v-else-if="h.warning > 0" class="absolute bottom-0 w-full bg-amber-500/80"
                                        :style="{ height: h.warningHeight }"></div>
                                    <div v-else class="absolute bottom-0 w-full bg-primary/40 h-full"></div>
                                </div>
                                <div v-else class="w-full h-px bg-border group-hover:bg-primary/50 transition-colors">
                                </div>

                                <!-- Axis label -->
                                <span class="text-[0.65rem] text-muted-foreground mt-1 block text-center opacity-50">{{
                                    h.hour % 4 === 0 ? h.hour : '' }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Logs Table -->
            <Card class="overflow-hidden shadow-sm">
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[100px]">Time</TableHead>
                                    <TableHead class="w-[120px]">Level</TableHead>
                                    <TableHead>Message</TableHead>
                                    <TableHead class="w-[80px] text-right">Details</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="isLoading">
                                    <TableCell colspan="4" class="h-32 text-center text-muted-foreground">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <div
                                                class="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin">
                                            </div>
                                            <p>Loading logs...</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-else-if="logs.length === 0">
                                    <TableCell colspan="4" class="h-32 text-center text-muted-foreground">
                                        No logs found for this filter.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="(log, idx) in logs" :key="idx"
                                    class="cursor-pointer hover:bg-muted/50" @click="openLogDetails(log)">
                                    <TableCell class="text-xs whitespace-nowrap text-muted-foreground py-4">
                                        <div class="font-medium text-foreground">{{ formatDate(log.datetime) }}</div>
                                        <div v-if="log.context?.duration || log.context?.time"
                                            class="flex items-center gap-1 mt-1 text-[10px]">
                                            <Timer class="h-3 w-3" />
                                            {{ log.context?.duration || log.context?.time }}ms
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <component :is="getLevelIcon(log.level)"
                                                :class="['h-4 w-4', getLevelColor(log.level)]" />
                                            <span :class="['text-xs font-semibold', getLevelColor(log.level)]">{{
                                                log.level }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="font-mono text-sm max-w-[500px]">
                                        <div class="truncate" :title="log.message">{{ log.message }}</div>
                                        <div v-if="log.message === 'HTTP_REQUEST' && log.context?.method"
                                            class="text-[10px] text-muted-foreground mt-0.5 flex gap-1 items-center">
                                            <span class="font-bold text-primary uppercase">{{ log.context.method
                                            }}</span>
                                            <span class="truncate">{{ getUrlPath(log.context.url) }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button variant="ghost" size="sm"
                                            @click.stop="openLogDetails(log)">View</Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="p-4 border-t bg-muted/20 flex flex-col sm:flex-row gap-4 items-center justify-between"
                        v-if="lastPage > 1">
                        <p class="text-sm text-muted-foreground">
                            Showing <span class="font-medium">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> to <span
                                class="font-medium">{{ Math.min(currentPage * itemsPerPage, totalResults)
                                }}</span> of <span class="font-medium">{{ totalResults }}</span> results
                        </p>
                        <Pagination v-slot="{ page }" :total="totalResults" :sibling-count="1" show-edges
                            :default-page="1" v-model:page="currentPage" :items-per-page="itemsPerPage"
                            class="justify-end">
                            <PaginationContent v-slot="{ items }">
                                <PaginationFirst />
                                <PaginationPrevious />

                                <template v-for="(item, index) in items">
                                    <PaginationItem v-if="item.type === 'page'" :key="index" :value="item.value"
                                        as-child>
                                        <Button class="w-9 h-9 p-0"
                                            :variant="item.value === page ? 'default' : 'outline'">
                                            {{ item.value }}
                                        </Button>
                                    </PaginationItem>
                                    <PaginationEllipsis v-else :key="item.type" :index="index" />
                                </template>

                                <PaginationNext />
                                <PaginationLast />
                            </PaginationContent>
                        </Pagination>
                    </div>
                </CardContent>
            </Card>

            <!-- Detail Sheet -->
            <Sheet v-model:open="isSheetOpen">
                <SheetContent side="right" class="w-full sm:max-w-2xl overflow-y-auto">
                    <SheetHeader class="mb-6">
                        <SheetTitle class="flex items-center gap-2 text-xl">
                            <component v-if="selectedLog" :is="getLevelIcon(selectedLog.level)"
                                :class="['h-6 w-6', getLevelColor(selectedLog.level)]" />
                            Log Event Details
                        </SheetTitle>
                        <SheetDescription v-if="selectedLog" class="flex gap-4 items-center">
                            <span class="flex items-center gap-1">
                                <Clock class="h-4 w-4" /> {{ formatDate(selectedLog.datetime) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <Server class="h-4 w-4" /> {{ selectedLog.env }}
                            </span>
                        </SheetDescription>
                    </SheetHeader>

                    <div v-if="selectedLog" class="space-y-6">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold">Message</h4>
                                <Button variant="ghost" size="icon" class="h-6 w-6"
                                    @click="copyToClipboard(selectedLog.message)" title="Copy message">
                                    <Copy class="h-3 w-3" />
                                </Button>
                            </div>
                            <div
                                class="bg-card border p-4 rounded-md overflow-x-auto text-sm font-mono whitespace-pre-wrap break-all shadow-inner">
                                {{ selectedLog.message }}
                            </div>
                        </div>

                        <div v-if="selectedLog.context && Object.keys(selectedLog.context).length > 0"
                            class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold">Context / Payload</h4>
                                <Button variant="ghost" size="icon" class="h-6 w-6"
                                    @click="copyToClipboard(JSON.stringify(selectedLog.context, null, 2))"
                                    title="Copy context">
                                    <Copy class="h-3 w-3" />
                                </Button>
                            </div>
                            <div
                                class="bg-card border p-4 rounded-md overflow-x-auto text-sm font-mono shadow-inner max-h-[400px]">
                                <pre>{{ JSON.stringify(selectedLog.context, null, 2) }}</pre>
                            </div>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    </DashboardLayout>
</template>
