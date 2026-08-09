<?php

declare(strict_types=1);

namespace App\Infrastructure\Sync;

use Illuminate\Database\Eloquent\Model;

/**
 * A record that something was deleted, kept so offline clients can drop it.
 *
 * Soft-deleted models do not need an entry — a client can see `deleted_at` move
 * on the row itself. This exists for the entities that are removed outright.
 */
class SyncTombstone extends Model
{
    protected $fillable = [
        'organization_id',
        'entity',
        'entity_id',
        'deleted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public static function record(string $entity, int $entityId, int $organizationId): void
    {
        static::query()->updateOrCreate(
            ['entity' => $entity, 'entity_id' => $entityId],
            ['organization_id' => $organizationId, 'deleted_at' => now()],
        );
    }
}
