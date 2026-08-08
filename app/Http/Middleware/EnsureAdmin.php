<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null
            && (bool) $user->is_admin
            && ($user->account_status ?? 'ACTIVE') === 'ACTIVE',
            403,
            'The administrative workspace is restricted to system administrators.'
        );

        return $next($request);
    }
}
