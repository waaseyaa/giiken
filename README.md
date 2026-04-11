# abaapi
Sovereign knowledge management platform for Indigenous communities — built on Waaseyaa

## Local Waaseyaa monorepo (path installs)

Use this when you want Giiken to load `waaseyaa/*` from a checkout of [waaseyaa/framework](https://github.com/waaseyaa/framework) instead of waiting for Packagist tags.

1. Place the repos side by side (paths in `composer.local.json.example` assume `../waaseyaa` next to this project root).
2. `cp composer.local.json.example composer.local.json` (`composer.local.json` is gitignored).
3. Run `composer update "waaseyaa/*"` so Composer symlinks `vendor/waaseyaa/*` to `../waaseyaa/packages/*`. You need `prepend-repositories` (already set in `composer.json` via the merge plugin) so the path repository wins over Packagist.
4. **Lock file:** CI installs from the committed `composer.lock` (Packagist). After a path `composer update`, your `composer.lock` will reference `dist.type: path` — do not commit that unless the whole team uses path installs. To return to tagged packages: remove `composer.local.json`, `git checkout composer.lock`, then `composer install`.
