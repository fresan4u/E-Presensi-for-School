<?php

namespace App\Console\Commands;

use App\Jobs\SendWaAttendanceNotification;
use App\Models\WaOutbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('wa:retry-failed')]
#[Description('Antre ulang pesan WhatsApp outbox yang gagal atau terhenti di tengah pengiriman.')]
class WaRetryFailed extends Command
{
    public function handle(): int
    {
        if (! config('wa.enabled')) {
            $this->components->warn('Notifikasi WhatsApp nonaktif (WA_ENABLED=false) — tidak ada yang diantre ulang.');

            return self::SUCCESS;
        }

        // Job yang mati di tengah jalan (worker crash): kembalikan ke pending.
        $stuck = WaOutbox::query()
            ->where('status', WaOutbox::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get();

        // Gagal dilaporkan job: antre ulang selama masih di bawah batas.
        $failed = WaOutbox::query()
            ->where('status', WaOutbox::STATUS_FAILED)
            ->where('attempts', '<', (int) config('wa.max_attempts'))
            ->get();

        foreach ($stuck->concat($failed) as $outbox) {
            $outbox->update(['status' => WaOutbox::STATUS_PENDING]);
            SendWaAttendanceNotification::dispatch($outbox->id);
        }

        $this->components->info("Mengantre ulang {$stuck->count()} terhenti, {$failed->count()} gagal.");

        return self::SUCCESS;
    }
}
