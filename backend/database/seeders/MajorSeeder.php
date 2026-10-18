<?php

namespace Database\Seeders;

use App\Models\Major;
use Illuminate\Database\Seeder;

class MajorSeeder extends Seeder
{
    public function run(): void
    {
        Major::updateOrCreate(['code' => 'RPL'], ['name' => 'Rekayasa Perangkat Lunak']);
        Major::updateOrCreate(['code' => 'ANM'], ['name' => 'Animasi']);
        Major::updateOrCreate(['code' => 'DKV'], ['name' => 'Desain Komunikasi Visual']);
    }
}
