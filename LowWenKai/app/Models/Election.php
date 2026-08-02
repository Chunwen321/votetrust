<?php

declare(strict_types=1);

namespace VoteTrust\Election\Models;

use DateTimeImmutable;
use VoteTrust\Election\ORM\Model;

final class Election extends Model
{
    public const DRAFT = 'DRAFT';
    public const PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const PENDING_ADMIN_APPROVAL = 'PENDING_ADMIN_APPROVAL';
    public const NOMINATION_OPEN = 'NOMINATION_OPEN';
    public const VOTING_OPEN = 'VOTING_OPEN';
    public const CLOSED = 'CLOSED';
    public const CANCELLED = 'CANCELLED';
    public const REJECTED = 'REJECTED';

    protected static string $table = 'election';

    protected static ?string $versionColumn = 'version';

    protected static array $fillable = [
        'organizer_id',
        'title',
        'description',
        'eligibility_rules',
        'nomination_start',
        'nomination_end',
        'voting_start',
        'voting_end',
        'hosting_fee',
        'currency',
        'status',
        'payment_id',
        'payment_reference',
        'payment_verified_at',
        'payment_request_id',
        'payment_provider_status',
        'payment_response_json',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'transition_history_json',
        'version',
    ];

    public function organizer(): ?User
    {
        return User::find((int) $this->get('organizer_id'));
    }

    public function approvedBy(): ?User
    {
        $id = (int) $this->get('approved_by', 0);

        return $id > 0 ? User::find($id) : null;
    }

    /** @return list<ElectionTransitionLog> */
    public function transitionLogs(): array
    {
        $history = json_decode((string) $this->get('transition_history_json', '[]'), true);
        if (!is_array($history)) {
            return [];
        }

        $records = [];
        foreach (array_reverse($history) as $entry) {
            if (is_array($entry)) {
                $records[] = new ElectionTransitionLog($entry);
            }
        }

        return $records;
    }

    /** @param array<string, mixed> $record */
    public function appendTransition(array $record): void
    {
        $history = json_decode((string) $this->get('transition_history_json', '[]'), true);
        if (!is_array($history)) {
            $history = [];
        }
        $history[] = $record;
        $this->set('transition_history_json', json_encode(
            $history,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    public function paymentVerification(): ?PaymentVerification
    {
        $requestId = (string) $this->get('payment_request_id', '');
        if ($requestId === '') {
            return null;
        }

        $response = json_decode((string) $this->get('payment_response_json', '{}'), true);

        return new PaymentVerification(
            $requestId,
            (string) $this->get('payment_reference', ''),
            (string) $this->get('payment_provider_status', ''),
            is_array($response) ? $response : [],
            $this->get('payment_verified_at') !== null ? (string) $this->get('payment_verified_at') : null
        );
    }

    public function status(): string
    {
        return (string) $this->get('status', self::DRAFT);
    }

    public function date(string $attribute): DateTimeImmutable
    {
        $value = (string) $this->get($attribute, '');
        if ($value === '') {
            throw new \RuntimeException("Election date is not configured: {$attribute}");
        }

        return new DateTimeImmutable($value);
    }

    public function paymentVerified(): bool
    {
        return $this->get('payment_verified_at') !== null && $this->get('payment_verified_at') !== '';
    }

    public function adminApproved(): bool
    {
        return (int) $this->get('approved_by', 0) > 0
            && $this->get('approved_at') !== null
            && $this->get('approved_at') !== '';
    }

    public function ownedBy(User $user): bool
    {
        return (int) $this->get('organizer_id') === $user->id();
    }
}
