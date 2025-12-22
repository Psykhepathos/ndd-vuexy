<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { $api, getErrorMessage } from '@/utils/api'

interface Notification {
  id: number
  title: string
  message: string
  type: 'info' | 'success' | 'warning' | 'error'
  icon: string | null
  link: string | null
  is_active: boolean
  expires_at: string | null
  created_at: string
  creator_name: string
  reads_count: number
}

// State
const notifications = ref<Notification[]>([])
const loading = ref(false)
const search = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = ref(20)

// Dialog state
const dialogOpen = ref(false)
const dialogMode = ref<'create' | 'edit'>('create')
const dialogLoading = ref(false)
const selectedNotification = ref<Notification | null>(null)

// Form state
const form = ref({
  title: '',
  message: '',
  type: 'info' as 'info' | 'success' | 'warning' | 'error',
  icon: '',
  link: '',
  expires_at: '',
})

// Delete dialog
const deleteDialogOpen = ref(false)
const deleteLoading = ref(false)
const notificationToDelete = ref<Notification | null>(null)

// Options
const typeOptions = [
  { title: 'Informação', value: 'info', icon: 'tabler-info-circle', color: 'info' },
  { title: 'Sucesso', value: 'success', icon: 'tabler-circle-check', color: 'success' },
  { title: 'Aviso', value: 'warning', icon: 'tabler-alert-triangle', color: 'warning' },
  { title: 'Erro', value: 'error', icon: 'tabler-alert-circle', color: 'error' },
]

const iconOptions = [
  'tabler-info-circle',
  'tabler-circle-check',
  'tabler-alert-triangle',
  'tabler-alert-circle',
  'tabler-bell',
  'tabler-message',
  'tabler-mail',
  'tabler-news',
  'tabler-speakerphone',
  'tabler-gift',
  'tabler-star',
  'tabler-heart',
  'tabler-rocket',
  'tabler-tools',
  'tabler-settings',
]

// Headers da tabela
const headers = [
  { title: 'Notificação', key: 'title', sortable: false },
  { title: 'Tipo', key: 'type', width: '100px', sortable: false },
  { title: 'Status', key: 'is_active', width: '100px', sortable: false },
  { title: 'Leituras', key: 'reads_count', width: '100px', sortable: false },
  { title: 'Criado em', key: 'created_at', width: '150px', sortable: false },
  { title: 'Ações', key: 'actions', width: '120px', sortable: false, align: 'center' as const },
]

// Computed
const getTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    error: 'error',
  }
  return colors[type] || 'primary'
}

const getTypeIcon = (type: string) => {
  const icons: Record<string, string> = {
    info: 'tabler-info-circle',
    success: 'tabler-circle-check',
    warning: 'tabler-alert-triangle',
    error: 'tabler-alert-circle',
  }
  return icons[type] || 'tabler-bell'
}

// Carregar notificações
const loadNotifications = async () => {
  loading.value = true
  try {
    const response = await $api('/notifications/admin', {
      params: {
        search: search.value,
        page: currentPage.value,
        per_page: perPage.value,
      },
    })
    if (response.success) {
      notifications.value = response.data
      totalPages.value = response.meta.last_page
      totalItems.value = response.meta.total
    }
  } catch (error) {
    console.error('Erro ao carregar notificações:', error)
  } finally {
    loading.value = false
  }
}

// Abrir dialog para criar
const openCreateDialog = () => {
  dialogMode.value = 'create'
  selectedNotification.value = null
  form.value = {
    title: '',
    message: '',
    type: 'info',
    icon: '',
    link: '',
    expires_at: '',
  }
  dialogOpen.value = true
}

// Abrir dialog para editar
const openEditDialog = (notification: Notification) => {
  dialogMode.value = 'edit'
  selectedNotification.value = notification
  form.value = {
    title: notification.title,
    message: notification.message,
    type: notification.type,
    icon: notification.icon || '',
    link: notification.link || '',
    expires_at: notification.expires_at ? notification.expires_at.split('T')[0] : '',
  }
  dialogOpen.value = true
}

// Salvar notificação
const saveNotification = async () => {
  dialogLoading.value = true
  try {
    const payload = {
      title: form.value.title,
      message: form.value.message,
      type: form.value.type,
      icon: form.value.icon || null,
      link: form.value.link || null,
      expires_at: form.value.expires_at || null,
    }

    if (dialogMode.value === 'create') {
      await $api('/notifications/admin', {
        method: 'POST',
        body: payload,
      })
    } else if (selectedNotification.value) {
      await $api(`/notifications/admin/${selectedNotification.value.id}`, {
        method: 'PUT',
        body: payload,
      })
    }

    dialogOpen.value = false
    loadNotifications()
  } catch (error) {
    console.error('Erro ao salvar notificação:', error)
    alert(getErrorMessage(error))
  } finally {
    dialogLoading.value = false
  }
}

// Toggle status
const toggleStatus = async (notification: Notification) => {
  try {
    await $api(`/notifications/admin/${notification.id}`, {
      method: 'PUT',
      body: { is_active: !notification.is_active },
    })
    notification.is_active = !notification.is_active
  } catch (error) {
    console.error('Erro ao alterar status:', error)
  }
}

// Confirmar exclusão
const confirmDelete = (notification: Notification) => {
  notificationToDelete.value = notification
  deleteDialogOpen.value = true
}

// Excluir notificação
const deleteNotification = async () => {
  if (!notificationToDelete.value) return

  deleteLoading.value = true
  try {
    await $api(`/notifications/admin/${notificationToDelete.value.id}`, {
      method: 'DELETE',
    })
    deleteDialogOpen.value = false
    loadNotifications()
  } catch (error) {
    console.error('Erro ao excluir notificação:', error)
    alert(getErrorMessage(error))
  } finally {
    deleteLoading.value = false
  }
}

// Formatar data
const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('pt-BR')
}

// Watch search
let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    loadNotifications()
  }, 500)
})

watch(currentPage, () => {
  loadNotifications()
})

onMounted(() => {
  loadNotifications()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-y-4 mb-6">
      <div>
        <h4 class="text-h4 font-weight-medium mb-1">
          Gerenciar Notificações
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Crie e gerencie notificações para todos os usuários
        </p>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="openCreateDialog"
      >
        Nova Notificação
      </VBtn>
    </div>

    <!-- Search -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VTextField
              v-model="search"
              placeholder="Buscar notificações..."
              prepend-inner-icon="tabler-search"
              density="compact"
              hide-details
              clearable
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Table -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="notifications"
        :loading="loading"
        :items-per-page="perPage"
        hide-default-footer
      >
        <!-- Title -->
        <template #item.title="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              :color="getTypeColor(item.type)"
              variant="tonal"
              size="40"
            >
              <VIcon :icon="item.icon || getTypeIcon(item.type)" size="20" />
            </VAvatar>
            <div>
              <p class="text-body-1 font-weight-medium mb-0">{{ item.title }}</p>
              <p class="text-caption text-medium-emphasis mb-0 text-truncate" style="max-width: 300px;">
                {{ item.message }}
              </p>
            </div>
          </div>
        </template>

        <!-- Type -->
        <template #item.type="{ item }">
          <VChip
            :color="getTypeColor(item.type)"
            size="small"
            variant="tonal"
          >
            {{ item.type }}
          </VChip>
        </template>

        <!-- Status -->
        <template #item.is_active="{ item }">
          <VSwitch
            :model-value="item.is_active"
            color="success"
            hide-details
            density="compact"
            @click.stop="toggleStatus(item)"
          />
        </template>

        <!-- Reads -->
        <template #item.reads_count="{ item }">
          <VChip size="small" variant="tonal" color="info">
            {{ item.reads_count }}
          </VChip>
        </template>

        <!-- Date -->
        <template #item.created_at="{ item }">
          <span class="text-caption">{{ formatDate(item.created_at) }}</span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <IconBtn
              size="small"
              @click="openEditDialog(item)"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent" location="top">Editar</VTooltip>
            </IconBtn>
            <IconBtn
              size="small"
              color="error"
              @click="confirmDelete(item)"
            >
              <VIcon icon="tabler-trash" size="18" />
              <VTooltip activator="parent" location="top">Excluir</VTooltip>
            </IconBtn>
          </div>
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon icon="tabler-bell-off" size="48" color="disabled" class="mb-4" />
            <p class="text-body-1 text-medium-emphasis">Nenhuma notificação encontrada</p>
          </div>
        </template>
      </VDataTable>

      <!-- Pagination -->
      <VCardText v-if="totalPages > 1">
        <div class="d-flex justify-center">
          <VPagination
            v-model="currentPage"
            :length="totalPages"
            :total-visible="5"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- Create/Edit Dialog -->
    <VDialog
      v-model="dialogOpen"
      max-width="600"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 pa-4">
          <VIcon :icon="dialogMode === 'create' ? 'tabler-plus' : 'tabler-edit'" />
          {{ dialogMode === 'create' ? 'Nova Notificação' : 'Editar Notificação' }}
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-4">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="form.title"
                label="Título"
                placeholder="Digite o título da notificação"
                :rules="[v => !!v || 'Título é obrigatório']"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.message"
                label="Mensagem"
                placeholder="Digite a mensagem da notificação"
                rows="3"
                :rules="[v => !!v || 'Mensagem é obrigatória']"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect
                v-model="form.type"
                :items="typeOptions"
                item-title="title"
                item-value="value"
                label="Tipo"
              >
                <template #item="{ item, props }">
                  <VListItem v-bind="props">
                    <template #prepend>
                      <VIcon :icon="item.raw.icon" :color="item.raw.color" />
                    </template>
                  </VListItem>
                </template>
              </VSelect>
            </VCol>

            <VCol cols="12" md="6">
              <VSelect
                v-model="form.icon"
                :items="iconOptions"
                label="Ícone (opcional)"
                clearable
              >
                <template #item="{ item, props }">
                  <VListItem v-bind="props">
                    <template #prepend>
                      <VIcon :icon="item.value" />
                    </template>
                    <template #title>
                      {{ item.value?.replace('tabler-', '') }}
                    </template>
                  </VListItem>
                </template>
                <template #selection="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VIcon :icon="item.value" size="18" />
                    {{ item.value?.replace('tabler-', '') }}
                  </div>
                </template>
              </VSelect>
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="form.link"
                label="Link (opcional)"
                placeholder="/pagina-destino"
                hint="Link interno para onde o usuário será redirecionado"
                persistent-hint
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="form.expires_at"
                label="Expira em (opcional)"
                type="date"
                hint="Após esta data, a notificação não será mais exibida"
                persistent-hint
              />
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="dialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            :disabled="!form.title || !form.message"
            @click="saveNotification"
          >
            {{ dialogMode === 'create' ? 'Criar' : 'Salvar' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Dialog -->
    <VDialog
      v-model="deleteDialogOpen"
      max-width="400"
    >
      <VCard>
        <VCardTitle class="text-h6 pa-4">
          Confirmar Exclusão
        </VCardTitle>

        <VCardText>
          Tem certeza que deseja excluir a notificação
          <strong>"{{ notificationToDelete?.title }}"</strong>?
          Esta ação não pode ser desfeita.
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="deleteDialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            :loading="deleteLoading"
            @click="deleteNotification"
          >
            Excluir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
