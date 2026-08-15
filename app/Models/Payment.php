<?php

namespace App\Models;

use App\States\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// PIC: Low Wen Kai — Election hosting payment integration.
// PIC: Wong Shun Bin — Candidate application deposit integration.
// PIC: Risky Puah Kaho - Payment Module — gateway settlement and IFA provider endpoints.
class Payment extends Model
{
    protected $table = 'payment';

    public const PURPOSE_ELECTION_HOSTING = 'ELECTION_HOSTING';

    public const PURPOSE_CANDIDATE_DEPOSIT = 'CANDIDATE_DEPOSIT';

    public const PURPOSE_SYSTEM_TEST = 'SYSTEM_TEST';

    protected $fillable = [
        'amount',
        'currency',
        'status',
        'gateway',
        'organizer_id',
        'candidate_id',
        'election_id',
        'purpose',
        'provider_reference',
        'request_id',
        'response_json',
        'verified_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'response_json' => 'array',
            'verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function elections(): HasMany
    {
        return $this->hasMany(Election::class);
    }

    public function candidacies(): HasMany
    {
        return $this->hasMany(Candidacy::class);
    }

    public function paymentStatus(): PaymentStatus
    {
        return PaymentStatus::tryFrom((string) $this->status) ?? PaymentStatus::PENDING;
    }
}
