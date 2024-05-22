<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VLFEquiposSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $equipos = [
            [
                'nombre' => 'Racing Club',
                'id_nacionalidad' => 9,
            ],
            [
                'nombre' => 'Independiente',
                'id_nacionalidad' => 9,
            ],
        ];
        DB::table('vlf_equipos')->insert($equipos);
    }
}
