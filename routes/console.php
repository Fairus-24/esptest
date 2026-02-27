<?php

use App\Models\Eksperimen;
use App\Services\ApplicationSimulationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$retentionDays = (int) config('dashboard.retention_days', 30);
if ($retentionDays > 0) {
    Schedule::call(function () use ($retentionDays): void {
        $cutoff = now()->subDays($retentionDays);

        $deletedRows = Eksperimen::query()
            ->where(function ($query) use ($cutoff) {
                $query->where('timestamp_server', '<', $cutoff)
                    ->orWhere(function ($fallback) use ($cutoff) {
                        $fallback->whereNull('timestamp_server')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->delete();

        Log::info('Eksperimen retention prune completed.', [
            'retention_days' => $retentionDays,
            'cutoff_utc' => $cutoff->toDateTimeString(),
            'deleted_rows' => $deletedRows,
        ]);
    })
        ->name('eksperimen-retention-prune')
        ->dailyAt('02:10')
        ->withoutOverlapping();
}

Schedule::call(function (): void {
    app(ApplicationSimulationService::class)->tick();
})
    ->name('application-simulation-tick')
    ->everySecond()
    ->withoutOverlapping();
