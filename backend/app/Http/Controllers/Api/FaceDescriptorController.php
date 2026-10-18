<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFaceDescriptorRequest;
use App\Http\Resources\FaceDescriptorResource;
use App\Models\Student;
use App\Models\StudentFaceDescriptor;
use Illuminate\Http\JsonResponse;

class FaceDescriptorController extends Controller
{
    public function index(Student $student)
    {
        return FaceDescriptorResource::collection(
            $student->faceDescriptors()->orderBy('sample_no')->get(),
        );
    }

    public function store(StoreFaceDescriptorRequest $request, Student $student): JsonResponse
    {
        $data = $request->validated();
        $maxSamples = (int) config('face.samples_per_student', 3);
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

        $model = $student->faceDescriptors()->updateOrCreate(
            ['sample_no' => $sampleNo],
            [
                'descriptor' => $descriptor,
                'model_version' => $data['model_version'] ?? config('face.model_version'),
                'quality_score' => $data['quality_score'] ?? null,
                'captured_by' => $request->user()?->id,
                'is_active' => true,
            ],
        );

        return (new FaceDescriptorResource($model->load('student')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Student $student, StudentFaceDescriptor $descriptor): JsonResponse
    {
        abort_unless($descriptor->student_id === $student->id, 404);
        $descriptor->delete();

        return response()->json(['message' => 'Sampel wajah dihapus.']);
    }
}
