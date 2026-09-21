## 1. Broadcast resilience and local runtime

- [x] 1.1 Mark `ActivityLogCreated` as implementing Laravel's `ShouldRescue` contract while preserving its after-commit dispatch, immediate delivery, channel authorization, and payload.
- [x] 1.2 Add `php artisan reverb:start` to the existing `composer dev` concurrent process group with a distinct process name.

## 2. Regression coverage

- [x] 2.1 Assert the activity broadcast event is rescuable and retains its existing broadcast contract.
- [x] 2.2 Exercise successful login and logout with an unavailable broadcaster and verify redirect, authentication state, and persisted attendance/activity behavior.

## 3. Validation and closeout

- [x] 3.1 Run the focused PHPUnit tests, Laravel Pint on modified PHP files, and Composer manifest validation.
- [x] 3.2 Sync the completed capability into the canonical OpenSpec specs and confirm the change is ready to archive.
