<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

class CleanIdempotencyKeysCommand extends Command
{
    protected $signature = 'idempotency:clean {--hours=24 : Delete keys older than this many hours}';

    protected $description = 'Delete expired idempotency keys';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $deleted = IdempotencyKey::query()
            ->where('created_at', '<', now()->subHours($hours))
            ->delete();

        $this->info("Deleted {$deleted} idempotency key(s) older than {$hours} hour(s).");

        return self::SUCCESS;
    }
}
