## 1. Queue and deployment routing

- [x] 1.1 Add a dedicated Horizon supervisor for the `telephony` queue with bounded Call History worker settings.
- [x] 1.2 Update local worker scripts, Composer development workflow, and operational README commands to consume `telephony` and `default`.
- [x] 1.3 Add queue-routing regression coverage for job queue assignment, Horizon configuration, scheduler dispatch, and worker command configuration.

## 2. Sync recovery and diagnostics

- [x] 2.1 Add future-checkpoint detection with bounded recent-window fallback and sanitized anomaly logging.
- [x] 2.2 Extend sync health metadata with last attempt, current window, duration, counters, and mapped campaign count.
- [x] 2.3 Add tests for future cursor recovery, failure diagnostics, and automatic healthy-state recovery.

## 3. Verification and closeout

- [x] 3.1 Run focused PHPUnit coverage and Pint on modified PHP files.
- [x] 3.2 Run the available frontend build and OpenSpec validation checks.
- [x] 3.3 Review the final diff for queue isolation, secret/privacy safety, and preservation of fast local Call History reads.
- [ ] 3.4 Run browser validation when the browser environment is available, then archive the change.
