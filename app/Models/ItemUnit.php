<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $item_id
 * @property int $unit_number 同一 item 中第幾件（1, 2, 3...）
 * @property string|null $used_at 開始使用時間
 * @property string|null $discarded_at 丟棄時間
 * @property string|null $notes 備註
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Item $item
 * @method static Builder<static>|ItemUnit newModelQuery()
 * @method static Builder<static>|ItemUnit newQuery()
 * @method static Builder<static>|ItemUnit query()
 * @method static Builder<static>|ItemUnit whereCreatedAt($value)
 * @method static Builder<static>|ItemUnit whereDiscardedAt($value)
 * @method static Builder<static>|ItemUnit whereId($value)
 * @method static Builder<static>|ItemUnit whereItemId($value)
 * @method static Builder<static>|ItemUnit whereNotes($value)
 * @method static Builder<static>|ItemUnit whereUnitNumber($value)
 * @method static Builder<static>|ItemUnit whereUpdatedAt($value)
 * @method static Builder<static>|ItemUnit whereUsedAt($value)
 * @mixin Eloquent
 */
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
