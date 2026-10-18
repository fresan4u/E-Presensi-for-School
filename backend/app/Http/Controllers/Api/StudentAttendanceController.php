<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use Illuminate\Http\Request;

/**
 * Riwayat absensi milik siswa yang sedang login (portal mobile).
 *
 * `student_id` diambil dari token, bukan dari query, sehingga siswa hanya bisa
 * melihat absensinya sendiri. Filter mendukung `date`, `session`, `per_page`.
 */
class StudentAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::query()
            ->with(['status', 'method', 'session'])
            ->where('student_id', $request->user()->id);

        if ($date = $request->query('date')) {
            $query->whereDate('attendance_date', $date);
        }

        if ($sessionCode = $request->query('session')) {
            $query->whereHas('session', fn ($q) => $q->where('code', $sessionCode));
        }

        $perPage = (int) $request->query('per_page', 25) ?: 25;
        $perPage = max(1, min($perPage, 200));

        return AttendanceResource::collection(
            $query->orderByDesc('checked_at')->paginate($perPage)->withQueryString(),
        );
    }
}
