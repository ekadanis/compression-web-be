@if (session('status'))
    <div class="flash flash--success">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="flash flash--danger">
        {{ $errors->first() }}
    </div>
@endif
