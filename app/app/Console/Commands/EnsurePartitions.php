<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('partitions:ensure {--months=2 : How many months ahead to create}')]
#[Description('Create monthly partitions for the events table')]
class EnsurePartitions extends Command
{
    public function handle(): int
    {
        $current = now()->utc()->startOfMonth();
        $monthsAhead = (int) $this->option('months');

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $from = $current->copy()->addMonths($i);
            $to = $from->copy()->addMonth();
            $name = 'events_'.$from->format('Y_m');

            DB::statement(sprintf(
                "CREATE TABLE IF NOT EXISTS %s PARTITION OF events FOR VALUES FROM ('%s') TO ('%s')",
                $name,
                $from->format('Y-m-d 00:00:00+00'),
                $to->format('Y-m-d 00:00:00+00'),
            ));

            $this->info("Partition {$name} ready");
        }

        return self::SUCCESS;
    }
}
