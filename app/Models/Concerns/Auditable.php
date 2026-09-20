<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', null, $model->getAttributes()));
        static::updated(fn (Model $model) => $model->writeAudit('updated', $model->getRawOriginal(), $model->getAttributes()));
        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->getRawOriginal(), null));
    }

    private function writeAudit(string $action, ?array $before, ?array $after): void
    {
        $excluded = array_merge(
            ['password', 'remember_token', 'api_token_hash', 'embedding'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        $sanitize = static fn (?array $values): ?array => $values === null
            ? null
            : array_diff_key($values, array_flip($excluded));

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'before' => $sanitize($before),
            'after' => $sanitize($after),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
        ]);
    }
}
