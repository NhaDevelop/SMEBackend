<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = ['name', 'version', 'industry', 'description', 'status', 'settings'];

    protected $casts = [
        'settings' => 'array'
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
