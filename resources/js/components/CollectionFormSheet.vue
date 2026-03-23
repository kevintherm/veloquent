<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import axios from "axios";
import { toast } from "vue-sonner";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetFooter,
  Button,
  Input,
  Label,
  Separator,
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from "@/components/ui";
import { Plus, Trash2, Copy, ArrowDown, ArrowUp, Settings2, FileJson, MoreVertical, Wrench, Lock, Unlock, List, Eye, Pencil, CirclePlus, RotateCcw } from "lucide-vue-next";
import { useDashboardState } from "@/lib/dashboardState";
import Select from "./ui/select/Select.vue";
import SelectTrigger from "./ui/select/SelectTrigger.vue";
import SelectValue from "./ui/select/SelectValue.vue";
import SelectContent from "./ui/select/SelectContent.vue";
import SelectItem from "./ui/select/SelectItem.vue";
import Checkbox from "./ui/Checkbox.vue";

const props = defineProps({
  sheetId: {
    type: String,
    required: true,
  },
  collection: {
    type: [Object, String],
    default: null,
  },
});

const emit = defineEmits(["close", "save", "delete"]);

const internalOpen = ref(false);
const loadingCollection = ref(false);
const submitting = ref(false);
const fetchedCollection = ref(null);
const validationErrors = ref({});
const availableCollections = ref([]);
const { activeCollection, requestCollectionsReload, requestRecordsReload } = useDashboardState();

const schemaCorrupt = ref(null); // { activity: string, collection_id: string }
const recovering = ref(false);

const defaultApiRules = () => ({
  list: null,
  view: null,
  create: null,
  update: null,
  delete: null,
});

const normalizeApiRules = (apiRules = {}) => ({
  list: apiRules?.list,
  view: apiRules?.view,
  create: apiRules?.create,
  update: apiRules?.update,
  delete: apiRules?.delete,
});

const fieldTypes = [
  { value: "text", label: "Text" },
  { value: "longtext", label: "Long Text" },
  { value: "number", label: "Number" },
  { value: "boolean", label: "Boolean" },
  { value: "timestamp", label: "Timestamp" },
  { value: "email", label: "Email" },
  { value: "url", label: "URL" },
  { value: "json", label: "JSON" },
  { value: "relation", label: "Relation" },
];

const collectionTypes = [
  { value: "base", label: "Base Collection" },
  { value: "auth", label: "Auth Collection" },
];

const apiRuleDefinitions = [
  { key: 'list', label: 'List Rule', icon: List, placeholder: "e.g. status = 'published'", description: "Use `field op value` expressions. Example: `status = \"published\" && views > 10`." },
  { key: 'view', label: 'View Rule', icon: Eye, placeholder: "e.g. status = 'published'", description: "Rule evaluated when viewing a single record." },
  { key: 'create', label: 'Create Rule', icon: CirclePlus, placeholder: "e.g. status = 'published'", description: "Main record fields represents the values that are going to be inserted to the database." },
  { key: 'update', label: 'Update Rule', icon: Pencil, placeholder: "e.g. status = 'published' || @request.body.status = 'draft'", description: "Main record fields represents the existing value, to target the values that are going to be inserted to the database use @request.body.*" },
  { key: 'delete', label: 'Delete Rule', icon: Trash2, placeholder: "e.g. status = 'published'", description: "Rule evaluated when deleting records." },
];

const isCreateMode = ref(!props.collection);

const isCreating = computed(() => {
  return isCreateMode.value;
});

const collectionIdentifier = computed(() => {
  if (!props.collection) {
    return null;
  }
  if (typeof props.collection === "string") {
    return props.collection;
  }
  return props.collection?.id ?? props.collection?.name ?? null;
});

const orderedFields = computed(() => {
  const fields = Array.isArray(formState.value.fields)
    ? formState.value.fields
    : [];
  return [...fields].sort((left, right) => {
    return Number(left?.order ?? 0) - Number(right?.order ?? 0);
  });
});

const orderedIndexes = computed(() => {
  const indexes = Array.isArray(formState.value.indexes)
    ? formState.value.indexes
    : [];
  return [...indexes].sort((left, right) => {
    return Number(left?.order ?? 0) - Number(right?.order ?? 0);
  });
});

const formState = ref({
  name: "",
  description: "",
  type: "base",
  fields: [],
  indexes: [],
  api_rules: defaultApiRules(),
  is_system: false,
});

const newField = ref({
  name: "",
  type: "text",
  nullable: false,
  unique: false,
  default: null,
  min: null,
  max: null,
  target_collection_id: null,
  cascade_on_delete: false,
  order: 0,
});

const newIndex = ref({
  columns: [],
  type: "index",
  order: 0,
});

const showNewFieldForm = ref(false);
const showNewIndexForm = ref(false);
const editingFieldIndex = ref(null);
const fieldsListContainer = ref(null);
const showDeleteCollectionDialog = ref(false);
const showTruncateCollectionDialog = ref(false);
const showCloseConfirmationDialog = ref(false);
const isCollectionModified = ref(false);

const normalizeCollectionPayload = (payload) => {
  if (payload?.data && !Array.isArray(payload.data)) {
    return payload.data;
  }
  return payload;
};

const baseReservedFieldNames = new Set(["id", "created_at", "updated_at"]);
const authReservedFieldNames = new Set(["id", "created_at", "updated_at", "email", "password", "email_visibility", "verified"]);

const getReservedFieldNames = (type) => {
  return type === "auth" ? authReservedFieldNames : baseReservedFieldNames;
};

const normalizeFieldForForm = (field) => {
  const normalized = { ...field };

  if (normalized.type === "relation") {
    normalized.target_collection_id = normalized.target_collection_id ?? normalized.collection ?? null;
    normalized.cascade_on_delete = Boolean(normalized.cascade_on_delete ?? false);
  }

  return normalized;
};

const normalizeIndexForForm = (index) => {
  if (index?.type === "unique" || index?.type === "index") {
    return {
      columns: Array.isArray(index.columns) ? [...index.columns] : [],
      type: index.type,
    };
  }

  return {
    columns: Array.isArray(index?.columns) ? [...index.columns] : [],
    type: index?.unique ? "unique" : "index",
  };
};

const isFieldIndexable = (type) => {
  return !["json", "longtext", "url"].includes(type);
};

const isRelationNeeded = computed(() => {
  return formState.value.fields.some(f => f.type === 'relation') ||
    newField.value.type === 'relation';
});

const isRelationFetched = ref(false);

const fetchCollectionForRelationFields = async () => {
  if (isRelationFetched.value) {
    return;
  }

  isRelationFetched.value = true;
  const loadingToastId = toast.loading("Fetching collections for relation fields...");
  try {
    const response = await axios.get("/api/collections", {
      params: {
        filter: `is_system = false`,
      },
    });

    const rows = Array.isArray(response?.data?.data) ? response.data.data : [];

    availableCollections.value = rows;
  } catch (error) {
    console.error(error);
    availableCollections.value = [];
    isRelationFetched.value = false;
  } finally {
    toast.dismiss(loadingToastId);
  }
};

watch(isRelationNeeded, (val) => {
  if (val) {
    fetchCollectionForRelationFields();
  }
}, { immediate: true });

const initializeFormState = () => {
  if (fetchedCollection.value) {
    isCreateMode.value = false;

    // Preserve all original field properties from API response
    const fields = Array.isArray(fetchedCollection.value.fields)
      ? fetchedCollection.value.fields.map(field => {
        // Ensure id is either a valid string or remove it entirely
        const fieldId = field.id;
        const cleanedField = normalizeFieldForForm(field);
        if (fieldId === undefined || fieldId === null || fieldId === '') {
          delete cleanedField.id;
        }
        return cleanedField;
      })
      : [];

    const indexes = Array.isArray(fetchedCollection.value.indexes)
      ? fetchedCollection.value.indexes.map((index) => normalizeIndexForForm(index))
      : [];

    formState.value = {
      name: fetchedCollection.value.name || "",
      description: fetchedCollection.value.description || "",
      type: fetchedCollection.value.type || "base",
      fields,
      indexes,
      api_rules: normalizeApiRules(fetchedCollection.value.api_rules),
      is_system: fetchedCollection.value.is_system || false,
    };
  } else {
    isCreateMode.value = true;

    formState.value = {
      name: "",
      description: "",
      type: "base",
      fields: [],
      indexes: [],
      api_rules: defaultApiRules(),
      is_system: false,
    };
  }
};

const fetchCollectionInfo = async () => {
  const identifier = collectionIdentifier.value;

  if (!identifier && !props.collection) {
    initializeFormState();
    internalOpen.value = true;
    return;
  }

  loadingCollection.value = true;
  const loadingToastId = toast.loading("Fetching collection...");

  try {
    const response = await axios.get(`/api/collections/${encodeURIComponent(identifier)}`);
    fetchedCollection.value = normalizeCollectionPayload(response?.data);
    initializeFormState();
    internalOpen.value = true;
  } catch (error) {
    console.error(error)
    toast.error("Failed to fetch collection");
    emit("close");
  } finally {
    toast.dismiss(loadingToastId);
    loadingCollection.value = false;
  }
};

const requestClose = () => {
  if (isCollectionModified.value) {
    showCloseConfirmationDialog.value = true;
    return;
  }

  handleClose();
}

const handleClose = () => {
  if (internalOpen.value === false) {
    return;
  }

  internalOpen.value = false;
  validationErrors.value = {};

  setTimeout(() => {
    emit("close");
    isCollectionModified.value = false;
  }, 300);
};

const clearValidationError = (key) => {
  isCollectionModified.value = true;

  if (!validationErrors.value[key]) {
    return;
  }

  const nextErrors = { ...validationErrors.value };
  delete nextErrors[key];
  validationErrors.value = nextErrors;
};

const setValidationErrors = (errors = {}) => {
  validationErrors.value = errors;
};

const firstErrorFor = (key) => {
  const error = validationErrors.value[key];

  if (Array.isArray(error) && error.length > 0) {
    return error[0];
  }

  return null;
};

const addField = () => {
  if (!newField.value.name.trim()) {
    toast.error("Field name is required");
    return;
  }

  const fieldExists = formState.value.fields.some(
    (f) => f.name === newField.value.name.trim()
  );

  if (getReservedFieldNames(formState.value.type).has(newField.value.name.trim())) {
    toast.error("Reserved fields are managed by the system.");
    return;
  }

  if (fieldExists) {
    toast.error("Field name already exists");
    return;
  }

  formState.value.fields.push({
    ...normalizeFieldForForm(newField.value),
    name: newField.value.name.trim(),
    order: formState.value.fields.length,
  });

  newField.value = {
    name: "",
    type: "text",
    nullable: false,
    unique: false,
    default: null,
    min: null,
    max: null,
    target_collection_id: null,
    cascade_on_delete: false,
    order: 0,
  };

  editingFieldIndex.value = null;
  showNewFieldForm.value = false;
};

const removeField = (index) => {
  const field = formState.value.fields[index];
  if (field && field.id) {
    field._deleted = !field._deleted;
    isCollectionModified.value = true;
  } else {
    formState.value.fields.splice(index, 1);
    reorderFields();
  }
};

const moveField = (index, direction) => {
  const newIndex = direction === "up" ? index - 1 : index + 1;
  if (newIndex < 0 || newIndex >= formState.value.fields.length) {
    return;
  }
  const temp = formState.value.fields[index];
  formState.value.fields[index] = formState.value.fields[newIndex];
  formState.value.fields[newIndex] = temp;
  reorderFields();
  editingFieldIndex.value = null;
};

const reorderFields = () => {
  let activeIdx = 0;
  formState.value.fields.forEach((field) => {
    if (!field._deleted) {
      field.order = activeIdx++;
    }
  });
};

const isExistingField = (field) => {
  return !isCreating.value && Boolean(field?.id);
};

const addIndex = () => {
  if (newIndex.value.columns.length === 0) {
    toast.error("Select at least one column");
    return;
  }

  const indexExists = formState.value.indexes.some((index) => {
    const sameType = (index.type ?? "index") === (newIndex.value.type ?? "index");
    const sameColumns = JSON.stringify(index.columns ?? []) === JSON.stringify(newIndex.value.columns ?? []);

    return sameType && sameColumns;
  });

  if (indexExists) {
    toast.error("Duplicate index definition");
    return;
  }

  formState.value.indexes.push({
    columns: [...newIndex.value.columns],
    type: newIndex.value.type,
    order: formState.value.indexes.length,
  });

  newIndex.value = {
    columns: [],
    type: "index",
    order: 0,
  };
  showNewIndexForm.value = false;
};

const removeIndex = (index) => {
  formState.value.indexes.splice(index, 1);
  reorderIndexes();
};

const reorderIndexes = () => {
  formState.value.indexes.forEach((index, idx) => {
    index.order = idx;
  });
};

const toggleColumnInIndex = (columnName) => {
  const idx = newIndex.value.columns.indexOf(columnName);
  if (idx > -1) {
    newIndex.value.columns.splice(idx, 1);
  } else {
    newIndex.value.columns.push(columnName);
  }
};

const buildPayload = () => {
  // Clean up fields - exclude deleted fields
  const cleanedFields = formState.value.fields
    .filter(field => !field._deleted)
    .map(field => {
      const cleaned = { ...field };
      // Only include id if it's a non-empty string
      if (cleaned.id === undefined || cleaned.id === null || cleaned.id === '') {
        delete cleaned.id;
      } else if (typeof cleaned.id !== 'string') {
        cleaned.id = String(cleaned.id);
      }
      if (cleaned.type !== "relation") {
        delete cleaned.target_collection_id;
        delete cleaned.cascade_on_delete;
        delete cleaned.collection;
      } else {
        cleaned.target_collection_id = cleaned.target_collection_id ?? cleaned.collection ?? null;
        cleaned.cascade_on_delete = Boolean(cleaned.cascade_on_delete ?? false);
        delete cleaned.collection;
      }

      return cleaned;
    });

  const cleanedIndexes = formState.value.indexes.map((index) => ({
    columns: Array.isArray(index.columns) ? [...index.columns] : [],
    type: index.type === "unique" ? "unique" : "index",
  }));

  return {
    name: formState.value.name,
    description: formState.value.description,
    type: formState.value.type,
    fields: cleanedFields,
    indexes: cleanedIndexes,
    api_rules: normalizeApiRules(formState.value.api_rules),
  };
};

const validateForm = () => {
  if (!formState.value.name.trim()) {
    toast.error("Collection name is required");
    return false;
  }

  if (!/^[a-zA-Z_]+$/.test(formState.value.name)) {
    toast.error("Collection name may only contain letters and underscores");
    return false;
  }

  return true;
};

const handleSave = async () => {
  if (!validateForm()) {
    return;
  }

  submitting.value = true;
  validationErrors.value = {};

  try {
    const payload = buildPayload();
    let response;

    if (isCreating.value) {
      response = await axios.post("/api/collections", payload);
      toast.success("Collection created successfully");
      activeCollection.value = response?.data?.data;
    } else {
      const identifier = fetchedCollection.value?.id ?? collectionIdentifier.value;
      response = await axios.put(`/api/collections/${encodeURIComponent(identifier)}`, payload);
      toast.success("Collection updated successfully");

      if (activeCollection.value?.id === response?.data?.data?.id) {
        activeCollection.value = response?.data?.data;
        requestRecordsReload();
      }
    }

    emit("save", response?.data?.data ?? null);
    requestCollectionsReload();
    handleClose();
  } catch (error) {
    if (error?.response?.status === 409 && error?.response?.data?.error_type === "SCHEMA_CORRUPT") {
      schemaCorrupt.value = {
        activity: error.response.data.activity,
        collection_id: error.response.data.collection_id,
      };
      toast.error("Schema is corrupt. Recovery required.");
      return;
    }

    if (error?.response?.status === 422) {
      const errors = error?.response?.data?.errors;
      setValidationErrors(errors ?? {});

      const firstError = Object.values(errors ?? {})?.[0]?.[0] ?? "Validation failed";
      toast.error(firstError);
    }
  } finally {
    submitting.value = false;
  }
};

const handleDelete = async () => {
  if (!fetchedCollection.value?.id) {
    return;
  }

  submitting.value = true;
  showDeleteCollectionDialog.value = false;

  try {
    await axios.delete(`/api/collections/${encodeURIComponent(fetchedCollection.value.id)}`);
    toast.success("Collection deleted successfully");
    emit("delete", fetchedCollection.value.id);
    requestCollectionsReload();
    handleClose();
  } finally {
    submitting.value = false;
  }
};

const handleTruncate = async () => {
  if (!fetchedCollection.value?.id) {
    return;
  }

  submitting.value = true;
  showTruncateCollectionDialog.value = false;

  try {
    await axios.delete(`/api/collections/${encodeURIComponent(fetchedCollection.value.id)}/truncate`);
    toast.success("Collection truncated successfully");
    requestRecordsReload();
  } catch (error) {
    toast.error(error?.response?.data?.message || "Failed to truncate collection");
  } finally {
    submitting.value = false;
  }
};

const handleRecover = async () => {
  if (!schemaCorrupt.value?.collection_id) {
    return;
  }

  recovering.value = true;

  try {
    await axios.post(`/api/collections/${encodeURIComponent(schemaCorrupt.value.collection_id)}/recover`);
    toast.success("Collection recovered successfully");
    schemaCorrupt.value = null;
    await fetchCollectionInfo();
    requestCollectionsReload();
  } finally {
    recovering.value = false;
  }
};

const requestDeleteCollection = () => {
  if (!fetchedCollection.value?.id) {
    return;
  }

  showDeleteCollectionDialog.value = true;
};

const requestTruncateCollection = () => {
  if (!fetchedCollection.value?.id) {
    return;
  }

  showTruncateCollectionDialog.value = true;
};

const handleCopy = () => {
  if (!fetchedCollection.value) {
    return;
  }

  isCreateMode.value = true;

  const reservedFields = getReservedFieldNames(fetchedCollection.value.type);

  const copiedFields = (fetchedCollection.value.fields || [])
    .filter((field) => !reservedFields.has(String(field?.name ?? "")))
    .map((field, index) => {
      const copiedField = {
        ...field,
        order: index,
      };

      delete copiedField.id;

      return copiedField;
    });

  const copiedIndexes = (fetchedCollection.value.indexes || [])
    .map((index) => {
      const columns = Array.isArray(index?.columns)
        ? index.columns.filter((column) => !reservedFields.has(String(column ?? "")))
        : [];

      return {
        ...index,
        columns,
      };
    })
    .filter((index) => index.columns.length > 0);

  const copyData = {
    name: `${fetchedCollection.value.name}_copy`,
    description: fetchedCollection.value.description || "",
    type: fetchedCollection.value.type || "base",
    fields: JSON.parse(JSON.stringify(copiedFields)),
    indexes: JSON.parse(JSON.stringify(copiedIndexes)),
    api_rules: normalizeApiRules(JSON.parse(JSON.stringify(fetchedCollection.value.api_rules || {}))),
  };

  formState.value = copyData;
  toast.success("Collection copied. Modify the name and save to create a new collection.");
};

const handleCopyRawJson = () => {
  try {
    const payload = buildPayload();
    const json = JSON.stringify(payload, null, 2);
    navigator.clipboard.writeText(json);
    toast.success("Raw JSON copied to clipboard");
  } catch (error) {
    console.error(error);
    toast.error("Failed to copy raw JSON");
  }
};

watch(showNewFieldForm, () => {
  void nextTick(() => {
    fieldsListContainer.value?.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  });
});

onMounted(async () => {
  await fetchCollectionInfo();
});
</script>

<template>
  <Sheet :open="internalOpen" @update:open="(isOpen) => { if (!isOpen) requestClose(); }">
    <SheetContent side="right" class="sm:max-w-2xl max-w-full flex h-full flex-col overflow-hidden">
      <SheetHeader>
        <SheetTitle>{{ isCreating ? 'Create' : 'Edit' }} Collection</SheetTitle>
        <SheetDescription>
          {{ isCreating ? 'Define a new collection and its fields.' : 'Modify existing collection settings and fields.'
          }}
        </SheetDescription>
      </SheetHeader>

      <div v-if="schemaCorrupt" class="p-4 rounded-md bg-destructive/10 border border-destructive/20">
        <div class="flex items-start gap-3">
          <Settings2 class="w-5 h-5 text-destructive shrink-0 mt-0.5" />
          <div class="flex-1">
            <h4 class="font-semibold text-destructive mb-1">Schema Corruption Detected</h4>
            <p class="text-sm text-muted-foreground mb-3">
              A previous database operation for this collection failed partway through, leaving it in an inconsistent
              state.
              <span v-if="schemaCorrupt.activity === 'create'">The underlying table might exist but is not
                linked.</span>
              <span v-else>The database table structure might not match the metadata. Rebuilding table means any
                existing data will be permanently deleted.</span>
            </p>
            <Button variant="destructive" size="sm" :disabled="recovering" @click="handleRecover">
              <Wrench v-if="!recovering" class="w-4 h-4 mr-2" />
              <span v-else
                class="w-4 h-4 mr-2 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
              {{ schemaCorrupt.activity === 'create' ? 'Drop Table & Clean Up' : 'Rebuild Table from Metadata' }}
            </Button>
          </div>
        </div>
      </div>

      <Separator />

      <div v-if="loadingCollection" class="py-8 text-center text-muted-foreground">
        Loading collection...
      </div>

      <div v-else class="mt-4 flex min-h-0 flex-1 flex-col overflow-hidden">
        <Tabs defaultValue="fields" class="flex min-h-0 flex-1 flex-col overflow-hidden">
          <TabsList class="w-full grid grid-cols-3">
            <TabsTrigger value="fields">Fields</TabsTrigger>
            <TabsTrigger value="indexes">Indexes</TabsTrigger>
            <TabsTrigger value="api">API Rules</TabsTrigger>
          </TabsList>

          <!-- Fields Tab -->
          <TabsContent value="fields" class="mt-4 flex min-h-0 flex-1 flex-col px-1 overflow-hidden">
            <!-- Basic Info - Fixed, no scroll -->
            <div class="grid gap-4 mb-4">
              <div class="grid gap-2">
                <Label for="collectionName">Collection Name</Label>
                <Input id="collectionName" v-model="formState.name" placeholder="e.g. products, blog_posts"
                  :disabled="formState.is_system" @input="clearValidationError('name')" />
                <p v-if="firstErrorFor('name')" class="text-xs text-destructive">{{ firstErrorFor('name') }}</p>
              </div>

              <div class="grid gap-2">
                <Label for="collectionDescription">Description</Label>
                <Input id="collectionDescription" v-model="formState.description" placeholder="Optional description"
                  @input="clearValidationError('description')" />
                <p v-if="firstErrorFor('description')" class="text-xs text-destructive">{{ firstErrorFor('description')
                  }}</p>
              </div>

              <div class="grid gap-2">
                <Label for="collectionType">Type</Label>
                <select id="collectionType" v-model="formState.type"
                  class="h-10 rounded-md border border-input bg-background px-3 text-sm" :disabled="!isCreating"
                  @change="clearValidationError('type')">
                  <option v-for="ct in collectionTypes" :key="ct.value" :value="ct.value">
                    {{ ct.label }}
                  </option>
                </select>
                <p v-if="firstErrorFor('type')" class="text-xs text-destructive">{{ firstErrorFor('type') }}</p>
              </div>
            </div>

            <Separator />

            <!-- Fields List - Scrollable -->
            <div ref="fieldsListContainer" class="flex-1 min-h-0 space-y-3 overflow-y-auto pr-2 pb-6">
              <div class="flex items-center justify-between sticky top-0 bg-background py-2 z-10">
                <h3 class="text-sm font-semibold">Schema Fields</h3>
                <Button variant="outline" size="sm" @click="showNewFieldForm = true">
                  <Plus class="h-4 w-4 mr-1" />
                  Add Field
                </Button>
              </div>

              <!-- New Field Form -->
              <div v-if="showNewFieldForm"
                class="p-5 border border-primary/20 rounded-lg flex flex-col gap-5 bg-primary/5 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                  <div class="space-y-2">
                    <Label class="text-xs font-semibold tracking-wide text-primary/80 uppercase">Field Name</Label>
                    <Input v-model="newField.name" class="h-9 focus-visible:ring-1 border-primary/20 bg-background"
                      placeholder="e.g. title, email" />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-xs font-semibold tracking-wide text-primary/80 uppercase">Field Type</Label>
                    <select v-model="newField.type"
                      class="flex h-9 w-full items-center justify-between rounded-md border border-primary/20 bg-background px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring">
                      <option v-for="ft in fieldTypes" :key="ft.value" :value="ft.value">
                        {{ ft.label }}
                      </option>
                    </select>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                  <div class="space-y-2">
                    <Label class="text-xs font-semibold tracking-wide text-primary/80 uppercase">Default Value</Label>
                    <Input v-model="newField.default" class="h-9 focus-visible:ring-1 border-primary/20 bg-background"
                      placeholder="Optional default value" />
                  </div>

                  <div v-if="newField.type === 'relation'" class="space-y-2">
                    <Label class="text-xs font-semibold tracking-wide text-primary/80 uppercase">Related
                      Collection</Label>
                    <select v-model="newField.target_collection_id"
                      class="flex h-9 w-full items-center justify-between rounded-md border border-primary/20 bg-background px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring">
                      <option :value="null">Select collection</option>
                      <option v-for="collection in availableCollections" :key="`new-field-target-${collection.id}`"
                        :value="collection.id">
                        {{ collection.name }}
                      </option>
                    </select>
                  </div>


                  <div v-if="['text', 'email', 'url'].includes(newField.type)"
                    class="space-y-2 grid grid-cols-2 gap-3 col-span-1 border-border/50">
                    <div class="space-y-2">
                      <Label class="text-xs font-semibold tracking-wide text-primary/80 uppercase">Min Length</Label>
                      <Input v-model.number="newField.min" type="number"
                        class="h-9 focus-visible:ring-1 border-primary/20 bg-background" placeholder="Optional" />
                    </div>
                    <div class="space-y-2">
                      <Label class="text-xs font-semibold tracking-wide text-primary/80 uppercase">Max Length</Label>
                      <Input v-model.number="newField.max" type="number"
                        class="h-9 focus-visible:ring-1 border-primary/20 bg-background" placeholder="Optional" />
                    </div>
                  </div>
                </div>

                <div class="pt-4 border-t border-primary/10 mt-1">
                  <Label
                    class="text-xs font-semibold tracking-wide text-primary/80 uppercase mb-3 block">Constraints</Label>
                  <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer group">
                      <Checkbox v-model="newField.nullable" />
                      <span
                        class="text-sm font-medium text-foreground/80 group-hover:text-foreground transition-colors">Allow
                        Null Values</span>
                    </label>
                  </div>
                  <div class="flex gap-3 justify-end mt-2">
                    <Button variant="outline" size="sm" class="border-primary/20 hover:bg-primary/10"
                      @click="showNewFieldForm = false">Cancel</Button>
                    <Button size="sm" @click="addField">Add Field</Button>
                  </div>
                </div>
              </div>

              <!-- Fields List -->
              <div v-for="(field, index) in orderedFields" :key="index"
                class="flex flex-col gap-2 p-3 border rounded-lg bg-background transition-all duration-200"
                :class="{ 'opacity-50 bg-destructive/5 border-destructive/20 relative overflow-hidden': field._deleted }">

                <!-- Summary Row -->
                <div class="flex items-center gap-2 relative z-20">
                  <div class="flex-1 grid grid-cols-4 gap-2 text-sm">
                    <div class="font-medium truncate">{{ field.name }}</div>
                    <div class="text-muted-foreground truncate">{{ field.type }}</div>
                    <div class="text-muted-foreground truncate">
                      <span v-if="field.nullable" class="text-xs mr-1">nullable</span>
                      <span v-if="field.unique" class="text-xs font-semibold">(unique)</span>
                    </div>
                    <div class="text-muted-foreground text-xs truncate">
                      <span v-if="field.min || field.max">min:{{ field.min }} max:{{ field.max }}</span>
                      <span v-if="field.target_collection_id">rel:{{ field.target_collection_id }}</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-1">
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="moveField(index, 'up')"
                      :disabled="index === 0 || field._deleted" title="Move Up">
                      <ArrowUp class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="moveField(index, 'down')"
                      :disabled="index === orderedFields.length - 1 || field._deleted" title="Move Down">
                      <ArrowDown class="h-4 w-4" />
                    </Button>
                    <Button variant="secondary" size="icon" class="h-8 w-8" :disabled="field._deleted"
                      @click="editingFieldIndex = editingFieldIndex === index ? null : index"
                      :class="{ 'bg-primary/20': editingFieldIndex === index }" title="Field Settings">
                      <Settings2 class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click="removeField(index)"
                      :disabled="['id', 'created_at', 'updated_at'].includes(field.name)"
                      :title="field._deleted ? 'Revert deletion' : 'Delete Field'">
                      <RotateCcw v-if="field._deleted" class="h-4 w-4 text-primary" />
                      <Trash2 v-else class="h-4 w-4" />
                    </Button>
                  </div>
                </div>

                <!-- General error for the field row -->
                <p v-if="firstErrorFor(`fields.${index}`)" class="text-xs text-destructive px-6">
                  {{ firstErrorFor(`fields.${index}`) }}
                </p>

                <!-- Expanded Field Settings Redesign -->
                <div v-if="editingFieldIndex === index"
                  class="mt-2 p-5 border rounded-md bg-muted/40 flex flex-col gap-5 shadow-sm">
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-2">
                      <Label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Field
                        Name</Label>
                      <Input v-model="field.name" class="h-9 focus-visible:ring-1" placeholder="e.g. title"
                        @input="clearValidationError(`fields.${index}.name`)" />
                      <p v-if="firstErrorFor(`fields.${index}.name`)" class="text-xs text-destructive">{{
                        firstErrorFor(`fields.${index}.name`) }}</p>
                    </div>

                    <div class="space-y-2">
                      <Label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Field
                        Type</Label>
                      <select v-model="field.type" :disabled="isExistingField(field)"
                        class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        @change="clearValidationError(`fields.${index}.type`)">
                        <option v-for="ft in fieldTypes" :key="ft.value" :value="ft.value">
                          {{ ft.label }}
                        </option>
                      </select>
                      <p v-if="isExistingField(field)" class="text-xs text-muted-foreground">
                        Field type is locked for existing fields. Delete and recreate the field to use a different type.
                      </p>
                      <p v-if="firstErrorFor(`fields.${index}.type`)" class="text-xs text-destructive">{{
                        firstErrorFor(`fields.${index}.type`) }}</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-2">
                      <Label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Default
                        Value</Label>
                      <Input v-model="field.default" class="h-9 focus-visible:ring-1"
                        placeholder="Optional default value" />
                    </div>

                    <div v-if="field.type === 'relation'" class="space-y-2">
                      <Label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Related
                        Collection</Label>
                      <select v-model="field.target_collection_id"
                        class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring">
                        <option :value="null">Select collection</option>
                        <option v-for="collection in availableCollections" :key="`field-target-${collection.id}`"
                          :value="collection.id">
                          {{ collection.name }}
                        </option>
                      </select>
                    </div>


                    <div v-if="['text', 'email', 'url'].includes(field.type)"
                      class="space-y-2 grid grid-cols-2 gap-3 col-span-1 border-border/50">
                      <div class="space-y-2">
                        <Label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Min
                          Length</Label>
                        <Input v-model.number="field.min" type="number" class="h-9 focus-visible:ring-1"
                          placeholder="Optional" />
                      </div>
                      <div class="space-y-2">
                        <Label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Max
                          Length</Label>
                        <Input v-model.number="field.max" type="number" class="h-9 focus-visible:ring-1"
                          placeholder="Optional" />
                      </div>
                    </div>
                  </div>

                  <div class="pt-4 border-t border-border mt-1">
                    <Label
                      class="text-xs font-semibold tracking-wide text-muted-foreground uppercase mb-3 block">Constraints</Label>
                    <div class="flex flex-wrap items-center gap-6">
                      <label class="flex items-center gap-2 cursor-pointer group">
                        <Checkbox v-model="field.nullable" />
                        <span
                          class="text-sm font-medium text-foreground/80 group-hover:text-foreground transition-colors">Allow
                          Null Values</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <p v-if="orderedFields.length === 0" class="text-sm text-muted-foreground text-center py-4">
                No fields defined yet.
              </p>
            </div>
          </TabsContent>

          <!-- Indexes Tab -->
          <TabsContent value="indexes" class="space-y-4 mt-4 flex-1 min-h-0 overflow-y-auto pr-2 pb-6">
            <div class="flex items-center justify-between pt-1">
              <h3 class="text-sm font-semibold">Database Indexes</h3>
              <Button variant="outline" size="sm" @click="showNewIndexForm = true">
                <Plus class="h-4 w-4 mr-1" />
                Add Index
              </Button>
            </div>

            <!-- New Index Form -->
            <div v-if="showNewIndexForm" class="p-4 border rounded-lg space-y-3 bg-muted/30">
              <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                  <Label>Unique</Label>
                  <Select v-model="newIndex.type">
                    <SelectTrigger>
                      <SelectValue placeholder="Select a fruit" />
                    </SelectTrigger>
                    <SelectContent class="bg-background">
                      <SelectItem value="index">
                        False
                      </SelectItem>
                      <SelectItem value="unique">
                        True
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div class="grid gap-2">
                <Label>Columns</Label>
                <div class="flex flex-wrap gap-2">
                  <template v-for="field in formState.fields" :key="field.name">
                    <button v-if="isFieldIndexable(field.type)" type="button" @click="toggleColumnInIndex(field.name)"
                      class="px-3 py-1 text-sm rounded-full border"
                      :class="newIndex.columns.includes(field.name) ? 'bg-primary text-primary-foreground' : 'bg-background'">
                      {{ field.name }}
                    </button>
                  </template>
                </div>
              </div>
              <div class="flex gap-2">
                <Button size="sm" @click="addIndex">Add</Button>
                <Button variant="outline" size="sm" @click="showNewIndexForm = false">Cancel</Button>
              </div>
            </div>

            <!-- Indexes List -->
            <div v-for="(index, idx) in orderedIndexes" :key="idx"
              class="flex items-center gap-2 p-3 border rounded-lg bg-background">
              <div class="flex-1 grid grid-cols-2 gap-2 text-sm">
                <div class="font-medium">{{ index.type }}</div>
                <div class="text-muted-foreground">
                  {{ index.columns.join(', ') }}
                </div>
              </div>
              <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click="removeIndex(idx)">
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>

            <p v-if="orderedIndexes.length === 0" class="text-sm text-muted-foreground text-center py-4">
              No indexes defined yet.
            </p>
          </TabsContent>

          <!-- API Rules Tab -->
          <TabsContent value="api" class="space-y-4 mt-4 flex-1 min-h-0 overflow-y-auto pr-2 pb-6">
            <div class="space-y-6">
              <div v-for="rule in apiRuleDefinitions" :key="rule.key"
                class="grid gap-3 p-4 border rounded-lg bg-background/50 shadow-sm relative overflow-hidden group transition-all duration-200 hover:border-primary/30">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <Label class="text-sm font-semibold flex items-center gap-2">
                      <component :is="rule.icon" class="w-4 h-4 text-muted-foreground mr-1" />
                      {{ rule.label }}
                    </Label>
                  </div>
                  <Button variant="ghost" size="sm"
                    class="h-8 px-2 text-xs gap-1.5 hover:bg-primary/5 transition-colors"
                    @click="formState.api_rules[rule.key] = formState.api_rules[rule.key] === null ? '' : null; isCollectionModified = true">
                    <template v-if="formState.api_rules[rule.key] === null">
                      <Unlock class="w-3.5 h-3.5" />
                      Unlock
                    </template>
                    <template v-else>
                      <Lock class="w-3.5 h-3.5" />
                      Lock
                    </template>
                  </Button>
                </div>

                <div class="relative group/input">
                  <textarea v-model="formState.api_rules[rule.key]"
                    class="w-full min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono focus-visible:ring-1 transition-opacity duration-200"
                    :class="{ 'opacity-10 pointer-events-none select-none': formState.api_rules[rule.key] === null }"
                    :placeholder="rule.placeholder" @input="clearValidationError(`api_rules.${rule.key}`)"></textarea>

                  <div v-if="formState.api_rules[rule.key] === null"
                    class="absolute inset-0 flex flex-col items-center justify-center bg-background/20 backdrop-blur-[1px] cursor-pointer rounded-md border border-dashed border-primary/20 hover:bg-primary/5 transition-colors group/overlay"
                    @click="formState.api_rules[rule.key] = ''">
                    <Lock class="w-6 h-6 text-primary/40 mb-2 group-hover/overlay:text-primary transition-colors" />
                    <p class="text-sm font-semibold text-primary/60 group-hover/overlay:text-primary transition-colors">
                      Superusers Only</p>
                    <p class="text-[10px] text-muted-foreground mt-1 px-6 text-center">Click here or the unlock button
                      to
                      define custom access rules</p>
                  </div>
                </div>

                <p class="text-[11px] text-muted-foreground leading-relaxed">{{ rule.description }}</p>
                <p v-if="firstErrorFor(`api_rules.${rule.key}`)" class="text-xs text-destructive font-medium mt-1">
                  {{ firstErrorFor(`api_rules.${rule.key}`) }}
                </p>
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </div>

      <SheetFooter class="mt-6 border-t bg-background pt-6">
        <div class="flex gap-2 w-full">
          <div v-if="!isCreating && !formState.is_system" class="flex gap-2">
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline" class="flex-1">
                  <MoreVertical class="h-4 w-4" />
                  <div class="sr-only">Actions</div>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-56 bg-background" align="start">
                <DropdownMenuItem @click="requestDeleteCollection" class="text-destructive focus:text-destructive">
                  <Trash2 class="h-4 w-4 mr-2" />
                  Delete Collection
                </DropdownMenuItem>
                <DropdownMenuItem @click="requestTruncateCollection" class="text-destructive focus:text-destructive">
                  <Trash2 class="h-4 w-4 mr-2" />
                  Truncate Collection
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="handleCopy">
                  <Copy class="h-4 w-4 mr-2" />
                  Copy Collection
                </DropdownMenuItem>
                <DropdownMenuItem @click="handleCopyRawJson">
                  <FileJson class="h-4 w-4 mr-2" />
                  Copy Raw JSON
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
          <Button variant="outline" class="flex-1" @click="requestClose">Cancel</Button>
          <Button class="flex-1" :disabled="submitting" @click="handleSave">
            {{ submitting ? 'Saving...' : (isCreating ? 'Create Collection' : 'Save Changes') }}
          </Button>
        </div>
      </SheetFooter>

      <AlertDialog :open="showTruncateCollectionDialog"
        @update:open="(value) => { showTruncateCollectionDialog = value; }">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Truncate collection?</AlertDialogTitle>
            <AlertDialogDescription>
              This will delete all records in "{{ fetchedCollection?.name }}" and cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel :disabled="submitting">Cancel</AlertDialogCancel>
            <AlertDialogAction :disabled="submitting" @click="handleTruncate">Truncate</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog :open="showDeleteCollectionDialog" @update:open="(value) => { showDeleteCollectionDialog = value; }">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete collection?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete "{{ fetchedCollection?.name }}" and cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel :disabled="submitting">Cancel</AlertDialogCancel>
            <AlertDialogAction :disabled="submitting" @click="handleDelete">Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog :open="showCloseConfirmationDialog"
        @update:open="(value) => { showCloseConfirmationDialog = value; }">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Close sheet?</AlertDialogTitle>
            <AlertDialogDescription>
              Unsaved changes will be gone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction @click="handleClose">Close</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </SheetContent>
  </Sheet>
</template>
