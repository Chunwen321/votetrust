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

@php($verificationComplete = $user->isVerified() || $user->isAdmin())
@php($canEditIdentity = ! $verificationComplete)
<section class="card profile-verification-card {{ $verificationComplete ? 'profile-verification-card--complete' : '' }}">
    <div class="profile-verification-card__main">
        <div class="profile-verification-card__icon" aria-hidden="true">
            @if ($verificationComplete)
                <svg viewBox="0 0 24 24" focusable="false"><path d="M12 3 5 6v5c0 4.5 2.9 8.6 7 10 4.1-1.4 7-5.5 7-10V6l-7-3Z"></path><path d="m8.8 12 2.1 2.1 4.4-4.5"></path></svg>
            @else
                <svg viewBox="0 0 24 24" focusable="false"><path d="M12 3 5 6v5c0 4.5 2.9 8.6 7 10 4.1-1.4 7-5.5 7-10V6l-7-3Z"></path><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>
            @endif
        </div>
        <div class="profile-verification-card__copy">
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
                <p>Your identity has been confirmed. You can organise elections, vote, and apply as a candidate.</p>
            @elseif ($ekycDriver === 'didit' && in_array($user->verification_status, ['PENDING', 'NOT_STARTED', 'IN_PROGRESS', 'AWAITING_USER', 'IN_REVIEW', 'RESUBMITTED'], true))
                <p>Didit will guide you through document capture and a selfie/liveness check. Your election access unlocks only after Didit returns an approved result.</p>
            @elseif (strtoupper($user->nationality_country_code ?? '') === 'MY')
                <p>Use eKYC to verify the Malaysian IC details saved to your account. Verification is required before you can organise, vote, or apply as a candidate.</p>
            @else
                <p>Use eKYC to verify the passport details and date of birth saved to your account. Verification is required before you can organise, vote, or apply as a candidate.</p>
            @endif
        </div>
    </div>
    <div class="profile-verification-card__status">
        <span class="badge {{ $verificationComplete ? 'status-approved' : 'status-pending' }}">{{ $user->isAdmin() ? 'eKYC exempt' : ($user->isVerified() ? 'Verified' : 'Verification required') }}</span>
        @if ($user->isVerified())
            <time datetime="{{ $user->verified_at?->toDateString() }}">Completed {{ $user->verified_at?->format('d M Y') }}</time>
        @elseif (! $user->isAdmin())
            <span>{{ str($user->verification_status ?? 'NOT_STARTED')->replace('_', ' ')->lower()->headline() }}</span>
        @endif
    </div>
    @if (! $user->isAdmin() && ! $user->isVerified())
        @php($hasResumableDiditSession = $ekycDriver === 'didit' && in_array($user->verification_status, ['PENDING', 'NOT_STARTED', 'IN_PROGRESS', 'AWAITING_USER', 'IN_REVIEW', 'RESUBMITTED'], true))
        <form class="profile-verification-card__action" method="post" action="{{ route('profile.verify.store') }}">
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
            @if ($canEditIdentity)
                <div class="profile-identity-edit">
                    <div>
                        <strong>Identity details</strong>
                        <p>You can update this until eKYC is completed. Changing it will restart any verification in progress.</p>
                    </div>
                    @if (strtoupper($user->nationality_country_code ?? '') === 'MY')
                        <label>IC number<input name="ic_number" value="{{ old('ic_number', $user->ic_number) }}" inputmode="numeric" maxlength="14" autocomplete="off" required></label>
                        <small>Enter 12 digits. Spaces and hyphens are accepted.</small>
                        @error('ic_number')<small class="field-error">{{ $message }}</small>@enderror
                    @else
                        <label>Passport number<input name="passport_number" value="{{ old('passport_number', $user->passport_number) }}" maxlength="20" autocomplete="off" required></label>
                        <small>Use 6–20 letters or numbers. Spaces and hyphens are accepted.</small>
                        @error('passport_number')<small class="field-error">{{ $message }}</small>@enderror
                    @endif
                </div>
            @elseif ($user->isVerified())
                <div class="profile-identity-locked">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>
                    <div><strong>Identity details are locked</strong><p>Your IC or passport number cannot be changed after eKYC verification.</p></div>
                </div>
            @endif
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
