<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { toast } from "vue-sonner";
import {
  Button,
  Separator,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui";
import { Database, Trash2, RefreshCw, AlertTriangle, CheckCircle2, Wrench, ShieldAlert } from "lucide-vue-next";

const loadingOrphans = ref(false);
const loadingCorrupt = ref(false);
const orphans = ref([]);
const corruptCollections = ref([]);
const cleaning = ref(false);
const recoveringIds = ref(new Set());

const fetchOrphans = async () => {
  loadingOrphans.value = true;
  try {
    const response = await axios.get("/api/schema/orphans");
    orphans.value = response.data.data || [];
  } catch (error) {
    console.error(error);
    toast.error("Failed to fetch orphan tables");
  } finally {
    loadingOrphans.value = false;
  }
};

const fetchCorrupt = async () => {
  loadingCorrupt.value = true;
  try {
    const response = await axios.get("/api/schema/corrupt");
    corruptCollections.value = response.data.data || [];
  } catch (error) {
    console.error(error);
    toast.error("Failed to fetch corrupt collections");
  } finally {
    loadingCorrupt.value = false;
  }
};

const handleCleanOrphan = async (tableName) => {
  if (!confirm(`Are you sure you want to drop the table "${tableName}"? This action is irreversible.`)) {
    return;
  }

  cleaning.value = true;
  try {
    await axios.delete(`/api/schema/orphans/${encodeURIComponent(tableName)}`);
    toast.success(`Table ${tableName} dropped successfully`);
    await fetchOrphans();
  } catch (error) {
    console.error(error);
    toast.error(error?.response?.data?.message || "Failed to drop table");
  } finally {
    cleaning.value = false;
  }
};

const handleCleanAll = async () => {
  if (!confirm("Are you sure you want to drop ALL orphan tables? This action is irreversible.")) {
    return;
  }

  cleaning.value = true;
  try {
    await axios.delete("/api/schema/orphans");
    toast.success("All orphan tables dropped successfully");
    await fetchOrphans();
  } catch (error) {
    console.error(error);
    toast.error(error?.response?.data?.message || "Failed to drop tables");
  } finally {
    cleaning.value = false;
  }
};

const handleRecover = async (job) => {
  const collectionId = job.collection_id;
  const activity = job.operation;
  
  const message = activity === 'create' 
    ? "This will DROP the physical table and DELETE the collection metadata. Are you sure?"
    : "This will REBUILD the physical table based on metadata. ANY EXISTING DATA in this table will be lost. Are you sure?";

  if (!confirm(message)) return;

  recoveringIds.value.add(collectionId);
  try {
    await axios.post(`/api/collections/${encodeURIComponent(collectionId)}/recover`);
    toast.success("Collection schema recovered successfully");
    await fetchCorrupt();
  } catch (error) {
    console.error(error);
    toast.error(error?.response?.data?.message || "Recovery failed");
  } finally {
    recoveringIds.value.delete(collectionId);
  }
};

const refreshAll = () => {
  fetchOrphans();
  fetchCorrupt();
};

onMounted(() => {
  refreshAll();
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-medium flex items-center gap-2">
          <Database class="w-5 h-5 text-primary" />
          Schema Maintenance
        </h3>
        <p class="text-sm text-muted-foreground">
          Detect and resolve database inconsistencies, stuck schema operations, and orphan tables.
        </p>
      </div>
      <Button variant="outline" size="sm" @click="refreshAll" :disabled="loadingOrphans || loadingCorrupt">
        <RefreshCw class="w-4 h-4 mr-2" :class="{ 'animate-spin': loadingOrphans || loadingCorrupt }" />
        Refresh All
      </Button>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <!-- Corrupt Collections Section -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base flex items-center gap-2">
            <ShieldAlert class="w-4 h-4 text-destructive" />
            Corrupt Collections
          </CardTitle>
          <CardDescription>
            Collections with a stuck schema operation that are currently blocking updates.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="loadingCorrupt && corruptCollections.length === 0" class="py-8 text-center text-muted-foreground text-sm">
            Checking for stuck jobs...
          </div>

          <div v-else-if="corruptCollections.length === 0" class="py-8 text-center border rounded-lg border-dashed">
            <CheckCircle2 class="w-8 h-8 text-green-500 mx-auto mb-2 opacity-50" />
            <p class="text-sm font-medium">No corrupt collections</p>
          </div>

          <div v-else class="space-y-4">
            <div class="bg-destructive/5 border border-destructive/20 p-3 rounded-md flex gap-3">
              <AlertTriangle class="w-5 h-5 text-destructive shrink-0 mt-0.5" />
              <p class="text-xs text-destructive">
                These collections have a schema operation that failed or is stuck. 
                They are locked and cannot be updated until recovered.
              </p>
            </div>

            <div class="space-y-2">
              <div v-for="job in corruptCollections" :key="job.id" 
                class="flex items-center justify-between p-3 border rounded-md hover:bg-muted/30 transition-colors">
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold">{{ job.collection?.name || 'Unknown Collection' }}</span>
                    <span class="text-[10px] uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 font-bold border border-amber-200">
                      {{ job.operation }}
                    </span>
                  </div>
                  <div class="flex flex-col gap-0.5">
                    <code class="text-[10px] font-mono opacity-60">{{ job.table_name }}</code>
                    <span class="text-[10px] text-muted-foreground italic">Started {{ job.started_at ? new Date(job.started_at).toLocaleString() : 'recently' }}</span>
                  </div>
                </div>
                <Button 
                  variant="outline" 
                  size="sm" 
                  class="h-8 gap-2 hover:bg-destructive hover:text-destructive-foreground"
                  @click="handleRecover(job)"
                  :disabled="recoveringIds.has(job.collection_id)"
                >
                  <Wrench v-if="!recoveringIds.has(job.collection_id)" class="w-3.5 h-3.5" />
                  <span v-else class="w-3.5 h-3.5 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                  Recover
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Orphan Tables Section -->
      <Card>
        <CardHeader>
          <CardTitle class="text-base flex items-center gap-2">
            <Trash2 class="w-4 h-4 text-amber-500" />
            Orphan Tables
          </CardTitle>
          <CardDescription>
            Tables with the system prefix that are not linked to any metadata.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="loadingOrphans && orphans.length === 0" class="py-8 text-center text-muted-foreground text-sm">
            Scanning database...
          </div>

          <div v-else-if="orphans.length === 0" class="py-8 text-center border rounded-lg border-dashed">
            <CheckCircle2 class="w-8 h-8 text-green-500 mx-auto mb-2 opacity-50" />
            <p class="text-sm font-medium">No orphan tables found</p>
          </div>

          <div v-else class="space-y-4">
            <div class="bg-amber-50 border border-amber-200 p-3 rounded-md flex gap-3">
              <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
              <p class="text-xs text-amber-800">
                These tables likely result from failed creations. 
                They consume storage and should be cleaned up.
              </p>
            </div>

            <div class="space-y-2">
              <div v-for="table in orphans" :key="table"
                class="flex items-center justify-between p-3 border rounded-md hover:bg-muted/30 transition-colors">
                <code class="text-xs font-mono bg-muted px-1.5 py-0.5 rounded">{{ table }}</code>
                <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-destructive"
                  @click="handleCleanOrphan(table)" :disabled="cleaning">
                  <Trash2 class="w-4 h-4" />
                </Button>
              </div>
            </div>

            <Separator />
            
            <div class="flex justify-end">
              <Button variant="destructive" size="sm" @click="handleCleanAll" :disabled="cleaning">
                <Trash2 class="w-4 h-4 mr-2" />
                Clean All Orphans
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

