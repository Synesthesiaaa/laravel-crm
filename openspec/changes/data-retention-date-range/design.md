## Context

The retention policy stores one `cutoff_date`, and the scheduled service applies an upper-bound query to each form's `date` column. The existing feature also supports whole-record deletion and type-safe selected-field clearing. This change crosses the policy schema, request/controller, cleanup service, admin Blade view, and automated/browser tests.

The database may already contain active policies, so the migration must preserve their current cleanup scope while enabling new bounded ranges.

## Goals / Non-Goals

**Goals:**

- Store and validate an inclusive `from_date`/`to_date` range.
- Preserve existing cutoff-only policies without writing a fabricated lower bound.
- Apply the range consistently to whole-record and selected-field modes.
- Show and submit the range in the existing Super Admin retention workflow.
- Preserve existing form-specific field filtering and type-safe clearing.

**Non-Goals:**

- Relative retention periods or recurring policy types beyond the existing daily command.
- Changes to form storage tables or the meaning of their `date` column.
- New deletion modes, audit workflows, or dependencies.

## Decisions

### Rename the cutoff column and add a nullable lower bound

The migration will rename `cutoff_date` to `to_date` and add nullable `from_date`. The lower bound remains nullable only for legacy rows. A null lower bound means the existing behavior, `date <= to_date`.

This is preferred over retaining a duplicate legacy column because the policy has one clear upper-bound name after migration. It is preferred over backfilling a sentinel earliest date because a sentinel could unintentionally broaden the scope of an old policy and make its meaning opaque.

### Use inclusive query predicates

The service will add `whereDate('date', '>=', from_date)` only when a lower bound exists and will always apply `whereDate('date', '<=', to_date)`. The same query construction will feed both update and delete paths so the two destruction modes cannot drift.

### Require explicit ranges when saving policies

The request will require valid `Y-m-d` `from_date` and `to_date` values for new or edited policies and will enforce `from_date <= to_date`. Legacy rows can continue running with a null lower bound; an administrator editing one must choose a From date before saving it.

### Preserve the existing admin workflow

The current Data Retention tab, selected-form loading, mode selector, selected-field list, warnings, and one-policy-per-form upsert remain in place. Only the cutoff input and policy table columns change to show From and To. A legacy null lower bound is rendered as `Any date`.

### Test the range at both boundaries

Unit tests will cover records before, on, within, and after the range, plus legacy upper-bound behavior. Feature tests will cover request validation, persistence, legacy rendering, and the existing form-specific field filtering. Browser verification will exercise the visible inputs and policy list.

## Risks / Trade-offs

- **Column rename incompatibility** → Use Laravel's schema builder in a dedicated migration and run migration tests against the configured database.
- **Accidental legacy scope expansion** → Keep `from_date` null for migrated policies and conditionally add only the lower-bound predicate.
- **Boundary off-by-one errors** → Use inclusive `whereDate` comparisons and explicit boundary test records.
- **Legacy policy edit friction** → Clearly display `Any date` for existing rows while requiring a From date when the policy is saved.

## Migration Plan

1. Add a migration that renames `cutoff_date` to `to_date` and adds nullable `from_date`.
2. Deploy the application code that reads and writes the new names.
3. Existing policies continue using `to_date` with no lower bound; newly saved policies use both dates.
4. If rollback is required, roll back the application code first, then reverse the migration to rename `to_date` back to `cutoff_date` and remove `from_date`.

## Open Questions

None. The inclusive range semantics and legacy compatibility behavior were approved before implementation.
