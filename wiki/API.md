# API (PHP)

Namespace `Grazulex\ShareLink`.

## Services\ShareLinkManager
- `create(string $resource): PendingShareLink`
- `resolveUrl(Models\ShareLink $model): string`
- `signedUrl(Models\ShareLink $model, ?int $minutes = null): string`
- `extend(Models\ShareLink $model, ?int $hours = null, ?\DateTimeInterface $until = null): Models\ShareLink`

### PendingShareLink
- `expiresIn(int $hours = 1): self`
- `maxClicks(?int $max = null): self`
- `withPassword(?string $password): self`
- `metadata(array $meta): self`
- `burnAfterReading(bool $on = true): self`
- `generate(): Models\ShareLink`

## Services\ShareLinkRevoker
- `revoke(Models\ShareLink $model): Models\ShareLink`

## Facades\ShareLink
Thin convenience wrapper around `ShareLinkManager`.
