# CLI

## Prune
```
php artisan sharelink:prune
```
- Removes expired and revoked links (expired dispatches `ShareLinkExpired`).

## Scheduler
- Package wires a daily prune by default (03:00). Override cron in config.
