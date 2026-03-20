<script setup>
import { computed } from "vue";
import { Button } from "@/components/ui";
import {
    Plus,
    Sliders,
} from "lucide-vue-next";
import { openRecordForm } from "@/lib/recordFormSheet";
import { openCollectionSheet } from "@/lib/collectionFormSheet";

const props = defineProps({
    activeCollection: {
        type: Object,
        required: true
    }
})

const collectionName = computed(() => {
    return props.activeCollection?.name ?? "";
});

const handleOpenRecordForm = () => {
    openRecordForm({
        collection: props.activeCollection,
        origin: "dashboard-header",
    });
};

const handleOpenCollectionSheet = () => {
    openCollectionSheet(props.activeCollection);
};
</script>

<template>
    <header class="h-16 border-b bg-card flex items-center justify-between px-8 shrink-0">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-semibold">{{ collectionName || "Collection" }}</h1>
            <Button variant="ghost" size="sm" class="text-muted-foreground gap-2" @click="handleOpenCollectionSheet">
                <Sliders class="h-4 w-4"/>
                Manage Collection
            </Button>
        </div>
        <div class="flex items-center gap-3">
            <Button size="sm" class="gap-1" @click="handleOpenRecordForm">
                <Plus class="h-4 w-4"/>
                Add Record
            </Button>
        </div>
    </header>
</template>
