<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="{{ asset('favicon.ico') }}" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DASHBOARD AICE</title>
  <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
  @vite(['resources/ts/main.ts'])
</head>

<body>
  <div id="app">
    <div id="loading-bg">
      <div class="loading-logo">
        <img
          src="{{ asset('images/dgtcp-logo.png') }}"
          alt="DGTCP — Direction Générale du Trésor et de la Comptabilité Publique"
        />
      </div>
      <div class="loading">
        <div class="effect-1 effects"></div>
        <div class="effect-2 effects"></div>
        <div class="effect-3 effects"></div>
      </div>
    </div>
  </div>

  <script>
    // Palette DGTCP — alignée sur theme.ts et CHARTE_UI.md
    document.documentElement.style.setProperty('--initial-loader-bg', '#F5F7FA')
    document.documentElement.style.setProperty('--initial-loader-color', '#08A04B')
  </script>
</body>
</html>
