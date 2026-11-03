<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One cell of the weekly Facebook plan, for example Tuesday flex: "Behind the Scenes".
 */
class PostingPlanSlot extends Model
{
    public const FORMATS = ['poster', 'reel', 'carousel', 'image', 'reel_poster', 'ugc'];

    protected $fillable = ['weekday', 'slot', 'format', 'title', 'updated_by'];
}
