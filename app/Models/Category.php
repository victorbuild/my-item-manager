<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $uuid
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $products_count
 * @property int|null $items_count
 * @property-read \App\Models\User|null $user
 *
 * @method static Builder<static>|Category newModelQuery()
 * @method static Builder<static>|Category newQuery()
 * @method static Builder<static>|Category query()
 * @method static Builder<static>|Category whereCreatedAt($value)
 * @method static Builder<static>|Category whereId($value)
 * @method static Builder<static>|Category whereName($value)
 * @method static Builder<static>|Category whereUpdatedAt($value)
 * @method static Builder<static>|Category whereUuid($value)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 *
 * @method static Builder<static>|Category whereUserId($value)
 *
 * @mixin Eloquent
 */
class Category extends Model
{
    protected $fillable = ['name', 'uuid', 'user_id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->uuid)) {
                $category->uuid = (string) Str::uuid();
            }
            // 如果沒有指定 user_id，使用當前登入用戶
            /** @var \Illuminate\Contracts\Auth\Guard $auth */
            $auth = auth();
            if (empty($category->user_id) && $auth->check()) {
                $category->user_id = (int) $auth->id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * 取得分類的所有產品
     *
     * @return HasMany<\App\Models\Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(\App\Models\Product::class);
    }
}
