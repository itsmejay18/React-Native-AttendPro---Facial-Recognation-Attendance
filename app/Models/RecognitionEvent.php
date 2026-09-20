<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecognitionEvent extends Model
{
    protected $fillable = [
        'uuid', 'external_event_id', 'terminal_id', 'person_id', 'attendance_record_id',
        'result', 'confidence', 'direction', 'captured_at', 'review_status',
        'failure_reason', 'metadata', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'captured_at' => 'datetime',
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
