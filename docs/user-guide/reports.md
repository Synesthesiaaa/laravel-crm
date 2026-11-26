# Read dashboards and reports

**Dashboard** summarizes configured campaign business activity. **Administration → Reports** opens **Telephony Reports** for Team Leaders, Admins, and Super Admins. **Operations Insights** is a separate reporting surface described in the [supervisor and operations guide](../admin/supervisor-and-operations.md).

## Choose the scope before reading a number

1. Choose the **CRM Campaign**.
2. Choose **Report Mode**: **Historical**, **Live**, or **Today**.
3. For historical reporting, choose **Start Date** and **End Date**.
4. Open **More Filters** when you need **VICIdial Campaigns**, **Disposition Scope**, or **Comparison**.
5. Select **Refresh Reports**. Review the displayed campaign, period, source health, and freshness before comparing results.

Historical and Today use the same VICIdial sources. Today selects the current calendar day in the report timezone. Figures for an open day are provisional, and the fetch time shows when the CRM received the figures. Live shows operational activity for a rolling window. Mapped VICIdial campaigns and the server's reporting permissions determine the available data.

## What the report metrics mean

| Metric | Implemented meaning |
| --- | --- |
| Total Call Attempts | The VICIdial call-status count for the selected campaigns and dates, including all dispositions. |
| Answered Attempts and Answer Rate | The answered count from the same VICIdial response. The rate divides that count by total attempts and rounds to two decimals. Confirmed zero totals produce a zero rate. |
| Calls Matching Disposition Filter | The sum of the disposition counts included by the active filter. |
| Contact and Abandoned Rates | Unavailable until the source can verify the required call outcomes. |
| Calls per Agent | Total attempts divided by agents with activity, rounded to two decimals. Requires an available, equivalently scoped agent count. |
| Average Talk Time | Total talk seconds divided by agent calls from the agent export, rounded to one decimal. |

Disposition filters change the matching subtotal and disposition breakdowns. The full attempt count, answer count, and hourly volume use the full disposition population. The optional funnel uses configured disposition groups. Use the native VICIdial report for official figures while a campaign shows **Native VICIdial reconciliation pending**. [Reconciliation runbook](../operations/vicidial-report-reconciliation.md)

Dashboard **Daily Comparison** has an **Overall / form** selector. Its chart and accessible data table compare the selected form against the equivalent previous-month days, with the existing volume/amount controls. Daily, weekly, and monthly activity charts show an Overall series and a named series for each form; use the legend to show or hide a series. Empty forms retain zero buckets. Activity counts all submissions; Daily Comparison follows configured sales qualification rules.

## Sales are a separate measure

Dashboard sales come from configured CRM form records, not simply from every answered call or a universal SALE code. In legacy field mode, a record counts when at least one field marked as a sale amount contains a numeric value; numeric zero still qualifies, and the marked numeric values are summed. Custom form rules can count all submissions, a numeric amount marker, or matching configured tags. Tag matching uses any matching configured condition, not an assumed all-conditions rule.

Date bases vary by widget: activity charts and sales summaries can use `created_at`, while date-range form reports can use the form's `date`. An Agent's form date is forced to the current application day. Check labels and scope instead of assuming all cards use the same period.

## Missing, stale, and partial results

**Unavailable** means a source or metric could not be verified; it does not mean zero. **Stale** means a last successful snapshot is being shown. Partial/degraded results mean some sections may be usable while others are not. Use **Retry** or **Refresh Reports** once, then ask an administrator to check routing and permissions if the issue persists. Do not combine a stale numerator with a fresh denominator manually.

Source: [report interface](../../resources/views/reports/index.blade.php), [historical calculations](../../app/Services/Telephony/HistoricalTelephonyReportService.php), [realtime service](../../app/Services/Telephony/RealtimeTelephonyReportService.php), [dashboard calculations](../../app/Services/DashboardStatsService.php), [sales rules](../../app/Services/DashboardSalesRuleService.php).
