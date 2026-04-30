<script setup>
import axios from "axios";
import {computed, onMounted, ref} from "vue";
import {toast} from "vue-sonner";
import {
    Button,
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    Input,
    Label,
    Separator,
    Checkbox
} from "@/components/ui";

const optionsLoading = ref(false);
const exporting = ref(false);
const importing = ref(false);

const availableCollections = ref([]);
const availableSystemTables = ref([]);
const selectedCollections = ref([]);
const selectedSystemTables = ref([]);

const includeRecords = ref(true);
const exportedJson = ref("");

const importConflict = ref("skip");
const importJson = ref("");
const importResult = ref(null);

const exportPayload = computed(() => {
  return {
    collections: selectedCollections.value,
    system_tables: selectedSystemTables.value,
    include_records: includeRecords.value,
  };
});

const fetchOptions = async () => {
  optionsLoading.value = true;

  try {
    const response = await axios.get("/api/schema/transfer/options");
    availableCollections.value = response.data?.data?.collections || [];
    availableSystemTables.value = response.data?.data?.system_tables || [];
  } finally {
    optionsLoading.value = false;
  }
};

const isCollectionSelected = (collectionName) => {
    return selectedCollections.value.includes(collectionName);
};

const toggleSelection = (list, value) => {
  const index = list.indexOf(value);
  if (index > -1) {
    list.splice(index, 1);
  } else {
    list.push(value);
  }
};

const selectAllCollections = () => {
  selectedCollections.value = availableCollections.value.map(c => c.name);
};

const selectNoneCollections = () => {
  selectedCollections.value = [];
};

const selectAllSystemTables = () => {
  selectedSystemTables.value = [...availableSystemTables.value];
};

const selectNoneSystemTables = () => {
  selectedSystemTables.value = [];
};

const handleExport = async () => {
  if (selectedCollections.value.length === 0 && selectedSystemTables.value.length === 0) {
    toast.error("Please select at least one collection or system table to export");
    return;
  }
  exporting.value = true;
  importResult.value = null;

  try {
    const response = await axios.post("/api/schema/transfer/export", exportPayload.value);
    exportedJson.value = JSON.stringify(response.data?.data || {}, null, 2);
    toast.success("Export generated successfully");
  } finally {
    exporting.value = false;
  }
};

const handleDownloadExport = () => {
  if (!exportedJson.value.trim()) {
    return;
  }

  const blob = new Blob([exportedJson.value], { type: "application/json;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = `schema-transfer-${new Date().toISOString()}.json`;
  anchor.click();
  URL.revokeObjectURL(url);
};

const handleImportFile = async (event) => {
  const file = event.target?.files?.[0];

  if (!file) {
    return;
  }

  importJson.value = await file.text();
};

const handleImport = async () => {
  if (!importJson.value.trim()) {
    toast.error("Please provide an import payload");
    return;
  }
  importing.value = true;
  importResult.value = null;

  let payload = null;

  try {
    payload = JSON.parse(importJson.value || "{}");
  } catch {
    importing.value = false;
    toast.error("Import JSON is invalid");

    return;
  }

  try {
    const response = await axios.post('/api/schema/transfer/import', {
      payload,
      conflict: importConflict.value,
    });

    importResult.value = response.data?.data || null;
    toast.success("Import completed");
  } catch (error) {
    toast.error(error.response?.data?.message || "Import failed");
  } finally {
    importing.value = false;
  }
};

onMounted(() => {
  fetchOptions();
});
</script>

<template>
  <div class="space-y-6">
    <Card>
      <CardHeader>
        <CardTitle>Schema Transfer Export</CardTitle>
        <CardDescription>
          Select collections and system tables, then export a DB-agnostic JSON payload.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <Label class="text-base font-semibold">Collections (metadata + records)</Label>
            <div class="flex gap-2">
              <Button variant="ghost" size="xs" @click="selectAllCollections">Select All</Button>
              <Button variant="ghost" size="xs" @click="selectNoneCollections">None</Button>
            </div>
          </div>
          <div class="grid gap-3 p-4 rounded-lg border bg-muted/20 md:grid-cols-2 lg:grid-cols-3">
            <label v-for="collection in availableCollections" :key="collection.id" class="flex items-center gap-2 text-sm cursor-pointer hover:text-primary transition-colors">
              <Checkbox :checked="isCollectionSelected(collection.name)" @update:checked="toggleSelection(selectedCollections, collection.name)" />
              <span class="truncate font-medium">{{ collection.name }}</span>
              <span v-if="collection.is_system" class="text-[10px] uppercase tracking-wider font-bold text-muted-foreground bg-muted px-1 rounded">system</span>
            </label>
          </div>
        </div>

        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <Label class="text-base font-semibold">System Tables (records only)</Label>
            <div class="flex gap-2">
              <Button variant="ghost" size="xs" @click="selectAllSystemTables">Select All</Button>
              <Button variant="ghost" size="xs" @click="selectNoneSystemTables">None</Button>
            </div>
          </div>
          <div class="grid gap-3 p-4 rounded-lg border bg-muted/20 md:grid-cols-2 lg:grid-cols-3">
            <label v-for="table in availableSystemTables" :key="table" class="flex items-center gap-2 text-sm cursor-pointer hover:text-primary transition-colors">
              <Checkbox :checked="selectedSystemTables.includes(table)" @update:checked="toggleSelection(selectedSystemTables, table)" />
              <span class="truncate font-medium">{{ table }}</span>
            </label>
          </div>
        </div>

        <div class="flex items-center justify-between p-4 rounded-lg border border-primary/20 bg-primary/5">
          <div class="space-y-0.5">
            <Label class="text-sm font-semibold">Include Records</Label>
            <p class="text-xs text-muted-foreground">If disabled, only the schema metadata will be exported.</p>
          </div>
          <Checkbox :checked="includeRecords" @update:checked="includeRecords = !includeRecords" class="h-5 w-5" />
        </div>

        <div class="flex gap-3 pt-2">
          <Button class="flex-1 md:flex-none" :disabled="optionsLoading || exporting" @click="handleExport">
            {{ exporting ? "Exporting..." : "Export JSON" }}
          </Button>
          <Button variant="outline" class="flex-1 md:flex-none" :disabled="!exportedJson" @click="handleDownloadExport">
            Download File
          </Button>
        </div>

        <div class="space-y-2 pt-4">
          <Label>Export Result Preview</Label>
          <textarea
            v-model="exportedJson"
            rows="10"
            readonly
            class="flex w-full rounded-md border border-input bg-muted/30 px-3 py-2 text-xs font-mono focus:ring-0"
            placeholder="Exported JSON will appear here"
          ></textarea>
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Schema Transfer Import</CardTitle>
        <CardDescription>
          Upload or paste exported JSON, then import with conflict mode skip or overwrite.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <div class="grid gap-6 md:grid-cols-2">
          <div class="space-y-2">
            <Label>Conflict Mode</Label>
            <select
              v-model="importConflict"
              class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
            >
              <option value="skip">Skip existing (metadata only)</option>
              <option value="overwrite">Overwrite existing (metadata + merge fields)</option>
            </select>
            <p class="text-[10px] text-muted-foreground px-1">
              Overwrite mode will intelligently merge fields for existing collections.
            </p>
          </div>
          <div class="space-y-2">
            <Label>Upload Export File</Label>
            <Input type="file" accept="application/json" class="cursor-pointer" @change="handleImportFile" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Import Payload (JSON)</Label>
          <textarea
            v-model="importJson"
            rows="10"
            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-mono focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            placeholder="Paste exported JSON here or upload a file above"
          ></textarea>
        </div>

        <Button class="w-full md:w-auto" :disabled="importing || !importJson" @click="handleImport">
          {{ importing ? "Importing..." : "Run Import" }}
        </Button>

        <Separator v-if="importResult" />

        <div v-if="importResult" class="space-y-3">
          <Label class="text-base font-semibold">Import Result</Label>
          <div class="rounded-lg border bg-muted/30 p-4">
            <pre class="max-h-96 overflow-auto text-[11px] font-mono leading-relaxed">{{ JSON.stringify(importResult, null, 2) }}</pre>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>


