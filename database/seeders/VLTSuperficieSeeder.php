<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VLTSuperficieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superficies = [
            ['nombre' => 'Indoor', 'modificador' => 4],
            ['nombre' => 'Tierra', 'modificador' => 1],
            ['nombre' => 'Hierba', 'modificador' => 5],
            ['nombre' => 'Dura', 'modificador' => 4],
            ['nombre' => 'Neutral', 'modificador' => 2]
        ];

        DB::table('vlt_superficies')->insert($superficies);
    }
}
