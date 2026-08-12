<?php

// PIC: Chew Zhexuan — blocks protected election actions until eKYC is complete.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureVerifiedProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ($user->isAdmin() || $user->isVerified())) {
            return $next($request);
        }

        return redirect()
            ->route('profile.verify')
            ->with('error', 'Complete eKYC profile verification before organising an election, voting, or applying as a candidate.');
    }
}
