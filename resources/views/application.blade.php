<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="{{ asset('iconetambasa.png') }}" type="image/png" />
  <link rel="icon" href="{{ asset('favicon.ico') }}" />
  <link rel="apple-touch-icon" href="{{ asset('iconetambasa.png') }}" />
  <link rel="shortcut icon" href="{{ asset('iconetambasa.png') }}" type="image/png" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tambasa - Sistema de Gestão Logística</title>
  <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
  @vite(['resources/ts/main.ts'])
</head>

<body>
  <div id="app">
    <div id="loading-bg">
      <div class="loading-logo">
        <!-- Logo PNG Tambasa -->
        <img src="{{ asset('iconetambasa.png') }}" width="48" height="48" alt="Tambasa" />
      </div>
      <div class=" loading">
        <div class="effect-1 effects"></div>
        <div class="effect-2 effects"></div>
        <div class="effect-3 effects"></div>
      </div>
    </div>
  </div>
  
  <script>
    // Detectar tema do Vuexy no localStorage
    const vuetifyTheme = localStorage.getItem('vuexy-vuetify-theme') || 'light'
    const isDark = vuetifyTheme === 'dark'

    // Definir cores baseadas no tema
    const loaderBgColor = isDark ? '#1a1a1a' : '#FFFFFF'
    const primaryColor = '#003595' // Cor azul Tambasa sempre

    // Aplicar cores do loader
    document.documentElement.style.setProperty('--initial-loader-bg', loaderBgColor)
    document.documentElement.style.setProperty('--initial-loader-color', primaryColor)

    // Definir cor de fundo do body para evitar flash
    document.body.style.backgroundColor = loaderBgColor
    </script>
  </body>
</html>
