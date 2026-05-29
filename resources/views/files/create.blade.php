@extends('layouts.app', ['title' => 'Upload File | Web Kompresi'])

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">Upload</p>
            <h1>Tambah file baru</h1>
            <p class="page-copy">Format yang didukung saat ini: MP4, MKV, AVI, MOV, MP3, WAV, AAC, OGG, dan M4A.</p>
        </div>
    </section>

    <section class="section-card section-card--narrow">
        <form method="POST" action="{{ route('files.store') }}" enctype="multipart/form-data" class="stack-lg">
            @csrf

            <div class="upload-box">
                <label for="file" class="field__label">Pilih file</label>
                <input id="file" type="file" name="file" required class="field__input">
                <p class="hint">Batas saat ini 512 MB mengikuti validasi backend yang sudah ada.</p>
            </div>

            <div class="button-row">
                <button type="submit" class="button button--primary">Upload file</button>
                <a href="{{ route('dashboard') }}" class="button button--ghost">Kembali</a>
            </div>
        </form>
    </section>
@endsection
