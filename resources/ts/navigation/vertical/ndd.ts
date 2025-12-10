export default [
  {
    title: 'Sistema NDD',
    icon: { icon: 'tabler-truck' },
    open: true,
    children: [
      {
        title: 'Dashboard NDD',
        to: 'ndd-dashboard',
        icon: { icon: 'tabler-dashboard' },
      },
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
      {
        title: 'Vale Pedágio',
        to: 'vale-pedagio',
        icon: { icon: 'tabler-route-2' },
      },
      {
        title: 'Praças de Pedágio',
        to: 'pracas-pedagio',
        icon: { icon: 'tabler-coin' },
      },
      {
        title: 'Rotas Padrão',
        to: 'rotas-padrao',
        icon: { icon: 'tabler-map-route' },
      },
      {
        title: 'Compra Viagem',
        to: 'compra-viagem',
        icon: { icon: 'tabler-shopping-cart' },
      },
      {
        title: 'Emissão VPO',
        to: 'vpo-emissao',
        icon: { icon: 'tabler-file-certificate' },
      },
    ],
  },
]