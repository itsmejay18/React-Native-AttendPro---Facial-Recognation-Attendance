<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use Auditable;

    public const LOCATION_TYPE_ANY = 'any';

    public const LOCATION_TYPE_MAIN_ENTRANCE = 'main_entrance';

    public const LOCATION_TYPE_CLASSROOM = 'classroom';

    public const LOCATION_TYPES = [
        'any' => 'Any Location',
        'main_entrance' => 'Main Entrance',
        'classroom' => 'Classroom',
        'computer_laboratory' => 'Computer Laboratory',
        'global_classroom' => 'Global Classroom',
        'other' => 'Other / Special Location',
    ];

    public const ROOM_TYPES = ['classroom', 'computer_laboratory', 'global_classroom', 'other'];

    /** Standard HCCD rooms offered when creating a classroom schedule. */
    public const ROOM_SUGGESTIONS = [
        'A2-201',
        'A2-202',
        'A2-203',
        'A2-204',
        'B1-101',
        'B1-102',
        'B1-103',
        'COMLAB E2-207',
        'COMLAB E2-208',
        'COMLAB E2-209',
    ];

    protected $fillable = [
        'code', 'name', 'person_type', 'department_id', 'program', 'location_id', 'location_type', 'room_code', 'days_of_week',
        'starts_at', 'ends_at', 'check_in_opens_at', 'check_in_closes_at', 'grace_minutes',
        'checkout_required', 'effective_from', 'effective_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'checkout_required' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isAnyLocation(): bool
    {
        return ($this->location_type ?? self::LOCATION_TYPE_ANY) === self::LOCATION_TYPE_ANY
            && $this->location_id === null
            && ($this->room_code === null || trim((string) $this->room_code) === '');
    }

    public function requiresRoom(): bool
    {
        return in_array($this->location_type, self::ROOM_TYPES, true);
    }

    /**
     * Human-readable room/location label, e.g. "Computer Laboratory - COMLAB-2".
     * Falls back to the linked location name so legacy schedules render unchanged.
     */
    public function getRoomDisplayAttribute(): string
    {
        $room = trim((string) ($this->room_code ?? ''));

        if ($room !== '' && $this->requiresRoom()) {
            $label = self::LOCATION_TYPES[$this->location_type] ?? $this->location_type;

            return $label.' - '.$room;
        }

        if ($room !== '') {
            return $room;
        }

        return $this->location?->name ?? 'Any location';
    }

    /**
     * Bind a free-text room to a registered Location row so terminals,
     * sessions, and attendance records keep using the existing location_id
     * plumbing. Rooms materialize on demand; nothing is pre-registered.
     */
    public function syncRoomLocation(string $locationType, ?string $roomCode = null): void
    {
        $room = $roomCode === null ? null : preg_replace('/\s+/', ' ', trim($roomCode));
        $room = ($room === '' || $room === null) ? null : substr($room, 0, 60);

        if ($locationType === self::LOCATION_TYPE_ANY) {
            $this->location_type = self::LOCATION_TYPE_ANY;
            $this->room_code = null;
            $this->location_id = null;

            return;
        }

        if ($locationType === self::LOCATION_TYPE_MAIN_ENTRANCE) {
            $this->location_type = self::LOCATION_TYPE_MAIN_ENTRANCE;
            $this->room_code = null;

            return;
        }

        $this->location_type = $locationType;
        $this->room_code = $room;

        if ($room !== null) {
            $code = strtoupper(preg_replace('/\s+/', '-', $room) ?? $room);
            $code = substr($code, 0, 30);
            $label = self::LOCATION_TYPES[$locationType] ?? $locationType;

            $location = Location::query()->where('code', $code)->first();

            if (! $location) {
                $location = Location::query()->create([
                    'code' => $code,
                    'name' => $label.' - '.$room,
                    'description' => 'Auto-registered classroom location for scheduled classes.',
                    'timezone' => config('app.timezone'),
                    'is_active' => true,
                ]);
            }

            $this->location_id = $location->id;
            $this->setRelation('location', $location);
        }
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'schedule_assignments')
            ->withPivot(['effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
