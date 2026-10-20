<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'attendance_id', 'guardian_id', 'recipient_phone', 'message', 'photo_path',
    'status', 'skip_reason', 'attempts', 'last_error', 'wamid', 'sent_at',
])]
class WaOutbox extends Model
{
    /** Menunggu dikirim (job sudah diantrekan). */
    public const string STATUS_PENDING = 'pending';

    /** Sedang diproses job (dikirim ke service wa-bot). */
    public const string STATUS_SENDING = 'sending';

    /** Diterima & terkirim oleh WhatsApp. */
    public const string STATUS_SENT = 'sent';

    /** Gagal dikirim; bisa diantre ulang oleh `wa:retry-failed`. */
    public const string STATUS_FAILED = 'failed';

    /**
     * Tidak dikirim: tidak ada wali ber-consent, nomor tidak valid,
     * statusnya bukan kehadiran, atau fitur sedang dinonaktifkan.
     */
    public const string STATUS_SKIPPED = 'skipped';

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
