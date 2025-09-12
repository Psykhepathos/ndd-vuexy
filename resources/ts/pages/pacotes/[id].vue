<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

interface PacoteDetalhe {
  codpac: number
  datforpac: string
  horforpac: string
  codtrn: number
  nomtrn: string
  codrot: string
  valpac: number
  volpac: number
  pespac: number
  sitpac: string
  nroped: number
  valfre?: number
}

interface Entrega {
  seqent: number
  nf: number
  codcli: number
  razcli: string
  uf: string
  desmun: string
  desbai: string
  desend: string
  numnot: string
  valnot: number
  peso: number
  volume: number
  gps_lat?: string
  gps_lon?: string
}

interface ItinerarioData {
  codpac: string
  rota: string
  motorista: number
  peso: number
  volume: number
  valor: number
  frete: number
  pedidos: Entrega[]
}

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const pacoteId = ref(route.params.id as string)
const pacoteDetalhes = ref<PacoteDetalhe | null>(null)
const itinerario = ref<ItinerarioData | null>(null)
const showItinerario = ref(false)

const fetchPacoteDetails = async () => {
  loading.value = true
  
  try {
    const response = await fetch(`http://localhost:8002/api/pacotes/${pacoteId.value}`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    const data = await response.json()

    if (data.success && data.data) {
      pacoteDetalhes.value = data.data
    } else {
      console.error('Erro na API:', data.message)
    }
  } catch (error) {
    console.error('Erro ao carregar pacote:', error)
  } finally {
    loading.value = false
  }
}

const fetchItinerario = async () => {
  if (!pacoteDetalhes.value) return
  
  loading.value = true
  
  try {
    const payload = {
      Pacote: {
        codPac: parseInt(pacoteId.value)
      }
    }
    
    const response = await fetch('http://localhost:8002/api/pacotes/itinerario', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })
    
    const data = await response.json()
    
    if (data.success) {
      itinerario.value = data.data
      showItinerario.value = true
      // Inicializar mapa após carregar dados
      setTimeout(() => {
        initializeMap()
      }, 100)
    } else {
      console.error('Erro ao carregar itinerário:', data.message)
    }
  } catch (error) {
    console.error('Erro ao carregar itinerário:', error)
  } finally {
    loading.value = false
  }
}

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  }).format(value || 0)
}

const formatNumber = (value: number) => {
  return new Intl.NumberFormat('pt-BR').format(value || 0)
}

const formatDate = (date: string) => {
  if (!date) return 'N/D'
  const d = new Date(date + 'T00:00:00')
  return d.toLocaleDateString('pt-BR')
}

const formatTime = (time: string) => {
  if (!time || time.length < 3) return 'N/D'
  const hours = time.substring(0, time.length - 2)
  const minutes = time.substring(time.length - 2)
  return `${hours}:${minutes}`
}

const formatSituacao = (situacao: string) => {
  const situacoes: { [key: string]: { text: string, color: string } } = {
    'U': { text: 'URGENTE', color: 'error' },
    'M': { text: 'MARCADA', color: 'warning' },
    'S': { text: 'EM SEPARAÇÃO', color: 'info' },
    'A': { text: 'AGUARDANDO', color: 'primary' },
    'F': { text: 'FINALIZADA', color: 'success' },
    '': { text: 'NORMAL', color: 'primary' },
    ' ': { text: 'VAZIA', color: 'secondary' }
  }
  
  return situacoes[situacao] || { text: 'INDEFINIDA', color: 'secondary' }
}

const goBack = () => {
  router.push('/pacotes')
}

// Mapa usando Google Maps simples (sem Leaflet para simplicidade)
const initializeMap = () => {
  const mapElement = document.getElementById('delivery-map')
  if (!mapElement || !itinerario.value?.pedidos?.length) return
  
  // Para simplificar, vou criar pontos fictícios de exemplo em São Paulo
  // Em produção, esses dados viriam das coordenadas GPS do banco
  const deliveries = itinerario.value.pedidos.map((pedido, index) => ({
    ...pedido,
    // Coordenadas fictícias para demonstração (região de São Paulo)
    lat: -23.5505 + (Math.random() - 0.5) * 0.2,
    lng: -46.6333 + (Math.random() - 0.5) * 0.2,
    sequence: index + 1
  }))
  
  // Criar HTML do mapa simples com pontos
  mapElement.innerHTML = `
    <div style="position: relative; background: #f5f5f5; height: 100%; display: flex; align-items: center; justify-content: center;">
      <div style="text-align: center;">
        <div style="margin-bottom: 20px;">
          <strong>Pontos de Entrega - Pacote #${itinerario.value.codpac}</strong>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
          ${deliveries.map(delivery => `
            <div style="
              background: #1976d2; 
              color: white; 
              padding: 8px 12px; 
              border-radius: 20px; 
              font-size: 12px;
              cursor: pointer;
              min-width: 60px;
              text-align: center;
            " onclick="alert('Entrega ${delivery.sequence}: Cliente ${delivery.codcli}\\nEndereço: ${delivery.desend || 'N/D'}')">
              ${delivery.sequence}
            </div>
          `).join('')}
        </div>
        <div style="margin-top: 20px; font-size: 12px; color: #666;">
          Clique nos números para ver detalhes da entrega
        </div>
      </div>
    </div>
  `
}

const focusMapOnDelivery = (delivery: any) => {
  // Para agora, mostrar um alerta simples
  alert(`Entrega ${delivery.seqent}\nCliente: ${delivery.razcli || delivery.codcli}\nEndereço: ${delivery.desend || 'N/D'}`)
}

onMounted(() => {
  fetchPacoteDetails()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-3 mb-6">
      <div>
        <VBtn
          variant="text"
          color="secondary"
          @click="goBack"
          class="mb-2"
        >
          <VIcon icon="tabler-arrow-left" start />
          Voltar
        </VBtn>
        <h4 class="text-h4 font-weight-medium mb-0">
          Pacote #{{ pacoteId }}
        </h4>
        <p class="text-body-1 mb-0">
          Detalhes e itinerário de entrega
        </p>
      </div>
      
      <div v-if="pacoteDetalhes && !showItinerario">
        <VBtn
          color="primary"
          @click="fetchItinerario"
          :loading="loading"
        >
          <VIcon icon="tabler-route" start />
          Ver Itinerário
        </VBtn>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading && !pacoteDetalhes" class="d-flex justify-center py-8">
      <VProgressCircular indeterminate color="primary" />
    </div>

    <!-- Detalhes do Pacote -->
    <VCard v-if="pacoteDetalhes" class="mb-6">
      <VCardTitle class="d-flex align-center gap-3">
        <VIcon icon="tabler-package" color="primary" />
        Informações do Pacote
        <VSpacer />
        <VChip
          :color="formatSituacao(pacoteDetalhes.sitpac).color"
          size="small"
        >
          {{ formatSituacao(pacoteDetalhes.sitpac).text }}
        </VChip>
      </VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="6">
            <VList>
              <VListItem>
                <VListItemTitle>Data de Formação</VListItemTitle>
                <VListItemSubtitle>
                  {{ formatDate(pacoteDetalhes.datforpac) }} às {{ formatTime(pacoteDetalhes.horforpac) }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <VListItemTitle>Transportador</VListItemTitle>
                <VListItemSubtitle>
                  {{ pacoteDetalhes.nomtrn }} (Cód: {{ pacoteDetalhes.codtrn }})
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <VListItemTitle>Rota</VListItemTitle>
                <VListItemSubtitle>{{ pacoteDetalhes.codrot }}</VListItemSubtitle>
              </VListItem>
            </VList>
          </VCol>
          <VCol cols="12" md="6">
            <VList>
              <VListItem>
                <VListItemTitle>Valor Total</VListItemTitle>
                <VListItemSubtitle>{{ formatCurrency(pacoteDetalhes.valpac) }}</VListItemSubtitle>
              </VListItem>
              <VListItem>
                <VListItemTitle>Peso Total</VListItemTitle>
                <VListItemSubtitle>{{ formatNumber(pacoteDetalhes.pespac) }} kg</VListItemSubtitle>
              </VListItem>
              <VListItem>
                <VListItemTitle>Volume Total</VListItemTitle>
                <VListItemSubtitle>{{ formatNumber(pacoteDetalhes.volpac) }} m³</VListItemSubtitle>
              </VListItem>
              <VListItem>
                <VListItemTitle>Número de Pedidos</VListItemTitle>
                <VListItemSubtitle>{{ pacoteDetalhes.nroped }}</VListItemSubtitle>
              </VListItem>
            </VList>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Itinerário de Entrega -->
    <VCard v-if="showItinerario && itinerario">
      <VCardTitle class="d-flex align-center gap-3">
        <VIcon icon="tabler-route" color="success" />
        Itinerário de Entrega
        <VSpacer />
        <VChip color="info" size="small">
          {{ itinerario.pedidos?.length || 0 }} Entregas
        </VChip>
      </VCardTitle>
      <VCardText>
        <VRow class="mb-4">
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="primary">
              <VCardText class="text-center">
                <p class="text-h6 mb-0">{{ formatCurrency(itinerario.frete) }}</p>
                <small class="text-disabled">Valor do Frete</small>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="info">
              <VCardText class="text-center">
                <p class="text-h6 mb-0">{{ formatNumber(itinerario.peso) }} kg</p>
                <small class="text-disabled">Peso Total</small>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="warning">
              <VCardText class="text-center">
                <p class="text-h6 mb-0">{{ formatNumber(itinerario.volume) }} m³</p>
                <small class="text-disabled">Volume Total</small>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="success">
              <VCardText class="text-center">
                <p class="text-h6 mb-0">{{ formatCurrency(itinerario.valor) }}</p>
                <small class="text-disabled">Valor Total</small>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Mapa de Entregas -->
        <VCard class="mb-6">
          <VCardTitle class="d-flex align-center gap-3">
            <VIcon icon="tabler-map" color="info" />
            Mapa de Entregas
            <VSpacer />
            <VChip color="success" size="small">
              Rota Sequencial
            </VChip>
          </VCardTitle>
          <VCardText>
            <div id="delivery-map" style="height: 400px; width: 100%;" class="rounded-lg overflow-hidden"></div>
          </VCardText>
        </VCard>

        <!-- Lista de Entregas -->
        <VDataTable
          :headers="[
            { title: 'SEQ', key: 'seqent', width: '80px' },
            { title: 'CLIENTE', key: 'razcli' },
            { title: 'CIDADE/UF', key: 'cidade' },
            { title: 'ENDEREÇO', key: 'desend' },
            { title: 'NF', key: 'numnot', width: '100px' },
            { title: 'VALOR', key: 'valnot', width: '120px' },
            { title: 'PESO', key: 'peso', width: '100px' },
            { title: 'MAPA', key: 'gps', width: '80px' }
          ]"
          :items="itinerario.pedidos || []"
          class="elevation-1"
          :items-per-page="50"
        >
          <template #item.seqent="{ item }">
            <VChip size="small" color="primary">
              {{ item.seqent }}
            </VChip>
          </template>

          <template #item.razcli="{ item }">
            <div>
              <p class="text-body-2 font-weight-medium mb-0">
                {{ item.razcli || 'Cliente ' + item.codcli }}
              </p>
              <small class="text-disabled">Cód: {{ item.codcli }}</small>
            </div>
          </template>

          <template #item.cidade="{ item }">
            {{ (item.desmun || 'N/D') }}{{ item.uf ? '/' + item.uf : '' }}
          </template>

          <template #item.desend="{ item }">
            <div>
              <p class="text-body-2 mb-0">{{ item.desend || 'Endereço não informado' }}</p>
              <small class="text-disabled">{{ item.desbai || '' }}</small>
            </div>
          </template>

          <template #item.valnot="{ item }">
            {{ formatCurrency(item.valnot) }}
          </template>

          <template #item.peso="{ item }">
            {{ formatNumber(item.peso) }} kg
          </template>

          <template #item.gps="{ item }">
            <VBtn
              size="small"
              color="info"
              variant="tonal"
              @click="focusMapOnDelivery(item)"
            >
              <VIcon icon="tabler-map-pin" size="16" />
            </VBtn>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Estado vazio -->
    <VCard v-if="!loading && !pacoteDetalhes">
      <VCardText class="text-center py-8">
        <VIcon icon="tabler-package-off" size="64" color="disabled" class="mb-4" />
        <p class="text-h6 mb-2">Pacote não encontrado</p>
        <p class="text-body-2 text-disabled mb-4">
          O pacote #{{ pacoteId }} não foi encontrado no sistema.
        </p>
        <VBtn color="primary" @click="goBack">
          Voltar para Pacotes
        </VBtn>
      </VCardText>
    </VCard>
  </div>
</template>