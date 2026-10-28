<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint pengaturan hanya untuk admin. Petugas tetap bisa memakai endpoint
 * operasional lain lewat `EnsurePanelUser`, tetapi tidak boleh mengubah
 * konfigurasi global sekolah.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role?->name !== 'admin') {
            abort(403, 'Akses hanya untuk admin.');
        }

        return $next($request);
    }
}
