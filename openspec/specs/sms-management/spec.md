# SMS Management

## Purpose

Provide Super Admin consent-based SMS preparation, truthful gateway submission and safe operation without changing telephony.

## Requirements

### Requirement: Super Admin authorization
Every SMS page, mutation and export SHALL require authenticated Super Admin access; jobs SHALL recheck the campaign creator role.

#### Scenario: Direct access by an Admin
- **WHEN** an Admin requests SMS pages or mutations
- **THEN** the server returns forbidden and persists no change

### Requirement: Consent and audience confirmation
Recipients SHALL have normalized Philippine mobile numbers, purpose-specific consent source and timestamp, unique campaign numbers and no applicable suppression. Imports SHALL retain exclusion reasons and require a fresh audience digest confirmation.

#### Scenario: Opt-out after queueing
- **WHEN** a recipient opts out before submission
- **THEN** queued marketing is canceled without transport I/O

### Requirement: Reliable asynchronous submission
Sending SHALL use dedicated queued jobs, atomic claims, persisted idempotency, explicit configured channels, quiet hours, intervals and count limits. Pause/cancel SHALL prevent new submissions. Ambiguous submission or interrupted processing SHALL become unknown and SHALL NOT be automatically resent.

#### Scenario: Gateway timeout
- **WHEN** transport times out after a submission attempt
- **THEN** the message becomes unknown and duplicate job execution cannot resend it

### Requirement: Truthful verified transport
Live sending SHALL default off and require explicit firmware verification and destination allowlisting. Gateway acceptance SHALL mean Submitted to Gateway. Only verified reliably correlated handset events may produce Delivered.

#### Scenario: Reference HTTP acceptance
- **WHEN** the expected response contains a matching channel, number and gateway ID
- **THEN** the message becomes submitted without claiming delivery

### Requirement: Authenticated durable inbound messages
The receiver SHALL run outside HTTP, authenticate exact source, client and secret, bound packets, deduplicate and persist before acknowledgment. STOP SHALL suppress marketing; arbitrary replies SHALL NOT restore consent.

#### Scenario: Spoofed or duplicate packet
- **WHEN** a packet has invalid authentication or repeats a durable event
- **THEN** invalid traffic is rejected and duplicates never create another message

### Requirement: Native operational interface and documentation
The module SHALL provide overview, campaigns/review, inbox/outbox, templates, gateway/channel settings and reports using the CRM design system, pagination, accessible controls and truthful empty states. Deployment and hardware acceptance SHALL be documented separately from automated verification.

#### Scenario: Hardware not connected
- **WHEN** only automated tests have run
- **THEN** documentation and UI do not claim live compatibility or production verification

### Requirement: Private operational filters and approved retention
Generated recipient-history filters SHALL submit phone numbers through CSRF-protected POST requests and session state rather than URL query strings. SMS request audits SHALL omit recipient search values. Content cleanup SHALL default off and preserve message states, consent references and event deduplication keys when explicitly enabled.

#### Scenario: Recipient history filter
- **WHEN** a Super Admin filters a conversation by phone
- **THEN** the redirect URL contains no phone number and the authorized history uses the session filter

#### Scenario: Approved content expiry
- **WHEN** a configured retention period expires for terminal messages
- **THEN** SMS content is removed while submission state and idempotency survive

### Requirement: Explicit draft and recovery operations
Draft changes SHALL invalidate previous audience confirmation. An existing draft audience SHALL require explicit reset before content or policy changes. Unknown outcomes SHALL permit only evidence-backed administrative reconciliation to submitted or failed, never an automatic resend or a claim of handset delivery. Emergency stop SHALL disable outbound gateways and pause active campaigns while authenticated inbound opt-outs remain available.

#### Scenario: Interrupted submission reviewed by an operator
- **WHEN** an unknown result is reconciled using independently verified evidence
- **THEN** the recorded state changes without resending any SMS

#### Scenario: Manila scheduling
- **WHEN** an operator enters a scheduled time in Asia/Manila
- **THEN** validation and persistence preserve that instant regardless of application timezone
