<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdministrativeDocument extends Model
{
    use HasFactory;

    protected $table = 'administrative_documents';

    protected $fillable = [
        'name',
        'type',
        'path',
        'version',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
