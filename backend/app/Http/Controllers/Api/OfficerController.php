<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfficerRequest;
use App\Http\Requests\UpdateOfficerRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OfficerController extends Controller
{
    /** Relasi standar untuk resource petugas. */
    private function relations(): array
    {
        return ['role'];
    }

    /** Id role petugas (semua akun yang dikelola di sini wajib role ini). */
    private function roleId(): ?int
    {
        return Role::where('name', 'petugas')->value('id');
    }

    /** Batasi aksi ke akun ber-role petugas saja. */
    private function ensureOfficer(User $user): void
    {
        abort_unless($user->role?->name === 'petugas', 404);
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->with($this->relations())
            ->where('role_id', $this->roleId());

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->query('is_active') !== null && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->query('per_page', 10) ?: 10;
        $perPage = max(1, min($perPage, 100));

        return UserResource::collection(
            $query->orderBy('name')->paginate($perPage)->withQueryString(),
        );
    }

    public function store(StoreOfficerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $officer = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $this->roleId(),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return (new UserResource($officer->load($this->relations())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $officer): UserResource
    {
        $this->ensureOfficer($officer);

        return new UserResource($officer->load($this->relations()));
    }

    public function update(UpdateOfficerRequest $request, User $officer): UserResource
    {
        $this->ensureOfficer($officer);

        $data = $request->validated();

        $fields = Arr::only($data, ['name', 'email', 'is_active']);

        if (array_key_exists('is_active', $fields) && ! $fields['is_active'] && $request->user()?->id === $officer->id) {
            abort(422, 'Tidak bisa menonaktifkan akun sendiri.');
        }

        if (! empty($data['password'])) {
            $fields['password'] = $data['password'];
        }

        $officer->update($fields);

        return new UserResource($officer->load($this->relations()));
    }

    public function destroy(Request $request, User $officer): JsonResponse
    {
        $this->ensureOfficer($officer);

        if ($request->user()?->id === $officer->id) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }

        $officer->delete();

        return response()->json(['message' => 'Petugas berhasil dihapus.']);
    }
}
