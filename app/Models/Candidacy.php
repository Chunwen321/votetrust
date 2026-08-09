<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// PIC: Low Wen Kai — Election candidate review lifecycle.
// PIC: Wong Shun Bin — Candidate Registration application data.
class Candidacy extends Model
{
    protected $table = 'candidacy';

    public const PENDING = 'Pending';

    public const APPROVED = 'Approved';

    public const REJECTED = 'Rejected';

    public const CANCELLED = 'Cancelled';

    protected $fillable = [
        'user_id',
        'election_id',
        'status',
        'registered_at',
        'position_sought',
        'manifesto',
        'supporting_details',
        'profile_photo',
        'profile_photo_mime',
        'payment_id',
    ];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }
}
