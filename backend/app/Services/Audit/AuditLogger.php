<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Single write path for the audit trail (audit_logs table). Called from
 * services after a security-sensitive or merchant-facing mutation —
 * never from controllers directly, so the "what changed" payload is
 * always captured at the point the domain logic actually knows it.
 */
class AuditLogger
{
    public function log(
        ?Shop $shop,
        string $action,
        string $actorType = 'user',
        ?int $userId = null,
        ?string $auditableType = null,
        ?int $auditableId = null,
        array $changes = [],
    ): AuditLog {
        $log = AuditLog::query()->create([
            'shop_id' => $shop?->id,
            'user_id' => $userId,
            'actor_type' => $actorType,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'changes' => $changes,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);

        Log::channel('audit')->info($action, [
            'shop' => $shop?->shopify_domain,
            'actor_type' => $actorType,
            'user_id' => $userId,
            'auditable' => $auditableType ? "{$auditableType}#{$auditableId}" : null,
            'ip' => Request::ip(),
        ]);

        return $log;
    }

    public function logModelChange(?Shop $shop, string $action, \Illuminate\Database\Eloquent\Model $model, array $before = []): AuditLog
    {
        return $this->log(
            shop: $shop,
            action: $action,
            auditableType: get_class($model),
            auditableId: $model->getKey(),
            changes: ['before' => $before, 'after' => $model->getChanges()],
        );
    }
}
