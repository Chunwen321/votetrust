<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// PIC: Low Wen Kai — Election hosting payment integration.
// PIC: Wong Shun Bin — Candidate application deposit integration.
class Payment extends Model
{
    protected $table = 'payment';

    protected $fillable = [
        'amount',
        'currency',
        'status',
        'organizer_id',
        'candidate_id',
        'provider_reference',
        'request_id',
        'response_json',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'response_json' => 'array',
            'verified_at' => 'datetime',
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

    public function elections(): HasMany
    {
        return $this->hasMany(Election::class);
    }

    public function candidacies(): HasMany
    {
        return $this->hasMany(Candidacy::class);
    }
}
