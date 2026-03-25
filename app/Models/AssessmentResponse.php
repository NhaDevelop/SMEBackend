<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentResponse extends Model
{
    protected $fillable = [
        'assessment_id', 'question_id', 
        'answer_value', 'score_awarded'
    ];

    protected $casts = [
        'answer_value' => 'array',
        'score_awarded' => 'decimal:2'
    ];

    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
