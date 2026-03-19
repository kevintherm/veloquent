<script setup>
import DashboardLayout from "@/layouts/DashboardLayout.vue";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui";
import { FlaskConical } from "lucide-vue-next";
import { useAuth } from "@/lib/auth.js";
import RealtimeTest from "@/pages/Settings/RealtimeTest.vue";

const { state } = useAuth();
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
        </TabsList>

        <TabsContent value="tests" class="space-y-4">
          <RealtimeTest />
        </TabsContent>
      </Tabs>
    </div>
  </DashboardLayout>
</template>
