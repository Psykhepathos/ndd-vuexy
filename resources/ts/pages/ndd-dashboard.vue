<script setup lang="ts">
import NDDCardStatistics from '@/views/apps/logistics/NDDCardStatistics.vue'
import NDDVehicleOverview from '@/views/apps/logistics/NDDVehicleOverview.vue'
import NDDShipmentStatistics from '@/views/apps/logistics/NDDShipmentStatistics.vue'
import NDDDeliveryPerformance from '@/views/apps/logistics/NDDDeliveryPerformance.vue'
import NDDDeliveryExceptions from '@/views/apps/logistics/NDDDeliveryExceptions.vue'
import NDDOrdersByRegion from '@/views/apps/logistics/NDDOrdersByRegion.vue'
import NDDOverviewTable from '@/views/apps/logistics/NDDOverviewTable.vue'

definePage({
  meta: {
    title: 'Dashboard NDD Transport',
    breadcrumb: [
      { title: 'Dashboard' },
      { title: 'NDD Transport', disabled: true },
    ],
  },
})
</script>

<template>
  <VRow class="match-height">
    <!-- Cards de Estatísticas NDD -->
    <VCol cols="12">
      <NDDCardStatistics />
    </VCol>

    <!-- Overview de Entregas NDD -->
    <VCol
      cols="12"
      md="6"
    >
      <NDDVehicleOverview />
    </VCol>

    <!-- Estatísticas de Entregas NDD -->
    <VCol
      cols="12"
      md="6"
    >
      <NDDShipmentStatistics />
    </VCol>

    <!-- Performance de Entrega NDD -->
    <VCol
      cols="12"
      md="4"
    >
      <NDDDeliveryPerformance />
    </VCol>

    <!-- Problemas de Entrega NDD -->
    <VCol
      cols="12"
      md="4"
    >
      <NDDDeliveryExceptions />
    </VCol>

    <!-- Pedidos por Região NDD -->
    <VCol
      cols="12"
      md="4"
    >
      <NDDOrdersByRegion />
    </VCol>

    <!-- Tabela Overview NDD -->
    <VCol cols="12">
      <NDDOverviewTable />
    </VCol>
  </VRow>
</template>