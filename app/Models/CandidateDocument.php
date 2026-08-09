<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// PIC: Wong Shun Bin — Candidate Registration document storage.
class CandidateDocument extends Model
{
    protected $table = 'candidate_document';

    public const CREATED_AT = 'uploaded_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'candidacy_id',
        'original_name',
        'stored_name',
        'mime_type',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    public function candidacy(): BelongsTo
    {
        return $this->belongsTo(Candidacy::class);
    }
}
