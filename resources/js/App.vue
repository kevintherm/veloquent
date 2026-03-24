<script setup>
import "vue-sonner/style.css";
import { Toaster } from "@/components/ui/sonner";
import CollectionFormSheetHost from "@/components/CollectionFormSheetHost.vue";
</script>

<template>
  <router-view />
  <Toaster :close-button="true" close-button-position="top-right" rich-colors position="bottom-center" />
</template>
