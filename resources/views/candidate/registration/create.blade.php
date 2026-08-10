{{-- PIC: Wong Shun Bin — Candidate Registration application view. --}}
@extends('candidate.layout')

@section('title', 'Stand for election')

@section('candidate-content')
    <div class="candidate-page-heading">
        <p class="candidate-eyebrow">Nomination</p>
        <h1>Stand for election</h1>
        <p>Submit your details and private supporting files for organizer review.</p>
    </div>

    @if ($elections->isEmpty())
        <section class="candidate-card">
            <h2>No nominations are open</h2>
            <p>There is currently no election accepting candidate applications.</p>
        </section>
    @else
        <section class="candidate-card">
            <h2>New application</h2>
            <p class="candidate-help">
                A refundable security deposit of MYR {{ number_format((float) config('candidate.deposit_amount', 10), 2) }}
                will be created with Pending status. Its live status is retrieved from the Payment Module web service;
                payment-dependent actions stay disabled if that service cannot be verified.
            </p>

            <form method="post" action="{{ route('candidate.applications.store') }}" enctype="multipart/form-data">
                @csrf

                <label for="election_id">Election</label>
                <select id="election_id" name="election_id" required>
                    @foreach ($elections as $election)
                        <option value="{{ $election->getKey() }}" @selected((string) old('election_id', $selectedElectionId) === (string) $election->getKey())>
                            {{ $election->title }}
                            @if ($election->nomination_end)
                                — closes {{ $election->nomination_end->format('j M Y, g:i A') }}
                            @endif
                        </option>
                    @endforeach
                </select>

                <label for="position_sought">Position sought</label>
                <input id="position_sought" name="position_sought" value="{{ old('position_sought') }}" maxlength="100" required>

                <label for="manifesto">Manifesto</label>
                <textarea id="manifesto" name="manifesto" minlength="50" maxlength="5000" required>{{ old('manifesto') }}</textarea>
                <p class="candidate-help">Between 50 and 5,000 characters.</p>

                <label for="supporting_details">Supporting details</label>
                <textarea id="supporting_details" name="supporting_details" maxlength="3000">{{ old('supporting_details') }}</textarea>

                <label for="documents">Supporting documents</label>
                <input id="documents" type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                <p class="candidate-help">
                    Up to {{ (int) config('candidate.max_documents', 5) }} genuine PDF, JPG, or PNG files;
                    {{ number_format((int) config('candidate.max_document_kilobytes', 5120) / 1024) }} MiB each.
                </p>

                <label for="profile_photo">Candidate photo</label>
                <input id="profile_photo" type="file" name="profile_photo" accept=".jpg,.jpeg,.png" required>
                <p class="candidate-help">
                    Genuine JPG or PNG; at most
                    {{ number_format((int) config('candidate.max_photo_kilobytes', 2048) / 1024) }} MiB.
                </p>

                <button class="candidate-button candidate-button--primary" type="submit">Submit application</button>
            </form>
        </section>
    @endif
@endsection
