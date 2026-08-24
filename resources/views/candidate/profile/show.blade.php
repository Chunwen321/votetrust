{{-- PIC: Wong Shun Bin — Candidate profile view. --}}
@extends('candidate.layout')

@section('title', 'My candidate profile')

@section('candidate-content')
    <div class="candidate-page-heading">
        <p class="candidate-eyebrow">User account</p>
        <h1>My candidate profile</h1>
        <p>Keep the contact information connected to your candidate applications up to date.</p>
    </div>

    <section class="candidate-card">
        <h2>Personal details</h2>
        <form method="post" action="{{ route('candidate.profile.update') }}">
            @csrf
            @method('PATCH')

            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}" maxlength="100" required>

            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="150" required>

            <label for="phone_number">Phone number</label>
            <input id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" maxlength="30">

            <button class="candidate-button candidate-button--primary" type="submit">Update profile</button>
        </form>
    </section>

    <section class="candidate-card candidate-workspace-link">
        <div>
            <p class="candidate-eyebrow">Application workspace</p>
            <h2>Candidate applications</h2>
            <p>
                You have {{ $applicationCount }} application{{ $applicationCount === 1 ? '' : 's' }}.
                View decisions, payment status, submitted details, and available actions in one place.
            </p>
        </div>
        <a class="candidate-button" href="{{ route('candidate.applications.index') }}">Manage applications</a>
    </section>
@endsection
