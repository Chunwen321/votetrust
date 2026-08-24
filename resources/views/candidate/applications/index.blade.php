{{-- PIC: Wong Shun Bin — Candidate application history and status workspace. --}}
@extends('candidate.layout')

@section('title', 'My candidate applications')

@section('candidate-content')
    <div class="candidate-page-heading candidate-page-heading--actions">
        <div>
            <p class="candidate-eyebrow">Application workspace</p>
            <h1>My candidate applications</h1>
            <p>Track every application, organizer decision, deposit status, and available next action.</p>
        </div>
        <a class="candidate-button" href="{{ route('elections.index') }}#candidate-elections">Browse election opportunities</a>
    </div>

    @if ($applications->isEmpty())
        <section class="candidate-card candidate-empty-state">
            <span class="candidate-empty-state__icon" aria-hidden="true">C</span>
            <div>
                <h2>No candidate applications yet</h2>
                <p>Open an election from the Elections page. When nominations are open, use its “Apply as candidate” button to begin.</p>
            </div>
            <a class="candidate-button candidate-button--primary" href="{{ route('elections.index') }}#candidate-elections">Browse elections</a>
        </section>
    @else
        <section class="candidate-application-list" aria-label="Candidate applications">
            @foreach ($applications as $application)
                @php($paymentStatus = $paymentStatuses[$application->getKey()] ?? null)
                @php($statusClass = strtolower($application->status))
                <article class="candidate-card candidate-application-card">
                    <div class="candidate-application-card__header">
                        <div>
                            <p class="candidate-eyebrow">{{ $application->election?->position_sought ?? 'Candidate position' }}</p>
                            <h2>{{ $application->election?->title ?? 'Election unavailable' }}</h2>
                        </div>
                        <span class="candidate-status candidate-status--{{ $statusClass }}">{{ $application->status }}</span>
                    </div>

                    <dl class="candidate-application-meta">
                        <div><dt>Submitted</dt><dd>{{ $application->registered_at?->format('d M Y, H:i') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Election stage</dt><dd>{{ str($application->election?->status ?? 'Unknown')->replace('_', ' ')->lower()->headline() }}</dd></div>
                        <div><dt>Deposit</dt><dd>{{ $paymentStatus?->displayStatus() ?? 'No payment reference' }}</dd></div>
                    </dl>

                    <div class="candidate-actions">
                        <a class="candidate-button candidate-button--primary" href="{{ route('candidate.applications.show', $application) }}">View application</a>
                        @if ($application->election)
                            <a class="candidate-button" href="{{ route('elections.show', $application->election) }}">View election</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
    @endif
@endsection
