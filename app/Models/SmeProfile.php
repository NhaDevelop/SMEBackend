<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmeProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'registration_number', 
        'industry', 'stage', 'years_in_business', 
        'team_size', 'address', 'readiness_score', 'risk_level',
        'founding_date', 'website_url', 'verified_by_user_id', 'verification_date'
    ];

    protected $casts = [
        'founding_date' => 'date',
        'verification_date' => 'datetime',
        'readiness_score' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class, 'sme_id');
    }

    public function goals()
    {
        return $this->hasMany(Goal::class, 'sme_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'sme_id');
    }

    public function enrollments()
    {
        return $this->hasMany(ProgramEnrollment::class, 'sme_id');
    }
}
