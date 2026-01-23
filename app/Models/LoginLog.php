<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 登入日誌模型
 * 記錄使用者的登入歷史
 *
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginLog query()
 * @mixin \Eloquent
 */
class LoginLog extends Model
{
    use HasFactory;

    /**
     * 可批量賦值的屬性
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'status',
        'logged_in_at',
    ];

    /**
     * 屬性轉換
     *
     * @var array<string, string>
     */
    protected $casts = [
        'logged_in_at' => 'datetime',
    ];

    /**
     * 取得關聯的使用者
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
