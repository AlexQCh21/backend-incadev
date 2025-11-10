<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use IncadevUns\CoreDomain\Traits\HasIncadevCore;

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
}
