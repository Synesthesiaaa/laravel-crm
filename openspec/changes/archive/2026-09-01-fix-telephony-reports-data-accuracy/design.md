## Context

The historical Reports endpoint already resolves a CRM campaign to one VICIdial server and mapped campaign codes, then batches `call_status_stats`, `agent_stats_export`, and `call_dispo_report`. The current implementation is not yet a reliable report boundary: transport success is treated as parse success even when rows are empty, parsers assume several positional layouts, agent time fields are incomplete, and the browser contains fallback normalizers that reintroduce zero values and duplicate interpretation.

The existing Agent Performance and Talk Time paths are the compatibility baseline. The implementation must remain within the current Laravel 12, Blade, Alpine, ApexCharts, and PHPUnit stack, with no dependency or schema changes.

## Goals / Non-Goals

**Goals:**

- Make the historical report service the single authority for parsed VICIdial metrics and availability semantics.
- Keep all report requests bound to the selected CRM campaign's selected server and enabled mapped VICIdial campaign codes, case-insensitively.
- Parse known VICIdial delimited report variants by normalized headers or documented positional layouts, and expose parser diagnostics rather than guessing.
- Sum raw numerators and denominators across campaigns before deriving rates, percentages, contact rate, and comparisons.
- Preserve confirmed zero as distinct from empty, unsupported, parse-failed, and transport-failed data.
- Honor a configured report timezone for date boundaries and return stable chart/table arrays with aligned labels and values.
- Preserve the last successful browser snapshot when a refresh fails and make unavailable values visible as `Unavailable`/`—`.
- Add fixture-backed, feature, unit, and browser-facing regression coverage for every affected report surface.

**Non-Goals:**

- Changing VICIdial configuration, report permissions, or upstream report definitions.
- Replacing the existing Supervisor realtime aggregation or the already-correct Agent Performance/Talk Time source behavior.
- Inferring unsupported Ready/Other metrics from talk, pause, wait, login, or CRM event data.
- Adding a database snapshot/cache table or a new frontend framework.

## Decisions

### 1. Keep one server-scoped batched upstream request and centralize interpretation

`ReportingService::historicalSnapshot` remains responsible for request construction and `HistoricalTelephonyReportService` remains responsible for parsing/aggregation. The controller continues to validate the public filters and delegates the selected CRM campaign to the shared resolver. This preserves server isolation and avoids duplicating scope logic in controllers or JavaScript.

Alternative considered: have each report card call a separate endpoint and normalize in Alpine. Rejected because it creates inconsistent scopes, more request races, and makes the browser authoritative for business metrics.

### 2. Use explicit metric state metadata alongside nullable values

Each report source and normalized section will carry a state such as `confirmed_zero`, `data`, `empty`, `unsupported`, `parse_failure`, or `transport_failure`. Numeric fields are `null` unless their state proves the value; a successful empty VICIdial response is not a zero-row confirmed report. Existing top-level fields remain for compatibility, while section metadata gives the UI enough information to choose `0`, `Unavailable`, or `—` correctly.

Alternative considered: use `0` plus a single dashboard status. Rejected because it cannot distinguish a real zero from missing or malformed data.

### 3. Parse headers first and isolate documented positional fallbacks

The parser will normalize headers by trimming, lowercasing, and converting non-alphanumeric runs to underscores. Headered reports map fields by names and aliases; known headerless `call_status_stats` rows use an isolated documented positional map. Required fields must be present and numeric before a row contributes. Malformed numeric fields are skipped or mark the relevant metric unavailable rather than coerced to zero. Raw response metadata remains available in the admin-only diagnostic block.

Alternative considered: broaden the positional indexes until fixtures pass. Rejected because production VICIdial exports can add/reorder columns and that approach silently assigns the wrong metric.

### 4. Aggregate raw facts, then derive all rates

Campaign rows, hourly buckets, status totals, disposition totals, agent contributions, and time totals are accumulated using normalized case-insensitive keys. Answer/contact/percentage values are calculated once from the accumulated raw counts. Comparison periods rerun the same scoped pipeline with the same campaign filter and derive changes from the two normalized summaries.

Alternative considered: average percentages returned by each campaign/report row. Rejected because it is mathematically incorrect for unequal denominators.

### 5. Treat timezones and unsupported time fields explicitly

The report service will resolve the configured report timezone (with a safe application fallback), build local start/end boundaries, and pass the corresponding VICIdial request values consistently. Agent `ready` and `other` values will be parsed only from recognized fields/aliases and will remain `null` with an `unsupported`/`unavailable` state if VICIdial does not supply them. No CRM-derived approximation will be presented as VICIdial data.

Alternative considered: keep `+00:00:00` and fill missing time values with zero. Rejected because it shifts date windows and misrepresents unavailable metrics.

### 6. Make the browser a renderer of the backend contract

Alpine will consume normalized arrays and states from the dashboard response. Legacy client-side VICIdial parsers will be removed or made test-only so charts do not parse the same raw rows a second time. Formatting helpers will return `Unavailable`/`—` for null values, while confirmed zero remains `0`. A retained successful snapshot is only replaced by a successful live response for the same filter key.

Alternative considered: repair both backend and frontend parsers independently. Rejected because two parsers will drift again and can disagree on totals.

## Risks / Trade-offs

- [Existing VICIdial installations use undocumented export variants] → classify the source as unsupported/parse failure, retain raw diagnostics for authorized admins, and add sanitized fixtures for each observed variant instead of guessing.
- [A report source returns a valid empty result] → keep the source state as `empty`; do not generate zero-valued cards or chart series from it.
- [One of the batched requests fails] → preserve successful sections and expose section-level availability; only retain the prior whole dashboard snapshot in the browser when the response is not a trustworthy complete replacement.
- [Configured timezone is invalid or absent] → use the application timezone and expose the effective timezone in request/diagnostic metadata; never silently use a different date range.
- [Changing null semantics affects existing UI/tests] → keep the existing response keys and update formatting/tests deliberately; no unavailable metric is converted to a numeric zero for compatibility.
- [Charts are visually useful but not inherently accessible] → retain aligned visible tables/empty states and text labels/statuses so color or hover is not the sole source of information.

## Migration Plan

No schema migration is required. Deploy the service/parser and UI contract changes together, run the focused PHPUnit suite and browser checks, then monitor report source classifications and raw response hashes. Rollback is a code rollback; existing mappings and VICIdial data are untouched.

## Open Questions

- Which exact Ready/Other column aliases are present in each production `agent_stats_export` version? Until verified, those fields remain explicitly unavailable.
- Is the configured report timezone intended to be the Laravel application timezone or a separate VICIdial server timezone? The implementation will prefer a dedicated report setting if present and otherwise use the application timezone.
