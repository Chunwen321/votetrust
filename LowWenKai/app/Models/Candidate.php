<?php

declare(strict_types=1);

namespace VoteTrust\Election\Models;

use VoteTrust\Election\ORM\Model;

/** Existing shared `candidate` table owned by the Candidate Registration Module. */
final class Candidate extends Model
{
    protected static string $table = 'candidate';

    protected static bool $timestamps = false;

    protected static array $fillable = ['id'];

    public function user(): ?User
    {
        return User::find($this->id());
    }
}
