<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class PreparationQueueStatus
{
    public function inspect(): array
    {
        $connection = (string) config('email_campaigns.preparation_connection');
        $queue = (string) config('email_campaigns.preparation_queue');
        $result = ['connection' => $connection, 'queue' => $queue, 'pending' => null, 'problem' => null];
        if (config('queue.connections.'.$connection.'.driver') === 'sync' || (int) config('queue.connections.'.$connection.'.retry_after', 0) <= 300) {
            $result['problem'] = 'Document and approval preparation needs an asynchronous queue with retry_after above 300 seconds.';

            return $result;
        }

        try {
            $result['pending'] = Queue::connection($connection)->size($queue);
        } catch (\Throwable) {
            $result['problem'] = 'The preparation queue could not be reached. Check its connection and supervised worker.';

            return $result;
        }
        if (! Cache::get('email-preparation-worker:'.$connection.':'.$queue)) {
            $result['problem'] = 'The document and approval preparation worker has no recent heartbeat. Check the matching preparation connection and queue.';
        }

        return $result;
    }
}
