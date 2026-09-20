<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RealSnapshotService;
use Illuminate\Console\Command;
use Throwable;

final class RefreshSentraSnapshot extends Command
{
    protected $signature = 'sentra:snapshot-refresh';

    protected $description = 'Capture valid live hotspot, weather, and facility data into the offline SENTRA snapshot';

    public function handle(RealSnapshotService $snapshotService): int
    {
        try {
            $snapshot = $snapshotService->refresh();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->warn('Snapshot sebelumnya dipertahankan dan tidak diubah.');

            return self::FAILURE;
        }

        $this->info('Snapshot Riil berhasil diperbarui secara atomik.');
        $this->line('Waktu tangkap (UTC): '.$snapshot['metadata']['captured_at']);
        $this->line('Hotspot: '.count($snapshot['hotspots']).' | Fasilitas: '.count($snapshot['facilities']));

        return self::SUCCESS;
    }
}
