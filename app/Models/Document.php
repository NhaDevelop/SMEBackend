<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'sme_id', 'name', 'original_filename', 
        'type', 'category', 'description', 
        'size', 'file_url', 'is_verified',
        'verified_by_user_id', 'uploaded_at'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'size' => 'integer',
        'uploaded_at' => 'datetime'
    ];

    public function smeProfile()
    {
        return $this->belongsTo(SmeProfile::class, 'sme_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
