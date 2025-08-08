# CLI

## Prune
```
php artisan sharelink:prune
```
- Removes expired and revoked links (expired dispatches `ShareLinkExpired`).

## Scheduler
- Package wires a daily prune by default (03:00). Override cron in config.

## Create
```
php artisan sharelink:create <resource> \
	--expires=HOURS \
	--max-clicks=INT \
	--password=SECRET \
	--metadata='{"key":"value"}' \
	--burn \
	--signed-minutes=MIN
```

## Revoke
```
php artisan sharelink:revoke <token>
```

## List
```
php artisan sharelink:list [--active]
```
