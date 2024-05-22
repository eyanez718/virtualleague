<?php

namespace Tests\Feature\VLT;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\VLT\Jugador;

class JugadorTest extends TestCase
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
     * 
     */
    public function test_cargar_jugador(): void
    {
        $jugador1 = Jugador::find(1);
        $jugador2 = Jugador::find(2);
        if (isset($jugador1) && isset($jugador2)) {
            dump(json_encode($jugador1));
            dump(json_encode($jugador2));
            $this->assertTrue(true);    
        } else {
            $this->assertTrue(false);    
        }
    }

    /**
     * Prueba de nombres de jugador
     */
    public function test_nombres_jugador(): void
    {
        $jugador1 = Jugador::find(1);
        if (isset($jugador1)) {
            dump($jugador1->getNombreApellido());
            dump($jugador1->getApellidoNombre());
            dump($jugador1->getNApellido());
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false);
        }
    }
}
