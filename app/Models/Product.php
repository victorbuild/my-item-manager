<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id 自動遞增主鍵，僅限內部使用
 * @property string $uuid UUID
 * @property string $short_id short ID
 * @property int $user_id 建立產品的使用者 ID
 * @property string $name 產品名稱
 * @property string|null $brand 品牌名稱
 * @property string|null $model 產品型號
 * @property string|null $spec 產品規格
 * @property string|null $barcode 產品條碼，可用於掃描比對
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @method static Builder<static>|Product newModelQuery()
 * @method static Builder<static>|Product newQuery()
 * @method static Builder<static>|Product query()
 * @method static Builder<static>|Product whereBarcode($value)
 * @method static Builder<static>|Product whereBrand($value)
 * @method static Builder<static>|Product whereCreatedAt($value)
 * @method static Builder<static>|Product whereId($value)
 * @method static Builder<static>|Product whereModel($value)
 * @method static Builder<static>|Product whereName($value)
 * @method static Builder<static>|Product whereShortId($value)
 * @method static Builder<static>|Product whereSpec($value)
 * @method static Builder<static>|Product whereUpdatedAt($value)
 * @method static Builder<static>|Product whereUserId($value)
 * @method static Builder<static>|Product whereUuid($value)
 * @property int|null $category_id 所屬分類
 * @property-read Category|null $category
 * @property-read Collection<int, Item> $items
 * @property-read int|null $items_count
 * @method static Builder<static>|Product whereCategoryId($value)
 * @mixin Eloquent
 */
class Product extends Model
{
    protected $fillable = [
        'uuid',
        'short_id',
        'user_id',
        'category_id',
        'name',
        'brand',
        'model',
        'spec',
        'barcode',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function latestOwnedItem(): HasOne
    {
        return $this->hasOne(Item::class)
            ->whereNull('discarded_at')
            ->orderByRaw("
                CASE
                    WHEN used_at IS NOT NULL THEN 0
                    ELSE 1
                END,
                CASE
                    WHEN expiration_date IS NOT NULL THEN 0
                    ELSE 1
                END,
                expiration_date ASC,
                created_at ASC
            ");
    }
}
