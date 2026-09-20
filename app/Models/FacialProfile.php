<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacialProfile extends Model
{
    use Auditable;

    protected $fillable = [
        'person_id', 'embedding', 'embedding_checksum', 'model', 'dimensions', 'version',
        'is_active', 'consented_at', 'retention_until', 'enrolled_by', 'enrollment_session_uuid', 'quality_metadata', 'sample_image_path',
    ];

    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return [
            'embedding' => 'encrypted:array',
            'is_active' => 'boolean',
            'consented_at' => 'datetime',
            'retention_until' => 'date',
            'quality_metadata' => 'array',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
