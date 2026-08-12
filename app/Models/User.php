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

    protected $table = 'user';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'full_name',
        'ic_number',
        'passport_number',
        'date_of_birth',
        'nationality_country_code',
        'phone_number',
        'email',
        'password',
        'is_admin',
        'account_status',
        'is_verified',
        'verified_at',
        'verification_reference',
        'verification_provider',
        'verification_session_id',
        'verification_status',
        'verification_requested_at',
        'verification_consent_at',
        'verification_last_event_id',
        'verification_failure_reason',
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
            'is_verified' => 'boolean',
            'date_of_birth' => 'date',
            'verified_at' => 'datetime',
            'verification_requested_at' => 'datetime',
            'verification_consent_at' => 'datetime',
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

    public function isVerified(): bool
    {
        return (bool) $this->is_verified && $this->verified_at !== null;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: ($this->name ?: $this->email);
    }
}
