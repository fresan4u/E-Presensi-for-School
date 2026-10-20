<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sapu bersih outbox WhatsApp: antre ulang yang gagal/terhenti (tiap 5 menit).
Schedule::command('wa:retry-failed')->everyFiveMinutes();
