## Scope and source

Historical and Today share `HistoricalTelephonyReportService::dashboard`. Today sets both report dates to the current day in the effective report timezone. Live retains the Supervisor snapshot and rolling metrics. The analytical API never substitutes CRM sessions for VICIdial totals.

Use the unfiltered call-status result for total and answered attempts, the cohort's answer rate, and hourly volume. Use the disposition report for the filtered subtotal and breakdown. The global hide-system setting supplies the default when the caller omits a scope. Explicit scopes take precedence.

Calculate answer rate as reported answers divided by reported attempts. Invalid or partial aggregate responses cannot establish headline counts. Agent statistics retain their existing data requirements. Contact and abandonment rates require validated lifecycle evidence. A visible ANA count is a disposition count. Funnel stages derived from configured codes remain disposition-based measures.

## Time and provenance

Return the effective timezone, inclusive calendar dates, exclusive end-of-day boundary, and fetch time. The VICIdial call-status and disposition functions receive calendar dates. An open day is provisional. A fetch timestamp records when the CRM received its snapshot and cannot establish a dialer processing watermark or a fixed intraday cutoff.

Use the established Reports layout and expose scope, fetch time, subtotal, and reconciliation status beside the metrics. Clear the visible result when scope changes and ignore superseded requests. Retain a stale snapshot only for the same scope.

## Reconciliation

Provide a read-only Artisan command to compare an analytical CRM JSON response with a normalized reference from an independently generated native export. Validate the cohort and campaign membership before comparing total, answered, and disposition counts. Compare stored integer counts and derive rates with agreed rounding. Flag missing, unexpected, and unequal rows and exit nonzero on any unexplained difference.

Only explicitly approved CRM campaign codes show approved rollout status. The default is pending. Operations must validate native deployment settings and the report definitions before setting approval. Document the export format, campaign review, fixed intraday evidence dependency, and sign-off procedure.

No migration or changes to VICIdial call control are needed. Defer new raw call ingestion until the deployed schema, IDs, lifecycle signals, and read-only permissions have been verified.
