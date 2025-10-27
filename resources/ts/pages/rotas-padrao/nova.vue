<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { API_ENDPOINTS, apiFetch } from '@/config/api'
import { useToast } from '@/composables/useToast'

// Composables
const router = useRouter()
const { showError, showSuccess } = useToast()

// Form data
const formData = ref({
  desspararrot: '',
  tempoviagem: 1,
  flgcd: false,
  flgretorno: false
})

const loading = ref(false)
const validationErrors = ref<string[]>([])
const showHelp = ref(false) // Controle para expandir/colapsar ajuda

/**
 * PADRÃO DE DESCRIÇÃO DA ROTA:
 *
 * Formato: [PREFIXO] - [REGIÃO/DETALHES INTROT](RETORNO)
 *
 * PREFIXOS VÁLIDOS:
 * - PP: Porta a Porta
 * - PR: Praça
 * - PC: (?)
 * - CD: Centro de Distribuição
 * - LG: Loja
 *
 * REGRAS:
 * 1. TUDO EM MAIÚSCULAS
 * 2. Deve começar com um dos prefixos válidos
 * 3. Após prefixo, usar " - " (espaço-hífen-espaço) para separar região
 * 4. Se for rota de retorno, deve conter "(RETORNO)" no final
 * 5. Pode incluir códigos de rotas internas (introt), ex: PR5,PR6
 *
 * EXEMPLOS VÁLIDOS:
 * - PP - UF MG PR5,PR6
 * - PP - UF MG PR5,PR6(RETORNO)
 * - PR - SUL SC/PR
 * - CD - GOIANIA
 * - LG - LOJA TESTE(RETORNO)
 */

// Prefixos válidos
const PREFIXOS_VALIDOS = ['PP', 'PR', 'PC', 'CD', 'LG']

// Função para validar padrão da descrição
const validateDescricao = (descricao: string): { valid: boolean; errors: string[] } => {
  const errors: string[] = []

  if (!descricao || descricao.trim() === '') {
    errors.push('Descrição é obrigatória')
    return { valid: false, errors }
  }

  // Converter para maiúsculas
  const desc = descricao.toUpperCase().trim()

  // Regra 1: Deve começar com um prefixo válido
  const prefixoMatch = PREFIXOS_VALIDOS.find(p => desc.startsWith(p))
  if (!prefixoMatch) {
    errors.push(`Deve começar com um dos prefixos: ${PREFIXOS_VALIDOS.join(', ')}`)
  }

  // Regra 2: Deve conter " - " após o prefixo
  if (prefixoMatch && !desc.includes(' - ')) {
    errors.push('Deve usar " - " (espaço-hífen-espaço) após o prefixo para separar a região')
  }

  // Regra 3: Se checkbox retorno estiver marcado, deve ter (RETORNO)
  if (formData.value.flgretorno && !desc.includes('(RETORNO)')) {
    errors.push('Rota de retorno deve conter "(RETORNO)" na descrição')
  }

  // Regra 4: Se não for retorno, não pode ter (RETORNO)
  if (!formData.value.flgretorno && desc.includes('(RETORNO)')) {
    errors.push('Descrição contém "(RETORNO)" mas checkbox não está marcado')
  }

  // Regra 5: Não pode ter caracteres minúsculos
  if (descricao !== desc) {
    errors.push('Descrição deve estar em MAIÚSCULAS')
  }

  // Regra 6: Mínimo de caracteres após formatação
  if (desc.length < 5) {
    errors.push('Descrição muito curta (mínimo 5 caracteres)')
  }

  return { valid: errors.length === 0, errors }
}

// Auto-formatação ao digitar (converte para maiúsculas)
watch(() => formData.value.desspararrot, (newValue) => {
  if (newValue) {
    formData.value.desspararrot = newValue.toUpperCase()
  }
})

// Auto-adicionar/remover (RETORNO) quando checkbox muda
watch(() => formData.value.flgretorno, (isRetorno) => {
  const desc = formData.value.desspararrot.trim()

  if (isRetorno && desc && !desc.includes('(RETORNO)')) {
    // Adicionar (RETORNO) no final
    formData.value.desspararrot = desc + '(RETORNO)'
  } else if (!isRetorno && desc.includes('(RETORNO)')) {
    // Remover (RETORNO)
    formData.value.desspararrot = desc.replace('(RETORNO)', '').trim()
  }
})

// Computed para mostrar preview da descrição formatada
const descricaoPreview = computed(() => {
  return formData.value.desspararrot.toUpperCase()
})

// Computed para validação em tempo real
const isDescricaoValid = computed(() => {
  if (!formData.value.desspararrot) return null // Não mostrar erro se vazio
  const result = validateDescricao(formData.value.desspararrot)
  validationErrors.value = result.errors
  return result.valid
})

// Actions
const goBack = () => {
  router.push('/rotas-padrao')
}

const createRoute = async () => {
  // Validação completa
  const validation = validateDescricao(formData.value.desspararrot)

  if (!validation.valid) {
    validation.errors.forEach(error => showError(error))
    return
  }

  if (formData.value.tempoviagem < 1 || formData.value.tempoviagem > 15) {
    showError('O tempo de viagem deve estar entre 1 e 15 dias')
    return
  }

  loading.value = true

  try {
    // Mapear campos do formulário para o formato da API
    const payload = {
      nome: formData.value.desspararrot.toUpperCase().trim(),
      tempo_viagem: formData.value.tempoviagem,
      flg_cd: formData.value.flgcd,
      flg_retorno: formData.value.flgretorno,
      municipios: []
    }

    const response = await apiFetch(API_ENDPOINTS.semPararRotas, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })

    const data = await response.json()

    if (data.success) {
      showSuccess('Rota criada com sucesso!')

      // Redirecionar para a página de edição/mapa da nova rota
      if (data.data && data.data.id) {
        router.push(`/rotas-padrao/mapa/${data.data.id}`)
      } else {
        router.push('/rotas-padrao')
      }
    } else {
      showError('Erro ao criar rota: ' + (data.message || 'Erro desconhecido'))
    }
  } catch (error) {
    console.error('Erro ao criar rota:', error)
    showError('Erro ao criar rota. Verifique sua conexão e tente novamente.')
  } finally {
    loading.value = false
  }
}

// Função helper para aplicar exemplo
const applyExample = (exemplo: string) => {
  formData.value.desspararrot = exemplo
  // Atualizar checkbox de retorno se exemplo contém (RETORNO)
  formData.value.flgretorno = exemplo.includes('(RETORNO)')
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center mb-6">
      <div class="d-flex align-center gap-2">
        <VBtn
          icon="tabler-arrow-left"
          variant="tonal"
          size="small"
          @click="goBack"
        />
        <div>
          <h4 class="text-h4 font-weight-medium mb-1">
            Nova Rota Padrão
          </h4>
          <p class="text-body-1 mb-0">
            Preencha os dados seguindo o padrão de nomenclatura
          </p>
        </div>
      </div>
    </div>

    <!-- Padrão de Nomenclatura Card (Colapsável) -->
    <VCard class="mb-6">
      <VCardText class="pa-4">
        <div
          class="d-flex align-center justify-space-between cursor-pointer"
          @click="showHelp = !showHelp"
        >
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-info-circle" color="info" size="20" />
            <span class="text-subtitle-1 font-weight-medium">Padrão de Nomenclatura</span>
          </div>
          <VBtn
            icon
            variant="text"
            size="small"
          >
            <VIcon :icon="showHelp ? 'tabler-chevron-up' : 'tabler-chevron-down'" />
          </VBtn>
        </div>

        <VExpandTransition>
          <div v-show="showHelp">
            <VDivider class="my-4" />

            <div class="mb-4">
              <p class="text-body-2 mb-2 text-medium-emphasis"><strong>Formato:</strong></p>
              <code class="bg-surface pa-2 rounded d-inline-block text-body-2">
                [PREFIXO] - [REGIÃO/DETALHES INTROT](RETORNO)
              </code>
            </div>

            <div class="mb-4">
              <p class="text-body-2 mb-2 text-medium-emphasis"><strong>Prefixos Válidos:</strong></p>
              <div class="d-flex flex-wrap gap-2">
                <VChip size="small" color="primary" variant="tonal" style="white-space: nowrap;"><strong>PP</strong> - Porta a Porta</VChip>
                <VChip size="small" color="primary" variant="tonal" style="white-space: nowrap;"><strong>PR</strong> - Praça</VChip>
                <VChip size="small" color="primary" variant="tonal" style="white-space: nowrap;"><strong>PC</strong> - (?)</VChip>
                <VChip size="small" color="primary" variant="tonal" style="white-space: nowrap;"><strong>CD</strong> - Centro de Distribuição</VChip>
                <VChip size="small" color="primary" variant="tonal" style="white-space: nowrap;"><strong>LG</strong> - Loja</VChip>
              </div>
            </div>

            <div>
              <p class="text-body-2 mb-2 text-medium-emphasis"><strong>Regras:</strong></p>
              <ul class="text-body-2 text-medium-emphasis" style="padding-left: 20px;">
                <li>Tudo em MAIÚSCULAS (automático)</li>
                <li>Usar " - " (espaço-hífen-espaço) após o prefixo</li>
                <li>Se for retorno, adicionar "(RETORNO)" no final (automático)</li>
                <li>Códigos de introt (ex: PR5,PR6) sem parênteses</li>
              </ul>
            </div>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Form Card -->
    <VCard>
      <VCardText>
        <VForm @submit.prevent="createRoute">
          <VRow>
            <!-- Descrição da Rota com Validação -->
            <VCol cols="12">
              <AppTextField
                v-model="formData.desspararrot"
                label="Descrição da Rota"
                placeholder="Ex: PP - UF MG PR5,PR6"
                :error="isDescricaoValid === false"
                :success="isDescricaoValid === true"
                required
              >
                <template #message v-if="validationErrors.length > 0">
                  <div v-for="(error, index) in validationErrors" :key="index" class="text-error text-caption">
                    {{ error }}
                  </div>
                </template>
              </AppTextField>

              <!-- Preview da descrição formatada -->
              <div v-if="formData.desspararrot" class="mt-2">
                <p class="text-caption text-medium-emphasis mb-1">Preview (formatado):</p>
                <VChip
                  :color="isDescricaoValid ? 'success' : 'error'"
                  variant="tonal"
                  size="small"
                >
                  {{ descricaoPreview }}
                </VChip>
              </div>
            </VCol>

            <!-- Tempo de Viagem -->
            <VCol cols="12" sm="6" md="4">
              <AppTextField
                v-model.number="formData.tempoviagem"
                label="Tempo de Viagem (dias)"
                type="number"
                min="1"
                max="15"
                placeholder="Ex: 5"
                required
              />
            </VCol>

            <!-- Spacer para forçar quebra de linha -->
            <VCol cols="12" class="py-0"></VCol>

            <!-- Switches em linha separada -->
            <VCol cols="12">
              <div class="d-flex flex-column flex-sm-row gap-6">
                <VSwitch
                  v-model="formData.flgcd"
                  color="info"
                  hide-details
                  inline
                >
                  <template #label>
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-building-warehouse" size="20" />
                      <span class="text-body-1">Centro de Distribuição</span>
                    </div>
                  </template>
                </VSwitch>

                <VSwitch
                  v-model="formData.flgretorno"
                  color="success"
                  hide-details
                  inline
                >
                  <template #label>
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-arrow-back-up" size="20" />
                      <span class="text-body-1">Rota de Retorno</span>
                      <VTooltip activator="parent" location="top">
                        Adiciona automaticamente "(RETORNO)" na descrição
                      </VTooltip>
                    </div>
                  </template>
                </VSwitch>
              </div>
            </VCol>

            <!-- Actions -->
            <VCol cols="12" class="d-flex gap-4 mt-4">
              <VBtn
                type="submit"
                color="primary"
                :loading="loading"
                :disabled="!isDescricaoValid"
                prepend-icon="tabler-check"
              >
                Criar Rota
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
                @click="goBack"
                :disabled="loading"
              >
                Cancelar
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Exemplos Válidos -->
    <VCard class="mt-6">
      <VCardText>
        <h6 class="text-h6 mb-4">
          Exemplos Válidos (clique para usar)
        </h6>
        <VChipGroup column>
          <VChip
            v-for="exemplo in [
              'PP - UF MG PR5,PR6',
              'PP - UF MG PR5,PR6(RETORNO)',
              'PR - SUL SC/PR',
              'PR - SUL SC/PR(RETORNO)',
              'PC - EXEMPLO TESTE',
              'PC - EXEMPLO TESTE(RETORNO)',
              'CD - GOIANIA',
              'CD - GOIANIA(RETORNO)',
              'LG - LOJA TESTE',
              'LG - LOJA TESTE(RETORNO)'
            ]"
            :key="exemplo"
            size="small"
            color="primary"
            variant="tonal"
            @click="applyExample(exemplo)"
            style="cursor: pointer;"
            prepend-icon="tabler-click"
          >
            {{ exemplo }}
          </VChip>
        </VChipGroup>
      </VCardText>
    </VCard>
  </div>
</template>
