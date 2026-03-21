import { ref } from "vue";

const sheetStack = ref([]);

const createSheetId = () => {
    return `record-form-sheet-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
};

export const openRecordForm = (payload = {}) => {
    const collection = payload.collection ?? null;

    if (!collection) {
        return null;
    }

    const sheetId = createSheetId();

    sheetStack.value.push({
        id: sheetId,
        collection,
        record: payload.record ?? null,
        onSave: typeof payload.onSave === "function" ? payload.onSave : null,
        origin: payload.origin ?? null,
    });

    return sheetId;
};

export const closeRecordForm = (sheetId, payload = null) => {
    const sheetIndex = sheetStack.value.findIndex((sheet) => sheet.id === sheetId);
    
    if (sheetIndex === -1) {
        return;
    }

    const sheet = sheetStack.value[sheetIndex];
    
    // Call onSave callback if provided and payload is not null
    if (payload && typeof sheet?.onSave === "function") {
        sheet.onSave(payload);
    }

    sheetStack.value = sheetStack.value.filter((sheet) => sheet.id !== sheetId);
};

export const closeTopRecordForm = () => {
    if (!sheetStack.value.length) {
        return;
    }

    sheetStack.value = sheetStack.value.slice(0, -1);
};

export const useRecordFormSheet = () => {
    return {
        sheetStack,
        openRecordForm,
        closeRecordForm,
        closeTopRecordForm,
    };
};
