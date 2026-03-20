<script setup>
import "vue-sonner/style.css";
import { Toaster } from "@/components/ui/sonner";
import CollectionFormSheetHost from "@/components/CollectionFormSheetHost.vue";
</script>

<template>
  <router-view />
  <Toaster rich-colors position="bottom-center" />
  <CollectionFormSheetHost />
</template>
