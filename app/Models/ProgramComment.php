<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramComment extends Model
{
    protected $fillable = [
        'program_id', 'user_id', 'content'
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
