<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VLFPosicionesJugadorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posiciones = [
            ['nombre' => 'Arquero', 'abreviatura' => 'ARQ'],
            ['nombre' => 'Defensor', 'abreviatura' => 'DEF'],
            ['nombre' => 'Mediocampista defensivo', 'abreviatura' => 'MCD'],
            ['nombre' => 'Mediocampista', 'abreviatura' => 'MC'],
            ['nombre' => 'Mediocampista ofensivo', 'abreviatura' => 'MCO'],
            ['nombre' => 'Delantero', 'abreviatura' => 'DEL'],
        ];
        DB::table('vlf_posiciones_jugador')->insert($posiciones);
    }
}
