<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Autentikasi panel admin & petugas (email + password) memakai token Sanctum.
 *
 * Portal siswa memakai kredensialnya sendiri (NIS + PIN, lihat
 * StudentAuthController); kedua jenis akun berbagi guard `sanctum` yang sama.
 */
class AuthController extends Controller
{
    public function login(StoreLoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()
            ->with('role')
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Akun tidak aktif. Hubungi admin.'], 403);
        }

        $token = $user->createToken('panel-'.$this->roleName($user))->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('role'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesi diakhiri.']);
    }

    /**
     * Nama role untuk penamaan token (mis. `panel-admin`), aman bila null.
     */
    private function roleName(User $user): string
    {
        return $user->role?->name ?? 'user';
    }
}
