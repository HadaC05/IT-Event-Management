<?php

namespace App\Console\Commands;

use App\Services\EventStatusSynchronizer;
use Illuminate\Console\Command;

class SyncEventStatuses extends Command
{
    protected $signature = 'events:sync-statuses';

    protected $description = 'Update event statuses from their scheduled start and end times';

    public function handle(EventStatusSynchronizer $synchronizer): int
    {
        $synchronizer->sync();

        return self::SUCCESS;
    }
}
