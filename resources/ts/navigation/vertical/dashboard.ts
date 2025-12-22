export default [
  {
    title: 'Painéis',
    icon: { icon: 'tabler-smart-home' },
    children: [
      {
        title: 'Análises',
        to: 'dashboards-analytics',
      },
      {
        title: 'NDD Dashboard',
        to: 'ndd-dashboard',
      },
      {
        title: 'E-commerce',
        to: 'dashboards-ecommerce',
      },
      {
        title: 'Academia',
        to: 'dashboards-academy',
      },
      {
        title: 'Logística',
        to: 'dashboards-logistics',
      },
    ],
    badgeContent: '5',
    badgeClass: 'bg-error',
  },
  {
    title: 'Páginas',
    icon: { icon: 'tabler-files' },
    children: [
      {
        title: 'Página Inicial',
        to: 'front-pages-landing-page',
        target: '_blank',
      },
      {
        title: 'Preços',
        to: 'front-pages-pricing',
        target: '_blank',
      },
      {
        title: 'Pagamento',
        to: 'front-pages-payment',
        target: '_blank',
      },
      {
        title: 'Checkout',
        to: 'front-pages-checkout',
        target: '_blank',
      },
      {
        title: 'Central de Ajuda',
        to: 'front-pages-help-center',
        target: '_blank',
      },
    ],
  },
]
