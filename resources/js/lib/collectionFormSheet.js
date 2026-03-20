import { ref } from "vue";

const sheetStack = ref([]);

const createSheetId = () => {
    return `collection-form-sheet-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
};

const isPlainObject = (value) => {
    return value !== null && typeof value === "object" && !Array.isArray(value);
};

const normalizeCollectionSheetPayload = (collectionOrPayload = null) => {
    if (isPlainObject(collectionOrPayload) && (
        Object.prototype.hasOwnProperty.call(collectionOrPayload, "collection") ||
        Object.prototype.hasOwnProperty.call(collectionOrPayload, "create") ||
        Object.prototype.hasOwnProperty.call(collectionOrPayload, "origin") ||
        Object.prototype.hasOwnProperty.call(collectionOrPayload, "onSave") ||
        Object.prototype.hasOwnProperty.call(collectionOrPayload, "onDelete")
    )) {
        return {
            collection: collectionOrPayload.collection ?? null,
            onSave: typeof collectionOrPayload.onSave === "function" ? collectionOrPayload.onSave : null,
            onDelete: typeof collectionOrPayload.onDelete === "function" ? collectionOrPayload.onDelete : null,
            origin: collectionOrPayload.origin ?? null,
        };
    }

    return {
        collection: collectionOrPayload ?? null,
        onSave: null,
        onDelete: null,
        origin: null,
    };
};

export const openCollectionSheet = (collectionOrPayload = null) => {
    const payload = normalizeCollectionSheetPayload(collectionOrPayload);

    const sheetId = createSheetId();

    sheetStack.value.push({
        id: sheetId,
        collection: payload.collection,
        onSave: payload.onSave,
        onDelete: payload.onDelete,
        origin: payload.origin,
    });

    return sheetId;
};

export const closeCollectionSheet = (sheetId) => {
    sheetStack.value = sheetStack.value.filter((sheet) => sheet.id !== sheetId);
};

export const closeTopCollectionSheet = () => {
    if (!sheetStack.value.length) {
        return;
    }

    sheetStack.value = sheetStack.value.slice(0, -1);
};

export const useCollectionFormSheet = () => {
    return {
        sheetStack,
        openCollectionSheet,
        closeCollectionSheet,
        closeTopCollectionSheet,
    };
};
