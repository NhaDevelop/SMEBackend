<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CloseFinishedPrograms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'programs:close-finished';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically set program status to Finished if end_date has passed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = \App\Models\Program::where('status', '!=', 'Finished')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->update(['status' => 'Finished']);

        $this->info("Closed {$count} finished programs.");
    }
}
