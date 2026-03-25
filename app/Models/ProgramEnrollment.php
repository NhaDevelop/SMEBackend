<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramEnrollment extends Model
{
    protected $fillable = [
        'program_id', 'sme_id', 'investor_id', 'status', 'enrollment_date'
    ];

    protected $casts = [
        'enrollment_date' => 'datetime'
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function smeProfile()
    {
        return $this->belongsTo(SmeProfile::class, 'sme_id');
    }

    public function investorProfile()
    {
        return $this->belongsTo(InvestorProfile::class, 'investor_id');
    }
}
