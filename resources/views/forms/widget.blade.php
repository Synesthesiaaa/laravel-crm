<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
      (function() {
        var t = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
    <title>{{ $formName }} - {{ $campaignName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--color-surface)]">
    <main class="p-4 lg:p-6">
        @include('forms._content')
    </main>
    <script>
      (function () {
        function applyTheme(theme) {
          document.documentElement.setAttribute('data-theme', theme || 'dark');
        }

        applyTheme(localStorage.getItem('theme') || 'dark');

        window.addEventListener('storage', function (event) {
          if (event.key !== 'theme') return;
          applyTheme(event.newValue || 'dark');
        });
      })();
    </script>
</body>
</html>
