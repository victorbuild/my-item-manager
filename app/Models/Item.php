<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/**
 *
 *
 * @property int $id
 * @property string $uuid UUID
 * @property string $short_id 網址id
 * @property string $name 物品名稱
 * @property string|null $barcode 商品條碼
 * @property string|null $description 物品描述
 * @property string|null $location 存放位置
 * @property string|null $price 總金額
 * @property string|null $purchased_at 購買日期
 * @property bool $is_discarded 是否報廢
 * @property string|null $discarded_at 報廢時間
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $category_id
 * @property-read Category|null $category
 * @property-read Collection<int, ItemImage> $images
 * @property-read int|null $images_count
 * @property-read Collection<int, ItemUnit> $units
 * @property-read int|null $units_count
 * @method static Builder<static>|Item newModelQuery()
 * @method static Builder<static>|Item newQuery()
 * @method static Builder<static>|Item query()
 * @method static Builder<static>|Item whereBarcode($value)
 * @method static Builder<static>|Item whereCategoryId($value)
 * @method static Builder<static>|Item whereCreatedAt($value)
 * @method static Builder<static>|Item whereDescription($value)
 * @method static Builder<static>|Item whereDiscardedAt($value)
 * @method static Builder<static>|Item whereId($value)
 * @method static Builder<static>|Item whereIsDiscarded($value)
 * @method static Builder<static>|Item whereLocation($value)
 * @method static Builder<static>|Item whereName($value)
 * @method static Builder<static>|Item wherePrice($value)
 * @method static Builder<static>|Item wherePurchasedAt($value)
 * @method static Builder<static>|Item whereQuantity($value)
 * @method static Builder<static>|Item whereShortId($value)
 * @method static Builder<static>|Item whereUpdatedAt($value)
 * @method static Builder<static>|Item whereUuid($value)
 * @property int|null $product_id 對應的產品
 * @method static Builder<static>|Item whereProductId($value)
 * @property string|null $received_at
 * @property string|null $used_at
 * @property string|null $notes
 * @property string|null $serial_number 實體序號
 * @method static Builder<static>|Item whereNotes($value)
 * @method static Builder<static>|Item whereReceivedAt($value)
 * @method static Builder<static>|Item whereSerialNumber($value)
 * @method static Builder<static>|Item whereUsedAt($value)
 * @property int|null $user_id
 * @method static Builder<static>|Item whereUserId($value)
 * @mixin Eloquent
 */
class Item extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'name',
        'description',
        'location',
        'price',
        'purchased_at',
        'received_at',
        'used_at',
        'discarded_at',
        'discard_note',
        'notes',
        'serial_number',
        'category_id',
        'barcode',
        'uuid',
        'short_id',
        'product_id',
        'expiration_date',
    ];

    protected $casts = [
        'expiration_date' => 'date',
    ];

    protected $appends = [
        'first_thumb_url',
        'first_preview_url',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function ($item) {
            $item->uuid = (string)Str::uuid();

            // 自訂字元集：a-zA-Z0-9_-（共64個字元）
            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
            $length = 11;

            do {
                $shortId = '';
                for ($i = 0; $i < $length; $i++) {
                    $shortId .= $characters[random_int(0, strlen($characters) - 1)];
                }
            } while (self::where('short_id', $shortId)->exists());

            $item->short_id = $shortId;
        });
    }

    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ItemUnit::class)->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'short_id';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 取得第一張縮圖網址
     *
     * @return string|null
     */
    public function getFirstThumbUrlAttribute(): ?string
    {
        $image = $this->images->first();
        if (!$image) {
            return null;
        }

        $uuid = $this->uuid;
        $filename = pathinfo($image->image_path, PATHINFO_FILENAME);

        return Storage::disk('gcs')->temporaryUrl(
            "item-images/$uuid/thumb/$filename.webp",
            now()->addMinutes(5)
        );
    }

    /**
     * 取得第一張預覽圖網址
     *
     * @return string|null
     */
    public function getFirstPreviewUrlAttribute(): ?string
    {
        $image = $this->images->first();
        if (!$image) {
            return null;
        }

        $uuid = $this->uuid;
        $filename = pathinfo($image->image_path, PATHINFO_FILENAME);

        return Storage::disk('gcs')->temporaryUrl(
            "item-images/$uuid/preview/$filename.webp",
            now()->addMinutes(5)
        );
    }

    /**
     * 動態屬性：取得物品狀態
     *
     * @return string
     */
    public function getStatusAttribute(): string
    {
        if ($this->discarded_at) {
            return $this->used_at ? 'used_and_gone' : 'unused_but_gone';
        } elseif ($this->used_at) {
            return 'in_use';
        } elseif ($this->received_at) {
            return 'stored';
        } else {
            return 'pre_arrival';
        }
    }
}
