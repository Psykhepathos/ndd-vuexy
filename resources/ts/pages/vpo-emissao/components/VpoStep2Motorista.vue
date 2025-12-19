<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { VpoEmissaoFormData, MotoristaEmpresa } from '../types'
import { CAMPOS_LABELS } from '../types'
import { apiFetch, getApiUrl } from '@/config/api'

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
const savingMotorista = ref(false)
const motoristas = ref<MotoristaEmpresa[]>([])
const selectedCodmot = ref<number | null>(null)
const errorMessage = ref<string | null>(null)

// Dialog de edição
const editDialog = ref(false)
const editingMotorista = ref<MotoristaEmpresa | null>(null)
const motoristaForm = ref({
  cpf: '',
  rntrc: '',
  nommae: '',
  data_nascimento: '',
  cnh: '',
})

// Computed
const transportador = computed(() => props.formData.pacote.transportador)
const codtrn = computed(() => transportador.value?.codtrn || 0)

const motoristaSelecionado = computed(() =>
  motoristas.value.find(m => m.codmot === selectedCodmot.value)
)

const motoristasCompletos = computed(() =>
  motoristas.value.filter(m => m.dados_completos)
)

const isStepValid = computed(() => {
  // Step válido se:
  // 1. Não é empresa (autônomo não precisa selecionar motorista)
  // 2. Ou motorista selecionado E com dados completos
  if (!props.formData.motorista.isEmpresa) {
    return true
  }
  return !!motoristaSelecionado.value?.dados_completos
})

// Watchers
watch(isStepValid, (valid) => {
  emit('stepComplete', valid)
})

watch(motoristaSelecionado, (motorista) => {
  if (motorista) {
    const updated: VpoEmissaoFormData = {
      ...props.formData,
      motorista: {
        ...props.formData.motorista,
        motoristaSelecionado: motorista,
      },
      step2Completo: motorista.dados_completos,
    }
    emit('update:formData', updated)
  }
})

// Methods
const carregarMotoristas = async () => {
  console.log('carregarMotoristas chamado, codtrn:', codtrn.value, 'isEmpresa:', props.formData.motorista.isEmpresa)
  if (!codtrn.value) return

  loading.value = true
  errorMessage.value = null

  try {
    const response = await apiFetch(`/vpo/motoristas/${codtrn.value}`)
    const data = await response.json()
    console.log('API motoristas response:', data)

    if (data.success) {
      motoristas.value = data.data || []
      console.log('Motoristas carregados:', motoristas.value.length)

      // Atualizar formData com lista de motoristas
      const updated: VpoEmissaoFormData = {
        ...props.formData,
        motorista: {
          ...props.formData.motorista,
          motoristas: motoristas.value,
        },
      }
      emit('update:formData', updated)

      // Se só tem 1 motorista com dados completos, selecionar automaticamente
      if (motoristasCompletos.value.length === 1) {
        selectedCodmot.value = motoristasCompletos.value[0].codmot
      }
    } else {
      errorMessage.value = data.message || 'Erro ao carregar motoristas'
    }
  } catch (error: any) {
    console.error('Erro ao carregar motoristas:', error)
    errorMessage.value = error.message || 'Erro ao carregar motoristas'
  } finally {
    loading.value = false
  }
}

const selecionarMotorista = (motorista: MotoristaEmpresa) => {
  if (!motorista.dados_completos) {
    // Abrir formulário para completar dados
    editarMotorista(motorista)
    return
  }
  selectedCodmot.value = motorista.codmot
}

const editarMotorista = (motorista: MotoristaEmpresa) => {
  console.log('Editando motorista:', motorista)
  editingMotorista.value = motorista
  motoristaForm.value = {
    cpf: motorista.cpf || '',
    rntrc: motorista.rntrc || '',
    nommae: motorista.nommae || '',
    data_nascimento: motorista.data_nascimento || '',
    cnh: motorista.cnh || '',
  }
  editDialog.value = true
  console.log('Dialog aberto:', editDialog.value)
}

const salvarMotorista = async () => {
  console.log('salvarMotorista chamado')
  console.log('editingMotorista:', editingMotorista.value)
  console.log('motoristaForm:', motoristaForm.value)

  if (!editingMotorista.value) {
    console.error('editingMotorista é null!')
    return
  }

  savingMotorista.value = true
  errorMessage.value = null

  const payload = {
    cpf: motoristaForm.value.cpf.replace(/\D/g, ''),
    rntrc: motoristaForm.value.rntrc.replace(/\D/g, ''),
    nommae: motoristaForm.value.nommae,
    data_nascimento: motoristaForm.value.data_nascimento,
    cnh: motoristaForm.value.cnh || undefined,
  }

  console.log('Payload a enviar:', payload)
  console.log('URL:', getApiUrl(`/vpo/motoristas/${codtrn.value}/${editingMotorista.value.codmot}`))

  try {
    const response = await apiFetch(
      `/vpo/motoristas/${codtrn.value}/${editingMotorista.value.codmot}`,
      {
        method: 'POST',
        body: JSON.stringify(payload),
      }
    )

    console.log('Response status:', response.status)
    const data = await response.json()
    console.log('Response data:', data)

    if (data.success) {
      console.log('Sucesso! Fechando dialog e recarregando motoristas')
      editDialog.value = false
      await carregarMotoristas()

      // Selecionar motorista se agora está completo
      if (data.dados_completos) {
        selectedCodmot.value = editingMotorista.value.codmot
      }
    } else {
      errorMessage.value = data.message || 'Erro ao salvar motorista'
      console.error('Erro da API:', data.message)
    }
  } catch (error: any) {
    console.error('Erro ao salvar motorista:', error)
    errorMessage.value = error.message || 'Erro ao salvar motorista'
  } finally {
    savingMotorista.value = false
  }
}

const getCampoLabel = (campo: string): string => {
  return CAMPOS_LABELS[campo] || campo
}

// Lifecycle
onMounted(() => {
  console.log('VpoStep2Motorista mounted')
  console.log('isEmpresa:', props.formData.motorista.isEmpresa)
  console.log('codtrn:', codtrn.value)
  if (props.formData.motorista.isEmpresa) {
    carregarMotoristas()
  }
})

// Debug watcher para o dialog
watch(editDialog, (newVal) => {
  console.log('editDialog changed to:', newVal)
  if (newVal) {
    console.log('editingMotorista:', editingMotorista.value)
    console.log('motoristaForm:', motoristaForm.value)
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <h6 class="text-h6 font-weight-medium mb-2">
      Seleção de Motorista
    </h6>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Selecione o motorista para a viagem
    </p>

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

    <!-- Loading -->
    <div v-if="loading" class="d-flex flex-column gap-4">
      <VSkeletonLoader type="card" />
      <VSkeletonLoader type="card" />
    </div>

    <!-- Não é empresa (autônomo) -->
    <VAlert
      v-else-if="!formData.motorista.isEmpresa"
      type="success"
      variant="tonal"
      class="mb-4"
    >
      <template #prepend>
        <VIcon icon="tabler-user-check" />
      </template>
      <div>
        <div class="font-weight-medium mb-1">
          Transportador Autônomo
        </div>
        <div class="text-caption">
          Dados do motorista são os mesmos do transportador. Não é necessário selecionar.
        </div>
      </div>
    </VAlert>

    <!-- Lista de Motoristas -->
    <template v-else>
      <!-- Nenhum motorista -->
      <VAlert
        v-if="motoristas.length === 0"
        type="warning"
        variant="tonal"
      >
        <template #prepend>
          <VIcon icon="tabler-alert-triangle" />
        </template>
        <div>
          <div class="font-weight-medium mb-1">
            Nenhum Motorista Cadastrado
          </div>
          <div class="text-caption">
            Esta empresa não possui motoristas cadastrados no sistema.
            Será necessário cadastrar um motorista no Progress.
          </div>
        </div>
      </VAlert>

      <!-- Cards de Motoristas -->
      <div v-else class="d-flex flex-column gap-4">
        <!-- Info sobre motoristas completos -->
        <VAlert
          v-if="motoristasCompletos.length === 0"
          type="error"
          variant="tonal"
        >
          <template #prepend>
            <VIcon icon="tabler-alert-circle" />
          </template>
          <div>
            <div class="font-weight-medium mb-1">
              Nenhum Motorista Pronto para VPO
            </div>
            <div class="text-caption">
              Complete os dados de pelo menos um motorista clicando no botão "Completar Dados".
            </div>
          </div>
        </VAlert>

        <!-- Lista de motoristas -->
        <VCard
          v-for="motorista in motoristas"
          :key="motorista.codmot"
          :variant="selectedCodmot === motorista.codmot ? 'tonal' : 'outlined'"
          :color="selectedCodmot === motorista.codmot ? 'primary' : undefined"
          class="cursor-pointer"
          @click="selecionarMotorista(motorista)"
        >
          <VCardItem>
            <template #prepend>
              <VAvatar
                :color="motorista.dados_completos ? 'success' : 'warning'"
                variant="tonal"
              >
                <VIcon
                  :icon="motorista.dados_completos ? 'tabler-user-check' : 'tabler-user-exclamation'"
                />
              </VAvatar>
            </template>

            <VCardTitle>{{ motorista.nommot }}</VCardTitle>

            <VCardSubtitle>
              <VChip
                :color="motorista.dados_completos ? 'success' : 'warning'"
                size="x-small"
                class="me-2"
              >
                {{ motorista.dados_completos ? 'Completo' : 'Incompleto' }}
              </VChip>
              <span v-if="motorista.cpf" class="text-caption">
                CPF: {{ motorista.cpf }}
              </span>
            </VCardSubtitle>

            <template #append>
              <div class="d-flex gap-2">
                <VBtn
                  v-if="!motorista.dados_completos"
                  size="small"
                  color="warning"
                  variant="tonal"
                  @click.stop="editarMotorista(motorista)"
                >
                  <VIcon icon="tabler-edit" start />
                  Completar
                </VBtn>

                <VBtn
                  v-else-if="selectedCodmot !== motorista.codmot"
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click.stop="selecionarMotorista(motorista)"
                >
                  Selecionar
                </VBtn>

                <VIcon
                  v-else
                  icon="tabler-check"
                  color="primary"
                  size="24"
                />
              </div>
            </template>
          </VCardItem>

          <!-- Campos faltantes -->
          <VCardText v-if="!motorista.dados_completos && motorista.campos_faltantes.length > 0" class="pt-0">
            <div class="text-caption text-error">
              <VIcon icon="tabler-alert-triangle" size="14" class="me-1" />
              Faltam: {{ motorista.campos_faltantes.map(getCampoLabel).join(', ') }}
            </div>
          </VCardText>
        </VCard>
      </div>
    </template>

    <!-- Dialog de Edição -->
    <VDialog v-model="editDialog" max-width="600" persistent>
      <VCard v-if="editingMotorista">
        <VCardTitle class="d-flex justify-space-between align-center">
          <span>Completar Dados do Motorista</span>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            @click="editDialog = false"
          />
        </VCardTitle>

        <VCardText>
          <h6 class="text-subtitle-1 font-weight-medium mb-4">
            {{ editingMotorista.nommot }}
          </h6>

          <!-- Erro ao salvar -->
          <VAlert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-4"
            closable
            @click:close="errorMessage = null"
          >
            {{ errorMessage }}
          </VAlert>

          <!-- Campos faltantes alert -->
          <VAlert
            v-if="editingMotorista.campos_faltantes.length > 0"
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            <template #prepend>
              <VIcon icon="tabler-alert-triangle" />
            </template>
            <span class="text-caption">
              Campos obrigatórios: {{ editingMotorista.campos_faltantes.map(getCampoLabel).join(', ') }}
            </span>
          </VAlert>

          <VForm @submit.prevent="salvarMotorista">
            <VRow>
              <!-- CPF -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="motoristaForm.cpf"
                  label="CPF *"
                  placeholder="000.000.000-00"
                  :error="editingMotorista.campos_faltantes.includes('cpf')"
                />
              </VCol>

              <!-- RNTRC -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="motoristaForm.rntrc"
                  label="RNTRC *"
                  placeholder="Registro ANTT"
                  :error="editingMotorista.campos_faltantes.includes('rntrc')"
                />
              </VCol>

              <!-- Nome da Mãe -->
              <VCol cols="12">
                <VTextField
                  v-model="motoristaForm.nommae"
                  label="Nome da Mãe *"
                  placeholder="Nome completo da mãe"
                  :error="editingMotorista.campos_faltantes.includes('nommae')"
                />
              </VCol>

              <!-- Data Nascimento -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="motoristaForm.data_nascimento"
                  label="Data de Nascimento *"
                  type="date"
                  :error="editingMotorista.campos_faltantes.includes('data_nascimento')"
                />
              </VCol>

              <!-- CNH (opcional) -->
              <VCol cols="12" md="6">
                <VTextField
                  v-model="motoristaForm.cnh"
                  label="CNH"
                  placeholder="Número da CNH"
                />
              </VCol>
            </VRow>
          </VForm>

          <!-- Dados do Progress (readonly) -->
          <VDivider class="my-4" />
          <p class="text-caption text-medium-emphasis mb-2">
            Dados do Progress (somente leitura)
          </p>

          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                :model-value="editingMotorista.numrg"
                label="RG"
                readonly
                variant="plain"
                density="compact"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                :model-value="editingMotorista.nompai"
                label="Nome do Pai"
                readonly
                variant="plain"
                density="compact"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="editDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="savingMotorista"
            @click="salvarMotorista"
          >
            Salvar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
