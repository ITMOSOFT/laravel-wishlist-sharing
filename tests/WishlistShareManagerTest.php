<?php

namespace ItmoSoft\WishlistSharing\Tests;

use Carbon\Carbon;
use ItmoSoft\WishlistSharing\Models\WishlistShare;
use ItmoSoft\WishlistSharing\Tests\Models\Wishlist;
use ItmoSoft\WishlistSharing\WishlistShareManager;

class WishlistShareManagerTest extends TestCase
{
    public function test_create_stores_hash_and_returns_raw_token_once(): void
    {
        $wishlist = Wishlist::create(['name' => 'Summer list']);
        $result = app(WishlistShareManager::class)->create($wishlist, null, 3, ['source' => 'email']);

        $this->assertNotEmpty($result['token']);
        $this->assertNotSame($result['token'], $result['share']->token_hash);
        $this->assertSame(hash('sha256', $result['token']), $result['share']->token_hash);
        $this->assertSame(['source' => 'email'], $result['share']->metadata);
    }

    public function test_resolve_counts_views_and_respects_view_limit(): void
    {
        $wishlist = Wishlist::create(['name' => 'Shared']);
        $manager = app(WishlistShareManager::class);
        $result = $manager->create($wishlist, null, 1);

        $resolved = $manager->resolve($result['token']);

        $this->assertInstanceOf(WishlistShare::class, $resolved);
        $this->assertSame(1, $resolved->view_count);
        $this->assertNull($manager->resolve($result['token']));
    }

    public function test_expired_and_revoked_tokens_cannot_be_resolved(): void
    {
        $wishlist = Wishlist::create(['name' => 'Private']);
        $manager = app(WishlistShareManager::class);

        $expired = $manager->create($wishlist, Carbon::now()->subMinute());
        $this->assertNull($manager->resolve($expired['token']));

        $revoked = $manager->create($wishlist);
        $manager->revoke($revoked['share']);
        $this->assertNull($manager->resolve($revoked['token']));
    }

    public function test_resolved_share_exposes_its_wishlist_relation(): void
    {
        $wishlist = Wishlist::create(['name' => 'Gift ideas']);
        $manager = app(WishlistShareManager::class);
        $created = $manager->create($wishlist);

        $resolved = $manager->resolve($created['token']);

        $this->assertSame($wishlist->getKey(), $resolved->shareable->getKey());
    }
}
