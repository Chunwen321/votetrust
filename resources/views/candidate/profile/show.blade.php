{{-- PIC: Wong Shun Bin — Candidate Registration profile and applications view. --}}
@extends('candidate.layout')

@section('title', 'My candidate profile')

@section('candidate-content')
    <div class="candidate-page-heading">
        <p class="candidate-eyebrow">Candidate account</p>
        <h1>My profile</h1>
    </div>

    <section class="candidate-card">
        <h2>Personal details</h2>
        <form method="post" action="{{ route('candidate.profile.update') }}">
            @csrf
            @method('PATCH')

            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" value="{{ old('full_name', $candidate?->full_name) }}" maxlength="100" required>

            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $candidate?->email) }}" maxlength="150" required>

            <label for="phone_number">Phone number</label>
            <input id="phone_number" name="phone_number" value="{{ old('phone_number', $candidate?->phone_number) }}" maxlength="30">

            <button class="candidate-button candidate-button--primary" type="submit">Update profile</button>
        </form>
    </section>

    <section class="candidate-card">
        <div class="candidate-section-heading">
            <h2>Applications</h2>
            <a class="candidate-button" href="{{ route('candidate.applications.create') }}">New application</a>
        </div>

        @forelse ($candidacies as $candidacy)
            @php($paymentStatus = $paymentStatuses[$candidacy->getKey()] ?? null)
            <article class="candidate-application">
                <div class="candidate-application__summary">
                    @if ($candidacy->profile_photo)
                        <img
                            class="candidate-photo"
                            src="{{ route('candidate.applications.photo', $candidacy) }}"
                            alt="Your candidate photo for {{ $candidacy->election?->title }}"
                        >
                    @endif
                    <div>
                        <h3>{{ $candidacy->election?->title ?? 'Election' }}</h3>
                        <p><strong>Position:</strong> {{ $candidacy->position_sought }}</p>
                        <p><strong>Application:</strong> {{ $candidacy->status }}</p>
                        <p>
                            <strong>Payment:</strong>
                            {{ $paymentStatus?->displayStatus() ?? 'No payment reference' }}
                            @if ($paymentStatus && ! $paymentStatus->available)
                                (actions disabled)
                            @endif
                        </p>
                    </div>
                </div>

                <details>
                    <summary>Application details</summary>
                    <p><strong>Manifesto:</strong></p>
                    <p class="candidate-preserve-lines">{{ $candidacy->manifesto }}</p>

                    @if ($candidacy->supporting_details)
                        <p><strong>Supporting details:</strong></p>
                        <p class="candidate-preserve-lines">{{ $candidacy->supporting_details }}</p>
                    @endif

                    @if ($candidacy->documents->isNotEmpty())
                        <p><strong>Private documents:</strong></p>
                        <ul>
                            @foreach ($candidacy->documents as $document)
                                <li>
                                    <a href="{{ route('candidate.applications.documents.download', [$candidacy, $document]) }}">
                                        {{ $document->original_name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </details>

                <div class="candidate-actions">
                    @if ($candidacy->payment_id)
                        <a class="candidate-button candidate-button--primary" href="{{ route('candidate.payments.show', $candidacy->payment_id) }}">
                            View payment
                        </a>
                    @endif

                    @if ($candidacy->status === 'Pending' && $paymentStatus?->isPending())
                        <form method="post" action="{{ route('candidate.applications.cancel', $candidacy) }}">
                            @csrf
                            <button class="candidate-button candidate-button--danger" type="submit">Cancel application</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p>You have not submitted a candidacy application.</p>
        @endforelse
    </section>
@endsection
