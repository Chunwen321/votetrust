<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Security\Session;

use Illuminate\Container\Attributes\Bind;
use Illuminate\Http\Request;

#[Bind(FullSessionTerminationStrategy::class)]
interface SessionTerminationStrategy
{
    public function terminate(Request $request): void;
}
