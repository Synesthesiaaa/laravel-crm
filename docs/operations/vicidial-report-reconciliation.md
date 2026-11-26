# Reconcile VICIdial call reports

Keep native VICIdial reports as the official reference until Operations signs off on each CRM campaign. A successful connection or a healthy report source only indicates that the CRM received usable data.

## Agree on the source

For each CRM campaign, record the mapped VICIdial server and campaigns, the native report name and version, the timestamp timezone and date field, and the date, direction, in-group, disposition, and retry filters. Verify whether transfers are separate records in the selected native report. Use the same report definition on both sides of every comparison.

| CRM figure | Source or calculation |
| --- | --- |
| Total Call Attempts | VICIdial `call_status_stats` total for the unfiltered disposition population in the selected campaign, in-group, and date scope. |
| Answered Attempts | The answered field from the same VICIdial response. Verify its deployed definition before interpreting it as human contact. |
| Answer Rate | Answered attempts divided by total attempts, multiplied by 100 and rounded to two decimals. A source-confirmed zero cohort reports zero. |
| Calls Matching Disposition Filter | Sum of the included codes from `call_dispo_report`. Scope choices affect this subtotal and applicable breakdowns. |
| Contact Rate and Abandoned Rate | Unavailable pending verified outcome evidence and an agreed denominator. A non-system status or `ANA` alone does not establish either outcome. |
| Agent statistics | VICIdial `agent_stats_export` within its supported campaign and date scope. Calls per agent is unavailable when an in-group filter cannot be applied to the agent export. |

Today and Historical use the same sources. An open day is provisional. The displayed fetch time records when the CRM received the remote response. VICIdial's date-only report functions cannot enforce a fixed intraday cutoff. For exact intraday qualification, acquire a validated source export or read-only ledger with timestamp bounds before granting approval.

## Compare a closed period

1. In the native VICIdial interface, generate and retain the report exports for the agreed scope. Use a dedicated read-only reporting account. Retain the report and filter reference in the internal audit record.
2. In an authorized CRM session, capture the JSON response for `GET /api/reports/dashboard` with the same CRM campaign, mapped campaign codes, dates, timezone, in-groups, and disposition scope. Start with All Dispositions. Repeat with each supported filtered scope.
3. Normalize the native export into the shape below using whole JSON numbers. Copy the effective scope values only after checking them against the export. Use one attempt subtotal row for each selected campaign, including a documented zero where confirmed. Disposition rows may repeat a campaign when the native export splits it into groups. Exclude report footer totals from the rows.
4. Run the offline comparison and retain its exit status and any differences with the source exports. The command reads both files locally and does not connect to the dialer or change the CRM.

```bash
php artisan reports:reconcile-vicidial crm-report.json native-report.json
```

```json
{
  "export_reference": "Native report archive reference",
  "campaign_scope": {"server": {"id": 42}},
  "filters": {
    "crm_campaign": "mbsales",
    "campaigns": "MBSALES",
    "query_date": "2026-11-25",
    "end_date": "2026-11-25",
    "timezone": "Asia/Manila",
    "ingroups": null,
    "disposition_scope": "all"
  },
  "report_scope": {"days_open": false},
  "summary": {"total_calls": 10, "answered_calls": 3},
  "campaigns": [{"campaign": "MBSALES", "total_calls": 10, "answered_calls": 3}],
  "disposition_summary": {"total_calls": 10},
  "disposition_rows": [
    {"campaign": "MBSALES", "metrics": [{"label": "SALE", "value": 3}, {"label": "NA", "value": 7}]}
  ]
}
```

The inputs can omit unrelated agent details and raw debug responses. The sample numbers are illustrative. The comparison verifies attempts and answers by campaign, the disposition subtotal and nonzero code counts by campaign, the scope, and the CRM's answer-rate arithmetic. Each capture must have complete numeric counts.

## Approve and monitor

Compare representative closed business days, an empty campaign/day, Manila midnight boundaries, retries, transfers, late disposition updates, and known inbound and outbound cases. Retain fixed-cutoff comparisons separately where the data source can enforce the cutoff. For direction-specific native exports, review the direction or in-group mapping as part of the comparison and record any scope the CRM source cannot reproduce.

Resolve every unexplained count difference. Operations and the VICIdial administrator should record the approved metric definitions, reference exports, tested windows, and sign-off. Set `VICI_RECONCILED_REPORT_CAMPAIGNS` to the approved CRM campaign codes and refresh Laravel configuration through the deployment process. An empty list leaves all campaigns pending. Remove a campaign's approval if the source version, mapping, or report definition invalidates the comparison. Monitor available source states, fetch delays, and discrepancies with repeat comparisons of closed periods.
