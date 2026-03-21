<script setup>
import {Button} from "@/components/ui";
import {
    Trash2,
} from "lucide-vue-next";

defineProps({
    selectedRecords: {
        type: Array,
        required: true
    },
    processing: {
        type: Boolean,
        default: false,
    }
})

const emit = defineEmits(['clear-selection', 'delete-records'])

const handleCancel = () => {
    emit("clear-selection");
};
</script>

<template>
    <div
        v-if="selectedRecords.length > 0"
        class="fixed bottom-8 left-1/2 -translate-x-1/2 flex items-center gap-6 px-6 py-3 text-foreground shadow-2xl animate-in fade-in slide-in-from-bottom-4 duration-300 z-50"
    >
        <span class="text-sm font-semibold whitespace-nowrap">{{
                selectedRecords.length
            }} selected</span>
        <div class="h-4 w-px bg-primary-foreground/20"></div>
        <div class="flex gap-2">
            <Button variant="destructive" size="sm" class="gap-1 h-8 border-none" :disabled="processing"
                @click="$emit('delete-records')">
                <Trash2 class="h-3.5 w-3.5"/>
                {{ processing ? 'Deleting...' : 'Delete' }}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                :disabled="processing"
                @click="handleCancel"
                class="h-8"
            >
                Cancel
            </Button>
        </div>
    </div>
</template>
