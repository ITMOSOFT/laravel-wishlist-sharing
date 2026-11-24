# Changelog

## [1.0.0] - 2026-11-14

- Initial release.
- Added polymorphic wishlist share records.
- Added cryptographically random share tokens with SHA-256 hash storage.
- Added expiry dates, maximum view limits, view tracking and revocation.
- Added Laravel 12/13 compatibility workflow and PHPUnit tests.

## [1.0.1] - 2026-11-24

### Fixed
- Validate token_length configuration strictly.
- Reject token lengths outside the supported range of 32–256 characters.
- Reject odd token lengths.
- Validate default expiry days and maximum views configuration.
- Preserve the original revocation timestamp when revoking an already-revoked share.

### Tests
- Add coverage for invalid configuration values.
- Add coverage for repeated revocation.
- Expand wishlist-sharing manager validation tests.
