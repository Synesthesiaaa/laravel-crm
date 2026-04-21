# System audit reports

This folder contains discovery and remediation reports from the system-wide audit and cleanup initiative.

## Index

| File | Phase | Purpose |
| ---- | ----- | ------- |
| [00-baseline.md](00-baseline.md) | 0 | Tooling baseline and metrics snapshot |
| [01-findings.md](01-findings.md) | 1 | Prioritized backlog of bugs and cleanup items |
| [02-ui-fixes.md](02-ui-fixes.md) | 2 | Soft-navigation UI stability fixes |
| [03-telephony.md](03-telephony.md) | 3 | SIP.js vs ViciPhone decision and feature flag |
| [04-backend.md](04-backend.md) | 4 | Backend cleanup and N+1 resolutions |
| [05-tests.md](05-tests.md) | 5 | Test suite restoration and CI gates |
| [06-security.md](06-security.md) | 6 | Session, middleware, dependency audit |
| [ui-soft-nav-rules.md](ui-soft-nav-rules.md) | 2 | Developer rules for soft-nav-safe scripts |

## Conventions

- Severity uses `P1` (user-visible bug), `P2` (quality/risk, no visible regression), `P3` (nice-to-have).
- All reports cite file paths relative to repo root.
- Each remediation lists commit hash and repro steps once merged.
