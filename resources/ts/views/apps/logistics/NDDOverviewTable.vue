<script setup lang="ts">
const headers = [
  { title: 'LOCALIZAÇÃO', key: 'location' },
  { title: 'ROTA ORIGEM', key: 'startRoute' },
  { title: 'ROTA DESTINO', key: 'endRoute' },
  { title: 'STATUS', key: 'status' },
  { title: 'PROGRESSO', key: 'progress' },
]

const transportData = [
  {
    location: 'São Paulo, SP',
    startRoute: 'Terminal Central',
    endRoute: 'Rio de Janeiro, RJ',
    status: 'Sem Problemas',
    progress: 85
  },
  {
    location: 'Belo Horizonte, MG',
    startRoute: 'Centro de Distribuição',
    endRoute: 'Salvador, BA',
    status: 'Combustível Baixo',
    progress: 42
  },
  {
    location: 'Curitiba, PR',
    startRoute: 'Porto de Santos',
    endRoute: 'Florianópolis, SC',
    status: 'Sem Problemas',
    progress: 67
  },
  {
    location: 'Brasília, DF',
    startRoute: 'Aeroporto Internacional',
    endRoute: 'Goiânia, GO',
    status: 'Temperatura Alta',
    progress: 23
  },
  {
    location: 'Recife, PE',
    startRoute: 'Porto de Suape',
    endRoute: 'Fortaleza, CE',
    status: 'Sem Problemas',
    progress: 91
  }
]

const resolveChipColor = (status: string) => {
  if (status === 'Sem Problemas')
    return 'success'
  if (status === 'Combustível Baixo')
    return 'warning'
  if (status === 'Temperatura Alta')
    return 'error'
  return 'info'
}

const getProgressColor = (progress: number) => {
  if (progress >= 80) return 'success'
  if (progress >= 50) return 'warning'
  return 'error'
}
</script>

<template>
  <VCard>
    <VCardItem title="Transportes em Rota">
      <template #append>
        <MoreBtn />
      </template>
    </VCardItem>

    <VDivider />

    <VDataTable
      :headers="headers"
      :items="transportData"
      items-per-page="5"
      class="text-no-wrap"
      hide-default-footer
    >
      <template #item.location="{ item }">
        <div class="d-flex align-center gap-x-3">
          <VAvatar
            color="primary"
            variant="tonal"
            size="30"
          >
            <VIcon
              icon="tabler-map-pin"
              size="16"
            />
          </VAvatar>
          <div>
            <div class="text-body-2 font-weight-medium">
              {{ item.location }}
            </div>
          </div>
        </div>
      </template>

      <template #item.startRoute="{ item }">
        <div class="text-body-2">
          {{ item.startRoute }}
        </div>
      </template>

      <template #item.endRoute="{ item }">
        <div class="text-body-2">
          {{ item.endRoute }}
        </div>
      </template>

      <template #item.status="{ item }">
        <VChip
          :color="resolveChipColor(item.status)"
          size="small"
          variant="elevated"
        >
          {{ item.status }}
        </VChip>
      </template>

      <template #item.progress="{ item }">
        <div class="d-flex align-center gap-3">
          <VProgressLinear
            :color="getProgressColor(item.progress)"
            :model-value="item.progress"
            height="8"
            width="100"
            rounded
          />
          <span class="text-body-2 font-weight-medium" style="min-width: 35px;">
            {{ item.progress }}%
          </span>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>