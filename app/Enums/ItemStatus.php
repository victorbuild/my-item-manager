<?php

namespace App\Enums;

/**
 * 物品狀態枚舉
 * 定義所有可能的物品狀態
 */
enum ItemStatus: string
{
    case PRE_ARRIVAL = 'pre_arrival';
    case UNUSED = 'unused';
    case IN_USE = 'in_use';
    case UNUSED_DISCARDED = 'unused_discarded';
    case USED_DISCARDED = 'used_discarded';

    /**
     * 取得狀態的中文名稱
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PRE_ARRIVAL => '尚未到貨',
            self::UNUSED => '未使用',
            self::IN_USE => '使用中',
            self::UNUSED_DISCARDED => '未使用就棄用',
            self::USED_DISCARDED => '使用後棄用',
        };
    }

    /**
     * 取得所有狀態值
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
