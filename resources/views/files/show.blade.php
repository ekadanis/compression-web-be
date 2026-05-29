@extends('layouts.app', ['title' => $file->name.' | Web Kompresi'])

@php use App\Support\Format; @endphp

@section('content')
    <section class="breadcrumb">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <span>/</span>
        <span>{{ $file->name }}</span>
    </section>

    <section class="section-card">
        <div class="detail-head">
            <div>
                <p class="eyebrow">{{ strtoupper($file->type) }}</p>
                <h1>{{ $file->name }}</h1>
                <p class="page-copy">
                    {{ Format::bytes($file->size) }} · {{ Format::duration($file->duration) }} · {{ Format::date($file->created_at) }}
                </p>
            </div>

            <div class="detail-head__actions">
                <span class="badge badge--{{ $file->status }}">{{ $file->status }}</span>
                <a href="{{ route('compressions.create', $file) }}" class="button button--primary">New compression</a>
                <form method="POST" action="{{ route('files.destroy', $file) }}" onsubmit="return confirm('Hapus file dan semua compression-nya?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button--danger">Delete file</button>
                </form>
            </div>
        </div>

        @if ($file->url)
            <div class="media-panel">
                @if ($file->isAudio())
                    <audio controls class="media-player">
                        <source src="{{ $file->url }}">
                    </audio>
                @else
                    <video controls class="media-player media-player--video">
                        <source src="{{ $file->url }}">
                    </video>
                @endif
            </div>
        @endif
    </section>

    <section class="section-card">
        <div class="section-card__header">
            <div>
                <h2>Compression results</h2>
                <p>Pilih minimal dua hasil compression yang statusnya sudah `done` untuk compare.</p>
            </div>
        </div>

        @if ($file->compressions->isEmpty())
            <div class="empty-state">
                <h3>Belum ada compression</h3>
                <p>Mulai dari preset baru untuk membuat output yang lebih kecil atau lebih kompatibel.</p>
                <a href="{{ route('compressions.create', $file) }}" class="button button--primary">Start compression</a>
            </div>
        @else
            <form method="GET" action="{{ route('compressions.compare', $file) }}" class="stack-lg">
                <div class="compression-grid">
                    @foreach ($file->compressions as $compression)
                        <article class="compression-card">
                            <div class="compression-card__top">
                                <div>
                                    @if ($compression->status === 'done')
                                        <label class="checkbox checkbox--compact">
                                            <input type="checkbox" name="ids[]" value="{{ $compression->id }}">
                                            <span>Select</span>
                                        </label>
                                    @endif
                                    <h3>.{{ $compression->format }}</h3>
                                    <p>{{ $compression->codec ?: 'codec auto' }}</p>
                                </div>
                                <span class="badge badge--{{ $compression->status }}">{{ $compression->status }}</span>
                            </div>

                            <dl class="meta-list">
                                <div><dt>Size</dt><dd>{{ Format::bytes($compression->size) }}</dd></div>
                                @if ($compression->bitrate)<div><dt>Bitrate</dt><dd>{{ $compression->bitrate }} kbps</dd></div>@endif
                                @if ($compression->resolution)<div><dt>Resolution</dt><dd>{{ str_replace(':', 'x', $compression->resolution) }}</dd></div>@endif
                                @if ($compression->fps)<div><dt>FPS</dt><dd>{{ $compression->fps }}</dd></div>@endif
                                @if ($compression->audio_bitrate)<div><dt>Audio</dt><dd>{{ $compression->audio_bitrate }} kbps</dd></div>@endif
                                @if ($compression->sample_rate)<div><dt>Sample rate</dt><dd>{{ $compression->sample_rate }} Hz</dd></div>@endif
                                @if ($compression->channel)<div><dt>Channel</dt><dd>{{ $compression->channel }}</dd></div>@endif
                            </dl>

                            @if ($compression->status === 'failed' && $compression->error_message)
                                <p class="error-copy">{{ $compression->error_message }}</p>
                            @endif

                            @if ($compression->url && $compression->status === 'done')
                                <div class="stack-sm">
                                    @if ($file->isAudio())
                                        <audio controls class="media-player media-player--compact">
                                            <source src="{{ $compression->url }}">
                                        </audio>
                                    @else
                                        <video controls class="media-player media-player--compact">
                                            <source src="{{ $compression->url }}">
                                        </video>
                                    @endif
                                    <a href="{{ $compression->url }}" class="button button--ghost button--block">Download</a>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('compressions.destroy', $compression) }}" onsubmit="return confirm('Hapus compression ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button button--danger button--block">Delete compression</button>
                            </form>
                        </article>
                    @endforeach
                </div>

                <div class="button-row">
                    <button type="submit" class="button button--primary">Compare selected</button>
                    <a href="{{ route('compressions.create', $file) }}" class="button button--ghost">Create another</a>
                </div>
            </form>
        @endif
    </section>

    @if ($file->compressions->contains(fn ($compression) => $compression->status === 'processing'))
        <script>
            setTimeout(() => window.location.reload(), 5000);
        </script>
    @endif
@endsection
