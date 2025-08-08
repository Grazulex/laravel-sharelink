# Quickstart

Create a one-hour link for a local file:

```php
$link = app(\Grazulex\ShareLink\Services\ShareLinkManager::class)
    ->create('/absolute/path/to/report.pdf')
    ->expiresIn(1)
    ->maxClicks(3)
    ->withPassword('secret')
    ->generate();

$url = $link->url; // e.g., https://app.test/share/{token}
```

Create a burn-after-reading link:

```php
$link = app(\Grazulex\ShareLink\Services\ShareLinkManager::class)
    ->create('/tmp/demo.txt')
    ->burnAfterReading()
    ->generate();
```

Signed URL (if `signed.enabled`):

```php
$url = app(\Grazulex\ShareLink\Services\ShareLinkManager::class)->signedUrl($link);
```

HTTP access:
- GET `/share/{token}` with optional `password` query parameter.
- Management (if enabled):
  - POST `/share/{token}/revoke`
  - POST `/share/{token}/extend` with `{ "hours": 2 }`
