<script setup lang="ts">
// Redireciona para o dashboard NDD
definePageMeta({
  middleware: () => {
    return navigateTo('/ndd-dashboard', { replace: true })
  },
})
</script>

<template>
  <div />
</template>
