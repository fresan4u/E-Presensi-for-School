<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi endpoint portal siswa hanya untuk akun `Student`.
 *
 * Mencegah token admin/petugas dipakai di route siswa (guard `sanctum` sama).
 */
class EnsureStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof Student) {
            abort(403, 'Akses hanya untuk siswa.');
        }

        return $next($request);
    }
}
