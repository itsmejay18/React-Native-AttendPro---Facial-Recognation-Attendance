<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use Auditable;

    public const STATUSES = ['present', 'late', 'absent', 'excused'];

    protected $fillable = [
        'uuid', 'person_id', 'schedule_id', 'location_id', 'terminal_id', 'attendance_date',
        'time_in', 'time_out', 'status', 'source', 'match_confidence', 'notes',
        'recorded_by', 'corrected_by', 'corrected_at', 'revision',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'time_in' => 'datetime',
            'time_out' => 'datetime',
            'match_confidence' => 'float',
            'corrected_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }
}
