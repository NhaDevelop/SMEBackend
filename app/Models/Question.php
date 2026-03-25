<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'template_id', 'pillar_id', 'text', 'type', 
        'weight', 'required', 'options', 'helper_text'
    ];

    protected $casts = [
        'template_id' => 'integer',
        'pillar_id' => 'integer',
        'weight' => 'float',
        'options' => 'array',
        'required' => 'boolean'
    ];

    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
