<template>
  <div class="vale-pedagio-container">
    <!-- Header Verde -->
    <VCard class="header-card mb-6" color="success" variant="flat">
      <VCardText class="d-flex justify-space-between align-center pa-6">
        <div class="d-flex align-center gap-4">
          <VIcon icon="tabler-route-2" size="32" color="white" />
          <div>
            <h2 class="text-h4 text-white font-weight-bold mb-1">Calculadora de Rota</h2>
            <p class="text-body-1 text-white mb-0 opacity-90">Calcule pedágios, combustível e distância</p>
          </div>
        </div>
        
        <div class="d-flex gap-3">
          <VBtn
            variant="outlined"
            color="white"
            prepend-icon="tabler-history"
          >
            Minhas Rotas
          </VBtn>
          <VBtn
            variant="flat"
            color="white"
            prepend-icon="tabler-map-pin"
          >
            Ver no Mapa
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <VRow>
      <!-- Formulário Principal -->
      <VCol cols="12" lg="6">
        <VCard class="form-card">
          <VCardText class="pa-6">
            <!-- Data/Hora -->
            <div class="d-flex align-center gap-4 mb-6">
              <VIcon icon="tabler-calendar" class="text-primary" />
              <AppTextField
                v-model="dataHoraFormatted"
                label="Data e Hora"
                placeholder="DD/MM/AAAA HH:MM"
                class="flex-grow-1"
                prepend-inner-icon="tabler-calendar"
                readonly
              />
            </div>

            <!-- Origem -->
            <div class="route-section mb-6">
              <div class="d-flex align-center gap-3 mb-4">
                <VIcon icon="tabler-map-pin" class="text-success" />
                <h3 class="text-h6 font-weight-medium">Origem</h3>
              </div>
              
              <VRow>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="origem.cidade"
                    label="Cidade"
                    placeholder="Digite a cidade"
                    prepend-inner-icon="tabler-building"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="origem.endereco"
                    label="Endereço"
                    placeholder="Digite o endereço"
                    prepend-inner-icon="tabler-map-pin"
                  />
                </VCol>
              </VRow>

              <div class="d-flex gap-2 mt-3">
                <VBtn
                  size="small"
                  variant="outlined"
                  color="success"
                  prepend-icon="tabler-plus"
                >
                  CEP
                </VBtn>
                <VBtn
                  size="small"
                  variant="outlined"
                  color="primary"
                  prepend-icon="tabler-edit"
                >
                  Editar
                </VBtn>
                <VBtn
                  size="small"
                  variant="outlined"
                  color="error"
                  prepend-icon="tabler-trash"
                >
                  Limpar
                </VBtn>
              </div>
            </div>

            <!-- Botão Inverter -->
            <div class="text-center mb-6">
              <VBtn
                icon="tabler-arrows-up-down"
                variant="outlined"
                color="primary"
                @click="inverterOrigemDestino"
              />
            </div>

            <!-- Destino -->
            <div class="route-section mb-6">
              <div class="d-flex align-center gap-3 mb-4">
                <VIcon icon="tabler-flag" class="text-error" />
                <h3 class="text-h6 font-weight-medium">Destino</h3>
              </div>
              
              <VRow>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="destino.cidade"
                    label="Cidade"
                    placeholder="Digite a cidade"
                    prepend-inner-icon="tabler-building"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField
                    v-model="destino.endereco"
                    label="Endereço"
                    placeholder="Digite o endereço"
                    prepend-inner-icon="tabler-map-pin"
                  />
                </VCol>
              </VRow>

              <div class="d-flex gap-2 mt-3">
                <VBtn
                  size="small"
                  variant="outlined"
                  color="success"
                  prepend-icon="tabler-plus"
                >
                  CEP
                </VBtn>
                <VBtn
                  size="small"
                  variant="outlined"
                  color="primary"
                  prepend-icon="tabler-edit"
                >
                  Editar
                </VBtn>
                <VBtn
                  size="small"
                  variant="outlined"
                  color="error"
                  prepend-icon="tabler-trash"
                >
                  Limpar
                </VBtn>
              </div>
            </div>

            <!-- Checkboxes de Opções -->
            <div class="route-options mb-6">
              <VRow>
                <VCol cols="12" md="6">
                  <VCheckbox
                    v-model="opcoes.calcularRota"
                    label="Calcular rota"
                    color="primary"
                  />
                </VCol>
                <VCol cols="12" md="6">
                  <VCheckbox
                    v-model="opcoes.calcularVolta"
                    label="Calcular volta"
                    color="primary"
                  />
                </VCol>
              </VRow>
            </div>

            <!-- Informações da Rota -->
            <VRow class="route-info mb-6">
              <VCol cols="6" md="3">
                <div class="text-center pa-3 border rounded">
                  <VIcon icon="tabler-gas-station" class="text-primary mb-2" size="24" />
                  <div class="text-h6 font-weight-bold">{{ combustivel.valor }}</div>
                  <div class="text-caption">Combustível</div>
                  <div class="text-caption">R$</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="text-center pa-3 border rounded">
                  <VIcon icon="tabler-car" class="text-success mb-2" size="24" />
                  <div class="text-h6 font-weight-bold">{{ consumo.valor }}</div>
                  <div class="text-caption">Consumo</div>
                  <div class="text-caption">KM/L</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="text-center pa-3 border rounded">
                  <VIcon icon="tabler-clock" class="text-warning mb-2" size="24" />
                  <div class="text-h6 font-weight-bold">{{ tempo.valor }}</div>
                  <div class="text-caption">Tempo</div>
                  <div class="text-caption">H</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="text-center pa-3 border rounded">
                  <VIcon icon="tabler-road" class="text-info mb-2" size="24" />
                  <div class="text-h6 font-weight-bold">{{ eixos.valor }}</div>
                  <div class="text-caption">Eixos</div>
                  <div class="text-caption">Qtd</div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Mais Opções da Rota -->
        <VCard class="mt-6">
          <VCardText class="pa-6">
            <h3 class="text-h6 font-weight-medium mb-4">Mais opções da rota</h3>
            
            <VRow>
              <VCol cols="12" md="6">
                <VCheckbox
                  v-model="maisOpcoes.pesquisarPosto"
                  label="Pesquisar Posto de combustível"
                  color="primary"
                />
                
                <div v-if="maisOpcoes.pesquisarPosto" class="ml-8 mt-2">
                  <AppTextField
                    v-model="maisOpcoes.raioBusca"
                    label="Raio de busca"
                    placeholder="0,00"
                    suffix="Metros"
                    type="number"
                  />
                </div>
              </VCol>
              <VCol cols="12" md="6">
                <VCheckbox
                  v-model="maisOpcoes.pontosInteresse"
                  label="Pesquisar pontos de interesse"
                  color="primary"
                />
                <VCheckbox
                  v-model="maisOpcoes.priorizarRodovias"
                  label="Priorizar as rodovias"
                  color="primary"
                />
                <VCheckbox
                  v-model="maisOpcoes.evitarPedagio"
                  label="Evitar pedágio"
                  color="primary"
                />
                <VCheckbox
                  v-model="maisOpcoes.evitarBalsa"
                  label="Evitar balsa"
                  color="primary"
                />
              </VCol>
            </VRow>

            <!-- Preferências de Rota -->
            <div class="mt-6">
              <h4 class="text-subtitle-1 font-weight-medium mb-4">Preferências de Rota</h4>
              
              <div class="d-flex gap-3 mb-4">
                <VBtn
                  :variant="preferencia === 'mais-rapida' ? 'flat' : 'outlined'"
                  :color="preferencia === 'mais-rapida' ? 'success' : 'default'"
                  @click="preferencia = 'mais-rapida'"
                >
                  Mais Rápida
                </VBtn>
                <VBtn
                  :variant="preferencia === 'curta' ? 'flat' : 'outlined'"
                  :color="preferencia === 'curta' ? 'success' : 'default'"
                  @click="preferencia = 'curta'"
                >
                  Curta
                </VBtn>
                <VBtn
                  :variant="preferencia === 'mais-curta' ? 'flat' : 'outlined'"
                  :color="preferencia === 'mais-curta' ? 'success' : 'default'"
                  @click="preferencia = 'mais-curta'"
                >
                  Mais Curta
                </VBtn>
                <VBtn
                  :variant="preferencia === 'economica' ? 'flat' : 'outlined'"
                  :color="preferencia === 'economica' ? 'success' : 'default'"
                  @click="preferencia = 'economica'"
                >
                  Econômica
                </VBtn>
              </div>

              <p class="text-body-2 text-medium-emphasis mb-4">
                Traçar rota priorizando rodovias preferenciais para:
              </p>

              <!-- Tipos de Veículo -->
              <div class="d-flex gap-3">
                <VBtn
                  :variant="tipoVeiculo === 'carro' ? 'flat' : 'outlined'"
                  :color="tipoVeiculo === 'carro' ? 'success' : 'default'"
                  @click="tipoVeiculo = 'carro'"
                  prepend-icon="tabler-car"
                >
                  Carro
                </VBtn>
                <VBtn
                  :variant="tipoVeiculo === 'moto' ? 'flat' : 'outlined'"
                  :color="tipoVeiculo === 'moto' ? 'success' : 'default'"
                  @click="tipoVeiculo = 'moto'"
                  prepend-icon="tabler-bike"
                >
                  Moto
                </VBtn>
                <VBtn
                  :variant="tipoVeiculo === 'caminhao' ? 'flat' : 'outlined'"
                  :color="tipoVeiculo === 'caminhao' ? 'success' : 'default'"
                  @click="tipoVeiculo = 'caminhao'"
                  prepend-icon="tabler-truck"
                >
                  Caminhão
                </VBtn>
                <VBtn
                  :variant="tipoVeiculo === 'onibus' ? 'flat' : 'outlined'"
                  :color="tipoVeiculo === 'onibus' ? 'success' : 'default'"
                  @click="tipoVeiculo = 'onibus'"
                  prepend-icon="tabler-bus"
                >
                  Ônibus
                </VBtn>
                <VBtn
                  :variant="tipoVeiculo === 'van' ? 'flat' : 'outlined'"
                  :color="tipoVeiculo === 'van' ? 'success' : 'default'"
                  @click="tipoVeiculo = 'van'"
                  prepend-icon="tabler-truck-delivery"
                >
                  Van
                </VBtn>
              </div>
            </div>

            <!-- Botão Calcular -->
            <div class="mt-6 text-center">
              <VBtn
                color="warning"
                size="large"
                variant="flat"
                prepend-icon="tabler-calculator"
                @click="calcularRota"
                :loading="loading"
              >
                Calcular Rota
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Mapa -->
      <VCol cols="12" lg="6">
        <VCard class="map-card h-100">
          <VCardText class="pa-0 h-100">
            <div id="map-container" class="w-100" style="height: 800px; background: #f5f5f5; border-radius: 8px;">
              <div class="d-flex flex-column justify-center align-center h-100 text-center pa-6">
                <VIcon icon="tabler-map" size="64" class="text-medium-emphasis mb-4" />
                <h3 class="text-h5 font-weight-medium text-medium-emphasis mb-2">Mapa da Rota</h3>
                <p class="text-body-1 text-medium-emphasis">
                  O mapa será exibido aqui após calcular a rota
                </p>
                
                <!-- Botões do Mapa -->
                <div class="mt-6">
                  <VBtn
                    variant="outlined"
                    color="success"
                    prepend-icon="tabler-route"
                    class="me-3"
                  >
                    Nova Rota
                  </VBtn>
                  <VBtn
                    variant="outlined"
                    color="primary"
                    prepend-icon="tabler-eye"
                  >
                    Ver no Mapa
                  </VBtn>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

// Estados do formulário
const dataHora = ref(new Date())
const loading = ref(false)

// Computed para formatar data e hora
const dataHoraFormatted = computed(() => {
  return dataHora.value.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
})

const origem = ref({
  cidade: '',
  endereco: ''
})

const destino = ref({
  cidade: '',
  endereco: ''
})

const opcoes = ref({
  calcularRota: true,
  calcularVolta: false
})

const maisOpcoes = ref({
  pesquisarPosto: false,
  raioBusca: '0,00',
  pontosInteresse: false,
  priorizarRodovias: false,
  evitarPedagio: false,
  evitarBalsa: false
})

const preferencia = ref('mais-rapida')
const tipoVeiculo = ref('carro')

// Dados da rota
const combustivel = ref({ valor: '0,00' })
const consumo = ref({ valor: '0,0' })
const tempo = ref({ valor: '0' })
const eixos = ref({ valor: '2' })

// Funções
function inverterOrigemDestino() {
  const temp = { ...origem.value }
  origem.value = { ...destino.value }
  destino.value = temp
}

async function calcularRota() {
  if (!origem.value.cidade || !destino.value.cidade) {
    // Mostrar erro de validação
    return
  }

  loading.value = true
  
  try {
    // Aqui você faria a chamada para a API de cálculo de rota
    await new Promise(resolve => setTimeout(resolve, 2000))
    
    // Atualizar dados da rota
    combustivel.value.valor = '45,80'
    consumo.value.valor = '12,5'
    tempo.value.valor = '3'
    eixos.value.valor = '2'
    
    console.log('Rota calculada:', {
      origem: origem.value,
      destino: destino.value,
      preferencia: preferencia.value,
      tipoVeiculo: tipoVeiculo.value,
      opcoes: opcoes.value
    })
  } catch (error) {
    console.error('Erro ao calcular rota:', error)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.vale-pedagio-container {
  padding: 24px;
}

.header-card {
  border-radius: 12px;
}

.form-card {
  border-radius: 12px;
}

.map-card {
  border-radius: 12px;
}

.route-section {
  border-left: 3px solid rgb(var(--v-theme-primary));
  padding-left: 16px;
}

.route-info .border {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>