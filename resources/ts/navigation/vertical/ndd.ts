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
        title: 'Emissão VPO NDD',
        to: 'vpo-emissao',
        icon: { icon: 'tabler-shopping-cart' },
      },
            {
        title: 'Emissão VPO SemParar',
        to: 'compra-viagem',
        icon: { icon: 'tabler-shopping-cart' },
      },
            {
        title: 'Rotas Padrão',
        to: 'rotas-padrao',
        icon: { icon: 'tabler-map-route' },
      },
            {
        title: 'Praças de Pedágio',
        to: 'pracas-pedagio',
        icon: { icon: 'tabler-coin' },
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
    //   {
    //     title: 'Vale Pedágio',
    //     to: 'vale-pedagio',
    //     icon: { icon: 'tabler-route-2' },
    //   },
    ],
  },
]
