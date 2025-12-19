<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { VpoEmissaoFormData, CustoVpo, PracaPedagio } from '../types'
import { apiFetch, getApiUrl } from '@/config/api'

// Props & Emits
const props = defineProps<{
  formData: VpoEmissaoFormData
}>()

const emit = defineEmits<{
  'update:formData': [value: VpoEmissaoFormData]
  'stepComplete': [complete: boolean]
  'emissaoRealizada': [result: any]
  'novaEmissao': []
}>()

// State
const calculando = ref(false)
const emitindo = ref(false)
const consultandoResultado = ref(false)
const dataInicio = ref(props.formData.periodo.dataInicio)
const dataFim = ref(props.formData.periodo.dataFim)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const emissaoResult = ref<any>(null)

// Roteirizador state (NDD Cargo)
const roteirizadorGuid = ref<string | null>(null)
const pracasPedagio = ref<PracaPedagio[]>([])
const roteirizadorStatus = ref<'idle' | 'processing' | 'completed' | 'failed'>('idle')

// Emissão status para timeline visual
const emissaoStatus = ref<'idle' | 'enviando' | 'aguardando' | 'consultando' | 'concluido' | 'erro'>('idle')
const emissaoUuid = ref<string | null>(null)
const emissaoTentativas = ref(0)

// Form para campos faltantes
const savingMissingFields = ref(false)
const missingFieldsForm = ref({
  contato_celular: '',
  contato_email: '',
  antt_validade: '',
  antt_rntrc: '',
  condutor_nome_mae: '',
  condutor_data_nascimento: '',
  condutor_rg: '',
})

// Computed
const pacote = computed(() => props.formData.pacote.pacote)
const transportador = computed(() => props.formData.pacote.transportador)
const motorista = computed(() => props.formData.motorista.motoristaSelecionado)
const veiculo = computed(() => props.formData.veiculo.veiculo)
const rota = computed(() => props.formData.rota.rota)
const municipios = computed(() => props.formData.rota.municipios)
const custo = computed(() => props.formData.custo.custo)
const custoCalculado = computed(() => props.formData.custo.calculado)
const eixos = computed(() => veiculo.value?.eixos || props.formData.periodo.eixos || 2)

// Categoria de pedágio baseada no número de eixos (NDD Cargo)
// Mapeamento correto: 5=Caminhão leve (2 eixos), 6=Caminhão médio (3-5 eixos), 7=Caminhão pesado (6+ eixos)
const categoriaPedagio = computed(() => {
  const numEixos = eixos.value
  if (numEixos <= 2) return 5 // Caminhão leve (2 eixos)
  if (numEixos <= 5) return 6 // Caminhão médio (3-5 eixos)
  return 7 // Caminhão pesado (6+ eixos)
})

const isStepValid = computed(() => {
  return custoCalculado.value && !emitindo.value
})

const canCalculate = computed(() => {
  return dataInicio.value && dataFim.value && rota.value && veiculo.value && municipios.value.length >= 2
})

const canEmit = computed(() => {
  // Permitir emissão mesmo com 0 praças (rota sem pedágios)
  // A condição é apenas: custo calculado (mesmo que seja 0) e não estar emitindo
  return custoCalculado.value && custo.value !== null && !emitindo.value
})

const emissaoFinalizada = computed(() => {
  return props.formData.status === 'concluido'
})

// CNPJ do transportador (para NDD Cargo)
const cnpjTransportador = computed(() => {
  const doc = transportador.value?.cpf_cnpj || ''
  // Remover formatação, manter apenas dígitos
  return doc.replace(/\D/g, '')
})

// Campos faltantes do transportador
const camposFaltantes = computed(() => transportador.value?.campos_faltantes || [])
const temCamposFaltantes = computed(() => camposFaltantes.value.length > 0)

// Labels amigáveis para campos
const camposLabels: Record<string, string> = {
  contato_celular: 'Celular',
  contato_email: 'E-mail',
  antt_validade: 'Validade RNTRC',
  antt_rntrc: 'RNTRC',
  condutor_nome_mae: 'Nome da Mãe',
  condutor_data_nascimento: 'Data de Nascimento',
  condutor_rg: 'RG',
}

const getCampoLabel = (campo: string): string => camposLabels[campo] || campo

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Methods

/**
 * Monta os CEPs dos pontos de parada a partir dos municípios da rota
 */
const montarPontosParada = () => {
  const pontos: { origem: string; destino: string; [key: string]: string } = {
    origem: '',
    destino: ''
  }

  if (municipios.value.length >= 2) {
    // Primeiro município = origem
    const primeiro = municipios.value[0]
    pontos.origem = primeiro.cep || primeiro.cdibge?.toString().padEnd(8, '0') || '00000000'

    // Último município = destino
    const ultimo = municipios.value[municipios.value.length - 1]
    pontos.destino = ultimo.cep || ultimo.cdibge?.toString().padEnd(8, '0') || '00000000'

    // Municípios intermediários como waypoints
    for (let i = 1; i < municipios.value.length - 1; i++) {
      const mun = municipios.value[i]
      pontos[`waypoint_${i}`] = mun.cep || mun.cdibge?.toString().padEnd(8, '0') || '00000000'
    }
  }

  return pontos
}

/**
 * PASSO 1: Calcular custo via NDD Cargo Roteirizador
 * Retorna as praças de pedágio e o valor total
 *
 * NOTA: Se o custo já foi calculado no Step 4, não precisa recalcular!
 * Apenas usar os dados existentes em formData.custo
 */
const calcularCusto = async () => {
  // Se já foi calculado no Step 4, apenas usar os dados existentes
  if (props.formData.custo.calculado && props.formData.custo.custo) {
    console.log('Custo já calculado no Step 4, usando dados existentes')
    roteirizadorStatus.value = 'completed'

    // Inicializar praças se ainda não foram
    if (pracasPedagio.value.length === 0) {
      const pracasExistentes = props.formData.custo.custo.pedagios || props.formData.rota.pracas || []
      pracasPedagio.value = pracasExistentes.map((p: any) => ({
        codigo: p.cnp || p.codigo || p.codigoPraca,
        cnp: p.cnp || p.codigo || p.codigoPraca,
        nome: p.nome || p.nomePraca,
        rodovia: p.rodovia || p.localizacao,
        km: p.km,
        valor: p.valor || p.valorPedagio || 0,
        sentido: p.sentido,
        concessionaria: p.concessionaria,
      }))
    }

    successMessage.value = 'Custo já calculado! Pronto para emissão.'
    return
  }

  if (!canCalculate.value) return

  calculando.value = true
  errorMessage.value = null
  roteirizadorStatus.value = 'processing'

  try {
    const pontosParada = montarPontosParada()

    // Chamar API NDD Cargo - Roteirizador
    const response = await apiFetch(getApiUrl(`/ndd-cargo/roteirizador`), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        cnpj_empresa: cnpjTransportador.value,
        cnpj_contratante: cnpjTransportador.value,
        categoria_pedagio: categoriaPedagio.value,
        pontos_parada: pontosParada,
        tipo_rota_padrao: 1,
        evitar_pedagogios: false,
        priorizar_rodovias: true,
        tipo_rota: 1,
        tipo_veiculo: 2, // 2 = Caminhão (valores válidos: 1=passeio, 2=caminhão, 3=ônibus, 4=caminhão trator)
        retornar_trecho: false,
      }),
    })

    const data = await response.json()

    if (data.success) {
      // Resultado síncrono - praças retornadas diretamente
      processarResultadoRoteirizador(data.data)
    } else if (response.status === 202 && data.guid) {
      // Resultado assíncrono - precisa polling
      roteirizadorGuid.value = data.guid
      await aguardarResultadoRoteirizador(data.guid)
    } else {
      errorMessage.value = data.message || 'Erro ao calcular rota no NDD Cargo'
      roteirizadorStatus.value = 'failed'
    }
  } catch (error: any) {
    console.error('Erro ao calcular custo NDD Cargo:', error)
    errorMessage.value = error.message || 'Erro ao conectar com NDD Cargo'
    roteirizadorStatus.value = 'failed'
  } finally {
    calculando.value = false
  }
}

/**
 * Aguarda resultado assíncrono do roteirizador (polling)
 */
const aguardarResultadoRoteirizador = async (guid: string, tentativas = 0) => {
  if (tentativas >= 30) {
    errorMessage.value = 'Timeout aguardando resultado do roteirizador'
    roteirizadorStatus.value = 'failed'
    return
  }

  consultandoResultado.value = true

  try {
    // Aguardar 2 segundos entre consultas
    await new Promise(resolve => setTimeout(resolve, 2000))

    const response = await apiFetch(getApiUrl(`/ndd-cargo/resultado/${guid}`))
    const data = await response.json()

    if (data.success && data.data) {
      processarResultadoRoteirizador(data.data)
    } else if (response.status === 202) {
      // Ainda processando - continuar polling
      await aguardarResultadoRoteirizador(guid, tentativas + 1)
    } else {
      errorMessage.value = data.message || 'Erro ao consultar resultado'
      roteirizadorStatus.value = 'failed'
    }
  } catch (error: any) {
    console.error('Erro ao consultar resultado:', error)
    errorMessage.value = error.message || 'Erro ao consultar resultado'
    roteirizadorStatus.value = 'failed'
  } finally {
    consultandoResultado.value = false
  }
}

/**
 * Processa resultado do roteirizador e atualiza estado
 */
const processarResultadoRoteirizador = (resultado: any) => {
  roteirizadorStatus.value = 'completed'

  // Extrair praças de pedágio
  // IMPORTANTE: O CNP vem do NDD Cargo no campo 'cnp', é obrigatório para emissão VPO
  const pracas: PracaPedagio[] = (resultado.pracas_pedagio || resultado.pracasPedagio || []).map((p: any) => ({
    codigo: p.cnp || p.codigo || p.codigoPraca,
    cnp: p.cnp || p.codigo || p.codigoPraca,
    nome: p.nome || p.nomePraca,
    rodovia: p.rodovia || p.localizacao,
    km: p.km,
    valor: p.valor || p.valorPedagio || 0,
    sentido: p.sentido,
    concessionaria: p.concessionaria,
  }))

  pracasPedagio.value = pracas

  // Calcular valor total
  const valorTotal = pracas.reduce((sum: number, p: PracaPedagio) => sum + (p.valor || 0), 0)

  const custoData: CustoVpo = {
    valor_total: resultado.valor_total || valorTotal,
    pedagios: pracas,
    rota_nome: rota.value!.desSPararRot,
    km_total: resultado.km_total || resultado.distanciaTotal || 0,
    tempo_estimado: resultado.tempo_estimado || `${rota.value!.tempoViagem}h`,
  }

  // Atualizar formData
  const updated: VpoEmissaoFormData = {
    ...props.formData,
    periodo: {
      dataInicio: dataInicio.value,
      dataFim: dataFim.value,
      eixos: eixos.value,
    },
    custo: {
      custo: custoData,
      calculado: true,
      calculando: false,
    },
    step5Completo: true,
  }
  emit('update:formData', updated)
}

/**
 * PASSO 2: Emitir VPO enviando as praças de pedágio
 * Agora com timeline visual e polling automático
 */
const emitirVpo = async () => {
  if (!canEmit.value) return

  // Scroll para o topo para ver o processamento
  window.scrollTo({ top: 0, behavior: 'smooth' })

  emitindo.value = true
  emissaoStatus.value = 'enviando'
  errorMessage.value = null
  emissaoTentativas.value = 0

  try {
    console.log('=== INICIANDO EMISSÃO VPO ===')
    console.log('pracasPedagio.value:', pracasPedagio.value)
    console.log('pracasPedagio.value.length:', pracasPedagio.value.length)
    console.log('custo.value:', custo.value)
    console.log('formData.custo:', props.formData.custo)
    console.log('formData.rota.pracas:', props.formData.rota.pracas)

    // Construir dados a enviar - usar praças do custo ou da rota se pracasPedagio estiver vazio
    const pracasParaEnviar = pracasPedagio.value.length > 0
      ? pracasPedagio.value
      : (props.formData.custo.custo?.pedagios || props.formData.rota.pracas || [])

    const dadosEmissao = {
      codpac: pacote.value!.codpac,
      codtrn: transportador.value!.codtrn,  // Obrigatório para o backend
      rota_id: rota.value!.sPararRotID,
      // Dados calculados pelo roteirizador - mapear campos corretamente
      // IMPORTANTE: O CNP vem do NDD Cargo no campo 'cnp', é obrigatório para emissão VPO
      pracas_pedagio: pracasParaEnviar.map((p: any) => ({
        codigo: p.cnp || p.codigo || p.codigoPraca || '',
        cnp: p.cnp || p.codigo || p.codigoPraca || '',
        nome: p.nome || p.nomePraca || '',
        rodovia: p.rodovia || p.localizacao || '',
        valor: p.valor || p.valorPedagio || 0,
      })),
      valor_total: custo.value?.valor_total || 0,
      km_total: custo.value?.km_total || 0,
      // Período
      data_inicio: dataInicio.value,
      data_fim: dataFim.value,
      // Dados do motorista (se empresa)
      codmot: motorista.value?.codmot || null,
      // Dados do veículo
      placa: veiculo.value!.placa,
      eixos: eixos.value,
    }

    // Debug: log dos eixos para verificar o valor
    console.log('=== DEBUG EIXOS ===')
    console.log('eixos.value:', eixos.value)
    console.log('veiculo.value?.eixos:', veiculo.value?.eixos)
    console.log('formData.periodo.eixos:', props.formData.periodo.eixos)
    console.log('categoriaPedagio:', categoriaPedagio.value)

    console.log('=== DADOS A ENVIAR ===')
    console.log('pracas_pedagio:', dadosEmissao.pracas_pedagio)
    console.log('valor_total:', dadosEmissao.valor_total)
    console.log('km_total:', dadosEmissao.km_total)

    // Chamar API de emissão VPO (NDD Cargo) com as praças de pedágio
    const response = await apiFetch(getApiUrl(`/vpo/emissao/iniciar`), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(dadosEmissao),
    })

    const data = await response.json()
    console.log('Resposta emissão:', data)

    if (data.success) {
      emissaoResult.value = data.data
      emissaoUuid.value = data.data?.uuid || null

      // Se já veio concluído (resposta síncrona)
      if (data.data?.status === 'completed') {
        emissaoStatus.value = 'concluido'
        successMessage.value = `VPO emitido com sucesso!`
        finalizarEmissao(data.data)
      } else if (data.data?.status === 'failed') {
        emissaoStatus.value = 'erro'
        errorMessage.value = data.data?.error_message || 'Erro ao emitir VPO'
      } else {
        // Emissão em processamento - iniciar polling
        emissaoStatus.value = 'aguardando'
        console.log('Emissão em processamento, iniciando polling...')
        await consultarResultadoEmissao()
      }
    } else {
      emissaoStatus.value = 'erro'
      errorMessage.value = data.message || 'Erro ao emitir VPO'
      // Mostrar erros de validação se houver
      if (data.validation_errors) {
        const errors = Object.values(data.validation_errors).flat().join(', ')
        errorMessage.value += `: ${errors}`
      }
    }
  } catch (error: any) {
    console.error('Erro ao emitir VPO:', error)
    emissaoStatus.value = 'erro'
    errorMessage.value = error.message || 'Erro ao emitir VPO'
  } finally {
    emitindo.value = false
  }
}

/**
 * Consulta resultado da emissão via polling
 * Tenta até 20 vezes com intervalo de 3 segundos
 */
const consultarResultadoEmissao = async () => {
  if (!emissaoUuid.value) return

  emissaoStatus.value = 'consultando'
  emissaoTentativas.value++

  console.log(`Polling tentativa ${emissaoTentativas.value} para UUID: ${emissaoUuid.value}`)

  try {
    const response = await apiFetch(getApiUrl(`/vpo/emissao/${emissaoUuid.value}`))
    const data = await response.json()

    console.log('Resposta polling:', data)

    if (data.success && data.data) {
      emissaoResult.value = data.data

      if (data.data.status === 'completed') {
        emissaoStatus.value = 'concluido'
        successMessage.value = `VPO emitido com sucesso!`
        finalizarEmissao(data.data)
        return
      } else if (data.data.status === 'failed') {
        emissaoStatus.value = 'erro'
        errorMessage.value = data.data.error_message || 'Erro ao emitir VPO'
        return
      }
    }

    // Ainda processando - continuar polling
    if (emissaoTentativas.value < 20) {
      emissaoStatus.value = 'aguardando'
      // Aguardar 3 segundos antes de consultar novamente
      await new Promise(resolve => setTimeout(resolve, 3000))
      await consultarResultadoEmissao()
    } else {
      // Timeout - mas pode consultar manualmente
      emissaoStatus.value = 'aguardando'
      errorMessage.value = 'Processamento demorado. Clique em "Consultar Resultado" para verificar.'
    }
  } catch (error: any) {
    console.error('Erro no polling:', error)
    emissaoStatus.value = 'erro'
    errorMessage.value = error.message || 'Erro ao consultar resultado'
  }
}

/**
 * Consulta manual do resultado (quando o usuário clica no botão)
 */
const consultarManualmente = async () => {
  if (!emissaoUuid.value) return
  emissaoTentativas.value = 0
  await consultarResultadoEmissao()
}

/**
 * Finaliza a emissão com sucesso
 */
const finalizarEmissao = (data: any) => {
  // Atualizar formData com UUID
  const updated: VpoEmissaoFormData = {
    ...props.formData,
    uuid: data.uuid,
    status: 'concluido',
  }
  emit('update:formData', updated)
  emit('emissaoRealizada', data)
}

const handleNovaEmissao = () => {
  emit('novaEmissao')
}

/**
 * Copia texto para o clipboard
 */
const copyToClipboard = (text: string) => {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text)
  }
}

/**
 * Salva os campos faltantes do transportador
 */
const salvarCamposFaltantes = async () => {
  if (!transportador.value?.codtrn) return

  savingMissingFields.value = true
  errorMessage.value = null

  try {
    // Montar payload apenas com campos preenchidos
    const payload: Record<string, string> = {}

    for (const campo of camposFaltantes.value) {
      const valor = missingFieldsForm.value[campo as keyof typeof missingFieldsForm.value]
      if (valor) {
        payload[campo] = valor
      }
    }

    if (Object.keys(payload).length === 0) {
      errorMessage.value = 'Preencha pelo menos um campo para salvar'
      return
    }

    console.log('Salvando campos faltantes:', payload)

    const response = await fetch(
      getApiUrl(`/vpo/transportadores/${transportador.value.codtrn}`),
      {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      }
    )

    const data = await response.json()

    if (data.success) {
      successMessage.value = 'Dados atualizados com sucesso!'

      // Atualizar transportador no formData com novos dados
      const updated: VpoEmissaoFormData = {
        ...props.formData,
        pacote: {
          ...props.formData.pacote,
          transportador: {
            ...props.formData.pacote.transportador!,
            ...payload,
            score_qualidade: data.data?.score_qualidade || transportador.value.score_qualidade,
            campos_faltantes: data.data?.campos_faltantes || [],
          },
        },
      }
      emit('update:formData', updated)

      // Limpar formulário
      Object.keys(missingFieldsForm.value).forEach((key) => {
        missingFieldsForm.value[key as keyof typeof missingFieldsForm.value] = ''
      })
    } else {
      errorMessage.value = data.message || 'Erro ao salvar dados'
    }
  } catch (error: any) {
    console.error('Erro ao salvar campos faltantes:', error)
    errorMessage.value = error.message || 'Erro ao salvar dados'
  } finally {
    savingMissingFields.value = false
  }
}

const recalcularCusto = () => {
  pracasPedagio.value = []
  roteirizadorGuid.value = null
  roteirizadorStatus.value = 'idle'

  const updated: VpoEmissaoFormData = {
    ...props.formData,
    custo: { custo: null, calculado: false, calculando: false },
    step5Completo: false,
  }
  emit('update:formData', updated)
}

/**
 * Inicializa o componente com dados já calculados do Step 4
 * Se formData.custo.calculado === true, os dados já vieram prontos
 */
const inicializarDadosExistentes = () => {
  console.log('=== VpoStep5Confirmacao: Inicializando ===')
  console.log('formData.custo.calculado:', props.formData.custo.calculado)
  console.log('formData.custo.custo:', props.formData.custo.custo)
  console.log('formData.custo.custo?.pedagios:', props.formData.custo.custo?.pedagios)
  console.log('formData.custo.custo?.pedagios?.length:', props.formData.custo.custo?.pedagios?.length)
  console.log('formData.rota.pracas:', props.formData.rota.pracas)
  console.log('formData.rota.pracas?.length:', props.formData.rota.pracas?.length)

  // Obter praças de todas as fontes possíveis
  const pracasExistentes = props.formData.custo.custo?.pedagios
    || props.formData.rota.pracas
    || []

  console.log('pracasExistentes:', pracasExistentes)
  console.log('pracasExistentes.length:', pracasExistentes.length)

  if (pracasExistentes.length > 0) {
    console.log('Inicializando praças existentes...')

    // Mapear praças para o formato esperado
    // IMPORTANTE: O CNP vem do NDD Cargo no campo 'cnp', é obrigatório para emissão VPO
    pracasPedagio.value = pracasExistentes.map((p: any) => ({
      codigo: p.cnp || p.codigo || p.codigoPraca || '',
      cnp: p.cnp || p.codigo || p.codigoPraca || '',
      nome: p.nome || p.nomePraca || '',
      rodovia: p.rodovia || p.localizacao || '',
      km: p.km || 0,
      valor: p.valor || p.valorPedagio || 0,
      sentido: p.sentido || '',
      concessionaria: p.concessionaria || '',
    }))

    roteirizadorStatus.value = 'completed'
    console.log('Praças inicializadas com sucesso:', pracasPedagio.value.length)
    console.log('Primeiras 2 praças:', pracasPedagio.value.slice(0, 2))
  } else {
    console.log('AVISO: Nenhuma praça encontrada no formData!')
    console.log('Será necessário calcular ao emitir')
  }
}

// Lifecycle
onMounted(() => {
  console.log('VpoStep5Confirmacao mounted')
  inicializarDadosExistentes()
})

const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(value)
}
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Confirmação e Emissão
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Calcule o custo via NDD Cargo e confirme a emissão do VPO
    </p>

    <!-- Success Message -->
    <VAlert
      v-if="successMessage"
      type="success"
      variant="tonal"
      class="mb-4"
    >
      <template #prepend>
        <VIcon icon="tabler-check" />
      </template>
      {{ successMessage }}
    </VAlert>

    <!-- Error Message -->
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      closable
      class="mb-4"
      @click:close="errorMessage = null"
    >
      {{ errorMessage }}
    </VAlert>

    <!-- Processing Status -->
    <VAlert
      v-if="roteirizadorStatus === 'processing' || consultandoResultado"
      type="info"
      variant="tonal"
      class="mb-4"
    >
      <template #prepend>
        <VProgressCircular indeterminate size="20" width="2" />
      </template>
      <span class="ms-2">
        {{ consultandoResultado ? 'Consultando resultado do roteirizador...' : 'Calculando rota no NDD Cargo...' }}
      </span>
    </VAlert>

    <!-- Timeline de Processamento (durante emissão) -->
    <VCard
      v-if="emissaoStatus !== 'idle' && !emissaoFinalizada"
      variant="outlined"
      class="mb-6"
    >
      <VCardItem>
        <template #prepend>
          <VAvatar
            :color="emissaoStatus === 'erro' ? 'error' : emissaoStatus === 'concluido' ? 'success' : 'primary'"
            size="48"
            variant="tonal"
          >
            <VProgressCircular
              v-if="['enviando', 'aguardando', 'consultando'].includes(emissaoStatus)"
              indeterminate
              size="24"
              width="2"
            />
            <VIcon v-else-if="emissaoStatus === 'concluido'" icon="tabler-check" size="24" />
            <VIcon v-else-if="emissaoStatus === 'erro'" icon="tabler-x" size="24" />
          </VAvatar>
        </template>

        <VCardTitle>
          <template v-if="emissaoStatus === 'enviando'">Enviando para NDD Cargo...</template>
          <template v-else-if="emissaoStatus === 'aguardando'">Aguardando Processamento</template>
          <template v-else-if="emissaoStatus === 'consultando'">Consultando Resultado...</template>
          <template v-else-if="emissaoStatus === 'concluido'">Emissão Concluída!</template>
          <template v-else-if="emissaoStatus === 'erro'">Erro na Emissão</template>
        </VCardTitle>

        <VCardSubtitle v-if="emissaoUuid" class="d-flex align-center gap-2">
          <VIcon icon="tabler-fingerprint" size="16" />
          UUID: <code class="text-primary">{{ emissaoUuid }}</code>
          <VBtn
            icon="tabler-copy"
            size="x-small"
            variant="text"
            @click="copyToClipboard(emissaoUuid || '')"
          >
            <VTooltip activator="parent" location="top">Copiar UUID</VTooltip>
          </VBtn>
        </VCardSubtitle>
      </VCardItem>

      <VCardText>
        <!-- Timeline Steps -->
        <div class="timeline-container">
          <div class="timeline-step" :class="{ active: ['enviando', 'aguardando', 'consultando', 'concluido'].includes(emissaoStatus), completed: ['aguardando', 'consultando', 'concluido'].includes(emissaoStatus) }">
            <div class="timeline-icon">
              <VIcon v-if="['aguardando', 'consultando', 'concluido'].includes(emissaoStatus)" icon="tabler-check" size="16" />
              <VProgressCircular v-else-if="emissaoStatus === 'enviando'" indeterminate size="16" width="2" />
              <span v-else>1</span>
            </div>
            <div class="timeline-content">
              <div class="font-weight-medium">Enviar para NDD Cargo</div>
              <div class="text-caption text-medium-emphasis">Transmitindo dados da emissão</div>
            </div>
          </div>

          <div class="timeline-step" :class="{ active: ['aguardando', 'consultando', 'concluido'].includes(emissaoStatus), completed: ['consultando', 'concluido'].includes(emissaoStatus) }">
            <div class="timeline-icon">
              <VIcon v-if="['consultando', 'concluido'].includes(emissaoStatus)" icon="tabler-check" size="16" />
              <VProgressCircular v-else-if="emissaoStatus === 'aguardando'" indeterminate size="16" width="2" />
              <span v-else>2</span>
            </div>
            <div class="timeline-content">
              <div class="font-weight-medium">Processamento NDD Cargo</div>
              <div class="text-caption text-medium-emphasis">
                <template v-if="emissaoTentativas > 0">Tentativa {{ emissaoTentativas }}/20</template>
                <template v-else>Aguardando resposta do servidor</template>
              </div>
            </div>
          </div>

          <div class="timeline-step" :class="{ active: ['consultando', 'concluido'].includes(emissaoStatus), completed: emissaoStatus === 'concluido' }">
            <div class="timeline-icon">
              <VIcon v-if="emissaoStatus === 'concluido'" icon="tabler-check" size="16" />
              <VProgressCircular v-else-if="emissaoStatus === 'consultando'" indeterminate size="16" width="2" />
              <span v-else>3</span>
            </div>
            <div class="timeline-content">
              <div class="font-weight-medium">Resultado da Emissão</div>
              <div class="text-caption text-medium-emphasis">Validação e confirmação</div>
            </div>
          </div>
        </div>

        <!-- Botão Consultar Manualmente -->
        <div v-if="emissaoStatus === 'aguardando' && emissaoTentativas >= 20" class="mt-4">
          <VBtn
            color="info"
            variant="tonal"
            :loading="consultandoResultado"
            @click="consultarManualmente"
          >
            <VIcon icon="tabler-refresh" start />
            Consultar Resultado
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Emissão Concluída -->
    <template v-if="emissaoFinalizada || emissaoStatus === 'concluido'">
      <VCard variant="tonal" color="success" class="mb-6">
        <VCardItem>
          <template #prepend>
            <VAvatar color="success" variant="tonal" size="56">
              <VIcon icon="tabler-check" size="32" />
            </VAvatar>
          </template>

          <VCardTitle class="text-h5">VPO Emitido com Sucesso!</VCardTitle>

          <VCardSubtitle class="d-flex flex-wrap align-center gap-2 mt-1">
            <VChip color="success" size="small" variant="flat">
              <VIcon icon="tabler-fingerprint" start size="14" />
              {{ emissaoUuid || emissaoResult?.uuid || 'N/A' }}
            </VChip>
            <VChip v-if="emissaoResult?.total_pracas" color="info" size="small" variant="outlined">
              {{ emissaoResult.total_pracas }} praças
            </VChip>
            <VChip v-if="emissaoResult?.custo_total" color="success" size="small" variant="outlined">
              R$ {{ parseFloat(emissaoResult.custo_total).toFixed(2) }}
            </VChip>
          </VCardSubtitle>
        </VCardItem>

        <VCardText>
          <!-- Detalhes do Resultado -->
          <VRow v-if="emissaoResult" class="mb-4">
            <VCol cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Praças</div>
              <div class="text-h6">{{ emissaoResult.total_pracas || 0 }}</div>
            </VCol>
            <VCol cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Custo Total</div>
              <div class="text-h6 text-success">R$ {{ parseFloat(emissaoResult.custo_total || 0).toFixed(2) }}</div>
            </VCol>
            <VCol cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Distância</div>
              <div class="text-h6">{{ emissaoResult.distancia_km || 0 }} km</div>
            </VCol>
            <VCol cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Score</div>
              <div class="text-h6">{{ emissaoResult.score_qualidade || 0 }}%</div>
            </VCol>
          </VRow>

          <VDivider class="mb-4" />

          <div class="d-flex flex-wrap gap-3">
            <VBtn color="primary" @click="handleNovaEmissao">
              <VIcon icon="tabler-plus" start />
              Nova Emissão
            </VBtn>

            <VBtn variant="outlined" :to="{ name: 'vpo-emissao' }">
              <VIcon icon="tabler-list" start />
              Ver Emissões
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </template>

    <!-- Formulário de Confirmação -->
    <template v-else>
      <!-- Campos Faltantes -->
      <VCard
        v-if="temCamposFaltantes"
        variant="outlined"
        color="warning"
        class="mb-4"
      >
        <VCardItem>
          <template #prepend>
            <VIcon icon="tabler-alert-triangle" color="warning" />
          </template>
          <VCardTitle class="text-body-1">Dados Faltantes</VCardTitle>
          <VCardSubtitle class="text-caption">
            Complete os campos abaixo para continuar
          </VCardSubtitle>
        </VCardItem>

        <VCardText>
          <VRow>
            <!-- Celular -->
            <VCol v-if="camposFaltantes.includes('contato_celular')" cols="12" md="6">
              <VTextField
                v-model="missingFieldsForm.contato_celular"
                label="Celular *"
                placeholder="(00) 00000-0000"
                prepend-inner-icon="tabler-phone"
              />
            </VCol>

            <!-- E-mail -->
            <VCol v-if="camposFaltantes.includes('contato_email')" cols="12" md="6">
              <VTextField
                v-model="missingFieldsForm.contato_email"
                label="E-mail *"
                placeholder="email@exemplo.com"
                type="email"
                prepend-inner-icon="tabler-mail"
              />
            </VCol>

            <!-- RNTRC -->
            <VCol v-if="camposFaltantes.includes('antt_rntrc')" cols="12" md="6">
              <VTextField
                v-model="missingFieldsForm.antt_rntrc"
                label="RNTRC (Registro ANTT) *"
                placeholder="00000000"
                prepend-inner-icon="tabler-id"
              />
            </VCol>

            <!-- Validade RNTRC -->
            <VCol v-if="camposFaltantes.includes('antt_validade')" cols="12" md="6">
              <VTextField
                v-model="missingFieldsForm.antt_validade"
                label="Validade RNTRC *"
                type="date"
                prepend-inner-icon="tabler-calendar"
              />
            </VCol>

            <!-- Nome da Mãe -->
            <VCol v-if="camposFaltantes.includes('condutor_nome_mae')" cols="12" md="6">
              <VTextField
                v-model="missingFieldsForm.condutor_nome_mae"
                label="Nome da Mãe *"
                placeholder="Nome completo da mãe"
                prepend-inner-icon="tabler-user"
              />
            </VCol>

            <!-- Data de Nascimento -->
            <VCol v-if="camposFaltantes.includes('condutor_data_nascimento')" cols="12" md="6">
              <VTextField
                v-model="missingFieldsForm.condutor_data_nascimento"
                label="Data de Nascimento *"
                type="date"
                prepend-inner-icon="tabler-calendar-event"
              />
            </VCol>

            <!-- RG -->
            <VCol v-if="camposFaltantes.includes('condutor_rg')" cols="12" md="6">
              <VTextField
                v-model="missingFieldsForm.condutor_rg"
                label="RG *"
                placeholder="00.000.000-0"
                prepend-inner-icon="tabler-id-badge"
              />
            </VCol>
          </VRow>

          <div class="d-flex justify-end mt-4">
            <VBtn
              color="warning"
              :loading="savingMissingFields"
              @click="salvarCamposFaltantes"
            >
              <VIcon icon="tabler-device-floppy" start />
              Salvar Dados
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- Período da Viagem -->
      <VCard variant="outlined" class="mb-4">
        <VCardItem>
          <template #prepend>
            <VIcon icon="tabler-calendar" color="primary" />
          </template>
          <VCardTitle class="text-body-1">Período da Viagem</VCardTitle>
        </VCardItem>

        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="dataInicio"
                label="Data de Início *"
                type="date"
                prepend-inner-icon="tabler-calendar-event"
                :disabled="custoCalculado"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="dataFim"
                label="Data de Fim *"
                type="date"
                prepend-inner-icon="tabler-calendar-event"
                :disabled="custoCalculado"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Resumo dos Dados -->
      <VCard variant="outlined" class="mb-4">
        <VCardItem>
          <template #prepend>
            <VIcon icon="tabler-file-description" color="primary" />
          </template>
          <VCardTitle class="text-body-1">Resumo da Emissão</VCardTitle>
        </VCardItem>

        <VCardText>
          <VTable density="compact">
            <tbody>
              <tr>
                <td class="font-weight-medium">Pacote:</td>
                <td>#{{ pacote?.codpac }}</td>
              </tr>
              <tr>
                <td class="font-weight-medium">Transportador:</td>
                <td>{{ transportador?.condutor_nome || transportador?.nomtrn }}</td>
              </tr>
              <tr v-if="motorista">
                <td class="font-weight-medium">Motorista:</td>
                <td>{{ motorista.nommot }}</td>
              </tr>
              <tr>
                <td class="font-weight-medium">Veículo:</td>
                <td>{{ veiculo?.placa }} ({{ eixos }} eixos)</td>
              </tr>
              <tr>
                <td class="font-weight-medium">Rota:</td>
                <td>{{ rota?.desSPararRot }}</td>
              </tr>
              <tr>
                <td class="font-weight-medium">Municípios:</td>
                <td>{{ municipios.length }} cidades</td>
              </tr>
              <tr>
                <td class="font-weight-medium">Período:</td>
                <td>{{ dataInicio }} até {{ dataFim }}</td>
              </tr>
              <tr>
                <td class="font-weight-medium">Categoria Pedágio:</td>
                <td>{{ categoriaPedagio }} ({{ eixos }} eixos)</td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>

      <!-- Botão Calcular Custo (NDD Cargo Roteirizador) -->
      <VBtn
        v-if="!custoCalculado"
        color="primary"
        block
        size="large"
        :loading="calculando || consultandoResultado"
        :disabled="!canCalculate"
        @click="calcularCusto"
      >
        <VIcon icon="tabler-route" start />
        Calcular Rota (NDD Cargo)
      </VBtn>

      <!-- Custo Calculado + Praças de Pedágio -->
      <VCard
        v-if="custoCalculado && custo"
        variant="tonal"
        color="primary"
        class="mb-4"
      >
        <VCardItem>
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="48">
              <VIcon icon="tabler-cash" size="32" />
            </VAvatar>
          </template>

          <VCardTitle class="text-h4">
            {{ formatCurrency(custo.valor_total) }}
          </VCardTitle>

          <VCardSubtitle>
            {{ pracasPedagio.length }} praças de pedágio | {{ custo.km_total }} km
          </VCardSubtitle>
        </VCardItem>

        <!-- Lista de Praças de Pedágio -->
        <VCardText v-if="pracasPedagio.length > 0">
          <div class="text-caption text-medium-emphasis mb-3">
            Praças de pedágio retornadas pelo NDD Cargo:
          </div>

          <div class="pracas-list">
            <div
              v-for="(praca, index) in pracasPedagio"
              :key="praca.codigo || index"
              class="praca-item d-flex justify-space-between align-center pa-3 rounded"
            >
              <div class="d-flex align-center gap-3">
                <VAvatar size="32" color="primary" variant="tonal">
                  <span class="text-caption font-weight-bold">{{ index + 1 }}</span>
                </VAvatar>
                <span class="font-weight-medium">{{ praca.nome }}</span>
              </div>
              <VChip size="small" color="success" variant="flat">
                {{ formatCurrency(praca.valor) }}
              </VChip>
            </div>
          </div>
        </VCardText>

        <!-- Mensagem quando não há praças de pedágio -->
        <VCardText v-else>
          <VAlert type="info" variant="tonal" density="compact">
            <VIcon icon="tabler-info-circle" start />
            Nenhuma praça de pedágio nesta rota. A emissão pode prosseguir normalmente.
          </VAlert>
        </VCardText>
      </VCard>

      <!-- Botões de Ação -->
      <div v-if="custoCalculado" class="d-flex flex-wrap gap-3 mt-6 action-buttons">
        <VBtn
          color="secondary"
          variant="outlined"
          size="large"
          class="action-btn"
          @click="recalcularCusto"
        >
          <VIcon icon="tabler-refresh" start />
          Recalcular
        </VBtn>

        <VBtn
          color="success"
          size="large"
          class="action-btn action-btn-primary"
          :loading="emitindo"
          :disabled="!canEmit"
          @click="emitirVpo"
        >
          <VIcon icon="tabler-check" start />
          Confirmar e Emitir VPO
        </VBtn>
      </div>

      <!-- Aviso sobre Emissão -->
      <VAlert
        v-if="custoCalculado"
        :type="pracasPedagio.length > 0 ? 'warning' : 'info'"
        variant="tonal"
        class="mt-4"
        density="compact"
      >
        <template #prepend>
          <VIcon :icon="pracasPedagio.length > 0 ? 'tabler-alert-triangle' : 'tabler-info-circle'" />
        </template>
        <span class="text-caption">
          <template v-if="pracasPedagio.length > 0">
            Atenção: Esta ação irá efetuar a compra do Vale Pedágio via NDD Cargo.
            {{ pracasPedagio.length }} praças serão processadas.
          </template>
          <template v-else>
            Rota sem praças de pedágio. A emissão será registrada sem custo de pedágio.
          </template>
        </span>
      </VAlert>
    </template>
  </div>
</template>

<style scoped>
/* Timeline de Processamento */
.timeline-container {
  display: flex;
  flex-direction: column;
  gap: 0;
  position: relative;
  padding-left: 4px;
}

.timeline-step {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  position: relative;
  padding-bottom: 24px;
  opacity: 0.5;
  transition: opacity 0.3s ease;
}

.timeline-step:last-child {
  padding-bottom: 0;
}

/* Linha conectora vertical */
.timeline-step::before {
  content: '';
  position: absolute;
  left: 15px;
  top: 32px;
  bottom: 0;
  width: 2px;
  background: rgba(var(--v-theme-on-surface), 0.12);
}

.timeline-step:last-child::before {
  display: none;
}

.timeline-step.active {
  opacity: 1;
}

.timeline-step.active::before {
  background: rgb(var(--v-theme-primary));
}

.timeline-step.completed::before {
  background: rgb(var(--v-theme-success));
}

.timeline-icon {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(var(--v-theme-on-surface), 0.08);
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
  z-index: 1;
}

.timeline-step.active .timeline-icon {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}

.timeline-step.completed .timeline-icon {
  background: rgb(var(--v-theme-success));
  color: rgb(var(--v-theme-on-success));
}

.timeline-content {
  flex: 1;
  padding-top: 4px;
}

.timeline-content .font-weight-medium {
  line-height: 1.4;
}

/* Animação de pulso para status ativo */
@keyframes pulse {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(var(--v-theme-primary), 0.4);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(var(--v-theme-primary), 0);
  }
}

.timeline-step.active:not(.completed) .timeline-icon {
  animation: pulse 2s infinite;
}

/* Responsividade para mobile */
@media (max-width: 600px) {
  .timeline-container {
    padding-left: 0;
  }

  .timeline-step {
    gap: 12px;
  }

  .timeline-icon {
    width: 28px;
    height: 28px;
    font-size: 12px;
  }

  .timeline-step::before {
    left: 13px;
    top: 28px;
  }
}

/* Lista de Praças de Pedágio */
.pracas-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 300px;
  overflow-y: auto;
}

.praca-item {
  background: rgba(var(--v-theme-on-surface), 0.04);
  transition: background 0.2s ease;
}

.praca-item:hover {
  background: rgba(var(--v-theme-on-surface), 0.08);
}

/* Botões de Ação */
.action-buttons {
  justify-content: flex-start;
}

.action-btn {
  min-width: 160px;
  height: 48px !important;
  font-weight: 500;
}

.action-btn-primary {
  min-width: 220px;
}

@media (max-width: 600px) {
  .action-buttons {
    flex-direction: column;
  }

  .action-btn,
  .action-btn-primary {
    width: 100%;
    min-width: unset;
  }
}
</style>
