<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 *
 *
 * @property int $id
 * @property string $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $original_extension
 * @property string|null $uuid UUID
 * @property string $status 圖片狀態：draft 僅上傳未被關聯、used 已關聯至 item
 * @property int $usage_count 被多少個 item 使用，作為刪除防呆依據
 * @property int|null $user_id
 * @property-read string|null $preview_url
 * @property-read string|null $thumb_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Item> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\User|null $user
 * @method static Builder<static>|ItemImage newModelQuery()
 * @method static Builder<static>|ItemImage newQuery()
 * @method static Builder<static>|ItemImage query()
 * @method static Builder<static>|ItemImage whereCreatedAt($value)
 * @method static Builder<static>|ItemImage whereId($value)
 * @method static Builder<static>|ItemImage whereImagePath($value)
 * @method static Builder<static>|ItemImage whereOriginalExtension($value)
 * @method static Builder<static>|ItemImage whereStatus($value)
 * @method static Builder<static>|ItemImage whereUpdatedAt($value)
 * @method static Builder<static>|ItemImage whereUsageCount($value)
 * @method static Builder<static>|ItemImage whereUserId($value)
 * @method static Builder<static>|ItemImage whereUuid($value)
 * @mixin Eloquent
 */
class ItemImage extends Model
{
    /**
     * @var string 草稿狀態
     */
    public const STATUS_DRAFT = 'draft';

    /**
     * @var string 已使用狀態
     */
    public const STATUS_USED = 'used';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'image_path',
        'original_extension',
        'status',
        'usage_count',
        'user_id',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany
     */
    public function items(): BelongsToMany
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
