<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUnit extends Model
{
    protected $fillable = [
        'unit_number',
        'used_at',
        'discarded_at',
        'notes',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
