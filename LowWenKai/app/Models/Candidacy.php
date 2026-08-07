<?php

declare(strict_types=1);

namespace VoteTrust\Election\Models;

use VoteTrust\Election\ORM\Model;

/** Existing shared `candidacy` table populated by the Candidate Registration Module. */
final class Candidacy extends Model
{
    public const PENDING = 'Pending';
    public const APPROVED = 'Approved';
    public const REJECTED = 'Rejected';

    protected static string $table = 'candidacy';

    protected static bool $timestamps = false;

    protected static array $fillable = [
        'candidate_id',
        'election_id',
        'status',
        'registered_at',
    ];

    public function candidate(): ?Candidate
    {
        return Candidate::find((int) $this->get('candidate_id'));
    }

    public function candidateUser(): ?User
    {
        return $this->candidate()?->user();
    }

    public function status(): string
    {
        return (string) $this->get('status', self::PENDING);
    }
}
