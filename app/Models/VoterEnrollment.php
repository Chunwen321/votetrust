<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// PIC: Chew Zhexuan — User, Voter, and Admin module.
class VoterEnrollment extends Model
{
    protected $table = 'voter';

    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const SUSPENDED = 'SUSPENDED';

    public const SOURCE_MANUAL = 'MANUAL';

    public const SOURCE_ORGANIZER_MANUAL = 'ORGANIZER_MANUAL';

    public const SOURCE_ORGANIZER_DIRECTORY = 'ORGANIZER_DIRECTORY';

    public const SOURCE_PRIVATE_CSV = 'PRIVATE_CSV';

    protected $fillable = [
        'user_id',
        'election_id',
        'status',
        'enrollment_source',
        'added_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
