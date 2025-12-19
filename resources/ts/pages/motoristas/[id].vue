<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiPost, getApiUrl } from '@/config/api'

// Interface para tipagem
interface Motorista {
  codtrn: number
  nomtrn: string
  codcnpjcpf: string
  desend: string
  numend: string
  cplend: string
  numceptrn: number
  numtel: number
  dddtel: number
  numcel: number
  dddcel: number
  'e-mail': string
  numpla: string
  natcam: string
  tipcam: number
  flgati: boolean
  indcd: string
  numhab: string
  venhab: string
  esthab: string
  cathab: string
  datnas: string
  orgrg: string
  numrg: string
  exprg: string
  renavam: string
  numcha: string
  fabmod: string
  marvei: string
  corvei: string
  ufvei: string
  desvei: string
}

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const motorista = ref<Motorista | null>(null)

// Buscar dados do motorista
const fetchMotorista = async () => {
  try {
    loading.value = true
    // Usando a tabela trnmot do Progress para motoristas
    const response = await apiPost(getApiUrl(`/transportes/query`), {
      sql: `SELECT codtrn, nomtrn, codcnpjcpf, desend, numend, cplend, numceptrn, numtel, dddtel, numcel, dddcel,
            "e-mail", numpla, natcam, tipcam, flgati, indcd, numhab, venhab, esthab, cathab, datnas, orgrg, numrg, exprg,
            renavam, numcha, fabmod, marvei, corvei, ufvei, desvei
            FROM PUB.trnmot WHERE codtrn = ${route.params.id}`
    })
    const result = await response.json()
    
    if (result.success && result.data.results?.length > 0) {
      motorista.value = result.data.results[0]
    } else {
      console.error('Motorista não encontrado')
    }
  } catch (error) {
    console.error('Erro na requisição:', error)
  } finally {
    loading.value = false
  }
}

// Computed properties
const telefoneFormatado = computed(() => {
  if (!motorista.value?.dddtel || !motorista.value?.numtel) return 'N/D'
  const tel = motorista.value.numtel.toString()
  return `(${motorista.value.dddtel}) ${tel.replace(/(\d{4,5})(\d{4})/, '$1-$2')}`
})

const celularFormatado = computed(() => {
  if (!motorista.value?.dddcel || !motorista.value?.numcel) return 'N/D'
  const cel = motorista.value.numcel.toString()
  return `(${motorista.value.dddcel}) ${cel.replace(/(\d{5})(\d{4})/, '$1-$2')}`
})

const enderecoCompleto = computed(() => {
  if (!motorista.value?.desend) return 'N/D'
  let endereco = motorista.value.desend.toUpperCase()
  if (motorista.value.numend) endereco += `, ${motorista.value.numend}`
  if (motorista.value.cplend) endereco += `, ${motorista.value.cplend.toUpperCase()}`
  return endereco
})

const placaFormatada = computed(() => {
  if (!motorista.value?.numpla) return null
  const placa = motorista.value.numpla.toUpperCase().replace(/[^A-Z0-9]/g, '')
  if (/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/.test(placa)) {
    return placa.substring(0, 3) + '-' + placa.substring(3, 4) + placa.substring(4, 5) + placa.substring(5, 7)
  } else if (/^[A-Z]{3}[0-9]{4}$/.test(placa)) {
    return placa.substring(0, 3) + '-' + placa.substring(3)
  }
  return placa
})

const cpfFormatado = computed(() => {
  if (!motorista.value?.codcnpjcpf) return 'N/D'
  const cpf = motorista.value.codcnpjcpf.replace(/\D/g, '')
  if (cpf.length === 11) {
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
  }
  return motorista.value.codcnpjcpf
})

const statusCnh = computed(() => {
  if (!motorista.value?.venhab) return 'NÃO INFORMADO'
  const vencimento = new Date(motorista.value.venhab)
  const hoje = new Date()
  const diasParaVencer = Math.ceil((vencimento.getTime() - hoje.getTime()) / (1000 * 3600 * 24))
  
  if (diasParaVencer < 0) return 'VENCIDA'
  if (diasParaVencer <= 30) return 'PRÓXIMA AO VENCIMENTO'
  return 'VÁLIDA'
})

const corStatusCnh = computed(() => {
  const status = statusCnh.value
  if (status === 'VENCIDA') return 'error'
  if (status === 'PRÓXIMA AO VENCIMENTO') return 'warning'
  if (status === 'VÁLIDA') return 'success'
  return 'default'
})

const idade = computed(() => {
  if (!motorista.value?.datnas) return 'N/D'
  const nascimento = new Date(motorista.value.datnas)
  const hoje = new Date()
  let idade = hoje.getFullYear() - nascimento.getFullYear()
  const m = hoje.getMonth() - nascimento.getMonth()
  if (m < 0 || (m === 0 && hoje.getDate() < nascimento.getDate())) {
    idade--
  }
  return `${idade} anos`
})

const voltar = () => {
  router.push({ name: 'transportes' })
}

onMounted(() => {
  fetchMotorista()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <!-- Header -->
      <div class="d-flex align-center justify-space-between mb-6">
        <div class="d-flex align-center">
          <VBtn
            icon
            variant="text"
            color="default"
            @click="voltar"
            class="me-3"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <div>
            <h4 class="text-h4 font-weight-medium mb-0">Detalhes do Motorista</h4>
            <p class="text-body-2 mb-0 text-medium-emphasis">Sistema Progress</p>
          </div>
        </div>

        <VChip
          :color="motorista?.flgati ? 'success' : 'error'"
          size="small"
        >
          {{ motorista?.flgati ? 'ATIVO' : 'INATIVO' }}
        </VChip>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="d-flex justify-center pa-12">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <!-- Content -->
      <div v-else-if="motorista">
        <!-- Info Principal -->
        <VCard class="mb-6">
          <VCardText>
            <VRow>
              <VCol cols="12" md="8">
                <div class="d-flex align-center mb-4">
                  <VAvatar
                    size="64"
                    color="success"
                    variant="tonal"
                    class="me-4"
                  >
                    <VIcon icon="tabler-user" size="32" />
                  </VAvatar>
                  <div>
                    <h5 class="text-h5 font-weight-medium mb-1">{{ motorista.nomtrn.toUpperCase() }}</h5>
                    <VChip
                      color="success"
                      size="small"
                      variant="tonal"
                      class="me-2"
                    >
                      MOTORISTA
                    </VChip>
                    <VChip
                      color="primary"
                      size="small"
                      variant="outlined"
                    >
                      #{{ motorista.codtrn }}
                    </VChip>
                  </div>
                </div>

                <!-- Informações Pessoais -->
                <VRow>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">CPF</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ cpfFormatado }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">IDADE</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ idade }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">RG</small>
                      <p class="text-body-1 mb-0 font-weight-medium">
                        {{ motorista.numrg || 'N/D' }}
                        <span v-if="motorista.orgrg" class="text-medium-emphasis"> - {{ motorista.orgrg }}</span>
                      </p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">EMAIL</small>
                      <p class="text-body-1 mb-0 font-weight-medium">
                        {{ motorista['e-mail']?.toUpperCase() || 'NÃO INFORMADO' }}
                      </p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">TELEFONE</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ telefoneFormatado }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">CELULAR</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ celularFormatado }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">ENDEREÇO</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ enderecoCompleto }}</p>
                    </div>
                  </VCol>
                </VRow>
              </VCol>

              <!-- CNH Info -->
              <VCol cols="12" md="4">
                <VCard variant="tonal" :color="corStatusCnh" class="h-100">
                  <VCardText>
                    <div class="text-center">
                      <VIcon icon="tabler-id-badge-2" size="48" class="mb-2" />
                      <h6 class="text-h6 font-weight-medium mb-2">CARTEIRA NACIONAL DE HABILITAÇÃO</h6>
                      
                      <div v-if="motorista.numhab">
                        <VChip
                          :color="corStatusCnh"
                          variant="elevated"
                          class="font-mono font-weight-bold mb-2"
                        >
                          {{ motorista.cathab || 'N/D' }}
                        </VChip>
                        <p class="text-body-2 mb-1">
                          <strong>Número:</strong> {{ motorista.numhab }}
                        </p>
                        <p class="text-body-2 mb-1">
                          <strong>Estado:</strong> {{ motorista.esthab || 'N/D' }}
                        </p>
                        <p class="text-body-2 mb-1">
                          <strong>Vencimento:</strong> {{ motorista.venhab || 'N/D' }}
                        </p>
                        <VChip
                          :color="corStatusCnh"
                          size="small"
                          class="mt-2"
                        >
                          {{ statusCnh }}
                        </VChip>
                      </div>
                      <div v-else class="text-medium-emphasis">
                        CNH NÃO INFORMADA
                      </div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Veículo -->
        <VCard class="mb-6">
          <VCardTitle class="d-flex align-center">
            <VIcon icon="tabler-truck" class="me-2" />
            VEÍCULO
          </VCardTitle>
          
          <VCardText>
            <div v-if="placaFormatada">
              <VRow>
                <VCol cols="12" md="4">
                  <VCard variant="tonal" color="primary">
                    <VCardText class="text-center">
                      <VIcon icon="tabler-truck" size="32" class="mb-2" />
                      <VChip
                        color="primary"
                        variant="elevated"
                        class="font-mono font-weight-bold mb-2"
                        style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; letter-spacing: 1px;"
                      >
                        {{ placaFormatada }}
                      </VChip>
                      <p class="text-body-2 mb-1">
                        <strong>UF:</strong> {{ motorista.ufvei || 'N/D' }}
                      </p>
                    </VCardText>
                  </VCard>
                </VCol>
                
                <VCol cols="12" md="8">
                  <VRow>
                    <VCol cols="12" sm="6">
                      <div class="mb-3">
                        <small class="text-medium-emphasis">DESCRIÇÃO</small>
                        <p class="text-body-1 mb-0 font-weight-medium">{{ motorista.desvei?.toUpperCase() || 'N/D' }}</p>
                      </div>
                    </VCol>
                    <VCol cols="12" sm="6">
                      <div class="mb-3">
                        <small class="text-medium-emphasis">FABRICANTE/MODELO</small>
                        <p class="text-body-1 mb-0 font-weight-medium">{{ motorista.fabmod?.toUpperCase() || 'N/D' }}</p>
                      </div>
                    </VCol>
                    <VCol cols="12" sm="6">
                      <div class="mb-3">
                        <small class="text-medium-emphasis">MARCA</small>
                        <p class="text-body-1 mb-0 font-weight-medium">{{ motorista.marvei?.toUpperCase() || 'N/D' }}</p>
                      </div>
                    </VCol>
                    <VCol cols="12" sm="6">
                      <div class="mb-3">
                        <small class="text-medium-emphasis">COR</small>
                        <p class="text-body-1 mb-0 font-weight-medium">{{ motorista.corvei?.toUpperCase() || 'N/D' }}</p>
                      </div>
                    </VCol>
                    <VCol cols="12" sm="6">
                      <div class="mb-3">
                        <small class="text-medium-emphasis">RENAVAM</small>
                        <p class="text-body-1 mb-0 font-weight-medium">{{ motorista.renavam || 'N/D' }}</p>
                      </div>
                    </VCol>
                    <VCol cols="12" sm="6">
                      <div class="mb-3">
                        <small class="text-medium-emphasis">CHASSI</small>
                        <p class="text-body-1 mb-0 font-weight-medium">{{ motorista.numcha || 'N/D' }}</p>
                      </div>
                    </VCol>
                  </VRow>
                </VCol>
              </VRow>
            </div>
            
            <div v-else class="text-center py-8">
              <VIcon icon="tabler-truck-off" size="64" class="text-medium-emphasis mb-4" />
              <h6 class="text-h6 text-medium-emphasis">NENHUM VEÍCULO CADASTRADO</h6>
              <p class="text-body-2 text-medium-emphasis">Este motorista não possui veículo registrado no sistema.</p>
            </div>
          </VCardText>
        </VCard>

        <!-- Informações Adicionais -->
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon icon="tabler-info-circle" class="me-2" />
            INFORMAÇÕES ADICIONAIS
          </VCardTitle>
          
          <VCardText>
            <VRow>
              <VCol cols="12" sm="6">
                <div class="mb-3">
                  <small class="text-medium-emphasis">NATUREZA TRANSPORTE</small>
                  <p class="text-body-1 mb-0 font-weight-medium">
                    {{ motorista.natcam === 'T' ? 'TRANSPORTE' : motorista.natcam === 'A' ? 'AGREGADO' : 'N/D' }}
                  </p>
                </div>
              </VCol>
              <VCol cols="12" sm="6">
                <div class="mb-3">
                  <small class="text-medium-emphasis">TIPO CAMINHÃO</small>
                  <p class="text-body-1 mb-0 font-weight-medium">{{ motorista.tipcam || 'N/D' }}</p>
                </div>
              </VCol>
              <VCol cols="12" sm="6">
                <div class="mb-3">
                  <small class="text-medium-emphasis">TRANSPORTE CD</small>
                  <VChip
                    :color="motorista.indcd === 'S' ? 'success' : 'default'"
                    size="small"
                    variant="tonal"
                  >
                    {{ motorista.indcd === 'S' ? 'SIM' : 'NÃO' }}
                  </VChip>
                </div>
              </VCol>
              <VCol cols="12" sm="6">
                <div class="mb-3">
                  <small class="text-medium-emphasis">CEP</small>
                  <p class="text-body-1 mb-0 font-weight-medium">{{ motorista.numceptrn || 'N/D' }}</p>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </div>

      <!-- Erro -->
      <VCard v-else>
        <VCardText class="text-center">
          <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
          <h6 class="text-h6 text-error">MOTORISTA NÃO ENCONTRADO</h6>
          <p class="text-body-2 text-medium-emphasis">O motorista solicitado não foi encontrado.</p>
          <VBtn color="primary" @click="voltar">
            Voltar para Lista
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>