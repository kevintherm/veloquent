<script setup>
import {
    ChevronLeft,
    ChevronRight,
} from "lucide-vue-next";
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

defineEmits(['toggle-all', 'toggle-record', 'prev-page', 'next-page'])

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

const formatValue = (value, column, columnTypes) => {
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

const columnLabel = (name) => {
    return name
        .replace(/_/g, " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());
};
</script>

<template>
    <Card>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12.5">
                            <Checkbox
                                :model-value="selectedRecords.length === records.length && records.length > 0"
                                @update:model-value="(val) => $emit('toggle-all', val)"
                            />
                        </TableHead>
                        <TableHead v-for="column in columns" :key="column">
                            {{ columnLabel(column) }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="!loading">
                        <TableRow
                            v-for="record in records"
                            :key="record.id"
                            :data-state="selectedRecords.includes(record.id) ? 'selected' : ''"
                        >
                            <TableCell>
                                <Checkbox
                                    :model-value="selectedRecords.includes(record.id)"
                                    @update:model-value="$emit('toggle-record', record.id)"
                                />
                            </TableCell>
                            <TableCell v-for="column in columns" :key="`${record.id}-${column}`" class="align-top">
                                <div v-if="isDatetimeColumn(column, columnTypes)" class="leading-tight">
                                    <p class="text-sm font-medium">
                                        {{ formatDatetimeParts(record[column]).date }}
                                    </p>
                                    <p v-if="formatDatetimeParts(record[column]).time" class="text-xs text-muted-foreground">
                                        {{ formatDatetimeParts(record[column]).time }}
                                    </p>
                                </div>
                                <span v-else :class="column === 'id' ? 'font-mono text-xs text-muted-foreground' : ''">
                                    {{ formatValue(record[column], column, columnTypes) }}
                                </span>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-if="loading">
                        <TableCell :colspan="columns.length + 1" class="space-y-3 py-4">
                            <div v-for="rowIndex in skeletonRows" :key="`skeleton-row-${rowIndex}`" class="grid gap-3"
                                :style="{ gridTemplateColumns: `2rem repeat(${columns.length}, minmax(0, 1fr))` }">
                                <Skeleton class="h-4 w-4 rounded-sm" />
                                <Skeleton v-for="column in columns" :key="`skeleton-${rowIndex}-${column}`" class="h-4 w-full" />
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
                <Button
                    variant="outline"
                    size="icon"
                    class="h-8 w-8"
                    :disabled="currentPage === 1"
                    @click="$emit('prev-page')"
                >
                    <ChevronLeft class="h-4 w-4"/>
                </Button>
                <div class="text-sm font-medium">Page {{ currentPage }} of {{ totalPages }}</div>
                <Button
                    variant="outline"
                    size="icon"
                    class="h-8 w-8"
                    :disabled="currentPage === totalPages"
                    @click="$emit('next-page')"
                >
                    <ChevronRight class="h-4 w-4"/>
                </Button>
            </div>
        </div>
    </Card>
</template>
