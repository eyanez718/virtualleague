<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VLTJugadoresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jugadores = [
            [
                'nombre' => "Federer, Roger",
                'id_nacionalidad' => 182,
                'id_usuario' => NULL,
                'id_superficie' => 5,
                'hab_derecha' => 9,
                'hab_reves' => 9,
                'hab_volea' => 8,
                'hab_dejada' => 9,
                'hab_velocidad' => 7,
                'hab_resistencia' => 8,
                'hab_servicio' => 8,
                'hab_potencia' => 9,
                'hab_slice' => 10,
                'hab_consistencia' => 8,
                'hab_forma' => 10,
            ],
            [
                'nombre' => "Nadal, Rafael",
                'id_nacionalidad' => 173,
                'id_usuario' => NULL,
                'id_superficie' => 2,
                'hab_derecha' => 9,
                'hab_reves' => 8,
                'hab_volea' => 8,
                'hab_dejada' => 7,
                'hab_velocidad' => 10,
                'hab_resistencia' => 10,
                'hab_servicio' => 8,
                'hab_potencia' => 8,
                'hab_slice' => 7,
                'hab_consistencia' => 9,
                'hab_forma' => 10,
            ],
        ];

        DB::table('vlt_jugadores')->insert($jugadores);
    }
}
