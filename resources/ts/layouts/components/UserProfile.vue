<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { $api } from '@/utils/api'
import { API_ENDPOINTS } from '@/config/api'

const router = useRouter()
const ability = useAbility()

// Manter uma cópia local dos dados do usuário para o template
// Isso evita que o componente seja destruído quando os cookies são limpos
const userDataCookie = useCookie<any>('userData')
const userData = ref(userDataCookie.value)

// Sincronizar quando o cookie mudar (ex: login em outra aba)
watch(userDataCookie, (newValue) => {
  if (newValue) {
    userData.value = newValue
  }
})

// Flag para controlar se o logout está em andamento
const isLoggingOut = ref(false)

/**
 * Função de logout
 *
 * IMPORTANTE: A ordem das operações é crítica!
 *
 * Problema original:
 * - O template tem v-if="userData" que destrói o componente quando userData é null
 * - O guard do router redireciona usuários logados para dashboard quando tentam acessar /login
 *
 * Solução:
 * 1. Chamar backend (com token ainda válido)
 * 2. Resetar CASL
 * 3. Limpar cookies PRIMEIRO (para que o guard permita ir para /login)
 * 4. Redirecionar para login
 */
const logout = async () => {
  // Prevenir múltiplos cliques
  if (isLoggingOut.value) return
  isLoggingOut.value = true

  // 1. Chamar backend para invalidar o token (com token ainda presente)
  try {
    await $api(API_ENDPOINTS.authLogout, { method: 'POST' })
  }
  catch (error) {
    console.warn('Erro ao invalidar token no servidor:', error)
  }

  // 2. Resetar abilities CASL
  ability.update([])

  // 3. Limpar cookies ANTES do redirect
  // Isso é necessário porque o guard não permite ir para /login se estiver logado
  // Usamos useCookie diretamente - o componente não será destruído porque
  // userData agora é um ref independente, não o cookie direto
  useCookie('accessToken').value = null
  useCookie('userData').value = null
  useCookie('userAbilityRules').value = null

  // 4. Redirecionar para login
  // Agora o guard vai permitir porque os cookies foram limpos
  await router.push({ name: 'login' })
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
