<?php

namespace ItmoSoft\WishlistSharing;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ItmoSoft\WishlistSharing\Models\WishlistShare;

class WishlistShareManager
{
    public function __construct(protected array $config)
    {
    }

    /**
     * Create a share link for any Eloquent wishlist model.
     *
     * The raw token is returned once. Only its SHA-256 hash is stored.
     *
     * @return array{share:WishlistShare,token:string}
     */
    public function create(
        Model $wishlist,
        ?CarbonInterface $expiresAt = null,
        ?int $maxViews = null,
        array $metadata = []
    ): array {
        if ($maxViews !== null && $maxViews < 1) {
            throw new InvalidArgumentException('Maximum views must be greater than zero.');
        }

        if ($expiresAt === null && ($days = (int) ($this->config['default_expiry_days'] ?? 30)) > 0) {
            $expiresAt = now()->addDays($days);
        }
        $maxViews ??= $this->config['default_max_views'] ?? null;

        $token = bin2hex(random_bytes((int) ($this->config['token_length'] ?? 64) / 2));

        $share = new WishlistShare();
        $share->shareable()->associate($wishlist);
        $share->token_hash = hash('sha256', $token);
        $share->expires_at = $expiresAt;
        $share->max_views = $maxViews;
        $share->view_count = 0;
        $share->metadata = $metadata;
        $share->save();

        return ['share' => $share->refresh(), 'token' => $token];
    }

    /**
     * Resolve an active token and atomically count the successful view.
     * Returns null for unknown, revoked, expired or exhausted links.
     */
    public function resolve(string $token): ?WishlistShare
    {
        if ($token === '') {
            return null;
        }

        return DB::transaction(function () use ($token) {
            $share = WishlistShare::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($share === null || ! $this->isActive($share)) {
                return null;
            }

            $share->increment('view_count');

            return $share->refresh();
        });
    }

    public function revoke(WishlistShare $share): WishlistShare
    {
        $share->forceFill(['revoked_at' => now()])->save();

        return $share->refresh();
    }

    public function isActive(WishlistShare $share): bool
    {
        if ($share->revoked_at !== null) {
            return false;
        }
        if ($share->expires_at !== null && $share->expires_at->isPast()) {
            return false;
        }
        if ($share->max_views !== null && $share->view_count >= $share->max_views) {
            return false;
        }

        return true;
    }
}
