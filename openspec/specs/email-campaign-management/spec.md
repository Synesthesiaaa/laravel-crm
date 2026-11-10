# Email Campaign Management

## Purpose

Manage consented audiences, safe composition, asynchronous campaign delivery and evidence-based reporting within Laravel CRM.

## Requirements

### Requirement: Authorized campaign administration
The system SHALL authorize Super Admin to manage all email workflows and SMTP/provider settings. Admin SHALL manage only their own attachment-free marketing campaigns, templates, contacts and segments. Team Leader and Agent MUST NOT access email administration. List, metrics, JSON, tests, reports and mutation endpoints MUST enforce the same scope.

#### Scenario: Admin attempts confidential access
- **WHEN** an Admin requests another actor's campaign, private PDF, payroll workflow or SMTP settings
- **THEN** access is denied without disclosing sensitive content or permitting a send

### Requirement: Bounded asynchronous audience preparation
The system SHALL stage manual entries, CSV, XLSX and explicitly mapped authorized CRM sources privately and prepare audiences through resumable bounded queue jobs. Upload requests MUST NOT parse entire recipient lists or documents. Invalid/incomplete/cancelled imports MUST NOT become sendable.

#### Scenario: Large spreadsheet import
- **WHEN** the operator uploads a supported XLSX within configured limits
- **THEN** the request returns an import resource, background jobs validate rows/formulas/archive expansion and progress is available without blocking the request

#### Scenario: Interrupted or cancelled import
- **WHEN** a preparation job is interrupted or the operator cancels its run
- **THEN** resume cannot duplicate staged recipients and cancellation prevents approval while private temporary material is cleaned according to retention

### Requirement: Contacts segments and consent
The system SHALL provide reusable contacts and allowlisted saved segments, validate and deduplicate recipient addresses, record consent provenance and exclude applicable suppressions. CRM source selection MUST require explicit named email-field mapping and MUST NOT infer consent from presence in CRM. New marketing approval MUST require opted-in contacts or an explicit documented operator assertion with source, permission date and actor; unknown/withdrawn/suppressed contacts MUST NOT be approved. Unfrozen historical drafts MUST meet the new approval rule; frozen version-1/2 campaigns MUST retain their approved permission contract and current suppression checks without invented consent backfills.

#### Scenario: Frozen segment audience
- **WHEN** a marketing campaign is approved from a saved segment
- **THEN** the eligible recipients and source description are frozen and later segment edits do not change the approved audience

#### Scenario: CRM consent unknown
- **WHEN** CRM recipients have no recorded consent and no documented permission assertion
- **THEN** import preview reports them as ineligible for marketing approval and no opt-in is inferred

### Requirement: Safe visual and HTML composition
The system SHALL provide self-hosted drag-and-drop composition, raw HTML editing, reusable template designs, allowlisted merge tags and sandboxed responsive previews. The server MUST sanitize all HTML and reject unsupported active content and merge tags in unsafe attribute/style/URL contexts. Legacy plain-text templates MUST retain escaped rendering.

#### Scenario: Malicious editor output
- **WHEN** an operator saves HTML containing executable markup or unsafe merge-tag placement
- **THEN** executable content is rejected or removed according to the documented sanitizer contract and preview/send cannot execute it

### Requirement: Explicit isolated email tests
The system SHALL retain the existing SMTP/synthetic test and provide an explicit rendered-template test using synthetic recipient values and no confidential attachments, addressed only to the current authorized operator's valid account address. Test actions MUST be rate-limited and MUST NOT approve or dispatch a campaign.

#### Scenario: Rendered template test
- **WHEN** the operator requests a rendered test
- **THEN** only the operator receives synthetic content and campaign mapping, scheduling and approval remain unchanged

### Requirement: Scheduling and lifecycle controls
New campaigns SHALL support Draft, Scheduled, Queued, Processing, Paused, Completed, Partially Failed, Failed and Cancelled with guarded transitions. The system MUST retain legacy state compatibility and explicit uncertain-outcome attention. Schedules SHALL store UTC and IANA timezone and require explicit resolution of invalid/ambiguous local times.

#### Scenario: Approval remains responsive
- **WHEN** the operator approves a large ready audience
- **THEN** HTTP records a durable approval request and the preparation worker freezes the unchanged approved draft without parsing or freezing the entire list in the HTTP request

#### Scenario: Concurrent due scheduling
- **WHEN** multiple scheduler processes see a due approved campaign
- **THEN** durable dispatch intent and atomic claiming prevent duplicate recipient submissions

#### Scenario: Pause resume and cancel
- **WHEN** an operator pauses/resumes or cancels an eligible campaign
- **THEN** pending claims follow the new lifecycle/generation, old jobs cannot send new work and already in-flight transport is not represented as recalled

#### Scenario: Future schedule resumed
- **WHEN** a future scheduled campaign is paused and resumed before its instant
- **THEN** it remains scheduled and does not submit early

#### Scenario: All in-flight work finished while paused
- **WHEN** no unfinished recipient remains when the operator resumes
- **THEN** the campaign derives its terminal state instead of waiting indefinitely in Queued

### Requirement: Durable asynchronous delivery
The system SHALL use native Laravel batches and bounded jobs with durable dispatch recovery, provider/account rate limits, configurable pacing, timeouts and worker/scheduler health diagnostics. Recipient attempt records and claims MUST remain authoritative independently of native batch job counts.

#### Scenario: Interrupted dispatch
- **WHEN** a process fails between approved state persistence and queued batch registration
- **THEN** recovery reclaims pending dispatch intent without resending accepted or uncertain attempts

### Requirement: Safe retries and external idempotency boundaries
The system SHALL use bounded exponential backoff for verified transient pre-submission failures or explicit adapter-confirmed non-acceptance. Potentially submitted attempts MUST become uncertain and MUST NOT be automatically retried. Accepted, delivered, in-flight and uncertain recipients MUST be excluded from retry controls.

#### Scenario: Worker dies after SMTP submission
- **WHEN** the provider may have accepted the message before the application records the result
- **THEN** the attempt becomes uncertain and only evidence-backed reconciliation can make verified no-submission eligible for separate retry

### Requirement: Authenticated provider feedback
The system SHALL implement an authenticated Postmark event adapter with configured account/stream correlation, minimal durable receipts and idempotent delivery/bounce/complaint processing. Generic SMTP MUST remain usable with unavailable provider-delivery metrics. Feedback MUST correlate to the exact recorded attempt and recipient.

#### Scenario: Duplicate out-of-order feedback
- **WHEN** verified provider events arrive twice or before the local transport result
- **THEN** recipient counters remain accurate, verified provider evidence is retained and no resend is triggered

#### Scenario: Invalid feedback identity
- **WHEN** webhook authentication or account/stream/attempt/recipient correlation fails
- **THEN** no campaign delivery state or suppression is changed from that untrusted event

### Requirement: Suppression and unsubscribe protection
The system SHALL enforce global marketing opt-outs plus reason/purpose-scoped suppressions at preparation and immediately before submission. Hard bounces and complaints MUST create applicable suppressions. New marketing messages SHALL provide opaque signed/token-protected unsubscribe confirmation and one-click POST with campaign attribution; historical signed links MUST remain functional.

#### Scenario: Link scanner visits unsubscribe
- **WHEN** a scanner issues GET to an unsubscribe URL
- **THEN** a confirmation page is served without changing consent, while a valid explicit or one-click POST applies the idempotent marketing opt-out

### Requirement: Optional privacy-aware engagement observations
Open/click tracking SHALL default off and SHALL remain disabled for confidential/payroll delivery. Enabled tracking MUST use opaque tokens, fixed safe destinations, minimal retained data and documented privacy controls. The system MUST label observations as potentially affected by automation/privacy proxies and MUST NOT infer a human read.

#### Scenario: Redirect tampering or disabled tracking
- **WHEN** a caller supplies an arbitrary destination or tracking is disabled
- **THEN** the application neither redirects to the caller's target nor records unauthorized engagement

### Requirement: Accurate live reporting
The system SHALL report accepted, confirmed receiving-server delivery, hard/soft bounce, complaint, blocked, uncertain, failed, skipped, cancelled, unique observed opens/clicks and attributed unsubscribe outcomes separately. `open_count` MUST NOT be interpreted as engagement. Counts MUST be recipient-based and attempt-aware, rates MUST state their denominator and unsupported/disabled metrics MUST show unavailable coverage instead of invented zeros.

#### Scenario: SMTP-only completed campaign
- **WHEN** all eligible recipients are accepted by an ordinary SMTP transport without an event adapter
- **THEN** campaign processing can complete while confirmed-delivery and engagement metrics remain explicitly unavailable

### Requirement: Histories exports audit and notifications
The system SHALL provide scoped paginated search/filter histories, safe recipient-level diagnostic codes, bounded progress polling, charts, redacted exports and separately authorized identifiable exports protected against spreadsheet formula injection. State, approval, consent, suppression and configuration changes MUST be audited without secrets. Terminal/stalled notifications MUST be idempotent and integrated with the existing notification UI.

#### Scenario: Repeated completion processing
- **WHEN** reconciliation or a retried job observes the same terminal campaign more than once
- **THEN** only one notification for that terminal event version is emitted and recipient-level details remain scoped to authorized actors

### Requirement: Evidence-based release acceptance
The change SHALL remain unarchived until relevant automated, MySQL concurrency, browser, responsive and operational checks pass. Real SMTP/provider/worker/scheduler acceptance MUST use an explicitly approved test mailbox and deployment, and MUST NOT be claimed from fake transports or source inspection.

#### Scenario: Live acceptance unavailable
- **WHEN** credentials, approved test targets or deployment access are unavailable
- **THEN** validation records clearly separate synthetic results from pending live checks and the change is not marked production-ready or archived
