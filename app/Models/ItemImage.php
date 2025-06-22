<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 *
 *
 * @property int $id
 * @property int $item_id
 * @property string $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $original_extension
 * @property string $uuid UUID
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
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'image_path',
        'original_extension',
        'status',
        'usage_count'
    ];

    protected $appends = ['url'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function items()
    {
        return $this->belongsToMany(
            Item::class,
            'item_image_item', // pivot table
            'item_image_uuid', // pivot 關聯到本 model 的欄位
            'item_id',         // pivot 關聯到對方 model 的欄位
            'uuid',            // 本 model 主鍵
            'id'               // 對方 model 主鍵
        );
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('gcs')->url($this->image_path);
    }

    public function getThumbUrlAttribute(): ?string
    {
        $thumbPath = "item-images/{$this->uuid}/thumb_{$this->image_path}.webp";
        return Storage::disk('gcs')->temporaryUrl($thumbPath, now()->addMinutes(60));
    }

    public function getPreviewUrlAttribute(): ?string
    {
        $previewPath = "item-images/{$this->uuid}/preview_{$this->image_path}.webp";
        return Storage::disk('gcs')->temporaryUrl($previewPath, now()->addMinutes(60));
    }
}
