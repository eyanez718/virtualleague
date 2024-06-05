<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VLF\Equipo;
use App\Models\VLF\Simulador\Partido;

class PartidoController extends Controller
{
    //
    public function simularPartido()
    {
        $equipo1 = Equipo::find(1);
        $equipo2 = Equipo::find(2);
        
        $partido = New Partido(json_encode($equipo1->preparacionPartido()), json_encode($equipo2->preparacionPartido()));
        $partido->simularPartido();

        //return response()->json(['partido' => $partido->toJson()], 200);
    }
}
