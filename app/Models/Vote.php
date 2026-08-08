<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// PIC: Goh Chun Wen — Voting Process module.
class Vote extends Model
{
    public const CREATED_AT = 'cast_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'election_id',
        'voter_id',
        'candidate_id',
        'cast_at',
    ];

    protected function casts(): array
    {
        return ['cast_at' => 'datetime'];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }
}
