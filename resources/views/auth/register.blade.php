{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Create account')

@section('content')
<div class="user-module">
<section class="auth-grid">
    <div class="auth-copy">
        <p class="eyebrow">User registration</p>
        <h1>Start with a trusted VoteTrust account.</h1>
        <p>An account does not automatically grant voting rights. An administrator enrols eligible users for each election.</p>
        <div class="trust-points">
            <span>Strong-password policy</span>
            <span>IC identity matching</span>
            <span>Election-specific access</span>
        </div>
    </div>

    <form class="card auth-card" method="post" action="{{ route('register.store') }}" novalidate>
        @csrf
        <h2>Create your account</h2>

        <label for="full-name">Full name</label>
        <input id="full-name" name="full_name" value="{{ old('full_name') }}" maxlength="100" autocomplete="name" required>
        @error('full_name')<small class="field-error">{{ $message }}</small>@enderror

        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" required>
        @error('email')<small class="field-error">{{ $message }}</small>@enderror

        <label for="ic-number">IC number</label>
        <input id="ic-number" name="ic_number" value="{{ old('ic_number') }}" inputmode="numeric" maxlength="14" required>
        <small>Use your 12-digit Malaysian IC number; spaces and hyphens are removed.</small>
        @error('ic_number')<small class="field-error">{{ $message }}</small>@enderror

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <small>At least 12 characters with upper- and lower-case letters, a number, and a symbol.</small>
        @error('password')<small class="field-error">{{ $message }}</small>@enderror

        <label for="password-confirmation">Confirm password</label>
        <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

        <button class="button button-block" type="submit">Create user account</button>
        <p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in.</a></p>
    </form>
</section>
</div>
@endsection
