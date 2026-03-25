<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestorInterest extends Model
{
    protected $fillable = [
        'investor_id', 'sme_id', 'notes'
    ];

    public function investorProfile()
    {
        return $this->belongsTo(InvestorProfile::class, 'investor_id');
    }

    public function smeProfile()
    {
        return $this->belongsTo(SmeProfile::class, 'sme_id');
    }
}
