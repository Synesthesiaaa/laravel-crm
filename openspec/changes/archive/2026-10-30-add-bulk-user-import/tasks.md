## 1. Regression contract

- [x] 1.1 Add and run failing `ImportUsersCommandTest` tests covering real CSV creation, preview, replay, validation, safe reports, and multiple batches.

## 2. Implementation

- [x] 2.1 Add the streaming importer and Artisan command with bounded duplicate detection and safe diagnostics.
- [x] 2.2 Add optional VICIdial suppression to UserService without changing existing callers.
- [x] 2.3 Add the example CSV and operator documentation.

## 3. Verification and completion

- [x] 3.1 Pass focused importer, user service/controller tests and Pint; verify synthetic scale behavior and review the diff.
- [x] 3.2 Synchronize the resulting specification, validate OpenSpec, and archive only completed work.
