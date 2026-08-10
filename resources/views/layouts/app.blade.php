{{-- PIC: Chew Zhexuan — User navigation integration. --}}
{{-- PIC: Low Wen Kai — Election navigation integration. --}}
{{-- PIC: Wong Shun Bin — Candidate navigation integration. --}}
{{-- PIC: Goh Chun Wen — Voting navigation integration. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="VoteTrust is a guided online election platform for organisers, candidates, and voters.">
    <title>@yield('title', 'VoteTrust') &middot; VoteTrust</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <a class="brand" href="{{ url('/') }}" aria-label="VoteTrust home">
            <span class="brand__mark" aria-hidden="true">VT</span>
            <span><strong>VoteTrust</strong><small>Trusted digital elections</small></span>
        </a>

        <nav class="site-nav" aria-label="Primary navigation">
            @auth
                @if (Route::has('dashboard'))
                    <a href="{{ route('dashboard') }}" @class(['is-active' => request()->routeIs('dashboard')])>Dashboard</a>
                @endif
                @if (Route::has('elections.index'))
                    <details @class([
                        'site-nav-dropdown',
                        'is-active' => request()->routeIs('elections.*', 'voter.*', 'voting.*', 'candidate.*'),
                    ])>
                        <summary>
                            <span class="site-nav-dropdown__label">Elections</span>
                            <span class="site-nav-dropdown__chevron" aria-hidden="true"></span>
                        </summary>
                        <div class="site-nav-dropdown__menu">
                            <a href="{{ route('elections.index') }}">Elections</a>
                            @if (Route::has('candidate.applications.create'))
                                <a href="{{ route('candidate.applications.create') }}">Candidate applications</a>
                            @endif
                        </div>
                    </details>
                @endif
                @if (auth()->user()->isAdmin() && Route::has('admin.dashboard'))
                    <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.*')])>Admin</a>
                @endif
            @endauth
            @guest
                @if (request()->routeIs('home'))
                    <a href="#why-votetrust">Why VoteTrust</a>
                    <a href="#how-it-works">How it works</a>
                @endif
            @endguest
        </nav>

        <div class="account-nav">
            @auth
                @if (Route::has('profile.show'))
                    <a href="{{ route('profile.show') }}" aria-label="Profile for {{ auth()->user()->display_name }}">Profile</a>
                @else
                    <span>{{ auth()->user()->display_name }}</span>
                @endif
                @if (Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="button button--quiet" type="submit">Sign out</button>
                    </form>
                @endif
            @else
                @if (Route::has('login'))
                    <a href="{{ route('login') }}">Sign in</a>
                @endif
                @if (Route::has('register'))
                    <a class="button button--primary" href="{{ route('register') }}">Create account</a>
                @endif
            @endauth
        </div>
    </div>
</header>

<main class="page-shell">
    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert--error" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert--error" role="alert">
            <strong>Please review the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<footer class="site-footer">
    <span>VoteTrust &middot; Clear, accountable elections</span>
    <span>SDG 16 &middot; Peace, Justice and Strong Institutions</span>
</footer>
@stack('scripts')
</body>
</html>
