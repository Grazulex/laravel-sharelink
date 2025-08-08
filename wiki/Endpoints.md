# Endpoints

Base prefix defaults to `/share` (configurable).

## GET /share/{token}
- Optional query: `password`
- Validations: existence, revocation, expiration, usage limit, signature (if required), rate limit, IP filter
- Responses:
  - 200: File download or JSON (when Accept: application/json)
  - 401: password.invalid
  - 403: sharelink.signature_required/sharelink.signature_invalid/sharelink.ip_denied
  - 410: sharelink.invalid
  - 429: sharelink.limit_reached/sharelink.rate_limited/password.throttled

## POST /share/{token}/revoke
- Enabled when `management.enabled` is true
- Returns the updated `ShareLinkResource`

## POST /share/{token}/extend
- Body: `{ "hours": <int> }` (positive)
- Returns the updated `ShareLinkResource`
