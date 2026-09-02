{{-- PIC: Wong Shun Bin — Owned candidate application detail and management view. --}}
@extends('candidate.layout')

@section('title', 'Candidate application status')

@section('candidate-content')
    @php
        $statusDescription = match ($application->status) {
            \App\Models\Candidacy::PENDING => 'Your application is waiting for the election organizer’s decision.',
            \App\Models\Candidacy::APPROVED => 'Your application was approved. You are confirmed as a candidate for this election.',
            \App\Models\Candidacy::REJECTED => 'Your application was not approved. Return to the election to check whether reapplication is available.',
            \App\Models\Candidacy::CANCELLED => 'You cancelled this application before the deposit was completed.',
            default => 'Your latest application status is shown below.',
        };
        $statusClass = strtolower($application->status);
    @endphp

    <nav class="candidate-back-nav" aria-label="Candidate application navigation">
        <a href="{{ route('candidate.applications.index') }}">&larr; All applications</a>
    </nav>

    <div class="candidate-page-heading candidate-page-heading--actions">
        <div>
            <p class="candidate-eyebrow">Application status</p>
            <h1>{{ $application->election?->title ?? 'Candidate application' }}</h1>
            <p>{{ $statusDescription }}</p>
        </div>
        <span class="candidate-status candidate-status--{{ $statusClass }}">{{ $application->status }}</span>
    </div>

    <section class="candidate-card candidate-status-overview">
        <div class="candidate-application__summary">
            @if ($application->profile_photo)
                <img
                    class="candidate-photo candidate-photo--large"
                    src="{{ route('candidate.applications.photo', $application) }}"
                    alt="Your candidate photo for {{ $application->election?->title }}"
                >
            @endif
            <dl class="candidate-definition-list candidate-definition-list--grow">
                <div><dt>Position</dt><dd>{{ $application->election?->position_sought ?? 'Not recorded' }}</dd></div>
                <div><dt>Submitted</dt><dd>{{ $application->registered_at?->format('d M Y, H:i') ?? 'Not recorded' }}</dd></div>
                <div><dt>Application</dt><dd>{{ $application->status }}</dd></div>
                <div>
                    <dt>Deposit</dt>
                    <dd>
                        {{ $paymentStatus?->displayStatus() ?? 'No payment reference' }}
                        @if ($paymentStatus && ! $paymentStatus->available)
                            — actions temporarily disabled
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        @error('application')
            <p class="candidate-field-error" role="alert">{{ $message }}</p>
        @enderror

        <div class="candidate-actions">
            @if ($application->election)
                <a class="candidate-button" href="{{ route('elections.show', $application->election) }}">View election</a>
            @endif
            @if ($application->payment_id)
                <a class="candidate-button candidate-button--primary" href="{{ route('candidate.payments.show', $application->payment_id) }}">View deposit</a>
            @endif
            @if ($application->status === \App\Models\Candidacy::PENDING && $paymentStatus?->isPending())
                <form method="post" action="{{ route('candidate.applications.cancel', $application) }}">
                    @csrf
                    <button class="candidate-button candidate-button--danger" type="submit">Cancel application</button>
                </form>
            @endif
        </div>
    </section>

    <section class="candidate-card">
        <h2>Submitted application</h2>
        <div class="candidate-submission-section">
            <h3>Candidate introduction</h3>
            <p class="candidate-preserve-lines">{{ $application->candidate_introduction ?: 'Not provided' }}</p>
        </div>
        <div class="candidate-submission-section">
            <h3>Qualifications / experience</h3>
            <p class="candidate-preserve-lines">{{ $application->qualifications ?: 'Not provided' }}</p>
        </div>
        <div class="candidate-submission-section">
            <h3>Reason for candidacy</h3>
            <p class="candidate-preserve-lines">{{ $application->reason_for_candidacy ?: 'Not provided' }}</p>
        </div>
        <div class="candidate-submission-section">
            <h3>Manifesto</h3>
            <p class="candidate-preserve-lines">{{ $application->manifesto ?: 'Not provided' }}</p>
        </div>

    </section>
@endsection
