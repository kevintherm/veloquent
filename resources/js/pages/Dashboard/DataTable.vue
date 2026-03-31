<script setup>
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import { resolveCollectionFieldTypeIcon } from "@/lib/collectionFieldTypeIcons";
import {
    Button,
    Card,
    Skeleton,
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
    Checkbox,
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
import { computed, watchEffect } from "vue";

const props = defineProps({
    records: {
        type: Array,
        required: true
    },
    columns: {
        type: Array,
        required: true
    },
    columnTypes: {
        type: Object,
        default: () => ({})
    },
    relationFields: {
        type: Object,
        default: () => ({})
    },
    sortBy: {
        type: String,
        default: null,
    },
    sortDirection: {
        type: String,
        default: "asc",
    },
    selectedRecords: {
        type: Array,
        required: true
    },
    currentPage: {
        type: Number,
        required: true
    },
    totalPages: {
        type: Number,
        required: true
    },
    itemsPerPage: {
        type: Number,
        required: true
    },
    filteredRecordsLength: {
        type: Number,
        required: true
    },
    loading: {
        type: Boolean,
        default: false
    }
})

defineEmits(['toggle-all', 'toggle-record', 'change-page', 'open-record', 'sort'])

const skeletonRows = computed(() => props.records.length > 0 ? props.records.length : 8);

const datetimeTypes = new Set(["timestamp", "datetime", "date"]);

const isDatetimeColumn = (column, columnTypes) => {
    return datetimeTypes.has(columnTypes?.[column]);
};

const formatDatetimeParts = (value) => {
    if (value === null || value === undefined || value === "") {
        return {
            date: "-",
            time: "",
        };
    }

    const parsedDate = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(parsedDate.getTime())) {
        return {
            date: String(value),
            time: "",
        };
    }

    return {
        date: parsedDate.toLocaleDateString(),
        time: parsedDate.toLocaleTimeString(),
    };
};

const formatValue = (value) => {
    if (value === null || value === undefined || value === "") {
        return "-";
    }

    if (typeof value === "boolean") {
        return value ? "Yes" : "No";
    }

    if (typeof value === "object") {
        return JSON.stringify(value);
    }

    return String(value);
};

const normalizeRelationIds = (value) => {
    if (Array.isArray(value)) {
        return value.filter((item) => item !== null && item !== undefined && item !== "");
    }

    if (value === null || value === undefined || value === "") {
        return [];
    }

    return [value];
};

const isRelationColumn = (column, relationFields) => {
    return Boolean(relationFields?.[column]?.targetCollectionId);
};

const relationRecordUrl = (column, relationId, relationFields) => {
    const targetCollectionId = relationFields?.[column]?.targetCollectionId;

    if (!targetCollectionId || !relationId) {
        return "#";
    }

    return `/${encodeURIComponent(targetCollectionId)}?recordId=${encodeURIComponent(String(relationId))}`;
};

const relationLinkTitle = (column, relationFields) => {
    const targetName = relationFields?.[column]?.targetCollectionName;

    if (!targetName) {
        return "Open related record";
    }

    return `Open related ${targetName} record`;
};

const resolveColumnIcon = (column, columnTypes) => {
    return resolveCollectionFieldTypeIcon(columnTypes?.[column]);
};

const resolveColumnWidthClass = (column, columnTypes) => {
    if (column === "id") {
        return "min-w-48 whitespace-nowrap";
    }

    const type = columnTypes?.[column];

    switch (type) {
        case "boolean":
        case "number":
            return "min-w-36";
        case "timestamp":
        case "datetime":
        case "date":
            return "min-w-40 whitespace-nowrap";
        case "json":
        case "longtext":
            return "min-w-80";
        case "relation":
            return "min-w-48";
        default:
            return "min-w-48";
    }
};

const columnLabel = (name) => {
    return String(name);
};

const sortIconFor = (column, sortBy, sortDirection) => {
    if (sortBy !== column) {
        return ArrowUpDown;
    }

    return sortDirection === "desc" ? ArrowDown : ArrowUp;
};

const copyRecordId = async (recordId) => {
    if (!recordId) {
        return;
    }

    try {
        await navigator.clipboard.writeText(String(recordId));
        toast.success("ID copied");
    } catch {
        toast.error("Failed to copy ID");
    }
};
</script>

<template>
    <Card>
        <div class="rounded-md border">
            <Table class="table-fixed">
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12.5">
                            <Checkbox :model-value="selectedRecords.length === records.length && records.length > 0"
                                @update:model-value="(val) => $emit('toggle-all', val)" />
                        </TableHead>
                        <TableHead v-for="column in columns" :key="column"
                            :class="resolveColumnWidthClass(column, columnTypes)">
                            <button type="button" class="inline-flex items-center gap-2" @click="$emit('sort', column)">
                                <component :is="resolveColumnIcon(column, columnTypes)"
                                    class="h-3.5 w-3.5 text-muted-foreground" />
                                <span>{{ columnLabel(column) }}</span>
                                <component :is="sortIconFor(column, sortBy, sortDirection)"
                                    class="h-3.5 w-3.5 text-muted-foreground" />
                            </button>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="!loading">
                        <TableRow v-for="record in records" :key="record.id"
                            :data-state="selectedRecords.includes(record.id) ? 'selected' : ''" class="cursor-pointer"
                            @click="$emit('open-record', record)">
                            <TableCell @click.stop>
                                <Checkbox :model-value="selectedRecords.includes(record.id)"
                                    @update:model-value="$emit('toggle-record', record.id)" />
                            </TableCell>
                            <TableCell v-for="column in columns" :key="`${record.id}-${column}`" :class="['align-top']">
                                <div v-if="isDatetimeColumn(column, columnTypes)"
                                    class="leading-tight whitespace-nowrap">
                                    <p class="text-sm font-medium">
                                        {{ formatDatetimeParts(record[column]).date }}
                                    </p>
                                    <p v-if="formatDatetimeParts(record[column]).time"
                                        class="text-xs text-muted-foreground">
                                        {{ formatDatetimeParts(record[column]).time }}
                                    </p>
                                </div>
                                <button v-else-if="column === 'id'" type="button"
                                    class="font-mono text-xs text-muted-foreground underline-offset-2 hover:underline truncate max-w-full block text-left"
                                    @click.stop="copyRecordId(record[column])">
                                    {{ formatValue(record[column]) }}
                                </button>
                                <div v-else-if="isRelationColumn(column, relationFields)" class="flex flex-col gap-1">
                                    <a v-for="relationId in normalizeRelationIds(record[column])"
                                        :key="`${record.id}-${column}-${relationId}`"
                                        :href="relationRecordUrl(column, relationId, relationFields)" target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-mono text-xs text-primary underline underline-offset-2 truncate"
                                        :title="relationLinkTitle(column, relationFields)" @click.stop>
                                        {{ relationId }}
                                    </a>
                                    <span v-if="normalizeRelationIds(record[column]).length === 0">-</span>
                                </div>
                                <span v-else class="line-clamp-2">
                                    {{ formatValue(record[column]) }}
                                </span>
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-if="loading">
                        <TableRow v-for="rowIndex in skeletonRows" :key="`skeleton-row-${rowIndex}`">
                            <TableCell>
                                <Skeleton class="h-8 w-8 rounded-sm" />
                            </TableCell>
                            <TableCell v-for="column in columns" :key="`skeleton-${rowIndex}-${column}`"
                                :class="resolveColumnWidthClass(column, columnTypes)">
                                <Skeleton class="h-8 w-full" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else-if="records.length === 0">
                        <TableCell :colspan="columns.length + 1" class="h-24 text-center text-muted-foreground">
                            No records found.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-6 py-4 border-t">
            <div class="text-sm text-muted-foreground">
                <template v-if="filteredRecordsLength > 0">
                    Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to
                    {{ Math.min(currentPage * itemsPerPage, filteredRecordsLength) }} of
                    {{ filteredRecordsLength }} records
                </template>
                <template v-else>
                    Showing 0 records
                </template>
            </div>
            <Pagination v-if="totalPages > 1" v-slot="{ page }" :total="filteredRecordsLength" :sibling-count="1"
                show-edges :page="currentPage" :items-per-page="itemsPerPage"
                @update:page="$emit('change-page', $event)" class="justify-end">
                <PaginationContent v-slot="{ items }">
                    <PaginationFirst />
                    <PaginationPrevious />

                    <template v-for="(item, index) in items">
                        <PaginationItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                            <Button class="w-9 h-9 p-0" :variant="item.value === page ? 'default' : 'outline'">
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
    </Card>
</template>
