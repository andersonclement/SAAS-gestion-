<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Superadmin')) — {{ config('app.name') }} Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
@auth('admin')
    <div class="admin-shell">
        <div class="admin-sidebar">
            <div class="brand">{{ config('app.name') }}<small>{{ __('Espace superadmin') }}</small></div>
            <details class="nav-repliable">
                <summary>{{ __('Menu') }}</summary>
                <nav>
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">{{ __('Tableau de bord') }}</a>
                    <a href="{{ route('admin.tenants.index') }}" class="{{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}">{{ __('Clients (tenants)') }}</a>
                    <a href="{{ route('admin.codes.index') }}" class="{{ request()->routeIs('admin.codes.*') ? 'active' : '' }}">{{ __("Codes d'activation") }}</a>
                    <a href="{{ route('admin.journal.index') }}" class="{{ request()->routeIs('admin.journal.*') ? 'active' : '' }}">{{ __('Journal global') }}</a>
                    <a href="{{ route('admin.connexions.index') }}" class="{{ request()->routeIs('admin.connexions.*') ? 'active' : '' }}">{{ __('Connexions') }}</a>
                    <a href="{{ route('admin.admins.index') }}" class="{{ request()->routeIs('admin.admins.*') ? 'active' : '' }}">{{ __('Administrateurs') }}</a>
                </nav>
            </details>
        </div>

        <div class="admin-main">
            <div class="admin-topbar">
                <span style="color:#555;font-size:.9rem;">{{ Auth::guard('admin')->user()->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="btn btn-muted" type="submit">{{ __('Déconnexion') }}</button>
                </form>
            </div>
            <div class="admin-content">
                @if (session('status'))
                    <div class="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="errors">
                        <ul style="margin:0;padding-left:1.2rem;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>
@else
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;">
        @if (session('status'))
            <div class="status" style="position:fixed;top:1rem;">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
@endauth
</body>
</html>
