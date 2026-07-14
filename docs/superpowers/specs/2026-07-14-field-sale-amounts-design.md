# Field Sale Amounts Design

## Context

Field Logic defines the form fields used by the CRM, while the dashboard currently derives sales from disposition records and a fixed set of lead-data keys. Administrators need a campaign-specific checkbox so a numeric field can be treated as sale value and so an ordinary submission with that value can count as a sale.

## Approved Design

Add an additive `is_sale_amount` boolean to `form_fields`. The add/edit Field Logic forms expose an `Is sale amount` checkbox only for `number` fields, and the list displays the current status. The backend enforces the numeric-only rule even if a crafted request includes the flag for another field type.

Dashboard aggregation resolves marked fields per campaign and form, reads only existing marked columns from each registered form table, and treats a row with any non-empty numeric marked value as one sale. Multiple marked values on the same row are summed into that row's sale amount. The rolling KPI uses `created_at`; the month-to-date leaderboard uses the existing `date` range and groups by `agent`.

Field-driven totals are authoritative when a campaign has active marked fields. Campaigns with no marked fields retain the existing disposition-based count and lead-data amount fallback, avoiding a behavior change before configuration is added. Dashboard caches are invalidated after Field Logic changes.

## Testing and Verification

Feature tests cover checkbox persistence, numeric-only behavior, and rendered controls. Service tests cover one-sale-per-row counting, multi-field summing, empty and malformed values, agent grouping, dynamic missing columns, and the fallback path. Run Laravel Pint on modified PHP files, the focused PHPUnit tests, and browser checks for the Field Logic page and dashboard.

## Self-Review

- No unresolved placeholders or open product decisions remain.
- The UI, persistence, aggregation, fallback, caching, and tests all match the approved behavior.
- Scope is limited to Field Logic and dashboard sales attribution; no new dependencies or public APIs are introduced.
