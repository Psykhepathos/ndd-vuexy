<script setup lang="ts">
const deliveryData = [
  { title: 'Pacotes em trânsito', value: '2.8k', change: 25.8, icon: 'tabler-box', color: 'primary' },
  { title: 'Saiu para entrega', value: '892', change: 4.3, icon: 'tabler-truck', color: 'info' },
  { title: 'Pacotes entregues', value: '12.4k', change: -12.5, icon: 'tabler-circle-check', color: 'success' },
  { title: 'Taxa de sucesso', value: '94%', change: 35.6, icon: 'tabler-percentage', color: 'warning' },
  { title: 'Tempo médio', value: '2.1 Dias', change: -2.15, icon: 'tabler-clock', color: 'secondary' },
  { title: 'Satisfação cliente', value: '4.3/5', change: 5.7, icon: 'tabler-users', color: 'error' },
]
</script>

<template>
  <VCard>
    <VCardItem title="Performance de Entregas">
      <VCardSubtitle>
        12% de aumento este mês
      </VCardSubtitle>
      <template #append>
        <MoreBtn />
      </template>
    </VCardItem>

    <VCardText>
      <VList class="card-list">
        <VListItem
          v-for="(data, index) in deliveryData"
          :key="index"
        >
          <template #prepend>
            <VAvatar
              :color="data.color"
              variant="tonal"
              rounded
              size="38"
              class="me-1"
            >
              <VIcon
                :icon="data.icon"
                size="26"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="me-2">
            {{ data.title }}
          </VListItemTitle>

          <VListItemSubtitle>
            <div
              :class="data.change > 0 ? 'text-success' : 'text-error'"
              class="text-sm"
            >
              <VIcon
                :icon="data.change > 0 ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                size="16"
              />
              <span>{{ Math.abs(data.change) }}%</span>
            </div>
          </VListItemSubtitle>

          <template #append>
            <VListItemAction>
              <h6 class="text-h6 font-weight-regular">
                {{ data.value }}
              </h6>
            </VListItemAction>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1rem;
}
</style>