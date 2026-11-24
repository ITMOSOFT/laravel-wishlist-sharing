<?php

namespace ItmoSoft\WishlistSharing;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

        $tokenLength = $this->validatedConfigInteger('token_length', 64, 32, 256);

        if ($tokenLength % 2 !== 0) {
            throw new InvalidArgumentException(
                'Configuration [token_length] must be an even number of hexadecimal characters.'
            );
        }

        $defaultExpiryDays = $this->validatedConfigInteger('default_expiry_days', 30, 0);
        $defaultMaxViews = $this->config['default_max_views'] ?? null;

        if ($defaultMaxViews !== null) {
            $defaultMaxViews = $this->validatedConfigInteger('default_max_views', 1, 1);
        }

        if ($expiresAt === null && $defaultExpiryDays > 0) {
            $expiresAt = now()->addDays($defaultExpiryDays);
        }

        $maxViews ??= $defaultMaxViews;

        // token_length is the number of hexadecimal characters, so the number
        // of random bytes must be exactly half that value.
        $token = bin2hex(random_bytes(intdiv($tokenLength, 2)));

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

    /**
     * Revoke a share once. Repeated calls preserve the original revocation time.
     */
    public function revoke(WishlistShare $share): WishlistShare
    {
        return DB::transaction(function () use ($share) {
            $lockedShare = WishlistShare::query()
                ->whereKey($share->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedShare->revoked_at === null) {
                $lockedShare->forceFill(['revoked_at' => now()])->save();
            }

            return $lockedShare->refresh();
        });
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

    /**
     * Read and validate an integer configuration value.
     */
    protected function validatedConfigInteger(
        string $key,
        int $default,
        int $minimum,
        ?int $maximum = null
    ): int {
        $value = $this->config[$key] ?? $default;
        $validated = filter_var($value, FILTER_VALIDATE_INT);

        if ($validated === false || $validated < $minimum || ($maximum !== null && $validated > $maximum)) {
            $range = $maximum === null ? "at least {$minimum}" : "between {$minimum} and {$maximum}";
            throw new InvalidArgumentException("Configuration [{$key}] must be an integer {$range}.");
        }

        return $validated;
    }
}
