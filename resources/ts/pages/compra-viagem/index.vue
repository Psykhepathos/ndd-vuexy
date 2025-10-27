<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { API_BASE_URL } from '@/config/api'

// ============================================================================
// SISTEMA DE DEBUG E LOGGING
// ============================================================================
interface DebugLog {
  timestamp: string
  level: 'info' | 'warn' | 'error' | 'success'
  category: string
  message: string
  data?: any
}

const debugLogs = ref<DebugLog[]>([])
const showDebugPanel = ref(false)
const debugStats = ref({
  totalRequests: 0,
  successfulRequests: 0,
  failedRequests: 0,
  cacheHits: 0,
  lastUpdate: null as Date | null
})

const addDebugLog = (level: DebugLog['level'], category: string, message: string, data?: any) => {
  const log: DebugLog = {
    timestamp: new Date().toISOString(),
    level,
    category,
    message,
    data
  }
  debugLogs.value.unshift(log)

  // Manter apenas últimos 100 logs
  if (debugLogs.value.length > 100) {
    debugLogs.value = debugLogs.value.slice(0, 100)
  }

  // Log no console também
  const emoji = {
    info: 'ℹ️',
    warn: '⚠️',
    error: '❌',
    success: '✅'
  }[level]

  console.log(`${emoji} [${category}] ${message}`, data || '')
}

const clearDebugLogs = () => {
  debugLogs.value = []
  debugStats.value = {
    totalRequests: 0,
    successfulRequests: 0,
    failedRequests: 0,
    cacheHits: 0,
    lastUpdate: null
  }
  addDebugLog('info', 'SYSTEM', 'Logs limpos')
}

// Helper para fazer requests COM DEBUG
const apiFetch = async (url: string, options: RequestInit = {}) => {
  const startTime = performance.now()
  debugStats.value.totalRequests++
  debugStats.value.lastUpdate = new Date()

  addDebugLog('info', 'API', `Request: ${options.method || 'GET'} ${url}`)

  try {
    const response = await fetch(`${API_BASE_URL}${url}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,
      },
    })

    const data = await response.json()
    const duration = ((performance.now() - startTime) / 1000).toFixed(2)

    if (data.success !== false) {
      debugStats.value.successfulRequests++
      addDebugLog('success', 'API', `Response ${response.status} em ${duration}s`, data)
    } else {
      debugStats.value.failedRequests++
      addDebugLog('error', 'API', `Erro ${response.status} em ${duration}s`, data)
    }

    return data
  } catch (error: any) {
    const duration = ((performance.now() - startTime) / 1000).toFixed(2)
    debugStats.value.failedRequests++
    addDebugLog('error', 'API', `Exceção após ${duration}s: ${error.message}`, error)
    throw error
  }
}

// ============================================================================
// ESTADO DO FORMULÁRIO
// ============================================================================

// Dados do pacote
const codpac = ref<number | null>(null)
const descPacote = ref('')
const codtrn = ref<number | null>(null)
const nomeTransporte = ref('')

// Dados da placa
const placa = ref('')
const descricaoVei = ref('')
const eixos = ref<number>(2)
const proprietario = ref('')
const tag = ref('')

// Dados da rota
const rotaId = ref<number | null>(null)
const rotaNome = ref('')
const rotasOptions = ref<any[]>([]) // Rotas atuais (muda com modo CD)
const rotasCache = ref<{cd: any[], normal: any[]}>({ cd: [], normal: [] }) // Cache completo

// Dados da compra
const nomRotSemParar = ref('')
const codRotaSemParar = ref('')
const valorViagem = ref(0)
const numeroViagem = ref('')
const dataInicio = ref('')
const dataFim = ref('')

// Modos de operação (Progress: compraRota.p linha 176-196)
const modoCD = ref(false) // false = Normal, true = CD (TCD)
const modoRetorno = ref(false) // false = Ida, true = Retorno

// Checkboxes de progresso
const verificaPacote = ref(false)
const verificaTransporte = ref(false)
const verificaPlaca = ref(false)
const verificaRota = ref(false)
const verificaValor = ref(false)

// Estados de loading
const loadingPacote = ref(false)
const loadingPlaca = ref(false)
const loadingRotas = ref(false)
const loadingPreco = ref(false)

// Timer para debounce de busca
let searchTimer: ReturnType<typeof setTimeout> | null = null

// Dialogs
const showPlacaDialog = ref(false)
const showPrecoDialog = ref(false)

// Snackbar/Toast (Vuexy pattern)
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref<'success' | 'error' | 'warning' | 'info'>('error')

const showToast = (message: string, color: 'success' | 'error' | 'warning' | 'info' = 'error') => {
  snackbarText.value = message
  snackbarColor.value = color
  snackbar.value = true
}

// Controle de campos
const pacoteDisabled = ref(false)
const placaDisabled = ref(true)
const rotaDisabled = ref(true)

// Config
const testMode = ref(false)

// ============================================================================
// WATCHERS - Recarrega rotas quando modo CD muda
// ============================================================================
watch(modoCD, async () => {
  // Recarrega rotas quando modo CD muda
  if (verificaPacote.value) {
    rotaId.value = null
    await carregarTodasRotas()
  }
})

// ============================================================================
// ETAPA ATUAL
// ============================================================================
const currentStep = computed(() => {
  if (!verificaPacote.value) return 1
  if (!verificaPlaca.value) return 2
  if (!verificaRota.value) return 3
  if (!verificaValor.value) return 4
  return 5
})

const steps = [
  { number: 1, title: 'Pacote', icon: 'tabler-package' },
  { number: 2, title: 'Placa', icon: 'tabler-car' },
  { number: 3, title: 'Rota', icon: 'tabler-route' },
  { number: 4, title: 'Preço', icon: 'tabler-currency-real' },
  { number: 5, title: 'Confirmar', icon: 'tabler-check' },
]

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================
const initialize = async () => {
  try {
    const data = await apiFetch('/api/compra-viagem/initialize')
    testMode.value = data.data.test_mode

    const hoje = new Date()
    dataInicio.value = hoje.toISOString().split('T')[0]
    const fim = new Date()
    fim.setDate(fim.getDate() + 5)
    dataFim.value = fim.toISOString().split('T')[0]
  }
  catch (error) {
    console.error('Erro ao inicializar:', error)
  }
}

// ============================================================================
// ETAPA 1: VALIDAR PACOTE
// ============================================================================
const validarPacote = async () => {
  if (!codpac.value) return

  loadingPacote.value = true
  try {
    const data = await apiFetch('/api/compra-viagem/validar-pacote', {
      method: 'POST',
      body: JSON.stringify({ codpac: codpac.value, flgcd: modoCD.value }),
    })

    if (!data.success) {
      showToast(data.error || 'Pacote inválido', 'error')
      return
    }

    const pkg = data.data
    descPacote.value = `${pkg.rota?.desrot || ''} ${pkg.rota?.codrot || ''}`
    codtrn.value = pkg.transporte.codtrn
    nomeTransporte.value = pkg.transporte.nomtrn
    placa.value = pkg.transporte.numpla

    // AUTO-SUGESTÃO DE ROTA (Progress: compraRota.p linha 432-475)
    if (pkg.rota_sugerida) {
      rotaId.value = pkg.rota_sugerida.spararrotid
      showToast(`Rota sugerida: ${pkg.rota_sugerida.desspararrot}`, 'info')
    }

    verificaPacote.value = true
    verificaTransporte.value = true
    pacoteDisabled.value = true
    placaDisabled.value = false

    // Carrega TODAS as rotas para o autocomplete
    await carregarTodasRotas()

    // Auto-validar placa
    setTimeout(() => validarPlaca(), 300)
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao validar pacote', 'error')
  }
  finally {
    loadingPacote.value = false
  }
}

// ============================================================================
// ETAPA 2: VALIDAR PLACA
// ============================================================================
const validarPlaca = async () => {
  if (!placa.value) return

  loadingPlaca.value = true
  try {
    const data = await apiFetch('/api/compra-viagem/validar-placa', {
      method: 'POST',
      body: JSON.stringify({ placa: placa.value }),
    })

    if (!data.success) {
      showToast(data.error || 'Placa inválida', 'error')
      return
    }

    descricaoVei.value = data.data.descricao
    eixos.value = data.data.eixos
    proprietario.value = data.data.proprietario
    tag.value = data.data.tag

    showPlacaDialog.value = true
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao validar placa', 'error')
  }
  finally {
    loadingPlaca.value = false
  }
}

const confirmarPlaca = () => {
  if (eixos.value < 2 || eixos.value > 10) {
    showToast('Eixos inválidos (mín: 2, máx: 10)', 'warning')
    return
  }

  showPlacaDialog.value = false
  verificaPlaca.value = true
  placaDisabled.value = true
  rotaDisabled.value = false
}

// ============================================================================
// ETAPA 3: BUSCAR E SELECIONAR ROTA
// Progress: compraRota.p linha 479-485 (F2 help)
// ============================================================================
const carregarTodasRotas = async () => {
  loadingRotas.value = true
  try {
    const tipoAtual = modoCD.value ? 'cd' : 'normal'

    // Se já tem cache, usa direto
    if (rotasCache.value[tipoAtual].length > 0) {
      rotasOptions.value = rotasCache.value[tipoAtual]
      loadingRotas.value = false
      return
    }

    // Busca TODAS as rotas do tipo atual
    const params = new URLSearchParams({
      search: '', // Vazio = busca TODAS
      flg_cd: modoCD.value ? '1' : '0',
    })

    const data = await apiFetch(`/api/compra-viagem/rotas?${params}`)
    const rotas = data.data || []

    // Guarda no cache
    rotasCache.value[tipoAtual] = rotas
    rotasOptions.value = rotas
  }
  catch (error) {
    console.error('Erro ao carregar rotas:', error)
    rotasOptions.value = []
  }
  finally {
    loadingRotas.value = false
  }
}

const selecionarRota = async () => {
  if (!rotaId.value) return

  // VALIDAR ROTA PRIMEIRO (Progress: compraRota.p linha 492-696)
  try {
    const data = await apiFetch('/api/compra-viagem/validar-rota', {
      method: 'POST',
      body: JSON.stringify({
        codpac: codpac.value,
        cod_rota: rotaId.value,
        flgcd: modoCD.value,
        flgretorno: modoRetorno.value,
      }),
    })

    if (!data.success) {
      showToast(data.error || 'Rota inválida', 'error')
      rotaId.value = null
      return
    }

    // Rota validada! Usa datas retornadas pelo backend
    showToast('Rota validada com sucesso', 'success')

    // Auto-verificar preço após validação
    await verificarPreco()
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao validar rota', 'error')
    rotaId.value = null
  }
}

// ============================================================================
// ETAPA 4: VERIFICAR PREÇO
// ============================================================================
const verificarPreco = async () => {
  if (!codpac.value || !rotaId.value || !placa.value) {
    showToast('Preencha todos os campos antes de verificar o preço', 'warning')
    return
  }

  loadingPreco.value = true
  try {
    const data = await apiFetch('/api/compra-viagem/verificar-preco', {
      method: 'POST',
      body: JSON.stringify({
        codpac: codpac.value,
        cod_rota: rotaId.value,
        qtd_eixos: eixos.value,
        placa: placa.value,
        data_inicio: dataInicio.value,
        data_fim: dataFim.value,
      }),
    })

    if (!data.success) {
      showToast(data.error || 'Erro ao calcular preço', 'error')
      return
    }

    valorViagem.value = data.data.valor
    numeroViagem.value = data.data.numero_viagem || ''
    nomRotSemParar.value = data.data.nome_rota || ''
    codRotaSemParar.value = data.data.cod_rota || ''

    verificaRota.value = true
    verificaValor.value = true
    rotaDisabled.value = true

    showPrecoDialog.value = true
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao verificar preço', 'error')
  }
  finally {
    loadingPreco.value = false
  }
}

// ============================================================================
// ETAPA 5: COMPRAR VIAGEM
// Progress: compraRota.p linha 827-995
// ============================================================================
const comprar = async () => {
  if (!codpac.value || !rotaId.value || !placa.value || !eixos.value) {
    showToast('Dados incompletos para compra', 'error')
    return
  }

  try {
    const data = await apiFetch('/api/compra-viagem/comprar', {
      method: 'POST',
      body: JSON.stringify({
        codpac: codpac.value,
        cod_rota: rotaId.value,
        placa: placa.value,
        qtd_eixos: eixos.value,
        data_inicio: dataInicio.value,
        data_fim: dataFim.value,
        nome_rota_semparar: nomRotSemParar.value,
        cod_rota_semparar: codRotaSemParar.value,
        valor_viagem: valorViagem.value,
        flgcd: modoCD.value,
        flgretorno: modoRetorno.value,
      }),
    })

    if (!data.success) {
      showToast(data.error || 'Erro ao comprar viagem', 'error')
      return
    }

    // Compra concluída com sucesso!
    numeroViagem.value = data.data.numero_viagem
    showPrecoDialog.value = false

    // Mostra mensagem de sucesso
    showToast(
      `Viagem comprada! Número: ${numeroViagem.value}`,
      'success',
    )

    // RESET COMPLETO (Progress: compraRota.p linha 919-956)
    setTimeout(() => resetarCompleto(), 2000)
  }
  catch (error: any) {
    showToast(error.message || 'Erro ao comprar viagem', 'error')
  }
}

// ============================================================================
// RESET COMPLETO PÓS-COMPRA
// Progress: compraRota.p linha 925-956
// ============================================================================
const resetarCompleto = () => {
  // Zera TODAS as flags de verificação
  verificaPacote.value = false
  verificaTransporte.value = false
  verificaPlaca.value = false
  verificaRota.value = false
  verificaValor.value = false

  // Reseta modos
  modoCD.value = false
  modoRetorno.value = false

  // Limpa TODAS as variáveis (Progress: linha 932-951)
  codpac.value = null
  descPacote.value = ''
  placa.value = ''
  nomeTransporte.value = ''
  descricaoVei.value = ''
  proprietario.value = ''
  tag.value = ''
  eixos.value = null
  rotaId.value = null
  rotaNome.value = ''
  nomRotSemParar.value = ''
  codRotaSemParar.value = ''
  valorViagem.value = 0
  numeroViagem.value = ''
  dataInicio.value = ''
  dataFim.value = ''
  codtrn.value = null

  // Reseta estados disabled
  pacoteDisabled.value = false
  placaDisabled.value = true
  rotaDisabled.value = true

  // Mostra mensagem e foca no campo pacote
  showToast('Sistema resetado. Pronto para nova compra!', 'info')
}

initialize()
addDebugLog('info', 'SYSTEM', 'Sistema de Compra de Viagem SemParar inicializado')
</script>

<template>
  <div>
    <!-- Header com título e stepper -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
          <div>
            <h4 class="text-h4 mb-1">
              Compra de Viagem SemParar
            </h4>
            <p class="text-body-1 mb-0">
              Siga as etapas para comprar uma viagem
            </p>
          </div>
          <div class="d-flex gap-3 align-center">
            <!-- Switches de Modo (Progress: compraRota.p linha 176-196) -->
            <VSwitch
              v-model="modoCD"
              color="primary"
              label="Modo CD (TCD)"
              hide-details
              density="compact"
              :disabled="verificaPacote"
            />

            <VSwitch
              v-model="modoRetorno"
              color="warning"
              label="Retorno"
              hide-details
              density="compact"
              :disabled="verificaPacote"
            />

            <VDivider vertical />

            <VChip
              v-if="testMode"
              color="warning"
              variant="tonal"
            >
              Modo Seguro
            </VChip>

            <!-- Botão Debug -->
            <VBtn
              :color="showDebugPanel ? 'warning' : 'info'"
              :variant="showDebugPanel ? 'flat' : 'tonal'"
              prepend-icon="tabler-bug"
              size="small"
              @click="showDebugPanel = !showDebugPanel"
              title="Abrir painel de debug"
            >
              Debug
              <VBadge
                v-if="debugLogs.length > 0"
                :content="debugLogs.length"
                color="error"
                inline
                class="ms-2"
              />
            </VBtn>
          </div>
        </div>

        <!-- Stepper visual -->
        <div class="d-flex align-center justify-space-between">
          <div
            v-for="(step, index) in steps"
            :key="step.number"
            class="d-flex align-center"
            :style="{ flex: index < steps.length - 1 ? 1 : 0 }"
          >
            <div class="d-flex flex-column align-center">
              <VAvatar
                :color="currentStep >= step.number ? 'primary' : 'default'"
                :variant="currentStep >= step.number ? 'tonal' : 'outlined'"
                size="48"
              >
                <VIcon :icon="step.icon" />
              </VAvatar>
              <span class="text-caption mt-2">{{ step.title }}</span>
            </div>
            <VDivider
              v-if="index < steps.length - 1"
              class="mx-4"
              :thickness="2"
              :color="currentStep > step.number ? 'primary' : 'default'"
            />
          </div>
        </div>
      </VCardText>
    </VCard>

    <VRow>
      <!-- Coluna principal -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardText>
            <!-- ETAPA 1: Pacote -->
            <div class="mb-6">
              <h5 class="text-h5 mb-4">
                1. Dados do Pacote
              </h5>
              <VRow>
                <VCol
                  cols="12"
                  sm="4"
                >
                  <VTextField
                    v-model.number="codpac"
                    label="Código do Pacote"
                    type="number"
                    :disabled="pacoteDisabled"
                    :loading="loadingPacote"
                    @keydown.enter="validarPacote"
                  >
                    <template #append-inner>
                      <VIcon
                        v-if="verificaPacote"
                        icon="tabler-check"
                        color="success"
                      />
                    </template>
                  </VTextField>
                </VCol>
                <VCol
                  cols="12"
                  sm="8"
                >
                  <VTextField
                    v-model="descPacote"
                    label="Descrição"
                    readonly
                    variant="outlined"
                  />
                </VCol>
              </VRow>

              <VRow v-if="verificaPacote">
                <VCol
                  cols="12"
                  sm="4"
                >
                  <VTextField
                    v-model="codtrn"
                    label="Cód. Transporte"
                    readonly
                    variant="plain"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="8"
                >
                  <VTextField
                    v-model="nomeTransporte"
                    label="Transportadora"
                    readonly
                    variant="plain"
                  >
                    <template #append-inner>
                      <VIcon
                        v-if="verificaTransporte"
                        icon="tabler-check"
                        color="success"
                      />
                    </template>
                  </VTextField>
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-6" />

            <!-- ETAPA 2: Placa -->
            <div class="mb-6">
              <h5 class="text-h5 mb-4">
                2. Veículo
              </h5>
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="placa"
                    label="Placa"
                    :disabled="placaDisabled"
                    :loading="loadingPlaca"
                    @keydown.enter="validarPlaca"
                  >
                    <template #append-inner>
                      <VIcon
                        v-if="verificaPlaca"
                        icon="tabler-check"
                        color="success"
                      />
                    </template>
                  </VTextField>
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-6" />

            <!-- ETAPA 3: Rota -->
            <div class="mb-6">
              <h5 class="text-h5 mb-4">
                3. Rota SemParar
              </h5>
              <VRow>
                <VCol cols="12">
                  <VAutocomplete
                    :key="`rota-autocomplete-${modoCD}`"
                    v-model="rotaId"
                    label="Buscar Rota SemParar"
                    placeholder="Digite para buscar..."
                    :items="rotasOptions"
                    :loading="loadingRotas"
                    :disabled="rotaDisabled"
                    item-title="title"
                    item-value="value"
                    variant="outlined"
                    clearable
                    :menu-props="{ maxHeight: 400 }"
                    @update:model-value="selecionarRota"
                  >
                    <template #prepend-inner>
                      <VIcon
                        icon="tabler-search"
                        class="me-1"
                      />
                    </template>

                    <template #item="{ props, item }">
                      <VListItem
                        v-bind="props"
                        :title="item.raw.title"
                        :subtitle="item.raw.subtitle"
                      >
                        <template #prepend>
                          <VAvatar
                            :color="item.raw.flgcd ? 'info' : 'primary'"
                            variant="tonal"
                            size="40"
                          >
                            <VIcon :icon="item.raw.flgcd ? 'tabler-building' : 'tabler-route'" />
                          </VAvatar>
                        </template>

                        <template #append>
                          <div class="d-flex gap-2">
                            <VChip
                              v-if="item.raw.flgretorno"
                              size="small"
                              color="warning"
                              variant="tonal"
                            >
                              <VIcon
                                icon="tabler-arrow-back"
                                start
                                size="14"
                              />
                              Retorno
                            </VChip>
                            <VChip
                              size="small"
                              color="secondary"
                              variant="tonal"
                            >
                              {{ item.raw.tempoviagem }} dias
                            </VChip>
                          </div>
                        </template>
                      </VListItem>
                    </template>

                    <template #append-inner>
                      <VIcon
                        v-if="verificaRota"
                        icon="tabler-circle-check"
                        color="success"
                      />
                    </template>

                    <template #no-data>
                      <VListItem>
                        <VListItemTitle class="text-center text-medium-emphasis">
                          <VIcon
                            icon="tabler-search-off"
                            class="me-2"
                          />
                          Digite pelo menos 2 caracteres para buscar
                        </VListItemTitle>
                      </VListItem>
                    </template>
                  </VAutocomplete>
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-6" />

            <!-- ETAPA 4: Datas -->
            <div>
              <h5 class="text-h5 mb-4">
                4. Período de Vigência
              </h5>
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="dataInicio"
                    label="Data Início"
                    type="date"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VTextField
                    v-model="dataFim"
                    label="Data Fim"
                    type="date"
                  />
                </VCol>
              </VRow>
            </div>
          </VCardText>

          <VCardActions class="justify-end">
            <VBtn
              variant="outlined"
              @click="resetar"
            >
              Limpar
            </VBtn>
          </VCardActions>
        </VCard>
      </VCol>

      <!-- Coluna lateral - Resumo -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardTitle>Resumo</VCardTitle>
          <VCardText>
            <div class="d-flex flex-column gap-4">
              <div>
                <p class="text-caption text-disabled mb-1">
                  Pacote
                </p>
                <p class="text-body-1 font-weight-medium">
                  {{ codpac || '-' }}
                </p>
              </div>

              <div>
                <p class="text-caption text-disabled mb-1">
                  Placa
                </p>
                <p class="text-body-1 font-weight-medium">
                  {{ placa || '-' }}
                </p>
              </div>

              <div>
                <p class="text-caption text-disabled mb-1">
                  Eixos
                </p>
                <p class="text-body-1 font-weight-medium">
                  {{ eixos }}
                </p>
              </div>

              <div v-if="nomRotSemParar">
                <p class="text-caption text-disabled mb-1">
                  Rota
                </p>
                <p class="text-body-2 font-weight-medium">
                  {{ nomRotSemParar }}
                </p>
              </div>

              <div v-if="valorViagem > 0">
                <p class="text-caption text-disabled mb-1">
                  Valor da Viagem
                </p>
                <p class="text-h5 text-success font-weight-bold">
                  R$ {{ valorViagem.toFixed(2) }}
                </p>
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- Checklist de progresso -->
        <VCard class="mt-4">
          <VCardTitle>Progresso</VCardTitle>
          <VCardText>
            <VList>
              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaPacote ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaPacote ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Pacote Validado</VListItemTitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaTransporte ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaTransporte ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Transporte Carregado</VListItemTitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaPlaca ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaPlaca ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Placa Validada</VListItemTitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaRota ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaRota ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Rota Selecionada</VListItemTitle>
              </VListItem>

              <VListItem>
                <template #prepend>
                  <VIcon
                    :icon="verificaValor ? 'tabler-circle-check' : 'tabler-circle'"
                    :color="verificaValor ? 'success' : 'default'"
                  />
                </template>
                <VListItemTitle>Preço Calculado</VListItemTitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Dialog: Confirmar Placa -->
    <VDialog
      v-model="showPlacaDialog"
      max-width="600"
    >
      <VCard>
        <VCardText class="pa-6">
          <!-- Header com ícone -->
          <div class="d-flex align-center mb-6">
            <VAvatar
              color="primary"
              variant="tonal"
              size="48"
              class="me-4"
            >
              <VIcon
                icon="tabler-truck"
                size="28"
              />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-1">
                Dados do Veículo
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Confirme as informações retornadas pelo SemParar
              </p>
            </div>
          </div>

          <VDivider class="mb-6" />

          <!-- Informações do veículo em formato lista elegante -->
          <VList class="mb-4">
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-car"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Descrição
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ descricaoVei }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-user"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Proprietário
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ proprietario }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-id-badge"
                  class="me-2"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Tag SemParar
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ tag }}
              </VListItemSubtitle>
            </VListItem>
          </VList>

          <!-- Eixos editável destacado -->
          <VCard
            variant="tonal"
            color="primary"
            class="pa-4"
          >
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-settings"
                  class="me-3"
                  size="24"
                />
                <div>
                  <div class="text-body-2 text-medium-emphasis">
                    Quantidade de Eixos
                  </div>
                  <div class="text-caption">
                    Ajuste se necessário
                  </div>
                </div>
              </div>
              <VTextField
                v-model.number="eixos"
                type="number"
                min="2"
                max="10"
                density="compact"
                style="max-width: 100px"
                variant="outlined"
              />
            </div>
          </VCard>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showPlacaDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            @click="confirmarPlaca"
          >
            <VIcon
              icon="tabler-check"
              start
            />
            Confirmar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog: Confirmar Preço -->
    <VDialog
      v-model="showPrecoDialog"
      max-width="650"
    >
      <VCard>
        <VCardText class="pa-6">
          <!-- Header com ícone -->
          <div class="d-flex align-center mb-6">
            <VAvatar
              color="success"
              variant="tonal"
              size="56"
              class="me-4"
            >
              <VIcon
                icon="tabler-cash"
                size="32"
              />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-1">
                Confirmar Compra de Viagem
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Revise os dados antes de finalizar
              </p>
            </div>
          </div>

          <VDivider class="mb-6" />

          <!-- Valor em destaque -->
          <VCard
            variant="tonal"
            color="success"
            class="pa-6 text-center mb-6"
          >
            <div class="text-body-2 text-medium-emphasis mb-2">
              Valor Total da Viagem
            </div>
            <h1 class="text-h1 font-weight-bold">
              R$ {{ valorViagem.toFixed(2) }}
            </h1>
          </VCard>

          <!-- Informações da rota -->
          <VList class="mb-4">
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-route"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Rota SemParar
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ nomRotSemParar }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon
                  icon="tabler-barcode"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Código da Rota
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ codRotaSemParar }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem v-if="numeroViagem">
              <template #prepend>
                <VIcon
                  icon="tabler-receipt"
                  class="me-2"
                  color="primary"
                />
              </template>
              <VListItemTitle class="text-body-2 text-medium-emphasis">
                Número da Viagem
              </VListItemTitle>
              <VListItemSubtitle class="text-h6 mt-1">
                {{ numeroViagem }}
              </VListItemSubtitle>
            </VListItem>
          </VList>

          <!-- Alerta importante -->
          <VAlert
            type="warning"
            variant="tonal"
            prominent
          >
            <VAlertTitle class="mb-2">
              <VIcon
                icon="tabler-alert-triangle"
                class="me-2"
              />
              Atenção
            </VAlertTitle>
            Se cancelar após esta etapa, será necessário excluir manualmente a rota temporária no sistema SemParar.
          </VAlert>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            color="error"
            @click="showPrecoDialog = false"
          >
            <VIcon
              icon="tabler-x"
              start
            />
            Cancelar
          </VBtn>
          <VBtn
            color="success"
            variant="elevated"
            size="large"
            @click="comprar"
          >
            <VIcon
              icon="tabler-check"
              start
            />
            Confirmar Compra
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Painel de Debug Funcional (Modal) -->
    <VDialog
      v-model="showDebugPanel"
      max-width="1200"
      scrollable
    >
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center bg-warning">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-bug" />
            <span>Painel de Debug - Compra de Viagem SemParar</span>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="showDebugPanel = false"
          />
        </VCardTitle>

        <VCardText class="pa-4">
          <!-- Estatísticas de Debug -->
          <div class="mb-4">
            <h6 class="text-h6 mb-3">📊 Estatísticas</h6>
            <VRow dense>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-blue-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-primary">{{ debugStats.totalRequests }}</div>
                  <div class="text-caption">Total de Requests</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-green-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-success">{{ debugStats.successfulRequests }}</div>
                  <div class="text-caption">Requests Bem-Sucedidos</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-red-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-error">{{ debugStats.failedRequests }}</div>
                  <div class="text-caption">Requests Falhados</div>
                </div>
              </VCol>
              <VCol cols="6" md="3">
                <div class="pa-3 bg-purple-lighten-5 rounded text-center">
                  <div class="text-h5 font-weight-bold text-purple">{{ debugStats.cacheHits }}</div>
                  <div class="text-caption">Cache Hits</div>
                </div>
              </VCol>
            </VRow>
            <div class="mt-2 text-caption text-medium-emphasis">
              Última atualização: {{ debugStats.lastUpdate ? debugStats.lastUpdate.toLocaleTimeString('pt-BR') : 'N/A' }}
            </div>
          </div>

          <VDivider class="my-4" />

          <!-- Estado Atual do Sistema -->
          <div class="mb-4">
            <h6 class="text-h6 mb-3">🎯 Estado do Sistema</h6>
            <VRow dense>
              <VCol cols="12" md="6">
                <VCard variant="tonal">
                  <VCardText>
                    <div class="text-caption text-medium-emphasis mb-2">Dados do Pacote</div>
                    <div><strong>Código:</strong> {{ codpac || 'N/A' }}</div>
                    <div><strong>Descrição:</strong> {{ descPacote || 'N/A' }}</div>
                    <div><strong>Transportador:</strong> {{ nomeTransporte || 'N/A' }}</div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12" md="6">
                <VCard variant="tonal">
                  <VCardText>
                    <div class="text-caption text-medium-emphasis mb-2">Rota & Placa</div>
                    <div><strong>Placa:</strong> {{ placa || 'N/A' }}</div>
                    <div><strong>Rota ID:</strong> {{ rotaId || 'N/A' }}</div>
                    <div><strong>Valor:</strong> {{ valorViagem > 0 ? `R$ ${valorViagem.toFixed(2)}` : 'N/A' }}</div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </div>

          <VDivider class="my-4" />

          <!-- Logs -->
          <div>
            <div class="d-flex justify-space-between align-center mb-3">
              <h6 class="text-h6">📋 Logs do Sistema ({{ debugLogs.length }})</h6>
              <VBtn
                size="small"
                variant="tonal"
                color="error"
                prepend-icon="tabler-trash"
                @click="clearDebugLogs"
              >
                Limpar Logs
              </VBtn>
            </div>

            <div class="debug-logs-container">
              <VCard
                v-for="(log, index) in debugLogs"
                :key="index"
                :color="log.level === 'error' ? 'error' : log.level === 'warn' ? 'warning' : log.level === 'success' ? 'success' : 'default'"
                variant="tonal"
                class="mb-2"
              >
                <VCardText class="py-2">
                  <div class="d-flex align-center gap-2 mb-1">
                    <VChip :color="log.level === 'error' ? 'error' : log.level === 'warn' ? 'warning' : log.level === 'success' ? 'success' : 'info'" size="x-small">
                      {{ log.level }}
                    </VChip>
                    <VChip color="default" size="x-small">{{ log.category }}</VChip>
                    <span class="text-caption text-medium-emphasis">{{ new Date(log.timestamp).toLocaleTimeString('pt-BR') }}</span>
                  </div>
                  <div class="text-body-2">{{ log.message }}</div>
                  <div v-if="log.data" class="mt-2">
                    <VExpansionPanels>
                      <VExpansionPanel>
                        <VExpansionPanelTitle class="text-caption py-1">Ver Dados</VExpansionPanelTitle>
                        <VExpansionPanelText>
                          <pre class="text-caption" style="white-space: pre-wrap; max-height: 200px; overflow: auto">{{ JSON.stringify(log.data, null, 2) }}</pre>
                        </VExpansionPanelText>
                      </VExpansionPanel>
                    </VExpansionPanels>
                  </div>
                </VCardText>
              </VCard>

              <div v-if="debugLogs.length === 0" class="text-center text-medium-emphasis py-8">
                Nenhum log ainda. As ações serão registradas aqui.
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Snackbar (Vuexy style toast) -->
    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      location="top end"
      variant="flat"
      :timeout="4000"
    >
      {{ snackbarText }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.debug-logs-container {
  max-height: 500px;
  overflow-y: auto;
}

.debug-logs-container::-webkit-scrollbar {
  width: 8px;
}

.debug-logs-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.debug-logs-container::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 4px;
}

.debug-logs-container::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>
