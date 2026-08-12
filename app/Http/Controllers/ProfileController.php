<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\VerifyProfileRequest;
use App\Models\User;
use App\Services\EkycVerificationService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly EkycVerificationService $ekyc,
    ) {}

    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user(),
            'ekycDriver' => strtolower((string) config('votetrust-user.ekyc.driver', 'local')),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->users->updateProfile($user, $request->validated());

        return back()->with('success', 'Your profile has been updated.');
    }

    public function verify(VerifyProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        if ($user->isAdmin()) {
            return redirect()->route('profile.show')
                ->with('success', 'System administrator accounts are exempt from eKYC verification.');
        }

        $result = $this->ekyc->start($user);

        if ($result['redirectUrl'] !== null) {
            return redirect()->away($result['redirectUrl']);
        }

        return redirect()->route('profile.show')->with('success', 'Your profile has been verified successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->users->updatePassword($user, (string) $request->validated('password'));
        $request->session()->regenerate();

        return back()->with('success', 'Your password has been updated.');
    }
}
