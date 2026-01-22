<?php

namespace App\Providers;

use App\Repositories\Contracts\ItemImageRepositoryInterface;
use App\Repositories\ItemImageRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
