<?php

namespace App\Services;

use Illuminate\Support\Facades\Queue;
use Throwable;

class EmailCampaignQueueStatus
{
    /**
     * @return array{connection: string, queue: string, pending: int|null, problem: string|null}
     */
    public function inspect(): array
    {
        $connection = (string) config('email_campaigns.connection');
        $queue = (string) config('email_campaigns.queue');
        $result = [
            'connection' => $connection,
            'queue' => $queue,
            'pending' => null,
            'problem' => null,
        ];

        if ($connection === 'sync') {
            $result['problem'] = 'Bulk email needs an asynchronous database or Redis queue; the sync connection is not supported.';

            return $result;
        }

        if (config('queue.connections.'.$connection.'.driver') === 'redis' && config('database.redis.client') === 'phpredis' && ! extension_loaded('redis')) {
            $result['problem'] = 'The Redis queue is configured, but PHP does not have the Redis extension installed.';

            return $result;
        }

        try {
            $result['pending'] = Queue::connection($connection)->size($queue);
        } catch (Throwable) {
            // Do not expose queue connection URLs or secrets in the admin UI.
            $result['problem'] = 'The configured queue could not be reached. Check the server queue connection and logs.';
        }

        if ($result['problem'] === null && ! \Illuminate\Support\Facades\Cache::get('email-worker:'.$connection.':'.$queue)) {
            $result['problem'] = 'The dedicated email worker has no recent heartbeat. Start and supervise the matching connection and queue.';
        }
        if ((int) config('queue.connections.'.$connection.'.retry_after', 0) <= 60) {
            $result['problem'] = 'Email queue retry_after must exceed the 60-second recipient timeout.';
        }

        return $result;
    }
}
