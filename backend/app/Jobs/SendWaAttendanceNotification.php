<?php

namespace App\Jobs;

use App\Models\WaOutbox;
use App\Services\WaNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SendWaAttendanceNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $outboxId) {}

    /**
     * Kirim satu baris outbox ke service `wa-bot/` (Baileys) lewat HTTP.
     *
     * Kegagalan TIDAK di-rethrow: notifikasi tidak boleh menggagalkan alur
     * absensi. Antre ulang ditangani perintah `wa:retry-failed` yang
     * dijadwalkan tiap 5 menit.
     */
    public function handle(WaNotifier $notifier): void
    {
        $outbox = WaOutbox::find($this->outboxId);

        if ($outbox === null || $outbox->status !== WaOutbox::STATUS_PENDING) {
            return;
        }

        $outbox->update([
            'status' => WaOutbox::STATUS_SENDING,
            'attempts' => $outbox->attempts + 1,
        ]);

        $payload = [
            'id' => $outbox->id,
            'to' => $outbox->recipient_phone,
            'text' => $outbox->message,
        ];

        if ($photo = $notifier->photoBase64($outbox)) {
            $payload['photo_base64'] = $photo;
        }

        try {
            $response = Http::timeout((int) config('wa.timeout'))
                ->withHeaders(['x-api-secret' => (string) config('wa.secret')])
                ->post(config('wa.url').'/send', $payload);
        } catch (Throwable $exception) {
            $outbox->update([
                'status' => WaOutbox::STATUS_FAILED,
                'last_error' => Str::limit($exception->getMessage(), 490),
            ]);

            return;
        }

        if ($response->successful()) {
            $outbox->update([
                'status' => WaOutbox::STATUS_SENT,
                'wamid' => $response->json('wamid'),
                'sent_at' => now(),
                'last_error' => null,
            ]);

            return;
        }

        $outbox->update([
            'status' => WaOutbox::STATUS_FAILED,
            'last_error' => Str::limit('HTTP '.$response->status().': '.$response->body(), 490),
        ]);
    }
}
