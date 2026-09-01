<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Security\Authentication\LoginAttemptResult;
use App\Security\Session\SessionTerminationStrategy;
use App\Services\UserService;
use App\Support\CountryList;
use App\Support\Identity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly SessionTerminationStrategy $sessionTermination,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($this->homeFor(Auth::user()));
        }

        return view('auth.login', [
            'activePanel' => 'login',
            'nationalityOptions' => array_diff_key(CountryList::options(), [CountryList::ANY => true]),
            'maximumLoginAttempts' => $this->maximumLoginAttempts(),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $result = $this->users->attemptAuthentication(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            $request->boolean('remember'),
        );

        if (! $result->succeeded()) {
            return redirect()->route('login')
                ->withInput($request->only('email', 'remember'))
                ->with('login_feedback', $this->loginFeedback($result));
        }

        $request->session()->regenerate();
        $user = $request->user();

        return redirect()->intended($this->homeFor($user))
            ->with('success', 'Welcome back, '.($user?->display_name ?: $user?->email).'.');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($this->homeFor(Auth::user()));
        }

        return view('auth.login', [
            'activePanel' => 'register',
            'nationalityOptions' => array_diff_key(CountryList::options(), [CountryList::ANY => true]),
            'maximumLoginAttempts' => $this->maximumLoginAttempts(),
        ]);
    }

    /**
     * Checks the two registration details that must be unique without submitting the form.
     *
     * The full RegisterRequest remains the authoritative validation when an account is created.
     */
    public function registrationAvailability(Request $request): JsonResponse
    {
        $field = (string) $request->input('field');

        if (! in_array($field, ['email', 'ic_number'], true)) {
            return response()->json(['message' => 'That registration field cannot be checked.'], 422);
        }

        $value = match ($field) {
            'email' => mb_strtolower(trim((string) $request->input('value'))),
            'ic_number' => Identity::normaliseIcNumber((string) $request->input('value')),
        };

        if ($value === '') {
            return response()->json([
                'field' => $field,
                'available' => true,
                'message' => null,
            ]);
        }

        $column = $field === 'email' ? 'email' : 'ic_number';
        $taken = User::query()->where($column, $value)->exists();

        return response()->json([
            'field' => $field,
            'available' => ! $taken,
            'message' => $taken
                ? ($field === 'email' ? 'The email has already been taken.' : 'The IC number has already been taken.')
                : null,
        ]);
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = $this->users->register($request->validated());
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('profile.verify')->with(
            'success',
            'Your account is ready. Complete eKYC profile verification to unlock voting, candidate applications, and election management.'
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->sessionTermination->terminate($request);

        return redirect()->route('login');
    }

    private function homeFor(mixed $user): string
    {
        return $user instanceof User && (bool) $user->is_admin
            ? route('admin.dashboard')
            : route('dashboard');
    }

    /**
     * @return array{
     *     type:string,
     *     title:string,
     *     message:string,
     *     remainingAttempts:?int,
     *     maximumAttempts:int,
     *     adminContact:?array{name:string,email:string,phone:?string}
     * }
     */
    private function loginFeedback(LoginAttemptResult $result): array
    {
        $locked = $result->status === LoginAttemptResult::ACCOUNT_LOCKED;
        $adminContact = $locked ? $this->activeAdministratorContact() : null;
        $remaining = $result->remainingAttempts;
        $remainingCopy = is_int($remaining)
            ? ' '.trans_choice(':count sign-in attempt remains|:count sign-in attempts remain', $remaining, ['count' => $remaining]).' before the account is locked.'
            : '';

        return [
            'type' => $locked ? 'locked' : 'invalid',
            'title' => $locked ? 'Account locked' : 'Invalid credentials',
            'message' => $locked
                ? ($adminContact === null
                    ? 'This account cannot sign in. Please contact a system administrator to restore access.'
                    : 'This account cannot sign in. Please contact the administrator below to restore access.')
                : 'The email address or password is incorrect.'.$remainingCopy,
            'remainingAttempts' => $remaining,
            'maximumAttempts' => $this->maximumLoginAttempts(),
            'adminContact' => $adminContact,
        ];
    }

    /** @return array{name:string,email:string,phone:?string}|null */
    private function activeAdministratorContact(): ?array
    {
        $administrator = User::query()
            ->where('is_admin', true)
            ->where('account_status', 'ACTIVE')
            ->whereNull('account_disabled_at')
            ->orderBy('id')
            ->first(['id', 'name', 'full_name', 'email', 'phone_number']);

        if (! $administrator instanceof User) {
            return null;
        }

        return [
            'name' => $administrator->display_name,
            'email' => (string) $administrator->email,
            'phone' => filled($administrator->phone_number) ? (string) $administrator->phone_number : null,
        ];
    }

    private function maximumLoginAttempts(): int
    {
        return max(1, (int) config('votetrust-user.auth.max_failed_attempts', 5));
    }
}
