<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Security\Session;

use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;

final class FullSessionTerminationStrategy implements SessionTerminationStrategy
{
    public function __construct(private readonly AuthManager $auth) {}

    public function terminate(Request $request): void
    {
        $this->auth->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
