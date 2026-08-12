{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', $activePanel === 'register' ? 'Create account' : 'Sign in')

@section('content')
<div class="user-module">
<section class="auth-layout">
    <div class="auth-switcher {{ $activePanel === 'register' ? 'is-register' : 'is-login' }}" data-auth-switcher>
        <div class="auth-form-area">
            <div class="auth-form-area__intro">
                <p class="eyebrow">Secure election access</p>
                <h1 class="auth-card__title auth-card__title--login">Welcome back</h1>
                <h1 class="auth-card__title auth-card__title--register">Create your account</h1>
                <p class="auth-form-area__lead auth-form-area__lead--login">Sign in to participate in the elections that matter to you.</p>
                <p class="auth-form-area__lead auth-form-area__lead--register">Set up a trusted VoteTrust account in just a few minutes.</p>
            </div>

            <div class="auth-tabs" role="tablist" aria-label="Account options">
                <button type="button" role="tab" aria-controls="login-panel" aria-selected="{{ $activePanel === 'login' ? 'true' : 'false' }}" data-auth-mode="login">Sign in</button>
                <button type="button" role="tab" aria-controls="register-panel" aria-selected="{{ $activePanel === 'register' ? 'true' : 'false' }}" data-auth-mode="register">Create account</button>
            </div>

            <form id="login-panel" class="auth-panel auth-panel--login" method="post" action="{{ route('login.store') }}" novalidate>
            @csrf
            <div class="auth-form-grid auth-form-grid--single">
                <div class="auth-field">
                    <label for="login-email">Email address</label>
                    <input id="login-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="190" required @if($activePanel === 'login') autofocus @endif>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="login-password">Password</label>
                    <div class="password-field">
                        <input id="login-password" name="password" type="password" autocomplete="current-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="login-password" aria-label="Show password" aria-pressed="false">
                            <svg class="password-toggle__icon password-toggle__icon--show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                            <svg class="password-toggle__icon password-toggle__icon--hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.7 6.2A9.7 9.7 0 0 1 12 6c6.1 0 9.5 6 9.5 6a18 18 0 0 1-3.1 3.7M6.1 6.1A18.6 18.6 0 0 0 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.2 3.3-.6M9.5 9.5a3.5 3.5 0 0 0 4.9 4.9"/></svg>
                        </button>
                    </div>
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <label class="check-field"><input name="remember" type="checkbox" value="1" @checked(old('remember'))> Remember me</label>
            <button class="button button-block" type="submit">Sign in</button>
            <p class="auth-switch">New to VoteTrust? <button type="button" class="auth-text-button" data-auth-mode="register">Create an account</button></p>
        </form>

        @php($isMalaysianRegistration = old('nationality_country_code', 'MY') === 'MY')
        <form id="register-panel" class="auth-panel auth-panel--register" method="post" action="{{ route('register.store') }}" novalidate>
            @csrf
            <div class="auth-form-grid">
                <div class="auth-field auth-field--wide">
                    <label for="full-name">Full name</label>
                    <input id="full-name" name="full_name" value="{{ old('full_name') }}" maxlength="100" autocomplete="name" required>
                    @error('full_name')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field auth-field--wide">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" required>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field" data-identity-my @if (! $isMalaysianRegistration) hidden @endif>
                    <label for="ic-number">IC number</label>
                    <input id="ic-number" name="ic_number" value="{{ old('ic_number') }}" inputmode="numeric" maxlength="14" autocomplete="off" data-ic-number @required($isMalaysianRegistration) @disabled(! $isMalaysianRegistration)>
                    @error('ic_number')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field" data-identity-foreign @if ($isMalaysianRegistration) hidden @endif>
                    <label for="passport-number">Passport number</label>
                    <input id="passport-number" name="passport_number" value="{{ old('passport_number') }}" maxlength="20" autocomplete="off" @required(! $isMalaysianRegistration) @disabled($isMalaysianRegistration)>
                    @error('passport_number')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="phone-number">Phone number</label>
                    <input id="phone-number" name="phone_number" type="tel" value="{{ old('phone_number') }}" maxlength="30" autocomplete="tel" required>
                    @error('phone_number')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="nationality">Nationality</label>
                    <select id="nationality" name="nationality_country_code" autocomplete="country-name" required data-nationality-select>
                        @foreach ($nationalityOptions as $countryCode => $countryName)
                            <option value="{{ $countryCode }}" @selected(old('nationality_country_code', 'MY') === $countryCode)>{{ $countryName }}</option>
                        @endforeach
                    </select>
                    @error('nationality_country_code')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field" data-identity-foreign @if ($isMalaysianRegistration) hidden @endif>
                    <label for="date-of-birth">Date of birth</label>
                    <input id="date-of-birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" autocomplete="bday" @required(! $isMalaysianRegistration) @disabled($isMalaysianRegistration)>
                    @error('date_of_birth')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field auth-field--wide">
                    <label for="register-password">Password</label>
                    <div class="password-field">
                        <input id="register-password" name="password" type="password" autocomplete="new-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="register-password" aria-label="Show password" aria-pressed="false">
                            <svg class="password-toggle__icon password-toggle__icon--show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                            <svg class="password-toggle__icon password-toggle__icon--hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.7 6.2A9.7 9.7 0 0 1 12 6c6.1 0 9.5 6 9.5 6a18 18 0 0 1-3.1 3.7M6.1 6.1A18.6 18.6 0 0 0 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.2 3.3-.6M9.5 9.5a3.5 3.5 0 0 0 4.9 4.9"/></svg>
                        </button>
                    </div>
                    <ul class="password-requirements" data-password-requirements aria-label="Password requirements">
                        <li data-password-rule="length"><span class="password-requirements__icon" aria-hidden="true">○</span> At least 12 characters</li>
                        <li data-password-rule="upper"><span class="password-requirements__icon" aria-hidden="true">○</span> An uppercase letter</li>
                        <li data-password-rule="lower"><span class="password-requirements__icon" aria-hidden="true">○</span> A lowercase letter</li>
                        <li data-password-rule="number"><span class="password-requirements__icon" aria-hidden="true">○</span> A number</li>
                        <li data-password-rule="symbol"><span class="password-requirements__icon" aria-hidden="true">○</span> A symbol</li>
                        <li data-password-rule="match"><span class="password-requirements__icon" aria-hidden="true">○</span> Passwords match</li>
                    </ul>
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field auth-field--wide">
                    <label for="password-confirmation">Confirm password</label>
                    <div class="password-field">
                        <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="password-confirmation" aria-label="Show password" aria-pressed="false">
                            <svg class="password-toggle__icon password-toggle__icon--show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                            <svg class="password-toggle__icon password-toggle__icon--hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.7 6.2A9.7 9.7 0 0 1 12 6c6.1 0 9.5 6 9.5 6a18 18 0 0 1-3.1 3.7M6.1 6.1A18.6 18.6 0 0 0 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.2 3.3-.6M9.5 9.5a3.5 3.5 0 0 0 4.9 4.9"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <button class="button button-block" type="submit">Create account</button>
            <p class="auth-switch">Already registered? <button type="button" class="auth-text-button" data-auth-mode="login">Sign in</button></p>
        </form>

        </div>

        <aside class="auth-showcase" aria-live="polite">
            <div class="auth-showcase__content auth-showcase__content--login">
                <p class="auth-showcase__eyebrow">Your vote, protected</p>
                <h2>Clear elections.<br>Confident participation.</h2>
                <p>Follow every step—from enrolment to a securely recorded vote—with one trusted account.</p>
            </div>
            <div class="auth-showcase__content auth-showcase__content--register">
                <p class="auth-showcase__eyebrow">Start securely</p>
                <h2>One account.<br>Every election in reach.</h2>
                <p>Build your VoteTrust profile once, then take part when an administrator enrols you.</p>
            </div>

            <div class="auth-election-preview" aria-hidden="true">
                <div class="auth-election-preview__bar">
                    <span></span><span></span><span></span>
                </div>
                <div class="auth-election-preview__body">
                    <div class="auth-election-preview__side">
                        <strong>VoteTrust</strong>
                        <span class="is-active">Overview</span>
                        <span>My elections</span>
                        <span>Profile</span>
                    </div>
                    <div class="auth-election-preview__main">
                        <div class="auth-election-preview__heading">
                            <span>Upcoming election</span>
                            <i></i>
                        </div>
                        <article>
                            <small>STUDENT COUNCIL · 2026</small>
                            <strong>Campus Representative Election</strong>
                            <p>Voting opens in 3 days</p>
                            <div><span></span><span></span><span></span></div>
                        </article>
                        <div class="auth-election-preview__stats">
                            <span><b>24</b> candidates</span>
                            <span><b>1,248</b> eligible voters</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</section>
</div>
@endsection

@push('scripts')
<script>
    const authSwitcher = document.querySelector('[data-auth-switcher]');

    if (authSwitcher) {
        const setAuthMode = (mode, moveFocus = false) => {
            const isLogin = mode === 'login';

            authSwitcher.classList.toggle('is-login', isLogin);
            authSwitcher.classList.toggle('is-register', !isLogin);

            ['login', 'register'].forEach((panel) => {
                const isActive = panel === mode;
                const form = document.getElementById(`${panel}-panel`);

                form.inert = !isActive;
                form.setAttribute('aria-hidden', String(!isActive));
            });

            document.querySelectorAll('.auth-tabs [data-auth-mode]').forEach((button) => {
                button.setAttribute('aria-selected', String(button.dataset.authMode === mode));
            });

            if (moveFocus) {
                document.getElementById(isLogin ? 'login-email' : 'full-name').focus();
            }
        };

        setAuthMode(authSwitcher.classList.contains('is-register') ? 'register' : 'login');

        document.querySelectorAll('[data-auth-mode]').forEach((button) => {
            button.addEventListener('click', () => setAuthMode(button.dataset.authMode, true));
        });
    }

    const dateFromIcNumber = (value) => {
        const icNumber = value.replace(/[\s-]/g, '');

        if (!/^\d{12}$/.test(icNumber)) {
            return null;
        }

        let year = 2000 + Number(icNumber.slice(0, 2));
        const month = Number(icNumber.slice(2, 4));
        const day = Number(icNumber.slice(4, 6));

        if (year > new Date().getFullYear()) {
            year -= 100;
        }

        const date = new Date(year, month - 1, day);

        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return null;
        }

        return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    };

    const formatIcNumber = (value) => {
        const digits = value.replace(/\D/g, '').slice(0, 12);

        if (digits.length <= 6) {
            return digits;
        }
        if (digits.length <= 8) {
            return `${digits.slice(0, 6)}-${digits.slice(6)}`;
        }

        return `${digits.slice(0, 6)}-${digits.slice(6, 8)}-${digits.slice(8)}`;
    };

    const icNumberInput = document.querySelector('[data-ic-number]');
    if (icNumberInput) {
        icNumberInput.value = formatIcNumber(icNumberInput.value);
        icNumberInput.addEventListener('input', () => {
            icNumberInput.value = formatIcNumber(icNumberInput.value);
        });
    }

    const updateIdentityFields = () => {
        const nationality = document.querySelector('[data-nationality-select]');
        const isMalaysian = nationality?.value === 'MY';

        document.querySelectorAll('[data-identity-my]').forEach((field) => {
            const input = field.querySelector('input');
            field.hidden = !isMalaysian;
            input.disabled = !isMalaysian;
            input.required = isMalaysian;
        });
        document.querySelectorAll('[data-identity-foreign]').forEach((field) => {
            const input = field.querySelector('input');
            field.hidden = isMalaysian;
            input.disabled = isMalaysian;
            input.required = !isMalaysian;
        });
    };

    const validationMessage = (input) => {
        const value = input.value.trim();
        const registrationForm = input.form.id === 'register-panel';

        if (input.required && value === '') {
            return 'This field is required.';
        }

        if (!registrationForm) {
            return '';
        }

        switch (input.name) {
            case 'full_name':
                return value.length >= 2 ? '' : 'Enter your full name.';
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Enter a valid email address.';
            case 'ic_number':
                return input.disabled || dateFromIcNumber(value) ? '' : 'Enter a valid 12-digit Malaysian IC number.';
            case 'passport_number':
                return input.disabled || /^[A-Za-z0-9]{6,20}$/.test(value.replace(/[\s-]/g, '')) ? '' : 'Enter a valid passport number.';
            case 'date_of_birth':
                return input.disabled || (!Number.isNaN(Date.parse(value)) && value <= new Date().toISOString().slice(0, 10)) ? '' : 'Enter a valid date of birth.';
            case 'nationality_country_code':
                return /^[A-Z]{2}$/.test(value) ? '' : 'Select your nationality.';
            case 'phone_number':
                return /^[0-9+().\- ]+$/.test(value) ? '' : 'Enter a valid phone number.';
            case 'password':
                return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/.test(value)
                    ? ''
                    : 'Use 12+ characters with upper- and lower-case letters, a number, and a symbol.';
            case 'password_confirmation':
                return value === document.getElementById('register-password').value ? '' : 'Passwords do not match.';
            default:
                return '';
        }
    };

    const validateField = (input) => {
        const field = input.closest('.auth-field');

        if (!field) {
            return true;
        }

        let error = field.querySelector(`[data-live-error-for="${input.id}"]`);

        if (!error) {
            error = document.createElement('small');
            error.className = 'field-error field-error--live';
            error.id = `${input.id}-live-error`;
            error.dataset.liveErrorFor = input.id;
            error.setAttribute('aria-live', 'polite');
            field.append(error);
            input.setAttribute('aria-errormessage', error.id);
        }

        const message = validationMessage(input);

        field.classList.toggle('is-invalid', message !== '');
        input.setAttribute('aria-invalid', String(message !== ''));
        error.textContent = message;

        if (input.dataset.touched === 'true') {
            field.querySelectorAll('.field-error:not(.field-error--live)').forEach((serverError) => {
                serverError.hidden = true;
            });
        }

        return message === '';
    };

    document.querySelectorAll('.auth-panel').forEach((form) => {
        if (form.id === 'login-panel') {
            return;
        }

        const fields = Array.from(form.querySelectorAll('input:not([type="checkbox"]), select'));

        fields.forEach((input) => {
            const validate = () => {
                input.dataset.touched = 'true';
                validateField(input);

                if (input.id === 'register-password') {
                    const confirmation = document.getElementById('password-confirmation');

                    if (confirmation.value !== '') {
                        validateField(confirmation);
                    }
                }
            };

            input.addEventListener('input', validate);
            input.addEventListener('change', validate);
            input.addEventListener('blur', validate);
        });

        form.addEventListener('submit', (event) => {
            const invalidField = fields.find((input) => {
                if (input.disabled) {
                    return false;
                }
                input.dataset.touched = 'true';

                return !validateField(input);
            });

            if (invalidField) {
                event.preventDefault();
                invalidField.focus();
            }
        });
    });

    document.querySelector('[data-nationality-select]')?.addEventListener('change', updateIdentityFields);
    updateIdentityFields();

    const registerPassword = document.getElementById('register-password');
    const passwordConfirmation = document.getElementById('password-confirmation');
    const passwordRequirements = document.querySelector('[data-password-requirements]');

    const updatePasswordRequirements = () => {
        if (!registerPassword || !passwordRequirements) {
            return;
        }

        const password = registerPassword.value;
        const rules = {
            length: password.length >= 12,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /\d/.test(password),
            symbol: /[^A-Za-z0-9]/.test(password),
            match: password !== '' && password === passwordConfirmation?.value,
        };

        Object.entries(rules).forEach(([rule, met]) => {
            const item = passwordRequirements.querySelector(`[data-password-rule="${rule}"]`);
            if (!item) {
                return;
            }

            item.classList.toggle('is-met', met);
            item.querySelector('.password-requirements__icon').textContent = met ? '✓' : '○';
        });
    };

    registerPassword?.addEventListener('input', updatePasswordRequirements);
    passwordConfirmation?.addEventListener('input', updatePasswordRequirements);
    updatePasswordRequirements();

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const password = document.getElementById(toggle.getAttribute('aria-controls'));

        toggle.addEventListener('click', () => {
            const showPassword = password.type === 'password';

            password.type = showPassword ? 'text' : 'password';
            toggle.classList.toggle('is-visible', showPassword);
            toggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', String(showPassword));
        });
    });
</script>
@endpush
