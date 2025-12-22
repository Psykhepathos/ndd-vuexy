<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { $api, showSuccess, showError, handleApiError } from '@/utils/api'

interface Permission {
  id: number
  name: string
  action: string
  display_name: string
  description: string
}

interface Module {
  module: string
  display: string
  permissions: Permission[]
}

interface PermissionGroup {
  group: string
  modules: Module[]
}

interface Role {
  id: number
  name: string
  display_name: string
  description: string | null
  color: string
  icon: string
  is_system: boolean
  is_active: boolean
  users_count: number
  permissions_count: number
  permissions?: Permission[]
}

interface Statistics {
  total: number
  active: number
  system: number
  custom: number
  topRoles: Role[]
}

// State
const roles = ref<Role[]>([])
const totalRoles = ref(0)
const loading = ref(false)
const statistics = ref<Statistics | null>(null)
const permissionGroups = ref<PermissionGroup[]>([])
const allPermissions = ref<Permission[]>([])

// Dialog state
const dialogRole = ref(false)
const dialogDelete = ref(false)
const dialogPermissions = ref(false)
const editMode = ref(false)
const selectedRole = ref<Role | null>(null)
const selectedRolePermissions = ref<number[]>([])

// Form
const formRole = ref({
  name: '',
  display_name: '',
  description: '',
  color: 'primary',
  icon: 'tabler-user',
  is_active: true,
})

// Table options
const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [{ key: 'name', order: 'asc' as const }],
})
const search = ref('')

// Colors
const colors = [
  { title: 'Primary', value: 'primary' },
  { title: 'Success', value: 'success' },
  { title: 'Info', value: 'info' },
  { title: 'Warning', value: 'warning' },
  { title: 'Error', value: 'error' },
  { title: 'Secondary', value: 'secondary' },
]

// Icons
const icons = [
  { title: 'Usuário', value: 'tabler-user' },
  { title: 'Coroa', value: 'tabler-crown' },
  { title: 'Escudo', value: 'tabler-shield' },
  { title: 'Check', value: 'tabler-user-check' },
  { title: 'Olho', value: 'tabler-eye' },
  { title: 'Configuração', value: 'tabler-settings' },
  { title: 'Editar', value: 'tabler-edit' },
  { title: 'Estrela', value: 'tabler-star' },
]

// Table headers
const headers = [
  { title: 'Perfil', key: 'display_name', sortable: true },
  { title: 'Identificador', key: 'name', sortable: true },
  { title: 'Usuários', key: 'users_count', sortable: true, align: 'center' as const },
  { title: 'Permissões', key: 'permissions_count', sortable: true, align: 'center' as const },
  { title: 'Status', key: 'is_active', sortable: true, align: 'center' as const },
  { title: 'Ações', key: 'actions', sortable: false, align: 'center' as const },
]

// Fetch functions
async function fetchRoles() {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: options.value.page.toString(),
      itemsPerPage: options.value.itemsPerPage.toString(),
      sortBy: options.value.sortBy[0]?.key || 'name',
      orderBy: options.value.sortBy[0]?.order || 'asc',
    })

    if (search.value) {
      params.append('q', search.value)
    }

    const data = await $api(`/roles?${params}`)
    roles.value = data.roles
    totalRoles.value = data.totalRoles
  } catch (error) {
    console.error('Erro ao carregar perfis:', error)
    handleApiError(error, 'Erro ao carregar perfis')
  } finally {
    loading.value = false
  }
}

async function fetchStatistics() {
  try {
    statistics.value = await $api('/roles/statistics')
  } catch (error) {
    console.error('Erro ao carregar estatísticas:', error)
    handleApiError(error, 'Erro ao carregar estatísticas')
  }
}

async function fetchPermissions() {
  try {
    const data = await $api('/roles/permissions')
    permissionGroups.value = data.groups
    allPermissions.value = data.allPermissions
  } catch (error) {
    console.error('Erro ao carregar permissões:', error)
    handleApiError(error, 'Erro ao carregar permissões')
  }
}

// Dialog handlers
function openCreateDialog() {
  editMode.value = false
  formRole.value = {
    name: '',
    display_name: '',
    description: '',
    color: 'primary',
    icon: 'tabler-user',
    is_active: true,
  }
  dialogRole.value = true
}

function openEditDialog(role: Role) {
  editMode.value = true
  selectedRole.value = role
  formRole.value = {
    name: role.name,
    display_name: role.display_name,
    description: role.description || '',
    color: role.color,
    icon: role.icon,
    is_active: role.is_active,
  }
  dialogRole.value = true
}

function openDeleteDialog(role: Role) {
  selectedRole.value = role
  dialogDelete.value = true
}

async function openPermissionsDialog(role: Role) {
  selectedRole.value = role

  // Buscar permissões do role
  try {
    const data = await $api(`/roles/${role.id}`)

    // Mapear nomes de permissões para IDs
    const permNames = data.permissions || []
    selectedRolePermissions.value = allPermissions.value
      .filter(p => permNames.includes(p.name))
      .map(p => p.id)
  } catch (error) {
    console.error('Erro ao carregar permissões do perfil:', error)
    handleApiError(error, 'Erro ao carregar permissões do perfil')
    selectedRolePermissions.value = []
  }

  dialogPermissions.value = true
}

// CRUD operations
async function saveRole() {
  try {
    const url = editMode.value
      ? `/roles/${selectedRole.value?.id}`
      : '/roles'

    await $api(url, {
      method: editMode.value ? 'PUT' : 'POST',
      body: formRole.value,
    })

    dialogRole.value = false
    showSuccess(editMode.value ? 'Perfil atualizado com sucesso!' : 'Perfil criado com sucesso!')
    fetchRoles()
    fetchStatistics()
  } catch (error: any) {
    console.error('Erro ao salvar perfil:', error)
    handleApiError(error, 'Erro ao salvar perfil')
  }
}

async function deleteRole() {
  if (!selectedRole.value) return

  try {
    await $api(`/roles/${selectedRole.value.id}`, {
      method: 'DELETE',
    })

    dialogDelete.value = false
    showSuccess('Perfil excluído com sucesso!')
    fetchRoles()
    fetchStatistics()
  } catch (error: any) {
    console.error('Erro ao excluir perfil:', error)
    handleApiError(error, 'Erro ao excluir perfil')
  }
}

async function savePermissions() {
  if (!selectedRole.value) return

  try {
    await $api(`/roles/${selectedRole.value.id}/sync-permissions`, {
      method: 'POST',
      body: { permissions: selectedRolePermissions.value },
    })

    dialogPermissions.value = false
    showSuccess('Permissões salvas com sucesso!')
    fetchRoles()
  } catch (error: any) {
    console.error('Erro ao salvar permissões:', error)
    handleApiError(error, 'Erro ao salvar permissões')
  }
}

// Helper functions
function toggleAllModulePermissions(module: Module, checked: boolean) {
  if (checked) {
    // Adicionar todas as permissões do módulo
    module.permissions.forEach(p => {
      if (!selectedRolePermissions.value.includes(p.id)) {
        selectedRolePermissions.value.push(p.id)
      }
    })
  } else {
    // Remover todas as permissões do módulo
    module.permissions.forEach(p => {
      const index = selectedRolePermissions.value.indexOf(p.id)
      if (index > -1) {
        selectedRolePermissions.value.splice(index, 1)
      }
    })
  }
}

function isModuleFullySelected(module: Module): boolean {
  return module.permissions.every(p => selectedRolePermissions.value.includes(p.id))
}

function isModulePartiallySelected(module: Module): boolean {
  const selected = module.permissions.filter(p => selectedRolePermissions.value.includes(p.id))
  return selected.length > 0 && selected.length < module.permissions.length
}

// Lifecycle
onMounted(() => {
  fetchRoles()
  fetchStatistics()
  fetchPermissions()
})

// Watch for table changes
function onTableUpdate(newOptions: any) {
  options.value = newOptions
  fetchRoles()
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">Perfis e Permissões</h4>
        <p class="text-body-1 mb-0">Gerencie os perfis de acesso e suas permissões</p>
      </div>
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="openCreateDialog"
      >
        Novo Perfil
      </VBtn>
    </div>

    <!-- Statistics Cards -->
    <VRow v-if="statistics" class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="primary"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-users-group" size="28" />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-0">{{ statistics.total }}</h5>
              <span class="text-body-2">Total de Perfis</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="success"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-check" size="28" />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-0">{{ statistics.active }}</h5>
              <span class="text-body-2">Ativos</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="warning"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-shield" size="28" />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-0">{{ statistics.system }}</h5>
              <span class="text-body-2">Sistema</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              color="info"
              variant="tonal"
              size="44"
              rounded
            >
              <VIcon icon="tabler-adjustments" size="28" />
            </VAvatar>
            <div>
              <h5 class="text-h5 mb-0">{{ statistics.custom }}</h5>
              <span class="text-body-2">Personalizados</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Table -->
    <VCard>
      <VCardText>
        <div class="d-flex flex-wrap gap-4 mb-4">
          <VTextField
            v-model="search"
            placeholder="Buscar perfis..."
            prepend-inner-icon="tabler-search"
            density="compact"
            style="max-width: 300px"
            clearable
            @update:model-value="fetchRoles"
          />
        </div>

        <VDataTableServer
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          v-model:sort-by="options.sortBy"
          :headers="headers"
          :items="roles"
          :items-length="totalRoles"
          :loading="loading"
          @update:options="onTableUpdate"
        >
          <!-- Display Name -->
          <template #item.display_name="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar
                :color="item.color"
                variant="tonal"
                size="38"
              >
                <VIcon :icon="item.icon" size="22" />
              </VAvatar>
              <div>
                <p class="font-weight-medium mb-0">{{ item.display_name }}</p>
                <span class="text-caption text-disabled">{{ item.description || 'Sem descrição' }}</span>
              </div>
            </div>
          </template>

          <!-- Name -->
          <template #item.name="{ item }">
            <VChip
              :color="item.is_system ? 'warning' : 'default'"
              size="small"
              variant="tonal"
            >
              {{ item.name }}
              <VTooltip v-if="item.is_system" activator="parent" location="top">
                Perfil do sistema (não pode ser excluído)
              </VTooltip>
            </VChip>
          </template>

          <!-- Users Count -->
          <template #item.users_count="{ item }">
            <VChip color="primary" size="small" variant="tonal">
              {{ item.users_count }}
            </VChip>
          </template>

          <!-- Permissions Count -->
          <template #item.permissions_count="{ item }">
            <VChip color="info" size="small" variant="tonal">
              {{ item.permissions_count }}
            </VChip>
          </template>

          <!-- Status -->
          <template #item.is_active="{ item }">
            <VChip
              :color="item.is_active ? 'success' : 'error'"
              size="small"
              variant="tonal"
            >
              {{ item.is_active ? 'Ativo' : 'Inativo' }}
            </VChip>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <div class="d-flex gap-1 justify-center">
              <VBtn
                icon
                size="small"
                variant="text"
                color="primary"
                @click="openPermissionsDialog(item)"
              >
                <VIcon icon="tabler-lock" size="20" />
                <VTooltip activator="parent" location="top">Permissões</VTooltip>
              </VBtn>
              <VBtn
                icon
                size="small"
                variant="text"
                color="default"
                @click="openEditDialog(item)"
              >
                <VIcon icon="tabler-edit" size="20" />
                <VTooltip activator="parent" location="top">Editar</VTooltip>
              </VBtn>
              <VBtn
                v-if="!item.is_system"
                icon
                size="small"
                variant="text"
                color="error"
                :disabled="item.users_count > 0"
                @click="openDeleteDialog(item)"
              >
                <VIcon icon="tabler-trash" size="20" />
                <VTooltip activator="parent" location="top">
                  {{ item.users_count > 0 ? 'Há usuários usando este perfil' : 'Excluir' }}
                </VTooltip>
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- Create/Edit Role Dialog -->
    <VDialog v-model="dialogRole" max-width="600">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 pa-5">
          <VIcon :icon="editMode ? 'tabler-edit' : 'tabler-plus'" />
          {{ editMode ? 'Editar Perfil' : 'Novo Perfil' }}
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VForm @submit.prevent="saveRole">
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="formRole.display_name"
                  label="Nome de Exibição"
                  placeholder="Ex: Administrador"
                  :rules="[v => !!v || 'Campo obrigatório']"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="formRole.name"
                  label="Identificador"
                  placeholder="Ex: admin"
                  :disabled="editMode && selectedRole?.is_system"
                  :rules="[
                    v => !!v || 'Campo obrigatório',
                    v => /^[a-z_]+$/.test(v) || 'Apenas letras minúsculas e underline'
                  ]"
                  hint="Apenas letras minúsculas e underline"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="formRole.description"
                  label="Descrição"
                  placeholder="Descreva o propósito deste perfil..."
                  rows="2"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="formRole.color"
                  :items="colors"
                  item-title="title"
                  item-value="value"
                  label="Cor"
                >
                  <template #selection="{ item }">
                    <VChip :color="item.value" size="small">
                      {{ item.title }}
                    </VChip>
                  </template>
                  <template #item="{ item, props }">
                    <VListItem v-bind="props">
                      <template #prepend>
                        <VAvatar :color="item.value" size="24" />
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="formRole.icon"
                  :items="icons"
                  item-title="title"
                  item-value="value"
                  label="Ícone"
                >
                  <template #selection="{ item }">
                    <div class="d-flex align-center gap-2">
                      <VIcon :icon="item.value" size="20" />
                      {{ item.title }}
                    </div>
                  </template>
                  <template #item="{ item, props }">
                    <VListItem v-bind="props">
                      <template #prepend>
                        <VIcon :icon="item.value" size="20" />
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>
              <VCol v-if="!selectedRole?.is_system" cols="12">
                <VSwitch
                  v-model="formRole.is_active"
                  label="Perfil ativo"
                  color="success"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn variant="outlined" @click="dialogRole = false">
            Cancelar
          </VBtn>
          <VBtn color="primary" @click="saveRole">
            {{ editMode ? 'Salvar' : 'Criar' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Dialog -->
    <VDialog v-model="dialogDelete" max-width="400">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 pa-5 text-error">
          <VIcon icon="tabler-alert-triangle" />
          Confirmar Exclusão
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <p class="mb-0">
            Tem certeza que deseja excluir o perfil <strong>{{ selectedRole?.display_name }}</strong>?
          </p>
          <p class="text-caption text-disabled mt-2 mb-0">
            Esta ação não pode ser desfeita.
          </p>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn variant="outlined" @click="dialogDelete = false">
            Cancelar
          </VBtn>
          <VBtn color="error" @click="deleteRole">
            Excluir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Permissions Dialog -->
    <VDialog v-model="dialogPermissions" max-width="900" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 pa-5">
          <VIcon icon="tabler-lock" />
          Permissões: {{ selectedRole?.display_name }}
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5" style="max-height: 60vh; overflow-y: auto;">
          <VAlert
            v-if="selectedRole?.is_system && selectedRole.name === 'admin'"
            type="info"
            variant="tonal"
            class="mb-4"
          >
            O perfil Administrador possui acesso total ao sistema.
          </VAlert>

          <div v-for="group in permissionGroups" :key="group.group" class="mb-6">
            <h6 class="text-h6 mb-3">{{ group.group }}</h6>

            <VCard
              v-for="module in group.modules"
              :key="module.module"
              variant="outlined"
              class="mb-3"
            >
              <VCardText class="pa-3">
                <div class="d-flex align-center justify-space-between mb-2">
                  <div class="d-flex align-center gap-2">
                    <VCheckbox
                      :model-value="isModuleFullySelected(module)"
                      :indeterminate="isModulePartiallySelected(module)"
                      hide-details
                      density="compact"
                      @update:model-value="(v: boolean) => toggleAllModulePermissions(module, v)"
                    />
                    <span class="font-weight-medium">{{ module.display }}</span>
                  </div>
                  <VChip size="x-small" variant="tonal">
                    {{ module.permissions.filter(p => selectedRolePermissions.includes(p.id)).length }} / {{ module.permissions.length }}
                  </VChip>
                </div>

                <div class="d-flex flex-wrap gap-2 ml-8">
                  <VCheckbox
                    v-for="perm in module.permissions"
                    :key="perm.id"
                    v-model="selectedRolePermissions"
                    :value="perm.id"
                    :label="perm.display_name"
                    hide-details
                    density="compact"
                    class="mr-4"
                  />
                </div>
              </VCardText>
            </VCard>
          </div>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-5">
          <div class="text-caption text-disabled">
            {{ selectedRolePermissions.length }} permissões selecionadas
          </div>
          <VSpacer />
          <VBtn variant="outlined" @click="dialogPermissions = false">
            Cancelar
          </VBtn>
          <VBtn color="primary" @click="savePermissions">
            Salvar Permissões
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
