<script setup>
import { computed, ref, watch } from "vue";
import { Search, Plus, Settings, LogOut, ChevronDown, ChevronUp } from "lucide-vue-next";
import { Button, Input } from "@/components/ui"
import Collapsible from "@/components/ui/collapsible/Collapsible.vue";
import CollapsibleTrigger from "@/components/ui/collapsible/CollapsibleTrigger.vue";
import CollapsibleContent from "@/components/ui/collapsible/CollapsibleContent.vue";
import { openCollectionSheet } from "@/lib/collectionFormSheet";

const props = defineProps({
    activeCollection: {
        type: Object,
        required: true
    },
    collectionSearchQuery: {
        type: String,
        required: true
    },
    filteredCollections: {
        type: Array,
        required: true
    },
    handleLogout: {
        type: Function,
        required: true
    },
    state: {
        type: Object,
        required: true
    },
    onNavigate: {
        type: Function,
        default: null,
    },
})

const emit = defineEmits(['update:collectionSearchQuery', 'update:activeCollection'])

const isSystemCollectionsShown = ref(false);

const handleCollectionSelect = (collection) => {
    emit('update:activeCollection', collection);
    props.onNavigate?.();
};

const handleSettingsNavigate = () => {
    props.onNavigate?.();
};

const handleLogoutClick = async () => {
    await props.handleLogout();
    props.onNavigate?.();
};

const regularCollections = computed(() => {
    return props.filteredCollections
        .filter((collection) => !collection.is_system)
        .sort((a, b) => {
            if (a.name === "users") return -1;
            if (b.name === "users") return 1;
            return a.name.localeCompare(b.name);
        });
});

const systemCollections = computed(() => {
    return props.filteredCollections.filter((collection) => collection.is_system);
});

watch(
    () => props.collectionSearchQuery,
    (query) => {
        isSystemCollectionsShown.value = query.trim().length > 0;
    }
);
</script>

<template>
    <aside class="w-64 border-r bg-card flex flex-col h-full">
        <div class="p-6 gap-2">
            <img :src="'/logo.svg'" alt="Velo Logo" class="h-8 w-8" draggable="false" />
        </div>

        <nav class="flex-1 overflow-y-auto px-4 space-y-1">
            <div class="mb-4">
                <p class="px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                    Collections</p>
                <div class="mb-4 px-2">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                        <Input :model-value="collectionSearchQuery"
                            @update:model-value="$emit('update:collectionSearchQuery', $event)"
                            placeholder="Search collections..." class="pl-8 h-8 text-xs bg-muted/50 border-none" />
                    </div>
                </div>

                <router-link v-for="collection in regularCollections" :key="collection.id"
                    :to="`/${encodeURIComponent(collection.name)}`" @click="handleCollectionSelect(collection)"
                    draggable="false" :class="[
                        'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
                        activeCollection.id === collection.id
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                    ]">
                    <component :is="collection.icon" class="h-4 w-4" />
                    {{ collection.name }}
                </router-link>
                <Collapsible v-if="systemCollections.length" v-model:open="isSystemCollectionsShown">
                    <CollapsibleTrigger as-child>
                        <Button variant="ghost" size="sm" class="flex w-full justify-between items-center gap-2">
                            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">System
                            </p>
                            <ChevronUp class="size-4" v-if="isSystemCollectionsShown" />
                            <ChevronDown class="size-4" v-if="!isSystemCollectionsShown" />
                            <span class="sr-only">Toggle</span>
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent class="flex flex-col gap-2">
                        <router-link v-for="collection in systemCollections" :key="collection.id"
                            :to="`/${encodeURIComponent(collection.name)}`" @click="handleCollectionSelect(collection)"
                            draggable="false" :class="[
                                'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
                                activeCollection.id === collection.id
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            ]">
                            <component :is="collection.icon" class="h-4 w-4" />
                            {{ collection.name }}
                        </router-link>
                    </CollapsibleContent>
                </Collapsible>
                <p v-if="!regularCollections.length && !systemCollections.length"
                    class="px-3 py-2 text-xs text-muted-foreground">
                    No collections found.
                </p>
                <button @click="openCollectionSheet(null)"
                    class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md text-primary hover:bg-primary/5 mt-1">
                    <Plus class="h-4 w-4" />
                    New Collection
                </button>
            </div>
        </nav>

        <div class="p-4 border-t mt-auto">
            <router-link to="/settings" @click="handleSettingsNavigate"
                class="mb-4 w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                active-class="bg-accent text-accent-foreground">
                <Settings class="h-4 w-4" />
                Settings
            </router-link>

            <div v-if="state.user" class="flex items-center gap-3 mb-4">
                <div
                    class="h-8 w-8 rounded-full bg-primary flex items-center justify-center text-primary-foreground font-bold">
                    {{ state.user.name.charAt(0) }}
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm font-medium truncate">{{ state.user.name }}</p>
                    <p class="text-xs text-muted-foreground truncate">{{ state.user.email }}</p>
                </div>
            </div>
            <Button @click="handleLogoutClick" variant="ghost"
                class="w-full justify-start gap-2 h-9 text-muted-foreground">
                <LogOut class="h-4 w-4" />
                Logout
            </Button>
        </div>
    </aside>
</template>
