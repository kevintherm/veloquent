<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import { Button, Card, CardContent, CardHeader, CardTitle } from "@/components/ui";

const route = useRoute();
const missingPath = computed(() => route.fullPath || "/");
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-background px-4 py-8">
    <Card class="w-full max-w-xl border-dashed">
      <CardHeader class="space-y-2 text-center">
        <p class="text-sm font-medium uppercase tracking-widest text-muted-foreground">Error 404</p>
        <CardTitle class="text-3xl">Page not found</CardTitle>
      </CardHeader>
      <CardContent class="space-y-6 text-center">
        <p class="text-sm text-muted-foreground">
          We could not find
          <code class="rounded bg-muted px-2 py-1 text-foreground">{{ missingPath }}</code>
        </p>

        <div class="flex flex-col justify-center gap-3 sm:flex-row">
          <Button as-child>
            <router-link to="/">Go Back</router-link>
          </Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
