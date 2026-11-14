# ITMOSOFT Laravel Wishlist Sharing

Secure, expiring share links and access tracking for Laravel wishlist models. Compatible with Laravel 12 and 13.

## Features

- Create share links for any Eloquent wishlist model through a polymorphic relation.
- Generate cryptographically random tokens.
- Store only SHA-256 token hashes; return the raw token once at creation.
- Optional expiry date and maximum view count.
- Track successful link views.
- Revoke links.
- Resolve links only while active.
- Store optional share metadata.
- Configurable table prefix.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require itmosoft/laravel-wishlist-sharing
php artisan vendor:publish --tag=itmosoft-wishlist-sharing-config
php artisan migrate
```

By default, the migration creates `itmosoft_wishlist_shares`. Set `table_prefix` before migrating if you need a different table name.

## Create a share

```php
use ItmoSoft\WishlistSharing\WishlistShareManager;

$manager = app(WishlistShareManager::class);

$result = $manager->create(
    wishlist: $wishlist,
    expiresAt: now()->addDays(7),
    maxViews: 100,
    metadata: ['channel' => 'email'],
);

$share = $result['share'];
$token = $result['token']; // Store/build the URL now; raw token is not persisted.
$shareUrl = route('shared-wishlist.show', ['token' => $token]);
```

The package deliberately accepts an Eloquent model rather than requiring a hard dependency on a particular wishlist package. Pass the wishlist model supplied by your application or `itmosoft/laravel-wishlist`.

## Resolve a shared wishlist

```php
$share = $manager->resolve($token);

abort_unless($share, 404);

$wishlist = $share->shareable;
```

`resolve()` increments the view counter only for active links. Implement the HTTP route/controller in your application and decide what wishlist fields/items are visible to anonymous visitors.

## Revoke a share

```php
$manager->revoke($share);
```

## Configuration

Published configuration: `config/itmosoft-wishlist-sharing.php`.

- `table_prefix`: defaults to `itmosoft_`.
- `default_expiry_days`: defaults to 30; set to `0` for no default expiry.
- `default_max_views`: defaults to `null` (unlimited).
- `token_length`: defaults to 64 hexadecimal characters.

## Important scope and production notes

- This package stores share permissions and resolves tokens; it does not provide HTTP routes, controllers, frontend pages, QR codes, email delivery, or a share UI.
- The raw token is returned once and is not stored. Save it only as part of a generated URL or deliver it to the intended recipient; it cannot be reconstructed from the database hash.
- Protect shared-wishlist endpoints with HTTPS, rate limiting, and careful output filtering. Do not expose private customer data, prices, notes or internal fields by default.
- `resolve()` uses a database transaction and `lockForUpdate()`. Lock behavior varies by database driver; SQLite does not provide production-equivalent row locking.
- If you need per-user access control, tenant scoping, password protection, or audit logs, implement those policies in the host application.
- The polymorphic relation can target any Eloquent model; ensure only intended wishlist model types can be passed to `create()`.
- View counts represent successful token resolutions, not unique visitors or page impressions.

## Testing

```bash
composer install
composer validate --strict
composer test
```

## License

MIT. See [LICENSE](LICENSE).
