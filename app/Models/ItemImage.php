<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    public function getUrlAttribute()
    {
        return url(Storage::url($this->image_path));
    }
}
