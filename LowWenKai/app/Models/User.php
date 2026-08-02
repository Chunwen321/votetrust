<?php

declare(strict_types=1);

namespace VoteTrust\Election\Models;

use VoteTrust\Election\ORM\Model;

final class User extends Model
{
    protected static string $table = 'user';

    protected static bool $timestamps = false;

    protected static array $fillable = [
        'username',
        'password_hash',
        'is_admin',
        'created_at',
    ];

    public function name(): string
    {
        return $this->username();
    }

    public function username(): string
    {
        return (string) $this->get('username');
    }

    public function passwordHash(): string
    {
        return (string) $this->get('password_hash');
    }

    public function isAdmin(): bool
    {
        return (int) $this->get('is_admin', 0) === 1;
    }

    public function isOrganizer(): bool
    {
        return Organizer::find($this->id()) instanceof Organizer;
    }

    public function role(): string
    {
        if ($this->isAdmin()) {
            return 'admin';
        }

        return $this->isOrganizer() ? 'organizer' : 'user';
    }

    /** @return list<Election> */
    public function elections(): array
    {
        if (!$this->isOrganizer()) {
            return [];
        }

        /** @var list<Election> $elections */
        $elections = Election::where('organizer_id', $this->id())->orderBy('created_at', 'DESC')->get();

        return $elections;
    }
}
