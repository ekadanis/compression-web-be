<aside class="sidebar">
    <div class="sidebar__brand">
        <div class="sidebar__mark">WK</div>
        <div>
            <p class="sidebar__title">Web Kompresi</p>
            <p class="sidebar__subtitle">Media workspace</p>
        </div>
    </div>

    <nav class="sidebar__nav">
        <a href="{{ route('dashboard') }}" class="sidebar__link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
            Dashboard
        </a>
        <a href="{{ route('files.create') }}" class="sidebar__link {{ request()->routeIs('files.create') ? 'is-active' : '' }}">
            Upload File
        </a>
    </nav>

    <div class="sidebar__footer">
        <div class="sidebar__user">
            <div class="sidebar__avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div>
                <p class="sidebar__user-name">{{ auth()->user()->name }}</p>
                <p class="sidebar__user-email">{{ auth()->user()->email }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="button button--ghost button--block">Logout</button>
        </form>
    </div>
</aside>
