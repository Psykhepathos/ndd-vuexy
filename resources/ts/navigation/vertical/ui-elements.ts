export default [
  { heading: 'Elementos de UI' },
  {
    title: 'Tipografia',
    icon: { icon: 'tabler-typography' },
    to: 'pages-typography',
  },
  {
    title: 'Ícones',
    icon: { icon: 'tabler-brand-tabler' },
    to: 'pages-icons',
  },
  {
    title: 'Cards',
    icon: { icon: 'tabler-id' },
    children: [
      { title: 'Básico', to: 'pages-cards-card-basic' },
      { title: 'Avançado', to: 'pages-cards-card-advance' },
      { title: 'Estatísticas', to: 'pages-cards-card-statistics' },
      { title: 'Widgets', to: 'pages-cards-card-widgets' },
      { title: 'Ações', to: 'pages-cards-card-actions' },
    ],
  },
  {
    title: 'Componentes',
    icon: { icon: 'tabler-atom' },
    children: [
      { title: 'Alerta', to: 'components-alert' },
      { title: 'Avatar', to: 'components-avatar' },
      { title: 'Badge', to: 'components-badge' },
      { title: 'Botão', to: 'components-button' },
      { title: 'Chip', to: 'components-chip' },
      { title: 'Diálogo', to: 'components-dialog' },
      { title: 'Painel Expansível', to: 'components-expansion-panel' },
      { title: 'Lista', to: 'components-list' },
      { title: 'Menu', to: 'components-menu' },
      { title: 'Paginação', to: 'components-pagination' },
      { title: 'Progresso Circular', to: 'components-progress-circular' },
      { title: 'Progresso Linear', to: 'components-progress-linear' },
      { title: 'Snackbar', to: 'components-snackbar' },
      { title: 'Abas', to: 'components-tabs' },
      { title: 'Linha do Tempo', to: 'components-timeline' },
      { title: 'Tooltip', to: 'components-tooltip' },
    ],
  },
  {
    title: 'Extensões',
    icon: { icon: 'tabler-box' },
    children: [
      { title: 'Tour', to: 'extensions-tour' },
      { title: 'Swiper', to: 'extensions-swiper' },
    ],
  },
]
