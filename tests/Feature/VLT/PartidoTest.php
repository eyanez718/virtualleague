<?php

namespace Tests\Feature\VLT;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\VLT\Jugador;
use App\Models\VLT\Simulador\PartidoSingles;

class PartidoTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Testeo de creación de un partido de singles
     */
    public function test_crear_partido(): void
    {
        $jugador1 = Jugador::find(1);
        $jugador2 = Jugador::find(2);
        $partido = New PartidoSingles($jugador1, $jugador2, 3);
        if (isset($partido)) {
            dump(json_encode($partido->getJugador1()->nombre . " - " . $partido->getMarcador()->getPuntuacionTotalToString(1)));
            dump(json_encode($partido->getJugador2()->nombre . " - " . $partido->getMarcador()->getPuntuacionTotalToString(2)));
            $this->assertTrue(true);    
        } else {
            $this->assertTrue(false);    
        }
    }

    /**
     * Testeo el sorteo de un saque
     */
    public function test_sortear_saque(): void
    {
        $jugador1 = Jugador::find(1);
        $jugador2 = Jugador::find(2);
        $partido = New PartidoSingles($jugador1, $jugador2, 3);
        if (isset($partido)) {
            $partido->sortearSaque();
            //dump($partido->getJugador1()->nombre);
            dump("Ganador del saque: " . $partido->obtenerJugadorAlSaque()->getNombreApellido());
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false);
        }
    }
}
