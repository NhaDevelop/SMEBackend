<?php

namespace App\Jobs;

use App\Models\Program;
use App\Models\ProgramEnrollment;
use App\Models\SmeProfile;
use App\Traits\AssessmentScoring;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateBatchReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AssessmentScoring;

    // Automatically retry up to 3 times if the job fails
    public int $tries = 3;

    // Kill the job if it runs longer than 10 minutes (prevents stuck jobs)
    public int $timeout = 600;

    public function __construct(
        protected string $reportKey,
        protected ?int $programId = null,
        protected int $requestedByUserId = 0
    ) {}

    public function handle(): void
    {
        Log::info("GenerateBatchReportJob started", [
            'key'       => $this->reportKey,
            'programId' => $this->programId,
        ]);

        $program = $this->programId ? Program::with('template')->find($this->programId) : null;

        $allSmeData = [];

        if ($program) {
            $smeIds = ProgramEnrollment::where('program_id', $program->id)
                ->whereNotNull('sme_id')
                ->pluck('sme_id');

            SmeProfile::with(['user', 'assessments.template', 'enrollments.program'])
                ->whereIn('id', $smeIds)
                ->chunk(100, function ($smes) use ($program, &$allSmeData) {
                    foreach ($smes as $sme) {
                        $allSmeData[] = $this->generateSmeReportData($sme, $program);
                    }
                });
        } else {
            SmeProfile::with(['user', 'assessments.template', 'enrollments.program'])
                ->chunk(100, function ($smes) use (&$allSmeData) {
                    foreach ($smes as $sme) {
                        $allSmeData[] = $this->generateSmeReportData($sme);
                    }
                });
        }

        Cache::put($this->reportKey . '_data', $allSmeData, now()->addMinutes(30));
        Cache::put($this->reportKey . '_status', 'ready', now()->addMinutes(30));

        Log::info("GenerateBatchReportJob finished", [
            'key'   => $this->reportKey,
            'count' => count($allSmeData),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateBatchReportJob failed", [
            'key'       => $this->reportKey,
            'exception' => $exception->getMessage(),
        ]);
        Cache::put($this->reportKey . '_status', 'failed', now()->addMinutes(10));
    }
}
