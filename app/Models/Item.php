<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Item extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = [
        'name',
        'description',
        'location',
        'quantity',
        'price',
        'purchased_at',
        'barcode',
        'uuid',
        'short_id',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            $item->uuid = (string) Str::uuid();

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
        return $this->hasMany(ItemUnit::class);
    }

    public function getRouteKeyName(): string
    {
        return 'short_id';
    }
}
