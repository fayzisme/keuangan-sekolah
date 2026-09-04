<?php

namespace App\Models;

use App\Domain\School\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * Kunci guard spatie/sanctum. Wajib roadmap API-first: role selalu di-assign
     * dengan guard 'sanctum' (lihat AuthSeeder); tanpa ini spatie fallback ke 'web'
     * dan middleware role:... melempar RoleDoesNotExist.
     */
    protected $guard_name = 'sanctum';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_user')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function activeSchool(): ?School
    {
        return $this->schools()->wherePivot('is_active', true)->first();
    }
}
