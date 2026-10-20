<?php

namespace App\Console\Commands;

use App\Models\WaOutbox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('wa:status')]
#[Description('Tampilkan ringkasan outbox notifikasi WhatsApp dan koneksi ke service wa-bot.')]
class WaStatus extends Command
{
    public function handle(): int
    {
        $this->components->twoColumnDetail(
            'Notifikasi (WA_ENABLED)',
            config('wa.enabled') ? 'aktif' : 'nonaktif',
        );
        $this->components->twoColumnDetail('Endpoint wa-bot', (string) config('wa.url'));

        foreach ([
            WaOutbox::STATUS_PENDING => 'pending',
            WaOutbox::STATUS_SENDING => 'sending',
            WaOutbox::STATUS_SENT => 'sent',
            WaOutbox::STATUS_FAILED => 'failed',
            WaOutbox::STATUS_SKIPPED => 'skipped',
        ] as $status => $label) {
            $this->components->twoColumnDetail(
                'Outbox '.$label,
                (string) WaOutbox::query()->where('status', $status)->count(),
            );
        }

        $this->components->twoColumnDetail('Service wa-bot', $this->healthCheck());

        return self::SUCCESS;
    }

    private function healthCheck(): string
    {
        try {
            $response = Http::timeout(5)->get(rtrim((string) config('wa.url'), '/').'/health');

            if (! $response->successful()) {
                return 'merespons HTTP '.$response->status();
            }

            return $response->json('connected')
                ? 'terhubung ke WhatsApp'
                : 'berjalan tetapi belum terhubung (scan QR dulu)';
        } catch (Throwable $exception) {
            return 'tidak dapat dihubungi — '.$exception->getMessage();
        }
    }
}
