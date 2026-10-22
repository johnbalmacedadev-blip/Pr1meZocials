<?php

namespace App\Services;

use App\Models\ContentActivity;
use App\Models\ContentItem;
use App\Models\User;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(ContentItem $item, ?User $actor, string $action, string $description, array $properties = []): ContentActivity
    {
        return $item->activities()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'properties' => $properties === [] ? null : $this->trim($properties),
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function trim(array $properties): array
    {
        array_walk_recursive($properties, function (&$value) {
            if (is_string($value) && strlen($value) > 500) {
                $value = substr($value, 0, 500).'…';
            }
        });

        return $properties;
    }
}
