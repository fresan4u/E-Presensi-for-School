<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Profil siswa untuk portal mobile (login NIS + PIN).
 *
 * Sama dengan StudentResource, ditambah:
 *   - `must_change_pin`  : PIN masih default (= NIS) sehingga wajib diganti.
 *   - `can_change_pin`   : apakah endpoint ganti PIN masih terbuka. PIN hanya
 *                          bisa diganti SEKALI; setelah `pin_changed_at`
 *                          terisi nilainya `false` selamanya.
 *
 * PIN itu sendiri tidak pernah ikut diserialisasi.
 */
class StudentProfileResource extends StudentResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'must_change_pin' => (bool) $this->must_change_pin,
            'can_change_pin' => $this->pin_changed_at === null,
        ];
    }
}
