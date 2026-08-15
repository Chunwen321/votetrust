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
    <link rel="icon" type="image/png" href="{{ asset('img/votetrust-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/votetrust-icon.png') }}">
    <title>@yield('title', 'VoteTrust') &middot; VoteTrust</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
@php
    $primaryDestination = auth()->check()
        ? route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard')
        : route('home');
@endphp
<header class="site-header">
    <div class="site-header__inner">
        <a class="brand" href="{{ $primaryDestination }}" aria-label="{{ auth()->check() ? 'VoteTrust dashboard' : 'VoteTrust home' }}">
            <img class="brand__logo" src="{{ asset('img/votetrust-logo.png') }}" alt="VoteTrust">
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
                                @if (auth()->user()->isVerified())
                                    <a href="{{ route('candidate.applications.create') }}">Candidate applications</a>
                                @else
                                    <a href="{{ route('profile.verify') }}">Verify profile to apply</a>
                                @endif
                            @endif
                        </div>
                    </details>
                @endif
                @if (auth()->user()->isAdmin() && Route::has('admin.dashboard'))
                    <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.*')])>Admin</a>
                @endif
            @endauth
            @guest
                <a href="{{ route('home') }}#why-votetrust">Why VoteTrust</a>
                <a href="{{ route('home') }}#how-it-works">How it works</a>
                <a href="{{ route('faq') }}" @class(['is-active' => request()->routeIs('faq')])>FAQ</a>
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
        <div class="alert alert--error" role="alert" data-validation-summary>
            <strong>Please review the following:</strong>
            <ul>
                @foreach ($errors->getMessages() as $field => $messages)
                    @foreach ($messages as $error)
                        <li data-summary-error-for="{{ $field }}">{{ $error }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__content">
            <a class="site-footer__brand" href="{{ $primaryDestination }}" aria-label="{{ auth()->check() ? 'Go to your VoteTrust dashboard' : 'Go to the VoteTrust home page' }}">
                <img src="{{ asset('img/votetrust-logo.png') }}" alt="VoteTrust">
            </a>
            <p>Clear online elections for organisers, candidates, and voters—from setup to the final result.</p>
        </div>

        <nav class="site-footer__nav" aria-label="Footer navigation">
            <div class="site-footer__group">
                <h2>Explore</h2>
                @guest
                    <a href="{{ route('home') }}#why-votetrust">Why VoteTrust</a>
                    <a href="{{ route('home') }}#how-it-works">How it works</a>
                @else
                    <a href="{{ route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard') }}">Dashboard</a>
                    <a href="{{ route('elections.index') }}">Elections</a>
                @endguest
                <a href="{{ route('faq') }}">Frequently asked questions</a>
            </div>

            <div class="site-footer__group">
                <h2>Account</h2>
                @guest
                    <a href="{{ route('login') }}">Sign in</a>
                    <a href="{{ route('register') }}">Create account</a>
                @else
                    <a href="{{ route('profile.show') }}">Profile</a>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}">Manage members</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                @endguest
            </div>
        </nav>
    </div>

    <div class="site-footer__bottom">
        <span>&copy; {{ now()->year }} VoteTrust. Clear, accountable elections.</span>
        <span>SDG 16 &middot; Peace, Justice and Strong Institutions</span>
    </div>
</footer>
@stack('scripts')
</body>
</html>
