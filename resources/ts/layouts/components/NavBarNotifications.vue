<script lang="ts" setup>
import { ref, onMounted, computed } from 'vue'
import { $api } from '@/utils/api'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

interface Notification {
  id: number
  title: string
  message: string
  type: 'info' | 'success' | 'warning' | 'error'
  icon: string
  link: string | null
  is_read: boolean
  time_ago: string
  created_at: string
  creator_name: string
}

const router = useRouter()

// State
const notifications = ref<Notification[]>([])
const unreadCount = ref(0)
const loading = ref(false)
const menuOpen = ref(false)

// Computed
const hasUnread = computed(() => unreadCount.value > 0)

// Color por tipo
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
    const response = await $api('/notifications')
    if (response.success) {
      notifications.value = response.data.notifications
      unreadCount.value = response.data.unread_count
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
    if (notification && !notification.is_read) {
      notification.is_read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
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
    if (notification && notification.is_read) {
      notification.is_read = false
      unreadCount.value++
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
    unreadCount.value = 0
  } catch (error) {
    console.error('Erro ao marcar todas como lidas:', error)
  }
}

// Toggle read/unread
const toggleReadStatus = (notification: Notification) => {
  if (notification.is_read) {
    markAsUnread(notification.id)
  } else {
    markAsRead(notification.id)
  }
}

// Ao clicar na notificação
const handleNotificationClick = (notification: Notification) => {
  if (!notification.is_read) {
    markAsRead(notification.id)
  }
  if (notification.link) {
    router.push(notification.link)
    menuOpen.value = false
  }
}

// Ver todas
const viewAllNotifications = () => {
  router.push({ name: 'notificacoes' })
  menuOpen.value = false
}

// Carregar ao montar
onMounted(() => {
  loadNotifications()
  // Recarregar a cada 60 segundos
  setInterval(loadNotifications, 60000)
})
</script>

<template>
  <IconBtn id="notification-btn">
    <VBadge
      :model-value="hasUnread"
      color="error"
      dot
      offset-x="2"
      offset-y="3"
    >
      <VIcon icon="tabler-bell" />
    </VBadge>

    <VMenu
      v-model="menuOpen"
      activator="parent"
      width="380px"
      location="bottom end"
      offset="12px"
      :close-on-content-click="false"
    >
      <VCard class="d-flex flex-column">
        <!-- Header -->
        <VCardItem class="notification-section">
          <VCardTitle class="text-h6">
            Notificações
          </VCardTitle>

          <template #append>
            <VChip
              v-if="hasUnread"
              size="small"
              color="primary"
              class="me-2"
            >
              {{ unreadCount }} Nova(s)
            </VChip>
            <IconBtn
              v-if="notifications.length && hasUnread"
              size="34"
              @click="markAllAsRead"
            >
              <VIcon
                size="20"
                color="high-emphasis"
                icon="tabler-mail-opened"
              />
              <VTooltip activator="parent" location="start">
                Marcar todas como lidas
              </VTooltip>
            </IconBtn>
            <IconBtn
              size="34"
              @click="loadNotifications"
            >
              <VIcon
                size="20"
                color="high-emphasis"
                icon="tabler-refresh"
                :class="{ 'animate-spin': loading }"
              />
              <VTooltip activator="parent" location="start">
                Atualizar
              </VTooltip>
            </IconBtn>
          </template>
        </VCardItem>

        <VDivider />

        <!-- Loading -->
        <div v-if="loading && !notifications.length" class="pa-4 text-center">
          <VProgressCircular indeterminate size="32" />
        </div>

        <!-- Lista de notificações -->
        <PerfectScrollbar
          v-else
          :options="{ wheelPropagation: false }"
          style="max-block-size: 23.75rem;"
        >
          <VList class="notification-list rounded-0 py-0">
            <template
              v-for="(notification, index) in notifications"
              :key="notification.id"
            >
              <VDivider v-if="index > 0" />
              <VListItem
                link
                lines="one"
                min-height="66px"
                class="list-item-hover-class"
                @click="handleNotificationClick(notification)"
              >
                <div class="d-flex align-start gap-3">
                  <VAvatar
                    :color="getTypeColor(notification.type)"
                    variant="tonal"
                  >
                    <VIcon :icon="notification.icon" />
                  </VAvatar>

                  <div class="flex-grow-1" style="min-width: 0;">
                    <p class="text-sm font-weight-medium mb-1 text-truncate">
                      {{ notification.title }}
                    </p>
                    <p
                      class="text-body-2 mb-2 text-truncate-2"
                      style="letter-spacing: 0.4px !important; line-height: 18px;"
                    >
                      {{ notification.message }}
                    </p>
                    <p
                      class="text-sm text-disabled mb-0"
                      style="letter-spacing: 0.4px !important; line-height: 18px;"
                    >
                      {{ notification.time_ago }}
                    </p>
                  </div>

                  <div class="d-flex flex-column align-end flex-shrink-0">
                    <VIcon
                      size="10"
                      icon="tabler-circle-filled"
                      :color="!notification.is_read ? 'primary' : '#a8aaae'"
                      :class="{ 'visible-in-hover': notification.is_read }"
                      class="mb-2 cursor-pointer"
                      @click.stop="toggleReadStatus(notification)"
                    />
                  </div>
                </div>
              </VListItem>
            </template>

            <VListItem
              v-if="!notifications.length && !loading"
              class="text-center text-medium-emphasis"
              style="block-size: 56px;"
            >
              <VListItemTitle>Nenhuma notificação</VListItemTitle>
            </VListItem>
          </VList>
        </PerfectScrollbar>

        <VDivider />

        <!-- Footer -->
        <VCardText class="pa-4">
          <VBtn
            block
            size="small"
            @click="viewAllNotifications"
          >
            Ver Todas as Notificações
          </VBtn>
        </VCardText>
      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style lang="scss">
.notification-section {
  padding-block: 0.75rem;
  padding-inline: 1rem;
}

.list-item-hover-class {
  .visible-in-hover {
    display: none;
  }

  &:hover {
    .visible-in-hover {
      display: block;
    }
  }
}

.notification-list.v-list {
  .v-list-item {
    border-radius: 0 !important;
    margin: 0 !important;
    padding-block: 0.75rem !important;
  }
}

.text-truncate-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.cursor-pointer {
  cursor: pointer;
}
</style>
