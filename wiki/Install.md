# Install

1) Install via Composer
```
composer require grazulex/laravel-sharelink
```

2) Publish config and migration (optional)
```
php artisan vendor:publish --tag=sharelink-config
php artisan vendor:publish --tag=sharelink-migrations
php artisan migrate
```

3) (Optional) Configure scheduler for prune
- By default the package registers `sharelink:prune` daily at 03:00.
- Override via `config/sharelink.php` -> `schedule.prune.expression`.

Next: [[Configuration]]
