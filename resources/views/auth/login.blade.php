@extends('layouts.guest', ['title' => 'Login | Web Kompresi'])

@section('content')
    <div class="panel">
        <div class="panel__header">
            <h2>Masuk ke dashboard</h2>
            <p>Pakai session auth Laravel untuk lanjut ke workspace Blade.</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="stack-lg">
            @csrf

            <div class="field">
                <label for="email" class="field__label">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="field__input">
            </div>

            <div class="field">
                <label for="password" class="field__label">Password</label>
                <input id="password" type="password" name="password" required class="field__input">
            </div>

            <label class="checkbox">
                <input type="checkbox" name="remember" value="1">
                <span>Ingat sesi ini</span>
            </label>

            <button type="submit" class="button button--primary button--block">Login</button>
        </form>

        <p class="panel__footer">
            Belum punya akun?
            <a href="{{ route('register') }}">Daftar di sini</a>
        </p>
    </div>
@endsection
