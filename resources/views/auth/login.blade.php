{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
<div class="user-module">
<section class="auth-grid">
    <div class="auth-copy">
        <p class="eyebrow">VoteTrust member services</p>
        <h1>One secure identity for every election.</h1>
        <p>Sign in to manage your profile and review the elections where you have been enrolled.</p>
        <div class="trust-points">
            <span>Protected authentication</span>
            <span>Election-specific eligibility</span>
            <span>Secure session termination</span>
        </div>
    </div>

    <form class="card auth-card" method="post" action="{{ route('login.store') }}" novalidate>
        @csrf
        <p class="eyebrow">Secure account portal</p>
        <h2>Welcome back</h2>

        <label for="username">Username</label>
        <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" maxlength="50" required autofocus>
        @error('username')<small class="field-error">{{ $message }}</small>@enderror

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')<small class="field-error">{{ $message }}</small>@enderror

        <label class="check-field"><input name="remember" type="checkbox" value="1" @checked(old('remember'))> Keep me signed in</label>
        <button class="button button-block" type="submit">Sign in securely</button>
        <p class="auth-switch">New user? <a href="{{ route('register') }}">Create an account.</a></p>
    </form>
</section>
</div>
@endsection
