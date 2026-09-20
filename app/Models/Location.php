<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'description', 'timezone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }
}
