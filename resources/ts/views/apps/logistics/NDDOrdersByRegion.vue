<script setup lang="ts">
const currentTab = ref('Novos')
const tabsData = ['Novos', 'Preparando', 'Enviados']

const orderData = {
  'Novos': [
    { region: 'São Paulo', orders: 85, percentage: 45, color: 'success' },
    { region: 'Rio de Janeiro', orders: 42, percentage: 23, color: 'info' },
    { region: 'Minas Gerais', orders: 38, percentage: 20, color: 'warning' },
    { region: 'Bahia', orders: 23, percentage: 12, color: 'error' },
  ],
  'Preparando': [
    { region: 'São Paulo', orders: 67, percentage: 41, color: 'success' },
    { region: 'Rio de Janeiro', orders: 35, percentage: 25, color: 'info' },
    { region: 'Minas Gerais', orders: 28, percentage: 19, color: 'warning' },
    { region: 'Paraná', orders: 22, percentage: 15, color: 'error' },
  ],
  'Enviados': [
    { region: 'São Paulo', orders: 124, percentage: 48, color: 'success' },
    { region: 'Rio de Janeiro', orders: 78, percentage: 28, color: 'info' },
    { region: 'Bahia', orders: 42, percentage: 16, color: 'warning' },
    { region: 'Ceará', orders: 21, percentage: 8, color: 'error' },
  ]
}
</script>

<template>
  <VCard class="country-order-card">
    <VCardItem
      title="Pedidos por Região"
      subtitle="247 entregas em andamento"
    >
      <template #append>
        <MoreBtn />
      </template>
    </VCardItem>

    <VTabs
      v-model="currentTab"
      grow
      class="disable-tab-transition"
    >
      <VTab
        v-for="(tab, index) in tabsData"
        :key="index"
      >
        {{ tab }}
      </VTab>
    </VTabs>

    <VCardText>
      <VWindow v-model="currentTab">
        <VWindowItem
          v-for="(tab, tabIndex) in tabsData"
          :key="tabIndex"
          :value="tab"
        >
          <VList class="card-list">
            <VListItem
              v-for="(order, index) in orderData[tab]"
              :key="index"
              class="px-0"
            >
              <template #prepend>
                <VAvatar
                  :color="order.color"
                  variant="tonal"
                  rounded
                  size="34"
                  class="me-3"
                >
                  <VIcon
                    icon="tabler-map-pin"
                    size="20"
                  />
                </VAvatar>
              </template>

              <VListItemTitle class="text-sm font-weight-medium">
                {{ order.region }}
              </VListItemTitle>

              <VListItemSubtitle class="text-xs">
                {{ order.orders }} pedidos
              </VListItemSubtitle>

              <template #append>
                <div class="d-flex flex-column align-end">
                  <h6 class="text-h6 text-high-emphasis">
                    {{ order.percentage }}%
                  </h6>
                  <VProgressLinear
                    :color="order.color"
                    :model-value="order.percentage"
                    height="4"
                    width="60"
                    class="mt-1"
                  />
                </div>
              </template>
            </VListItem>
          </VList>
        </VWindowItem>
      </VWindow>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.country-order-card {
  .card-list {
    --v-card-list-gap: 1.25rem;
  }

  .v-timeline-item__body {
    padding-inline-start: 1rem !important;
  }
}

.disable-tab-transition {
  .v-slide-group__content {
    transition: none !important;
  }
}
</style>