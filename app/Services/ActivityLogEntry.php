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
        $action = strtolower((string) ($activity->event ?: 'action'));
        $properties = $this->sanitizer->sanitize($activity->properties?->toArray() ?? []);
        $subject = $activity->subject;
        $causer = $activity->causer;

        return [
            'id' => (int) $activity->getKey(),
            'timestamp' => $activity->created_at?->toIso8601String(),
            'actor' => $this->actorLabel($causer),
            'actor_id' => $causer?->getKey(),
            'action' => $action,
            'resource' => $this->resourceLabel($subject, $activity->subject_id),
            'resource_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'resource_id' => $activity->subject_id,
            'description' => (string) $activity->description,
            'severity' => $this->severityFor($action),
            'changes' => [
                'attributes' => $properties['attributes'] ?? [],
                'old' => $properties['old'] ?? [],
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
                    ->orWhere('subject_type', 'like', $search);
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

    private function severityFor(string $action): string
    {
        return match (true) {
            in_array($action, ['failed', 'force_deleted'], true) => 'error',
            in_array($action, ['deleted', 'logout'], true) => 'warning',
            in_array($action, ['created', 'updated', 'restored', 'login'], true) => 'success',
            Str::contains($action, ['failed', 'error']) => 'error',
            default => 'info',
        };
    }
}
