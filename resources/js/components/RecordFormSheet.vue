<script setup>
import { computed, onMounted, ref } from "vue";
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
  Switch,
  Input,
  Label,
} from "@/components/ui";
import { Plus } from "lucide-vue-next";
import { resolveCollectionFieldTypeIcon } from "@/lib/collectionFieldTypeIcons";
import { openRecordForm } from "@/lib/recordFormSheet";
import { useDashboardState } from "@/lib/dashboardState";

const props = defineProps({
  sheetId: {
    type: String,
    required: true,
  },
  collection: {
    type: [Object, String],
    required: true,
  },
  record: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["save", "close"]);

const internalOpen = ref(false);
const loadingCollection = ref(false);
const submitting = ref(false);
const fetchedCollection = ref(null);
const formState = ref({});
const fieldErrors = ref({});
const relationOptions = ref({});
const relationLoading = ref({});
const relationErrors = ref({});
const { requestRecordsReload } = useDashboardState();

const isUpdating = computed(() => {
  return Boolean(props.record?.id);
});

const collectionIdentifier = computed(() => {
  if (typeof props.collection === "string") {
    return props.collection;
  }

  return props.collection?.id ?? props.collection?.name ?? null;
});

const orderedFields = computed(() => {
  const fields = Array.isArray(fetchedCollection.value?.fields)
    ? fetchedCollection.value.fields
    : [];

  return [...fields].sort((left, right) => {
    return Number(left?.order ?? 0) - Number(right?.order ?? 0);
  });
});

const isAuthCollection = computed(() => {
  return fetchedCollection.value?.type === "auth";
});

const normalizeCollectionPayload = (payload) => {
  if (payload?.data && !Array.isArray(payload.data)) {
    return payload.data;
  }

  return payload;
};

const normalizeRecordsPayload = (payload) => {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  return [];
};

const formatDatetimeLocal = (value) => {
  if (!value) {
    return "";
  }

  const parsedDate = value instanceof Date ? value : new Date(value);

  if (Number.isNaN(parsedDate.getTime())) {
    return "";
  }

  const year = parsedDate.getFullYear();
  const month = String(parsedDate.getMonth() + 1).padStart(2, "0");
  const day = String(parsedDate.getDate()).padStart(2, "0");
  const hours = String(parsedDate.getHours()).padStart(2, "0");
  const minutes = String(parsedDate.getMinutes()).padStart(2, "0");

  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const defaultFieldValue = (field) => {
  if (props.record && props.record[field.name] !== undefined) {
    const existingValue = props.record[field.name];

    if (field.type === "json" && existingValue && typeof existingValue === "object") {
      return JSON.stringify(existingValue, null, 2);
    }

    if (field.type === "timestamp") {
      return formatDatetimeLocal(existingValue);
    }

    if (field.type === "boolean") {
      return Boolean(existingValue);
    }

    return existingValue;
  }

  if (field.default !== undefined && field.default !== null) {
    if (field.type === "timestamp") {
      return formatDatetimeLocal(field.default);
    }

    if (field.type === "boolean") {
      return Boolean(field.default);
    }

    if (field.type === "json" && typeof field.default === "object") {
      return JSON.stringify(field.default, null, 2);
    }

    return field.default;
  }

  if (field.type === "boolean") {
    return false;
  }

  return "";
};

const initializeFormState = () => {
  const nextState = {};

  for (const field of orderedFields.value) {
    if (!field?.name) {
      continue;
    }

    nextState[field.name] = defaultFieldValue(field);
  }

  formState.value = nextState;
  fieldErrors.value = {};
};

const relationDisplayValue = (fieldName, value) => {
  const options = relationOptions.value[fieldName] ?? [];
  const option = options.find((item) => item.value === value);

  return option ? option.label : value;
};

const loadRelationOptions = async (field) => {
  if (field.type !== "relation" || !field.collection) {
    return;
  }

  relationLoading.value[field.name] = true;
  relationErrors.value[field.name] = null;

  try {
    const response = await axios.get(
      `/api/collections/${encodeURIComponent(field.collection)}/records`,
      {
        params: {
          per_page: 100,
        },
      }
    );

    const rows = normalizeRecordsPayload(response?.data?.data);

    relationOptions.value[field.name] = rows.map((row) => ({
      value: row?.id,
      label:
        row?.name ??
        row?.title ??
        row?.email ??
        row?.username ??
        row?.id,
    }));
  } catch {
    relationOptions.value[field.name] = [];
    relationErrors.value[field.name] = "Failed to load related records.";
  } finally {
    relationLoading.value[field.name] = false;
  }
};

const loadAllRelationOptions = async () => {
  const relationFields = orderedFields.value.filter((field) => {
    return field?.type === "relation" && field.collection;
  });

  await Promise.all(relationFields.map((field) => loadRelationOptions(field)));
};

const fetchCollectionInfo = async () => {
  const identifier = collectionIdentifier.value;

  if (!identifier) {
    emit("close");
    return;
  }

  loadingCollection.value = true;
  const loadingToastId = toast.loading("Fetching collection fields...");

  try {
    const response = await axios.get(`/api/collections/${encodeURIComponent(identifier)}`);

    fetchedCollection.value = normalizeCollectionPayload(response?.data);
    initializeFormState();
    internalOpen.value = true;
    await loadAllRelationOptions();
  } catch {
    emit("close");
  } finally {
    toast.dismiss(loadingToastId);
    loadingCollection.value = false;
  }
};

const handleClose = () => {
  internalOpen.value = false;
  emit("close");
};

const displayFieldName = (fieldName) => {
  if (!fieldName) {
    return "Field";
  }

  return String(fieldName);
};

const isRequiredField = (field) => {
  return !field?.nullable;
};

const resolveInputType = (field) => {
  if (isAuthCollection.value && field?.name === "password") {
    return "password";
  }

  if (field?.type === "timestamp") {
    return "datetime-local";
  }

  if (field?.type === "number") {
    return "number";
  }

  return field?.type;
};

const coerceFieldValue = (field, rawValue) => {
  if (rawValue === "" || rawValue === undefined) {
    return null;
  }

  if (field.type === "number") {
    const numberValue = Number(rawValue);

    return Number.isFinite(numberValue) ? numberValue : null;
  }

  if (field.type === "boolean") {
    return Boolean(rawValue);
  }

  if (field.type === "timestamp") {
    const dateValue = new Date(rawValue);

    if (Number.isNaN(dateValue.getTime())) {
      return null;
    }

    return dateValue.toISOString();
  }

  if (field.type === "json") {
    if (rawValue === null) {
      return null;
    }

    if (typeof rawValue !== "string") {
      return rawValue;
    }

    return JSON.parse(rawValue);
  }

  return rawValue;
};

const validateJsonFields = () => {
  let isValid = true;

  for (const field of orderedFields.value) {
    if (field.type !== "json") {
      continue;
    }

    const rawValue = formState.value[field.name];

    if (rawValue === "" || rawValue === null || rawValue === undefined) {
      fieldErrors.value[field.name] = null;
      continue;
    }

    try {
      JSON.parse(rawValue);
      fieldErrors.value[field.name] = null;
    } catch {
      fieldErrors.value[field.name] = "Invalid JSON format.";
      isValid = false;
    }
  }

  return isValid;
};

const buildPayload = () => {
  const payload = {};

  for (const field of orderedFields.value) {
    if (!field?.name) {
      continue;
    }

    const rawValue = formState.value[field.name];
    payload[field.name] = coerceFieldValue(field, rawValue);
  }

  return payload;
};

const applyValidationErrors = (errors) => {
  const nextErrors = {};

  for (const [field, messages] of Object.entries(errors ?? {})) {
    if (Array.isArray(messages) && messages.length) {
      nextErrors[field] = messages[0];
      continue;
    }

    if (typeof messages === "string" && messages.length) {
      nextErrors[field] = messages;
    }
  }

  fieldErrors.value = {
    ...fieldErrors.value,
    ...nextErrors,
  };
};

const handleOpenRelatedCollectionForm = (field) => {
  if (!field.collection) {
    return;
  }

  openRecordForm({
    collection: field.collection,
    origin: props.sheetId,
  });
};

const handleSave = async () => {
  if (!validateJsonFields()) {
    return;
  }

  const identifier = fetchedCollection.value?.id ?? collectionIdentifier.value;

  if (!identifier) {
    return;
  }

  submitting.value = true;
  fieldErrors.value = {};

  try {
    const payload = buildPayload();
    let response;

    if (isUpdating.value) {
      response = await axios.put(
        `/api/collections/${encodeURIComponent(identifier)}/records/${encodeURIComponent(props.record.id)}`,
        payload
      );
    } else {
      response = await axios.post(
        `/api/collections/${encodeURIComponent(identifier)}/records`,
        payload
      );
    }

    emit("save", response?.data?.data ?? null);
    requestRecordsReload();
    handleClose();
  } catch (error) {
    if (error?.response?.status === 422) {
      applyValidationErrors(error?.response?.data?.errors);
    }
  } finally {
    submitting.value = false;
  }
};

onMounted(async () => {
  await fetchCollectionInfo();
});
</script>

<template>
  <Sheet :open="internalOpen" @update:open="(isOpen) => { if (!isOpen) handleClose(); }">
    <SheetContent side="right" class="sm:max-w-md">
      <SheetHeader>
        <SheetTitle>{{ isUpdating ? 'Edit' : 'Add' }} {{ fetchedCollection?.name ?? 'Collection' }} Record</SheetTitle>
        <SheetDescription>
          Populate values based on the collection schema fields.
        </SheetDescription>
      </SheetHeader>

      <form class="grid gap-4 py-4 overflow-y-auto max-h-[calc(100vh-200px)]">
        <div v-if="loadingCollection" class="px-1 text-sm text-muted-foreground">
          Fetching collection information...
        </div>

        <template v-else>
          <div v-for="field in orderedFields" :key="field.id ?? field.name" class="grid gap-2 px-1">
            <div class="flex items-center justify-between gap-2">
              <Label :for="`field-${sheetId}-${field.name}`" class="flex items-center gap-2">
                <component :is="resolveCollectionFieldTypeIcon(field.type)" class="h-4 w-4 text-muted-foreground" />
                <span>{{ displayFieldName(field.name) }}</span>
                <span v-if="isRequiredField(field)" class="text-destructive text-xl">*</span>
              </Label>
              <span class="text-[11px] uppercase tracking-wide text-muted-foreground">{{ field.type }}</span>
            </div>

            <textarea v-if="field.type === 'longtext' || field.type === 'json'" :id="`field-${sheetId}-${field.name}`"
              v-model="formState[field.name]"
              class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
              :placeholder="`Enter ${displayFieldName(field.name)}...`"></textarea>

            <Input v-else-if="field.type !== 'boolean' && field.type !== 'relation'"
              :id="`field-${sheetId}-${field.name}`" v-model="formState[field.name]" :type="resolveInputType(field)"
              :placeholder="`Enter ${displayFieldName(field.name)}...`" />

            <div v-else-if="field.type === 'boolean'" class="flex items-center gap-2 pt-1">
              <Switch :model-value="Boolean(formState[field.name])"
                @update:model-value="(value) => { formState[field.name] = value; }" />
            </div>

            <div v-else class="space-y-2">
              <select :id="`field-${sheetId}-${field.name}`" v-model="formState[field.name]"
                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                <option value="">Select related record</option>
                <option v-for="option in relationOptions[field.name] ?? []" :key="`${field.name}-${option.value}`"
                  :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <p v-if="relationLoading[field.name]" class="text-xs text-muted-foreground">Loading related records...</p>
              <p v-else-if="relationErrors[field.name]" class="text-xs text-destructive">{{ relationErrors[field.name]
                }}</p>
              <p v-else-if="formState[field.name]" class="text-xs text-muted-foreground">
                Selected: {{ relationDisplayValue(field.name, formState[field.name]) }}
              </p>
              <Button variant="outline" size="sm" class="gap-1" type="button"
                @click="handleOpenRelatedCollectionForm(field)">
                <Plus class="h-3.5 w-3.5" />
                New Related Record
              </Button>
            </div>

            <p v-if="fieldErrors[field.name]" class="text-xs text-destructive">
              {{ fieldErrors[field.name] }}
            </p>

          </div>
        </template>

        <p v-if="!loadingCollection && orderedFields.length === 0" class="px-1 text-sm text-muted-foreground">
          This collection does not have schema fields yet.
        </p>
      </form>

      <SheetFooter class="absolute bottom-0 left-0 right-0 p-6 bg-background border-t">
        <div class="flex gap-2 w-full">
          <Button variant="outline" class="flex-1" @click="handleClose">Cancel</Button>
          <Button class="flex-1" :disabled="submitting || loadingCollection" @click="handleSave">
            {{ submitting ? 'Saving...' : 'Save Record' }}
          </Button>
        </div>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
