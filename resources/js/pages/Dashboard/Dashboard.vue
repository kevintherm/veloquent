<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import axios from "axios";
import { Input } from "@/components/ui";
import {Search} from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import DashboardLayout from "@/layouts/DashboardLayout.vue";
import DataTable from "@/pages/Dashboard/DataTable.vue";
import BulkActions from "@/pages/Dashboard/BulkActions.vue";
import { useDashboardState } from "@/lib/dashboardState";
import { openRecordForm } from "@/lib/recordFormSheet";

const route = useRoute();
const router = useRouter();
const { activeCollection, recordsReloadNonce } = useDashboardState();
const records = ref([]);
const loading = ref(false);

const selectedRecords = ref([]);
const searchQuery = ref("");
const debouncedSearchQuery = ref("");
const currentPage = ref(1);
const itemsPerPage = 15;
const totalPages = ref(1);
const totalRecords = ref(0);
const lastAutoOpenedRecordKey = ref(null);
const isAutoOpeningRecord = ref(false);
let searchDebounceTimer = null;

const normalizeRecordsPayload = (payload) => {
    if (Array.isArray(payload)) {
        return {
            rows: payload,
            meta: null,
        };
    }

    if (Array.isArray(payload?.data)) {
        return {
            rows: payload.data,
            meta: payload.meta ?? null,
        };
    }

    return {
        rows: [],
        meta: null,
    };
};

const fetchRecords = async () => {
    if (!activeCollection.value?.id) {
        records.value = [];
        totalPages.value = 1;
        totalRecords.value = 0;
        selectedRecords.value = [];
        return;
    }

    loading.value = true;

    const query = debouncedSearchQuery.value.trim();

    try {
        const response = await axios.get(`/api/collections/${activeCollection.value.name}/records`, {
            params: {
                page: currentPage.value,
                per_page: itemsPerPage,
                filter: query,
            },
        });

        const { rows, meta } = normalizeRecordsPayload(response?.data?.data);

        records.value = rows;

        if (meta) {
            totalPages.value = Number(meta.last_page ?? 1);
            totalRecords.value = Number(meta.total ?? rows.length);
            currentPage.value = Number(meta.current_page ?? currentPage.value);
        } else {
            totalPages.value = 1;
            totalRecords.value = rows.length;
        }
    } catch {
        records.value = [];
        totalPages.value = 1;
        totalRecords.value = 0;
    } finally {
        selectedRecords.value = [];
        loading.value = false;
    }
};

const recordColumns = computed(() => {
    const excludedColumns = new Set(["collection_id", "collection_name"]);
    const collectionFields = activeCollection.value?.fields ?? [];
    const fields = collectionFields
        .map((field) => field?.name)
        .filter((name) => name && !excludedColumns.has(name));

    const rows = records.value;

    if (!rows.length) {
        return fields.length ? fields : ["id", "created_at", "updated_at"];
    }

    const rowKeys = Object.keys(rows[0]).filter((key) => !excludedColumns.has(key));
    const orderedColumns = fields.length ? fields : rowKeys;

    for (const key of rowKeys) {
        if (!orderedColumns.includes(key)) {
            orderedColumns.push(key);
        }
    }

    return orderedColumns;
});

const columnTypes = computed(() => {
    const types = {};
    const collectionFields = activeCollection.value?.fields ?? [];

    for (const field of collectionFields) {
        if (!field?.name) {
            continue;
        }

        types[field.name] = field.type ?? null;
    }

    if (!types.created_at) {
        types.created_at = "timestamp";
    }

    if (!types.updated_at) {
        types.updated_at = "timestamp";
    }

    return types;
});

const toggleAll = (checked) => {
    if (checked) {
        selectedRecords.value = records.value.map((r) => r.id);
    } else {
        selectedRecords.value = [];
    }
};

const toggleRecord = (id) => {
    const index = selectedRecords.value.indexOf(id);
    if (index > -1) {
        selectedRecords.value.splice(index, 1);
    } else {
        selectedRecords.value.push(id);
    }
};

const handleOpenRecord = (record) => {
    if (!activeCollection.value?.id) {
        return;
    }

    openRecordForm({
        collection: activeCollection.value,
        record,
        origin: "dashboard-row-click",
    });
};

const normalizedRouteRecordId = computed(() => {
    if (typeof route.query.recordId !== "string") {
        return null;
    }

    const value = route.query.recordId.trim();

    return value.length ? value : null;
});

const fetchRecordById = async (recordId) => {
    if (!activeCollection.value?.name || !recordId) {
        return null;
    }

    const response = await axios.get(
        `/api/collections/${encodeURIComponent(activeCollection.value.name)}/records/${encodeURIComponent(recordId)}`
    );

    return response?.data?.data ?? response?.data ?? null;
};

watch(
    () => activeCollection.value?.id,
    async () => {
        currentPage.value = 1;
        await fetchRecords();
    },
    { immediate: true }
);

watch(
    () => route.query.q,
    (value) => {
        const nextQuery = typeof value === "string" ? value : "";

        if (nextQuery === searchQuery.value) {
            return;
        }

        if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
        }

        searchQuery.value = nextQuery;
        debouncedSearchQuery.value = nextQuery;
        currentPage.value = 1;
    },
    { immediate: true }
);

watch(searchQuery, (value) => {
    const normalizedValue = value.trim();
    const currentQuery = typeof route.query.q === "string" ? route.query.q : "";

    if (normalizedValue !== currentQuery) {
        const nextQuery = { ...route.query };

        if (normalizedValue) {
            nextQuery.q = normalizedValue;
        } else {
            delete nextQuery.q;
        }

        void router.replace({ query: nextQuery });
    }

    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(() => {
        debouncedSearchQuery.value = value;
    }, 750);
});

watch(debouncedSearchQuery, async () => {
    if (currentPage.value !== 1) {
        currentPage.value = 1;
        return;
    }

    await fetchRecords();
});

watch(currentPage, async () => {
    await fetchRecords();
});

watch(recordsReloadNonce, async () => {
    await fetchRecords();
});

watch(
    [
        () => activeCollection.value?.id,
        normalizedRouteRecordId,
    ],
    async ([collectionId, recordId]) => {
        if (!collectionId || !recordId) {
            if (!recordId) {
                lastAutoOpenedRecordKey.value = null;
            }

            return;
        }

        const autoOpenKey = `${collectionId}:${recordId}`;

        if (lastAutoOpenedRecordKey.value === autoOpenKey || isAutoOpeningRecord.value) {
            return;
        }

        isAutoOpeningRecord.value = true;

        try {
            const record = await fetchRecordById(recordId);

            if (!record || typeof record !== "object") {
                return;
            }

            openRecordForm({
                collection: activeCollection.value,
                record,
                origin: "dashboard-record-id-query",
            });

            lastAutoOpenedRecordKey.value = autoOpenKey;

            if (typeof route.query.recordId === "string") {
                const nextQuery = { ...route.query };
                delete nextQuery.recordId;

                await router.replace({
                    path: route.path,
                    query: nextQuery,
                });
            }
        } finally {
            isAutoOpeningRecord.value = false;
        }
    },
    { immediate: true }
);

onUnmounted(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});
</script>

<template>
    <DashboardLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="relative w-full">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"/>
                    <Input
                        v-model="searchQuery"
                        placeholder="Search records..."
                        class="pl-9 h-10 bg-card w-full"
                    />
                </div>
            </div>

            <!-- Floating Bulk Actions Bar -->
            <BulkActions :selected-records="selectedRecords" @clear-selection="selectedRecords = []"/>

            <DataTable
                :records="records"
                :columns="recordColumns"
                :column-types="columnTypes"
                :selected-records="selectedRecords"
                :current-page="currentPage"
                :total-pages="totalPages"
                :items-per-page="itemsPerPage"
                :filtered-records-length="totalRecords"
                :loading="loading"
                @toggle-all="toggleAll"
                @toggle-record="toggleRecord"
                @prev-page="currentPage--"
                @next-page="currentPage++"
                @open-record="handleOpenRecord"
            />
        </div>
    </DashboardLayout>
</template>
