@extends('layouts.app', ['title' => 'Compression Config | Web Kompresi'])

@php
    $isVideo = $file->isVideo();
    $videoFormats = ['mp4', 'mkv', 'avi', 'mov'];
    $audioFormats = ['mp3', 'wav', 'aac', 'ogg'];
@endphp

@section('content')
    <section class="breadcrumb">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <span>/</span>
        <a href="{{ route('files.show', $file) }}">{{ $file->name }}</a>
        <span>/</span>
        <span>Compression config</span>
    </section>

    <section class="page-head">
        <div>
            <p class="eyebrow">Compression</p>
            <h1>Atur output untuk {{ $file->name }}</h1>
            <p class="page-copy">Form ini memanggil service compression Laravel yang sudah ada, lalu menjalankan job ffmpeg di background.</p>
        </div>
    </section>

    <section class="section-card section-card--narrow">
        <form method="POST" action="{{ route('compressions.store', $file) }}" class="stack-lg">
            @csrf

            <input type="hidden" name="media_type" value="{{ $isVideo ? 'video' : 'audio' }}">

            <div class="grid-two">
                <div class="field">
                    <label class="field__label" for="format">Format output</label>
                    <select id="format" name="format" class="field__input">
                        @foreach ($isVideo ? $videoFormats : $audioFormats as $format)
                            <option value="{{ $format }}" @selected(old('format', $isVideo ? 'mp4' : 'mp3') === $format)>.{{ $format }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="field__label" for="codec">Codec</label>
                    <input id="codec" type="text" name="codec" value="{{ old('codec', $isVideo ? 'libx264' : 'libmp3lame') }}" class="field__input">
                </div>

                @if ($isVideo)
                    <div class="field">
                        <label class="field__label" for="bitrate">Video bitrate (kbps)</label>
                        <input id="bitrate" type="number" name="bitrate" value="{{ old('bitrate', 2000) }}" class="field__input">
                    </div>

                    <div class="field">
                        <label class="field__label" for="resolution">Resolution</label>
                        <select id="resolution" name="resolution" class="field__input">
                            <option value="">Keep original</option>
                            @foreach (['1920:1080', '1280:720', '854:480', '640:360'] as $resolution)
                                <option value="{{ $resolution }}" @selected(old('resolution', '1280:720') === $resolution)>{{ str_replace(':', 'x', $resolution) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="field__label" for="fps">FPS</label>
                        <select id="fps" name="fps" class="field__input">
                            <option value="">Keep original</option>
                            @foreach ([24, 30, 60] as $fps)
                                <option value="{{ $fps }}" @selected((string) old('fps', 30) === (string) $fps)>{{ $fps }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="field">
                    <label class="field__label" for="audio_bitrate">Audio bitrate (kbps)</label>
                    <input id="audio_bitrate" type="number" name="audio_bitrate" value="{{ old('audio_bitrate', 128) }}" class="field__input">
                </div>

                @unless ($isVideo)
                    <div class="field">
                        <label class="field__label" for="sample_rate">Sample rate</label>
                        <select id="sample_rate" name="sample_rate" class="field__input">
                            @foreach ([22050, 44100, 48000] as $sampleRate)
                                <option value="{{ $sampleRate }}" @selected((string) old('sample_rate', 44100) === (string) $sampleRate)>{{ $sampleRate }} Hz</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="field__label" for="channel">Channel</label>
                        <select id="channel" name="channel" class="field__input">
                            @foreach (['mono', 'stereo'] as $channel)
                                <option value="{{ $channel }}" @selected(old('channel', 'stereo') === $channel)>{{ ucfirst($channel) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_recommended" value="1" @checked(old('is_recommended'))>
                <span>Tandai sebagai recommended output</span>
            </label>

            <div class="button-row">
                <button type="submit" class="button button--primary">Start compression</button>
                <a href="{{ route('files.show', $file) }}" class="button button--ghost">Kembali</a>
            </div>
        </form>
    </section>
@endsection
