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

export const closeRecordForm = (sheetId) => {
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
