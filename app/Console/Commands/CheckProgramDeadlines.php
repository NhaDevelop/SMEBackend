<?php

namespace App\Console\Commands;

use App\Models\Program;
use App\Models\Template;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckProgramDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'programs:check-deadlines
                            {--dry-run : Show what would be changed without making actual changes}';

    /**
     * The console command description.
     */
    protected $description = 'Auto-transition programs and their linked templates to Finished status when end_date has passed.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $now = Carbon::now();

        // Find all active programs whose end_date has passed and are NOT already Finished
        $expiredPrograms = Program::with('template')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now)
            ->whereNotIn('status', ['Finished'])
            ->get();

        if ($expiredPrograms->isEmpty()) {
            $this->info('✅ No programs need to be transitioned. All up to date.');
            return self::SUCCESS;
        }

        $this->info("Found {$expiredPrograms->count()} expired program(s) to finalize.");

        $finishedProgramCount = 0;
        $finishedTemplateCount = 0;

        /** @var \App\Models\Program $program */
        foreach ($expiredPrograms as $program) {
            $this->line("  → Program [{$program->id}] \"{$program->name}\" (end_date: {$program->end_date->format('Y-m-d')})");

            if (!$isDryRun) {
                // 1. Finish the Program
                $program->update(['status' => 'Finished']);
                $finishedProgramCount++;

                // 2. Finish the linked Template (if any and not already Finished)
                if ($program->template_id) {
                    \App\Models\Template::where('id', $program->template_id)
                        ->update(['status' => 'Finished']);
                    $this->line("    ↳ Template [{$program->template_id}] also set to Finished.");
                    $finishedTemplateCount++;
                }

                Log::info("Program lifecycle: Finished program [{$program->id}] \"{$program->name}\".");
            } else {
                $this->line("    [DRY-RUN] Would set program status to Finished.");
                if ($program->template && $program->template->status !== 'Finished') {
                    $this->line("    [DRY-RUN] Would also set template [{$program->template->id}] \"{$program->template->name}\" to Finished.");
                }
            }
        }

        if (!$isDryRun) {
            $this->info("✅ Done. Finished {$finishedProgramCount} program(s) and {$finishedTemplateCount} template(s).");
        } else {
            $this->warn('[DRY-RUN] No changes were made.');
        }

        return self::SUCCESS;
    }
}
