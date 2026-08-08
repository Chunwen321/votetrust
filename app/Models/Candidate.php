<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// PIC: Low Wen Kai — Election candidate relationship.
// PIC: Wong Shun Bin — Candidate Registration module profile data.
class Candidate extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function candidacies(): HasMany
    {
        return $this->hasMany(Candidacy::class, 'candidate_id', 'user_id');
    }
}
