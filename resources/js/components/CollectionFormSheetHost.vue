<script setup>
import CollectionFormSheet from "@/components/CollectionFormSheet.vue";
import { closeCollectionSheet, useCollectionFormSheet } from "@/lib/collectionFormSheet";

const { sheetStack } = useCollectionFormSheet();

const handleClose = (sheetId) => {
    closeCollectionSheet(sheetId);
};

const handleSave = (sheetId, payload) => {
    const sheet = sheetStack.value.find((item) => item.id === sheetId);

    if (typeof sheet?.onSave === "function") {
        sheet.onSave(payload);
    }
};

const handleDelete = (sheetId, payload) => {
    const sheet = sheetStack.value.find((item) => item.id === sheetId);

    if (typeof sheet?.onDelete === "function") {
        sheet.onDelete(payload);
    }
    
    closeCollectionSheet(sheetId);
};
</script>

<template>
    <CollectionFormSheet
        v-for="sheet in sheetStack"
        :key="sheet.id"
        :sheet-id="sheet.id"
        :collection="sheet.collection"
        @close="handleClose(sheet.id)"
        @save="handleSave(sheet.id, $event)"
        @delete="handleDelete(sheet.id, $event)"
    />
</template>
