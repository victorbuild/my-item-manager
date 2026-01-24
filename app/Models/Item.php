<?php

namespace App\Models;

use App\Enums\ItemStatus;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 *
 * @property int $id
 * @property string $uuid UUID
 * @property string $short_id 網址id
 * @property string $name 物品名稱
 * @property string|null $barcode 商品條碼
 * @property string|null $description 物品描述
 * @property string|null $location 存放位置
 * @property string|null $price 總金額
 * @property Carbon|null $purchased_at 購買日期
 * @property bool $is_discarded 是否報廢
 * @property Carbon|null $discarded_at 報廢時間
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $product_id 對應的產品
 * @property Carbon|null $received_at
 * @property Carbon|null $used_at
 * @property string|null $notes
 * @property string|null $serial_number 實體序號
 * @property int|null $user_id
 * @property string|null $discard_note 棄用反思或情緒想法
 * @property Carbon|null $expiration_date 物品有效期限
 * @property-read \App\Models\Category|null $category
 * @property-read string $status
 * @property-read Collection<int, \App\Models\ItemImage> $images
 * @property-read int|null $images_count
 * @property-read \App\Models\Product|null $product
 * @method static \Database\Factories\ItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|Item inUse()
 * @method static Builder<static>|Item newModelQuery()
 * @method static Builder<static>|Item newQuery()
 * @method static Builder<static>|Item preArrival()
 * @method static Builder<static>|Item query()
 * @method static Builder<static>|Item status(\App\Enums\ItemStatus|array|string $statuses)
 * @method static Builder<static>|Item unused()
 * @method static Builder<static>|Item unusedDiscarded()
 * @method static Builder<static>|Item usedDiscarded()
 * @method static Builder<static>|Item whereBarcode($value)
 * @method static Builder<static>|Item whereCreatedAt($value)
 * @method static Builder<static>|Item whereDescription($value)
 * @method static Builder<static>|Item whereDiscardNote($value)
 * @method static Builder<static>|Item whereDiscardedAt($value)
 * @method static Builder<static>|Item whereExpirationDate($value)
 * @method static Builder<static>|Item whereId($value)
 * @method static Builder<static>|Item whereIsDiscarded($value)
 * @method static Builder<static>|Item whereLocation($value)
 * @method static Builder<static>|Item whereName($value)
 * @method static Builder<static>|Item whereNotes($value)
 * @method static Builder<static>|Item wherePrice($value)
 * @method static Builder<static>|Item whereProductId($value)
 * @method static Builder<static>|Item wherePurchasedAt($value)
 * @method static Builder<static>|Item whereReceivedAt($value)
 * @method static Builder<static>|Item whereSerialNumber($value)
 * @method static Builder<static>|Item whereShortId($value)
 * @method static Builder<static>|Item whereUpdatedAt($value)
 * @method static Builder<static>|Item whereUsedAt($value)
 * @method static Builder<static>|Item whereUserId($value)
 * @method static Builder<static>|Item whereUuid($value)
 * @mixin Eloquent
 */
class Item extends Model
{
    use HasFactory;

    /**
     * @var list<string>
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
        'purchased_at'    => 'date',
        'received_at'     => 'date',
        'used_at'         => 'date',
        'discarded_at'    => 'date',
        'expiration_date' => 'date',
    ];

    protected $appends = [
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

    /**
     * 取得物品的圖片關聯
     *
     * @return BelongsToMany
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemImage::class,
            'item_image_item', // pivot table
            'item_id',         // 本 model 關聯欄位
            'item_image_uuid', // 關聯 model 關聯欄位
            'id',              // 本 model 主鍵
            'uuid'             // 關聯 model 主鍵
        )->withPivot(['sort_order'])->withTimestamps()->orderBy('sort_order');
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
     * 根據日期欄位計算物品狀態
     * 統一的狀態計算邏輯，所有狀態判斷都應該使用此方法
     *
     * @param \Illuminate\Support\Carbon|string|null $discardedAt
     * @param \Illuminate\Support\Carbon|string|null $usedAt
     * @param \Illuminate\Support\Carbon|string|null $receivedAt
     * @return string 狀態值（ItemStatus enum value）
     */
    public static function getStatusFromDates(
        $discardedAt = null,
        $usedAt = null,
        $receivedAt = null
    ): string {
        // 第一優先：檢查是否已棄用
        if ($discardedAt) {
            return $usedAt ? ItemStatus::USED_DISCARDED->value : ItemStatus::UNUSED_DISCARDED->value;
        }

        // 第二優先：檢查是否正在使用
        if ($usedAt) {
            return ItemStatus::IN_USE->value;
        }

        // 第三優先：檢查是否已到貨
        if ($receivedAt) {
            return ItemStatus::UNUSED->value;
        }

        // 第四優先：其他情況（尚未到貨）
        return ItemStatus::PRE_ARRIVAL->value;
    }

    /**
     * 動態屬性：取得物品狀態
     * 基於文件定義的5個主要狀態
     *
     * @return string
     */
    public function getStatusAttribute(): string
    {
        return self::getStatusFromDates(
            $this->discarded_at,
            $this->used_at,
            $this->received_at
        );
    }

    /**
     * Scope：未到貨（未到貨、未使用、未棄用）
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePreArrival(Builder $query): Builder
    {
        return $query
            ->whereNull('discarded_at')
            ->whereNull('used_at')
            ->whereNull('received_at');
    }

    /**
     * Scope：未使用（已到貨、未使用、未棄用）
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnused(Builder $query): Builder
    {
        return $query
            ->whereNotNull('received_at')
            ->whereNull('used_at')
            ->whereNull('discarded_at');
    }

    /**
     * Scope：使用中（已使用、未棄用）
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeInUse(Builder $query): Builder
    {
        return $query
            ->whereNotNull('used_at')
            ->whereNull('discarded_at');
    }

    /**
     * Scope：未使用就棄用（已棄用、未使用）
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnusedDiscarded(Builder $query): Builder
    {
        return $query
            ->whereNotNull('discarded_at')
            ->whereNull('used_at');
    }

    /**
     * Scope：使用後棄用（已棄用、已使用）
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUsedDiscarded(Builder $query): Builder
    {
        return $query
            ->whereNotNull('discarded_at')
            ->whereNotNull('used_at');
    }

    /**
     * Scope：篩選特定狀態的物品
     *
     * @param Builder $query
     * @param string|ItemStatus|array<string|ItemStatus> $statuses 狀態值或狀態陣列
     * @return Builder
     */
    public function scopeStatus(Builder $query, string|ItemStatus|array $statuses): Builder
    {
        // 將單一狀態轉為陣列
        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        // 將 ItemStatus enum 轉為字串值
        $statuses = array_map(function ($status) {
            return $status instanceof ItemStatus ? $status->value : $status;
        }, $statuses);

        return $query->where(function ($q) use ($statuses) {
            foreach ($statuses as $status) {
                $q->orWhere(function ($sub) use ($status) {
                    match ($status) {
                        ItemStatus::PRE_ARRIVAL->value => $this->scopePreArrival($sub),
                        ItemStatus::UNUSED->value => $this->scopeUnused($sub),
                        ItemStatus::IN_USE->value => $this->scopeInUse($sub),
                        ItemStatus::UNUSED_DISCARDED->value => $this->scopeUnusedDiscarded($sub),
                        ItemStatus::USED_DISCARDED->value => $this->scopeUsedDiscarded($sub),
                        default => null,
                    };
                });
            }
        });
    }
}
