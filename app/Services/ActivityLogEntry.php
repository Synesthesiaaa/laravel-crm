<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityLogEntry
{
    public function __construct(private ActivityLogSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function recent(array $filters = [], int $limit = 100): Collection
    {
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);

        return $query
            ->limit(min(max($limit, 1), 100))
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (Activity $activity): array => $this->format($activity));
    }

    /**
     * @return array<string, mixed>
     */
    public function format(Activity $activity): array
    {
        $event = strtolower((string) ($activity->event ?: 'action'));
        $properties = $this->sanitizer->sanitize($activity->properties?->toArray() ?? []);
        $request = is_array($properties['request'] ?? null) ? $properties['request'] : null;
        $attributes = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        $action = $this->actionFor($event, $request);
        $subject = $activity->subject;
        $causer = $activity->causer;

        return [
            'id' => (int) $activity->getKey(),
            'timestamp' => $activity->created_at?->toIso8601String(),
            'actor' => $this->actorLabel($causer),
            'actor_id' => $causer?->getKey(),
            'actor_details' => $this->actorDetails($causer),
            'event' => $event,
            'log_name' => $activity->log_name,
            'action' => $action,
            'resource' => $this->resourceLabel($subject, $activity->subject_id),
            'resource_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'resource_id' => $activity->subject_id,
            'description' => (string) $activity->description,
            'severity' => $this->severityFor($action, $request),
            'request' => $request,
            'changes' => [
                'attributes' => $attributes,
                'old' => $old,
                'diff' => $this->changeDiff($attributes, $old),
            ],
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['actor_id'])) {
            $query->where('causer_id', (int) $filters['actor_id']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', (string) $filters['event']);
        }

        if (! empty($filters['resource'])) {
            $resource = (string) $filters['resource'];
            $query->where(function (Builder $resourceQuery) use ($resource): void {
                $resourceQuery
                    ->where('subject_type', 'like', '%'.$resource.'%')
                    ->orWhere('subject_id', ctype_digit($resource) ? (int) $resource : -1);
            });
        }

        if (! empty($filters['search'])) {
            $search = '%'.addcslashes((string) $filters['search'], '%_').'%';
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('description', 'like', $search)
                    ->orWhere('log_name', 'like', $search)
                    ->orWhere('subject_type', 'like', $search)
                    ->orWhere('properties->request->path', 'like', $search)
                    ->orWhere('properties->request->route', 'like', $search);
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['to']);
        }

        if (! empty($filters['since_id'])) {
            $query->where('id', '>', (int) $filters['since_id']);
        }
    }

    private function actorLabel(mixed $causer): string
    {
        if ($causer === null) {
            return 'SYSTEM';
        }

        return (string) ($causer->full_name
            ?? $causer->name
            ?? $causer->username
            ?? 'USER #'.$causer->getKey());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actorDetails(mixed $causer): ?array
    {
        if ($causer === null) {
            return null;
        }

        return [
            'id' => $causer->getKey(),
            'username' => $causer->getAttribute('username'),
            'full_name' => $causer->getAttribute('full_name') ?? $causer->getAttribute('name'),
            'role' => $causer->getAttribute('role'),
        ];
    }

    /**
     * @param  array<string|int, mixed>  $attributes
     * @param  array<string|int, mixed>  $old
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changeDiff(array $attributes, array $old): array
    {
        $diff = [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($attributes)));

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $attributes[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $diff[(string) $key] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $diff;
    }

    private function resourceLabel(mixed $subject, ?int $subjectId): ?string
    {
        if ($subject === null) {
            return $subjectId === null ? null : '#'.$subjectId;
        }

        foreach (['name', 'full_name', 'username', 'label', 'code', 'form_code', 'setting_key', 'server_name', 'field_key'] as $attribute) {
            $value = $subject->getAttribute($attribute);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '#'.$subject->getKey();
    }

    private function actionFor(string $event, ?array $request): string
    {
        if ($event !== 'request' || $request === null) {
            return $event;
        }

        $method = strtoupper((string) ($request['method'] ?? ''));

        return $method !== '' ? $method : $event;
    }

    private function severityFor(string $action, ?array $request = null): string
    {
        if ($request !== null && array_key_exists('status', $request)) {
            $status = (int) $request['status'];

            return match (true) {
                $status >= 500 => 'error',
                $status >= 400 => 'warning',
                $status >= 200 && $status < 400 => 'success',
                default => 'info',
            };
        }

        return match (true) {
            in_array($action, ['failed', 'force_deleted'], true) => 'error',
            in_array($action, ['deleted', 'logout'], true) => 'warning',
            in_array($action, ['created', 'updated', 'restored', 'login'], true) => 'success',
            Str::contains($action, ['failed', 'error']) => 'error',
            default => 'info',
        };
    }
}
