<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Program extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($program) {
            if (empty($program->slug)) {
                $program->slug = Str::slug($program->name);
            }
        });

        static::updating(function ($program) {
            if ($program->isDirty('name')) {
                $program->slug = Str::slug($program->name);
            }
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'template_id',
        'program_id',
        'status',
        'start_date',
        'end_date',
        'enrollment_deadline',
        'sector',
        'duration',
        'investment_amount',
        'benefits',
        'thresholds',
        'created_by_user_id'
    ];

    protected $casts = [
        'template_id' => 'integer',
        'program_id' => 'integer',
        'benefits' => 'array',
        'thresholds' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'enrollment_deadline' => 'datetime',
        'created_by_user_id' => 'integer'
    ];

    /**
     * Returns true if new enrollments are no longer allowed.
     * Checks enrollment_deadline if set, otherwise falls back to end_date.
     */
    public function isEnrollmentClosed(): bool
    {
        if ($this->enrollment_deadline) {
            return Carbon::now()->isAfter($this->enrollment_deadline);
        }
        if ($this->end_date) {
            return Carbon::now()->isAfter($this->end_date);
        }
        return false;
    }

    /**
     * Returns true if the program's assessment period has ended (based on end_date).
     */
    public function isAssessmentPeriodOver(): bool
    {
        if ($this->end_date) {
            return Carbon::now()->isAfter($this->end_date);
        }
        return false;
    }

    /**
     * Returns true if program's start date is in the future.
     */
    public function isComingSoon(): bool
    {
        if ($this->start_date) {
            return Carbon::now()->isBefore($this->start_date);
        }
        return false;
    }

    /**
     * Returns true if program is not coming soon and not closed.
     */
    public function isEnrollmentOpen(): bool
    {
        return !$this->isComingSoon() && !$this->isEnrollmentClosed();
    }

    /**
     * Returns true if program is considered "Finished" (status or end_date passed).
     */
    public function isFinished(): bool
    {
        return $this->status === 'Finished' || $this->isAssessmentPeriodOver();
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function enrollments()
    {
        return $this->hasMany(ProgramEnrollment::class);
    }
}