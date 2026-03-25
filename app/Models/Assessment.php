<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'sme_id', 'template_id', 'status', 
        'total_score', 'questions_snapshot', 
        'started_at', 'completed_at'
    ];

    protected $casts = [
        'questions_snapshot' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_score' => 'decimal:2'
    ];

    public function smeProfile()
    {
        return $this->belongsTo(SmeProfile::class, 'sme_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function responses()
    {
        return $this->hasMany(AssessmentResponse::class);
    }
}
