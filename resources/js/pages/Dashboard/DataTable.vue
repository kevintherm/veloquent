<script setup>
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
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

defineProps({
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

defineEmits(['toggle-all', 'toggle-record', 'prev-page', 'next-page', 'open-record', 'sort'])

const skeletonRows = 5;

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
        return "Open related record in new tab";
    }

    return `Open related ${targetName} record in new tab`;
};

const resolveColumnIcon = (column, columnTypes) => {
    return resolveCollectionFieldTypeIcon(columnTypes?.[column]);
};

const fixedWidthColumns = new Set(["id", "created_at", "updated_at"]);

const isFixedWidthColumn = (column) => {
    return fixedWidthColumns.has(column);
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
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12.5">
                            <Checkbox :model-value="selectedRecords.length === records.length && records.length > 0"
                                @update:model-value="(val) => $emit('toggle-all', val)" />
                        </TableHead>
                        <TableHead v-for="column in columns" :key="column"
                            :class="isFixedWidthColumn(column) ? 'w-45 min-w-45 whitespace-nowrap' : ''">
                            <button type="button" class="inline-flex items-center gap-2"
                                @click="$emit('sort', column)">
                                <component :is="resolveColumnIcon(column, columnTypes)"
                                    class="h-3.5 w-3.5 text-muted-foreground" />
                                <span>{{ columnLabel(column) }}</span>
                                <component :is="sortIconFor(column, sortBy, sortDirection)" class="h-3.5 w-3.5 text-muted-foreground" />
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
                            <TableCell v-for="column in columns" :key="`${record.id}-${column}`" :class="[
                                'align-top',
                                isFixedWidthColumn(column) ? 'w-45 min-w-45 whitespace-nowrap' : '',
                            ]">
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
                                    class="font-mono text-xs text-muted-foreground underline-offset-2 hover:underline"
                                    @click.stop="copyRecordId(record[column])">
                                    {{ formatValue(record[column]) }}
                                </button>
                                <div v-else-if="isRelationColumn(column, relationFields)" class="flex flex-wrap gap-2">
                                    <a
                                        v-for="relationId in normalizeRelationIds(record[column])"
                                        :key="`${record.id}-${column}-${relationId}`"
                                        :href="relationRecordUrl(column, relationId, relationFields)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-mono text-xs text-primary underline underline-offset-2"
                                        :title="relationLinkTitle(column, relationFields)"
                                        @click.stop
                                    >
                                        {{ relationId }}
                                    </a>
                                    <span v-if="normalizeRelationIds(record[column]).length === 0">-</span>
                                </div>
                                <span v-else>
                                    {{ formatValue(record[column]) }}
                                </span>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-if="loading">
                        <TableCell :colspan="columns.length + 1" class="space-y-3 py-4">
                            <div v-for="rowIndex in skeletonRows" :key="`skeleton-row-${rowIndex}`" class="grid gap-3"
                                :style="{ gridTemplateColumns: `2rem repeat(${columns.length}, minmax(0, 1fr))` }">
                                <Skeleton class="h-4 w-4 rounded-sm" />
                                <Skeleton v-for="column in columns" :key="`skeleton-${rowIndex}-${column}`"
                                    class="h-4 w-full" />
                            </div>
                        </TableCell>
                    </TableRow>
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
            <div class="flex items-center gap-2">
                <Button variant="outline" size="icon" class="h-8 w-8" :disabled="currentPage === 1"
                    @click="$emit('prev-page')">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <div class="text-sm font-medium">Page {{ currentPage }} of {{ totalPages }}</div>
                <Button variant="outline" size="icon" class="h-8 w-8" :disabled="currentPage === totalPages"
                    @click="$emit('next-page')">
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </Card>
</template>
