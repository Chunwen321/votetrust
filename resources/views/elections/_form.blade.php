{{-- PIC: Low Wen Kai - Election Management Module --}}

@php
    $value = fn (string $key, mixed $fallback = '') => old($key, $election?->{$key} ?? ($defaults[$key] ?? $fallback));
    $mode = old('eligibility_mode', $eligibility->mode());
    $minimumAge = old('eligibility_minimum_age', $eligibility->minimumAge());
    $nationality = old('eligibility_nationality', $eligibility->nationalityFormValue());
    $feeCurrency = strtoupper((string) config('election.hosting_fee.currency', 'MYR'));
    $publicFee = number_format((float) config('election.hosting_fee.public', 100), 2);
    $privateFee = number_format((float) config('election.hosting_fee.private', 120), 2);
@endphp

<form class="election-card election-form" method="post" action="{{ $formAction }}" data-election-form novalidate>
    @csrf
    @if ($election)
        @method('PUT')
        <input type="hidden" name="version" value="{{ $election->version }}">
    @endif

    <fieldset>
        <legend>Election identity</legend>
        <label class="election-required-label" for="title">Title <span class="election-required-marker" aria-hidden="true">*</span></label>
        <input id="title" name="title" maxlength="120" value="{{ $value('title') }}" required>
        @error('title') <small class="election-field-error" data-server-error-for="title">{{ $message }}</small> @enderror

        <label class="election-required-label" for="position_sought">Position sought <span class="election-required-marker" aria-hidden="true">*</span></label>
        <input id="position_sought" name="position_sought" maxlength="100" value="{{ $value('position_sought') }}" required>
        <p class="election-help">Every candidate in this election applies for this fixed position.</p>
        @error('position_sought') <small class="election-field-error">{{ $message }}</small> @enderror

        <label class="election-required-label" for="description">Description <span class="election-required-marker" aria-hidden="true">*</span></label>
        <textarea id="description" name="description" rows="5" maxlength="2000" required>{{ $value('description') }}</textarea>
        @error('description') <small class="election-field-error" data-server-error-for="description">{{ $message }}</small> @enderror
    </fieldset>

    <fieldset>
        <legend>Voter eligibility</legend>
        <div class="election-choice-row">
            <label><input type="radio" name="eligibility_mode" value="PUBLIC" @checked($mode === 'PUBLIC')> Public</label>
            <label><input type="radio" name="eligibility_mode" value="PRIVATE" @checked($mode === 'PRIVATE')> Private</label>
        </div>
        @error('eligibility_mode') <small class="election-field-error" data-server-error-for="eligibility_mode">{{ $message }}</small> @enderror

        <p class="election-help" data-public-help @if ($mode !== 'PUBLIC') hidden @endif>Public elections are open to verified VoteTrust users who meet the rules below.</p>
        <p class="election-help" data-private-help @if ($mode !== 'PRIVATE') hidden @endif>Private elections use an organiser roster. After saving the draft, upload a CSV/XLSX/XLS file with names and IC/passport numbers, or assign voters individually by IC or passport number.</p>

        <div class="election-criteria" data-eligibility-criteria @if ($mode !== 'PUBLIC') hidden @endif>
            <div class="election-criterion-field">
                <label class="election-required-label" for="eligibility_minimum_age">Minimum age <span class="election-required-marker" aria-hidden="true">*</span></label>
                <select id="eligibility_minimum_age" name="eligibility_minimum_age" required>
                    <option value="">Select minimum age</option>
                    @foreach (\App\ValueObjects\EligibilityRules::minimumAgeOptions() as $age)
                        <option value="{{ $age }}" @selected((string) $minimumAge === (string) $age)>{{ $age }} years or older</option>
                    @endforeach
                </select>
                @error('eligibility_minimum_age') <small class="election-field-error" data-server-error-for="eligibility_minimum_age">{{ $message }}</small> @enderror
            </div>

            <div class="election-criterion-field">
                <label for="eligibility_nationality">Nationality</label>
                <select id="eligibility_nationality" name="eligibility_nationality">
                    @foreach (\App\ValueObjects\EligibilityRules::nationalityOptions() as $key => $label)
                        <option value="{{ $key }}" @selected($nationality === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('eligibility_nationality') <small class="election-field-error" data-server-error-for="eligibility_nationality">{{ $message }}</small> @enderror
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Schedule</legend>
        <div class="election-schedule">
            @foreach ([
                [
                    'class' => 'nomination',
                    'step' => '01',
                    'title' => 'Nomination period',
                    'description' => 'Set when candidates can submit their applications.',
                    'fields' => [
                        'nomination_start' => 'Nomination starts',
                        'nomination_end' => 'Nomination ends',
                    ],
                ],
                [
                    'class' => 'voting',
                    'step' => '02',
                    'title' => 'Voting period',
                    'description' => 'Set when eligible voters can cast their votes.',
                    'fields' => [
                        'voting_start' => 'Voting starts',
                        'voting_end' => 'Voting ends',
                    ],
                ],
            ] as $phase)
                <section class="election-schedule-phase election-schedule-phase--{{ $phase['class'] }}" aria-labelledby="{{ $phase['class'] }}-period-title">
                    <header class="election-schedule-phase__header">
                        <span class="election-schedule-phase__step" aria-hidden="true">{{ $phase['step'] }}</span>
                        <div>
                            <h3 id="{{ $phase['class'] }}-period-title">{{ $phase['title'] }}</h3>
                            <p>{{ $phase['description'] }}</p>
                        </div>
                    </header>

                    <div class="election-field-grid">
                        @foreach ($phase['fields'] as $field => $label)
                            @php
                                $dateValue = old($field);
                                if ($dateValue === null) {
                                    $raw = $election?->{$field} ?? ($defaults[$field] ?? '');
                                    $dateValue = $raw ? \Carbon\CarbonImmutable::parse($raw)->format('Y-m-d\TH:i') : '';
                                }
                            @endphp
                            <div>
                                <label class="election-required-label" for="{{ $field }}">{{ $label }} <span class="election-required-marker" aria-hidden="true">*</span></label>
                                <input id="{{ $field }}" name="{{ $field }}" type="datetime-local" value="{{ $dateValue }}" required>
                                @error($field) <small class="election-field-error" data-server-error-for="{{ $field }}">{{ $message }}</small> @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend>Hosting fee</legend>
        <div class="election-fee-preview" data-hosting-fee-preview data-currency="{{ $feeCurrency }}" data-public-fee="{{ $publicFee }}" data-private-fee="{{ $privateFee }}">
            <span>Fixed fee for the selected election type</span>
            <strong data-hosting-fee-amount>{{ $feeCurrency }} {{ $mode === 'PRIVATE' ? $privateFee : $publicFee }}</strong>
            <small>Public: {{ $feeCurrency }} {{ $publicFee }} &middot; Private: {{ $feeCurrency }} {{ $privateFee }}</small>
        </div>
        <p class="election-form-note">VoteTrust calculates this fee automatically. It cannot be changed by the organiser or administrator.</p>
    </fieldset>

    <div class="election-form-actions">
        <a class="election-button" href="{{ $election ? route('elections.show', $election) : route('elections.index') }}">Cancel</a>
        <button class="election-button election-button-primary" type="submit">{{ $submitLabel }}</button>
    </div>
</form>
