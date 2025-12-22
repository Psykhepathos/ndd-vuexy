<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { $api } from '@/utils/api'

interface Notification {
  id: number
  title: string
  message: string
  type: 'info' | 'success' | 'warning' | 'error'
  icon: string
  link: string | null
  is_read: boolean
  read_at: string | null
  time_ago: string
  created_at: string
  creator_name: string
  is_active: boolean
  is_expired: boolean
}

// State
const notifications = ref<Notification[]>([])
const loading = ref(false)
const filter = ref<'all' | 'read' | 'unread'>('all')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = ref(20)

// Computed
const filterOptions = [
  { title: 'Todas', value: 'all' },
  { title: 'Não lidas', value: 'unread' },
  { title: 'Lidas', value: 'read' },
]

const getTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    error: 'error',
  }
  return colors[type] || 'primary'
}

// Carregar notificações
const loadNotifications = async () => {
  loading.value = true
  try {
    const response = await $api('/notifications/history', {
      params: {
        filter: filter.value,
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

// Marcar como lida
const markAsRead = async (id: number) => {
  try {
    await $api(`/notifications/${id}/read`, { method: 'POST' })
    const notification = notifications.value.find(n => n.id === id)
    if (notification) {
      notification.is_read = true
    }
  } catch (error) {
    console.error('Erro ao marcar como lida:', error)
  }
}

// Marcar como não lida
const markAsUnread = async (id: number) => {
  try {
    await $api(`/notifications/${id}/unread`, { method: 'POST' })
    const notification = notifications.value.find(n => n.id === id)
    if (notification) {
      notification.is_read = false
    }
  } catch (error) {
    console.error('Erro ao marcar como não lida:', error)
  }
}

// Marcar todas como lidas
const markAllAsRead = async () => {
  try {
    await $api('/notifications/read-all', { method: 'POST' })
    notifications.value.forEach(n => n.is_read = true)
  } catch (error) {
    console.error('Erro ao marcar todas como lidas:', error)
  }
}

// Formatar data
const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleString('pt-BR')
}

// Watch filter changes
watch(filter, () => {
  currentPage.value = 1
  loadNotifications()
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
          Minhas Notificações
        </h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Histórico de notificações recebidas
        </p>
      </div>

      <div class="d-flex gap-4">
        <VBtn
          v-if="notifications.some(n => !n.is_read)"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-checks"
          @click="markAllAsRead"
        >
          Marcar todas como lidas
        </VBtn>
      </div>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <VSelect
              v-model="filter"
              :items="filterOptions"
              item-title="title"
              item-value="value"
              label="Filtrar por"
              density="compact"
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Notifications List -->
    <VCard>
      <VCardText v-if="loading" class="text-center py-8">
        <VProgressCircular indeterminate size="48" />
        <p class="text-body-2 mt-4">Carregando notificações...</p>
      </VCardText>

      <VCardText v-else-if="!notifications.length" class="text-center py-8">
        <VIcon icon="tabler-bell-off" size="48" color="disabled" class="mb-4" />
        <p class="text-body-1 text-medium-emphasis">Nenhuma notificação encontrada</p>
      </VCardText>

      <template v-else>
        <VList lines="two">
          <template v-for="(notification, index) in notifications" :key="notification.id">
            <VDivider v-if="index > 0" />
            <VListItem
              class="pa-4"
            >
              <template #prepend>
                <VAvatar
                  :color="getTypeColor(notification.type)"
                  variant="tonal"
                  size="48"
                >
                  <VIcon :icon="notification.icon" size="24" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium mb-1">
                {{ notification.title }}
                <VChip
                  v-if="!notification.is_read"
                  size="x-small"
                  color="primary"
                  class="ms-2"
                >
                  Nova
                </VChip>
                <VChip
                  v-if="notification.is_expired"
                  size="x-small"
                  color="warning"
                  class="ms-2"
                >
                  Expirada
                </VChip>
              </VListItemTitle>

              <VListItemSubtitle class="text-body-2 mb-2">
                {{ notification.message }}
              </VListItemSubtitle>

              <div class="d-flex align-center gap-4 text-caption text-disabled">
                <span>
                  <VIcon icon="tabler-clock" size="14" class="me-1" />
                  {{ notification.time_ago }}
                </span>
                <span>
                  <VIcon icon="tabler-user" size="14" class="me-1" />
                  {{ notification.creator_name }}
                </span>
                <span v-if="notification.read_at">
                  <VIcon icon="tabler-eye" size="14" class="me-1" />
                  Lida em {{ formatDate(notification.read_at) }}
                </span>
              </div>

              <template #append>
                <div class="d-flex flex-column gap-2">
                  <VBtn
                    v-if="notification.link"
                    icon
                    size="small"
                    variant="text"
                    :href="notification.link"
                  >
                    <VIcon icon="tabler-external-link" size="18" />
                    <VTooltip activator="parent" location="top">Abrir link</VTooltip>
                  </VBtn>
                  <VBtn
                    icon
                    size="small"
                    variant="text"
                    @click="notification.is_read ? markAsUnread(notification.id) : markAsRead(notification.id)"
                  >
                    <VIcon
                      :icon="notification.is_read ? 'tabler-mail' : 'tabler-mail-opened'"
                      size="18"
                    />
                    <VTooltip activator="parent" location="top">
                      {{ notification.is_read ? 'Marcar como não lida' : 'Marcar como lida' }}
                    </VTooltip>
                  </VBtn>
                </div>
              </template>
            </VListItem>
          </template>
        </VList>

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
      </template>
    </VCard>
  </div>
</template>
