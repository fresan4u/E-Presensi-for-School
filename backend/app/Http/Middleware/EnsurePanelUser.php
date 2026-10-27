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
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'Akses hanya untuk admin atau petugas.');
        }

        // Cek role: hanya admin atau petugas yang bisa akses endpoint panel
        $allowedRoles = ['admin', 'petugas'];
        $userRole = $user->role ? $user->role->name : null;

        if (! in_array($userRole, $allowedRoles)) {
            abort(403, 'Akses hanya untuk admin atau petugas.');
        }

        return $next($request);
    }
}
