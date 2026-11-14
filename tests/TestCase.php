<?php

namespace ItmoSoft\WishlistSharing\Tests;

use ItmoSoft\WishlistSharing\WishlistSharingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WishlistSharingServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('itmosoft-wishlist-sharing.default_expiry_days', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Schema::create('wishlists', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->artisan('migrate')->run();
    }
}
