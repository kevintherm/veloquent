<script setup>
import DashboardLayout from "@/layouts/DashboardLayout.vue";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui";
import RealtimeTest from "@/pages/Settings/RealtimeTest.vue";
import ApiTest from "@/pages/Settings/ApiTest.vue";
import SchemaMaintenance from "@/pages/Settings/SchemaMaintenance.vue";
import { Database, FlaskConical } from "lucide-vue-next";
</script>

<template>
  <DashboardLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-3xl font-bold tracking-tight">Settings</h2>
        <p class="text-muted-foreground">Manage your account settings and preferences.</p>
      </div>

      <Tabs default-value="tests" class="space-y-4">
        <TabsList>
          <TabsTrigger value="tests" class="gap-2">
            <FlaskConical class="h-4 w-4" />
            Tests
          </TabsTrigger>
          <TabsTrigger value="maintenance" class="gap-2">
            <Database class="h-4 w-4" />
            Schema Maintenance
          </TabsTrigger>
        </TabsList>

        <TabsContent value="tests" class="space-y-4">
          <ApiTest />
          <RealtimeTest />
        </TabsContent>

        <TabsContent value="maintenance" class="space-y-4">
          <SchemaMaintenance />
        </TabsContent>
      </Tabs>
    </div>
  </DashboardLayout>
</template>
