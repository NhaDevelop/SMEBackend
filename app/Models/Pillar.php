<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pillar extends Model
{
    protected $fillable = ['name', 'weight'];

    protected $casts = [
        'weight' => 'float',
    ];
}
