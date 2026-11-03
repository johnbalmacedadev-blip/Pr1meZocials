<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An image the team uploaded to guide Studio: a product photo or a one-off reference.
 */
class StudioReference extends Model
{
    public const KINDS = ['product', 'reference'];

    protected $fillable = ['brand_id', 'user_id', 'kind', 'name', 'path', 'mime_type', 'size'];

    public function key(): string
    {
        return 'upload:'.$this->id;
    }
}
