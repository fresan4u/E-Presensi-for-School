<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Titik pusat & radius area absensi sekolah (satu baris).
 *
 * Baris ini diubah admin lewat panel "Lokasi Sekolah". Bila belum ada baris,
 * nilai fallback diambil dari `config('presensi.school')` (env), sehingga app
 * tetap berjalan tanpa konfigurasi awal.
 */
class SchoolSetting extends Model
{
    protected $fillable = ['latitude', 'longitude', 'radius_meter'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meter' => 'integer',
        ];
    }

    /**
     * Pengaturan aktif: baris database, atau fallback config bila belum diisi.
     */
    public static function current(): self
    {
        $config = config('presensi.school');
        $setting = static::query()->first() ?? new static;

        $setting->latitude ??= (float) $config['latitude'];
        $setting->longitude ??= (float) $config['longitude'];
        $setting->radius_meter ??= (int) $config['radius_meter'];

        return $setting;
    }
}
