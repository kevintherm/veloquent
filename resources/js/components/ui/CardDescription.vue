<script setup>
import { cn } from "@/lib/utils";

defineProps({
  class: { type: null, default: "" },
});
</script>

<template>
  <p :class="cn('text-sm text-muted-foreground', $props.class)">
    <slot />
  </p>
</template>
