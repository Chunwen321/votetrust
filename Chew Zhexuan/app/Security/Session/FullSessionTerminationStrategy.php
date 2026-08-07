<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Security\Session;

use VoteTrust\UserAdmin\Core\Session;

/** Removes all server-side session data and expires the browser session cookie. */
final class FullSessionTerminationStrategy implements SessionTerminationStrategy
{
    public function terminate(): void
    {
        Session::destroy();
    }
}
