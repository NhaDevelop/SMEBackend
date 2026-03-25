<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationRequest extends Model
{
    protected $fillable = ['user_id', 'document_type', 'status', 'evidence_link', 'notes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
