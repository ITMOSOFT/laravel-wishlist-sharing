<?php

namespace ItmoSoft\WishlistSharing\Tests;

use Carbon\Carbon;
use InvalidArgumentException;
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
        $this->assertSame(64, strlen($result['token']));
        $this->assertNotSame($result['token'], $result['share']->token_hash);
        $this->assertSame(hash('sha256', $result['token']), $result['share']->token_hash);
        $this->assertSame(['source' => 'email'], $result['share']->metadata);
    }

    public function test_create_applies_validated_configuration_defaults(): void
    {
        $wishlist = Wishlist::create(['name' => 'Defaults']);
        $manager = new WishlistShareManager([
            'token_length' => 80,
            'default_expiry_days' => 7,
            'default_max_views' => 5,
        ]);

        $result = $manager->create($wishlist);

        $this->assertSame(80, strlen($result['token']));
        $this->assertSame(5, $result['share']->max_views);
        $this->assertTrue($result['share']->expires_at->isFuture());
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

    public function test_revoke_is_idempotent_and_preserves_original_timestamp(): void
    {
        $wishlist = Wishlist::create(['name' => 'Revocation']);
        $manager = app(WishlistShareManager::class);
        $created = $manager->create($wishlist);
        $first = $manager->revoke($created['share']);
        $firstRevokedAt = $first->revoked_at;

        Carbon::setTestNow(Carbon::now()->addMinute());

        try {
            $second = $manager->revoke($first);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertEquals($firstRevokedAt, $second->revoked_at);
    }

    public function test_invalid_token_length_configuration_is_rejected(): void
    {
        $wishlist = Wishlist::create(['name' => 'Invalid token config']);

        foreach ([0, 1, 31, 33, 257, -2, '64.5', 'invalid'] as $length) {
            try {
                (new WishlistShareManager(['token_length' => $length]))->create($wishlist);
                $this->fail('Invalid token_length configuration was accepted: '.var_export($length, true));
            } catch (InvalidArgumentException $exception) {
                $this->assertInstanceOf(InvalidArgumentException::class, $exception);
            }
        }
    }

    public function test_invalid_default_max_views_configuration_is_rejected(): void
    {
        $wishlist = Wishlist::create(['name' => 'Invalid max views']);

        foreach ([0, -1, 'invalid'] as $maxViews) {
            try {
                (new WishlistShareManager(['default_max_views' => $maxViews]))->create($wishlist);
                $this->fail('Invalid default_max_views configuration was accepted: '.var_export($maxViews, true));
            } catch (InvalidArgumentException $exception) {
                $this->assertInstanceOf(InvalidArgumentException::class, $exception);
            }
        }
    }

    public function test_negative_or_non_integer_default_expiry_is_rejected(): void
    {
        $wishlist = Wishlist::create(['name' => 'Invalid expiry']);

        foreach ([-1, 'invalid', '7.5'] as $days) {
            try {
                (new WishlistShareManager(['default_expiry_days' => $days]))->create($wishlist);
                $this->fail('Invalid default_expiry_days configuration was accepted: '.var_export($days, true));
            } catch (InvalidArgumentException $exception) {
                $this->assertInstanceOf(InvalidArgumentException::class, $exception);
            }
        }
    }

    public function test_explicit_zero_max_views_is_rejected(): void
    {
        $wishlist = Wishlist::create(['name' => 'Zero max views']);

        $this->expectException(InvalidArgumentException::class);
        app(WishlistShareManager::class)->create($wishlist, null, 0);
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
