<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'password', 'phone', 'role', 'status', 'is_verified', 'last_login_at'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    // JWT Required Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        // Embed the role and status inside the token for fast frontend checks
        return [
            'role' => $this->role,
            'status' => $this->status
        ];
    }

    // Relationships
    public function smeProfile()
    {
        return $this->hasOne(SmeProfile::class);
    }

    public function investorProfile()
    {
        return $this->hasOne(InvestorProfile::class);
    }
}

