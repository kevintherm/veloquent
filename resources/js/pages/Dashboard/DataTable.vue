<script setup>
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    X,
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
    Dialog,
    DialogContent
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
import { computed, watchEffect, ref, onMounted, onUnmounted } from "vue";
import { getAuthToken } from "@/lib/tokenAuth";

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
const imageExtensions = new Set(["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg", "avif"]);

const isDatetimeColumn = (column, columnTypes) => {
    return datetimeTypes.has(columnTypes?.[column]);
};

const isFileColumn = (column, columnTypes) => {
    return columnTypes?.[column] === "file";
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

const normalizeFileMetadataList = (value) => {
    if (value === null || value === undefined || value === "") {
        return [];
    }

    if (Array.isArray(value)) {
        return value.filter((item) => item && typeof item === "object");
    }

    if (value && typeof value === "object") {
        return [value];
    }

    return [];
};

const normalizeStoragePathForUrl = (path) => {
    return String(path)
        .split("/")
        .filter((segment) => segment.length > 0)
        .map((segment) => encodeURIComponent(segment))
        .join("/");
};

const revokeObjectURLs = () => {
    objectUrls.value.forEach((url) => URL.revokeObjectURL(url));
    objectUrls.value.clear();
};

const objectUrls = ref(new Map());
const activePreview = ref(null);

onUnmounted(() => {
    if (observer) {
        observer.disconnect();
    }
    revokeObjectURLs();
});

const loadProtectedImage = async (event, url) => {
    if (objectUrls.value.has(url)) {
        event.target.src = objectUrls.value.get(url);
        return;
    }

    try {
        const response = await fetch(url, {
            headers: {
                Authorization: `Bearer ${getAuthToken()}`,
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to load image: ${response.status}`);
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        objectUrls.value.set(url, objectUrl);
        event.target.src = objectUrl;
    } catch (error) {
        console.error("Error loading protected image:", error);
        // Fallback or leave as is (broken image)
    }
};

const resolveFileSourceUrl = (metadata) => {
    if (!metadata || typeof metadata !== "object") {
        return null;
    }

    const protectedFile = Boolean(metadata.protected ?? false);

    const url = String(metadata.url ?? "").trim();
    if (url !== "") {
        return { url, protected: protectedFile };
    }

    const path = String(metadata.path ?? "").trim();
    if (path === "") {
        return null;
    }

    if (path.startsWith("http://") || path.startsWith("https://") || path.startsWith("/")) {
        return { url: path, protected: protectedFile };
    }

    return {
        url: `/storage/${normalizeStoragePathForUrl(path)}`,
        protected: protectedFile
    };
};

const filePreviewInfo = (value) => {
    return resolveFileSourceUrl(primaryFileForDisplay(value));
};

const resolveFileExtension = (metadata) => {
    const explicitExtension = String(metadata?.extension ?? "").trim().toLowerCase();
    if (explicitExtension !== "") {
        return explicitExtension;
    }

    const nameOrPath = String(metadata?.name ?? metadata?.path ?? "").trim().toLowerCase();
    const segments = nameOrPath.split(".");

    if (segments.length <= 1) {
        return "";
    }

    return segments.at(-1) ?? "";
};

const isImageFile = (metadata) => {
    const mime = String(metadata?.mime ?? "").trim().toLowerCase();
    if (mime.startsWith("image/")) {
        return true;
    }

    return imageExtensions.has(resolveFileExtension(metadata));
};

const firstImageFile = (value) => {
    return normalizeFileMetadataList(value).find((item) => isImageFile(item)) ?? null;
};

const firstFile = (value) => {
    return normalizeFileMetadataList(value)[0] ?? null;
};

const primaryFileForDisplay = (value) => {
    return firstImageFile(value) ?? firstFile(value);
};

const displayFilesForDataTable = (value) => {
    return normalizeFileMetadataList(value).slice(0, 3);
};

const fileDisplayName = (value) => {
    const file = primaryFileForDisplay(value);

    if (!file) {
        return "-";
    }

    return String(file.name ?? file.path ?? "-");
};

const filePreviewUrl = (metadata) => {
    return resolveFileSourceUrl(metadata)?.url;
};

const handleImageLoad = (event, metadata) => {
    const info = resolveFileSourceUrl(metadata);
    if (info && info.protected) {
        loadProtectedImage(event, info.url);
    } else if (info) {
        event.target.src = info.url;
    }
};

const handleIntersection = (entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            const img = entry.target;
            const recordId = img.dataset.recordId;
            const column = img.dataset.column;
            const fileIndex = parseInt(img.dataset.fileIndex ?? "0");

            // Find the record and column value
            const record = props.records.find((r) => String(r.id) === recordId);
            if (record && record[column]) {
                const files = normalizeFileMetadataList(record[column]);
                const file = files[fileIndex];
                if (file) {
                    handleImageLoad({ target: img }, file);
                }
            }

            if (observer) {
                observer.unobserve(img);
            }
        }
    });
};

let observer = null;

const imageRef = (el, recordId, column, fileIndex = 0) => {
    if (el) {
        el.dataset.recordId = recordId;
        el.dataset.column = column;
        el.dataset.fileIndex = String(fileIndex);
        if (!observer) {
            observer = new IntersectionObserver(handleIntersection, {
                rootMargin: "50px",
            });
        }
        observer.observe(el);
    }
};

const openProtectedFile = async (value) => {
    const info = filePreviewInfo(value);
    if (!info) {
        return;
    }

    if (isImageFile(primaryFileForDisplay(value))) {
        activePreview.value = value;
        return;
    }

    if (objectUrls.value.has(info.url)) {
        window.open(objectUrls.value.get(info.url), "_blank");
        return;
    }

    try {
        const response = await fetch(info.url, {
            headers: {
                Authorization: `Bearer ${getAuthToken()}`,
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to load file: ${response.status}`);
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        objectUrls.value.set(info.url, objectUrl);
        window.open(objectUrl, "_blank");
    } catch (error) {
        console.error("Error opening protected file:", error);
    }
};

const handlePreviewClick = (metadata) => {
    if (isImageFile(metadata)) {
        activePreview.value = metadata;
    } else {
        const info = resolveFileSourceUrl(metadata);
        if (info) {
            window.open(info.url, "_blank");
        }
    }
};

const filePreviewAlt = (metadata) => {
    return String(metadata?.name ?? "Image preview");
};

const fileCount = (value) => {
    return normalizeFileMetadataList(value).length;
};

const additionalFileCount = (value) => {
    return Math.max(0, fileCount(value) - 1);
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
        case "file":
            return "min-w-64";
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
                                    <span v-if="relationFields[column]?.targetCollectionName"
                                        class="text-xs font-medium text-muted-foreground leading-none">
                                        {{ relationFields[column].targetCollectionName }}
                                    </span>
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
                                <div v-else-if="isFileColumn(column, columnTypes)" class="flex items-center gap-1.5 min-w-0">
                                    <template v-for="(file, index) in displayFilesForDataTable(record[column])" :key="file.path">
                                        <div v-if="isImageFile(file)" class="relative group shrink-0">
                                            <a href="javascript:void(0)"
                                                class="shrink-0" @click.stop="handlePreviewClick(file)">
                                                <img v-if="resolveFileSourceUrl(file)?.protected" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                                                    :ref="(el) => imageRef(el, String(record.id), column, index)"
                                                    :alt="filePreviewAlt(file)"
                                                    class="h-10 w-10 rounded-md border bg-muted object-cover hover:ring-2 hover:ring-primary/50 transition-all" />
                                                <img v-else-if="filePreviewUrl(file)"
                                                    :src="filePreviewUrl(file)"
                                                    :alt="filePreviewAlt(file)"
                                                    class="h-10 w-10 rounded-md border bg-muted object-cover hover:ring-2 hover:ring-primary/50 transition-all"
                                                    loading="lazy" />
                                            </a>
                                        </div>
                                    </template>
                                    <span v-if="fileCount(record[column]) > 3" class="text-[10px] font-bold text-muted-foreground whitespace-nowrap bg-muted px-1.5 py-0.5 rounded-sm">
                                        +{{ fileCount(record[column]) - 3 }}
                                    </span>
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

    <Dialog :open="!!activePreview" @update:open="(val) => !val && (activePreview = null)">
        <DialogContent class="max-w-[90vw] max-h-[90vh] p-0 overflow-hidden bg-transparent border-none shadow-none  flex items-center justify-center">
            <template v-if="activePreview">
                <div class="relative flex items-center justify-center w-full h-full p-12">
                     <button 
                        type="button"
                        class="fixed top-6 right-6 z-[60] rounded-full bg-white/10 p-3 text-white backdrop-blur-md transition-all hover:bg-white/20 hover:scale-110 active:scale-95 ring-1 ring-white/20"
                        @click="activePreview = null"
                    >
                        <X class="h-6 w-6" />
                        <span class="sr-only">Close</span>
                    </button>

                    <img
                        :src="objectUrls.get(resolveFileSourceUrl(activePreview)?.url) || filePreviewUrl(activePreview)"
                        :alt="filePreviewAlt(activePreview)"
                        class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl bg-black/20 animate-in zoom-in-95 duration-300"
                        @load="(e) => handleImageLoad(e, activePreview)"
                    />
                    <div class="absolute bottom-6 left-0 right-0 text-center animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <div class="inline-block bg-black/60 px-4 py-2 rounded-full backdrop-blur-md ring-1 ring-white/10 shadow-xl">
                            <p class="text-white text-sm font-semibold tracking-wide">{{ activePreview.name ?? activePreview.path }}</p>
                        </div>
                    </div>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
