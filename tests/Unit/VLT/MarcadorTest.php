<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\VLT\Simulador\Marcador;
use Illuminate\Support\Arr;

class MarcadorTest extends TestCase
{
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_marcador()
    {
        $marcador = new Marcador(3);
        dump("CREO MARCADOR");
        for ($i=1; $i <= 300; $i++) { 
            $jugadores = [1, 2];
            $aux_ganador_punto = Arr::random($jugadores);
            $marcador->sumarPunto($aux_ganador_punto);
            dump("PUNTO JUGADOR " . $aux_ganador_punto);
            if ($marcador->gameTerminado($aux_ganador_punto)) {
                dump("GAME PARA JUGADOR " . $aux_ganador_punto);
                $marcador->sumarGame($aux_ganador_punto);
                if ($marcador->setTerminado($aux_ganador_punto)) {
                    dump("SET " . $marcador->getSetActual() . " PARA JUGADOR " . $aux_ganador_punto . ": " . Arr::get($marcador->getSets1(), 'set' . $marcador->getSetActual()) . " - " . Arr::get($marcador->getSets2(), 'set' . $marcador->getSetActual()));
                    if ($marcador->partidoTerminado($aux_ganador_punto)) {
                        dump("PARTIDO PARA JUGADOR " . $aux_ganador_punto);
                        dump("");
                        dump("JUGADOR 1: " . $marcador->getPuntuacionTotalToString(1));
                        dump("JUGADOR 2: " . $marcador->getPuntuacionTotalToString(2));
                        break;
                    } else {
                        dump("PARTIDO: " . $marcador->getSetsGanados(1) . " - " . $marcador->getSetsGanados(2));
                        $marcador->iniciarNuevoSet();
                        $marcador->iniciarNuevoGame();
                    }
                } else {
                    dump("SET " . $marcador->getSetActual() . ": " . Arr::get($marcador->getSets1(), 'set' . $marcador->getSetActual()) . " - " . Arr::get($marcador->getSets2(), 'set' . $marcador->getSetActual()));
                    $marcador->iniciarNuevoGame();
                }
            } else {
                dump("GAME: " . $marcador->getPuntuacionGame(1) . " - " . $marcador->getPuntuacionGame(2));
            }
        }
        $this->assertTrue(true);
    }
}
