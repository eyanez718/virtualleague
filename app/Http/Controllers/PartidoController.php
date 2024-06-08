<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VLF\Equipo;
use App\Models\VLF\Simulador\Partido;

class PartidoController extends Controller
{
    //
    public function index()
    {
        return view('vlf.simulador.index');
    }
    public function simularPartido()
    {
        $equipo1 = Equipo::find(1);
        $equipo2 = Equipo::find(2);
        
        $partido = New Partido(json_encode($equipo1->preparacionPartido()), json_encode($equipo2->preparacionPartido()));
        //return response()->json(['equipo' => $equipo1->preparacionPartido()], 200);
        $partido->simularPartido();

        //return response()->json(['partido' => $partido->toJson()], 200);
        return response()->json(['partido' => $partido->toArray()], 200);
    }
}
