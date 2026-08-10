{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', $activePanel === 'register' ? 'Create account' : 'Sign in')

@section('content')
<div class="user-module">
<section class="auth-grid auth-grid--switcher">
    <div class="auth-switcher {{ $activePanel === 'register' ? 'is-register' : 'is-login' }}" data-auth-switcher>
        <div class="auth-mobile-tabs" role="tablist" aria-label="Account options">
            <button type="button" role="tab" aria-controls="login-panel" aria-selected="{{ $activePanel === 'login' ? 'true' : 'false' }}" data-auth-mode="login">Sign in</button>
            <button type="button" role="tab" aria-controls="register-panel" aria-selected="{{ $activePanel === 'register' ? 'true' : 'false' }}" data-auth-mode="register">Create account</button>
        </div>

        <form id="login-panel" class="auth-panel auth-panel--login" method="post" action="{{ route('login.store') }}" novalidate>
            @csrf
            <h1 class="auth-card__title">Sign in</h1>

            <div class="auth-form-grid auth-form-grid--single">
                <div class="auth-field">
                    <label for="login-username">Username</label>
                    <input id="login-username" name="username" value="{{ old('username') }}" autocomplete="username" maxlength="50" required @if($activePanel === 'login') autofocus @endif>
                    @error('username')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="login-password">Password</label>
                    <div class="password-field">
                        <input id="login-password" name="password" type="password" autocomplete="current-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="login-password" aria-pressed="false">Show</button>
                    </div>
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <label class="check-field"><input name="remember" type="checkbox" value="1" @checked(old('remember'))> Remember me</label>
            <button class="button button-block" type="submit">Sign in</button>
        </form>

        <form id="register-panel" class="auth-panel auth-panel--register" method="post" action="{{ route('register.store') }}" novalidate>
            @csrf
            <h1 class="auth-card__title">Create account</h1>

            <div class="auth-form-grid">
                <div class="auth-field">
                    <label for="register-username">Username</label>
                    <input id="register-username" name="username" value="{{ old('username') }}" maxlength="50" autocomplete="username" required>
                    @error('username')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="full-name">Full name</label>
                    <input id="full-name" name="full_name" value="{{ old('full_name') }}" maxlength="100" autocomplete="name" required>
                    @error('full_name')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field auth-field--wide">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" required>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="ic-number">IC number</label>
                    <input id="ic-number" name="ic_number" value="{{ old('ic_number') }}" inputmode="numeric" maxlength="14" required>
                    @error('ic_number')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="date-of-birth">Date of birth</label>
                    <input id="date-of-birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" autocomplete="bday" required>
                    @error('date_of_birth')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="nationality">Nationality</label>
                    <select id="nationality" name="nationality_country_code" autocomplete="country-name" required>
                        @foreach ($nationalityOptions as $countryCode => $countryName)
                            <option value="{{ $countryCode }}" @selected(old('nationality_country_code', 'MY') === $countryCode)>{{ $countryName }}</option>
                        @endforeach
                    </select>
                    @error('nationality_country_code')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="phone-number">Phone number</label>
                    <input id="phone-number" name="phone_number" type="tel" value="{{ old('phone_number') }}" maxlength="30" autocomplete="tel" required>
                    @error('phone_number')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="register-password">Password</label>
                    <div class="password-field">
                        <input id="register-password" name="password" type="password" autocomplete="new-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="register-password" aria-pressed="false">Show</button>
                    </div>
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="auth-field">
                    <label for="password-confirmation">Confirm password</label>
                    <div class="password-field">
                        <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="password-confirmation" aria-pressed="false">Show</button>
                    </div>
                </div>
            </div>

            <button class="button button-block" type="submit">Create account</button>
        </form>

        <aside class="auth-cover" aria-live="polite">
            <div class="auth-cover__content auth-cover__content--login">
                <p>New to VoteTrust?</p>
                <button class="auth-cover__button" type="button" data-auth-mode="register">Create account</button>
            </div>
            <div class="auth-cover__content auth-cover__content--register">
                <p>Already have an account?</p>
                <button class="auth-cover__button" type="button" data-auth-mode="login">Sign in</button>
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

            document.querySelectorAll('.auth-mobile-tabs [data-auth-mode]').forEach((button) => {
                button.setAttribute('aria-selected', String(button.dataset.authMode === mode));
            });

            if (moveFocus) {
                document.getElementById(`${mode}-username`).focus();
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

    const validationMessage = (input) => {
        const value = input.value.trim();
        const registrationForm = input.form.id === 'register-panel';

        if (input.required && value === '') {
            return 'This field is required.';
        }

        if (!registrationForm) {
            if (input.name === 'username' && (value.length < 3 || !/^[A-Za-z0-9_.-]+$/.test(value))) {
                return 'Use at least 3 letters, numbers, dots, hyphens, or underscores.';
            }

            return '';
        }

        switch (input.name) {
            case 'username':
                return value.length >= 3 && /^[A-Za-z0-9_.-]+$/.test(value)
                    ? ''
                    : 'Use at least 3 letters, numbers, dots, hyphens, or underscores.';
            case 'full_name':
                return value.length >= 2 ? '' : 'Enter your full name.';
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Enter a valid email address.';
            case 'ic_number':
                return dateFromIcNumber(value) ? '' : 'Enter a valid 12-digit Malaysian IC number.';
            case 'date_of_birth': {
                const icDate = dateFromIcNumber(document.getElementById('ic-number').value);

                if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                    return 'Enter a valid date of birth.';
                }

                return icDate && value !== icDate ? 'This date must match the IC number.' : '';
            }
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
        const fields = Array.from(form.querySelectorAll('input:not([type="checkbox"]), select'));

        fields.forEach((input) => {
            const validate = () => {
                input.dataset.touched = 'true';
                validateField(input);

                if (input.id === 'ic-number') {
                    const dateOfBirth = document.getElementById('date-of-birth');

                    if (dateOfBirth.value !== '') {
                        validateField(dateOfBirth);
                    }
                }

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
                input.dataset.touched = 'true';

                return !validateField(input);
            });

            if (invalidField) {
                event.preventDefault();
                invalidField.focus();
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const password = document.getElementById(toggle.getAttribute('aria-controls'));

        toggle.addEventListener('click', () => {
            const showPassword = password.type === 'password';

            password.type = showPassword ? 'text' : 'password';
            toggle.textContent = showPassword ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', String(showPassword));
        });
    });
</script>
@endpush
