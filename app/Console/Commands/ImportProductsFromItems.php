<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportProductsFromItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:products-from-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根據 items 表資料建立對應的 products 並更新 item 的 product_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('開始匯入 products...');
        $count = 0;

        Item::chunk(100, function ($items) use (&$count) {
            foreach ($items as $item) {
                $product = Product::create([
                    'uuid' => (string)Str::uuid(),
                    'short_id' => substr(Str::random(11), 0, 11),
                    'user_id' => $item->user_id ?? 1,
                    'category_id' => $item->category_id ?? null,
                    'name' => $item->name,
                    'brand' => null,
                    'model' => null,
                    'spec' => null,
                    'barcode' => $item->barcode,
                ]);

                $item->product_id = $product->id;
                $item->save();

                $count++;
            }
        });

        $this->info("匯入完成，共建立 $count 筆 product。");
    }
}
