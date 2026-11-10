## MODIFIED Requirements

### Requirement: Authorized review and immutable delivery
Only Super Admin SHALL access private documents and confidential/transactional/payroll campaign details. Admin SHALL access only their own attachment-free marketing campaigns under explicit policies, without access to private or generated protected PDFs or SMTP settings. The system MUST verify document ownership/hash, validate variables, run dry-run and require explicit confirmation before confidential sends. Queued and scheduled content and mappings MUST be frozen. Legacy version-1/2 snapshots and escaped plain-text composition MUST remain supported; new composition formats MUST be independently versioned and authenticated.

#### Scenario: Tampering or unauthorized access
- **WHEN** a file changes, a cross-campaign ID is requested or an unauthorized user requests a preview
- **THEN** delivery or access is denied without exposing private content

#### Scenario: Isolated Admin marketing access
- **WHEN** an Admin manages an owned marketing campaign without uploaded or generated attachments
- **THEN** permitted marketing actions work while another actor's campaigns, private files, transactional/payroll workflows and SMTP settings remain inaccessible

### Requirement: Truthful queue outcomes and lifecycle
The system SHALL send one message with one address per claim, report provider acceptance separately from confirmed receiving-server delivery and inbox delivery, retain uncertain outcomes without blind retry, diagnose worker/scheduler readiness and purge expired documents with auditable metadata. New lifecycle states MUST preserve historical sending/needs_attention compatibility and MUST NOT collapse uncertainty into retryable failure. Provider-event and transport-result races MUST preserve verified attempt evidence.

#### Scenario: Interrupted transport
- **WHEN** SMTP might have accepted a message before interruption
- **THEN** the recipient becomes uncertain and automatic retry cannot send another copy

#### Scenario: Expired document
- **WHEN** retention expires for a draft, failed or cancelled campaign
- **THEN** original and temporary document bytes are deleted and preview/send are blocked

#### Scenario: Provider reconciliation
- **WHEN** Super Admin explicitly confirms provider evidence with a non-personal case ID and verified outcome
- **THEN** acceptance is recorded with its actual timestamp or verified no-submission becomes blocked for separate revalidation; reconciliation never sends mail

#### Scenario: Scheduled document expires
- **WHEN** an approved document campaign reaches its scheduled time after an original has expired
- **THEN** dispatch is blocked with an actionable integrity/policy reason rather than bypassing document retention or approval
