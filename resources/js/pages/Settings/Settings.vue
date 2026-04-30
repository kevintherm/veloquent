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
import SchemaTransfer from "@/pages/Settings/SchemaTransfer.vue";
import SettingsForm from "@/components/SettingsForm.vue";
import { Database, FlaskConical, FileJson, Settings as SettingsIcon } from "lucide-vue-next";
</script>

<template>
  <DashboardLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-3xl font-bold tracking-tight">Settings</h2>
        <p class="text-muted-foreground">Manage your account settings and preferences.</p>
      </div>

      <Tabs default-value="system" class="space-y-4">
        <TabsList class="flex-wrap">
          <TabsTrigger value="system" class="gap-2">
            <SettingsIcon class="h-4 w-4" />
            System
          </TabsTrigger>
          <TabsTrigger value="tests" class="gap-2">
            <FlaskConical class="h-4 w-4" />
            Tests
          </TabsTrigger>
          <TabsTrigger value="maintenance" class="gap-2">
            <Database class="h-4 w-4" />
            Schema Maintenance
          </TabsTrigger>
          <TabsTrigger value="schema-transfer" class="gap-2">
            <FileJson class="h-4 w-4" />
            Schema Transfer
          </TabsTrigger>
        </TabsList>

        <TabsContent value="tests" class="space-y-4">
          <ApiTest />
          <RealtimeTest />
        </TabsContent>

        <TabsContent value="maintenance" class="space-y-4">
          <SchemaMaintenance />
        </TabsContent>

        <TabsContent value="system" class="space-y-4">
          <SettingsForm />
        </TabsContent>

        <TabsContent value="schema-transfer" class="space-y-4">
          <SchemaTransfer />
        </TabsContent>
      </Tabs>
    </div>
  </DashboardLayout>
</template>
