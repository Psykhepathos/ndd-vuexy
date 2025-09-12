<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

// Interface para tipagem
interface Transporte {
  codtrn: number
  nomtrn: string
  flgautonomo: boolean
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
  cathab: string
  datnas: string
  motoristas?: Motorista[]
  veiculos?: Veiculo[]
}

interface Motorista {
  codtrn: number
  codmot: number
  codcpf: string
  nommot: string
  desend: string
  codest: number
  codmun: number
  codbai: number
  numcep: number
  dddtel: number
  numtel: number
  dddtel1: number
  numtel1: number
  numhab: string
  nompai: string
  nommae: string
  sitmot: string
  desnac: string
  estciv: string
  codrntrc: string
  datvldrntrc: string
  venhab: string
  esthab: string
  cathab: string
  codmopp: string
  estmopp: string
  venmopp: string
  numrg: string
  orgrg: string
  exprg: string
  datnas: string | null
  numrenach: string
  sitseg: string
  datvenseg: string
  datprihab: string
  datemihab: string
  codseghab: string
  cplend: string
  numend: string
  tiplog: string
  codlog: number
  catmot: string
  desobs: string
  email: string
  flgpro: boolean
  datvldtox: string
}

interface Veiculo {
  numpla: string
  desvei: string
  fabmod: string
  marvei: string
  corvei: string
  ufvei: string
  renavam: string
  numcha: string
  tipcam: number
  natcam: string
  flgati: boolean
  pesmax: number
  volmax: number
  placar: string
}

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const transporte = ref<Transporte | null>(null)
const currentMotoristaIndex = ref(0)
const showMotoristaDialog = ref(false)

// Buscar dados do transportador
const fetchTransporte = async () => {
  try {
    loading.value = true
    const response = await fetch(`http://localhost:8002/api/transportes/${route.params.id}`, {
      headers: { 'Accept': 'application/json' }
    })
    
    const result = await response.json()
    
    if (result.success) {
      transporte.value = result.data
    } else {
      console.error('Erro ao carregar transportador:', result.message)
    }
  } catch (error) {
    console.error('Erro na requisição:', error)
  } finally {
    loading.value = false
  }
}

// Computed properties
const isAutonomo = computed(() => transporte.value?.flgautonomo || false)
const telefoneFormatado = computed(() => {
  if (!transporte.value?.dddtel || !transporte.value?.numtel) return 'N/D'
  const tel = transporte.value.numtel.toString()
  return `(${transporte.value.dddtel}) ${tel.replace(/(\d{4,5})(\d{4})/, '$1-$2')}`
})

const celularFormatado = computed(() => {
  if (!transporte.value?.dddcel || !transporte.value?.numcel) return 'N/D'
  const cel = transporte.value.numcel.toString()
  return `(${transporte.value.dddcel}) ${cel.replace(/(\d{5})(\d{4})/, '$1-$2')}`
})

const enderecoCompleto = computed(() => {
  if (!transporte.value?.desend) return 'N/D'
  let endereco = transporte.value.desend.toUpperCase()
  if (transporte.value.numend) endereco += `, ${transporte.value.numend}`
  if (transporte.value.cplend) endereco += `, ${transporte.value.cplend.toUpperCase()}`
  return endereco
})

const placaFormatada = computed(() => {
  if (!transporte.value?.numpla) return null
  const placa = transporte.value.numpla.toUpperCase().replace(/[^A-Z0-9]/g, '')
  if (/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/.test(placa)) {
    return placa.substring(0, 3) + '-' + placa.substring(3, 4) + placa.substring(4, 5) + placa.substring(5, 7)
  } else if (/^[A-Z]{3}[0-9]{4}$/.test(placa)) {
    return placa.substring(0, 3) + '-' + placa.substring(3)
  }
  return placa
})

const currentMotorista = computed(() => {
  if (!transporte.value?.motoristas?.length) return null
  return transporte.value.motoristas[currentMotoristaIndex.value]
})

// Funções de navegação
const nextMotorista = () => {
  if (transporte.value?.motoristas?.length) {
    currentMotoristaIndex.value = (currentMotoristaIndex.value + 1) % transporte.value.motoristas.length
  }
}

const prevMotorista = () => {
  if (transporte.value?.motoristas?.length) {
    currentMotoristaIndex.value = currentMotoristaIndex.value === 0 
      ? transporte.value.motoristas.length - 1 
      : currentMotoristaIndex.value - 1
  }
}

const formatMotoristaPhone = (ddd: number, phone: number) => {
  if (!ddd || !phone) return 'N/D'
  const tel = phone.toString()
  return `(${ddd}) ${tel.replace(/(\d{4,5})(\d{4})/, '$1-$2')}`
}

const formatCpf = (cpf: string) => {
  if (!cpf) return 'N/D'
  return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
}

const formatDate = (date: string) => {
  if (!date) return 'N/D'
  // Converter formato yyyy-mm-dd para dd/mm/yyyy
  const d = new Date(date)
  if (isNaN(d.getTime())) return date // Se não for uma data válida, retorna original
  return d.toLocaleDateString('pt-BR')
}

const formatAddress = (motorista: Motorista) => {
  if (!motorista.desend) return 'N/D'
  let address = `${motorista.tiplog || ''} ${motorista.desend}`.trim()
  if (motorista.numend) address += `, ${motorista.numend}`
  if (motorista.cplend) address += ` - ${motorista.cplend}`
  if (motorista.numcep) {
    const cep = motorista.numcep.toString().replace(/(\d{5})(\d{3})/, '$1-$2')
    address += ` - CEP: ${cep}`
  }
  return address.toUpperCase()
}

const voltar = () => {
  router.push('/transportes')
}

onMounted(() => {
  fetchTransporte()
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
            <h4 class="text-h4 font-weight-medium mb-0">Detalhes do Transportador</h4>
            <p class="text-body-2 mb-0 text-medium-emphasis">Sistema Progress</p>
          </div>
        </div>

        <VChip
          :color="transporte?.flgati ? 'success' : 'error'"
          size="small"
        >
          {{ transporte?.flgati ? 'ATIVO' : 'INATIVO' }}
        </VChip>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="d-flex justify-center pa-12">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <!-- Content -->
      <div v-else-if="transporte">
        <!-- Info Principal -->
        <VCard class="mb-6">
          <VCardText>
            <VRow>
              <VCol cols="12" md="8">
                <div class="d-flex align-center mb-4">
                  <VAvatar
                    size="64"
                    :color="isAutonomo ? 'success' : 'info'"
                    variant="tonal"
                    class="me-4"
                  >
                    <VIcon 
                      :icon="isAutonomo ? 'tabler-user' : 'tabler-building'"
                      size="32"
                    />
                  </VAvatar>
                  <div>
                    <h5 class="text-h5 font-weight-medium mb-1">{{ transporte.nomtrn.toUpperCase() }}</h5>
                    <VChip
                      :color="isAutonomo ? 'success' : 'info'"
                      size="small"
                      variant="tonal"
                      class="me-2"
                    >
                      {{ isAutonomo ? 'AUTÔNOMO' : 'EMPRESA' }}
                    </VChip>
                    <VChip
                      color="primary"
                      size="small"
                      variant="outlined"
                    >
                      #{{ transporte.codtrn }}
                    </VChip>
                  </div>
                </div>

                <!-- Informações -->
                <VRow>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">DOCUMENTO</small>
                      <p class="text-body-1 mb-0 font-weight-medium">
                        {{ transporte.codcnpjcpf || 'NÃO INFORMADO' }}
                      </p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">EMAIL</small>
                      <p class="text-body-1 mb-0 font-weight-medium">
                        {{ transporte['e-mail']?.toUpperCase() || 'NÃO INFORMADO' }}
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

              <!-- Veículo Principal -->
              <VCol cols="12" md="4">
                <VCard variant="tonal" color="primary" class="h-100">
                  <VCardText>
                    <div class="text-center">
                      <VIcon icon="tabler-truck" size="48" class="mb-2" />
                      <h6 class="text-h6 font-weight-medium mb-2">VEÍCULO PRINCIPAL</h6>
                      
                      <div v-if="placaFormatada">
                        <VChip
                          color="primary"
                          variant="elevated"
                          class="font-mono font-weight-bold mb-2"
                          style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; letter-spacing: 1px;"
                        >
                          {{ placaFormatada }}
                        </VChip>
                        <p class="text-body-2 mb-1">
                          <strong>Natureza:</strong> {{ transporte.natcam === 'T' ? 'TRANSPORTE' : 'AGREGADO' }}
                        </p>
                        <p class="text-body-2 mb-0">
                          <strong>Código:</strong> {{ transporte.tipcam || 'N/D' }}
                        </p>
                      </div>
                      <div v-else class="text-medium-emphasis">
                        SEM VEÍCULO CADASTRADO
                      </div>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Motoristas (apenas para empresas) -->
        <VCard v-if="!isAutonomo && transporte.motoristas?.length" class="mb-6">
          <VCardTitle class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <VIcon icon="tabler-users" class="me-2" />
              MOTORISTAS
            </div>
            <VChip size="small" color="info">
              {{ transporte.motoristas.length }} motorista(s)
            </VChip>
          </VCardTitle>
          
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <VBtn
                icon
                variant="outlined"
                :disabled="transporte.motoristas.length <= 1"
                @click="prevMotorista"
              >
                <VIcon icon="tabler-chevron-left" />
              </VBtn>
              
              <div class="text-center flex-grow-1">
                <small class="text-medium-emphasis">
                  {{ currentMotoristaIndex + 1 }} de {{ transporte.motoristas.length }}
                </small>
              </div>
              
              <VBtn
                icon
                variant="outlined"
                :disabled="transporte.motoristas.length <= 1"
                @click="nextMotorista"
              >
                <VIcon icon="tabler-chevron-right" />
              </VBtn>
            </div>

            <!-- Card do Motorista Atual -->
            <VCard v-if="currentMotorista" variant="tonal" color="success">
              <VCardText>
                <div class="d-flex align-center justify-space-between mb-4">
                  <div class="d-flex align-center">
                    <VAvatar
                      size="48"
                      color="success"
                      variant="tonal"
                      class="me-3"
                    >
                      <VIcon icon="tabler-user" size="24" />
                    </VAvatar>
                    <div>
                      <h6 class="text-h6 font-weight-medium mb-1">{{ currentMotorista.nommot.toUpperCase() }}</h6>
                      <VChip
                        :color="currentMotorista.sitmot === 'A' ? 'success' : 'error'"
                        size="small"
                      >
                        {{ currentMotorista.sitmot === 'A' ? 'ATIVO' : 'INATIVO' }}
                      </VChip>
                    </div>
                  </div>
                  
                  <VBtn
                    icon
                    variant="outlined"
                    size="small"
                    color="success"
                    @click="router.push(`/motoristas/${currentMotorista.codtrn}`)"
                  >
                    <VTooltip text="Ver detalhes do motorista" activator="parent" />
                    <VIcon icon="tabler-eye" size="18" />
                  </VBtn>
                </div>

                <VRow>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">CPF</small>
                      <p class="text-body-2 mb-0">{{ formatCpf(currentMotorista.codcpf) || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">CNH</small>
                      <p class="text-body-2 mb-0">{{ currentMotorista.numhab || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">CATEGORIA CNH</small>
                      <p class="text-body-2 mb-0">{{ currentMotorista.cathab || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">VENCIMENTO CNH</small>
                      <p class="text-body-2 mb-0">{{ formatDate(currentMotorista.venhab) || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">TELEFONE 1</small>
                      <p class="text-body-2 mb-0">{{ formatMotoristaPhone(currentMotorista.dddtel, currentMotorista.numtel) }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">TELEFONE 2</small>
                      <p class="text-body-2 mb-0">{{ formatMotoristaPhone(currentMotorista.dddtel1, currentMotorista.numtel1) }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">RNTRC</small>
                      <p class="text-body-2 mb-0">{{ currentMotorista.codrntrc || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">RENACH</small>
                      <p class="text-body-2 mb-0">{{ currentMotorista.numrenach || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">ENDEREÇO</small>
                      <p class="text-body-2 mb-0">{{ formatAddress(currentMotorista) || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">NOME DO PAI</small>
                      <p class="text-body-2 mb-0">{{ currentMotorista.nompai || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-2">
                      <small class="text-medium-emphasis">NOME DA MÃE</small>
                      <p class="text-body-2 mb-0">{{ currentMotorista.nommae || 'N/D' }}</p>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCardText>
        </VCard>

        <!-- Motorista Autônomo -->
        <VCard v-if="isAutonomo" class="mb-6">
          <VCardTitle class="d-flex align-center">
            <VIcon icon="tabler-user" class="me-2" />
            DADOS DO MOTORISTA AUTÔNOMO
          </VCardTitle>
          
          <VCardText>
            <VCard variant="tonal" color="success">
              <VCardText>
                <VRow>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">CNH</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ transporte.motoristas?.[0]?.numhab || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">CATEGORIA CNH</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ transporte.motoristas?.[0]?.cathab || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">VENCIMENTO CNH</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ formatDate(transporte.motoristas?.[0]?.venhab) || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">RNTRC</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ transporte.motoristas?.[0]?.codrntrc || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">TELEFONE 1</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ formatMotoristaPhone(transporte.motoristas?.[0]?.dddtel, transporte.motoristas?.[0]?.numtel) || 'N/D' }}</p>
                    </div>
                  </VCol>
                  <VCol cols="12" sm="6">
                    <div class="mb-3">
                      <small class="text-medium-emphasis">TELEFONE 2</small>
                      <p class="text-body-1 mb-0 font-weight-medium">{{ formatMotoristaPhone(transporte.motoristas?.[0]?.dddtel1, transporte.motoristas?.[0]?.numtel1) || 'N/D' }}</p>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCardText>
        </VCard>

        <!-- Sem motoristas (empresa) -->
        <VCard v-if="!isAutonomo && (!transporte.motoristas || transporte.motoristas.length === 0)" class="mb-6">
          <VCardText class="text-center">
            <VIcon icon="tabler-users-off" size="64" class="text-medium-emphasis mb-4" />
            <h6 class="text-h6 text-medium-emphasis">NENHUM MOTORISTA CADASTRADO</h6>
            <p class="text-body-2 text-medium-emphasis">Esta empresa não possui motoristas registrados no sistema.</p>
          </VCardText>
        </VCard>
      </div>

      <!-- Erro -->
      <VCard v-else>
        <VCardText class="text-center">
          <VIcon icon="tabler-alert-circle" size="64" color="error" class="mb-4" />
          <h6 class="text-h6 text-error">TRANSPORTADOR NÃO ENCONTRADO</h6>
          <p class="text-body-2 text-medium-emphasis">O transportador solicitado não foi encontrado.</p>
          <VBtn color="primary" @click="voltar">
            Voltar para Lista
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>