<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import axios from "axios";
import { toast } from "vue-sonner";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    Button,
    Checkbox,
    Input,
} from "@/components/ui";
import { Search, SlidersHorizontal } from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import DashboardLayout from "@/layouts/DashboardLayout.vue";
import DataTable from "@/pages/Dashboard/DataTable.vue";
import BulkActions from "@/pages/Dashboard/BulkActions.vue";
import { useDashboardState } from "@/lib/dashboardState";
import { openRecordForm } from "@/lib/recordFormSheet";

const route = useRoute();
const router = useRouter();
const { activeCollection, collections, recordsReloadNonce } = useDashboardState();
const records = ref([]);
const loading = ref(false);

const selectedRecords = ref([]);
const searchQuery = ref("");
const debouncedSearchQuery = ref("");
const currentPage = ref(1);
const itemsPerPage = 15;
const totalPages = ref(1);
const totalRecords = ref(0);
const visibleColumns = ref([]);
const showColumnPicker = ref(false);
const sortBy = ref(null);
const sortDirection = ref("asc");
const lastAutoOpenedRecordKey = ref(null);
const isAutoOpeningRecord = ref(false);
const bulkActionProcessing = ref(false);
const showBulkDeleteDialog = ref(false);
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
    const sort = sortBy.value
        ? (sortDirection.value === "desc" ? `-${sortBy.value}` : sortBy.value)
        : null;

    try {
        const response = await axios.get(`/api/collections/${activeCollection.value.name}/records`, {
            params: {
                page: currentPage.value,
                per_page: itemsPerPage,
                filter: query,
                sort,
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

const collectionsById = computed(() => {
    return collections.value.reduce((carry, collection) => {
        if (collection?.id) {
            carry[collection.id] = collection;
        }

        return carry;
    }, {});
});

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

const relationFieldsMeta = computed(() => {
    const fields = Array.isArray(activeCollection.value?.fields) ? activeCollection.value.fields : [];

    return fields.reduce((carry, field) => {
        if (field?.type !== "relation" || !field?.name) {
            return carry;
        }

        const targetCollection = collectionsById.value[field.target_collection_id] ?? null;

        carry[field.name] = {
            targetCollectionId: field.target_collection_id ?? null,
            targetCollectionName: targetCollection?.name ?? null,
        };

        return carry;
    }, {});
});

const displayedColumns = computed(() => {
    if (!visibleColumns.value.length) {
        return recordColumns.value;
    }

    const visible = new Set(visibleColumns.value);

    return recordColumns.value.filter((column) => visible.has(column));
});

const toggleColumn = (column) => {
    const index = visibleColumns.value.indexOf(column);

    if (index === -1) {
        visibleColumns.value.push(column);
        return;
    }

    if (visibleColumns.value.length === 1) {
        return;
    }

    visibleColumns.value.splice(index, 1);
};

const handleSort = (column) => {
    if (sortBy.value !== column) {
        sortBy.value = column;
        sortDirection.value = "asc";
        return;
    }

    sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
};

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

const performBulkDelete = async (collectionName, recordIds) => {
    // Current implementation uses per-record deletes. Replace with a future bulk endpoint here.
    return Promise.allSettled(recordIds.map((recordId) => {
        return axios.delete(
            `/api/collections/${encodeURIComponent(collectionName)}/records/${encodeURIComponent(recordId)}`
        );
    }));
};

const handleBulkDelete = async () => {
    if (bulkActionProcessing.value) {
        return;
    }

    if (!activeCollection.value?.name || !selectedRecords.value.length) {
        toast.error("No records selected.");
        return;
    }

    bulkActionProcessing.value = true;
    showBulkDeleteDialog.value = false;

    try {
        const results = await performBulkDelete(activeCollection.value.name, selectedRecords.value);
        const failedCount = results.filter((result) => result.status === "rejected").length;
        const deletedCount = results.length - failedCount;

        if (deletedCount > 0) {
            toast.success(`Deleted ${deletedCount} record(s).`);
        }

        if (failedCount > 0) {
            toast.error(`Failed to delete ${failedCount} record(s).`);
        }

        await fetchRecords();
    } finally {
        bulkActionProcessing.value = false;
    }
};

const requestBulkDelete = () => {
    if (!activeCollection.value?.name || !selectedRecords.value.length) {
        toast.error("No records selected.");
        return;
    }

    showBulkDeleteDialog.value = true;
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
        visibleColumns.value = [];
        showColumnPicker.value = false;
        sortBy.value = null;
        sortDirection.value = "asc";
        await fetchRecords();
    },
    { immediate: true }
);

watch(recordColumns, (columns) => {
    const visible = new Set(visibleColumns.value);
    const nextVisible = columns.filter((column) => visible.has(column));

    if (nextVisible.length) {
        visibleColumns.value = nextVisible;
        return;
    }

    if (activeCollection.value?.type === "auth") {
        visibleColumns.value = columns.filter(
            (col) => !["password", "visibility"].includes(col)
        );
        return;
    }

    visibleColumns.value = [...columns];
});

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

watch([sortBy, sortDirection], async () => {
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
            <div class="flex items-center justify-between gap-3">
                <div class="relative w-full">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground"/>
                    <Input
                        v-model="searchQuery"
                        placeholder="Search records..."
                        class="pl-9 h-10 bg-card w-full"
                    />
                </div>
                <Button type="button" variant="outline" class="shrink-0 gap-2" @click="showColumnPicker = !showColumnPicker">
                    <SlidersHorizontal class="h-4 w-4" />
                    Columns
                </Button>
            </div>

            <div v-if="showColumnPicker" class="rounded-md border bg-card p-4">
                <div class="mb-3 text-sm font-medium">Show/Hide Fields</div>
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                    <label
                        v-for="column in recordColumns"
                        :key="`column-picker-${column}`"
                        class="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <Checkbox
                            :model-value="displayedColumns.includes(column)"
                            @update:model-value="() => toggleColumn(column)"
                        />
                        <span>{{ column }}</span>
                    </label>
                </div>
            </div>

            <!-- Floating Bulk Actions Bar -->
            <BulkActions
                :selected-records="selectedRecords"
                :processing="bulkActionProcessing"
                @clear-selection="selectedRecords = []"
                @delete-records="requestBulkDelete"
            />

            <AlertDialog :open="showBulkDeleteDialog" @update:open="(value) => { showBulkDeleteDialog = value; }">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete selected records?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will delete {{ selectedRecords.length }} record(s). This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="bulkActionProcessing">Cancel</AlertDialogCancel>
                        <AlertDialogAction :disabled="bulkActionProcessing" @click="handleBulkDelete">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <DataTable
                :records="records"
                :columns="displayedColumns"
                :column-types="columnTypes"
                :relation-fields="relationFieldsMeta"
                :sort-by="sortBy"
                :sort-direction="sortDirection"
                :selected-records="selectedRecords"
                :current-page="currentPage"
                :total-pages="totalPages"
                :items-per-page="itemsPerPage"
                :filtered-records-length="totalRecords"
                :loading="loading"
                @toggle-all="toggleAll"
                @toggle-record="toggleRecord"
                @sort="handleSort"
                @change-page="(p) => currentPage = p"
                @open-record="handleOpenRecord"
            />
        </div>
    </DashboardLayout>
</template>
