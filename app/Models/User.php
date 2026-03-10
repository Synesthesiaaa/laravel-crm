<?php

namespace App\Models;

use App\Casts\EncryptedIfPossible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivity, HasRoles;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['username', 'full_name', 'role'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
        'extension',
        'sip_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'vici_pass',
        'sip_password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'vici_pass' => EncryptedIfPossible::class,
            'sip_password' => 'encrypted',
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

    public function callSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CallSession::class);
    }

    public function vicidialSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VicidialAgentSession::class);
    }
}
