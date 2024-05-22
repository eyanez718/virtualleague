<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VLFTacticasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tacticas = [
            ['nombre' => 'Normal', 'abreviatura' => 'N'],
            ['nombre' => 'Defensiva', 'abreviatura' => 'D'],
            ['nombre' => 'Ofensiva', 'abreviatura' => 'O'],
            ['nombre' => 'Pases', 'abreviatura' => 'P'],
            ['nombre' => 'Contrataque', 'abreviatura' => 'C'],
            ['nombre' => 'Juego largo', 'abreviatura' => 'L'],
        ];
        DB::table('vlf_tacticas')->insert($tacticas);
    }
}
