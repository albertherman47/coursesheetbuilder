<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'academic_degree',
        'position',
        'first_name',
        'last_name',
        'neptun_code',
        'phone',
        'office_location',
        'consultation_hours',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function coordinatedPrograms(): HasMany
    {
        return $this->hasMany(Program::class, 'coordinator_id');
    }

    public function managedPrograms(): HasMany
    {
        return $this->hasMany(Program::class, 'program_manager_id');
    }


    public function courseLeadAssignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class, 'course_leader_id');
    }


    public function seminarLeadAssignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class, 'seminar_leader_id');
    }


    public function labLeadAssignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class, 'lab_leader_id');
    }


    public function projectLeadAssignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class, 'project_leader_id');
    }


    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }


    public function getFormattedName(): string
    {
        $parts = [];

        // Add position if available
        if (!empty($this->position)) {
            $parts[] = $this->position;
        }

        // Add academic degree if available
        if (!empty($this->academic_degree)) {
            $parts[] = $this->academic_degree;
        }

        // Add name (last_name, first_name)
        $firstName = $this->first_name ?? '';
        $lastName = $this->last_name ?? '';
        $fullName = trim("$lastName $firstName");
        if (!empty($fullName)) {
            $parts[] = $fullName;
        }

        return trim(implode(' ', $parts));
    }
}
