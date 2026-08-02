<?php

declare(strict_types=1);

namespace VoteTrust\Election\Models;

use VoteTrust\Election\ORM\Model;

final class Organizer extends Model
{
    protected static string $table = 'organizer';

    protected static bool $timestamps = false;

    protected static array $fillable = ['id'];

    public function user(): ?User
    {
        return User::find($this->id());
    }
}
