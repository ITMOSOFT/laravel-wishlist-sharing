# Release checklist

1. Run `composer validate --strict`.
2. Run `composer test` on PHP 8.3 with Laravel 12 and Laravel 13 dependency sets.
3. Push `main` and confirm both GitHub Actions matrix jobs pass.
4. Create and push the annotated tag:
   ```bash
   git tag -a v1.0.0 -m "Initial release"
   git push origin v1.0.0
   ```
5. Create GitHub Release `v1.0.0 — Initial Release`.
6. Submit `https://github.com/ITMOSOFT/laravel-wishlist-sharing` to Packagist as `itmosoft/laravel-wishlist-sharing`.
7. Configure the Packagist GitHub webhook and verify the version is listed.
