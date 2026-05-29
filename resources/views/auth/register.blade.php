@extends('layouts.guest', ['title' => 'Register | Web Kompresi'])

@section('content')
    <div class="panel">
        <div class="panel__header">
            <h2>Buat akun baru</h2>
            <p>Kita pakai akun ini untuk upload, compression history, dan integrasi berikutnya.</p>
        </div>

        <form method="POST" action="{{ route('register.store') }}" class="stack-lg">
            @csrf

            <div class="field">
                <label for="name" class="field__label">Nama</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus class="field__input">
            </div>

            <div class="field">
                <label for="email" class="field__label">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required class="field__input">
            </div>

            <div class="field">
                <label for="password" class="field__label">Password</label>
                <input id="password" type="password" name="password" required class="field__input">
            </div>

            <div class="field">
                <label for="password_confirmation" class="field__label">Konfirmasi Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required class="field__input">
            </div>

            <button type="submit" class="button button--primary button--block">Buat akun</button>
        </form>

        <p class="panel__footer">
            Sudah punya akun?
            <a href="{{ route('login') }}">Masuk di sini</a>
        </p>
    </div>
@endsection
