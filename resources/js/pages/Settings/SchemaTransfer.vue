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
  if (list.includes(value)) {
    const index = list.indexOf(value);
    list.splice(index, 1);

    return;
  }

  list.push(value);
};

const handleExport = async () => {
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
      <CardContent class="space-y-4">
        <div class="space-y-2">
          <Label>Collections (metadata + records)</Label>
          <div class="grid gap-2 md:grid-cols-2">
            <label v-for="collection in availableCollections" :key="collection.id" class="flex items-center gap-2 text-sm cursor-pointer">
              <Checkbox :checked="isCollectionSelected(collection.name)" @update:checked="toggleSelection(selectedCollections, collection.name)" />
              <span>{{ collection.name }}</span>
              <span v-if="collection.is_system" class="text-xs text-muted-foreground">(system)</span>
            </label>
          </div>
        </div>

        <div class="space-y-2">
          <Label>System Tables (records only)</Label>
          <div class="grid gap-2 md:grid-cols-2">
            <label v-for="table in availableSystemTables" :key="table" class="flex items-center gap-2 text-sm cursor-pointer">
              <Checkbox :checked="selectedSystemTables.includes(table)" @update:checked="toggleSelection(selectedSystemTables, table)" />
              <span>{{ table }}</span>
            </label>
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm cursor-pointer">
          <Checkbox :checked="includeRecords" @update:checked="includeRecords = !includeRecords" />
          <span>Include records</span>
        </label>

        <div class="flex gap-2">
          <Button :disabled="optionsLoading || exporting" @click="handleExport">
            {{ exporting ? "Exporting..." : "Export JSON" }}
          </Button>
          <Button variant="outline" :disabled="!exportedJson" @click="handleDownloadExport">
            Download
          </Button>
        </div>

        <div class="space-y-2">
          <Label>Export Result</Label>
          <textarea
            v-model="exportedJson"
            rows="12"
            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-mono"
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
      <CardContent class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2">
          <div class="space-y-2">
            <Label>Conflict Mode</Label>
            <select
              v-model="importConflict"
              class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              <option value="skip">skip</option>
              <option value="overwrite">overwrite</option>
            </select>
          </div>
          <div class="space-y-2">
            <Label>Upload JSON</Label>
            <Input type="file" accept="application/json" @change="handleImportFile" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Import Payload</Label>
          <textarea
            v-model="importJson"
            rows="12"
            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs font-mono"
            placeholder="Paste exported JSON here"
          ></textarea>
        </div>

        <Button :disabled="importing" @click="handleImport">
          {{ importing ? "Importing..." : "Import JSON" }}
        </Button>

        <Separator />

        <div v-if="importResult" class="space-y-2">
          <Label>Import Result</Label>
          <pre class="max-h-96 overflow-auto rounded-md border border-border bg-muted/30 p-3 text-xs">{{ JSON.stringify(importResult, null, 2) }}</pre>
        </div>
      </CardContent>
    </Card>
  </div>
</template>

