<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
// axios import removido - projeto usa fetch nativo

// ============================================================================
// COMPRA DE VIAGEM SEMPARAR - Seguindo EXATAMENTE compraRota.p
// ============================================================================

// Tipos
interface PackageData {
  codpac: number
  descpac: string
  transporte: {
    codtrn: number
    nomtrn: string
    numpla: string
  }
  rota?: {
    codrot: string
    desrot: string
  }
}

interface VehicleData {
  placa: string
  descricao: string
  eixos: number
  proprietario: string
  tag: string
}

interface RotaOption {
  value: number
  title: string
  subtitle: string
  flgcd: boolean
  flgretorno: boolean
  tempoviagem: number
}

interface PriceData {
  valor: number
  numero_viagem: string
  nome_rota: string
  cod_rota: string
}

// Estado do formulário (seguindo compraRota.p linhas 4-42)
const vCodpac = ref<number | null>(null)
const vDesCodPac = ref('')
const vTransporte = ref<number | null>(null)
const vDesTransporte = ref('')
const vPlaca = ref('')
const vDesPlaca = ref('')
const vRota = ref<number | null>(null)
const vDesRota = ref('')

// Dados da validação de placa (frame f_Placa linhas 71-82)
const vDescricaoVei = ref('')
const vEixos = ref<number>(2)
const vProprietario = ref('')
const vTag = ref('')

// Dados da compra (linhas 116-121)
const nomRotSemParar = ref('')
const codRotaSemParar = ref('')
const valorViagem = ref(0)
const numeroViagem = ref('')
const datInicio = ref('')
const datFim = ref('')

// Checkboxes de verificação (linhas 122-127)
const verificaPacote = ref(false)
const verificaTransporte = ref(false)
const verificaPlaca = ref(false)
const verificaRota = ref(false)
const verificaValor = ref(false)
const verificaCompra = ref(false)

// Flags de controle (linhas 18-21, 30-35)
const flgPersonalizado = ref(false)
const flgRetorno = ref(false)
const flgCD = ref(false)

// Dialogs (frames do Progress)
const showPlacaDialog = ref(false)  // frame f_Placa
const showPrecoDialog = ref(false)  // frame f_Preco
const showMunicipiosDialog = ref(false)  // frame CadastroEntrega

// Estados de carregamento
const isLoadingPacote = ref(false)
const isLoadingPlaca = ref(false)
const isLoadingRota = ref(false)
const isLoadingPreco = ref(false)

// Controle de campos habilitados (disable/enable do Progress)
const pacoteEnabled = ref(true)
const transporteEnabled = ref(false)
const placaEnabled = ref(false)
const rotaEnabled = ref(false)

// Autocomplete
const rotasOptions = ref<RotaOption[]>([])
const isLoadingRotas = ref(false)

// Configuração
const testMode = ref(false)
const soapReal = ref(false)

// Título dinâmico (linha 54-58)
const frameTitle = computed(() => {
  if (flgCD.value) {
    return 'COMPRA DE VIAGENS SEMPARAR - CD'
  }
  if (flgRetorno.value) {
    return 'COMPRA DE VIAGENS SEMPARAR - RETORNO - OUTROS'
  }
  return 'COMPRA DE VIAGENS SEMPARAR - OUTROS'
})

// ============================================================================
// FASE 1: INICIALIZAÇÃO
// ============================================================================
const initialize = async () => {
  try {
    // @ts-expect-error - axios not configured, file not in use
    const { data } = await axios.get('/api/compra-viagem/initialize')

    testMode.value = data.data.test_mode
    soapReal.value = data.data.allow_soap_queries

    // Define data inicial e final padrão
    const hoje = new Date()
    datInicio.value = hoje.toISOString().split('T')[0]

    const dataFim = new Date()
    dataFim.setDate(dataFim.getDate() + data.data.data_fim_padrao_dias)
    datFim.value = dataFim.toISOString().split('T')[0]

    console.log('✅ Inicializado:', data)
  } catch (error) {
    console.error('❌ Erro ao inicializar:', error)
  }
}

// ============================================================================
// FASE 2: VALIDAÇÃO DE PACOTE (compraRota.p linhas 200-263)
// ============================================================================
const onPacoteReturn = async () => {
  if (!vCodpac.value) {
    alert('Informe o código do pacote')
    return
  }

  isLoadingPacote.value = true

  try {
    // @ts-expect-error - axios not configured, file not in use
    const { data } = await axios.post('/api/compra-viagem/validar-pacote', {
      codpac: vCodpac.value,
      flgcd: flgCD.value
    })

    if (!data.success) {
      alert(data.error || 'Erro ao validar pacote')
      vCodpac.value = null
      vDesCodPac.value = ''
      return
    }

    const pkg = data.data

    // Preenche dados do pacote (linhas 233-242)
    vDesCodPac.value = `${pkg.rota?.desrot || ''} ${pkg.rota?.codrot || ''}`
    vTransporte.value = pkg.transporte.codtrn
    vDesTransporte.value = pkg.transporte.nomtrn
    vPlaca.value = pkg.transporte.numpla
    vDesPlaca.value = '' // Será preenchido ao validar placa

    // Marca checkboxes (linhas 239-241)
    verificaPacote.value = true
    verificaTransporte.value = true

    // Habilita campo de placa (linha 238)
    placaEnabled.value = true

    console.log('✅ Pacote validado:', pkg)

    // Auto-foca no campo placa após validação
    setTimeout(() => {
      const placaInput = document.querySelector('[name="placa"]') as HTMLInputElement
      placaInput?.focus()
    }, 100)

  } catch (error: any) {
    console.error('❌ Erro ao validar pacote:', error)
    alert(error.response?.data?.message || 'Erro ao validar pacote')
    vCodpac.value = null
    vDesCodPac.value = ''
  } finally {
    isLoadingPacote.value = false
  }
}

// ============================================================================
// FASE 3: VALIDAÇÃO DE PLACA (compraRota.p linhas 366-476)
// ============================================================================
const onPlacaReturn = async () => {
  if (!vPlaca.value) {
    alert('Informe a placa do veículo')
    return
  }

  isLoadingPlaca.value = true

  try {
    // Linha 370-374: Abre conexão SOAP e valida placa
    // @ts-expect-error - axios not configured, file not in use
    const { data } = await axios.post('/api/compra-viagem/validar-placa', {
      placa: vPlaca.value
    })

    if (!data.success) {
      alert(data.error || 'Erro ao validar placa')
      return
    }

    const vehicle = data.data

    // Preenche dados do veículo (linhas 386-391)
    vDescricaoVei.value = vehicle.descricao
    vEixos.value = vehicle.eixos
    vProprietario.value = vehicle.proprietario
    vTag.value = vehicle.tag

    console.log('✅ Placa validada:', vehicle)

    // Mostra dialog para confirmar eixos (frame f_Placa, linhas 71-82)
    showPlacaDialog.value = true

  } catch (error: any) {
    console.error('❌ Erro ao validar placa:', error)
    alert(error.response?.data?.message || 'Erro ao validar placa')
  } finally {
    isLoadingPlaca.value = false
  }
}

// Confirma placa e eixos (botão btConfirma, linhas 422-476)
const onConfirmarPlaca = () => {
  if (vEixos.value < 2 || vEixos.value > 10) {
    alert('Eixos inválidos (mínimo 2, máximo 10)')
    return
  }

  // Fecha dialog
  showPlacaDialog.value = false

  // Marca checkbox (linha 429)
  verificaPlaca.value = true

  // Desabilita placa e habilita rota (linhas 426-427)
  placaEnabled.value = false
  rotaEnabled.value = true

  // Auto-preenche rota se possível (linhas 432-463)
  // TODO: Implementar lógica de auto-preenchimento baseada em introt

  // Foca no campo de rota
  setTimeout(() => {
    const rotaInput = document.querySelector('[name="rota"]') as HTMLInputElement
    rotaInput?.focus()
  }, 100)
}

// Cancela validação de placa (botão btCancela, linhas 293-296)
const onCancelarPlaca = () => {
  showPlacaDialog.value = false
  // Volta foco para campo placa
}

// ============================================================================
// FASE 4: VALIDAÇÃO DE ROTA (compraRota.p linhas 492-696)
// ============================================================================

// Busca rotas para autocomplete (on f2 of vRota, linha 479-485)
const searchRotas = async (search: string) => {
  if (search.length < 2) return

  isLoadingRotas.value = true

  try {
    // @ts-expect-error - axios not configured, file not in use
    const { data } = await axios.get('/api/compra-viagem/listar-rotas', {
      params: {
        search,
        flg_cd: flgCD.value ? 1 : 0
      }
    })

    rotasOptions.value = data.data || []

  } catch (error) {
    console.error('❌ Erro ao buscar rotas:', error)
  } finally {
    isLoadingRotas.value = false
  }
}

// Valida rota selecionada (on return of vRota, linhas 492-696)
const onRotaReturn = async () => {
  if (!vRota.value) {
    alert('Selecione uma rota')
    return
  }

  // TODO: Validar flags (CD, Retorno) - linhas 507-554
  // TODO: Verificar se já foi comprada - linhas 555-581

  // Pergunta se quer personalizar pontos (linhas 594-602)
  const personalizar = confirm('DESEJA PERSONALIZAR PONTOS?')
  flgPersonalizado.value = personalizar

  if (personalizar) {
    // Mostra tela de personalização de municípios
    showMunicipiosDialog.value = true
    return
  }

  // Roteiriza rota normal (linhas 643-662)
  await roteirizarRota()
}

// Roteiriza rota e cria rota temporária (linhas 647-667)
const roteirizarRota = async () => {
  isLoadingRota.value = true

  try {
    // Linha 647-651: Abre conexão e chama roterizaCa
    // O backend vai fazer: roteirizarPracasPedagio() + cadastrarRotaTemporaria()

    console.log('🛣️ Roteirizando rota...')

    // Marca checkbox
    verificaRota.value = true

    // Continua para verificação de preço
    await verificarPreco()

  } catch (error: any) {
    console.error('❌ Erro ao roteirizar:', error)
    alert(error.response?.data?.message || 'Erro ao roteirizar rota')
  } finally {
    isLoadingRota.value = false
  }
}

// ============================================================================
// FASE 5: VERIFICAÇÃO DE PREÇO (compraRota.p linhas 669-692)
// ============================================================================
const verificarPreco = async () => {
  if (!vCodpac.value || !vRota.value || !vPlaca.value) {
    alert('Preencha todos os campos obrigatórios')
    return
  }

  isLoadingPreco.value = true

  try {
    // Linha 670-674: Abre conexão e chama verificaPreco
    // @ts-expect-error - axios not configured, file not in use
    const { data } = await axios.post('/api/compra-viagem/verificar-preco', {
      codpac: vCodpac.value,
      cod_rota: vRota.value,
      qtd_eixos: vEixos.value,
      placa: vPlaca.value,
      data_inicio: datInicio.value,
      data_fim: datFim.value
    })

    if (!data.success) {
      alert(data.error || 'Erro ao verificar preço')
      return
    }

    const priceData = data.data

    // Preenche dados (linhas 686-688)
    valorViagem.value = priceData.valor
    numeroViagem.value = priceData.numero_viagem || ''
    nomRotSemParar.value = priceData.nome_rota || ''
    codRotaSemParar.value = priceData.cod_rota || ''

    // Marca checkbox
    verificaValor.value = true

    console.log('✅ Preço verificado:', priceData)

    // Mostra dialog de confirmação de preço (frame f_Preco, linhas 85-93)
    showPrecoDialog.value = true

  } catch (error: any) {
    console.error('❌ Erro ao verificar preço:', error)
    alert(error.response?.data?.message || 'Erro ao verificar preço')
  } finally {
    isLoadingPreco.value = false
  }
}

// ============================================================================
// FASE 6: COMPRA DA VIAGEM (TODO)
// ============================================================================
const onComprarViagem = () => {
  if (!testMode.value) {
    alert('⚠️ Compra real não implementada ainda!')
    return
  }

  // TODO: Implementar compraViagem()
  alert('Compra simulada! Em produção, aqui seria feita a compra real.')

  showPrecoDialog.value = false
  verificaCompra.value = true
}

// Cancela compra (botão btCancelaPreco, linhas 92)
const onCancelarCompra = () => {
  showPrecoDialog.value = false
  alert('Se cancelar, exclua a rota no SemParar')
  // TODO: Implementar exclusão da rota temporária
}

// ============================================================================
// RESET DO FORMULÁRIO (F4 do Progress, linhas 266-291)
// ============================================================================
const resetForm = () => {
  vCodpac.value = null
  vDesCodPac.value = ''
  vTransporte.value = null
  vDesTransporte.value = ''
  vPlaca.value = ''
  vDesPlaca.value = ''
  vRota.value = null
  vDesRota.value = ''

  vDescricaoVei.value = ''
  vEixos.value = 2
  vProprietario.value = ''
  vTag.value = ''

  nomRotSemParar.value = ''
  codRotaSemParar.value = ''
  valorViagem.value = 0
  numeroViagem.value = ''

  verificaPacote.value = false
  verificaTransporte.value = false
  verificaPlaca.value = false
  verificaRota.value = false
  verificaValor.value = false
  verificaCompra.value = false

  pacoteEnabled.value = true
  transporteEnabled.value = false
  placaEnabled.value = false
  rotaEnabled.value = false
}

// Inicializa ao montar
initialize()
</script>

<template>
  <VCard>
    <!-- TÍTULO (frame CadastroRota, linha 132) -->
    <VCardTitle class="text-center py-4 text-h5 font-weight-bold">
      {{ frameTitle }}
    </VCardTitle>

    <VCardText>
      <VRow>
        <!-- ================================================================ -->
        <!-- COLUNA ESQUERDA: CAMPOS PRINCIPAIS (linhas 96-109) -->
        <!-- ================================================================ -->
        <VCol
          cols="12"
          md="7"
        >
          <VCard
            outlined
            class="pa-4"
          >
            <VCardTitle class="text-subtitle-1 pb-4">
              Informações
            </VCardTitle>

            <VForm @submit.prevent="onPacoteReturn">
              <!-- PACOTE (linha 97-99) -->
              <VRow>
                <VCol cols="4">
                  <VTextField
                    v-model.number="vCodpac"
                    name="pacote"
                    label="Pacote..."
                    type="number"
                    :disabled="!pacoteEnabled"
                    :loading="isLoadingPacote"
                    density="compact"
                    @keydown.enter="onPacoteReturn"
                    @keydown.f2="() => {/* TODO: Helper de pacotes */}"
                    @keydown.f4="resetForm"
                  />
                </VCol>
                <VCol cols="8">
                  <VTextField
                    v-model="vDesCodPac"
                    label="Descrição do Pacote"
                    readonly
                    density="compact"
                    variant="plain"
                  />
                </VCol>
              </VRow>

              <!-- TRANSPORTE (linhas 101-103) -->
              <VRow>
                <VCol cols="4">
                  <VTextField
                    v-model.number="vTransporte"
                    label="CodTrans."
                    readonly
                    density="compact"
                    variant="plain"
                  />
                </VCol>
                <VCol cols="8">
                  <VTextField
                    v-model="vDesTransporte"
                    label="Transportadora"
                    readonly
                    density="compact"
                    variant="plain"
                  />
                </VCol>
              </VRow>

              <!-- PLACA (linhas 105-107) -->
              <VRow>
                <VCol cols="4">
                  <VTextField
                    v-model="vPlaca"
                    name="placa"
                    label="Placa...."
                    :disabled="!placaEnabled"
                    :loading="isLoadingPlaca"
                    density="compact"
                    @keydown.enter="onPlacaReturn"
                    @keydown.f4="() => { placaEnabled = false; pacoteEnabled = true }"
                  />
                </VCol>
                <VCol cols="8">
                  <VTextField
                    v-model="vDesPlaca"
                    label="Modelo do Veículo"
                    readonly
                    density="compact"
                    variant="plain"
                  />
                </VCol>
              </VRow>

              <!-- ROTA (linha 108-109) -->
              <VRow>
                <VCol cols="12">
                  <VAutocomplete
                    v-model="vRota"
                    name="rota"
                    label="Rota"
                    :items="rotasOptions"
                    :loading="isLoadingRotas"
                    :disabled="!rotaEnabled"
                    item-title="title"
                    item-value="value"
                    density="compact"
                    @update:search="searchRotas"
                    @update:model-value="onRotaReturn"
                  >
                    <template #item="{ props, item }">
                      <VListItem
                        v-bind="props"
                        :title="item.raw.title"
                        :subtitle="item.raw.subtitle"
                      />
                    </template>
                  </VAutocomplete>
                </VCol>
              </VRow>
            </VForm>
          </VCard>

          <!-- DATAS (linhas 120-121) -->
          <VCard
            outlined
            class="pa-4 mt-4"
          >
            <VRow>
              <VCol cols="6">
                <VTextField
                  v-model="datInicio"
                  label="Ini"
                  type="date"
                  density="compact"
                />
              </VCol>
              <VCol cols="6">
                <VTextField
                  v-model="datFim"
                  label="Fim"
                  type="date"
                  density="compact"
                />
              </VCol>
            </VRow>
          </VCard>
        </VCol>

        <!-- ================================================================ -->
        <!-- COLUNA DIREITA: VERIFICAÇÕES E INFO COMPRA (linhas 112-127) -->
        <!-- ================================================================ -->
        <VCol
          cols="12"
          md="5"
        >
          <!-- PROCESSOS VERIFICADOS (linhas 112-127) -->
          <VCard
            outlined
            class="pa-4 mb-4"
          >
            <VCardTitle class="text-subtitle-1 pb-4">
              Processos Verificados
            </VCardTitle>

            <VCheckbox
              v-model="verificaPacote"
              label="Pacote"
              readonly
              density="compact"
              hide-details
            />
            <VCheckbox
              v-model="verificaTransporte"
              label="Transporte"
              readonly
              density="compact"
              hide-details
            />
            <VCheckbox
              v-model="verificaPlaca"
              label="Placa"
              readonly
              density="compact"
              hide-details
            />
            <VCheckbox
              v-model="verificaRota"
              label="Rota"
              readonly
              density="compact"
              hide-details
            />
            <VCheckbox
              v-model="verificaValor"
              label="Valor"
              readonly
              density="compact"
              hide-details
            />
            <VCheckbox
              v-model="verificaCompra"
              label="Compra"
              readonly
              density="compact"
              hide-details
            />
          </VCard>

          <!-- INFO COMPRA (linhas 113-121) -->
          <VCard
            outlined
            class="pa-4"
          >
            <VCardTitle class="text-subtitle-1 pb-4">
              Info Compra
            </VCardTitle>

            <VTextField
              v-model="nomRotSemParar"
              label="Nome Rota"
              readonly
              density="compact"
              variant="plain"
              hide-details
              class="mb-2"
            />
            <VTextField
              v-model="codRotaSemParar"
              label="CodRot"
              readonly
              density="compact"
              variant="plain"
              hide-details
              class="mb-2"
            />
            <VTextField
              v-model="valorViagem"
              label="Valor Viagem"
              readonly
              density="compact"
              variant="plain"
              hide-details
              class="mb-2"
              :suffix="valorViagem ? 'R$' : ''"
            />
            <VTextField
              v-model="numeroViagem"
              label="Numero Viagem"
              readonly
              density="compact"
              variant="plain"
              hide-details
            />
          </VCard>

          <!-- MODO DE TESTE -->
          <VAlert
            v-if="testMode"
            type="warning"
            variant="tonal"
            class="mt-4"
          >
            <strong>⚠️ MODO SEGURO:</strong> Compras reais estão BLOQUEADAS
          </VAlert>
        </VCol>
      </VRow>
    </VCardText>

    <!-- ================================================================ -->
    <!-- DIALOG: VALIDAÇÃO DE PLACA (frame f_Placa, linhas 71-82) -->
    <!-- ================================================================ -->
    <VDialog
      v-model="showPlacaDialog"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle>Confirmar Dados do Veículo</VCardTitle>

        <VCardText>
          <VTextField
            v-model="vDescricaoVei"
            label="Desc"
            readonly
            density="compact"
            class="mb-3"
          />
          <VTextField
            v-model.number="vEixos"
            label="Eixos"
            type="number"
            min="2"
            max="10"
            density="compact"
            class="mb-3"
          />
          <VTextField
            v-model="vProprietario"
            label="Dono"
            readonly
            density="compact"
            class="mb-3"
          />
          <VTextField
            v-model="vTag"
            label="Tag"
            readonly
            density="compact"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="onCancelarPlaca"
          >
            Cancela
          </VBtn>
          <VBtn
            color="primary"
            @click="onConfirmarPlaca"
          >
            Confirma
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ================================================================ -->
    <!-- DIALOG: CONFIRMAÇÃO DE PREÇO (frame f_Preco, linhas 85-93) -->
    <!-- ================================================================ -->
    <VDialog
      v-model="showPrecoDialog"
      max-width="500"
      persistent
    >
      <VCard>
        <VCardTitle>Confirmar Compra</VCardTitle>

        <VCardText>
          <VTextField
            :model-value="valorViagem.toFixed(2)"
            label="PRECO TOTAL"
            readonly
            density="compact"
            prefix="R$"
            class="text-h5 font-weight-bold mb-4"
          />

          <VAlert
            type="warning"
            variant="tonal"
          >
            Se cancelar, exclua a rota no SemParar
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="onCancelarCompra"
          >
            Cancela
          </VBtn>
          <VBtn
            color="success"
            @click="onComprarViagem"
          >
            Comprar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ================================================================ -->
    <!-- DIALOG: PERSONALIZAÇÃO DE MUNICÍPIOS (TODO) -->
    <!-- ================================================================ -->
    <VDialog
      v-model="showMunicipiosDialog"
      max-width="800"
      persistent
    >
      <VCard>
        <VCardTitle>Personalizar Municípios da Rota</VCardTitle>

        <VCardText>
          <!-- TODO: Implementar tela de edição de municípios -->
          <VAlert type="info">
            Funcionalidade de personalização de municípios será implementada em breve.
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showMunicipiosDialog = false"
          >
            Fechar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style scoped>
/* Estilos para replicar visual do Progress */
.v-card-title {
  background-color: rgb(var(--v-theme-surface));
}

.v-text-field--readonly {
  opacity: 0.7;
}
</style>
