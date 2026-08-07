<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Security\Session;

/** Defines how an authenticated browser session is terminated. */
interface SessionTerminationStrategy
{
    public function terminate(): void;
}
