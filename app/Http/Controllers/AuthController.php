<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Security\Session\SessionTerminationStrategy;
use App\Services\UserService;
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

        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! $this->users->authenticate(
            (string) $request->validated('username'),
            (string) $request->validated('password'),
            $request->boolean('remember')
        )) {
            throw ValidationException::withMessages([
                'username' => 'We could not sign you in with those details.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        return redirect()->intended($this->homeFor($user))
            ->with('success', 'Welcome back, '.($user?->full_name ?: $user?->username).'.');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($this->homeFor(Auth::user()));
        }

        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = $this->users->register($request->validated());
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with(
            'success',
            'Your user account is ready. An organiser can now enrol you for an election.'
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
