<?php

namespace Tests\Feature\VLF;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Arr;
use Tests\TestCase;
use App\Models\VLF\Simulador\Partido;
use App\Models\VLF\Simulador\EquipoPartido;
use App\Enums\VLF\EventosPartido;
use App\Enums\VLF\EstadisticasJugadorPartido;
use App\Enums\VLF\EstadisticasEquipoPartido;
use App\Enums\VLF\IndicadoresPartido;

class VLFPartidoTest extends TestCase
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
     * Prueba la creación de un partido
     */
    public function test_inic_partido(): void
    {
        $partido = New Partido(1, 2);
        
        $this->assertTrue(true);
    }

    /**
     * Prueba la simulacion de un partido
     */
    public function test_simular_partido(): void
    {
        $partido = New Partido(1, 2);
        $partido->simularPartido();
        
        $this->assertTrue(true);
    }

    /**
     * Prueba un evento de partido
     */
    public function test_evento_partido(): void
    {
        $partido = New Partido(1, 2);
        dump("EVENTO ASISTENCIA");
        $partido->quienHizoEso(1, EventosPartido::HIZO_ASISTENCIA, 2);
        dump("--------");
        dump("EVENTO TIRO");
        $partido->quienHizoEso(1, EventosPartido::HIZO_TIRO, 2);
        dump("--------");
        dump("EVENTO FALTA");
        $partido->quienHizoEso(1, EventosPartido::HIZO_FALTA, 2);
        dump("--------");
        dump("EVENTO QUITE");
        $partido->quienHizoEso(1, EventosPartido::HIZO_QUITE, 2);
        
        $this->assertTrue(true);
    }

    /**
     * Prueba obtener jugador partido
     */
    public function test_obtener_arquero_equipo(): void
    {
        $aux_equipo = new EquipoPartido(1, false);
        // PRUEBA OK
        if ($aux_equipo->getOK()) {
            $aux_jugador = $aux_equipo->obtenerArquero();
            //dump($aux_jugador);
            dump($aux_jugador->getJugador()->habilidad->habilidad_arquero * 200 + 3500);
        } else {
            dump('no se pudo cargar el equipo');
        }
        $this->assertTrue(true);
    }

    /**
     * Prueba obtener jugador partido
     */
    public function test_obtener_jugador_partido(): void
    {
        $aux_equipo = new EquipoPartido(1, false);
        // PRUEBA OK
        if ($aux_equipo->getOK()) {
            $aux_jugador = $aux_equipo->obtenerJugador(6);
            $aux_jugador->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::ASISTENCIAS);
            $aux_jugador->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::QUITES);
            $aux_jugador->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::QUITES);
            $aux_jugador->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::QUITES);
            $aux_jugador->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::GOLES);
            $aux_jugador->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::TARJETAS_ROJAS);
            dump($aux_jugador->getEstadisticas());
        } else {
            dump('no se pudo cargar el equipo');
        }
        $this->assertTrue(true);
    }

    /**
     * Prueba obtener jugador partido
     */
    public function test_estadisticas_equipo_partido(): void
    {
        $aux_equipo = new EquipoPartido(1, false);
        // PRUEBA OK
        if ($aux_equipo->getOK()) {
            $aux_equipo->getEstadisticas()->sumarEstadistica(EstadisticasEquipoPartido::GOLES);
            $aux_equipo->getEstadisticas()->sumarEstadistica(EstadisticasEquipoPartido::GOLES);
            $aux_equipo->getEstadisticas()->sumarEstadistica(EstadisticasEquipoPartido::GOLES);
            dump($aux_equipo->getEstadisticas());
        } else {
            dump('no se pudo cargar el equipo');
        }
        $this->assertTrue(true);
    }

    /**
     * Prueba el cambio de posicion de un jugador del equipo
     */
    public function test_cambiar_posicion_jugador(): void
    {
        $aux_equipo = new EquipoPartido(1, false);
        // PRUEBA OK
        if ($aux_equipo->getOK()) {
            if ($aux_equipo->cambiarPosicionJugador(26, 'DF')) {
                foreach ($aux_equipo->getJugadores() as $jugador) {
                    if ($jugador->getActivo()) {
                        dump($jugador->getPosicion());
                    }
                }
            } else {
                dump('no se pudo cambiar la posición');
            }
            
        } else {
            dump('no se pudo cargar el equipo');
        }
        $this->assertTrue(true);
    }

    /**
     * Obtener reemplazante
     */
    public function test_obtener_jugador_reemplazante(): void
    {
        $aux_equipo = new EquipoPartido(1, false);
        // PRUEBA OK
        if ($aux_equipo->getOK()) {
            if ($aux_equipo->cambiarPosicionJugador(26, 'DF')) {
                foreach ($aux_equipo->getJugadores() as $jugador) {
                    if ($jugador->getActivo()) {
                        dump($jugador->getPosicion());
                    }
                }
            } else {
                dump('no se pudo cambiar la posición');
            }
            
        } else {
            dump('no se pudo cargar el equipo');
        }
        $this->assertTrue(true);
    }

    /**
     * Obtener reemplazante
     */
    public function test_expulsar_jugador(): void
    {
        $partido = New Partido(1, 2);
        // PRUEBA OK
        $partido->imprimirJugadores(1);
        $partido->setMinuto(0);
        dump('expulsion');
        $partido->expulsarJugador(1, 1);
        $partido->imprimirJugadores(1);
        $this->assertTrue(true);
    }

    /**
     * Prueba obtener jugador partido
     */
    public function test_indicadores_partido(): void
    {
        $partido = New Partido(1, 2);
        $partido->limpiar_indicadores_lesion_tarjetas();
        dump(json_encode($partido->getIndicadorTarjetasAmarillas()));
        dump(json_encode($partido->getIndicadorTarjetasRojas()));
        dump(json_encode($partido->getIndicadorLesiones()));
        dump('------');
        //$partido->setIndicadorTarjetasAmarillas(Arr::set($partido->getIndicadorTarjetasAmarillas(), 1, 2));
        /*$aux_indicadorta = $partido->getIndicadorTarjetasAmarillas();
        $partido->setIndicadorTarjetasAmarillas(Arr::set($aux_indicadorta, 1, 2));*/
        $partido->sumarIndicador(1, IndicadoresPartido::TARJETA_AMARILLA, 1);
        //$partido->setIndicadorTarjetasRojas(Arr::set($partido->getIndicadorTarjetasRojas(), 2, 1));
        /*$aux_indicadortr = $partido->getIndicadorTarjetasRojas();
        $partido->setIndicadorTarjetasRojas(Arr::set($aux_indicadortr, 2, 1));*/
        $partido->sumarIndicador(2, IndicadoresPartido::TARJETA_ROJA, 1);
        //$partido->setIndicadorLesiones(Arr::set($partido->getIndicadorLesiones(), 1, 1));
        /*$aux_indicadorlesiones1 = $partido->getIndicadorLesiones();
        $partido->setIndicadorLesiones(Arr::set($aux_indicadorlesiones1, 1, 1));
        $aux_indicadorlesiones1 = $partido->getIndicadorLesiones();
        $partido->setIndicadorLesiones(Arr::set($aux_indicadorlesiones1, 2, 1));*/
        //$partido->setIndicadorLesiones(Arr::set($partido->getIndicadorLesiones(), 2, 1));
        $partido->sumarIndicador(1, IndicadoresPartido::LESION, 1);
        $partido->sumarIndicador(2, IndicadoresPartido::LESION, 1);

        dump(json_encode($partido->getIndicadorTarjetasAmarillas()));
        dump(json_encode($partido->getIndicadorTarjetasRojas()));
        dump(json_encode($partido->getIndicadorLesiones()));
        $this->assertTrue(true);
    }
}
