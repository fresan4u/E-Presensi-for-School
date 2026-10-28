<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\SchoolSetting;
use App\Models\SettingsAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Satu pintu baca/tulis pengaturan operasional.
 *
 * Nilai skalar disimpan di `app_settings`; titik sekolah tetap di
 * `school_settings` (satu baris). Semua nilai punya fallback ke config
 * sehingga deployment lama tetap berjalan sebelum admin menyimpan apa pun.
 */
class SettingsService
{
    private const CACHE_KEY = 'app_settings.v1';

    /**
     * Semua setting skalar dengan tipe yang sudah di-cast.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $registry = config('settings', []);

        $stored = Cache::rememberForever(self::CACHE_KEY, function (): array {
            return AppSetting::query()
                ->get()
                ->mapWithKeys(fn (AppSetting $setting) => [$setting->key => $setting->value])
                ->all();
        });

        $values = [];
        foreach ($registry as $key => $definition) {
            $values[$key] = array_key_exists($key, $stored)
                ? $this->cast($stored[$key], $definition['type'] ?? 'string')
                : ($definition['default'] ?? null);
        }

        return $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * Simpan setting skalar (flat key => value).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function update(array $values, User $actor, ?Request $request = null): array
    {
        $registry = config('settings', []);
        $values = Arr::only($values, array_keys($registry));

        if ($values === []) {
            return $this->all();
        }

        $old = $this->all();

        // Validasi juga di service supaya pemanggil non-HTTP tetap aman.
        foreach ($values as $key => $value) {
            $rules = $registry[$key]['rules'] ?? [];
            Validator::make(['value' => $value], ['value' => $rules])->validate();
        }

        DB::transaction(function () use ($values, $registry, $old, $actor, $request): void {
            $changes = [];

            foreach ($values as $key => $value) {
                $normalized = $this->normalize($value, $registry[$key]['type'] ?? 'string');

                AppSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $normalized, 'updated_by' => $actor->id],
                );

                $new = $this->cast($normalized, $registry[$key]['type'] ?? 'string');
                if (($old[$key] ?? null) !== $new) {
                    $changes[$key] = ['old' => $old[$key] ?? null, 'new' => $new];
                }
            }

            if ($changes !== []) {
                SettingsAuditLog::record($actor, 'update', 'app_settings', null, $changes, $request);
            }
        });

        $this->flush();

        return $this->all();
    }

    /**
     * Titik & radius sekolah aktif.
     *
     * @return array<string, mixed>
     */
    public function location(): array
    {
        // Baris lama bisa saja ada tetapi koordinatnya belum diisi; anggap
        // belum terkonfigurasi supaya geofence tidak memakai titik (0, 0).
        $configured = SchoolSetting::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->exists();
        $setting = SchoolSetting::current();

        return [
            'latitude' => (float) $setting->latitude,
            'longitude' => (float) $setting->longitude,
            'radius_meter' => (int) $setting->radius_meter,
            'geofence_enabled' => (bool) $setting->geofence_enabled,
            'configured' => $configured,
            'updated_at' => $setting->updated_at,
        ];
    }

    /**
     * Simpan lokasi + toggle geofence dalam satu transaksi.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateLocation(array $data, User $actor, ?Request $request = null): array
    {
        $old = $this->location();

        $setting = SchoolSetting::query()->first() ?? new SchoolSetting;
        $setting->fill($data)->save();

        $new = $this->location();
        $changes = [];

        foreach (['latitude', 'longitude', 'radius_meter', 'geofence_enabled'] as $key) {
            if (($old[$key] ?? null) !== ($new[$key] ?? null)) {
                $changes[$key] = ['old' => $old[$key] ?? null, 'new' => $new[$key] ?? null];
            }
        }

        if ($changes !== []) {
            SettingsAuditLog::record($actor, 'update', 'school_settings', $setting->id, $changes, $request);
        }

        return $new;
    }

    public function selfScanEnabled(): bool
    {
        return (bool) $this->get('attendance.self_scan_enabled', true);
    }

    public function geofenceEnabled(): bool
    {
        return (bool) ($this->location()['geofence_enabled'] ?? false);
    }

    public function faceThreshold(): float
    {
        return (float) $this->get('face.match_threshold', config('face.match_threshold', 0.6));
    }

    public function faceSamples(): int
    {
        return (int) $this->get('face.samples_per_student', config('face.samples_per_student', 3));
    }

    public function waEnabled(): bool
    {
        return (bool) $this->get('notification.wa_enabled', true);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }

    private function normalize(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }
}
