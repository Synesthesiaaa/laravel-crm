<?php

namespace App\Models;

use App\Casts\EncryptedIfPossible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'Super Admin';
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_TEAM_LEADER = 'Team Leader';
    public const ROLE_AGENT = 'Agent';

    protected $fillable = [
        'username',
        'name',
        'full_name',
        'email',
        'password',
        'role',
        'vici_user',
        'vici_pass',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'vici_pass',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'vici_pass' => EncryptedIfPossible::class,
        ];
    }

    public function username(): string
    {
        return 'username';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isTeamLeader(): bool
    {
        return $this->role === self::ROLE_TEAM_LEADER || $this->isAdmin();
    }

    public function isAgent(): bool
    {
        return !empty($this->role);
    }

    public function attendanceLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
