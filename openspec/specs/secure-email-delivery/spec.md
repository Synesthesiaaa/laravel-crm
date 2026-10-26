# Secure Email Delivery

## Purpose

Protect private recipient documents through explicit mapping, authorized review, immutable transport snapshots and fail-closed delivery.

## Requirements

### Requirement: Exact private document mapping
The system SHALL store PDFs privately and encrypted, reject unsafe archives and invalid PDFs, and require a unique exact manifest mapping for every personal recipient. Invalid or extra mappings MUST block the import.

#### Scenario: Two distinct documents
- **WHEN** Alice and Bob import explicitly named PDFs
- **THEN** each receives only their assigned PDF plus intentionally shared attachments

#### Scenario: Unsafe import
- **WHEN** traversal, symlinks, duplicate addresses/files, encrypted/corrupt PDFs or missing/extra documents occur
- **THEN** no campaign is dispatched and temporary files are cleaned

### Requirement: Authorized review and immutable delivery
Only Super Admin SHALL access documents and campaign details. The system MUST verify document ownership/hash, validate variables, run dry-run and require explicit confirmation before confidential sends. Queued content and mappings MUST be frozen.

#### Scenario: Tampering or unauthorized access
- **WHEN** a file changes, a cross-campaign ID is requested or an unauthorized user requests a preview
- **THEN** delivery or access is denied without exposing private content

### Requirement: Purpose-specific composition
Marketing SHALL retain opt-outs and unsubscribe. Transactional/payroll SHALL omit promotional disclosures and require configured organizational policy for payroll; mandated unavailable protections MUST block payroll.

#### Scenario: Transactional opt-out
- **WHEN** an opted-out marketing recipient receives an authorized transactional notice
- **THEN** promotional footer and List-Unsubscribe are absent

### Requirement: Truthful queue outcomes and lifecycle
The system SHALL send one message with one address per claim, report provider acceptance separately from inbox delivery, retain uncertain outcomes without blind retry, diagnose worker readiness and purge expired documents with auditable metadata.

#### Scenario: Interrupted transport
- **WHEN** SMTP might have accepted a message before interruption
- **THEN** the recipient becomes uncertain and automatic retry cannot send another copy

#### Scenario: Expired document
- **WHEN** retention expires for a draft, failed or cancelled campaign
- **THEN** original and temporary document bytes are deleted and preview/send are blocked

#### Scenario: Provider reconciliation
- **WHEN** Super Admin explicitly confirms provider evidence with a non-personal case ID and verified outcome
- **THEN** acceptance is recorded with its actual timestamp or verified no-submission becomes blocked for separate revalidation; reconciliation never sends mail

### Requirement: Synthetic self-test is independent of campaign delivery
An authorized Super Admin SHALL be able to explicitly send a synthetic text-only test to their own valid account email through configured SMTP, including locally with bulk sending disabled. The system MUST NOT attach campaign documents, accept an alternate recipient, queue campaign delivery or alter mapping approval. Synthetic-test errors SHALL use a separate error bag and explain SMTP/account recovery without requiring a replacement draft.

#### Scenario: Local configured SMTP
- **WHEN** the operator explicitly requests a synthetic test with SMTP configured and bulk sending disabled
- **THEN** exactly one synthetic message targets the operator's account with no attachments or CC/BCC while the campaign remains a draft and bulk guards remain in force

#### Scenario: SMTP test fails independently
- **WHEN** SMTP is unconfigured or the synthetic transport fails
- **THEN** a sanitized synthetic-test alert links to Email Configuration while valid document mappings and internal dry-run remain available

### Requirement: Explicit local bulk delivery
The system SHALL permit authorized bulk delivery in APP_ENV=local only when both EMAIL_CAMPAIGN_LIVE_ENABLED and EMAIL_CAMPAIGN_ALLOW_LOCAL are explicitly enabled. HTTP start, pre-send retries and live worker transports MUST apply the same policy. Testing SHALL remain blocked from real transport. Existing authorization, asynchronous queue, SMTP/worker readiness, mapping integrity, payroll policy and explicit confirmation MUST remain required.

#### Scenario: Local opt-in
- **WHEN** both live and local flags are enabled and campaign readiness/confirmation pass
- **THEN** the operator can queue recipients for the dedicated worker without changing APP_ENV

#### Scenario: Local opt-in revoked or testing
- **WHEN** either required flag is disabled locally, or a real transport is attempted in testing
- **THEN** HTTP live start/retry or the worker live attempt is blocked without a transport submission

### Requirement: Frozen delivery verification is recipient-bounded

Newly frozen campaigns SHALL retain the full canonical approval hash and independently authenticate a compact campaign header and each recipient snapshot. A delivery attempt SHALL verify only the selected recipient and its assigned documents. Previously frozen version-1 campaigns SHALL retain full-manifest verification through completion.

#### Scenario: New campaign delivers one recipient
- **WHEN** a version-2 campaign sends a recipient from a large frozen recipient list
- **THEN** the worker authenticates that recipient and its assigned document metadata without hashing or scanning the whole recipient list

#### Scenario: Frozen data is tampered with
- **WHEN** the campaign header, selected recipient payload, or assigned document metadata fails authentication
- **THEN** the recipient is blocked before the transport boundary

### Requirement: Campaign counters follow recipient transitions

Sent, failed, skipped, open, and attention counters SHALL change atomically with each authorized recipient status transition. Active campaign counters SHALL be reconciled periodically without resending recipients. New counter columns SHALL be nullable for existing rows and SHALL NOT require a deployment backfill.

#### Scenario: Recipient reaches a terminal outcome
- **WHEN** an accepted, skipped, blocked, uncertain, cancelled, retried, or reconciled status is recorded
- **THEN** the corresponding campaign counters change exactly once with that status transition

#### Scenario: Active campaign counters drift
- **WHEN** periodic maintenance reconciles an active campaign
- **THEN** it repairs stored counters and final status from grouped recipient states without sending any message

