<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Profil siswa untuk portal mobile (login NIS + PIN).
 *
 * Sama dengan StudentResource, ditambah penanda `must_change_pin` (PIN masih
 * default = NIS sehingga wajib diganti saat login pertama). PIN itu sendiri
 * tidak pernah ikut diserialisasi.
 */
class StudentProfileResource extends StudentResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'must_change_pin' => (bool) $this->must_change_pin,
        ];
    }
}
