<?php

namespace ItmoSoft\WishlistSharing;

use Illuminate\Support\ServiceProvider;

class WishlistSharingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/wishlist-sharing.php', 'itmosoft-wishlist-sharing');

        $this->app->singleton(WishlistShareManager::class, fn ($app) => new WishlistShareManager(
            $app['config']->get('itmosoft-wishlist-sharing', [])
        ));
        $this->app->alias(WishlistShareManager::class, 'itmosoft.wishlist-sharing');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wishlist-sharing.php' => config_path('itmosoft-wishlist-sharing.php'),
            ], 'itmosoft-wishlist-sharing-config');
        }
    }
}
