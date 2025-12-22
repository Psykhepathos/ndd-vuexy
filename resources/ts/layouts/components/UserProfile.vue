<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { $api } from '@/utils/api'
import { API_ENDPOINTS } from '@/config/api'

const router = useRouter()
const ability = useAbility()

// TODO: Get type from backend
const userData = useCookie<any>('userData')

/**
 * Função de logout robusta
 * ORDEM CORRETA:
 * 1. Chamar backend para invalidar o token (COM o token ainda presente)
 * 2. Limpar todos os cookies de autenticação
 * 3. Resetar as abilities CASL
 * 4. Redirecionar para login
 */
const logout = async () => {
  // 1. PRIMEIRO chamar o backend para invalidar o token (ANTES de limpar cookies!)
  // Isso garante que o token é deletado do banco de dados
  try {
    await $api(API_ENDPOINTS.authLogout, { method: 'POST' })
  }
  catch (error) {
    // Ignorar erro - pode ser token já expirado
    console.warn('Erro ao invalidar token no servidor:', error)
  }

  // 2. DEPOIS limpar cookies de autenticação
  const accessTokenCookie = useCookie('accessToken')
  const userDataCookie = useCookie('userData')
  const userAbilityCookie = useCookie('userAbilityRules')

  accessTokenCookie.value = null
  userDataCookie.value = null
  userAbilityCookie.value = null

  // 3. Resetar abilities CASL
  ability.update([])

  // 4. Redirecionar para login usando hard redirect para garantir limpeza completa
  const baseUrl = window.location.origin + (import.meta.env.BASE_URL || '/')
  const loginUrl = baseUrl.replace(/\/+$/, '') + '/login'
  window.location.href = loginUrl
}

const userProfileList = [
  { type: 'divider' },
  { type: 'navItem', icon: 'tabler-user', title: 'Perfil', to: { name: 'apps-user-view-id', params: { id: 21 } } },
  { type: 'navItem', icon: 'tabler-settings', title: 'Configurações', to: { name: 'pages-account-settings-tab', params: { tab: 'account' } } },
  { type: 'navItem', icon: 'tabler-file-dollar', title: 'Plano de Cobrança', to: { name: 'pages-account-settings-tab', params: { tab: 'billing-plans' } }, badgeProps: { color: 'error', content: '4' } },
  { type: 'divider' },
  { type: 'navItem', icon: 'tabler-currency-dollar', title: 'Preços', to: { name: 'pages-pricing' } },
  { type: 'navItem', icon: 'tabler-question-mark', title: 'Perguntas Frequentes', to: { name: 'pages-faq' } },
]
</script>

<template>
  <VBadge
    v-if="userData"
    dot
    bordered
    location="bottom right"
    offset-x="1"
    offset-y="2"
    color="success"
  >
    <VAvatar
      size="38"
      class="cursor-pointer"
      :color="!(userData && userData.avatar) ? 'primary' : undefined"
      :variant="!(userData && userData.avatar) ? 'tonal' : undefined"
    >
      <VImg
        v-if="userData && userData.avatar"
        :src="userData.avatar"
      />
      <VIcon
        v-else
        icon="tabler-user"
      />

      <!-- SECTION Menu -->
      <VMenu
        activator="parent"
        width="240"
        location="bottom end"
        offset="12px"
      >
        <VList>
          <VListItem>
            <div class="d-flex gap-2 align-center">
              <VListItemAction>
                <VBadge
                  dot
                  location="bottom right"
                  offset-x="3"
                  offset-y="3"
                  color="success"
                  bordered
                >
                  <VAvatar
                    :color="!(userData && userData.avatar) ? 'primary' : undefined"
                    :variant="!(userData && userData.avatar) ? 'tonal' : undefined"
                  >
                    <VImg
                      v-if="userData && userData.avatar"
                      :src="userData.avatar"
                    />
                    <VIcon
                      v-else
                      icon="tabler-user"
                    />
                  </VAvatar>
                </VBadge>
              </VListItemAction>

              <div>
                <h6 class="text-h6 font-weight-medium">
                  {{ userData.fullName || userData.username }}
                </h6>
                <VListItemSubtitle class="text-capitalize text-disabled">
                  {{ userData.role }}
                </VListItemSubtitle>
              </div>
            </div>
          </VListItem>

          <PerfectScrollbar :options="{ wheelPropagation: false }">
            <template
              v-for="item in userProfileList"
              :key="item.title"
            >
              <VListItem
                v-if="item.type === 'navItem'"
                :to="item.to"
              >
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="22"
                  />
                </template>

                <VListItemTitle>{{ item.title }}</VListItemTitle>

                <template
                  v-if="item.badgeProps"
                  #append
                >
                  <VBadge
                    rounded="sm"
                    class="me-3"
                    v-bind="item.badgeProps"
                  />
                </template>
              </VListItem>

              <VDivider
                v-else
                class="my-2"
              />
            </template>

            <div class="px-4 py-2">
              <VBtn
                block
                size="small"
                color="error"
                append-icon="tabler-logout"
                @click="logout"
              >
                Sair
              </VBtn>
            </div>
          </PerfectScrollbar>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>
