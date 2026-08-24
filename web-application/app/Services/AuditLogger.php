<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(string $action, Model|string $entity, ?int $entityId = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entity instanceof Model ? $entity::class : $entity,
            'entity_id' => $entity instanceof Model ? $entity->getKey() : $entityId,
            'metadata' => $metadata ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'timestamp' => now(),
        ]);
    }
}
