{{-- PIC: Wong Shun Bin — Election-scoped candidate application view. --}}
@extends('candidate.layout')

@section('title', $isReapplication ? 'Reapply as candidate' : 'Apply as candidate')

@section('candidate-content')
    <nav class="candidate-back-nav" aria-label="Candidate application navigation">
        <a href="{{ route('elections.show', $election) }}">&larr; Back to election</a>
    </nav>

    <div class="candidate-page-heading">
        <p class="candidate-eyebrow">{{ $isReapplication ? 'Candidate reapplication' : 'Candidate application' }}</p>
        <h1>{{ $isReapplication ? 'Reapply for this election' : 'Apply as a candidate' }}</h1>
        <p>Your application is linked to the election you selected. Review that fixed context before submitting your details.</p>
    </div>

    <section class="candidate-card candidate-election-context" aria-labelledby="selected-election-title">
        <div>
            <p class="candidate-eyebrow">Selected election</p>
            <h2 id="selected-election-title">{{ $election->title }}</h2>
            <p>{{ $election->description ?: 'No election description was provided.' }}</p>
        </div>
        <dl class="candidate-definition-list">
            <div><dt>Position</dt><dd>{{ $election->position_sought }}</dd></div>
            <div><dt>Nominations close</dt><dd>{{ $election->nomination_end?->format('d M Y, H:i') ?? 'No closing time configured' }}</dd></div>
        </dl>
        <p class="candidate-context-note">This election is fixed for this application. To apply for another election, return to Elections and open that election first.</p>
    </section>

    <section class="candidate-card">
        <div class="candidate-section-heading candidate-section-heading--start">
            <div>
                <p class="candidate-eyebrow">Application details</p>
                <h2>{{ $isReapplication ? 'Submit an updated application' : 'Tell voters about yourself' }}</h2>
            </div>
            <span class="candidate-step">Election selected</span>
        </div>

        <p class="candidate-help">
            A refundable security deposit of MYR {{ number_format((float) config('candidate.deposit_amount', 10), 2) }}
            will be created with Pending status. Payment-dependent actions stay disabled whenever the Payment Module cannot be verified.
        </p>

        @error('application')
            <p class="candidate-field-error" role="alert">{{ $message }}</p>
        @enderror

        <form method="post" action="{{ route('candidate.applications.store', $election) }}" enctype="multipart/form-data">
            @csrf

            <label for="manifesto">Manifesto</label>
            <textarea id="manifesto" name="manifesto" minlength="50" maxlength="5000" required data-live-length aria-describedby="manifesto-help manifesto-live-error">{{ old('manifesto') }}</textarea>
            <p id="manifesto-help" class="candidate-help">Describe the priorities and commitments you would bring to this position. Between 50 and 5,000 characters.</p>
            <p id="manifesto-live-error" class="candidate-live-validation" aria-live="polite"></p>
            @error('manifesto')<p class="candidate-field-error">{{ $message }}</p>@enderror

            <label for="candidate_introduction">Candidate introduction</label>
            <textarea id="candidate_introduction" name="candidate_introduction" minlength="20" maxlength="1000" required data-live-length aria-describedby="candidate-introduction-help candidate-introduction-live-error">{{ old('candidate_introduction') }}</textarea>
            <p id="candidate-introduction-help" class="candidate-help">Briefly introduce yourself, such as your course, year, or department.</p>
            <p id="candidate-introduction-live-error" class="candidate-live-validation" aria-live="polite"></p>
            @error('candidate_introduction')<p class="candidate-field-error">{{ $message }}</p>@enderror

            <label for="qualifications">Qualifications / experience</label>
            <textarea id="qualifications" name="qualifications" maxlength="1000" data-live-length aria-describedby="qualifications-help qualifications-live-error">{{ old('qualifications') }}</textarea>
            <p id="qualifications-help" class="candidate-help">Optional relevant experience, such as committee or club leadership.</p>
            <p id="qualifications-live-error" class="candidate-live-validation" aria-live="polite"></p>
            @error('qualifications')<p class="candidate-field-error">{{ $message }}</p>@enderror

            <label for="reason_for_candidacy">Reason for candidacy</label>
            <textarea id="reason_for_candidacy" name="reason_for_candidacy" minlength="20" maxlength="1000" required data-live-length aria-describedby="reason-help reason-live-error">{{ old('reason_for_candidacy') }}</textarea>
            <p id="reason-help" class="candidate-help">Explain why you want to stand for this position.</p>
            <p id="reason-live-error" class="candidate-live-validation" aria-live="polite"></p>
            @error('reason_for_candidacy')<p class="candidate-field-error">{{ $message }}</p>@enderror

            <label for="profile_photo">Candidate photo</label>
            <input id="profile_photo" type="file" name="profile_photo" accept=".jpg,.jpeg,.png" required>
            <p class="candidate-help">
                Genuine JPG or PNG; at most {{ number_format((int) config('candidate.max_photo_kilobytes', 2048) / 1024) }} MiB.
            </p>
            @error('profile_photo')<p class="candidate-field-error">{{ $message }}</p>@enderror

            <label for="documents">Supporting documents <span class="candidate-optional">Optional</span></label>
            <input id="documents" type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
            <p class="candidate-help">Upload up to {{ (int) config('candidate.max_documents', 5) }} private PDF, JPG, or PNG documents for organizer review.</p>
            @error('documents')<p class="candidate-field-error">{{ $message }}</p>@enderror
            @error('documents.*')<p class="candidate-field-error">{{ $message }}</p>@enderror

            <div class="candidate-form-actions">
                <a class="candidate-button" href="{{ route('elections.show', $election) }}">Cancel</a>
                <button class="candidate-button candidate-button--primary" type="submit">{{ $isReapplication ? 'Submit reapplication' : 'Submit application' }}</button>
            </div>
        </form>
    </section>
@endsection
