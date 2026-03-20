<script setup>
import "vue-sonner/style.css";
import { Toaster } from "@/components/ui/sonner";
</script>

<template>
  <router-view />
  <Toaster rich-colors position="top-center" />
</template>
