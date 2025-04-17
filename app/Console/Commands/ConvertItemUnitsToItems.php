<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ConvertItemUnitsToItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convert:item-units-to-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '將 item_unit 轉換為 item，unit_number = 1 寫回原 item，其餘建立新 item';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('開始轉換 item_unit ➝ item');

        $items = Item::with('units')->get();
        $createdCount = 0;

        /** @var Item $item */
        foreach ($items as $item) {
            /** @var ItemUnit $unit */
            foreach ($item->units as $unit) {
                if ($unit->unit_number === 1) {
                    // 寫入原本 item
                    $item->used_at = $unit->used_at;
                    $item->discarded_at = $unit->discarded_at;
                    $item->is_discarded = !is_null($unit->discarded_at);
                    $item->notes = $unit->notes;
                    $item->serial_number = $unit->serial_number ?? 'S-' . Str::random(6);
                    $item->save();

                    $this->line("更新原 item [ID: $item->id] ← unit_id: $unit->id");
                    continue;
                }

                // 其他 unit ➝ 建立新 item
                $newItem = new Item();
                $newItem->uuid = Str::uuid();
                $newItem->short_id = Str::random(8);
                $newItem->serial_number = $unit->serial_number ?? 'S-' . Str::random(6);
                $newItem->name = $item->name;
                $newItem->description = $item->description;
                $newItem->location = $item->location;
                $newItem->price = $item->price;
                $newItem->barcode = $item->barcode;
                $newItem->purchased_at = $item->purchased_at;
                $newItem->received_at = $item->received_at;
                $newItem->used_at = $unit->used_at;
                $newItem->is_discarded = !is_null($unit->discarded_at);
                $newItem->discarded_at = $unit->discarded_at;
                $newItem->notes = $unit->notes;
                $newItem->user_id = $item->user_id;
                $newItem->category_id = $item->category_id;
                $newItem->product_id = $item->product_id;
                $newItem->save();

                $this->line("新增 item [ID: {$newItem->id}] ← unit_id: {$unit->id}");
                $createdCount++;
            }
        }

        $this->info("全部轉換完成，共建立 $createdCount 筆新 item");
    }
}
