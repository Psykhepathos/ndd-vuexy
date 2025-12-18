<script setup lang="ts">
const shortcuts = [
  {
    icon: 'tabler-calendar',
    title: 'Calendário',
    subtitle: 'Compromissos',
    to: { name: 'apps-calendar' },
  },
  {
    icon: 'tabler-file-dollar',
    title: 'Faturas',
    subtitle: 'Gerenciar Contas',
    to: { name: 'apps-invoice-list' },
  },
  {
    icon: 'tabler-user',
    title: 'Usuários',
    subtitle: 'Gerenciar Usuários',
    to: { name: 'apps-user-list' },
  },
  {
    icon: 'tabler-users',
    title: 'Gerenciar Funções',
    subtitle: 'Permissões',
    to: { name: 'apps-roles' },
  },
  {
    icon: 'tabler-device-desktop-analytics',
    title: 'Painel',
    subtitle: 'Análises do Painel',
    to: { name: 'dashboards-analytics' },
  },
  {
    icon: 'tabler-settings',
    title: 'Configurações',
    subtitle: 'Configurações da Conta',
    to: { name: 'pages-account-settings-tab', params: { tab: 'account' } },
  },

]
</script>

<template>
  <Shortcuts :shortcuts="shortcuts" />
</template>
