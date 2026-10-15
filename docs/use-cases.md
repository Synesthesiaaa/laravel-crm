# Practical use cases

These scenarios use invented organizations, people, identifiers and records. `training` is an illustrative CRM campaign code, not a promise of seeded data. Any phone or email test must use operator-controlled destinations; none of the procedures below was executed for this documentation. Expected results describe the intended workflow and acceptance criteria, not verified end-to-end calling, delivery or deployment readiness.

## 1. Run an outbound customer campaign

**Business problem:** agents need to contact the intended customer, record the conversation and leave an accurate outcome so the next agent and campaign reports have useful context.

**Intended users:** Agent handles calls; Team Leader supervises exceptions; Super Admin provisions the campaign and integration.

**Prerequisites:** a configured active CRM campaign, enabled Agent Screen/calling features, permitted synthetic lead data, approved VICIdial account and campaign/server routing, usable phone session and a separately validated media path. Complete [daily startup](user-guide/daily-workflow.md) and [phone preparation](user-guide/calls-and-dispositions.md) first.

**Synthetic scenario:** Northstar Demo's `training` campaign asks agent Alex to discuss a fictional service renewal with a test customer. The destination is a controlled test extension or number supplied by the PBX operator, not an arbitrary example number.

1. Sign in and confirm the displayed CRM campaign. Open the phone widget and establish the configured VICIdial/phone session; check readiness and any error messages.
2. Open Agent Screen, search/load the synthetic lead through the available [lead tools](user-guide/campaigns-and-leads.md), and verify its lead ID and destination before dialing.
3. Select **Dial phone number** once. Watch the result; if local state and the external phone disagree, stop and involve the supervisor before retrying.
4. During the approved test conversation, save the required form or capture record and wait for its success confirmation.
5. End the call if still active. Save the configured disposition that matches the actual outcome, then wait for wrap-up to clear.
6. If the test customer requested follow-up, separately [schedule a callback](user-guide/callbacks.md), verify its date/lead, and review the saved result.
7. Have an authorized supervisor reconcile the call, business record and disposition in their appropriate views.

**Expected result and benefit:** one intended contact has a traceable call outcome and saved business context; the next action is clear and pending wrap-up does not block subsequent work unnoticed.

**Limits:** successful dial submission does not prove answer or two-way audio. Form, capture, call, disposition and callback saves are separate actions. A CRM campaign is not a VICIdial campaign, and local session readiness can differ from remote state. Real calls/media remain unverified until an authorized environment test succeeds.

## 2. Collect and correct customer information

**Business problem:** information collected during a conversation must be attached to the correct person and form, with required fields completed once and corrections visible to authorized staff.

**Intended users:** Agent collects information; authorized Admin/Team Leader reviews records according to page permissions; Super Admin configures fields/forms.

**Prerequisites:** a configured campaign form or enabled capture webform, reviewed field rules/options/visibility, synthetic customer data and access to the relevant save/review pages. See [forms and records](user-guide/forms-and-records.md) and [form configuration](admin/forms-and-field-logic.md).

**Synthetic scenario:** Alex records fictional customer Morgan Sample's preferred contact method and service interest in the `training` campaign. The form definition already contains the needed fields; the agent does not invent new fields while taking the call.

1. Confirm the campaign and synthetic customer/lead context. If loaded details belong to another lead, stop before saving.
2. Open the intended form or capture surface. Review required fields and the options revealed by conditional choices.
3. Enter synthetic values, complete required fields and submit once. If validation fails, correct the indicated allowed values rather than bypassing browser controls.
4. Wait for a successful save and note the non-sensitive record identifier/time. A timeout or missing acknowledgment requires checking for an accepted record before submitting again.
5. Ask an authorized reviewer to find the row in **Data Master** for form data or **Capture Records** for an Agent Screen capture.
6. If a value is wrong, correct the existing record through the permitted administration action and verify the result. If a field definition is wrong, escalate it to form administration instead of deleting customer rows.

**Expected result and benefit:** a single correctly associated record captures the intended information, and corrections preserve operational clarity without duplicate entries.

**Limits:** form records, captures and VICIdial leads are different entities. A local capture may queue a separate remote update only for configured writable mappings; local save success does not confirm remote synchronization. Field metadata can affect physical storage, and the campaign resolver is not a per-user membership security boundary.

## 3. Monitor a team and investigate missing activity

**Business problem:** a supervisor needs to distinguish idle/paused/on-call staff, stale integration data and genuinely missing business activity before taking action.

**Intended users:** Team Leader, Admin and Super Admin for supervisor routes. Quality Analyst or Operations Manager are organizational responsibilities, not additional application roles.

**Prerequisites:** an authorized management account, known CRM campaign/server routing, understood metric definitions and source/freshness indicators. Review [supervisor and operations guidance](admin/supervisor-and-operations.md).

**Synthetic scenario:** Team Leader Sam supervises three synthetic agents. Alex has a saved form record, Blair is on an attendance break, and Casey's phone status feed is stale. Sam must avoid interpreting all three signals as the same kind of activity.

1. Open **Administration → Supervisor** and select the intended CRM campaign in VICIdial Routing.
2. Confirm the selected server/routing message and inspect freshness/source health before reading agent counts.
3. Review available/on-call/paused states. Open **Staff Attendance** separately to inspect login/status activity; do not infer a phone pause from an attendance break.
4. For Alex's reported missing work, compare **Call History** or **Records List**, then the correct **Data Master** or **Capture Records** scope. Match campaign, date, timezone, agent and record type.
5. For Casey's stale feed, use the available retry once after a transient failure and escalate persistent unavailability with sanitized campaign/time/status details.
6. Use monitor, whisper, force-pause/logout or notification controls only when actually exposed, authorized and operationally justified; confirm the exact agent and campaign first.

**Expected result and benefit:** the supervisor can separate data-source failure from staff activity and choose a justified follow-up without duplicating records or disrupting the wrong agent.

**Limits:** stale/unavailable is not zero or absence. CRM management roles do not prove remote permissions, and supervisor controls can affect real sessions. A sent notification is not proof it was read. This scenario does not establish end-to-end realtime or PBX behavior.

## 4. Configure a campaign and onboard staff

**Business problem:** a new workstream needs consistent forms, outcomes, staff access and telephony routing before agents begin entering real records.

**Intended users:** Super Admin configures campaign, users, forms, servers and feature switches; agents and supervisors participate in controlled acceptance checks.

**Prerequisites:** approved campaign requirements, storage/field definitions, role assignments, external provisioning plan and a safe rehearsal environment. Follow [first campaign setup](getting-started/first-campaign.md).

**Synthetic scenario:** Northstar Demo creates a `training` renewal workstream with a simple information form, a small set of agreed outcomes, one synthetic Agent account and an operator-controlled phone-system campaign.

1. Create an active CRM campaign with a unique code/display name and confirm it appears in the intended selector.
2. Create its registered form and fields; review requiredness, options and conditional visibility. Configure Agent Screen capture/webform behavior separately where needed.
3. Create synthetic accounts with one of the four supported roles. Verify allowed navigation and that disallowed administration actions remain forbidden.
4. Configure the campaign-linked VICIdial server and explicit mappings. Have the PBX operator separately verify remote accounts, allowed functions, TLS/WSS and the selected audio owner.
5. Configure appropriate disposition codes and feature switches. Enabling a control is not evidence that its remote dependency works.
6. Sign in as the synthetic Agent, confirm the displayed campaign, submit a safe form and have the supervisor review it. Conduct calling/callback acceptance only in the approved PBX sandbox.
7. Review report scope, empty-state handling and operational readiness before allowing real work.

**Expected result and benefit:** staff receive a coherent configured work area with reviewed fields and outcomes, while remote integration readiness is tracked separately rather than assumed from successful account creation.

**Limits:** missing/invalid campaign selection may fall back to another active campaign; verify page labels. No general per-user campaign membership assignment is implemented. CRM, email and VICIdial campaigns are distinct. A CRM user save can succeed while external credential synchronization fails. CSV imports currently create form submissions rather than automatically populating a dialer queue; see [import limitations](admin/campaigns.md#lead-hopper-imports).

## 5. Prepare secure personalized PDF email

**Business problem:** an operator must associate each private document with the intended recipient, review content and mapping, and prevent an unsafe or ambiguous bulk send.

**Intended users:** Super Admin only. HR or payroll responsibility does not introduce a separate application role or bypass the access checks.

**Prerequisites:** organizational authorization, approved recipient/document source, private storage and inspection/scanner dependencies, configured SMTP and dedicated email worker policy, and the canonical [Email Campaigns and Private PDF Delivery guide](EMAIL_CAMPAIGNS.md). Use the [synthetic personalized manifest](examples/email-personalized-recipients.csv) only as a format reference; its filenames do not supply actual PDFs.

**Synthetic scenario:** Super Admin Robin prepares two fictional service statements for operator-controlled test mailboxes. Each synthetic PDF contains only its matching fictional recipient's information. A separate rehearsal considers payroll documents, which must remain blocked if required protection is unavailable.

1. Choose the purpose and attachment mode deliberately. For private originals, use recipient-specific mode, or a combined mode only when an additional genuinely shared PDF is intended.
2. Prepare a strict CSV with `email,pdf_filename` and only supported optional headers. For payroll, also supply required `employee_id,period`. Give each recipient one unique exact filename and independently verify the PDF's contents belong to that recipient.
3. Upload either a ZIP of root-level PDFs or multiple personal PDFs. Resolve missing/extra/case-colliding files and all validation/inspection blockers; never place personal PDFs in a shared attachment slot.
4. Create the draft and review every recipient/document assignment using the private authenticated review controls. A filename match alone is insufficient evidence of content ownership.
5. Run the internal dry-run. It checks content/mappings/hashes without SMTP; changes invalidate it. If an email test is authorized, the review self-test sends synthetic content to the operator account without personal PDFs and does not validate final attachments.
6. Check purpose-specific policy gates before authorizing dispatch. **If payroll password protection is required, keep payroll blocked: uploaded-original password protection is unavailable.** A generated password-protected template PDF does not encrypt uploaded originals; do not disable a required policy to proceed.
7. Only when all applicable gates pass and sending is explicitly authorized, complete review/confirmation and use the intended controlled delivery environment. Review per-recipient outcomes; reconcile uncertain sends with provider evidence before any retry.
8. Verify the report, private document retention and incident-handling process. Share document passwords separately through an approved channel where applicable; never include real identifiers, signed URLs or PDFs in support notes.

**Expected result and benefit:** the operator obtains an auditable, reviewed one-recipient/one-document mapping and either an authorized dispatch or an explicit safety block. Preventing an unsupported payroll send is a successful policy outcome.

**Limits:** this is not a claim of end-to-end delivery or compliance. SMTP/provider acceptance is not inbox delivery. A protected generated PDF does not protect original attachments, and template protection is not employee-specific password derivation. Frozen approved transport/content and uncertain delivery outcomes require the canonical recovery procedure. No send was performed for this scenario.

## 6. Compare operational performance

**Business problem:** operations staff need comparable figures for the same campaign, period and source without confusing local form sales, telephony dispositions or unavailable remote data.

**Intended users:** authorized Team Leader/Admin/Super Admin for ordinary Reports; organizational QA/Operations staff may use approved reporting surfaces under the deployment's access policy.

**Prerequisites:** agreed metric definitions, reporting timezone, source/mapping configuration and an approved data-access boundary. Read [reports](user-guide/reports.md) and [Operations Insights access notes](admin/supervisor-and-operations.md).

**Synthetic scenario:** Sam compares two five-day windows in `training` using a known fictional dataset. One window has form submissions but delayed imported call history, so sales totals and telephony totals should not be forced to agree.

1. Open the appropriate Reports or Operations Insights surface and verify which data is permitted to be viewed there.
2. Select the CRM campaign, reporting context/mode and timezone. Set comparable dates within the supported historical range (Operations Insights limits historical requests to 31 days).
3. Choose disposition scope, any external campaign/ingroup filters and the intended comparison period. Check sales-date/time filters independently where offered.
4. Inspect freshness/source availability before interpreting results. Mark unavailable or stale sections as unresolved instead of recording them as zero.
5. Compare the same metric definition and source across both windows. Use Data Records or the relevant authorized record view to reconcile the known synthetic submissions.
6. Document the filters, timezone, source/freshness and unresolved sync issue with the comparison; escalate unexplained differences without re-entering records.

**Expected result and benefit:** the comparison explains an operational change using consistent definitions and clearly separates known business activity from incomplete telephony evidence.

**Limits:** Operations Insights and its data/records routes are public at the application route layer, including form-data access; deployment owners must assess exposure before use. A read-only interface is not a privacy guarantee. QA/OM are not formal roles. Live/today/historical views can use different sources, and a zero count is meaningful only when its source is available.

## 7. Troubleshoot failed VICIdial synchronization

**Business problem:** staff report missing calls or stale lead/account data after a CRM action; operators must identify the failing boundary without duplicating remote actions or losing accepted local work.

**Intended users:** Super Admin for configuration/account changes; authorized deployment/PBX operator for source, worker and provider investigation; supervisor supplies sanitized incident context.

**Prerequisites:** incident time/timezone, campaign/server aliases, affected operation and expected synthetic result; access to approved diagnostics/status reports and current [VICIdial integration](integrations/vicidial.md), [worker guide](operations/queues-and-workers.md) and [troubleshooting](operations/troubleshooting.md).

**Synthetic scenario:** a completed test call from the previous day is absent from `training` Call History. Refresh was accepted, but the last successful local history synchronization has not advanced. The operator must distinguish an unconsumed job from a wrong external campaign mapping or rejected provider request.

1. Identify the exact sync: call history, user credentials, disposition or lead fields. Record the local action/result and sanitized time/scope; do not assume one shared synchronization state covers all of them.
2. For history, review the selected date/campaign filters and local sync status/freshness. A refresh acknowledgment means work was requested, not that a remote fetch completed. Check whether the intended window falls outside the recent-sync coverage.
3. Have the authorized operator inspect the campaign-linked server, Agent versus Non-Agent endpoint, explicit campaign mappings, TLS trust and remote account permissions. Preserve the same server scope; do not fall back to an unrelated server to make a request succeed.
4. Inspect the intended queue connection/lane and consumer, scheduler enablement, failed-job status and overlap/uniqueness behavior using the deployment procedure. Starting a consumer may execute queued side effects and must be deliberate.
5. Correct the identified configuration, permission or process fault under the normal change procedure. Do not disable TLS/readiness/secret checks or blindly replay dial, transfer or uncertain remote writes.
6. For older history, plan an explicitly authorized bounded backfill using the documented command/options and expected remote load. The command dispatches jobs that contact VICIdial; it is not a read-only health probe.
7. Recheck successful sync timestamp/status and reconcile the known synthetic call in the intended window. For credential/lead/disposition sync, verify that specific remote result separately while retaining already accepted local records.
8. Escalate unresolved failures with release, server/campaign aliases, API family, timestamps and redacted status/error. Exclude query credentials, customer numbers, SIP/SMTP secrets and private document contents.

**Expected result and benefit:** the operator identifies a specific failed boundary, recovers it safely where authorized, and reconciles local/remote results without treating a queued acknowledgment as completion or creating duplicate actions.

**Limits:** no live sync/backfill/worker was run for this scenario. Remote permissions, availability and supported API functions require environment evidence. Repairing history does not prove credential, call-state or lead-field synchronization. A CRM account/form save can remain valid even when the corresponding remote operation failed.

## Additional routine cases

### Take a break without confusing attendance and phone state

An Agent starts the appropriate **Attendance** status, separately pauses the phone session if local procedure requires it, then ends the attendance status and resumes phone availability on return. Confirm each result. The benefit is a clearer attendance/availability record; neither subsystem automatically proves the other's state. See [attendance](user-guide/attendance.md).

### Import a customer CSV without assuming dialer enrollment

A Super Admin selects the campaign/form in **Lead Hopper**, uploads an authorized synthetic CSV and reconciles queued/imported/failed counts against accepted form rows before retrying. The current job writes form submissions, not hopper rows or VICIdial leads. Done means the intended form records were reconciled; automatic dialer enrollment is not an expected result. See [campaign import limitations](admin/campaigns.md#lead-hopper-imports).

### Remove aged form data under an approved policy

A Super Admin agrees the retention scope/recovery process, selects the form and fixed date window in **Data Retention**, reviews whole-row versus selected-field mode, and rehearses the policy on synthetic data before an authorized run. Review the result and maintain the agreed backup process. The benefit is deliberate lifecycle management; a policy does not remove every copy in exports, backups or external systems, and deleting the policy does not restore removed data. See [retention](admin/retention-and-activity-log.md).
