<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const { toast, hideToast } = useToast()

const getIcon = (color: string) => {
  switch (color) {
    case 'success': return 'tabler-check'
    case 'error': return 'tabler-alert-circle'
    case 'warning': return 'tabler-alert-triangle'
    case 'info': return 'tabler-info-circle'
    default: return 'tabler-info-circle'
  }
}
</script>

<template>
  <VSnackbar
    v-model="toast.show"
    :color="toast.color"
    :timeout="toast.timeout"
    location="top end"
    class="global-toast"
    @update:model-value="hideToast"
  >
    <div class="d-flex align-center gap-2">
      <VIcon :icon="getIcon(toast.color)" size="20" />
      <span>{{ toast.message }}</span>
    </div>

    <template #actions>
      <VBtn
        icon
        size="x-small"
        variant="text"
        @click="hideToast"
      >
        <VIcon icon="tabler-x" size="18" />
      </VBtn>
    </template>
  </VSnackbar>
</template>

<style lang="scss">
.global-toast {
  .v-snackbar__wrapper {
    min-width: 300px;
    max-width: 500px;
  }
}
</style>
