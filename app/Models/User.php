<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use IncadevUns\CoreDomain\Traits\HasIncadevCore;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\Enrollment;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasIncadevCore;

    protected $fillable = [
        'name',
        'email',
        'password',
        'dni',
        'fullname',
        'avatar',
        'phone',
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

      // Relación con grupos como profesor
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            Group::class,
            'group_teachers',
            'user_id',
            'group_id'
        )->withTimestamps();
    }

    // Relación con matrículas como estudiante
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'user_id');
    }

    // Relación con contratos como staff
    public function contracts(): HasMany
    {
        return $this->hasMany(\IncadevUns\CoreDomain\Models\Contract::class, 'user_id');
    }
}
