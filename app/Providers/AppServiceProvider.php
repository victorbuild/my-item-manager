<?php

namespace App\Providers;

use App\Models\Item;
use App\Policies\ItemPolicy;
use App\Repositories\Contracts\ItemImageRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\ItemImageRepository;
use App\Repositories\ItemRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use App\Services\ItemService;
use App\Strategies\Sort\SortStrategyFactory;
use Illuminate\Support\Facades\Gate;
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

        $this->app->bind(
            ItemRepositoryInterface::class,
            ItemRepository::class
        );

        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );

        // 綁定 ItemService 時注入 config 值，避免 Service 直接依賴 config
        $this->app->when(ItemService::class)
            ->needs('$maxItemQuantity')
            ->give(fn () => config('app.max_item_quantity', 100));

        // 註冊排序策略工廠
        $this->app->singleton(SortStrategyFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Item::class, ItemPolicy::class);

        // Laravel 12 使用 Event Discovery，不需要手動註冊
        // 只要 Listener 的 handle() 方法 typehint Event，Laravel 會自動發現
    }
}
