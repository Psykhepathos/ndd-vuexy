<script setup lang="ts">
import { VDataTableServer } from 'vuetify/components/VDataTable'

interface Role {
  id: number
  name: string
  display_name: string
  color: string
  icon: string
}

interface User {
  id: number
  name: string
  email: string
  role: string
  role_id: number | null
  role_relation: Role | null
  status: string
  password_reset_required: boolean
  created_at: string
}

interface Statistics {
  total: number
  admins: number
  users: number
  active: number
  inactive: number
  pending: number
  pendingPasswordReset: number
  thisWeek: number
  weekChange: number
}

interface AuditLog {
  id: number
  user_id: number
  performed_by: number | null
  action: string
  action_description: string
  action_color: string
  action_icon: string
  field_changed: string | null
  old_value: string | null
  new_value: string | null
  reason: string | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
  performer: { id: number; name: string; email: string } | null
}

definePage({
  meta: {
    action: 'read',
    subject: 'users',
  },
})

// State
const searchQuery = ref('')
const selectedRole = ref<string | null>(null)
const selectedStatus = ref<string | null>(null)
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('created_at')
const orderBy = ref('desc')
const isLoading = ref(false)
const isAddUserDialogVisible = ref(false)
const isEditUserDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const isResetPasswordDialogVisible = ref(false)
const isAuditDialogVisible = ref(false)
const selectedUser = ref<User | null>(null)
const users = ref<User[]>([])
const totalUsers = ref(0)
const statistics = ref<Statistics | null>(null)
const temporaryPassword = ref('')
const resetReason = ref('')
const auditLogs = ref<AuditLog[]>([])
const totalAuditLogs = ref(0)
const auditPage = ref(1)
const isLoadingAudit = ref(false)
const availableRoles = ref<Role[]>([])
const setupUrl = ref('')

// Form state
const newUser = ref({
  name: '',
  email: '',
  role_id: null as number | null,
  status: 'active',
})

const editUser = ref({
  id: 0,
  name: '',
  email: '',
  password: '',
  role_id: null as number | null,
  status: 'active',
  reason: '',
})

const statusOptions = [
  { title: 'Ativo', value: 'active' },
  { title: 'Inativo', value: 'inactive' },
  { title: 'Pendente', value: 'pending' },
]

// Role options para filtro (sistema legado)
const roleOptions = computed(() => [
  { title: 'Todos', value: null },
  ...availableRoles.value.map(role => ({
    title: role.display_name,
    value: role.name,
  })),
])

// Table headers
const headers = [
  { title: 'Usuário', key: 'user', sortable: false },
  { title: 'Perfil', key: 'role' },
  { title: 'Status', key: 'status' },
  { title: 'Criado em', key: 'created_at' },
  { title: 'Ações', key: 'actions', sortable: false, align: 'center' as const },
]

// Fetch users
const fetchUsers = async () => {
  isLoading.value = true
  try {
    const params = new URLSearchParams({
      page: page.value.toString(),
      itemsPerPage: itemsPerPage.value.toString(),
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    })

    if (searchQuery.value) params.append('q', searchQuery.value)
    if (selectedRole.value) params.append('role', selectedRole.value)
    if (selectedStatus.value) params.append('status', selectedStatus.value)

    const response = await $api(`/users?${params.toString()}`)
    users.value = response?.users || []
    totalUsers.value = response?.totalUsers || 0
  } catch (error: any) {
    console.error('Erro ao carregar usuários:', error)
    users.value = []
    totalUsers.value = 0
  } finally {
    isLoading.value = false
  }
}

// Fetch statistics
const fetchStatistics = async () => {
  try {
    const response = await $api('/users/statistics')
    statistics.value = response
  } catch (error) {
    console.error('Erro ao carregar estatísticas:', error)
  }
}

// Fetch roles
const fetchRoles = async () => {
  try {
    const response = await $api('/roles?itemsPerPage=-1')
    availableRoles.value = response?.roles || []
  } catch (error) {
    console.error('Erro ao carregar perfis:', error)
  }
}

// Create user
const createUser = async () => {
  try {
    const response = await $api('/users', {
      method: 'POST',
      body: newUser.value,
    })
    setupUrl.value = response?.setupUrl || ''
    isAddUserDialogVisible.value = false
    resetNewUserForm()
    fetchUsers()
    fetchStatistics()
  } catch (error) {
    console.error('Erro ao criar usuário:', error)
  }
}

// Update user
const updateUser = async () => {
  if (!editUser.value.id) return

  try {
    const payload: Record<string, any> = {
      name: editUser.value.name,
      email: editUser.value.email,
      role_id: editUser.value.role_id,
      status: editUser.value.status,
    }

    if (editUser.value.password) {
      payload.password = editUser.value.password
    }

    if (editUser.value.reason) {
      payload.reason = editUser.value.reason
    }

    await $api(`/users/${editUser.value.id}`, {
      method: 'PUT',
      body: payload,
    })
    isEditUserDialogVisible.value = false
    fetchUsers()
    fetchStatistics()
  } catch (error) {
    console.error('Erro ao atualizar usuário:', error)
  }
}

// Delete user
const deleteUser = async () => {
  if (!selectedUser.value) return

  try {
    await $api(`/users/${selectedUser.value.id}`, {
      method: 'DELETE',
    })
    isDeleteDialogVisible.value = false
    selectedUser.value = null
    fetchUsers()
    fetchStatistics()
  } catch (error) {
    console.error('Erro ao excluir usuário:', error)
  }
}

// Reset password
const resetPassword = async () => {
  if (!selectedUser.value) return

  try {
    const response = await $api(`/users/${selectedUser.value.id}/reset-password`, {
      method: 'POST',
      body: { reason: resetReason.value },
    })
    temporaryPassword.value = response.temporaryPassword
    fetchUsers()
    fetchStatistics()
  } catch (error) {
    console.error('Erro ao resetar senha:', error)
  }
}

// Fetch audit logs
const fetchAuditLogs = async (userId: number) => {
  isLoadingAudit.value = true
  try {
    const params = new URLSearchParams({
      page: auditPage.value.toString(),
      itemsPerPage: '10',
    })

    const response = await $api(`/users/${userId}/audit-logs?${params.toString()}`)
    auditLogs.value = response?.logs || []
    totalAuditLogs.value = response?.totalLogs || 0
  } catch (error) {
    console.error('Erro ao carregar auditoria:', error)
    auditLogs.value = []
    totalAuditLogs.value = 0
  } finally {
    isLoadingAudit.value = false
  }
}

// Open edit dialog
const openEditDialog = (user: User) => {
  editUser.value = {
    id: user.id,
    name: user.name,
    email: user.email,
    password: '',
    role_id: user.role_id,
    status: user.status,
    reason: '',
  }
  isEditUserDialogVisible.value = true
}

// Open delete dialog
const openDeleteDialog = (user: User) => {
  selectedUser.value = user
  isDeleteDialogVisible.value = true
}

// Open reset password dialog
const openResetPasswordDialog = (user: User) => {
  selectedUser.value = user
  resetReason.value = ''
  temporaryPassword.value = ''
  isResetPasswordDialogVisible.value = true
}

// Open audit dialog
const openAuditDialog = (user: User) => {
  selectedUser.value = user
  auditPage.value = 1
  fetchAuditLogs(user.id)
  isAuditDialogVisible.value = true
}

// Reset form
const resetNewUserForm = () => {
  newUser.value = {
    name: '',
    email: '',
    role_id: availableRoles.value.find(r => r.name === 'operador')?.id || null,
    status: 'active',
  }
}

// Table options update
const updateOptions = (options: any) => {
  if (options.sortBy?.[0]) {
    sortBy.value = options.sortBy[0].key
    orderBy.value = options.sortBy[0].order
  }
  fetchUsers()
}

// Helpers
const resolveRoleVariant = (user: User) => {
  if (user.role_relation) {
    return {
      color: user.role_relation.color,
      icon: user.role_relation.icon,
      text: user.role_relation.display_name,
    }
  }
  // Fallback para sistema legado
  if (user.role === 'admin')
    return { color: 'primary', icon: 'tabler-crown', text: 'Administrador' }
  return { color: 'info', icon: 'tabler-user', text: 'Usuário' }
}

const resolveStatusVariant = (status: string) => {
  if (status === 'active') return { color: 'success', text: 'Ativo' }
  if (status === 'inactive') return { color: 'secondary', text: 'Inativo' }
  return { color: 'warning', text: 'Pendente' }
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const formatDateTime = (dateString: string) => {
  return new Date(dateString).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
  } catch (error) {
    console.error('Erro ao copiar:', error)
  }
}

// Watchers
watch([searchQuery, selectedRole, selectedStatus], () => {
  page.value = 1
  fetchUsers()
})

watch(auditPage, () => {
  if (selectedUser.value) {
    fetchAuditLogs(selectedUser.value.id)
  }
})

// Initial fetch
onMounted(() => {
  fetchUsers()
  fetchStatistics()
  fetchRoles()
})
</script>

<template>
  <section>
    <!-- Statistics Cards -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex justify-space-between">
              <div class="d-flex flex-column gap-y-1">
                <span class="text-body-1 text-medium-emphasis">Total de Usuários</span>
                <h4 class="text-h4">{{ statistics?.total ?? 0 }}</h4>
                <span class="text-sm text-medium-emphasis">
                  {{ statistics?.thisWeek ?? 0 }} esta semana
                </span>
              </div>
              <VAvatar color="primary" variant="tonal" rounded size="42">
                <VIcon icon="tabler-users" size="26" />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex justify-space-between">
              <div class="d-flex flex-column gap-y-1">
                <span class="text-body-1 text-medium-emphasis">Administradores</span>
                <h4 class="text-h4">{{ statistics?.admins ?? 0 }}</h4>
                <span class="text-sm text-medium-emphasis">Acesso total</span>
              </div>
              <VAvatar color="error" variant="tonal" rounded size="42">
                <VIcon icon="tabler-crown" size="26" />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex justify-space-between">
              <div class="d-flex flex-column gap-y-1">
                <span class="text-body-1 text-medium-emphasis">Usuários Ativos</span>
                <h4 class="text-h4">{{ statistics?.active ?? 0 }}</h4>
                <span class="text-sm text-medium-emphasis">Com acesso ao sistema</span>
              </div>
              <VAvatar color="success" variant="tonal" rounded size="42">
                <VIcon icon="tabler-user-check" size="26" />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex justify-space-between">
              <div class="d-flex flex-column gap-y-1">
                <span class="text-body-1 text-medium-emphasis">Reset de Senha</span>
                <h4 class="text-h4">{{ statistics?.pendingPasswordReset ?? 0 }}</h4>
                <span class="text-sm text-medium-emphasis">Aguardando troca</span>
              </div>
              <VAvatar color="warning" variant="tonal" rounded size="42">
                <VIcon icon="tabler-key" size="26" />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Users Table -->
    <VCard>
      <VCardItem class="pb-4">
        <VCardTitle>Gerenciamento de Usuários</VCardTitle>
      </VCardItem>

      <!-- Filters -->
      <VCardText>
        <VRow>
          <VCol cols="12" sm="4">
            <AppSelect
              v-model="selectedRole"
              placeholder="Filtrar por perfil"
              :items="roleOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol cols="12" sm="4">
            <AppSelect
              v-model="selectedStatus"
              placeholder="Filtrar por status"
              :items="statusOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol cols="12" sm="4">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar usuário..."
              prepend-inner-icon="tabler-search"
              clearable
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- Actions Bar -->
      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
            ]"
            style="inline-size: 6.25rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10)"
          />
        </div>
        <VSpacer />
        <VBtn
          prepend-icon="tabler-plus"
          @click="isAddUserDialogVisible = true"
        >
          Novo Usuário
        </VBtn>
      </VCardText>

      <VDivider />

      <!-- Data Table -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="users"
        :items-length="totalUsers"
        :headers="headers"
        :loading="isLoading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- User Column -->
        <template #item.user="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="34"
              variant="tonal"
              :color="resolveRoleVariant(item).color"
            >
              <span>{{ item.name.charAt(0).toUpperCase() }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <div class="d-flex align-center gap-x-2">
                <h6 class="text-base font-weight-medium">
                  {{ item.name }}
                </h6>
                <VChip
                  v-if="item.password_reset_required"
                  color="warning"
                  size="x-small"
                  label
                >
                  Reset pendente
                </VChip>
              </div>
              <span class="text-sm text-medium-emphasis">{{ item.email }}</span>
            </div>
          </div>
        </template>

        <!-- Role Column -->
        <template #item.role="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :icon="resolveRoleVariant(item).icon"
              :color="resolveRoleVariant(item).color"
              size="22"
            />
            <span class="text-capitalize">
              {{ resolveRoleVariant(item).text }}
            </span>
          </div>
        </template>

        <!-- Status Column -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveStatusVariant(item.status).color"
            size="small"
            label
          >
            {{ resolveStatusVariant(item.status).text }}
          </VChip>
        </template>

        <!-- Created At Column -->
        <template #item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <IconBtn
              size="small"
              @click="openEditDialog(item)"
            >
              <VIcon icon="tabler-edit" />
              <VTooltip activator="parent" location="top">Editar</VTooltip>
            </IconBtn>
            <IconBtn
              size="small"
              color="warning"
              @click="openResetPasswordDialog(item)"
            >
              <VIcon icon="tabler-key" />
              <VTooltip activator="parent" location="top">Resetar Senha</VTooltip>
            </IconBtn>
            <IconBtn
              size="small"
              color="info"
              @click="openAuditDialog(item)"
            >
              <VIcon icon="tabler-history" />
              <VTooltip activator="parent" location="top">Histórico</VTooltip>
            </IconBtn>
            <IconBtn
              size="small"
              color="error"
              @click="openDeleteDialog(item)"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent" location="top">Excluir</VTooltip>
            </IconBtn>
          </div>
        </template>

        <!-- Pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalUsers"
          />
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Add User Dialog -->
    <VDialog
      v-model="isAddUserDialogVisible"
      max-width="500"
    >
      <VCard title="Novo Usuário">
        <VCardText>
          <VAlert
            type="info"
            variant="tonal"
            class="mb-4"
          >
            <template #prepend>
              <VIcon icon="tabler-info-circle" />
            </template>
            O usuário receberá um link para criar sua própria senha no primeiro acesso.
          </VAlert>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="newUser.name"
                label="Nome"
                placeholder="Nome completo"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="newUser.email"
                label="E-mail"
                placeholder="email@exemplo.com"
                type="email"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppSelect
                v-model="newUser.role_id"
                label="Perfil"
                :items="availableRoles"
                item-title="display_name"
                item-value="id"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppSelect
                v-model="newUser.status"
                label="Status"
                :items="statusOptions"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isAddUserDialogVisible = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            @click="createUser"
          >
            Criar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit User Dialog -->
    <VDialog
      v-model="isEditUserDialogVisible"
      max-width="500"
    >
      <VCard title="Editar Usuário">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="editUser.name"
                label="Nome"
                placeholder="Nome completo"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="editUser.email"
                label="E-mail"
                placeholder="email@exemplo.com"
                type="email"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="editUser.password"
                label="Nova Senha (deixe em branco para manter)"
                placeholder="Digite a nova senha"
                type="password"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppSelect
                v-model="editUser.role_id"
                label="Perfil"
                :items="availableRoles"
                item-title="display_name"
                item-value="id"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppSelect
                v-model="editUser.status"
                label="Status"
                :items="statusOptions"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="editUser.reason"
                label="Motivo da alteração (opcional)"
                placeholder="Ex: Atualização de cargo"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isEditUserDialogVisible = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            @click="updateUser"
          >
            Salvar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Reset Password Dialog -->
    <VDialog
      v-model="isResetPasswordDialogVisible"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="text-h5 pa-4">
          Resetar Senha
        </VCardTitle>
        <VCardText v-if="!temporaryPassword">
          <VAlert
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            <template #prepend>
              <VIcon icon="tabler-alert-triangle" />
            </template>
            Ao resetar a senha, o usuário <strong>{{ selectedUser?.name }}</strong> será obrigado a criar uma nova senha no próximo login.
          </VAlert>

          <AppTextField
            v-model="resetReason"
            label="Motivo do reset (opcional)"
            placeholder="Ex: Usuário esqueceu a senha"
          />
        </VCardText>

        <VCardText v-else>
          <VAlert
            type="success"
            variant="tonal"
            class="mb-4"
          >
            <template #prepend>
              <VIcon icon="tabler-check" />
            </template>
            Senha resetada com sucesso!
          </VAlert>

          <div class="text-body-1 mb-4">
            <p class="mb-2">Senha temporária gerada:</p>
            <div class="d-flex align-center gap-2">
              <VTextField
                :model-value="temporaryPassword"
                readonly
                variant="outlined"
                density="compact"
              />
              <VBtn
                icon
                size="small"
                color="primary"
                variant="tonal"
                @click="copyToClipboard(temporaryPassword)"
              >
                <VIcon icon="tabler-copy" />
                <VTooltip activator="parent" location="top">Copiar</VTooltip>
              </VBtn>
            </div>
          </div>

          <VAlert
            type="info"
            variant="tonal"
          >
            <template #prepend>
              <VIcon icon="tabler-info-circle" />
            </template>
            Envie esta senha temporária ao usuário. Ele será obrigado a criar uma nova senha ao fazer login.
          </VAlert>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            v-if="!temporaryPassword"
            variant="tonal"
            color="secondary"
            @click="isResetPasswordDialogVisible = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            v-if="!temporaryPassword"
            color="warning"
            @click="resetPassword"
          >
            Resetar Senha
          </VBtn>
          <VBtn
            v-else
            color="primary"
            @click="isResetPasswordDialogVisible = false; temporaryPassword = ''"
          >
            Fechar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Audit Log Dialog -->
    <VDialog
      v-model="isAuditDialogVisible"
      max-width="800"
    >
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <span>Histórico de Auditoria - {{ selectedUser?.name }}</span>
          <VBtn
            icon
            size="small"
            variant="text"
            @click="isAuditDialogVisible = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-0">
          <VList v-if="auditLogs.length > 0" lines="three">
            <template v-for="(log, index) in auditLogs" :key="log.id">
              <VListItem>
                <template #prepend>
                  <VAvatar
                    :color="log.action_color"
                    variant="tonal"
                    size="40"
                  >
                    <VIcon :icon="log.action_icon" size="22" />
                  </VAvatar>
                </template>

                <VListItemTitle class="font-weight-medium">
                  {{ log.action_description }}
                </VListItemTitle>

                <VListItemSubtitle>
                  <div class="d-flex flex-column gap-1 mt-1">
                    <div class="d-flex align-center gap-2 text-body-2">
                      <VIcon icon="tabler-user" size="14" />
                      <span>Por: {{ log.performer?.name || 'Sistema' }}</span>
                    </div>
                    <div class="d-flex align-center gap-2 text-body-2">
                      <VIcon icon="tabler-calendar" size="14" />
                      <span>{{ formatDateTime(log.created_at) }}</span>
                    </div>
                    <div v-if="log.field_changed" class="d-flex align-center gap-2 text-body-2">
                      <VIcon icon="tabler-edit" size="14" />
                      <span>Campo: {{ log.field_changed }}</span>
                    </div>
                    <div v-if="log.old_value || log.new_value" class="d-flex align-center gap-2 text-body-2">
                      <VIcon icon="tabler-arrows-exchange" size="14" />
                      <span>{{ log.old_value || '(vazio)' }} → {{ log.new_value || '(vazio)' }}</span>
                    </div>
                    <div v-if="log.reason" class="d-flex align-center gap-2 text-body-2">
                      <VIcon icon="tabler-message" size="14" />
                      <span>Motivo: {{ log.reason }}</span>
                    </div>
                    <div v-if="log.ip_address" class="d-flex align-center gap-2 text-body-2 text-disabled">
                      <VIcon icon="tabler-world" size="14" />
                      <span>IP: {{ log.ip_address }}</span>
                    </div>
                  </div>
                </VListItemSubtitle>
              </VListItem>

              <VDivider v-if="index < auditLogs.length - 1" />
            </template>
          </VList>

          <div v-else-if="isLoadingAudit" class="d-flex justify-center py-8">
            <VProgressCircular indeterminate />
          </div>

          <div v-else class="text-center py-8 text-medium-emphasis">
            <VIcon icon="tabler-history-off" size="48" class="mb-2" />
            <p>Nenhum registro de auditoria encontrado</p>
          </div>
        </VCardText>

        <VDivider v-if="totalAuditLogs > 10" />

        <VCardActions v-if="totalAuditLogs > 10" class="justify-center">
          <VPagination
            v-model="auditPage"
            :length="Math.ceil(totalAuditLogs / 10)"
            :total-visible="5"
          />
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirmation Dialog -->
    <VDialog
      v-model="isDeleteDialogVisible"
      max-width="400"
    >
      <VCard>
        <VCardTitle class="text-h5">
          Confirmar Exclusão
        </VCardTitle>
        <VCardText>
          Tem certeza que deseja excluir o usuário <strong>{{ selectedUser?.name }}</strong>?
          Esta ação não pode ser desfeita.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDeleteDialogVisible = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            @click="deleteUser"
          >
            Excluir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template>
