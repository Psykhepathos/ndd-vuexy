<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const motoristas = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Código Progress', key: 'codigo_progress' },
  { title: 'Nome', key: 'nome' },
  { title: 'CPF', key: 'cpf' },
  { title: 'CNH', key: 'cnh' },
  { title: 'Telefone', key: 'telefone' },
  { title: 'Email', key: 'email' },
  { title: 'Status', key: 'status' },
  { title: 'Ações', key: 'actions', sortable: false }
]

const carregarMotoristas = async () => {
  try {
    loading.value = true
    error.value = ''
    
    const response = await axios.get('http://localhost:8002/api/motoristas')
    if (response.data.success) {
      motoristas.value = response.data.data
    } else {
      error.value = 'Erro ao carregar motoristas'
    }
  } catch (err) {
    error.value = 'Erro de conexão com a API'
    console.error('Erro:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  carregarMotoristas()
})

const getStatusColor = (status: string) => {
  switch (status) {
    case 'ativo': return 'success'
    case 'inativo': return 'error'
    case 'suspenso': return 'warning'
    default: return 'default'
  }
}

const filteredMotoristas = computed(() => {
  if (!search.value) return motoristas.value
  
  return motoristas.value.filter((motorista: any) => 
    motorista.nome.toLowerCase().includes(search.value.toLowerCase()) ||
    motorista.cpf.includes(search.value) ||
    motorista.codigo_progress.toLowerCase().includes(search.value.toLowerCase())
  )
})
</script>

<template>
  <VRow>
    <!-- Cabeçalho do Dashboard -->
    <VCol cols="12">
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VIcon 
              icon="mdi-truck" 
              class="me-3" 
              color="primary" 
              size="32"
            />
            <div>
              <h2 class="mb-0">Sistema NDD - Gestão de Transporte</h2>
              <p class="text-body-2 mb-0">Dashboard de Integração Vale Pedágio e CIOT</p>
            </div>
          </div>
          <VBtn
            color="primary"
            prepend-icon="mdi-refresh"
            @click="carregarMotoristas"
            :loading="loading"
          >
            Atualizar
          </VBtn>
        </VCardTitle>
      </VCard>
    </VCol>

    <!-- Cards de Resumo -->
    <VCol cols="12" md="3">
      <VCard>
        <VCardText class="text-center">
          <VIcon 
            icon="mdi-account-group" 
            size="40" 
            color="primary" 
            class="mb-3"
          />
          <h3 class="text-h5 mb-2">{{ motoristas.length }}</h3>
          <p class="text-body-2 mb-0">Total de Motoristas</p>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12" md="3">
      <VCard>
        <VCardText class="text-center">
          <VIcon 
            icon="mdi-account-check" 
            size="40" 
            color="success" 
            class="mb-3"
          />
          <h3 class="text-h5 mb-2">
            {{ motoristas.filter(m => m.status === 'ativo').length }}
          </h3>
          <p class="text-body-2 mb-0">Motoristas Ativos</p>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12" md="3">
      <VCard>
        <VCardText class="text-center">
          <VIcon 
            icon="mdi-account-off" 
            size="40" 
            color="error" 
            class="mb-3"
          />
          <h3 class="text-h5 mb-2">
            {{ motoristas.filter(m => m.status === 'inativo').length }}
          </h3>
          <p class="text-body-2 mb-0">Motoristas Inativos</p>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12" md="3">
      <VCard>
        <VCardText class="text-center">
          <VIcon 
            icon="mdi-database-check" 
            size="40" 
            color="info" 
            class="mb-3"
          />
          <h3 class="text-h5 mb-2">API</h3>
          <p class="text-body-2 mb-0">Conectado via REST</p>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Tabela de Motoristas -->
    <VCol cols="12">
      <VCard>
        <VCardTitle>
          Lista de Motoristas
        </VCardTitle>
        
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="search"
                label="Buscar motoristas..."
                prepend-inner-icon="mdi-magnify"
                clearable
                variant="outlined"
                density="compact"
              />
            </VCol>
          </VRow>

          <VAlert 
            v-if="error" 
            type="error" 
            class="mb-4"
            closable
            @click:close="error = ''"
          >
            {{ error }}
          </VAlert>

          <VDataTable
            :headers="headers"
            :items="filteredMotoristas"
            :loading="loading"
            loading-text="Carregando motoristas..."
            no-data-text="Nenhum motorista encontrado"
            items-per-page="10"
            class="elevation-1"
          >
            <template #item.status="{ item }">
              <VChip
                :color="getStatusColor(item.status)"
                size="small"
                variant="elevated"
              >
                {{ item.status }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <VTooltip text="Visualizar">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="mdi-eye"
                    size="small"
                    variant="text"
                    color="primary"
                  />
                </template>
              </VTooltip>

              <VTooltip text="Editar">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="mdi-pencil"
                    size="small"
                    variant="text"
                    color="info"
                  />
                </template>
              </VTooltip>

              <VTooltip text="Excluir">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    color="error"
                  />
                </template>
              </VTooltip>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.elevation-1 {
  box-shadow: 0px 2px 1px -1px rgba(0,0,0,0.2), 0px 1px 1px 0px rgba(0,0,0,0.14), 0px 1px 3px 0px rgba(0,0,0,0.12);
}
</style>