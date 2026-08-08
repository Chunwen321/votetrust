<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'full_name',
        'ic_number',
        'email',
        'password',
        'is_admin',
        'account_status',
        'failed_login_attempts',
        'account_disabled_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'failed_login_attempts' => 'integer',
            'account_disabled_at' => 'datetime',
        ];
    }

    // PIC: Chew Zhexuan — User, authentication, and voter enrolment module.
    public function voterEnrollments()
    {
        return $this->hasMany(VoterEnrollment::class);
    }

    public function isActive(): bool
    {
        return $this->account_status === 'ACTIVE' && $this->account_disabled_at === null;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: ($this->name ?: $this->username);
    }

    // PIC: Low Wen Kai — Election organizer relationship.
    public function organizer()
    {
        return $this->hasOne(Organizer::class);
    }

    public function organizedElections()
    {
        return $this->hasMany(Election::class, 'organizer_id');
    }

    // PIC: Wong Shun Bin — Candidate registration relationship.
    public function candidate()
    {
        return $this->hasOne(Candidate::class);
    }

    // PIC: Goh Chun Wen — Voting relationship.
    public function votes()
    {
        return $this->hasMany(Vote::class, 'voter_id');
    }
}
