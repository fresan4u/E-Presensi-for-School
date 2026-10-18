<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi endpoint panel hanya untuk akun `User` (admin/petugas).
 *
 * Token siswa dan petugas memakai guard `sanctum` yang sama, jadi tanpa cek ini
 * token siswa bisa menembus endpoint admin/petugas.
 */
class EnsurePanelUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            abort(403, 'Akses hanya untuk admin atau petugas.');
        }

        return $next($request);
    }
}
