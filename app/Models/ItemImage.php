<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 *
 *
 * @property int $id
 * @property int $item_id
 * @property string $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $original_extension
 * @property-read mixed $url
 * @property-read Item $item
 * @method static Builder<static>|ItemImage newModelQuery()
 * @method static Builder<static>|ItemImage newQuery()
 * @method static Builder<static>|ItemImage query()
 * @method static Builder<static>|ItemImage whereCreatedAt($value)
 * @method static Builder<static>|ItemImage whereId($value)
 * @method static Builder<static>|ItemImage whereImagePath($value)
 * @method static Builder<static>|ItemImage whereItemId($value)
 * @method static Builder<static>|ItemImage whereOriginalExtension($value)
 * @method static Builder<static>|ItemImage whereUpdatedAt($value)
 * @mixin Eloquent
 */
class ItemImage extends Model
{
    protected $fillable = [
        'image_path',
        'original_extension'
    ];

    protected $appends = ['url'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getUrlAttribute(): string
    {
        return url(Storage::url($this->image_path));
    }
}
