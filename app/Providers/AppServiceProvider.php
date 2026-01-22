<?php

namespace App\Providers;

use App\Repositories\Contracts\ItemImageRepositoryInterface;
use App\Repositories\ItemImageRepository;
use App\Services\ItemService;
use App\Strategies\Sort\SortStrategyFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 綁定 Repository 介面到實作
        $this->app->bind(
            ItemImageRepositoryInterface::class,
            ItemImageRepository::class
        );

        // 綁定 ItemService 時注入 config 值，避免 Service 直接依賴 config
        $this->app->when(ItemService::class)
            ->needs('$maxItemQuantity')
            ->give(fn() => config('app.max_item_quantity', 100));

        // 註冊排序策略工廠
        $this->app->singleton(SortStrategyFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
