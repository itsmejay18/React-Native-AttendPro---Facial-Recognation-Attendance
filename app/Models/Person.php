<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use Auditable, SoftDeletes;

    public const TYPES = ['student', 'faculty', 'staff'];

    public const PROGRAMS = [
        'Bachelor of Science in Business Administration (BSBA)',
        'Bachelor of Science in Information Technology (BSIT)',
        'Bachelor of Science in Criminology (BSCRIM)',
        'Bachelor of Science in Elementary Education (BEED)',
        'Bachelor of Science in Secondary Education Major in English (BSED)',
    ];

    /** Roles exposed in the student attendance directory and enrollment flow. */
    public const DIRECTORY_TYPES = ['student'];

    protected $fillable = [
        'institution_id', 'type', 'first_name', 'middle_name', 'last_name', 'suffix',
        'email', 'phone', 'department_id', 'program', 'year_level', 'position',
        'status', 'joined_on', 'metadata',
    ];

    protected $appends = ['full_name', 'is_face_enrolled'];

    protected function casts(): array
    {
        return ['joined_on' => 'date', 'metadata' => 'array'];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            $middle = $this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '';
            $suffix = $this->suffix ? ', '.$this->suffix : '';

            return trim("{$this->first_name}{$middle} {$this->last_name}{$suffix}");
        });
    }

    protected function isFaceEnrolled(): Attribute
    {
        return Attribute::get(fn (): bool => $this->relationLoaded('activeFacialProfiles')
            ? $this->activeFacialProfiles->isNotEmpty()
            : $this->activeFacialProfiles()->exists());
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function facialProfiles(): HasMany
    {
        return $this->hasMany(FacialProfile::class);
    }

    public function activeFacialProfiles(): HasMany
    {
        return $this->facialProfiles()->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('retention_until')->orWhereDate('retention_until', '>=', today()));
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'schedule_assignments')
            ->withPivot(['effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
