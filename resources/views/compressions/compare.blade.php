@extends('layouts.app', ['title' => 'Compare | Web Kompresi'])

@php use App\Support\Format; @endphp

@section('content')
    <section class="breadcrumb">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <span>/</span>
        <a href="{{ route('files.show', $file) }}">{{ $file->name }}</a>
        <span>/</span>
        <span>Compare</span>
    </section>

    <section class="page-head">
        <div>
            <p class="eyebrow">Compare</p>
            <h1>Bandingkan hasil compression</h1>
            <p class="page-copy">Original file dibandingkan langsung dengan output yang statusnya sudah selesai.</p>
        </div>
    </section>

    <section class="section-card">
        <div class="compare-original">
            <strong>Original</strong>
            <span>{{ $file->name }}</span>
            <span>{{ Format::bytes($file->size) }}</span>
            <span>{{ Format::duration($file->duration) }}</span>
            @if ($originalMetadata['codec'])
                <span>{{ $originalMetadata['codec'] }}</span>
            @endif
            @if ($originalMetadata['resolution'])
                <span>{{ str_replace(':', 'x', $originalMetadata['resolution']) }}</span>
            @endif
        </div>

        <div class="compare-grid">
            @foreach ($compressions as $compression)
                <article class="compare-card">
                    <header>
                        <h2>.{{ $compression->format }}</h2>
                        <span class="badge badge--done">done</span>
                    </header>

                    <dl class="meta-list">
                        <div><dt>Size</dt><dd>{{ Format::bytes($compression->size) }}</dd></div>
                        <div><dt>Reduction</dt><dd>{{ $file->size > 0 && $compression->size ? round((1 - $compression->size / $file->size) * 100, 1) : 0 }}%</dd></div>
                        <div><dt>Codec</dt><dd>{{ $compression->codec ?: '-' }}</dd></div>
                        @if ($compression->bitrate)<div><dt>Bitrate</dt><dd>{{ $compression->bitrate }} kbps</dd></div>@endif
                        @if ($compression->resolution)<div><dt>Resolution</dt><dd>{{ str_replace(':', 'x', $compression->resolution) }}</dd></div>@endif
                        @if ($compression->fps)<div><dt>FPS</dt><dd>{{ $compression->fps }}</dd></div>@endif
                        @if ($compression->audio_bitrate)<div><dt>Audio</dt><dd>{{ $compression->audio_bitrate }} kbps</dd></div>@endif
                        @if ($compression->sample_rate)<div><dt>Sample rate</dt><dd>{{ $compression->sample_rate }} Hz</dd></div>@endif
                        @if ($compression->channel)<div><dt>Channel</dt><dd>{{ $compression->channel }}</dd></div>@endif
                    </dl>

                    <a href="{{ $compression->url }}" class="button button--ghost button--block">Download</a>
                </article>
            @endforeach
        </div>
    </section>
@endsection
