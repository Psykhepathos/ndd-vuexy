export default [
  { heading: 'Apps e Páginas' },
  {
    title: 'E-commerce',
    icon: { icon: 'tabler-shopping-cart' },
    children: [
      {
        title: 'Painel',
        to: 'apps-ecommerce-dashboard',
      },
      {
        title: 'Produto',
        children: [
          { title: 'Lista', to: 'apps-ecommerce-product-list' },
          { title: 'Adicionar', to: 'apps-ecommerce-product-add' },
          { title: 'Categoria', to: 'apps-ecommerce-product-category-list' },
        ],
      },
      {
        title: 'Pedido',
        children: [
          { title: 'Lista', to: 'apps-ecommerce-order-list' },
          { title: 'Detalhes', to: { name: 'apps-ecommerce-order-details-id', params: { id: '9042' } } },
        ],
      },
      {
        title: 'Cliente',
        children: [
          { title: 'Lista', to: 'apps-ecommerce-customer-list' },
          { title: 'Detalhes', to: { name: 'apps-ecommerce-customer-details-id', params: { id: 478426 } } },
        ],
      },
      {
        title: 'Gerenciar Avaliações',
        to: 'apps-ecommerce-manage-review',
      },
      {
        title: 'Indicações',
        to: 'apps-ecommerce-referrals',
      },
      {
        title: 'Configurações',
        to: 'apps-ecommerce-settings',
      },
    ],
  },
  {
    title: 'Academia',
    icon: { icon: 'tabler-school' },
    children: [
      { title: 'Painel', to: 'apps-academy-dashboard' },
      { title: 'Meus Cursos', to: 'apps-academy-my-course' },
      { title: 'Detalhes do Curso', to: 'apps-academy-course-details' },
    ],
  },
  {
    title: 'Logística',
    icon: { icon: 'tabler-truck' },
    children: [
      { title: 'Painel', to: 'apps-logistics-dashboard' },
      { title: 'Frota', to: 'apps-logistics-fleet' },
    ],
  },
  {
    title: 'E-mail',
    icon: { icon: 'tabler-mail' },
    to: 'apps-email',
  },
  {
    title: 'Chat',
    icon: { icon: 'tabler-message-circle-2' },
    to: 'apps-chat',
  },
  {
    title: 'Calendário',
    icon: { icon: 'tabler-calendar' },
    to: 'apps-calendar',
  },
  {
    title: 'Kanban',
    icon: { icon: 'tabler-layout-kanban' },
    to: 'apps-kanban',
  },
  {
    title: 'Fatura',
    icon: { icon: 'tabler-file-invoice' },

    children: [
      { title: 'Lista', to: 'apps-invoice-list' },
      { title: 'Visualizar', to: { name: 'apps-invoice-preview-id', params: { id: '5036' } } },
      { title: 'Editar', to: { name: 'apps-invoice-edit-id', params: { id: '5036' } } },
      { title: 'Adicionar', to: 'apps-invoice-add' },
    ],
  },
  {
    title: 'Usuário',
    icon: { icon: 'tabler-user' },
    children: [
      { title: 'Lista', to: 'apps-user-list' },
      { title: 'Visualizar', to: { name: 'apps-user-view-id', params: { id: 21 } } },
    ],
  },
  {
    title: 'Funções e Permissões',
    icon: { icon: 'tabler-lock' },
    children: [
      { title: 'Funções', to: 'apps-roles' },
      { title: 'Permissões', to: 'apps-permissions' },
    ],
  },

  {
    title: 'Páginas',
    icon: { icon: 'tabler-file' },
    children: [
      { title: 'Perfil do Usuário', to: { name: 'pages-user-profile-tab', params: { tab: 'profile' } } },
      { title: 'Configurações da Conta', to: { name: 'pages-account-settings-tab', params: { tab: 'account' } } },
      { title: 'Preços', to: 'pages-pricing' },
      { title: 'Perguntas Frequentes', to: 'pages-faq' },
      {
        title: 'Diversos',
        children: [
          { title: 'Em Breve', to: 'pages-misc-coming-soon', target: '_blank' },
          { title: 'Em Manutenção', to: 'pages-misc-under-maintenance', target: '_blank' },
          { title: 'Página Não Encontrada - 404', to: { path: '/pages/misc/not-found' }, target: '_blank' },
          { title: 'Não Autorizado - 401', to: { path: '/pages/misc/not-authorized' }, target: '_blank' },
        ],
      },
    ],
  },
  {
    title: 'Autenticação',
    icon: { icon: 'tabler-shield-lock' },
    children: [
      {
        title: 'Login',
        children: [
          { title: 'Login v1', to: 'pages-authentication-login-v1', target: '_blank' },
          { title: 'Login v2', to: 'pages-authentication-login-v2', target: '_blank' },
        ],
      },
      {
        title: 'Cadastro',
        children: [
          { title: 'Cadastro v1', to: 'pages-authentication-register-v1', target: '_blank' },
          { title: 'Cadastro v2', to: 'pages-authentication-register-v2', target: '_blank' },
          { title: 'Cadastro Multi-Etapas', to: 'pages-authentication-register-multi-steps', target: '_blank' },
        ],
      },
      {
        title: 'Verificar E-mail',
        children: [
          { title: 'Verificar E-mail v1', to: 'pages-authentication-verify-email-v1', target: '_blank' },
          { title: 'Verificar E-mail v2', to: 'pages-authentication-verify-email-v2', target: '_blank' },
        ],
      },
      {
        title: 'Esqueci a Senha',
        children: [
          { title: 'Esqueci a Senha v1', to: 'pages-authentication-forgot-password-v1', target: '_blank' },
          { title: 'Esqueci a Senha v2', to: 'pages-authentication-forgot-password-v2', target: '_blank' },
        ],
      },
      {
        title: 'Redefinir Senha',
        children: [
          { title: 'Redefinir Senha v1', to: 'pages-authentication-reset-password-v1', target: '_blank' },
          { title: 'Redefinir Senha v2', to: 'pages-authentication-reset-password-v2', target: '_blank' },
        ],
      },
      {
        title: 'Duas Etapas',
        children: [
          { title: 'Duas Etapas v1', to: 'pages-authentication-two-steps-v1', target: '_blank' },
          { title: 'Duas Etapas v2', to: 'pages-authentication-two-steps-v2', target: '_blank' },
        ],
      },
    ],
  },
  {
    title: 'Exemplos de Assistente',
    icon: { icon: 'tabler-dots' },
    children: [
      { title: 'Checkout', to: { name: 'wizard-examples-checkout' } },
      { title: 'Listagem de Imóveis', to: { name: 'wizard-examples-property-listing' } },
      { title: 'Criar Negócio', to: { name: 'wizard-examples-create-deal' } },
    ],
  },
  {
    title: 'Exemplos de Diálogo',
    icon: { icon: 'tabler-square' },
    to: 'pages-dialog-examples',
  },
]
