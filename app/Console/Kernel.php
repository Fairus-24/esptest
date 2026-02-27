<?php

namespace App\Console;

use App\Models\Eksperimen;
use App\Services\ApplicationSimulationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\MqttSave::class,
        \App\Console\Commands\MqttSaveFile::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $retentionDays = (int) config('dashboard.retention_days', 30);
        if ($retentionDays > 0) {
            $schedule->call(function () use ($retentionDays): void {
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

        $schedule->call(function (): void {
            app(ApplicationSimulationService::class)->tick();
        })
            ->name('application-simulation-tick')
            ->everySecond()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
