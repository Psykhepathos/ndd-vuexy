export default [
  { heading: 'Outros' },
  {
    title: 'Controle de Acesso',
    icon: { icon: 'tabler-command' },
    to: 'access-control',
    action: 'read',
    subject: 'AclDemo',
  },
  {
    title: 'Níveis de Menu',
    icon: { icon: 'tabler-menu-2' },
    children: [
      {
        title: 'Nível 2.1',
        to: null,
      },
      {
        title: 'Nível 2.2',
        children: [
          {
            title: 'Nível 3.1',
            to: null,
          },
          {
            title: 'Nível 3.2',
            to: null,
          },
        ],
      },
    ],
  },
  {
    title: 'Menu Desabilitado',
    to: null,
    icon: { icon: 'tabler-eye-off' },
    disable: true,
  },
  {
    title: 'Suporte',
    href: 'https://pixinvent.ticksy.com/',
    icon: { icon: 'tabler-headphones' },
    target: '_blank',
  },
  {
    title: 'Documentação',
    href: 'https://demos.pixinvent.com/vuexy-vuejs-admin-template/documentation/guide/laravel-integration/folder-structure.html',
    icon: { icon: 'tabler-file-text' },
    target: '_blank',
  },
]
