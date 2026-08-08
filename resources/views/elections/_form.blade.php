{{-- PIC: Low Wen Kai - Election Management Module --}}

@php
    $value = fn (string $key, mixed $fallback = '') => old($key, $election?->{$key} ?? ($defaults[$key] ?? $fallback));
    $mode = old('eligibility_mode', $eligibility->mode());
    $minimumAge = old('eligibility_minimum_age', $eligibility->minimumAge());
    $occupation = old('eligibility_occupation', $eligibility->occupation());
    $nationality = old('eligibility_nationality', $eligibility->nationality());
@endphp

<form class="election-card election-form" method="post" action="{{ $formAction }}" data-election-form>
    @csrf
    @if ($election)
        @method('PUT')
        <input type="hidden" name="version" value="{{ $election->version }}">
    @endif

    <fieldset>
        <legend>Election identity</legend>
        <label for="title">Title</label>
        <input id="title" name="title" maxlength="120" value="{{ $value('title') }}" required>
        @error('title') <small class="election-field-error">{{ $message }}</small> @enderror

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5" maxlength="2000" required>{{ $value('description') }}</textarea>
        @error('description') <small class="election-field-error">{{ $message }}</small> @enderror
    </fieldset>

    <fieldset>
        <legend>Voter eligibility</legend>
        <div class="election-choice-row">
            <label><input type="radio" name="eligibility_mode" value="PUBLIC" @checked($mode === 'PUBLIC')> Public</label>
            <label><input type="radio" name="eligibility_mode" value="RESTRICTED" @checked($mode === 'RESTRICTED')> Restricted</label>
        </div>
        @error('eligibility_mode') <small class="election-field-error">{{ $message }}</small> @enderror

        <div class="election-criteria" data-eligibility-criteria @if ($mode !== 'RESTRICTED') hidden @endif>
            <label for="eligibility_minimum_age">Minimum age</label>
            <select id="eligibility_minimum_age" name="eligibility_minimum_age">
                <option value="">Any age</option>
                @foreach (\App\ValueObjects\EligibilityRules::minimumAgeOptions() as $age)
                    <option value="{{ $age }}" @selected((string) $minimumAge === (string) $age)>{{ $age }} years or older</option>
                @endforeach
            </select>

            <label for="eligibility_occupation">Occupation</label>
            <select id="eligibility_occupation" name="eligibility_occupation">
                @foreach (\App\ValueObjects\EligibilityRules::occupationOptions() as $key => $label)
                    <option value="{{ $key }}" @selected($occupation === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <label for="eligibility_nationality">Nationality</label>
            <select id="eligibility_nationality" name="eligibility_nationality">
                @foreach (\App\ValueObjects\EligibilityRules::nationalityOptions() as $key => $label)
                    <option value="{{ $key }}" @selected($nationality === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @error('eligibility_criteria') <small class="election-field-error">{{ $message }}</small> @enderror
    </fieldset>

    <fieldset>
        <legend>Schedule</legend>
        <div class="election-field-grid">
            @foreach ([
                'nomination_start' => 'Nomination starts',
                'nomination_end' => 'Nomination ends',
                'voting_start' => 'Voting starts',
                'voting_end' => 'Voting ends',
            ] as $field => $label)
                @php
                    $dateValue = old($field);
                    if ($dateValue === null) {
                        $raw = $election?->{$field} ?? ($defaults[$field] ?? '');
                        $dateValue = $raw ? \Carbon\CarbonImmutable::parse($raw)->format('Y-m-d\TH:i') : '';
                    }
                @endphp
                <div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="datetime-local" value="{{ $dateValue }}" required>@error($field) <small class="election-field-error">{{ $message }}</small> @enderror</div>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend>Hosting fee</legend>
        <div class="election-field-grid">
            <div><label for="hosting_fee">Amount</label><input id="hosting_fee" name="hosting_fee" type="number" min="1" max="10000" step="0.01" value="{{ $value('hosting_fee', '100.00') }}" required>@error('hosting_fee') <small class="election-field-error">{{ $message }}</small> @enderror</div>
            <div><label for="currency">Currency</label><input id="currency" name="currency" maxlength="3" value="{{ $value('currency', 'MYR') }}" required>@error('currency') <small class="election-field-error">{{ $message }}</small> @enderror</div>
        </div>
    </fieldset>

    <div class="election-form-actions">
        <a class="election-button" href="{{ $election ? route('elections.show', $election) : route('elections.index') }}">Cancel</a>
        <button class="election-button election-button-primary" type="submit">{{ $submitLabel }}</button>
    </div>
</form>
