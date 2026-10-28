<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFaceDescriptorRequest;
use App\Http\Resources\FaceDescriptorResource;
use App\Models\Student;
use App\Models\StudentFaceDescriptor;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceDescriptorController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function index(Student $student)
    {
        return FaceDescriptorResource::collection(
            $student->faceDescriptors()->orderBy('sample_no')->get(),
        );
    }

    public function store(StoreFaceDescriptorRequest $request, Student $student): JsonResponse
    {
        return $this->storeFor($request, $student);
    }

    /**
     * Daftar sampel wajah milik siswa yang sedang login (portal mobile).
     */
    public function mine(Request $request)
    {
        /** @var Student $student */
        $student = $request->user();

        return FaceDescriptorResource::collection(
            $student->faceDescriptors()->orderBy('sample_no')->get(),
        );
    }

    /**
     * Registrasi wajah mandiri oleh siswa.
     *
     * `student_id` selalu diambil dari token, bukan dari URL, sehingga siswa
     * tidak bisa mendaftarkan wajah untuk akun lain.
     */
    public function storeMine(StoreFaceDescriptorRequest $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return $this->storeFor($request, $student);
    }

    /**
     * Hapus sampel wajah milik siswa yang sedang login.
     */
    public function destroyMine(Request $request, StudentFaceDescriptor $descriptor): JsonResponse
    {
        abort_unless($descriptor->student_id === $request->user()?->id, 404);

        $descriptor->delete();

        return response()->json(['message' => 'Sampel wajah dihapus.']);
    }

    public function destroy(Student $student, StudentFaceDescriptor $descriptor): JsonResponse
    {
        abort_unless($descriptor->student_id === $student->id, 404);
        $descriptor->delete();

        return response()->json(['message' => 'Sampel wajah dihapus.']);
    }

    private function storeFor(StoreFaceDescriptorRequest $request, Student $student): JsonResponse
    {
        $data = $request->validated();
        $maxSamples = $this->settings->faceSamples();
        $descriptor = array_map('floatval', $data['descriptor']);

        // Tentukan nomor sampel: pakai yang dikirim, atau slot kosong berikutnya.
        $sampleNo = $data['sample_no'] ?? null;
        if ($sampleNo === null) {
            $used = $student->faceDescriptors()->pluck('sample_no')->all();
            for ($i = 1; $i <= $maxSamples; $i++) {
                if (! in_array($i, $used, true)) {
                    $sampleNo = $i;
                    break;
                }
            }
            $sampleNo ??= $maxSamples;
        }

        // `captured_by` hanya diisi saat pendaftaran dilakukan admin/petugas.
        $officer = $request->user() instanceof User ? $request->user() : null;

        $model = $student->faceDescriptors()->updateOrCreate(
            ['sample_no' => $sampleNo],
            [
                'descriptor' => $descriptor,
                'model_version' => $data['model_version'] ?? config('face.model_version'),
                'quality_score' => $data['quality_score'] ?? null,
                'captured_by' => $officer?->id,
                'is_active' => true,
            ],
        );

        return (new FaceDescriptorResource($model->load('student')))
            ->response()
            ->setStatusCode(201);
    }
}
