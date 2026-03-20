<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useAuth } from "@/lib/auth.js";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import { Button, Sheet, SheetContent, SheetTrigger } from "@/components/ui";
import {
    PanelLeft,
    Table,
    Users,
} from "lucide-vue-next";
import Sidebar from "@/pages/Dashboard/Sidebar.vue";
import DashboardHeader from "@/pages/Dashboard/DashboardHeader.vue";
import RecordFormSheetHost from "@/components/RecordFormSheetHost.vue";
import { useDashboardState } from "@/lib/dashboardState";

const { state, logout } = useAuth();
const router = useRouter();
const route = useRoute();
const { activeCollection } = useDashboardState();

const collections = ref([]);
const isMobileSidebarOpen = ref(false);

const collectionSearchQuery = ref("");

const getRouteCollectionParam = () => {
    return typeof route.params.collection === "string"
        ? decodeURIComponent(route.params.collection).toLowerCase()
        : null;
};

const resolveCollectionFromRoute = () => {
    const routeCollection = getRouteCollectionParam();

    if (!routeCollection) {
        return null;
    }

    return collections.value.find((collection) => {
        const collectionName = String(collection?.name ?? "").toLowerCase();
        const collectionId = String(collection?.id ?? "").toLowerCase();

        return collectionName === routeCollection || collectionId === routeCollection;
    }) ?? null;
};

const ensureCollectionPath = () => {
    const collectionName = activeCollection.value?.name;

    if (!collectionName) {
        return;
    }

    const expectedPath = `/${encodeURIComponent(collectionName)}`;

    if (route.path === expectedPath) {
        return;
    }

    void router.replace({
        path: expectedPath,
        query: route.query,
    });
};

const resolveCollectionIcon = (collection) => {
    return collection?.type === "auth" ? Users : Table;
};

const fetchCollections = async () => {
    try {
        const response = await axios.get("/api/collections");
        const items = Array.isArray(response?.data?.data) ? response.data.data : [];

        collections.value = items.map((collection) => ({
            ...collection,
            icon: resolveCollectionIcon(collection),
        }));
    } catch {
        collections.value = [];
    }

    if (!collections.value.length) {
        activeCollection.value = { id: null, name: "Collections", icon: Table };
        return;
    }

    const routeCollection = resolveCollectionFromRoute();
    const currentId = activeCollection.value?.id;
    const selectedCollection = collections.value.find((collection) => collection.id === currentId);
    const defaultUsersCollection = collections.value.find(
        (collection) => collection.name?.toLowerCase() === "users"
    );

    activeCollection.value = routeCollection ?? selectedCollection ?? defaultUsersCollection ?? collections.value[0];
    ensureCollectionPath();
};

const filteredCollections = computed(() => {
    if (!collectionSearchQuery.value) return collections.value;
    return collections.value.filter((collection) =>
        collection.name.toLowerCase().includes(collectionSearchQuery.value.toLowerCase())
    );
});

const mobileHeaderTitle = computed(() => {
    if (route.path === "/settings") {
        return "Settings";
    }

    return activeCollection.value?.name ?? "Collections";
});

const closeMobileSidebar = () => {
    isMobileSidebarOpen.value = false;
};

onMounted(async () => {
    await fetchCollections();
});

watch(
    () => route.params.collection,
    (value) => {
        if (!collections.value.length || typeof value !== "string") {
            return;
        }

        const nextCollection = resolveCollectionFromRoute();

        if (nextCollection?.id && nextCollection.id !== activeCollection.value?.id) {
            activeCollection.value = nextCollection;
        }
    }
);

watch(
    () => activeCollection.value?.id,
    () => {
        ensureCollectionPath();
    }
);

const handleLogout = async () => {
    await logout();
    router.push("/login");
};
</script>

<template>
    <div class="flex h-screen bg-background overflow-hidden">
        <!-- Desktop Sidebar -->
        <div class="hidden lg:block">
            <Sidebar
                v-model:activeCollection="activeCollection"
                v-model:collectionSearchQuery="collectionSearchQuery"
                :filteredCollections="filteredCollections"
                :handleLogout="handleLogout"
                :state="state"
            />
        </div>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0 bg-muted/20">
            <div class="flex h-14 items-center justify-between border-b bg-card px-4 lg:hidden">
                <Sheet v-model:open="isMobileSidebarOpen">
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Open sidebar">
                            <PanelLeft class="h-5 w-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="p-0">
                        <Sidebar class="h-full w-full border-r-0"
                            v-model:activeCollection="activeCollection"
                            v-model:collectionSearchQuery="collectionSearchQuery"
                            :filteredCollections="filteredCollections"
                            :handleLogout="handleLogout"
                            :state="state"
                            :onNavigate="closeMobileSidebar"
                        />
                    </SheetContent>
                </Sheet>

                <h1 class="text-sm font-semibold truncate">{{ mobileHeaderTitle }}</h1>
                <div class="w-9"></div>
            </div>

            <!-- Top Bar -->
            <div v-if="$route.path !== '/settings'" class="hidden lg:block">
                <DashboardHeader :active-collection="activeCollection" />
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-auto p-8">
                <div class="max-w-7xl mx-auto space-y-6 pb-20">
                    <slot :active-collection="activeCollection"></slot>
                </div>
            </div>
        </main>

        <RecordFormSheetHost />
    </div>
</template>
