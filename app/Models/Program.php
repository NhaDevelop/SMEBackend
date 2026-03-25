<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'name', 'description', 'template_id', 'status',
        'start_date', 'end_date', 'sector', 'duration',
        'investment_amount', 'benefits', 'created_by_user_id'
    ];

    protected $casts = [
        'template_id' => 'integer',
        'benefits' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_by_user_id' => 'integer'
    ];

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function smes()
    {
        return $this->belongsToMany(User::class , 'program_sme', 'program_id', 'user_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function enrollments()
    {
        return $this->hasMany(ProgramEnrollment::class);
    }
}