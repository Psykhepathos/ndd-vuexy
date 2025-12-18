export default [
  { heading: 'Formulários e Tabelas' },
  {
    title: 'Elementos de Formulário',
    icon: { icon: 'tabler-checkbox' },
    children: [
      { title: 'Autocomplete', to: 'forms-autocomplete' },
      { title: 'Checkbox', to: 'forms-checkbox' },
      { title: 'Combobox', to: 'forms-combobox' },
      { title: 'Seletor de Data/Hora', to: 'forms-date-time-picker' },
      { title: 'Editores', to: 'forms-editors' },
      { title: 'Upload de Arquivo', to: 'forms-file-input' },
      { title: 'Radio', to: 'forms-radio' },
      { title: 'Input Personalizado', to: 'forms-custom-input' },
      { title: 'Slider de Intervalo', to: 'forms-range-slider' },
      { title: 'Avaliação', to: 'forms-rating' },
      { title: 'Select', to: 'forms-select' },
      { title: 'Slider', to: 'forms-slider' },
      { title: 'Switch', to: 'forms-switch' },
      { title: 'Textarea', to: 'forms-textarea' },
      { title: 'Campo de Texto', to: 'forms-textfield' },
    ],
  },
  {
    title: 'Layouts de Formulário',
    icon: { icon: 'tabler-layout' },
    to: 'forms-form-layouts',
  },
  {
    title: 'Assistente de Formulário',
    icon: { icon: 'tabler-git-merge' },
    children: [
      { title: 'Numerado', to: 'forms-form-wizard-numbered' },
      { title: 'Ícones', to: 'forms-form-wizard-icons' },
    ],
  },
  {
    title: 'Validação de Formulário',
    icon: { icon: 'tabler-checkup-list' },
    to: 'forms-form-validation',
  },
  {
    title: 'Tabelas',
    icon: { icon: 'tabler-table' },
    children: [
      { title: 'Tabela Simples', to: 'tables-simple-table' },
      { title: 'Tabela de Dados', to: 'tables-data-table' },
    ],
  },
]
