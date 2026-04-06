<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'version', 'industry', 'description', 'status', 'settings'];

    protected $casts = [
        'settings' => 'array'
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
