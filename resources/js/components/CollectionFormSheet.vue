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
} from "@/components/ui";
import { Plus, Trash2, Copy, ArrowDown, ArrowUp, Settings2 } from "lucide-vue-next";
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

const defaultApiRules = () => ({
  list: "",
  view: "",
  create: "",
  update: "",
  delete: "",
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
  max_select: 1,
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

const normalizeCollectionPayload = (payload) => {
  if (payload?.data && !Array.isArray(payload.data)) {
    return payload.data;
  }
  return payload;
};

const reservedFieldNames = new Set(["id", "created_at", "updated_at"]);

const normalizeFieldForForm = (field) => {
  const normalized = { ...field };

  if (normalized.type === "relation") {
    normalized.target_collection_id = normalized.target_collection_id ?? normalized.collection ?? null;
    normalized.max_select = Number(normalized.max_select ?? 1);
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

const fetchAvailableCollections = async () => {
  try {
    const response = await axios.get("/api/collections");
    const rows = Array.isArray(response?.data?.data) ? response.data.data : [];

    availableCollections.value = rows;
  } catch {
    availableCollections.value = [];
  }
};

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

const handleClose = () => {
  internalOpen.value = false;
  validationErrors.value = {};
  emit("close");
};

const clearValidationError = (key) => {
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

  if (reservedFieldNames.has(newField.value.name.trim())) {
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
    max_select: 1,
    cascade_on_delete: false,
    order: 0,
  };

  editingFieldIndex.value = null;
  showNewFieldForm.value = false;
};

const removeField = (index) => {
  formState.value.fields.splice(index, 1);
  reorderFields();
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
  formState.value.fields.forEach((field, idx) => {
    field.order = idx;
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
  // Clean up fields - ensure id is either a valid string or not present
  const cleanedFields = formState.value.fields.map(field => {
    const cleaned = { ...field };
    // Only include id if it's a non-empty string
    if (cleaned.id === undefined || cleaned.id === null || cleaned.id === '') {
      delete cleaned.id;
    } else if (typeof cleaned.id !== 'string') {
      cleaned.id = String(cleaned.id);
    }
    if (cleaned.type !== "relation") {
      delete cleaned.target_collection_id;
      delete cleaned.max_select;
      delete cleaned.cascade_on_delete;
      delete cleaned.collection;
    } else {
      cleaned.target_collection_id = cleaned.target_collection_id ?? cleaned.collection ?? null;
      cleaned.max_select = Number(cleaned.max_select ?? 1);
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

  const confirmed = window.confirm(
    `Are you sure you want to delete "${fetchedCollection.value.name}"? This action cannot be undone.`
  );

  if (!confirmed) {
    return;
  }

  submitting.value = true;

  try {
    await axios.delete(`/api/collections/${encodeURIComponent(fetchedCollection.value.id)}`);
    toast.success("Collection deleted successfully");
    emit("delete", fetchedCollection.value.id);
    requestCollectionsReload();
    handleClose();
  } catch (error) {
    toast.error(error?.response?.data?.message || "Failed to delete collection");
  } finally {
    submitting.value = false;
  }
};

const handleTruncate = async () => {
  if (!fetchedCollection.value?.id) {
    return;
  }

  const confirmed = window.confirm(
    `Are you sure you want to truncate all records in "${fetchedCollection.value.name}"? This action cannot be undone.`
  );

  if (!confirmed) {
    return;
  }

  submitting.value = true;

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

const handleCopy = () => {
  if (!fetchedCollection.value) {
    return;
  }

  isCreateMode.value = true;

  const copyData = {
    name: `${fetchedCollection.value.name}_copy`,
    description: fetchedCollection.value.description || "",
    type: fetchedCollection.value.type || "base",
    fields: JSON.parse(JSON.stringify(fetchedCollection.value.fields || [])),
    indexes: JSON.parse(JSON.stringify(fetchedCollection.value.indexes || [])),
    api_rules: normalizeApiRules(JSON.parse(JSON.stringify(fetchedCollection.value.api_rules || {}))),
  };

  formState.value = copyData;
  toast.success("Collection copied. Modify the name and save to create a new collection.");
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
  // await fetchAvailableCollections();
  await fetchCollectionInfo();
});
</script>

<template>
  <Sheet :open="internalOpen" @update:open="(isOpen) => { if (!isOpen) handleClose(); }">
    <SheetContent side="right" class="sm:max-w-2xl max-w-full flex h-full flex-col overflow-hidden">
      <SheetHeader>
        <SheetTitle>{{ isCreating ? 'Create' : 'Manage' }} Collection</SheetTitle>
        <SheetDescription>
          {{ isCreating ? 'Create a new collection with custom fields and settings.' : `Configure collection
          fields,indexes, and API rules.` }}
        </SheetDescription>
      </SheetHeader>

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

                  <div v-if="newField.type === 'relation'" class="space-y-2">
                    <Label class="text-xs font-semibold tracking-wide text-primary/80 uppercase">Max Select</Label>
                    <Input v-model.number="newField.max_select" type="number" min="1"
                      class="h-9 focus-visible:ring-1 border-primary/20 bg-background"
                      placeholder="1 for single, 2+ for multiple" />
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
                  <div class="flex flex-wrap items-center gap-6 mb-4">
                    <label class="flex items-center gap-2 cursor-pointer group">
                      <Checkbox v-model="newField.nullable" />
                      <span
                        class="text-sm font-medium text-foreground/80 group-hover:text-foreground transition-colors">Allow
                        Null Values</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer group">
                      <Checkbox v-model="newField.unique" />
                      <span
                        class="text-sm font-medium text-foreground/80 group-hover:text-foreground transition-colors">Must
                        Be Unique</span>
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
                class="flex flex-col gap-2 p-3 border rounded-lg bg-background">
                <!-- Summary Row -->
                <div class="flex items-center gap-2">
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
                      :disabled="index === 0" title="Move Up">
                      <ArrowUp class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="moveField(index, 'down')"
                      :disabled="index === orderedFields.length - 1" title="Move Down">
                      <ArrowDown class="h-4 w-4" />
                    </Button>
                    <Button variant="secondary" size="icon" class="h-8 w-8"
                      @click="editingFieldIndex = editingFieldIndex === index ? null : index"
                      :class="{ 'bg-primary/20': editingFieldIndex === index }" title="Field Settings">
                      <Settings2 class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click="removeField(index)"
                      :disabled="['id', 'created_at', 'updated_at'].includes(field.name)" title="Delete Field">
                      <Trash2 class="h-4 w-4" />
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

                    <div v-if="field.type === 'relation'" class="space-y-2">
                      <Label class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Max
                        Select</Label>
                      <Input v-model.number="field.max_select" type="number" min="1" class="h-9 focus-visible:ring-1"
                        placeholder="1 for single, 2+ for multiple" />
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
                      <label class="flex items-center gap-2 cursor-pointer group">
                        <Checkbox v-model="field.unique" />
                        <span
                          class="text-sm font-medium text-foreground/80 group-hover:text-foreground transition-colors">Must
                          Be Unique</span>
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
            <div class="flex items-center justify-between">
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
                  <button v-for="field in formState.fields" :key="field.name" type="button"
                    @click="toggleColumnInIndex(field.name)" class="px-3 py-1 text-sm rounded-full border"
                    :class="newIndex.columns.includes(field.name) ? 'bg-primary text-primary-foreground' : 'bg-background'">
                    {{ field.name }}
                  </button>
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
            <div class="space-y-4">
              <div class="grid gap-2">
                <Label>List Rule</Label>
                <textarea v-model="formState.api_rules.list"
                  class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"
                  placeholder="e.g. status = 'published'" @input="clearValidationError('api_rules.list')"></textarea>
                <p class="text-xs text-muted-foreground">Use `field op value` expressions. Example: `status =
                  "published" && views > 10`.</p>
                <p v-if="firstErrorFor('api_rules.list')" class="text-xs text-destructive">{{
                  firstErrorFor('api_rules.list') }}</p>
              </div>

              <div class="grid gap-2">
                <Label>View Rule</Label>
                <textarea v-model="formState.api_rules.view"
                  class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"
                  placeholder="e.g. auth()" @input="clearValidationError('api_rules.view')"></textarea>
                <p class="text-xs text-muted-foreground">Rule evaluated when viewing a single record.</p>
                <p v-if="firstErrorFor('api_rules.view')" class="text-xs text-destructive">{{
                  firstErrorFor('api_rules.view') }}</p>
              </div>

              <div class="grid gap-2">
                <Label>Create Rule</Label>
                <textarea v-model="formState.api_rules.create"
                  class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"
                  placeholder="e.g. auth()" @input="clearValidationError('api_rules.create')"></textarea>
                <p class="text-xs text-muted-foreground">Rule evaluated when creating records. Return true to allow.</p>
                <p v-if="firstErrorFor('api_rules.create')" class="text-xs text-destructive">{{
                  firstErrorFor('api_rules.create') }}</p>
              </div>

              <div class="grid gap-2">
                <Label>Update Rule</Label>
                <textarea v-model="formState.api_rules.update"
                  class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"
                  placeholder="e.g. auth()" @input="clearValidationError('api_rules.update')"></textarea>
                <p class="text-xs text-muted-foreground">Rule evaluated when updating records.</p>
                <p v-if="firstErrorFor('api_rules.update')" class="text-xs text-destructive">{{
                  firstErrorFor('api_rules.update') }}</p>
              </div>

              <div class="grid gap-2">
                <Label>Delete Rule</Label>
                <textarea v-model="formState.api_rules.delete"
                  class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"
                  placeholder="e.g. auth()" @input="clearValidationError('api_rules.delete')"></textarea>
                <p class="text-xs text-muted-foreground">Rule evaluated when deleting records.</p>
                <p v-if="firstErrorFor('api_rules.delete')" class="text-xs text-destructive">{{
                  firstErrorFor('api_rules.delete') }}</p>
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </div>

      <SheetFooter class="mt-6 border-t bg-background p-6">
        <div class="flex flex-col gap-3 w-full">
          <div class="flex gap-2 w-full">
            <Button variant="outline" class="flex-1" @click="handleClose">Cancel</Button>
            <Button class="flex-1" :disabled="submitting" @click="handleSave">
              {{ submitting ? 'Saving...' : (isCreating ? 'Create Collection' : 'Save Changes') }}
            </Button>
          </div>

          <div v-if="!isCreating && !formState.is_system" class="flex gap-2">
            <Button variant="outline" class="flex-1" @click="handleCopy">
              <Copy class="h-4 w-4 mr-1" />
              Copy
            </Button>
            <Button variant="destructive" class="flex-1" @click="handleTruncate" :disabled="submitting">
              Truncate
            </Button>
            <Button variant="destructive" class="flex-1" @click="handleDelete" :disabled="submitting">
              Delete
            </Button>
          </div>
        </div>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
