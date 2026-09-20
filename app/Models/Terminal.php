<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends Model
{
    use Auditable;

    protected $fillable = [
        'uuid', 'code', 'name', 'location_id', 'status', 'is_active', 'api_token_id',
        'api_token_hash', 'last_ip', 'last_seen_at', 'app_version', 'capabilities',
    ];

    protected $hidden = ['api_token_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'capabilities' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function recognitionEvents(): HasMany
    {
        return $this->hasMany(RecognitionEvent::class);
    }
}
