<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Web Kompresi' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell">
    <div class="app-frame">
        @include('partials.sidebar')

        <main class="app-main">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</body>
</html>
