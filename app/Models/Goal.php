<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $fillable = [
        'sme_id', 'created_by', 'title', 'description', 
        'pillar_id', 'status', 'due_date', 
        'progress_percentage', 'target_score', 'pillar_targets',
        'proof_note', 'proof_document',
        'proof_verified', 'verified_by', 'verified_at',
        'rejection_note'
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected $casts = [
        'due_date' => 'date',
        'target_score' => 'decimal:2',
        'progress_percentage' => 'integer',
        'pillar_targets' => 'array',
        'proof_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function smeProfile()
    {
        return $this->belongsTo(SmeProfile::class, 'sme_id');
    }
}
