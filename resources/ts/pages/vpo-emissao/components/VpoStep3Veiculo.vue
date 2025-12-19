<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { VpoEmissaoFormData, VeiculoVpo } from '../types'
import { formatPlaca } from '../types'
import { apiFetch } from '@/config/api'

// Props & Emits
const props = defineProps<{
  formData: VpoEmissaoFormData
}>()

const emit = defineEmits<{
  'update:formData': [value: VpoEmissaoFormData]
  'stepComplete': [complete: boolean]
}>()

// State
const loading = ref(false)
const validatingPlaca = ref(false)
const placaInput = ref('')
const eixosInput = ref(2)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const veiculosDisponiveis = ref<VeiculoVpo[]>([])

// Cadastro manual de tag
const showCadastroTag = ref(false)
const cadastrandoTag = ref(false)
const tagManualInput = ref('')

// Cache de veículos
const veiculosCache = ref<any[]>([])
const loadingCache = ref(false)

// Computed
const transportador = computed(() => props.formData.pacote.transportador)
const veiculoSelecionado = computed(() => props.formData.veiculo.veiculo)

const isStepValid = computed(() => {
  // Veículo precisa estar selecionado E ter tag SemParar válida
  return veiculoSelecionado.value !== null &&
         veiculoSelecionado.value.tag !== null &&
         veiculoSelecionado.value.status_semparar === 'ativo'
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

// Quando o usuário altera os eixos, atualizar formData imediatamente
watch(eixosInput, (newEixos) => {
  if (veiculoSelecionado.value) {
    const updated: VpoEmissaoFormData = {
      ...props.formData,
      veiculo: {
        ...props.formData.veiculo,
        veiculo: {
          ...veiculoSelecionado.value,
          eixos: newEixos,
        },
      },
      periodo: {
        ...props.formData.periodo,
        eixos: newEixos,
      },
    }
    emit('update:formData', updated)
  }
})

// Methods

// Carregar veículos do cache para o transportador
const carregarVeiculosCache = async () => {
  if (!transportador.value) return

  loadingCache.value = true
  try {
    const response = await fetch(
      `/api/veiculos-cache/transportador/${transportador.value.codtrn}`
    )
    const data = await response.json()

    if (data.success) {
      veiculosCache.value = data.data || []
      console.log('Veículos em cache carregados:', veiculosCache.value.length)
    }
  } catch (error) {
    console.error('Erro ao carregar cache de veículos:', error)
  } finally {
    loadingCache.value = false
  }
}

// Verificar se existe no cache antes de validar no SemParar
const verificarCache = async (placa: string): Promise<any | null> => {
  try {
    const placaNormalizada = placa.replace(/[^A-Z0-9]/gi, '').toUpperCase()
    const response = await apiFetch(`/api/veiculos-cache/${placaNormalizada}`)

    if (response.ok) {
      const data = await response.json()
      if (data.success && data.data) {
        return data.data
      }
    }
  } catch (error) {
    console.log('Veículo não encontrado no cache')
  }
  return null
}

// Salvar veículo no cache após validação
const salvarNoCache = async (veiculoData: any, codtrn: number) => {
  try {
    await apiFetch(`/api/veiculos-cache`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...veiculoData,
        codtrn: codtrn,
      }),
    })
    // Recarregar cache
    await carregarVeiculosCache()
  } catch (error) {
    console.error('Erro ao salvar no cache:', error)
  }
}

const carregarVeiculos = async () => {
  if (!transportador.value) return

  loading.value = true
  errorMessage.value = null

  try {
    // Carregar veículos do cache primeiro
    await carregarVeiculosCache()

    // Para empresas, buscar veículos do transportador
    if (props.formData.motorista.isEmpresa) {
      const response = await fetch(
        `/api/transportes/${transportador.value.codtrn}`
      )
      const data = await response.json()

      if (data.success && data.data?.veiculos) {
        // Mesclar veículos do Progress com dados do cache
        veiculosDisponiveis.value = data.data.veiculos.map((v: any) => {
          // Verificar se existe no cache
          const cached = veiculosCache.value.find(
            (c: any) => c.placa?.replace(/[^A-Z0-9]/gi, '') === v.numpla?.replace(/[^A-Z0-9]/gi, '')
          )

          return {
            placa: v.numpla,
            descricao: cached?.descricao || v.descricao || '',
            tipo: cached?.tipo_veiculo || v.tipcam || '',
            modelo: cached?.modelo || v.modvei || '',
            eixos: cached?.eixos || 2,
            proprietario: cached?.proprietario || transportador.value?.condutor_nome || '',
            tag: cached?.tag || null,
            status_semparar: cached?.status === 'ATIVO' ? 'ativo' : (cached ? 'pendente' : null),
            // Dados extras do cache
            cache_id: cached?.id,
            dados_semparar_reais: cached?.dados_semparar_reais || false,
            ultima_validacao: cached?.ultima_validacao,
          }
        })

        // Atualizar formData
        const updated: VpoEmissaoFormData = {
          ...props.formData,
          veiculo: {
            ...props.formData.veiculo,
            veiculosDisponiveis: veiculosDisponiveis.value,
          },
        }
        emit('update:formData', updated)
      }
    } else {
      // Para autônomos, usar placa do transportador
      if (transportador.value.placa) {
        placaInput.value = transportador.value.placa
      }
    }
  } catch (error: any) {
    console.error('Erro ao carregar veículos:', error)
    errorMessage.value = error.message || 'Erro ao carregar veículos'
  } finally {
    loading.value = false
  }
}

const validarPlaca = async () => {
  const placa = placaInput.value.replace(/[^A-Z0-9]/gi, '').toUpperCase()

  if (!placa || placa.length !== 7) {
    errorMessage.value = 'Placa inválida. Use formato AAA1234 ou AAA1A23'
    return
  }

  validatingPlaca.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    // 1. Verificar se existe no cache primeiro
    const cachedVeiculo = await verificarCache(placa)
    let veiculoData: any = {}
    let hasTag = false
    let isActive = false
    let fromCache = false

    if (cachedVeiculo && cachedVeiculo.tag && cachedVeiculo.status === 'ATIVO') {
      // Usar dados do cache se tem tag ativa
      console.log('Usando dados do cache:', cachedVeiculo)
      veiculoData = cachedVeiculo
      hasTag = true
      isActive = true
      fromCache = true
      successMessage.value = 'Veículo encontrado no cache! Dados carregados automaticamente.'
    } else {
      // 2. Validar placa no SemParar (status do veículo)
      const response = await apiFetch(`/api/semparar/status-veiculo`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ placa }),
      })

      const data = await response.json()

      // Dados do veículo vêm em data.data.dados_veiculo (estrutura do SemPararService)
      veiculoData = data.data?.dados_veiculo || data.data || {}

      // data.success já indica se o veículo está ativo (verificado no backend)
      hasTag = data.success && veiculoData?.tag
      isActive = data.success

      // 3. Salvar no cache para uso futuro
      if (transportador.value && (veiculoData.descricao || veiculoData.eixos)) {
        await salvarNoCache({
          placa: placa,
          descricao: veiculoData.descricao || '',
          eixos: veiculoData.eixos || eixosInput.value,
          proprietario: veiculoData.proprietario || transportador.value?.condutor_nome || '',
          tag: veiculoData.tag || null,
          status: isActive ? 'ATIVO' : 'PENDENTE',
          tipo_veiculo: transportador.value?.veiculo_tipo || '',
        }, transportador.value.codtrn)
      }
    }

    // Criar veículo com dados obtidos
    const veiculo: VeiculoVpo = {
      placa: formatPlaca(placa),
      descricao: veiculoData?.descricao || '',
      tipo: veiculoData?.tipo_veiculo || transportador.value?.veiculo_tipo || '',
      modelo: veiculoData?.modelo || transportador.value?.veiculo_modelo || '',
      eixos: veiculoData?.eixos || eixosInput.value,
      proprietario: veiculoData?.proprietario || transportador.value?.condutor_nome || '',
      tag: veiculoData?.tag || null,
      status_semparar: isActive ? 'ativo' : 'pendente',
    }

    // Atualizar eixosInput com o valor do veículo
    eixosInput.value = veiculo.eixos

    // Atualizar formData
    const stepCompleto = hasTag && isActive
    const updated: VpoEmissaoFormData = {
      ...props.formData,
      veiculo: {
        ...props.formData.veiculo,
        veiculo: veiculo,
      },
      periodo: {
        ...props.formData.periodo,
        eixos: veiculo.eixos,
      },
      step3Completo: stepCompleto,
    }
    emit('update:formData', updated)

    // Mensagens de erro específicas (apenas se não veio do cache)
    if (!fromCache) {
      if (!isActive) {
        errorMessage.value = 'Veículo não encontrado no SemParar. É necessário cadastrar a tag antes de emitir o VPO.'
      } else if (!hasTag) {
        errorMessage.value = 'Veículo encontrado, mas não possui tag SemParar cadastrada. Cadastre a tag para continuar.'
      }
    }
  } catch (error: any) {
    console.error('Erro ao validar placa:', error)
    errorMessage.value = error.message || 'Erro ao validar placa'
  } finally {
    validatingPlaca.value = false
  }
}

const selecionarVeiculo = (veiculo: VeiculoVpo) => {
  placaInput.value = veiculo.placa
  eixosInput.value = veiculo.eixos || 2
  validarPlaca()
}

const limparVeiculo = () => {
  const updated: VpoEmissaoFormData = {
    ...props.formData,
    veiculo: {
      ...props.formData.veiculo,
      veiculo: null,
    },
    step3Completo: false,
  }
  emit('update:formData', updated)
  placaInput.value = ''
  errorMessage.value = null
  showCadastroTag.value = false
  tagManualInput.value = ''
}

// Mostrar formulário de cadastro de tag
const abrirCadastroTag = () => {
  showCadastroTag.value = true
  tagManualInput.value = ''
  errorMessage.value = null
}

// Informar tag manualmente (para quando já foi cadastrada no portal SemParar)
const informarTagManual = async () => {
  if (!tagManualInput.value.trim()) {
    errorMessage.value = 'Informe o número da tag'
    return
  }

  if (!veiculoSelecionado.value) {
    errorMessage.value = 'Selecione um veículo primeiro'
    return
  }

  cadastrandoTag.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    // Primeiro, tentar validar novamente no SemParar
    // Pode ser que a tag já tenha sido cadastrada e agora está disponível
    const response = await apiFetch(`/api/semparar/status-veiculo`, {
      method: 'POST',
      body: JSON.stringify({
        placa: veiculoSelecionado.value.placa.replace(/[^A-Z0-9]/gi, ''),
      }),
    })

    const data = await response.json()
    const veiculoData = data.data?.dados_veiculo || data.data || {}

    // Se encontrou a tag no SemParar, usar os dados reais
    if (data.success && veiculoData?.tag) {
      successMessage.value = 'Tag encontrada no SemParar!'

      const veiculoAtualizado: VeiculoVpo = {
        ...veiculoSelecionado.value,
        tag: veiculoData.tag,
        status_semparar: 'ativo',
        eixos: veiculoData.eixos || veiculoSelecionado.value.eixos,
      }

      const updated: VpoEmissaoFormData = {
        ...props.formData,
        veiculo: {
          ...props.formData.veiculo,
          veiculo: veiculoAtualizado,
        },
        step3Completo: true,
      }
      emit('update:formData', updated)
      showCadastroTag.value = false
      tagManualInput.value = ''
      return
    }

    // Se não encontrou no SemParar, usar a tag informada manualmente
    // com aviso de que será validada durante a emissão
    successMessage.value = 'Tag informada. Será validada durante a emissão do VPO.'

    const veiculoAtualizado: VeiculoVpo = {
      ...veiculoSelecionado.value,
      tag: tagManualInput.value.trim(),
      status_semparar: 'ativo', // Assumimos ativo para permitir prosseguir
    }

    const updated: VpoEmissaoFormData = {
      ...props.formData,
      veiculo: {
        ...props.formData.veiculo,
        veiculo: veiculoAtualizado,
      },
      step3Completo: true,
    }
    emit('update:formData', updated)
    showCadastroTag.value = false
    tagManualInput.value = ''
  } catch (error: any) {
    console.error('Erro ao verificar tag:', error)
    // Em caso de erro de conexão, ainda permite informar manualmente
    successMessage.value = 'Tag informada. Será validada durante a emissão do VPO.'

    const veiculoAtualizado: VeiculoVpo = {
      ...veiculoSelecionado.value!,
      tag: tagManualInput.value.trim(),
      status_semparar: 'ativo',
    }

    const updated: VpoEmissaoFormData = {
      ...props.formData,
      veiculo: {
        ...props.formData.veiculo,
        veiculo: veiculoAtualizado,
      },
      step3Completo: true,
    }
    emit('update:formData', updated)
    showCadastroTag.value = false
    tagManualInput.value = ''
  } finally {
    cadastrandoTag.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await carregarVeiculos()

  // Se já tem placa no formData (auto-preenchido), usar
  if (props.formData.veiculo.veiculo?.placa) {
    placaInput.value = props.formData.veiculo.veiculo.placa
    eixosInput.value = props.formData.veiculo.veiculo.eixos || 2
  } else if (transportador.value?.placa) {
    placaInput.value = transportador.value.placa
    // Auto-validar placa se já está preenchida
    if (placaInput.value && placaInput.value.length >= 7) {
      await validarPlaca()
    }
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Veículo
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Informe a placa e quantidade de eixos do veículo
    </p>

    <!-- Success Message -->
    <VAlert
      v-if="successMessage"
      type="success"
      variant="tonal"
      closable
      class="mb-4"
      @click:close="successMessage = null"
    >
      {{ successMessage }}
    </VAlert>

    <!-- Error/Warning Message -->
    <VAlert
      v-if="errorMessage"
      :type="errorMessage.startsWith('Aviso') ? 'warning' : 'error'"
      variant="tonal"
      closable
      class="mb-4"
      @click:close="errorMessage = null"
    >
      {{ errorMessage }}
    </VAlert>

    <!-- Loading -->
    <VSkeletonLoader v-if="loading" type="card" />

    <template v-else>
      <!-- Veículos Disponíveis (para empresas) -->
      <div v-if="veiculosDisponiveis.length > 0" class="mb-6">
        <p class="text-body-2 font-weight-medium mb-3">
          Veículos cadastrados:
        </p>

        <div class="d-flex flex-wrap gap-2">
          <VChip
            v-for="v in veiculosDisponiveis"
            :key="v.placa"
            :color="veiculoSelecionado?.placa === v.placa ? 'primary' : 'default'"
            :variant="veiculoSelecionado?.placa === v.placa ? 'elevated' : 'outlined'"
            class="cursor-pointer"
            @click="selecionarVeiculo(v)"
          >
            <VIcon icon="tabler-car" start />
            {{ v.placa }}
          </VChip>
        </div>
      </div>

      <!-- Input de Placa -->
      <VRow>
        <VCol cols="12" md="6">
          <VTextField
            v-model="placaInput"
            label="Placa do Veículo *"
            placeholder="AAA1234 ou AAA1A23"
            prepend-inner-icon="tabler-car"
            :disabled="validatingPlaca"
            @keyup.enter="validarPlaca"
          />
        </VCol>

        <VCol cols="12" md="6">
          <VSelect
            v-model="eixosInput"
            :items="[2, 3, 4, 5, 6, 7, 8, 9]"
            label="Quantidade de Eixos *"
            prepend-inner-icon="tabler-circles"
          />
        </VCol>
      </VRow>

      <!-- Botão Validar -->
      <VBtn
        color="primary"
        variant="tonal"
        class="mt-4"
        :loading="validatingPlaca"
        :disabled="!placaInput || validatingPlaca"
        @click="validarPlaca"
      >
        <VIcon icon="tabler-check" start />
        Validar Placa
      </VBtn>

      <!-- Veículo Selecionado -->
      <VCard
        v-if="veiculoSelecionado"
        class="mt-6"
        variant="tonal"
        :color="isStepValid ? 'success' : 'error'"
      >
        <VCardItem>
          <template #prepend>
            <VIcon
              icon="tabler-car"
              :color="isStepValid ? 'success' : 'error'"
              size="32"
            />
          </template>

          <VCardTitle>{{ veiculoSelecionado.placa }}</VCardTitle>

          <VCardSubtitle>
            {{ veiculoSelecionado.modelo || veiculoSelecionado.tipo || 'Veículo' }}
            • {{ veiculoSelecionado.eixos }} eixos
          </VCardSubtitle>

          <template #append>
            <VBtn
              icon="tabler-x"
              size="small"
              variant="text"
              @click="limparVeiculo"
            />
          </template>
        </VCardItem>

        <VDivider />

        <VCardText>
          <div class="d-flex flex-wrap gap-4">
            <!-- Status SemParar -->
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-badge" size="small" :color="veiculoSelecionado.status_semparar === 'ativo' ? 'success' : 'error'" />
              <span class="text-body-2 font-weight-medium">Status:</span>
              <VChip
                :color="veiculoSelecionado.status_semparar === 'ativo' ? 'success' : 'error'"
                size="small"
              >
                {{ veiculoSelecionado.status_semparar === 'ativo' ? 'Ativo' : 'Não cadastrado' }}
              </VChip>
            </div>

            <!-- Tag -->
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-tag" size="small" :color="veiculoSelecionado.tag ? 'success' : 'error'" />
              <span class="text-body-2 font-weight-medium">Tag:</span>
              <VChip
                v-if="veiculoSelecionado.tag"
                color="success"
                size="small"
              >
                {{ veiculoSelecionado.tag }}
              </VChip>
              <VChip
                v-else
                color="error"
                size="small"
              >
                Não cadastrada
              </VChip>
            </div>
          </div>

          <!-- Seção de cadastro de tag quando não encontrada -->
          <template v-if="!isStepValid">
            <!-- Formulário para Informar Tag Manualmente -->
            <VCard
              v-if="showCadastroTag"
              variant="outlined"
              color="warning"
              class="mt-4"
            >
              <VCardItem>
                <template #prepend>
                  <VIcon icon="tabler-tag" color="warning" />
                </template>
                <VCardTitle class="text-body-1">Informar Tag SemParar</VCardTitle>
                <VCardSubtitle class="text-caption">
                  Informe o número da tag já cadastrada no portal SemParar
                </VCardSubtitle>
              </VCardItem>

              <VCardText>
                <VAlert type="info" variant="tonal" density="compact" class="mb-4">
                  <VIcon icon="tabler-info-circle" start size="18" />
                  <span class="text-caption">
                    A tag deve estar previamente cadastrada no
                    <a href="https://empresas.semparar.com.br" target="_blank" class="text-primary">
                      Portal SemParar
                    </a>.
                    Informe o número da tag abaixo para prosseguir.
                  </span>
                </VAlert>

                <VRow>
                  <VCol cols="12" md="8">
                    <VTextField
                      v-model="tagManualInput"
                      label="Número da Tag *"
                      placeholder="Ex: 1234567890"
                      prepend-inner-icon="tabler-tag"
                      :disabled="cadastrandoTag"
                      @keyup.enter="informarTagManual"
                    />
                  </VCol>
                  <VCol cols="12" md="4" class="d-flex align-center">
                    <VBtn
                      color="warning"
                      :loading="cadastrandoTag"
                      :disabled="!tagManualInput.trim()"
                      block
                      @click="informarTagManual"
                    >
                      <VIcon icon="tabler-check" start />
                      Confirmar
                    </VBtn>
                  </VCol>
                </VRow>

                <div class="d-flex justify-end mt-2">
                  <VBtn
                    variant="text"
                    size="small"
                    @click="showCadastroTag = false"
                  >
                    Cancelar
                  </VBtn>
                </div>
              </VCardText>
            </VCard>

            <!-- Alerta com opções quando tag não encontrada -->
            <VAlert
              v-else
              type="error"
              variant="tonal"
              density="compact"
              class="mt-4"
            >
              <template #prepend>
                <VIcon icon="tabler-alert-triangle" />
              </template>
              <div>
                <div class="font-weight-medium">
                  Tag SemParar não encontrada
                </div>
                <div class="text-caption mt-1">
                  Para emitir o VPO, é obrigatório que o veículo tenha uma tag SemParar ativa.
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                  <VBtn
                    color="warning"
                    size="small"
                    @click="abrirCadastroTag"
                  >
                    <VIcon icon="tabler-tag" start />
                    Informar Tag
                  </VBtn>
                  <VBtn
                    variant="outlined"
                    size="small"
                    @click="validarPlaca"
                    :loading="validatingPlaca"
                  >
                    <VIcon icon="tabler-refresh" start />
                    Verificar Novamente
                  </VBtn>
                  <VBtn
                    variant="text"
                    size="small"
                    href="https://empresas.semparar.com.br"
                    target="_blank"
                  >
                    <VIcon icon="tabler-external-link" start />
                    Acessar Portal SemParar
                  </VBtn>
                </div>
              </div>
            </VAlert>
          </template>
        </VCardText>
      </VCard>

      <!-- Info quando nada selecionado -->
      <VAlert
        v-else
        type="info"
        variant="tonal"
        class="mt-6"
      >
        <template #prepend>
          <VIcon icon="tabler-info-circle" />
        </template>
        <div>
          <div class="font-weight-medium mb-1">
            Veículo Obrigatório
          </div>
          <div class="text-caption">
            Informe a placa do veículo e clique em "Validar Placa".
            O sistema verificará se o veículo está cadastrado no SemParar.
          </div>
        </div>
      </VAlert>
    </template>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
