# Glossary

| Term | Meaning in this application |
| --- | --- |
| Agent | Formal account role for everyday CRM work. Other roles may also use agent-facing tools. |
| Team Leader | Formal management role with Reports, Supervisor, and several data-management tools. Not simply a read-only role. |
| Admin | Formal role above Team Leader for selected configuration actions such as field creation/update and dashboard layout. |
| Super Admin | Formal role for user access, core setup, email tools, retention, branding, and Activity Log. |
| QA / OM | Quality Analyst / Operations Manager job responsibilities; not additional formal account roles. |
| CRM campaign | Application context grouping forms, business records, configuration, and server/report routing. |
| VICIdial campaign | External telephony campaign; distinct from the CRM campaign code. |
| Campaign mapping | Association from a CRM campaign to a selected server and VICIdial campaign codes. |
| Default campaign | User preference/context value. It is not a promise of membership isolation. |
| Lead | Customer/prospect context used by telephony tools. Lead IDs and phone numbers serve different purposes. |
| Lead hydration | Fetching existing lead information to populate the current screen. |
| Lead Hopper | Page showing queued-lead/import information. Its current CSV job saves form rows, not hopper rows. |
| Campaign Form | Configured business form backed by per-form storage, with submission history. |
| Capture Details / capture record | Agent Screen fields and their saved capture data, reviewed in Capture Records. |
| Webform | Configured embedded form surface available for agent capture; not automatically the same as a Campaign Form. |
| Field Logic | Management configuration for campaign form field labels, types, ordering, options, requiredness, and visibility. |
| Disposition | Recorded call outcome code/label and optional remarks. |
| Wrap-up | Post-call state requiring completion of outcome work before another dial. |
| Callback | A scheduled follow-up request against a lead in the telephony integration. |
| ANYONE / USERONLY | Callback assignment types for a pool or a specified phone-system user. |
| VD Login | VICIdial agent username; separate from the CRM username/password. |
| Phone Login / extension | Telephony endpoint identifier used for the phone session. |
| SIP | Voice signaling protocol; SIP credentials are distinct from CRM credentials. |
| DTMF | Telephone keypad tones sent during a call. |
| Inbound group / ingroup | Phone-system grouping used for inbound routing. |
| Predictive dialing | Automated dialing workflow, dependent on campaign settings and telephony readiness. |
| Attendance status | Custom CRM time segment such as a configured break. Separate from VICIdial pause. |
| Data Master | Editor/browser for campaign form rows. |
| Records List | Management activity/submission view with a Calls tab. |
| Extraction | CSV export of selected campaign form data. Not a full-system backup. |
| Historical / Today / Live | Period-based reporting, midnight-to-now reporting, and operational/current-window reporting contexts. |
| Confirmed zero | A source-supported count of zero; different from unavailable data. |
| Stale | Previously successful data retained after a fresh update could not be obtained. |
| Degraded / partial | Some sources or sections are usable while others are not. |
| Sale attribution | Configured rules deciding which CRM form records count toward sales and amounts. |
| Retention policy | Per-form rule targeting a fixed date window for whole-row deletion or selected-field clearing. |
| Activity Log | Filtered application activity records with sanitized information. Not a complete copy of every external-system action. |
| Email campaign | Recipient/template/document sending workflow; distinct from CRM and VICIdial campaigns. |
| Queue | Background work waiting for a matching worker; queued does not mean finished. |

See [features](features.md), [role boundaries](roles-and-permissions.md), and [report formulas](user-guide/reports.md) for operational detail.
