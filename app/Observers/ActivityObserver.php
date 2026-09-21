<?php

namespace App\Observers;

use App\Events\ActivityLogCreated;
use App\Services\ActivityLogEntry;
use App\Services\ActivityLogSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class ActivityObserver
{
    public function __construct(
        private ActivityLogSanitizer $sanitizer,
        private ActivityLogEntry $entryFormatter,
    ) {}

    public function creating(Activity $activity): void
    {
        $properties = $activity->properties instanceof Collection
            ? $activity->properties->toArray()
            : (array) $activity->properties;

        $activity->properties = collect($this->sanitizer->sanitize($properties));
    }

    public function created(Activity $activity): void
    {
        try {
            ActivityLogCreated::dispatch(
                (int) $activity->getKey(),
                $this->entryFormatter->format($activity),
            );
        } catch (\Throwable $exception) {
            Log::channel('audit')->warning('Activity realtime broadcast failed.', [
                'activity_id' => $activity->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }
}
