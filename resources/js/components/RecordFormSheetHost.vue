<script setup>
import RecordFormSheet from "@/components/RecordFormSheet.vue";
import { closeRecordForm, useRecordFormSheet } from "@/lib/recordFormSheet";

const { sheetStack } = useRecordFormSheet();

const handleClose = (sheetId) => {
    closeRecordForm(sheetId);
};

const handleSave = (sheetId, payload) => {
    const sheet = sheetStack.value.find((item) => item.id === sheetId);

    if (typeof sheet?.onSave === "function") {
        sheet.onSave(payload);
    }
};
</script>

<template>
    <RecordFormSheet
        v-for="sheet in sheetStack"
        :key="sheet.id"
        :sheet-id="sheet.id"
        :collection="sheet.collection"
        :record="sheet.record"
        @close="handleClose(sheet.id)"
        @save="handleSave(sheet.id, $event)"
    />
</template>
