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
import { Database, FlaskConical, FileJson, Settings as SettingsIcon, Info } from "lucide-vue-next";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui";
</script>

<template>
  <DashboardLayout>
    <div class="space-y-6">
      <div>
        <h2 class="text-3xl font-bold tracking-tight">Settings</h2>
        <p class="text-muted-foreground">Manage your account settings and preferences.</p>
      </div>

      <Tabs default-value="tests" class="space-y-4">
        <TabsList class="flex-wrap">
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
          <TabsTrigger value="system" class="gap-2">
            <SettingsIcon class="h-4 w-4" />
            System
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
          <Card>
            <CardHeader>
              <CardTitle>System Management</CardTitle>
              <CardDescription>System configuration.</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="flex flex-col items-center justify-center p-8 text-center space-y-4 rounded-lg border-2 border-dashed border-muted">
                <Info class="h-10 w-10 text-muted-foreground animate-pulse" />
                <div class="space-y-2">
                  <h3 class="text-lg font-semibold tracking-tight">System Section Coming Soon</h3>
                  <p class="text-sm text-muted-foreground max-w-[250px]">
                    Available in future updates.
                  </p>
                </div>
                <div class="flex items-center gap-2 p-3 text-xs font-medium text-muted-foreground bg-muted/30 rounded-md border border-border">
                  <Info class="h-3.5 w-3.5" />
                  <span>For now, please use Laravel's config for mail, cache, and storage configuration.</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="schema-transfer" class="space-y-4">
          <SchemaTransfer />
        </TabsContent>
      </Tabs>
    </div>
  </DashboardLayout>
</template>
