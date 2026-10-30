## 1. Regression contract

- [x] 1.1 Add and observe failing HTTP/workflow tests for role/owner protection, upload/preview/confirmation, checkpoints, duplicate handling, cancellation, cleanup, and safe artifacts.

## 2. Background workflow

- [x] 2.1 Add import/checkpoint/error/identity tables and private source storage.
- [x] 2.2 Adapt the shared CSV importer for bounded resumable batches while preserving CLI behavior.
- [x] 2.3 Implement transactional background processing, explicit start/cancel, safe status/report/template endpoints, dedicated queue and expiry cleanup.

## 3. User Access interface

- [x] 3.1 Add the inline upload/review/progress panel and recent runs, matching existing components and themes.
- [x] 3.2 Add resilient polling, accessible feedback, download/actions, and lifecycle cleanup.

## 4. Verification and completion

- [x] 4.1 Pass relevant PHP/JS tests, build, formatting, migration checks, and regression suite.
- [x] 4.2 Browser-validate synthetic queued imports, authorization, widths/themes, interactions, and browser health; perform Impeccable review.
- [x] 4.3 Update operator docs, synchronize specifications, and archive completed work.
