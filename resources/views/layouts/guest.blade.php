<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Web Kompresi' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell">
    <div class="auth-grid">
        <section class="auth-brand">
            <div class="auth-brand__badge">WEB KOMPRESI</div>
            <h1 class="auth-brand__title">Satu workspace untuk upload, kompres, compare, dan distribusi media.</h1>
            <p class="auth-brand__copy">
                Kita pindahkan flow lama React ke Blade tanpa bikin alur kerja user jadi patah. Halaman ini jadi pintu masuk session auth untuk dashboard baru.
            </p>
            <div class="auth-brand__stats">
                <div class="auth-stat">
                    <span>FFMPEG</span>
                    <strong>Local Processing</strong>
                </div>
                <div class="auth-stat">
                    <span>Queue</span>
                    <strong>Ready for Redis</strong>
                </div>
                <div class="auth-stat">
                    <span>Target</span>
                    <strong>Blade Full Stack</strong>
                </div>
            </div>
        </section>

        <main class="auth-panel">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</body>
</html>
