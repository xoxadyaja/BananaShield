<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173d2c">
    <meta name="description" content="BananaShield preliminary disease screening, farm reporting, monitoring, and advisory support.">
    <title>@yield('title', 'BananaShield')</title>
    <link rel="icon" href="{{ asset('images/bananashield-logo.svg') }}?v={{ filemtime(public_path('images/bananashield-logo.svg')) }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/bananashield.css') }}?v={{ filemtime(public_path('css/bananashield.css')) }}">
</head>
<body class="{{ request()->routeIs('login') ? 'auth-route' : 'app-route' }}">
<a class="skip-link" href="#main-content">Skip to main content</a>
<div class="shell">
    <header class="app-header">
        <nav class="container nav" aria-label="Primary navigation">
            <a class="brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                <span class="brand-mark"><img src="{{ asset('images/bananashield-logo.svg') }}?v={{ filemtime(public_path('images/bananashield-logo.svg')) }}" alt="BananaShield banana and shield emblem"></span>
                <span><span class="brand-name">BananaShield</span><span class="brand-sub">DISEASE SCREENING &amp; FARM MONITORING</span></span>
            </a>
            <div class="nav-actions">
                @auth
                    <span class="avatar" title="{{ auth()->user()->name }}">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link" type="submit" aria-label="Sign out"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg></button></form>
                @endauth
            </div>
        </nav>
    </header>

    @if(request()->routeIs('login'))
        @yield('content')
    @else
        <div class="container app-frame">
        @auth
            @php
                $operational = auth()->user()->isOperational();
            @endphp
            <aside class="sidebar" aria-label="Workspace navigation">
                <div class="sidebar-user"><span class="sidebar-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ ucwords(str_replace('_',' ',auth()->user()->role)) }}</small></div></div>
                <p class="sidebar-label">Workspace</p>
                <nav class="sidebar-nav">
                    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 11 12 4l9 7v9H3z"/><path d="M9 20v-6h6v6"/></svg><span>Dashboard</span></a>
                    @if($operational)
                        @if(auth()->user()->canSubmitScreenings())<a class="{{ request()->routeIs('screenings.*') ? 'active' : '' }}" href="{{ route('screenings.create') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg><span>AI Screening</span></a>@endif
                        <a class="{{ request()->routeIs('monitoring','cases.*') ? 'active' : '' }}" href="{{ route('monitoring') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg><span>Monitoring &amp; Reports</span></a>
                    @endif
                    @if(auth()->user()->role === 'farm_owner')
                        <a class="{{ request()->routeIs('analytics') ? 'active' : '' }}" href="{{ route('analytics') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg><span>Analytics</span></a>
                        <a class="{{ request()->routeIs('farm-settings.*') ? 'active' : '' }}" href="{{ route('farm-settings.index') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 7h16M7 3v8M4 17h16M16 13v8"/></svg><span>Farm Profile &amp; Settings</span></a>
                        <a class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}" href="{{ route('accounts.index') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2-7 6-7s6 3 6 7M18 8v6M15 11h6"/></svg><span>Manage Accounts</span></a>
                    @endif
                    @if(auth()->user()->role === 'system_administrator')
                        <a class="{{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.index') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3 4 7v5c0 5 3 8 8 10 5-2 8-5 8-10V7z"/><path d="M9 12h6M12 9v6"/></svg><span>Administration</span></a>
                    @endif
                </nav>
                <p class="sidebar-label">Advisory support</p>
                <nav class="sidebar-nav"><a class="{{ request()->routeIs('advisories') ? 'active' : '' }}" href="{{ route('advisories') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 4h11a3 3 0 0 1 3 3v13H7a2 2 0 0 1-2-2z"/><path d="M8 8h7M8 12h6"/></svg><span>Advisory Library</span></a></nav>
                <div class="sidebar-help"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg><div><strong>Preliminary screening only</strong><small>Results do not replace agricultural assessment or laboratory confirmation.</small></div></div>
            </aside>
        @endauth
        <main class="main app-content" id="main-content" tabindex="-1">
            @if(session('success'))<div class="alert alert-success"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m5 12 4 4L19 6"/></svg><span>{{ session('success') }}</span></div>@endif
            @if($errors->any())<div class="alert alert-error"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg><span>{{ $errors->first() }}</span></div>@endif
            @yield('content')
        </main></div>
    @endif

    @auth
        @php
            $mobileNavClass = auth()->user()->role === 'farm_owner' ? 'five' : (auth()->user()->isOperational() ? 'four' : 'three');
        @endphp
        <nav class="mobile-nav mobile-nav-{{ $mobileNavClass }}" aria-label="Mobile navigation">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 11 12 4l9 7v9H3z"/></svg>Home</a>
            @if(auth()->user()->isOperational())
                @if(auth()->user()->canSubmitScreenings())<a class="{{ request()->routeIs('screenings.*') ? 'active' : '' }}" href="{{ route('screenings.create') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>Screen</a>@endif
                <a class="{{ request()->routeIs('monitoring','cases.*') ? 'active' : '' }}" href="{{ route('monitoring') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 5h16v14H4z"/></svg>Cases</a>
                @if(auth()->user()->role === 'farm_owner')
                    <a class="{{ request()->routeIs('analytics') ? 'active' : '' }}" href="{{ route('analytics') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 20V10M10 20V4M16 20v-7"/></svg>Analytics</a>
                    <a class="{{ request()->routeIs('farm-settings.*') ? 'active' : '' }}" href="{{ route('farm-settings.index') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 7h16M7 3v8M4 17h16M16 13v8"/></svg>Farm</a>
                    <a class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}" href="{{ route('accounts.index') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2-7 6-7s6 3 6 7M18 8v6M15 11h6"/></svg>Accounts</a>
                @else<a href="{{ route('advisories') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 4h14v16H5z"/></svg>Advisories</a>@endif
            @else
                <a class="{{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.index') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3 4 7v5c0 5 3 8 8 10 5-2 8-5 8-10V7z"/></svg>Admin</a>
                <a class="{{ request()->routeIs('advisories') ? 'active' : '' }}" href="{{ route('advisories') }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 4h14v16H5z"/></svg>Advisories</a>
            @endif
        </nav>
    @endauth
</div>
@stack('scripts')
</body>
</html>
