<?php

namespace App\Jobs;

use App\Models\LeadList;
use App\Services\Leads\HopperLoaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LoadHopperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $listId,
        public int $limit = 500,
    ) {
        $this->onQueue('default');
    }

    public function handle(HopperLoaderService $loader): void
    {
        $list = LeadList::find($this->listId);
        if (! $list) {
            Log::warning('LoadHopperJob: list not found', ['list_id' => $this->listId]);

            return;
        }
        $loader->loadList($list, $this->limit);
    }
}
