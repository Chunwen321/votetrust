<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Security\Session\SessionTerminationStrategy;
use App\Services\UserService;
use App\Support\CountryList;
use App\Support\Identity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! $this->users->authenticate(
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            $request->boolean('remember')
        )) {
            throw ValidationException::withMessages([
                'email' => 'We could not sign you in with those details.',
            ]);
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
}
