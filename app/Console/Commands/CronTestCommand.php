<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CronTestCommand extends Command
{
    protected $signature = 'cron:test';
    protected $description = 'Test if Laravel scheduler cron is working';

    public function handle()
    {
        $message = "CRON WORKING: " . now('Asia/Kolkata')->toDateTimeString();

        file_put_contents(
            storage_path('logs/cron_test.log'),
            $message . PHP_EOL,
            FILE_APPEND
        );

        $this->info($message);
    }
}