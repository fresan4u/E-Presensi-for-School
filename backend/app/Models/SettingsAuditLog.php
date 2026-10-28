<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

#[Fillable([
    'user_id', 'action', 'subject_type', 'subject_id',
    'changes', 'ip_address', 'user_agent', 'created_at',
])]
class SettingsAuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Catat satu perubahan pengaturan. `changes` sengaja berbentuk bebas
     * (old/new atau snapshot baris) supaya bisa dipakai setting skalar,
     * jadwal, maupun kalender libur.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function record(
        ?User $actor,
        string $action,
        string $subjectType,
        ?int $subjectId,
        array $changes,
        ?Request $request = null,
    ): self {
        $request ??= request();

        return static::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
