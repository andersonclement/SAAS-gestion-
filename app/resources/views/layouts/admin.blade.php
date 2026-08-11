<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Superadmin')) — {{ config('app.name') }} Admin</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, sans-serif; background: #f1f2f6; color: #1a1d23; }
        .admin-shell { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 230px; background: #111827; color: #cbd5e1; flex-shrink: 0; padding: 1.5rem 0; }
        .admin-sidebar .brand { padding: 0 1.25rem 1.5rem; font-weight: 700; font-size: 1.1rem; color: #fff; border-bottom: 1px solid #1f2937; margin-bottom: 1rem; }
        .admin-sidebar .brand small { display: block; font-weight: 400; font-size: .75rem; color: #818cf8; margin-top: .15rem; }
        .admin-sidebar a { display: block; padding: .6rem 1.25rem; color: #cbd5e1; text-decoration: none; font-size: .92rem; border-left: 3px solid transparent; }
        .admin-sidebar a:hover { background: #1f2937; color: #fff; }
        .admin-sidebar a.active { background: #1f2937; color: #fff; border-left-color: #6366f1; font-weight: 600; }
        .admin-main { flex: 1; min-width: 0; }
        .admin-topbar { background: #fff; border-bottom: 1px solid #e2e4e8; padding: .9rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .admin-topbar form { display: inline; }
        .admin-content { padding: 2rem; max-width: 1200px; }
        .card { background: #fff; border: 1px solid #e2e4e8; border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .6rem; border-bottom: 1px solid #eee; font-size: .92rem; }
        th { color: #555; font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
        .btn { display: inline-block; background: #4338ca; color: #fff; padding: .5rem 1rem; border-radius: 6px; text-decoration: none; border: none; cursor: pointer; font-size: .95rem; }
        .btn:hover { background: #3730a3; }
        .btn-muted { background: #6b7280; }
        .btn-danger { background: #b91c1c; }
        .field { margin-bottom: 1rem; }
        .field label { display: block; font-weight: 600; margin-bottom: .3rem; font-size: .9rem; }
        .field input, .field select, .field textarea { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; }
        .errors { background: #fdecea; color: #611a15; border: 1px solid #f5c6cb; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .status { background: #e6f4ea; color: #1e4620; border: 1px solid #b7dfc0; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 600; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .kpi-card { background: #fff; border: 1px solid #e2e4e8; border-radius: 10px; padding: 1.25rem; }
        .kpi-card .label { color: #6b7280; font-size: .82rem; margin: 0 0 .3rem; }
        .kpi-card .value { font-size: 1.6rem; font-weight: 700; margin: 0; }
        code.code-pill { background: #eef2ff; color: #3730a3; padding: .2rem .5rem; border-radius: 5px; font-weight: 600; letter-spacing: .02em; }
    </style>
</head>
<body>
@auth('admin')
    <div class="admin-shell">
        <nav class="admin-sidebar">
            <div class="brand">{{ config('app.name') }}<small>{{ __('Espace superadmin') }}</small></div>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">{{ __('Tableau de bord') }}</a>
            <a href="{{ route('admin.tenants.index') }}" class="{{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}">{{ __('Clients (tenants)') }}</a>
            <a href="{{ route('admin.codes.index') }}" class="{{ request()->routeIs('admin.codes.*') ? 'active' : '' }}">{{ __("Codes d'activation") }}</a>
            <a href="{{ route('admin.journal.index') }}" class="{{ request()->routeIs('admin.journal.*') ? 'active' : '' }}">{{ __('Journal global') }}</a>
            <a href="{{ route('admin.admins.index') }}" class="{{ request()->routeIs('admin.admins.*') ? 'active' : '' }}">{{ __('Administrateurs') }}</a>
        </nav>
        <div class="admin-main">
            <div class="admin-topbar">
                <div></div>
                <div>
                    <span style="color:#555;font-size:.9rem;">{{ Auth::guard('admin')->user()->name }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="btn btn-muted" type="submit" style="margin-left:.75rem;">{{ __('Déconnexion') }}</button>
                    </form>
                </div>
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
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;">
        @if (session('status'))
            <div class="status" style="position:fixed;top:1.5rem;">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
@endauth
</body>
</html>
