<?php

namespace Tests\Feature\VLF;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\VLF\Jugador;
use App\Models\VLF\Equipo;
use App\Models\Nacionalidad;

class VLFJugadorEquipoTest extends TestCase
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
     * Busca en la base un jugador de futbol
     */
    public function test_cargar_jugador(): void
    {
        $jugador1 = Jugador::with('habilidad')
                            ->with('equipo')
                            ->with('nacionalidad')
                            ->find(1);
        $jugador2 = Jugador::with('habilidad')
                            ->with('equipo')
                            ->with('nacionalidad')
                            ->find(2);
        if (isset($jugador1) && isset($jugador2)) {
            dump(json_encode($jugador1));
            dump(json_encode($jugador2));
            $this->assertTrue(true);    
        } else {
            $this->assertTrue(false);    
        }
    }

    /**
     * Busca en la base jugadores por nacionalidad
     */
    public function test_cargar_jugadores_nacionalidad(): void
    {
        $equipo1 = Nacionalidad::with('jugadores')
                            ->find(9);
        if (isset($equipo1)) {
            dump(json_encode($equipo1));
            $this->assertTrue(true);    
        } else {
            $this->assertTrue(false);    
        }
    }

    /**
     * Busca en la base un equipo de futbol
     */
    public function test_cargar_equipo(): void
    {
        $equipo1 = Equipo::with('jugadores')
                            ->with('nacionalidad')
                            ->find(1);
        if (isset($equipo1)) {
            dump(json_encode($equipo1));
            $this->assertTrue(true);    
        } else {
            $this->assertTrue(false);    
        }
    }

    /**
     * Busca en la base equipos por nacionalidad
     */
    public function test_cargar_equipos_nacionalidad(): void
    {
        $equipo1 = Nacionalidad::with('equipos')
                            ->find(9);
        if (isset($equipo1)) {
            dump(json_encode($equipo1));
            $this->assertTrue(true);    
        } else {
            $this->assertTrue(false);    
        }
    }

    public function test_equipo_json(): void
    {
        $equipo1 = Equipo::with('jugadores.habilidad')->find(1);
        //dump($equipo1->toJson());
        $equipoJson = $equipo1->toJson();
        dump($equipoJson);/*
        $equipo2 = new Equipo;
        $equipo2->fill(json_decode($equipoJson, true));
        dump(json_decode($equipoJson, true)['nombre']);
        dump(json_decode($equipoJson, true)['jugadores']);*/
    }
}
