<template>
  <div class="vale-pedagio-fullscreen">
    <!-- Mapa de Fundo -->
    <div class="map-background">
      <div id="map-container" class="w-100 h-100">
        <div class="d-flex flex-column justify-center align-center h-100 text-center pa-6">
          <VIcon icon="tabler-map" size="64" class="text-white mb-4" />
          <h3 class="text-h5 font-weight-medium text-white mb-2">Mapa da Rota</h3>
          <p class="text-body-1 text-white opacity-80">
            O mapa será exibido aqui após calcular a rota
          </p>
        </div>
      </div>
    </div>

    <!-- Painel Lateral Deslizante -->
    <div 
      class="calculator-panel"
      :class="{ 'panel-minimized': panelMinimized }"
    >
      <!-- Botão de Toggle -->
      <VBtn
        class="toggle-button"
        :icon="panelMinimized ? 'tabler-chevron-right' : 'tabler-chevron-left'"
        variant="flat"
        color="primary"
        size="small"
        @click="togglePanel"
      />

      <!-- Conteúdo do Painel -->
      <div class="panel-content">
        <!-- Header Verde -->
        <VCard class="header-card" color="success" variant="flat">
          <VCardText class="d-flex justify-space-between align-center pa-4">
            <div class="d-flex align-center gap-3">
              <VIcon icon="tabler-route-2" size="24" color="white" />
              <div>
                <h3 class="text-h6 text-white font-weight-bold mb-0">Calculadora de Rota</h3>
                <p class="text-caption text-white mb-0 opacity-90">Calcule pedágios e combustível</p>
              </div>
            </div>
            
            <VBtn
              icon="tabler-history"
              variant="text"
              color="white"
              size="small"
            />
          </VCardText>
        </VCard>

        <!-- Formulário Scrollável -->
        <div class="form-scroll">
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
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

// Estados do formulário
const dataHora = ref(new Date())
const loading = ref(false)
const panelMinimized = ref(false)

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
function togglePanel() {
  panelMinimized.value = !panelMinimized.value
}

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
/* Layout Full Screen */
.vale-pedagio-fullscreen {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  z-index: 1;
}

/* Mapa de Fundo */
.map-background {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
  z-index: 1;
}

/* Painel Lateral Deslizante */
.calculator-panel {
  position: absolute;
  top: 0;
  right: 0;
  width: 500px;
  height: 100vh;
  background: white;
  box-shadow: -2px 0 20px rgba(0,0,0,0.1);
  transition: transform 0.3s ease-in-out;
  z-index: 10;
  display: flex;
  flex-direction: column;
}

.calculator-panel.panel-minimized {
  transform: translateX(450px);
}

/* Botão de Toggle */
.toggle-button {
  position: absolute;
  left: -40px;
  top: 50%;
  transform: translateY(-50%);
  border-radius: 8px 0 0 8px !important;
  z-index: 11;
}

/* Conteúdo do Painel */
.panel-content {
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.header-card {
  border-radius: 0;
  flex-shrink: 0;
}

.form-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

/* Seções da Rota */
.route-section {
  border-left: 3px solid rgb(var(--v-theme-primary));
  padding-left: 16px;
  background: rgba(var(--v-theme-surface), 1);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
}

/* Cards de Informação */
.route-info .border {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.route-info .border:hover {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.1);
}

/* Responsividade */
@media (max-width: 768px) {
  .calculator-panel {
    width: 100vw;
  }
  
  .calculator-panel.panel-minimized {
    transform: translateX(calc(100vw - 50px));
  }
  
  .toggle-button {
    left: -50px;
  }
}

/* Scroll personalizado */
.form-scroll::-webkit-scrollbar {
  width: 6px;
}

.form-scroll::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.form-scroll::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

.form-scroll::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>