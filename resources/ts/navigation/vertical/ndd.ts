export default [
  // Dashboard
  {
    title: 'Dashboard',
    to: 'ndd-dashboard',
    icon: { icon: 'tabler-dashboard' },
  },

  // Operações - Seção header
  {
    heading: 'Operações',
  },

  // Vale Pedágio (submenu)
  {
    title: 'Vale Pedágio',
    icon: { icon: 'tabler-receipt' },
    children: [
      {
        title: 'Compra SemParar',
        to: 'compra-viagem',
        icon: { icon: 'tabler-credit-card' },
      },
      {
        title: 'Emissão VPO NDD',
        to: 'vpo-emissao',
        icon: { icon: 'tabler-file-invoice' },
      },
    ],
  },

  // Cadastros - Seção header
  {
    heading: 'Cadastros',
  },

  // Transportes (submenu)
  {
    title: 'Transportes',
    icon: { icon: 'tabler-truck' },
    children: [
      {
        title: 'Transportadores',
        to: 'transportes',
        icon: { icon: 'tabler-truck-delivery' },
      },
      {
        title: 'Pacotes',
        to: 'pacotes',
        icon: { icon: 'tabler-package' },
      },
    ],
  },

  // Rotas (submenu)
  {
    title: 'Rotas',
    icon: { icon: 'tabler-map-2' },
    children: [
      {
        title: 'Rotas Padrão',
        to: 'rotas-padrao',
        icon: { icon: 'tabler-route' },
      },
      {
        title: 'Praças de Pedágio',
        to: 'pracas-pedagio',
        icon: { icon: 'tabler-coin' },
      },
    ],
  },

  // Administração - Seção header
  {
    heading: 'Administração',
  },

  // Usuários e Acessos (submenu)
  {
    title: 'Usuários e Acessos',
    icon: { icon: 'tabler-users-group' },
    children: [
      {
        title: 'Usuários',
        to: 'usuarios',
        icon: { icon: 'tabler-users' },
        action: 'read',
        subject: 'users',
      },
      {
        title: 'Perfis e Permissões',
        to: 'perfis',
        icon: { icon: 'tabler-shield-lock' },
        action: 'manage',
        subject: 'roles',
      },
    ],
  },

  // Comunicação (submenu)
  {
    title: 'Comunicação',
    icon: { icon: 'tabler-message-circle' },
    children: [
      {
        title: 'Minhas Notificações',
        to: 'notificacoes',
        icon: { icon: 'tabler-bell' },
      },
      {
        title: 'Gerenciar Notificações',
        to: 'notificacoes-admin',
        icon: { icon: 'tabler-bell-ringing' },
        action: 'manage',
        subject: 'notifications',
      },
    ],
  },
]
