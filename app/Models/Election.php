<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// PIC: Low Wen Kai — Election Management Module.
class Election extends Model
{
    public const DRAFT = 'DRAFT';

    public const PENDING_PAYMENT = 'PENDING_PAYMENT';

    public const PENDING_ADMIN_APPROVAL = 'PENDING_ADMIN_APPROVAL';

    public const NOMINATION_OPEN = 'NOMINATION_OPEN';

    public const VOTING_OPEN = 'VOTING_OPEN';

    public const CLOSED = 'CLOSED';

    public const REJECTED = 'REJECTED';

    public const CANCELLED = 'CANCELLED';

    protected $fillable = [
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

    protected function casts(): array
    {
        return [
            'nomination_start' => 'datetime',
            'nomination_end' => 'datetime',
            'voting_start' => 'datetime',
            'voting_end' => 'datetime',
            'hosting_fee' => 'decimal:2',
            'payment_verified_at' => 'datetime',
            'payment_response_json' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'transition_history_json' => 'array',
            'version' => 'integer',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function candidacies(): HasMany
    {
        return $this->hasMany(Candidacy::class);
    }

    // PIC: Chew Zhexuan — Election-specific voter enrolments.
    public function voterEnrollments(): HasMany
    {
        return $this->hasMany(VoterEnrollment::class);
    }

    // PIC: Goh Chun Wen — Voting Process module.
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function scopeCurrentlyVoting(Builder $query): Builder
    {
        return $query
            ->where('status', self::VOTING_OPEN)
            ->where(fn (Builder $builder) => $builder
                ->whereNull('voting_start')
                ->orWhere('voting_start', '<=', now()))
            ->where(fn (Builder $builder) => $builder
                ->whereNull('voting_end')
                ->orWhere('voting_end', '>=', now()));
    }
}
