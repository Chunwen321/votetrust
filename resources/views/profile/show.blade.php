{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="user-module">
<section class="page-heading">
    <p class="eyebrow">Member services</p>
    <h1>Your profile</h1>
    <p>Keep your contact details current and protect your account with a strong password.</p>
</section>

<div class="profile-layout">
    <section class="card profile-summary-card">
        <h2>Account details</h2>
        <dl class="key-values">
            <div><dt>Username</dt><dd>{{ $user->username }}</dd></div>
            <div><dt>IC number</dt><dd>{{ $user->ic_number }}</dd></div>
            <div><dt>Account status</dt><dd><span class="badge status-{{ strtolower($user->account_status ?? 'active') }}">{{ $user->account_status ?? 'ACTIVE' }}</span></dd></div>
            <div><dt>Role</dt><dd>{{ $user->is_admin ? 'System administrator' : 'Member' }}</dd></div>
        </dl>
    </section>

    <section class="card profile-form-card">
        <h2>Contact information</h2>
        <form class="form-stack" method="post" action="{{ route('profile.update') }}" novalidate>
            @csrf
            @method('PATCH')
            <label>Full name<input name="full_name" value="{{ old('full_name', $user->full_name) }}" maxlength="100" autocomplete="name" required></label>
            @error('full_name')<small class="field-error">{{ $message }}</small>@enderror
            <label>Email address<input name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="190" autocomplete="email" required></label>
            @error('email')<small class="field-error">{{ $message }}</small>@enderror
            <button type="submit">Save profile</button>
        </form>
    </section>
</div>

<section class="card password-card">
    <h2>Change password</h2>
    <form class="password-form" method="post" action="{{ route('profile.password.update') }}" novalidate>
        @csrf
        @method('PUT')
        <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
        @error('current_password')<small class="field-error">{{ $message }}</small>@enderror
        <label>New password<input name="password" type="password" autocomplete="new-password" required></label>
        @error('password')<small class="field-error">{{ $message }}</small>@enderror
        <label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" required></label>
        <button type="submit">Update password</button>
    </form>
</section>
</div>
@endsection
