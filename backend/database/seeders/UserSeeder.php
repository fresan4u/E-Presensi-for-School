<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Membuat SATU akun admin tetap (kredensial dari config/presensi.php) plus
     * satu akun petugas demo. Akun petugas lain dibuat admin lewat panel
     * "Kelola Petugas"; sistem tidak menyediakan endpoint untuk menambah admin.
     */
    public function run(): void
    {
        $adminRoleId = Role::where('name', 'admin')->value('id');
        $petugasRoleId = Role::where('name', 'petugas')->value('id');

        $admin = config('presensi.admin');

        // Jamin hanya ada satu akun admin: hapus admin lain selain akun tetap.
        if ($adminRoleId) {
            User::query()
                ->where('role_id', $adminRoleId)
                ->where('email', '!=', $admin['email'])
                ->delete();
        }

        User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'role_id' => $adminRoleId,
                'is_active' => true,
            ],
        );

        $petugas = config('presensi.petugas');

        User::updateOrCreate(
            ['email' => $petugas['email']],
            [
                'name' => $petugas['name'],
                'password' => $petugas['password'],
                'role_id' => $petugasRoleId,
                'is_active' => true,
            ],
        );
    }
}
