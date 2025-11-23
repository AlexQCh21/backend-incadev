<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'goal_value',
        'current_value',
        'previous_value',
        'trend',
        'status',
    ];

    protected $casts = [
        'goal_value' => 'float',      // ← Cambiar de 'decimal:2' a 'float'
        'current_value' => 'float',   // ← Cambiar de 'decimal:2' a 'float'
        'previous_value' => 'float',  // ← Cambiar de 'decimal:2' a 'float'
        'trend' => 'float',           // ← Cambiar de 'decimal:2' a 'float'
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
