<script setup>
import { computed, onMounted, ref, watch } from "vue";
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
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui";
import { Copy, Plus, Search, Trash2, X, MoreVertical, Key } from "lucide-vue-next";
import { resolveCollectionFieldTypeIcon } from "@/lib/collectionFieldTypeIcons";
import { openRecordForm } from "@/lib/recordFormSheet";
import { useDashboardState } from "@/lib/dashboardState";
import TiptapEditor from "./TiptapEditor.vue";

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
const fileSelections = ref({});
const fileAppends = ref({});
const fileDeletions = ref({});
const relationDialogState = ref({
  open: false,
  fieldName: null,
  search: "",
  selected: [],
  refreshCallback: null,
});
const recordActionsMenuOpen = ref(false);
const showDeleteRecordDialog = ref(false);
const availableCollections = ref([]);
const isRecordModified = ref(false);
const showCloseConfirmationDialog = ref(false);
const { requestRecordsReload } = useDashboardState();

const localRecordId = ref(props.record?.id);

const isUpdating = computed(() => {
  return Boolean(localRecordId.value);
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

const formatJsonValue = (value) => {
  if (value === null || value === undefined) {
    return "";
  }

  if (typeof value === "object") {
    return JSON.stringify(value, null, 2);
  }

  if (typeof value === "string") {
    try {
      const parsed = JSON.parse(value);
      return JSON.stringify(parsed, null, 2);
    } catch {
      return value;
    }
  }

  return "";
};

const defaultFieldValue = (field) => {
  if (props.record && props.record[field.name] !== undefined) {
    const existingValue = props.record[field.name];

    if (field.type === "json") {
      return formatJsonValue(existingValue);
    }

    if (field.type === "timestamp") {
      return formatDatetimeLocal(existingValue);
    }

    if (field.type === "boolean") {
      return Boolean(existingValue);
    }

    if (field.type === "relation") {
      return existingValue;
    }

    return existingValue;
  }

  if (field.default !== undefined && field.default !== null) {
    if (field.type === "file") {
      return null;
    }

    if (field.type === "timestamp") {
      return formatDatetimeLocal(field.default);
    }

    if (field.type === "boolean") {
      return Boolean(field.default);
    }

    if (field.type === "json") {
      return formatJsonValue(field.default);
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
  fileSelections.value = {};
  fileAppends.value = {};
  fileDeletions.value = {};
};

const normalizeRelationSelection = (value) => {
  if (Array.isArray(value)) {
    return value
      .filter((item) => item !== null && item !== undefined && item !== "")
      .map((item) => String(item));
  }

  if (value === null || value === undefined || value === "") {
    return [];
  }

  return [String(value)];
};

const relationSelectionLabels = (field) => {
  const fieldName = field?.name;

  if (!fieldName) {
    return [];
  }

  const selectedValues = normalizeRelationSelection(formState.value[fieldName]);
  const options = relationOptions.value[fieldName] ?? [];

  return selectedValues.map((value) => {
    const option = options.find((item) => String(item.value) === value);
    return option?.label ?? value;
  });
};

const relationSelectionSummary = (field) => {
  const labels = relationSelectionLabels(field);

  if (!labels.length) {
    return "No related records selected.";
  }

  if (labels.length === 1) {
    return labels[0];
  }

  return `${labels.length} related records selected`;
};

const fetchAvailableCollections = async () => {
  try {
    const response = await axios.get("/api/collections");
    availableCollections.value = Array.isArray(response?.data?.data) ? response.data.data : [];
  } catch {
    availableCollections.value = [];
  }
};

const resolveRelationTargetIdentifier = (field) => {
  if (field?.target_collection_id) {
    return field.target_collection_id;
  }

  return field?.collection ?? null;
};

const resolveRelationTargetName = (field) => {
  const targetCollectionId = resolveRelationTargetIdentifier(field);

  if (!targetCollectionId) {
    return null;
  }

  const targetCollection = availableCollections.value.find((collection) => {
    return collection?.id === targetCollectionId || collection?.name === targetCollectionId;
  });

  return targetCollection?.name ?? targetCollectionId;
};

const loadRelationOptions = async (field) => {
  const targetCollection = resolveRelationTargetName(field);

  if (field.type !== "relation" || !targetCollection) {
    return;
  }

  relationLoading.value[field.name] = true;
  relationErrors.value[field.name] = null;

  try {
    const response = await axios.get(
      `/api/collections/${encodeURIComponent(targetCollection)}/records`,
      {
        params: {
          per_page: 250,
        },
      }
    );

    const rows = normalizeRecordsPayload(response?.data);

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
    return field?.type === "relation" && resolveRelationTargetIdentifier(field);
  });

  await Promise.all(relationFields.map((field) => loadRelationOptions(field)));
};

const currentRelationDialogField = computed(() => {
  if (!relationDialogState.value.fieldName) {
    return null;
  }

  return orderedFields.value.find((field) => field?.name === relationDialogState.value.fieldName) ?? null;
});

const filteredRelationDialogOptions = computed(() => {
  const fieldName = relationDialogState.value.fieldName;

  if (!fieldName) {
    return [];
  }

  const options = relationOptions.value[fieldName] ?? [];
  const keyword = relationDialogState.value.search.trim().toLowerCase();

  if (!keyword) {
    return options;
  }

  return options.filter((option) => {
    return String(option?.label ?? "").toLowerCase().includes(keyword)
      || String(option?.value ?? "").toLowerCase().includes(keyword);
  });
});

const openRelationDialog = async (field) => {
  if (!field?.name) {
    return;
  }

  if (!Array.isArray(relationOptions.value[field.name]) || relationOptions.value[field.name].length === 0) {
    await loadRelationOptions(field);
  }

  relationDialogState.value = {
    open: true,
    fieldName: field.name,
    search: "",
    selected: normalizeRelationSelection(formState.value[field.name]),
    refreshCallback: () => loadRelationOptions(field),
  };
};

const closeRelationDialog = () => {
  relationDialogState.value = {
    open: false,
    fieldName: null,
    search: "",
    selected: [],
    refreshCallback: null,
  };
};

const isRelationDialogValueSelected = (value) => {
  return relationDialogState.value.selected.includes(String(value));
};

const toggleRelationDialogValue = (field, value) => {
  const normalizedValue = String(value);
  relationDialogState.value.selected = [normalizedValue];
};

const clearRelationDialogSelection = () => {
  relationDialogState.value.selected = [];
};

const applyRelationDialogSelection = () => {
  const field = currentRelationDialogField.value;

  if (!field?.name) {
    closeRelationDialog();
    return;
  }

  const nextSelection = [...relationDialogState.value.selected];

  formState.value[field.name] = nextSelection[0] ?? "";

  closeRelationDialog();
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
    await fetchAvailableCollections();
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

const modifyRecord = () => {
  isRecordModified.value = true;
}

const adjustTextareaHeight = (element) => {
  if (!element) return;
  element.style.height = "auto";
  element.style.height = `${element.scrollHeight}px`;
};

const adjustAllJsonTextareasHeight = () => {
  const textareas = document.querySelectorAll(`textarea[id^="field-${props.sheetId}-"]`);
  textareas.forEach((textarea) => adjustTextareaHeight(textarea));
};

const requestClose = () => {
  if (isRecordModified.value == true) {
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
  setTimeout(() => {
    emit("close");
  }, 300);
};

const displayFieldName = (fieldName) => {
  if (!fieldName) {
    return "Field";
  }

  return String(fieldName);
};

const isRequiredField = (field) => {
  return !["id", "created_at", "updated_at"].includes(field?.name) && field?.nullable === false;
};

const isDisabledField = (field) => {
  if (["created_at", "updated_at"].includes(field?.name)) {
    return true;
  }
  return field?.name === "id" && isUpdating.value;
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

const normalizeFileMetadataList = (value) => {
  if (value === null || value === undefined || value === "") {
    return [];
  }

  if (Array.isArray(value)) {
    return value.filter((item) => item && typeof item === "object");
  }

  if (typeof value === "object") {
    return [value];
  }

  return [];
};

const selectedFileNames = (field) => {
  return (fileSelections.value[field?.name] ?? []).map((file) => file?.name).filter(Boolean);
};

const existingFileNames = (field) => {
  const currentValue = formState.value[field?.name];

  return normalizeFileMetadataList(currentValue)
    .map((item) => item?.name ?? item?.path)
    .filter(Boolean);
};

const fileSelectionSummary = (field) => {
  const selected = selectedFileNames(field);

  if (selected.length > 0) {
    return `Will replace with: ${selected.join(", ")}`;
  }

  const appended = (fileAppends.value[field?.name] ?? []).map((f) => f.name);
  if (appended.length > 0) {
    return `Will append: ${appended.join(", ")}`;
  }

  const existing = existingFileNames(field);

  if (existing.length > 0) {
    return `Current: ${existing.join(", ")}`;
  }

  return field?.multiple ? "No files selected." : "No file selected.";
};

const handleFileInputChange = (field, event) => {
  const files = Array.from(event?.target?.files ?? []);

  fileSelections.value[field.name] = files;
  fileAppends.value[field.name] = [];
  fileDeletions.value[field.name] = [];
  modifyRecord();
};

const handleFileAppendChange = (field, event) => {
  const files = Array.from(event?.target?.files ?? []);

  if (!fileAppends.value[field.name]) {
    fileAppends.value[field.name] = [];
  }

  fileAppends.value[field.name].push(...files);
  modifyRecord();
};

const removeAppendedFile = (field, index) => {
  if (fileAppends.value[field.name]) {
    fileAppends.value[field.name].splice(index, 1);
    modifyRecord();
  }
};

const toggleFileDeletion = (field, file) => {
  const fieldName = field.name;
  if (!fileDeletions.value[fieldName]) {
    fileDeletions.value[fieldName] = [];
  }

  const path = file.path;
  const index = fileDeletions.value[fieldName].indexOf(path);

  if (index > -1) {
    fileDeletions.value[fieldName].splice(index, 1);
  } else {
    fileDeletions.value[fieldName].push(path);
  }
  modifyRecord();
};

const isFileMarkedForDeletion = (field, file) => {
  return (fileDeletions.value[field.name] ?? []).includes(file.path);
};

const hasFilePayload = (payload) => {
  return Object.values(payload).some((value) => {
    if (value instanceof File) {
      return true;
    }

    if (Array.isArray(value)) {
      return value.some((item) => item instanceof File);
    }

    return false;
  });
};

const appendFormDataEntry = (formData, key, value) => {
  if (value === undefined) {
    return;
  }

  if (value === null) {
    formData.append(key, "");
    return;
  }

  if (value instanceof File) {
    formData.append(key, value);
    return;
  }

  if (Array.isArray(value)) {
    if (value.every((item) => item instanceof File)) {
      for (const file of value) {
        formData.append(`${key}[]`, file);
      }
      return;
    }

    formData.append(key, JSON.stringify(value));
    return;
  }

  if (typeof value === "object") {
    formData.append(key, JSON.stringify(value));
    return;
  }

  if (typeof value === "boolean") {
    formData.append(key, value ? "1" : "0");
    return;
  }

  formData.append(key, String(value));
};

const buildFormDataPayload = (payload, isUpdateRequest) => {
  const formData = new FormData();

  if (isUpdateRequest) {
    formData.append("_method", "PATCH");
  }

  for (const [key, value] of Object.entries(payload)) {
    appendFormDataEntry(formData, key, value);
  }

  return formData;
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

  if (field.type === "relation") {
    if (Array.isArray(rawValue)) {
      return rawValue[0] ?? null;
    }

    return rawValue;
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

    if (field.type === "file") {
      const selectedFiles = fileSelections.value[field.name] ?? [];
      const appendedFiles = fileAppends.value[field.name] ?? [];
      const deletedFiles = fileDeletions.value[field.name] ?? [];

      if (selectedFiles.length > 0) {
        payload[field.name] = field.multiple ? selectedFiles : selectedFiles[0];
      } else {
        if (appendedFiles.length > 0) {
          payload[`${field.name}+`] = appendedFiles;
        }

        if (deletedFiles.length > 0) {
          payload[`${field.name}-`] = deletedFiles;
        }

        if (!isUpdating.value) {
          payload[field.name] = null;
        }
      }

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
    let targetField = field;
    if (field.endsWith("+") || field.endsWith("-")) {
      targetField = field.slice(0, -1);
    }

    if (Array.isArray(messages) && messages.length) {
      nextErrors[targetField] = messages[0];
      continue;
    }

    if (typeof messages === "string" && messages.length) {
      nextErrors[targetField] = messages;
    }
  }

  fieldErrors.value = {
    ...fieldErrors.value,
    ...nextErrors,
  };
};

const handleOpenRelatedCollectionForm = (field, callback = null) => {
  const targetCollection = resolveRelationTargetName(field);

  if (!targetCollection) {
    return;
  }

  openRecordForm({
    collection: targetCollection,
    origin: props.sheetId,
    onSave: () => {
      // Call the refresh callback if provided
      if (callback) {
        callback();
      }
    },
  });
};

const handleCreateNewRelatedRecord = () => {
  const field = currentRelationDialogField.value;

  if (!field) {
    return;
  }

  handleOpenRelatedCollectionForm(field, relationDialogState.value.refreshCallback);
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
    const containsFiles = hasFilePayload(payload);
    let response;

    if (containsFiles) {
      const formData = buildFormDataPayload(payload, isUpdating.value);

      response = await axios.post(
        `/api/collections/${encodeURIComponent(identifier)}/records${isUpdating.value ? `/${encodeURIComponent(localRecordId.value)}` : ""}`,
        formData,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          },
        }
      );
    } else {
      if (isUpdating.value) {
        response = await axios.put(
          `/api/collections/${encodeURIComponent(identifier)}/records/${encodeURIComponent(localRecordId.value)}`,
          payload
        );
      } else {
        response = await axios.post(
          `/api/collections/${encodeURIComponent(identifier)}/records`,
          payload
        );
      }
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

const toggleRecordActionsMenu = () => {
  recordActionsMenuOpen.value = !recordActionsMenuOpen.value;
};

const closeRecordActionsMenu = () => {
  recordActionsMenuOpen.value = false;
};

const resolveRecordIdentifier = () => {
  return localRecordId.value ?? null;
};

const handleCopyRecord = () => {
  closeRecordActionsMenu();

  localRecordId.value = null;

  const sensitiveFields = ["id", "created_at", "updated_at"];
  for (const field of sensitiveFields) {
    if (formState.value[field] !== undefined) {
      delete formState.value[field];
    }
  }

  toast.success("Record copied into a new form.");
};

const handleCopyRawJson = async () => {
  closeRecordActionsMenu();

  if (!props.record) {
    toast.error("No record available to copy.");
    return;
  }

  try {
    await navigator.clipboard.writeText(JSON.stringify(props.record, null, 2));
    toast.success("Raw JSON copied.");
  } catch {
    toast.error("Failed to copy raw JSON.");
  }
};

const handleGenerateAuthToken = async () => {
  closeRecordActionsMenu();

  const recordId = resolveRecordIdentifier();
  const identifier = fetchedCollection.value?.id ?? collectionIdentifier.value;

  if (!recordId || !identifier) {
    toast.error("Unable to resolve record or collection.");
    return;
  }

  submitting.value = true;
  const loadingToastId = toast.loading("Generating auth token...");

  try {
    const response = await axios.post(
      `/api/collections/${encodeURIComponent(identifier)}/auth/impersonate/${encodeURIComponent(recordId)}`
    );

    const token = response?.data?.data?.token;

    if (!token) {
      throw new Error("No token received.");
    }

    await navigator.clipboard.writeText(token);
    toast.success("Auth token generated and copied to clipboard.");
  } catch (err) {
    if (err?.response?.status === 403) {
      toast.error("You are not authorized to generate tokens.");
    } else {
      toast.error("Failed to generate auth token.");
    }
  } finally {
    toast.dismiss(loadingToastId);
    submitting.value = false;
  }
};

const handleDeleteRecord = async () => {
  closeRecordActionsMenu();

  const recordId = resolveRecordIdentifier();
  const identifier = fetchedCollection.value?.id ?? collectionIdentifier.value;

  if (!recordId || !identifier) {
    toast.error("Unable to resolve record for deletion.");
    return;
  }

  submitting.value = true;
  showDeleteRecordDialog.value = false;

  try {
    await axios.delete(
      `/api/collections/${encodeURIComponent(identifier)}/records/${encodeURIComponent(recordId)}`
    );
    toast.success("Record deleted.");
    requestRecordsReload();
    handleClose();
  } finally {
    submitting.value = false;
  }
};

const requestDeleteRecord = () => {
  closeRecordActionsMenu();

  if (!resolveRecordIdentifier()) {
    toast.error("Unable to resolve record for deletion.");
    return;
  }

  showDeleteRecordDialog.value = true;
};

watch(internalOpen, (isOpen) => {
  if (isOpen) {
    adjustAllJsonTextareasHeight();
  }
}, { flush: 'post' });

onMounted(async () => {
  await fetchCollectionInfo();
});
</script>

<template>
  <Sheet :open="internalOpen" @update:open="(isOpen) => { if (!isOpen) requestClose(); }">
    <SheetContent side="right" class="sm:max-w-xl md:max-w-2xl overflow-hidden">
      <SheetHeader>
        <SheetTitle>{{ isUpdating ? 'Edit' : 'Add' }} {{ fetchedCollection?.name ?? 'Collection' }} Record</SheetTitle>
        <SheetDescription>
          Populate values based on the collection schema fields.
        </SheetDescription>
      </SheetHeader>

      <form class="grid gap-4 py-4 pr-2 overflow-y-auto max-h-[calc(100vh-200px)]">
        <div v-if="loadingCollection" class="px-2 text-sm text-muted-foreground">
          Fetching collection information...
        </div>

        <template v-else>
          <div v-for="field in orderedFields" :key="field.id ?? field.name" class="grid gap-2 px-2">
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
              :class="[isDisabledField(field) ? 'cursor-not-allowed bg-muted' : '', field.type === 'json' ? 'overflow-hidden' : '']" :disabled="isDisabledField(field)"
              :placeholder="`Enter ${displayFieldName(field.name)}...`" @input="(e) => { modifyRecord(); adjustTextareaHeight(e.target); }"></textarea>

            <TiptapEditor v-else-if="field.type === 'richtext'" v-model="formState[field.name]"
              :placeholder="`Write ${displayFieldName(field.name)}...`" @update:model-value="modifyRecord" />

            <div v-else-if="field.type === 'file'" class="space-y-4">
              <div v-if="normalizeFileMetadataList(formState[field.name]).length > 0" class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Current Files</p>
                <div class="grid gap-2">
                  <div v-for="file in normalizeFileMetadataList(formState[field.name])" :key="file.path"
                    class="flex items-center justify-between rounded-md border p-2 text-sm"
                    :class="isFileMarkedForDeletion(field, file) ? 'bg-destructive/10 border-destructive/20 opacity-70' : 'bg-muted/30'">
                    <div class="flex items-center gap-2 min-w-0">
                      <component :is="resolveCollectionFieldTypeIcon(field.type)" class="h-4 w-4 shrink-0 text-muted-foreground" />
                      <span class="truncate font-medium">{{ file.name ?? file.path }}</span>
                      <span v-if="isFileMarkedForDeletion(field, file)" class="text-[10px] font-bold text-destructive uppercase">Deleting</span>
                    </div>
                    <Button variant="ghost" size="icon" class="h-7 w-7" 
                      :class="isFileMarkedForDeletion(field, file) ? 'text-primary' : 'text-destructive'"
                      type="button"
                      @click="toggleFileDeletion(field, file)">
                      <X v-if="isFileMarkedForDeletion(field, file)" class="h-4 w-4" />
                      <Trash2 v-else class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>

              <div v-if="fileAppends[field.name]?.length > 0" class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Pending Uploads</p>
                <div class="grid gap-2">
                  <div v-for="(file, index) in fileAppends[field.name]" :key="index"
                    class="flex items-center justify-between rounded-md border border-dashed bg-primary/5 p-2 text-sm">
                    <div class="flex items-center gap-2 min-w-0">
                      <Plus class="h-4 w-4 shrink-0 text-primary" />
                      <span class="truncate font-medium">{{ file.name }}</span>
                    </div>
                    <Button variant="ghost" size="icon" class="h-7 w-7 text-muted-foreground" type="button" @click="removeAppendedFile(field, index)">
                      <X class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>

              <div class="grid gap-2">
                <div v-if="isUpdating && field.multiple" class="flex items-center gap-2">
                  <Button variant="outline" size="sm" class="w-full relative cursor-pointer" type="button">
                    <Plus class="h-4 w-4 mr-2" />
                    Add Files
                    <input type="file" multiple class="absolute inset-0 opacity-0 cursor-pointer" @change="(event) => handleFileAppendChange(field, event)" />
                  </Button>
                  <Button variant="ghost" size="sm" class="relative cursor-pointer" type="button">
                    Replace
                    <input type="file" :multiple="Boolean(field.multiple)" class="absolute inset-0 opacity-0 cursor-pointer" @change="(event) => handleFileInputChange(field, event)" />
                  </Button>
                </div>
                <Input v-else
                  :id="`field-${sheetId}-${field.name}`"
                  type="file"
                  :multiple="Boolean(field.multiple)"
                  :disabled="isDisabledField(field)"
                  @change="(event) => handleFileInputChange(field, event)"
                />
              </div>

              <p class="text-xs text-muted-foreground">
                {{ fileSelectionSummary(field) }}
              </p>
            </div>

            <Input v-else-if="field.type !== 'boolean' && field.type !== 'relation'"
              :id="`field-${sheetId}-${field.name}`" v-model="formState[field.name]" :type="resolveInputType(field)"
              :disabled="isDisabledField(field)" :placeholder="`Enter ${displayFieldName(field.name)}...`"
              @input="modifyRecord" />

            <div v-else-if="field.type === 'boolean'" class="flex items-center gap-2 pt-1">
              <Switch :model-value="Boolean(formState[field.name])"
                @update:model-value="(value) => { formState[field.name] = value; }" />
            </div>

            <div v-else class="space-y-2">
              <Button variant="outline" type="button" class="w-full justify-start font-normal"
                @click="openRelationDialog(field)">
                {{ relationSelectionSummary(field) }}
              </Button>
              <p v-if="relationLoading[field.name]" class="text-xs text-muted-foreground">Loading related records...</p>
              <p v-else-if="relationErrors[field.name]" class="text-xs text-destructive">{{ relationErrors[field.name]
              }}</p>
              <p v-else-if="relationSelectionLabels(field).length" class="text-xs text-muted-foreground">
                Selected: {{ relationSelectionLabels(field).join(", ") }}
              </p>
            </div>

            <p v-if="fieldErrors[field.name]" class="text-xs text-destructive">
              {{ fieldErrors[field.name] }}
            </p>

          </div>
        </template>

        <p v-if="!loadingCollection && orderedFields.length === 0" class="px-2 text-sm text-muted-foreground">
          This collection does not have schema fields yet.
        </p>
      </form>

      <Transition name="relation-dialog">
        <div v-if="relationDialogState.open"
          class="fixed inset-0 z-60 flex items-center justify-center bg-black/70 p-4">
          <div
            class="relation-dialog-panel flex h-[min(80vh,720px)] w-full max-w-3xl flex-col rounded-lg border bg-background shadow-xl">
            <div class="flex items-center justify-between border-b px-4 py-3">
              <div>
                <h3 class="text-base font-semibold">Select Related Record</h3>
                <p class="text-xs text-muted-foreground">
                  {{ currentRelationDialogField?.name }}
                </p>
              </div>
              <Button variant="ghost" size="icon" type="button" @click="closeRelationDialog">
                <X class="h-4 w-4" />
              </Button>
            </div>

            <div class="border-b px-4 py-3">
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input v-model="relationDialogState.search" class="pl-8" placeholder="Search related records" />
              </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-4">
              <div v-if="currentRelationDialogField && relationLoading[currentRelationDialogField.name]"
                class="text-sm text-muted-foreground">
                Loading related records...
              </div>
              <div v-else-if="currentRelationDialogField && relationErrors[currentRelationDialogField.name]"
                class="text-sm text-destructive">
                {{ relationErrors[currentRelationDialogField.name] }}
              </div>
              <div v-else-if="filteredRelationDialogOptions.length === 0" class="text-sm text-muted-foreground">
                No related records found.
              </div>
              <div v-else class="space-y-2">
                <button v-for="option in filteredRelationDialogOptions"
                  :key="`${relationDialogState.fieldName}-${option.value}`" type="button"
                  class="flex w-full items-center justify-between rounded-md border px-3 py-2 text-left hover:bg-muted"
                  :class="isRelationDialogValueSelected(option.value) ? 'border-primary bg-primary/10' : 'border-input'"
                  @click="toggleRelationDialogValue(currentRelationDialogField, option.value)">
                  <span class="truncate text-sm">{{ option.label }}</span>
                  <span class="font-mono text-xs text-muted-foreground">{{ option.value }}</span>
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between border-t px-4 py-3">
              <div class="flex gap-2">
                <Button variant="ghost" type="button" @click="clearRelationDialogSelection">
                  Clear Selection
                </Button>
                <Button variant="outline" type="button" @click="handleCreateNewRelatedRecord">
                  <Plus class="h-3.5 w-3.5 mr-1" />
                  New Related Record
                </Button>
              </div>
              <div class="flex gap-2">
                <Button variant="outline" type="button" @click="closeRelationDialog">Cancel</Button>
                <Button type="button" @click="applyRelationDialogSelection">Apply</Button>
              </div>
            </div>
          </div>
        </div>
      </Transition>

      <SheetFooter class="absolute bottom-0 left-0 right-0 p-6 bg-background border-t">
        <div class="flex gap-2 w-full">
          <div v-if="isUpdating" class="relative">
            <Button variant="outline" :disabled="submitting || loadingCollection" @click="toggleRecordActionsMenu">
              <MoreVertical class="h-4 w-4" />
              <div class="sr-only">Actions</div>
            </Button>
            <div v-if="recordActionsMenuOpen"
              class="absolute bottom-11 left-0 z-20 min-w-44 rounded-md border bg-background p-1 shadow-lg">
              <button type="button"
                class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-left text-sm text-destructive hover:bg-muted"
                :disabled="submitting" @click="requestDeleteRecord">
                <Trash2 class="h-4 w-4" />
                Delete Record
              </button>
              <button type="button"
                class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-left text-sm hover:bg-muted"
                :disabled="submitting" @click="handleCopyRecord">
                <Copy class="h-4 w-4" />
                Copy Record
              </button>
              <button type="button"
                class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-left text-sm hover:bg-muted"
                :disabled="submitting" @click="handleCopyRawJson">
                <Copy class="h-4 w-4" />
                Copy Raw JSON
              </button>
              <button v-if="isAuthCollection" type="button"
                class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-left text-sm hover:bg-muted"
                :disabled="submitting" @click="handleGenerateAuthToken">
                <Key class="h-4 w-4" />
                Impersonate
              </button>
            </div>
          </div>

          <Button variant="outline" class="flex-1" @click="requestClose">Cancel</Button>
          <Button class="flex-1" :disabled="submitting || loadingCollection" @click="handleSave">
            {{ submitting ? 'Saving...' : 'Save Record' }}
          </Button>
        </div>
      </SheetFooter>

      <AlertDialog :open="showDeleteRecordDialog" @update:open="(value) => { showDeleteRecordDialog = value; }">
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete record?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel :disabled="submitting">Cancel</AlertDialogCancel>
            <AlertDialogAction :disabled="submitting" @click="handleDeleteRecord">Delete</AlertDialogAction>
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

<style scoped>
.relation-dialog-enter-active,
.relation-dialog-leave-active {
  transition: opacity 180ms ease;
}

.relation-dialog-enter-active .relation-dialog-panel,
.relation-dialog-leave-active .relation-dialog-panel {
  transition: opacity 180ms ease, transform 180ms ease;
}

.relation-dialog-enter-from,
.relation-dialog-leave-to {
  opacity: 0;
}

.relation-dialog-enter-from .relation-dialog-panel,
.relation-dialog-leave-to .relation-dialog-panel {
  opacity: 0;
  transform: translateY(12px) scale(0.98);
}
</style>
