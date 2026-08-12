{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="user-module">
<section class="page-heading">
    <p class="eyebrow">Member services</p>
    <h1>Your profile</h1>
    <p>Keep your contact details current{{ $user->isAdmin() ? '' : ', complete eKYC verification,' }} and protect your account with a strong password.</p>
</section>

<section class="card profile-verification-card {{ $user->isVerified() || $user->isAdmin() ? 'profile-verification-card--complete' : '' }}">
    <div>
        <p class="eyebrow">{{ $user->isAdmin() ? 'Administrator account' : 'Profile verification' }}</p>
        <h2>
            @if ($user->isAdmin())
                eKYC is not required for administrators
            @elseif ($user->isVerified())
                Your profile is verified
            @else
                Verify your profile to unlock election actions
            @endif
        </h2>
        @if ($user->isAdmin())
            <p>Your administrator access is managed by VoteTrust. You can use the administrative workspace without completing eKYC.</p>
        @elseif ($user->isVerified())
            <p>Your eKYC verification was completed on {{ $user->verified_at?->format('d M Y, g:i A') }}. You can organise elections, vote, and apply as a candidate.</p>
        @elseif ($ekycDriver === 'didit' && in_array($user->verification_status, ['PENDING', 'NOT_STARTED', 'IN_PROGRESS', 'AWAITING_USER', 'IN_REVIEW', 'RESUBMITTED'], true))
            <p>Didit will guide you through document capture and a selfie/liveness check. Your election access unlocks only after Didit returns an approved result.</p>
        @elseif (strtoupper($user->nationality_country_code ?? '') === 'MY')
            <p>Use eKYC to verify the Malaysian IC details saved to your account. Verification is required before you can organise, vote, or apply as a candidate.</p>
        @else
            <p>Use eKYC to verify the passport details and date of birth saved to your account. Verification is required before you can organise, vote, or apply as a candidate.</p>
        @endif
    </div>
    @if (! $user->isAdmin() && ! $user->isVerified())
        @php($hasResumableDiditSession = $ekycDriver === 'didit' && in_array($user->verification_status, ['PENDING', 'NOT_STARTED', 'IN_PROGRESS', 'AWAITING_USER', 'IN_REVIEW', 'RESUBMITTED'], true))
        <form method="post" action="{{ route('profile.verify.store') }}">
            @csrf
            <label class="verification-consent"><input type="checkbox" name="ekyc_consent" value="1" required> I consent to sending my identity details to {{ $ekycDriver === 'didit' ? 'Didit' : 'the configured eKYC provider' }} for verification.</label>
            @if ($ekycDriver === 'didit')
                <small>Didit will capture your Malaysian IC or passport and perform the selfie/liveness check in its secure verification flow.</small>
            @endif
            <button type="submit">{{ $hasResumableDiditSession ? 'Continue with Didit' : 'Verify with eKYC' }}</button>
            @if ($user->verification_failure_reason)
                <small class="field-error">{{ $user->verification_failure_reason }}</small>
            @endif
        </form>
    @endif
</section>

<div class="profile-layout">
    <section class="card profile-summary-card">
        <h2>Account details</h2>
        <dl class="key-values">
            <div><dt>Email address</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>Nationality</dt><dd>{{ $user->nationality_country_code }}</dd></div>
            <div><dt>{{ strtoupper($user->nationality_country_code ?? '') === 'MY' ? 'IC number' : 'Passport number' }}</dt><dd>{{ strtoupper($user->nationality_country_code ?? '') === 'MY' ? $user->ic_number : $user->passport_number }}</dd></div>
            <div><dt>Date of birth</dt><dd>{{ $user->date_of_birth?->format('d M Y') ?? 'Not recorded' }}</dd></div>
            <div><dt>Verification</dt><dd><span class="badge {{ $user->isVerified() || $user->isAdmin() ? 'status-approved' : 'status-pending' }}">{{ $user->isAdmin() ? 'EXEMPT' : ($user->isVerified() ? 'VERIFIED' : 'VERIFICATION REQUIRED') }}</span></dd></div>
            @if (! $user->isAdmin() && ! $user->isVerified())
                <div><dt>Verification status</dt><dd>{{ str($user->verification_status ?? 'NOT_STARTED')->replace('_', ' ')->lower()->headline() }}</dd></div>
            @endif
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
