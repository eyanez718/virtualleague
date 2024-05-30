<?php

namespace App\Models\VLF\Simulador;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Config;
use App\Models\VLF\Simulador\EquipoPartido;
use App\Enums\VLF\AccionesPartido;
use App\Enums\VLF\EstadisticasJugadorPartido;
use App\Enums\VLF\TarjetasPartido;
use App\Enums\VLF\IndicadoresPartido;
use App\Enums\VLF\ComentariosPartido;

class Partido extends Model
{
    use HasFactory;

    // Configuraciones partido
    private int $bonus_local;
    private int $diferencia_goles;
    private int $cantidad_suplentes = 7;

    private array $equipos = [];

    /**
     * "Gross" game minute.
     *
     * From 1 to 45 + extra time in the first half, and from
     * 46 to 90 + extra time in the second half. Includes the
     * "extra time" added by the referee at the end of each
     * half on account of injuries/delays.
     *
     */
    private int $minuto;

    /*
     * "Net" game minute.
     *
     * From 1 to 45 in the first half, from 46 to 90 in the
     * second half - used for game / player statistics.
     *
     */
    private int $minuto_formal;

    /**
     * These indicators are used for INJ, RED and YELLOW conditionals
     * each is an array, [0] for team 0, [1] for team 1 and contains
     * the number of the player that was injured or got a card on that
     * minute, or -1 if there is no such player
     */

    private array $indicador_tarjetas_amarilla = [];
    private array $indicador_tarjetas_roja = [];
    private array $indicador_lesiones = [];

    private int $numero_jugadores;

    /**
     * Constructor
     */
    public function __construct(int $idEquipo1, int $idEquipo2) {
        $this->setNumeroJugadores(Config::get('vlf.partido.numero_jugadores') + Config::get('vlf.partido.numero_suplentes'));
        $this->inicializarEquipos($idEquipo1, $idEquipo2);
    }

    /**
     * Carga los equipos desde la base y desde la táctica, retorna true si se pueden cargar correctamente
     * 
     * @return boolean
     */
    public function inicializarEquipos(int $idEquipo1, int $idEquipo2): bool
    {
        //INICIALIZO EQUIPOS
        $this->equipos = Arr::add($this->equipos, 1, new EquipoPartido($idEquipo1, false));
        $this->equipos = Arr::add($this->equipos, 2, new EquipoPartido($idEquipo2, false));
        // PRUEBA OK
        if ($this->getEquipo(1)->getOK() && $this->getEquipo(2)->getOK()) {
            return true;
        } else {
            dump('error al cargar el equipo');
            return false;
        }
    }
    
    /**
     * 
     * The game running loop
     * 
     * 
     * The timing logic is as follows:
     * 
     * The game is divided to two structurally identical
     * halves. The difference between the halves is their
     * start times.
     * 
     * For each half, an injury time is added. This time
     * goes into the minute counter, but not into the
     * formal_minute counter (that is needed for reports)
     * 
     */
    public function simularPartido()
    {
        $aux_tiempo_juego_mitad = 45;
        $this->imprimirAlineaciones();
        // Genero variables auxiliares utilizables para el el cálculo de los minutos añadidos
        $aux_sustituciones = $aux_lesiones = $aux_faltas = 0;
        // For de cada mitad
        for ($aux_inicio_mitad = 1; $aux_inicio_mitad < 2 * $aux_tiempo_juego_mitad ; $aux_inicio_mitad += $aux_tiempo_juego_mitad) { 
            if ($aux_inicio_mitad == 1) {
                dump(ComentariosPartido::getComentario('COMENTARIO_INICIO_PARTIDO'));
                $aux_mitad = 1;
            } else {
                dump(ComentariosPartido::getComentario('COMENTARIO_MITAD_PARTIDO'));
                $aux_mitad = 2;
            }
            $aux_ultimo_minuto_mitad = $aux_inicio_mitad + $aux_tiempo_juego_mitad - 1;
            $aux_en_tiempo_anadido = false;
            
            // Juego los minutos de esta mitad
            // $aux_ultimo_minuto_mitad será incrementado por $aux_tiempo_aniadido
            // al final de la mitad
            for ($aux_minuto = $aux_minuto_formal = $aux_inicio_mitad; $aux_minuto <= $aux_ultimo_minuto_mitad; $aux_minuto++) {
                //VOLVER
                // Guardo el minuto actual
                $this->setMinuto($aux_minuto);
                // Limpio indicadores
                $this->limpiarIndicadoresLesionTarjetas();
                // Recalculo la fatiga
                $this->recalcularDatosEquipo();
                
                // Para cada equipo calculo diferentes eventos
                for ($i = 1; $i <= 2; $i++) { 
                    $this->siHayTiro($i);
                    $this->siHayFalta($i);
                    $this->randomLesion($i);

                    //score_diff = team[j].score - team[!j].score;
                    //chequeo_condicionales($i);
                }

                // Chequeo si estoy en tiempo añadido para acumular minutos de jugadores
                if (!$aux_en_tiempo_anadido) {
                    $aux_minuto_formal += 1;
                    $this->actualizarMinutosJugadores();
                }

                if ($aux_minuto == $aux_ultimo_minuto_mitad && !$aux_en_tiempo_anadido) {
                    $aux_en_tiempo_anadido = true;

                    // No debería haberse incrementado, pero por las dudas se decrementa
                    $aux_minuto_formal -= 1;

                    // Actualizo las variables auxiliares
                    $aux_sustituciones = ($this->getEquipo(1)->getSustituciones() + $this->getEquipo(2)->getSustituciones()) - $aux_sustituciones;
                    $aux_lesiones = ($this->getEquipo(1)->obtenerCantidadLesionados() + $this->getEquipo(2)->obtenerCantidadLesionados()) - $aux_lesiones;
                    $aux_faltas = ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS) + $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS)) - $aux_faltas;

                    $aux_minutos_anadidos = $this->calcularMinutosAnadidos($aux_sustituciones, $aux_lesiones, $aux_faltas);
                    $aux_ultimo_minuto_mitad += $aux_minutos_anadidos;

                    // Busco comentario
                    dump(Str::replace('{ma}', $aux_minutos_anadidos, Str::replace('{m}', $this->getMinuto(), ComentariosPartido::getComentario('COMENTARIO_TIEMPO_ANADIDO'))));
                }
            }
        }
        //////////////SUPLEMENTARIO
        if ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) == $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES)) {
            dump(ComentariosPartido::getComentario('COMENTARIO_FIN_PARTIDO'));
            $aux_tiempo_juego_mitad_suplementario = 15;
            for ($aux_inicio_mitad ; $aux_inicio_mitad < (2 * $aux_tiempo_juego_mitad_suplementario) + 90 ; $aux_inicio_mitad += $aux_tiempo_juego_mitad_suplementario) { 
                if ($aux_inicio_mitad == 91) {
                    dump(ComentariosPartido::getComentario('COMENTARIO_INICIO_SUPLEMENTARIO'));
                    $aux_mitad = 1;
                } else {
                    dump(ComentariosPartido::getComentario('COMENTARIO_MITAD_SUPLEMENTARIO'));
                    $aux_mitad = 2;
                }
                $aux_ultimo_minuto_mitad = $aux_inicio_mitad + $aux_tiempo_juego_mitad_suplementario - 1;
                $aux_en_tiempo_anadido = false;
                
                // Juego los minutos de esta mitad
                // $aux_ultimo_minuto_mitad será incrementado por $aux_tiempo_aniadido
                // al final de la mitad
                for ($aux_minuto = $aux_minuto_formal = $aux_inicio_mitad; $aux_minuto <= $aux_ultimo_minuto_mitad; $aux_minuto++) {
                    // Guardo el minuto actual
                    $this->setMinuto($aux_minuto);
                    // Limpio indicadores
                    $this->limpiarIndicadoresLesionTarjetas();
                    // Recalculo la fatiga
                    $this->recalcularDatosEquipo();
                    
                    // Para cada equipo calculo diferentes eventos
                    for ($i = 1; $i <= 2; $i++) { 
                        $this->siHayTiro($i);
                        $this->siHayFalta($i);
                        $this->randomLesion($i);
    
                        //score_diff = team[j].score - team[!j].score;
                        //chequeo_condicionales($i);
                    }
    
                    // Chequeo si estoy en tiempo añadido para acumular minutos de jugadores
                    if (!$aux_en_tiempo_anadido) {
                        $aux_minuto_formal += 1;
                        $this->actualizarMinutosJugadores();
                    }
    
                    if ($aux_minuto == $aux_ultimo_minuto_mitad && !$aux_en_tiempo_anadido) {
                        $aux_en_tiempo_anadido = true;
    
                        // No debería haberse incrementado, pero por las dudas se decrementa
                        $aux_minuto_formal -= 1;
    
                        // Actualizo las variables auxiliares
                        $aux_sustituciones = ($this->getEquipo(1)->getSustituciones() + $this->getEquipo(2)->getSustituciones()) - $aux_sustituciones;
                        $aux_lesiones = ($this->getEquipo(1)->obtenerCantidadLesionados() + $this->getEquipo(2)->obtenerCantidadLesionados()) - $aux_lesiones;
                        $aux_faltas = ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS) + $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS)) - $aux_faltas;
    
                        $aux_minutos_anadidos = $this->calcularMinutosAnadidos($aux_sustituciones, $aux_lesiones, $aux_faltas);
                        $aux_ultimo_minuto_mitad += $aux_minutos_anadidos;
    
                        // Busco comentario
                        dump(Str::replace('{ma}', $aux_minutos_anadidos, Str::replace('{m}', $this->getMinuto(), ComentariosPartido::getComentario('COMENTARIO_TIEMPO_ANADIDO'))));
                    }
                }
            }
        }
        ///////////////SUPLEMENTARIO
        ///////////////PENALES
        if ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) == $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES)) {
            dump(ComentariosPartido::getComentario('COMENTARIO_FIN_PARTIDO'));
            $this->ejecutarTandaPenales();
        }
        ///////////////PENALES
        dump(ComentariosPartido::getComentario('COMENTARIO_FIN_PARTIDO'));
        $this->imprimirReporteFinal();
    }

    /**
     * Llamada al comienzo de cada minuto para limpiar los indicadores de lesion, tarjeta amarilla y tarjeta roja
     * 
     * (Estos son usados por condicionales).
     */
    private function limpiarIndicadoresLesionTarjetas(): void
    {
        $aux_indicador_amarillas = [];
        $aux_indicador_amarillas = Arr::add($aux_indicador_amarillas, 1, 0);
        $aux_indicador_amarillas = Arr::add($aux_indicador_amarillas, 2, 0);
        $this->setIndicadorTarjetasAmarillas($aux_indicador_amarillas);
        $aux_indicador_rojas = [];
        $aux_indicador_rojas = Arr::add($aux_indicador_rojas, 1, 0);
        $aux_indicador_rojas = Arr::add($aux_indicador_rojas, 2, 0);
        $this->setIndicadorTarjetasRojas($aux_indicador_rojas);
        $aux_indicador_lesiones = [];
        $aux_indicador_lesiones = Arr::add($aux_indicador_lesiones, 1, 0);
        $aux_indicador_lesiones = Arr::add($aux_indicador_lesiones, 2, 0);
        $this->setIndicadorLesiones($aux_indicador_lesiones);
    }

    /**
     * Esta función es llamada por el bucle del partido al comienzo de cada minuto del partido
     * 
     * Esta recaulcula la fatiga del equipo
     */
    private function recalcularDatosEquipo(): void
    {
        for ($indiceEquipo = 1; $indiceEquipo <= 2; $indiceEquipo++) {
            // Recalculo la fatiga
            $this->getEquipo($indiceEquipo)->recalcularFatiga();
        }
    }

    /**
     * Llamado una vez por minuto para manejar las chances de marcar gol del equipo en ese minuto
     * 
     * @param int $indiceEquipo
     */
    private function siHayTiro(int $indiceEquipo)
    {
        $aux_tirador = 0;
        $aux_asistidor = 0;
        $aux_quitador = 0;
        $chance_quite = 0;
        $chance_asistido = 0;
        if ($indiceEquipo == 1) {
            $indiceEquipoRival = 2;
        } else {
            $indiceEquipoRival = 1;
        }
        $aux_tactica_equipo = $this->getEquipo($indiceEquipo)->getTactica();
        $aux_tactica_equipo_rival = $this->getEquipo($indiceEquipoRival)->getTactica();

        // Ocurrió una chance de gol?
        if ($this->randomp((int) $this->getEquipo($indiceEquipo)->calcularProbabilidadTiro($aux_tactica_equipo_rival))) {
            // Hay un probabilidad de 0.75 que la chance sea asistida, y 0.25 de ser una jugada individual
            if ($this->randomp(7500)) {
                $aux_asistidor = $this->quienHizoEso($indiceEquipo, AccionesPartido::HIZO_ASISTENCIA);
                $chance_asistido = 1;

                $aux_tirador = $this->quienFueAsistido($indiceEquipo, $aux_asistidor);

                // Busco comentario
                dump(Str::replace('{a}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_asistidor)->getJugador()->getNombreApellido(), Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getJugador()->getNombreApellido(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()->abreviatura, Str::replace('{m}', $this->getMinuto(), ComentariosPartido::getComentario('CHANCE_ASISTIDA'))))));
                
                // Sumo el pase clave al jugador asistidor
                $this->getEquipo($indiceEquipo)->obtenerJugador($aux_asistidor)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::PASES_CLAVE);
            } else {
                $aux_tirador = $this->quienHizoEso($indiceEquipo, AccionesPartido::HIZO_TIRO);

                $chance_asistido = 0;
                $aux_asistidor = 0;

                // Busco comentario
                dump(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getJugador()->getNombreApellido(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()->abreviatura, Str::replace('{m}', $this->getMinuto(), ComentariosPartido::getComentario('CHANCE_INDIVIDUAL')))));
            }

            $chance_quite = (int) (4000.0 * (($this->getEquipo($indiceEquipoRival)->calcularQuite($aux_tactica_equipo) * 3.0) / ($this->getEquipo($indiceEquipo)->calcularPase($aux_tactica_equipo_rival) * 2.0 + $this->getEquipo($indiceEquipo)->calcularTiro($aux_tactica_equipo_rival))));

            // Chequeo si la chance fue obstruida 
            if ($this->randomp($chance_quite)) {
                $aux_quitador = $this->quienHizoEso($indiceEquipoRival, AccionesPartido::HIZO_QUITE);
                
                // Busco comentario
                dump(Str::replace('{j}', $this->getEquipo($indiceEquipoRival)->obtenerJugador($aux_quitador)->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('QUITE')));
                
                // Sumo el quite al jugador quitador rival
                $this->getEquipo($indiceEquipoRival)->obtenerJugador($aux_quitador)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::QUITES);
            } else { // La chance no fue interceptada (quite), será un tiro al arco
                //Busco comentario
                dump(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('TIRO')));
                
                // Sumo el tiro al jugador tirador
                $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::TIROS);
                
                // Compruebo si el tiro va al arco
                if ($this->siHayTiroAlArco($indiceEquipo, $aux_tirador)) {
                    //REVISAR SI HAY QUE SUMAR ESTADISTICAS EN EL EQUIPO O OBTENER LA SUMA DE LOS JUGADORES
                    //team[a].finalshots_on++;

                    // Sumo el tiro al arco al jugador tirador
                    $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::TIROS_AL_ARCO);
                    
                    // Compruebo si el tiro termina en gol
                    if ($this->siHayGol($indiceEquipo, $aux_tirador)) {
                        // Busco comentario
                        dump(ComentariosPartido::getComentario('GOL_CONVERTIDO'));
                        
                        // Compruebo si el gol fue anulado
                        if (!$this->siGolAnulado()) {
                            //REVISAR SI HAY QUE SUMAR ESTADISTICAS EN EL EQUIPO O OBTENER LA SUMA DE LOS JUGADORES
                            //team[a].score++;

                            // Si el $aux_asistidor es el mismo que $aux_tirador, entonces no fue asistido, es un gol simple.
                            if ($chance_asistido == 1 && ($aux_asistidor != $aux_tirador)){
                                // Sumo la asistencia al jugador asistidor
                                $this->getEquipo($indiceEquipo)->obtenerJugador($aux_asistidor)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::ASISTENCIAS);
                            }

                            // Sumo el gol al jugador tirador
                            $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::GOLES);
                            
                            // Sumo el gol concedido al arquero rival
                            $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::GOLES_CONCEDIDOS);
                            
                            // Busco comentario
                            dump(Str::replace('{e1}', $this->getEquipo(1)->getEquipo()->abreviatura, Str::replace('{g1}', $this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), Str::replace('{g2}', $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), Str::replace('{e2}', $this->getEquipo(2)->getEquipo()->abreviatura, ComentariosPartido::getComentario('COMENTARIO_RESULTADO'))))));
                            
                            // reportar evento
                            /*report_event* an_event = new report_event_goal(team[a].player[shooter].name,
                                                    team[a].name, formal_minute_str().c_str());

                            report_vec.push_back(an_event);*/
                        } else {
                            // Busco comentario
                            dump(ComentariosPartido::getComentario('GOL_ANULADO'));
                        }
                    } else { // El arquero rival atajó el tiro
                        // Busco comentario
                        dump(Str::replace('{j}', $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('ATAJADA')));
                        
                        // Sumo la atajada al arquero rival
                        $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::ATAJADAS);
                    }
                } else { // El tiro fue afuera
                    // Sumo el tiro errado
                    $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::TIROS_AFUERA);
                    
                    // Busco comentario
                    dump(ComentariosPartido::getComentario('TIRO_AFUERA'));
                    
                    //REVISAR SI HAY QUE SUMAR ESTADISTICAS EN EL EQUIPO O OBTENER LA SUMA DE LOS JUGADORES
                    //team[a].finalshots_off++;
                }
            }
        }
    }

    /**
     * Cuando se generó una oportunidad para el $indiceEquipo y fue asistida por el $auxAsistidor, ¿quién recibió la asistencia?
     * 
     * Esto es casi como quienHizoEso(), pero también tiene en cuenta el lado del asistente:
     *      un jugador de su lado tiene mayores posibilidades de recibir la asistencia.
     * 
     * Cómo se hace: si el lado del tirador (elegido por quienHizoEso()) es diferente del lado del $auxAsistidor,
     * quienHizoEso() se ejecuta una vez más, pero esto sucede solo una vez. Esto aumenta las posibilidades
     * de que el jugador del mismo lado sea elegido, pero también deja una posibilidad para otros lados.
     * 
     * @param $indiceEquipo
     * @param $auxAsistidor
     * @return int
     */
    private function quienFueAsistido(int $indiceEquipo, int $auxAsistidor): int
    {
        $aux_tirador = $auxAsistidor;

        // El $auxAsistidor y el $aux_tirador deben ser diferentes,
        // entonces ejecuto cada vez que el $aux_tirador generado coincida con el $auxAsistidor
        while ($aux_tirador == $auxAsistidor) {
            $aux_tirador = $this->quienHizoEso($indiceEquipo, AccionesPartido::HIZO_TIRO);

            // Si el lado del asistidor y del tirador son diferentes, ejecuto una vez más
            if ($this->getEquipo($indiceEquipo)->obtenerJugador($auxAsistidor)->getLado() == $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getLado()) {
                $aux_tirador = $this->quienHizoEso($indiceEquipo, AccionesPartido::HIZO_TIRO);
            }
        }

        return $aux_tirador;
    }

    /**
     * Indica si el tiro va al arco según la fatiga del jugador
     * 
     * @param int $indiceEquipo
     * @param int $auxTirador
     * @return bool
     */
    private function siHayTiroAlArco(int $indiceEquipo, int $auxTirador): bool
    {
        if ($this->randomp((int) (5800.0 * $this->getEquipo($indiceEquipo)->obtenerJugador($auxTirador)->getFatiga())))
            return true;
        else
            return false;
    }

    /**
     * Dado un tiro a puerta (un tiro del equipo $indiceEquipo al arco del equipo $indiceEquipoRival), ¿fue un gol?
     * 
     * @param int $indiceEquipo
     * @param int $auxTirador
     * @return bool
     */
    private function siHayGol(int $indiceEquipo, int $auxTirador): bool
    {
        if ($indiceEquipo == 1) {
            $indiceEquipoRival = 2;
        } else {
            $indiceEquipoRival = 1;
        }
        // Factores tomados en cuenta
        // * La habilidad_tiro del tirador y la fatiga contra la habilidad_arquero del arquero rival
        // * La "mediana" es 0,35
        // * Los límites inferior y superior son 0,1 y 0,9 respectivamente
        $aux_temp = $this->getEquipo($indiceEquipo)->obtenerJugador($auxTirador)->getJugador()->habilidad->habilidad_tiro *
                    $this->getEquipo($indiceEquipo)->obtenerJugador($auxTirador)->getFatiga() * 200 -
                    $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getJugador()->habilidad->habilidad_arquero * 200 + 3500;

        if ($aux_temp > 9000) {
            $aux_temp = 9000;
        }
        if ($aux_temp < 1000) {
            $aux_temp = 1000;
        }

        if ($this->randomp((int) $aux_temp)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Indica si un gol fue anulado
     * 
     * @return bool
     */
    private function siGolAnulado(): bool
    {
        if ($this->randomp(500)) {
            return true;
        }

        return false;
    }

    /**
     * Maneja las faltas (llamado por cada minuto para cada equipo)
     * 
     * @param int $indiceEquipo
     */
    private function siHayFalta(int $indiceEquipo)
    {
        if ($indiceEquipo == 1) {
            $indiceEquipoRival = 2;
        } else {
            $indiceEquipoRival = 1;
        }
        $aux_infractor = 0;

        if ($this->randomp((int) $this->getEquipo($indiceEquipo)->calcularAgresividad() * 3 / 4)) {
            $aux_infractor = $this->quienHizoEso($indiceEquipo, AccionesPartido::HIZO_FALTA);
            
            // Busco comentario
            dump(Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()->abreviatura, Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_infractor)->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('FALTA')))));
            
            //REVISAR SI HAY QUE SUMAR ESTADISTICAS EN EL EQUIPO O OBTENER LA SUMA DE LOS JUGADORES
            //team[a].finalfouls++;         /* For final stats */

            // Sumo la falta al jugador infractor
            $this->getEquipo($indiceEquipo)->obtenerJugador($aux_infractor)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::FALTAS);
            
            $aux_infractor_arquero = false; // Indica si el infractor es el arquero
            if ($aux_infractor == $this->getEquipo($indiceEquipo)->obtenerArquero()->getJugador()->id) {
                $aux_infractor_arquero = true; // Resguardo la condición por si es expulsado, para ver si fue penal
            }
            // Calculo la chancede que la falta sea sancionada con tarjeta amarilla o tarjeta roja
            if ($this->randomp(6000)) {
                $this->anotarTarjeta($indiceEquipo, $aux_infractor, TarjetasPartido::AMARILLA);
            } else if ($this->randomp(400)) {
                $this->anotarTarjeta($indiceEquipo, $aux_infractor, TarjetasPartido::ROJA);
            } else {
                // Busco el comentario
                dump(ComentariosPartido::getComentario('ADVERTENCIA'));
            }
                
            // Compruebo si la falta fue un penal (si el arquero fue el $aux_infractor o un random)
            if ($aux_infractor_arquero || ($this->randomp(500))) {
                // Si el pateador asignado no está activo, elige al mejor tirador para patear el penal.
                if ($this->getEquipo($indiceEquipoRival)->obtenerPateadorPenales()->getActivo() == false) {
                    $this->getEquipo($indiceEquipoRival)->buscarPateadorPenales();
                }
                
                // Busco comentario
                dump(Str::replace('{j}', $this->getEquipo($indiceEquipoRival)->obtenerPateadorPenales()->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('PENAL')));
                
                // Si es penal, ¿es gol?
                if ($this->randomp(8000 + $this->getEquipo($indiceEquipoRival)->obtenerPateadorPenales()->getJugador()->habilidad->habilidad_tiro * 100 -
                    $this->getEquipo($indiceEquipo)->obtenerArquero()->getJugador()->habilidad->habilidad_arquero * 100)) {
                    // Busco el comentario
                    dump(ComentariosPartido::getComentario('GOL_CONVERTIDO'));
                    
                    //REVISAR SI HAY QUE SUMAR ESTADISTICAS EN EL EQUIPO O OBTENER LA SUMA DE LOS JUGADORES
                    //team[!a].score++;

                    // Sumo el gol al pateador rival
                    $this->getEquipo($indiceEquipoRival)->obtenerPateadorPenales()->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::GOLES);
                    
                    // Sumo el gol concedido al arquero
                    $this->getEquipo($indiceEquipo)->obtenerArquero()->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::GOLES_CONCEDIDOS);
                    
                    // Busco comentario
                    dump(Str::replace('{e1}', $this->getEquipo(1)->getEquipo()->abreviatura, Str::replace('{g1}', $this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), Str::replace('{g2}', $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), Str::replace('{e2}', $this->getEquipo(2)->getEquipo()->abreviatura, ComentariosPartido::getComentario('COMENTARIO_RESULTADO'))))));
                    
                    // reportar evento
                    /*report_event* an_event = new report_event_penalty(team[!a].player[team[!a].penalty_taker].name,
                                            team[!a].name, formal_minute_str().c_str());
                    report_vec.push_back(an_event);*/

                } else { // Si el pateador no convirtió el penal
                    // Chequeo si lo atajó el arquero o la tiró afuera
                    if ($this->randomp(7500)) { // Atajado
                        // Busco comentario
                        dump(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerArquero()->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('ATAJADA')));
                    } else { // Lo tiró afuera
                        // Busco comentario
                        dump(ComentariosPartido::getComentario('TIRO_AFUERA'));
                    }
                }
            }
        }
    }

    /**
     * Se ocupa del manejo de las TarjetasPartido y rojas del $auxInfractor del $indiceEquipo
     * 
     * @param int $indiceEquipo
     * @param int $auxInfractor
     * @param TarjetasPartido $colorTarjeta
     */
    private function anotarTarjeta(int $indiceEquipo, int $auxInfractor, TarjetasPartido $colorTarjeta)
    {
        if ($colorTarjeta == TarjetasPartido::AMARILLA) { // La tarjeta es amarilla
            // Busco comentario
            dump(ComentariosPartido::getComentario('TARJETA_AMARILLA'));

            $this->getEquipo($indiceEquipo)->obtenerJugador($auxInfractor)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS);

            // Una segunda tarjeta amarilla es igual a una tarjeta roja
            if ($this->getEquipo($indiceEquipo)->obtenerJugador($auxInfractor)->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS) == 2) {
                // Buscar comentario
                dump(ComentariosPartido::getComentario('SEGUNDA_AMARILLA'));
                
                // Expulso al jugador
                $this->expulsarJugador($indiceEquipo, $auxInfractor);

                // reportar evento
                /*report_event* an_event = new report_event_red_card(team[a].player[b].name,
                                        team[a].name, formal_minute_str().c_str());
                report_vec.push_back(an_event);*/

                // Sumo el indicador de tarjetas rojas
                $this->sumarIndicador($indiceEquipo, IndicadoresPartido::TARJETA_ROJA, $auxInfractor);
            } else {
                // Sumo el indicador de tarjetas amarillas
                $this->sumarIndicador($indiceEquipo, IndicadoresPartido::TARJETA_AMARILLA, $auxInfractor);
            }
        } else if ($colorTarjeta == TarjetasPartido::ROJA) {
            // Busco comentario
            dump(ComentariosPartido::getComentario('TARJETA_ROJA'));
            
            // Expulso al jugador
            $this->expulsarJugador($indiceEquipo, $auxInfractor);

            // reportar evento
            /*report_event* an_event = new report_event_red_card(team[a].player[b].name,
                                    team[a].name, formal_minute_str().c_str());
            report_vec.push_back(an_event);*/

            // Sumo el indicador de tarjetas rojas
            $this->sumarIndicador($indiceEquipo, IndicadoresPartido::TARJETA_ROJA, $auxInfractor);
        }
    }

    /**
     * Toma un equipo y una accion (ej: TIRO) y elige un jugador al azar (ponderado por $fuerza) que realizó esta accion.
     * 
     * Por ejemplo, para TIRO, elige a un jugador al azar según las habilidades de tiro
     * 
     * @param int $indiceEquipo
     * @param AccionesPartido $accion
     * @return int
     */
    private function quienHizoEso(int $indiceEquipo, AccionesPartido $accion): int
    {
        $aux_total = 0;
        $aux_fuerza = 0;
        $array_jugadores = [];
        $aux_tactica_equipo = $this->getEquipo($indiceEquipo)->getTactica();
        if ($indiceEquipo == 1) {
            $indiceEquipoRival = 2;
        } else {
            $indiceEquipoRival = 1;
        }
        $aux_tactica_equipo_rival = $this->getEquipo($indiceEquipoRival)->getTactica();
        $aux_indice_jugador = 0;

        // Emplea el algoritmo aleatorio ponderado. La probabilidad de que un jugador HAGA_ESO es su contribución en relación con la contribución total del equipo.
        foreach ($this->getEquipo($indiceEquipo)->getJugadores() as $jugador) {
            if ($jugador->getActivo()) {
                switch($accion) {
                    case AccionesPartido::HIZO_TIRO:
                        $aux_fuerza += $jugador->getContribucionTiro($aux_tactica_equipo, $aux_tactica_equipo_rival, $this->getEquipo($indiceEquipo)->calcularMultiplicadoresBalanceTactica($jugador->getPosicion())) * 100.0;
                        $aux_total = $this->getEquipo($indiceEquipo)->calcularTiro($aux_tactica_equipo_rival) * 100.0;
                        break;
                    case AccionesPartido::HIZO_FALTA:
                        $aux_fuerza += $jugador->getJugador()->habilidad->agresividad;
                        $aux_total = $this->getEquipo($indiceEquipo)->calcularAgresividad();
                        break;
                    case AccionesPartido::HIZO_QUITE:
                        $aux_fuerza += $jugador->getContribucionQuite($aux_tactica_equipo, $aux_tactica_equipo_rival, $this->getEquipo($indiceEquipo)->calcularMultiplicadoresBalanceTactica($jugador->getPosicion())) * 100.0;
                        $aux_total = $this->getEquipo($indiceEquipo)->calcularQuite($aux_tactica_equipo_rival) * 100.0;
                        break;
                    case AccionesPartido::HIZO_ASISTENCIA:
                        $aux_fuerza += $jugador->getContribucionPase($aux_tactica_equipo, $aux_tactica_equipo_rival, $this->getEquipo($indiceEquipo)->calcularMultiplicadoresBalanceTactica($jugador->getPosicion())) * 100.0;
                        $aux_total = $this->getEquipo($indiceEquipo)->calcularPase($aux_tactica_equipo_rival) * 100.0;
                        break;
                    default:
                        break;
                }
                $aux_indice_jugador = $aux_indice_jugador + 1;
                $array_jugadores = Arr::add($array_jugadores, $aux_indice_jugador, ['id' => $jugador->getJugador()->id, 'valor' => $aux_fuerza]);
            }
        }
        $aux_valor_random = rand(0, $aux_total);
        $aux_jugador = 0;

        $aux_limite_inferior = $aux_limite_superior = 0;
        for ($i=1; $i <= count($array_jugadores); $i++) { 
            $aux_limite_superior = Arr::get($array_jugadores, $i)['valor'];
            if ($aux_limite_inferior <= $aux_valor_random && $aux_valor_random <= $aux_limite_superior) {
                $aux_jugador = Arr::get($array_jugadores, $i)['id'];
            }
            $aux_limite_inferior = $aux_limite_superior;
        }
        return $aux_jugador;
    }

    /**
     * Guarda el $idJugador del $indiceEquipo asociado al $indicador
     * 
     * @param int $indiceEquipo
     * @param IndicadoresEquipo $indicador
     * @param int $idJugador
     */
    public function sumarIndicador(int $indiceEquipo, IndicadoresPartido $indicador, int $idJugador = 1)
    {
        switch ($indicador) {
            case IndicadoresPartido::TARJETA_AMARILLA:
                $aux_indicador_ta = $this->getIndicadorTarjetasAmarillas();
                $this->setIndicadorTarjetasAmarillas(Arr::set($aux_indicador_ta, $indiceEquipo, $idJugador));
                break;
            case IndicadoresPartido::TARJETA_ROJA:
                $aux_indicador_tr = $this->getIndicadorTarjetasRojas();
                $this->setIndicadorTarjetasRojas(Arr::set($aux_indicador_tr, $indiceEquipo, $idJugador));
                break;
            case IndicadoresPartido::LESION:
                $aux_indicador_l = $this->getIndicadorLesiones();
                $this->setIndicadorLesiones(Arr::set($aux_indicador_l, $indiceEquipo, $idJugador));
                break;
            default:
                # code...
                break;
        }
    }

    /**
     * Genera un número aleatorio entre 0 y 10000. Si el $valor generado es menor al $umbral return 1, de lo contrario retorna 0.
     * 
     * Se utiliza para "tirar dados" y comprobar si ocurrió un evento con cierta probabilidad. $valor es 0..10000; por ejemplo,
     * 2000 significa probabilidad 0,2. Entonces, cuando se da 2000, esta función simula un evento con probabilidad 0,2 y 
     * dice si sucedió (naturalmente, tiene una probabilidad de 0,2 de que suceda)
     * 
     * @param int $umbral
     * @return bool
     */
    private function randomp(int $umbral): bool
    {
        $valor = rand(0, 10000);

        if ($valor < $umbral) { // El valor calculado es menor al umbral
            return true;
        } else {
            return false;
        }
    }

    /**
     * Controla la expulsión del $idExpulsado del $indiceEquipo
     * 
     * @param int $indiceEquipo
     */
    //PRIVATIZAR
    public function expulsarJugador(int $indiceEquipo, int $idExpulsado): void
    {
        // Controlo si el expulsado es el arquero
        if ($this->getEquipo($indiceEquipo)->obtenerJugador($idExpulsado)->getPosicion() == 'AR') {
            // Quito al jugador expulsado
            $this->getEquipo($indiceEquipo)->quitarJugador($idExpulsado, 1);

            // Busco arquero entre los jugadores de campo y lo pongo de arquero
            $aux_arquero_alternativo = $this->getEquipo($indiceEquipo)->buscarReemplazante($idExpulsado, true);
            $this->getEquipo($indiceEquipo)->cambiarPosicionJugador($aux_arquero_alternativo, 'AR', ''); // La función retorna un bool, se podría mejorar usandolo
            
            // Busco comentario
            dump(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getJugador()->getNombreApellido(), Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()->abreviatura, Str::replace('{p}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getPosicion() . $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getLado(), ComentariosPartido::getComentario('CAMBIO_POSICION'))))));
            if ($this->getEquipo($indiceEquipo)->getSustituciones() < Config::get('vlf.partido.sustituciones')) { // No le quedan sustituciones al equipo
                // Busco arquero entre los suplentes y lo pongo a jugar
                $aux_suplente = $this->getEquipo($indiceEquipo)->buscarReemplazante($aux_arquero_alternativo, false);
                $this->getEquipo($indiceEquipo)->sustituirJugador($aux_arquero_alternativo, $aux_suplente, null, null, 1);
                
                // Busco comentario
                dump(Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()->abreviatura, Str::replace('{c}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getJugador()->getNombreApellido(), Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($idExpulsado)->getJugador()->getNombreApellido(), Str::replace('{p}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getPosicion() . $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getLado(), ComentariosPartido::getComentario('SUSTITUCION')))))));
            }
        } else {
            // Quito al jugador expulsado
            $this->getEquipo($indiceEquipo)->quitarJugador($idExpulsado, 1);
        }
        $this->getEquipo($indiceEquipo)->obtenerJugador($idExpulsado)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::TARJETAS_ROJAS);
    }

    /**
     * Esta función controla la aparición aleatoria de lesiones.
     * La PROBABILIDAD de que un jugador se lesione depende de un factor constante + la agresión total del equipo rival.
     * La función buscará quién resultó lesionado y lo sustituirá por jugador un en su posición.
     * 
     * @param int $indiceEquipo
     */
    private function randomLesion(int $indiceEquipo)
    {
        if ($indiceEquipo == 1) {
            $indiceEquipoRival = 2;
        } else {
            $indiceEquipoRival = 1;
        }
        $aux_lesionado = $aux_indice_lesionado = $aux_suplente = 0;
        $aux_jugador_lesionado = null;
        $aux_encontrado = false;

        if ($this->randomp((1500 + $this->getEquipo($indiceEquipoRival)->calcularAgresividad()) / 50)) { // Si alguien resultó lesionado
            //REVISAR SI HAY QUE SUMAR ESTADISTICAS EN EL EQUIPO O OBTENER LA SUMA DE LOS JUGADORES
            //++team[a].injuries;

            do { // El jugador lesionado no puede ser nº0 n.0 y debe estar reproduciéndose
                $aux_indice_lesionado = rand(1 , $this->getNumeroJugadores());
                $aux_lesionado = $this->getEquipo($indiceEquipo)->obtenerJugadorIndice($aux_indice_lesionado)->getJugador()->id;
            }
            while ($aux_indice_lesionado == 0 || $this->getEquipo($indiceEquipo)->obtenerJugador($aux_lesionado)->getActivo() == false);
            
            // Busco comentario
            dump(Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()->abreviatura, Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_lesionado)->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('LESION')))));
            
            // reportar evento
            /*report_event* an_event = new report_event_injury(team[a].player[injured].name,
                                    team[a].name, formal_minute_str().c_str());
            report_vec.push_back(an_event);*/

            $this->sumarIndicador($indiceEquipo, IndicadoresPartido::LESION, $aux_lesionado);

            /* Only 3 substitutions are allowed per team per game */
            // Busco la cantidad de sustituciones permitidas y las comparo con los cambios realizados por el equipo del $aux_lesionado
            if ($this->getEquipo($indiceEquipo)->getSustituciones() >= Config::get('vlf.partido.sustituciones')) { // No le quedan sustituciones al equipo
                //Desactivo al jugador lesionado
                $this->getEquipo($indiceEquipo)->quitarJugador($aux_lesionado, 2);
                
                // Busco comentarios
                dump(ComentariosPartido::getComentario('NO_QUEDAN_SUSTITUCIONES'));
                
                // Reviso si el lesionado es arquero
                if ($this->getEquipo($indiceEquipo)->obtenerJugador($aux_lesionado)->getPosicion() == 'AR') {
                     // El lesionado es arquero, elijo un jugador de campo para sustituirlo (parámetro false)
                    $aux_arquero_alternativo = $this->getEquipo($indiceEquipo)->buscarReemplazante($aux_lesionado);
                    
                    $this->getEquipo($indiceEquipo)->cambiarPosicionJugador($aux_arquero_alternativo, 'AR', ''); // La función retorna un bool, se podría mejorar usandolo
                    
                    // Busco comentario
                    dump(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getJugador()->getNombreApellido(), Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()->abreviatura, Str::replace('{p}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getPosicion() . $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getLado(), ComentariosPartido::getComentario('CAMBIO_POSICION'))))));
                }
            } else { // El equipo tiene sustituciones disponibles, realizo una
                $aux_suplente = $this->getEquipo($indiceEquipo)->buscarReemplazante($aux_lesionado);
                $this->getEquipo($indiceEquipo)->sustituirJugador($aux_lesionado, $aux_suplente, null, null, 2);
                
                // Busco comentario
                dump(Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()->abreviatura, Str::replace('{c}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getJugador()->getNombreApellido(), Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_lesionado)->getJugador()->getNombreApellido(), Str::replace('{p}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getPosicion() . $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getLado(), ComentariosPartido::getComentario('SUSTITUCION')))))));
            } // if (team[a].substitutions >= 3)
        } // if (randomp((1500 + team[!a].aggression)/50))
    }

    /**
     * Incrementa la estadística de minutos de todos los jugadores activos de ambos equipos
     */
    private function actualizarMinutosJugadores()
    {
        for ($i = 1; $i <= 2; $i++) { 
            foreach ($this->getEquipo($i)->getJugadores() as $jugador) {
                if ($jugador->getActivo()) { // Si el jugador está activo, sumo un minuto
                    $jugador->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::MINUTOS);
                }
            }
        }
    }

    /**
     * Recibe la cantidad de cambios, lesiones y faltas de ambos equipos y en base a ello calcula los minutos añadidos
     */
    private function calcularMinutosAnadidos(int $cantidadSustituciones, int $cantidadLesiones, int $cantidadFaltas): int
    {
        return (int) ceil($cantidadSustituciones * 0.5 + $cantidadLesiones * 0.5 + $cantidadFaltas * 0.5);
    }


    /**
     * 
     */
    private function ejecutarTandaPenales()
    {
        dump(ComentariosPartido::getComentario('COMENTARIO_TANDA_PENALES'));
        $aux_pateadores_equipo_1 = [];
        $aux_pateadores_equipo_2 = [];
        // Obtengo los pateadores de penales de cada equipo
        for ($i = 1; $i <= 2; $i++) { 
            foreach ($this->getEquipo($i)->getJugadores() as $jugador) {
                if ($i == 1) {
                    if ($jugador->getActivo()) {
                        $aux_pateadores_equipo_1 = Arr::set($aux_pateadores_equipo_1, $jugador->getJugador()->id, $jugador->getJugador()->habilidad->habilidad_tiro);
                    }
                } else {
                    if ($jugador->getActivo()) {
                        $aux_pateadores_equipo_2 = Arr::set($aux_pateadores_equipo_2, $jugador->getJugador()->id, $jugador->getJugador()->habilidad->habilidad_tiro);
                    }
                }
                
            }
        }
        $aux_pateadores_equipo_1 = Arr::sortDesc($aux_pateadores_equipo_1);
        $aux_pateadores_equipo_2 = Arr::sortDesc($aux_pateadores_equipo_2);
        $aux_pateadores_final_1 = [];
        $aux_pateadores_final_2 = [];
        $aux_cantidad_pateadores = 0;
        if (count($aux_pateadores_equipo_1) == count($aux_pateadores_equipo_2)) {
            $aux_cantidad_pateadores = count($aux_pateadores_equipo_1);
        } elseif (count($aux_pateadores_equipo_1) < count($aux_pateadores_equipo_2)) {
            $aux_cantidad_pateadores = count($aux_pateadores_equipo_1);
        } else {
            $aux_cantidad_pateadores = count($aux_pateadores_equipo_2);
        }
        $aux_contador = 0;
        foreach ($aux_pateadores_equipo_1 as $key => $value) {
            if ($aux_contador < $aux_cantidad_pateadores) {
                $aux_contador += 1;
                $aux_pateadores_final_1 = Arr::add($aux_pateadores_final_1, $aux_contador, $key);
            }
        }
        $aux_contador = 0;
        foreach ($aux_pateadores_equipo_2 as $key => $value) {
            if ($aux_contador < $aux_cantidad_pateadores) {
                $aux_contador += 1;
                $aux_pateadores_final_2 = Arr::add($aux_pateadores_final_2, $aux_contador, $key);
            }
        }
        $aux_goles_equipo_1 = $aux_goles_equipo_2 = 0;
        /* Main penalties loop 
        Each team takes 5 penalties (the shootout will stop if
        it will be obvious that one team won at some stage before
        the end, ie. one team leading 4-1 etc.) 
        */
        $auxNumeroPenal = 0;
        for ($auxNumeroPenal = 1; $auxNumeroPenal <= 5; $auxNumeroPenal++) {
            // Busco el comentario
            dump(Str::replace('{n}', $auxNumeroPenal, ComentariosPartido::getComentario('RONDA_TANTA_PENALES')));
            for ($auxIndiceEquipo = 1; $auxIndiceEquipo <= 2; $auxIndiceEquipo++) {
                if ($auxIndiceEquipo == 1) {
                    //dump(Arr::get($aux_pateadores_final_1, $auxNumeroPenal));
                    if ($this->ejecutarPenal($auxIndiceEquipo, Arr::get($aux_pateadores_final_1, $auxNumeroPenal))) {
                        $aux_goles_equipo_1 += 1;
                    }
                } else {
                    //dump(Arr::get($aux_pateadores_final_2, $auxNumeroPenal));
                    if ($this->ejecutarPenal($auxIndiceEquipo, Arr::get($aux_pateadores_final_2, $auxNumeroPenal))) {
                        $aux_goles_equipo_2 += 1;
                    }
                }
                // Busco comentario
                dump(Str::replace('{e1}', $this->getEquipo(1)->getEquipo()->abreviatura, Str::replace('{g1}', $aux_goles_equipo_1, Str::replace('{g2}', $aux_goles_equipo_2, Str::replace('{e2}', $this->getEquipo(2)->getEquipo()->abreviatura, ComentariosPartido::getComentario('COMENTARIO_RESULTADO'))))));
                
                /* Special condition for stopping the PKs 
                Algorithm: if after a certain kick, the amount of kicks 
                left for team A is less than the score B - A            
                there is no point to proceed with the PKs, as           
                team A loses anyway.                                    
                */
                $aux_diferencia_goles = ($aux_goles_equipo_1 - $aux_goles_equipo_2);
                if (
                    (($auxIndiceEquipo == 1) && (($aux_diferencia_goles > 5 - ($auxNumeroPenal-1)) || ((-$aux_diferencia_goles) > 4 - ($auxNumeroPenal-1))))
                        ||
                    (($auxIndiceEquipo == 2) && (($aux_diferencia_goles > 4 - ($auxNumeroPenal-1)) || ((-$aux_diferencia_goles) > 4 - ($auxNumeroPenal-1))))
                ) {
                    goto frenarPenales;
                }
            }
        }
        frenarPenales:

        /* If there is still a draw after 5 penalties by each team,
           both teams will take one penalty at a time until one team
           will lead (after an even amount of shots was taken by each team).
        */
        if ($aux_diferencia_goles == 0) {
            /* Flag indicating when penalties can be stopped */
            $aux_partido_terminado = false;
        
            /* Until the game is decided (one team leads after both teams
            took the same amount of shots)
            */
            while (!$aux_partido_terminado) {
                // Busco el comentario
                dump(Str::replace('{n}', $auxNumeroPenal, ComentariosPartido::getComentario('RONDA_TANTA_PENALES')));
                for ($auxIndiceEquipo = 1; $auxIndiceEquipo <= 2; $auxIndiceEquipo++) {
                    if ($auxIndiceEquipo == 1) {
                        if ($this->ejecutarPenal($auxIndiceEquipo, Arr::get($aux_pateadores_final_1, $auxNumeroPenal))) {
                            $aux_goles_equipo_1 += 1;
                        }
                    } else {
                        if ($this->ejecutarPenal($auxIndiceEquipo, Arr::get($aux_pateadores_final_2, $auxNumeroPenal))) {
                            $aux_goles_equipo_2 += 1;
                        }
                    }
                    // Busco comentario
                    dump(Str::replace('{e1}', $this->getEquipo(1)->getEquipo()->abreviatura, Str::replace('{g1}', $aux_goles_equipo_1, Str::replace('{g2}', $aux_goles_equipo_2, Str::replace('{e2}', $this->getEquipo(2)->getEquipo()->abreviatura, ComentariosPartido::getComentario('COMENTARIO_RESULTADO'))))));
                }
                $aux_diferencia_goles = $aux_goles_equipo_1 - $aux_goles_equipo_2;
                // Chequeo si la tanda de penales fue terminado
                if ($aux_diferencia_goles != 0) {
                    $aux_partido_terminado = true;
                } else {
                    $aux_partido_terminado = false;
                
                    /* prepare next players in each team */
                    if ($auxNumeroPenal == $aux_cantidad_pateadores)
                        $auxNumeroPenal = 1;
                    else {
                        $auxNumeroPenal++;
                    }
                }
            }
        }
        if ($aux_diferencia_goles > 0) {
            //fprintf(comm, "\n%s", the_commentary().rand_comment("WONPENALTYSHOOTOUT", team[0].name).c_str());
            // Busco el comentario
            dump(Str::replace('{e}', $this->getEquipo(1)->getEquipo()->nombre, ComentariosPartido::getComentario('GANADOR_TANTA_PENALES')));
        } else {
            //fprintf(comm, "\n%s", the_commentary().rand_comment("WONPENALTYSHOOTOUT", team[1].name).c_str());
            // Busco el comentario
            dump(Str::replace('{e}', $this->getEquipo(2)->getEquipo()->nombre, ComentariosPartido::getComentario('GANADOR_TANTA_PENALES')));
        }
    }

    /**
     * 
    */
    private function ejecutarPenal(int $indiceEquipo, int $idJugador): bool
    {
        if ($indiceEquipo == 1) {
            $indiceEquipoRival = 2;
        } else {
            $indiceEquipoRival = 1;
        }
        //fprintf(comm, "\n%s", the_commentary().rand_comment("PENALTY", PenaltyTaker[nTeam][nPenaltyNum].name).c_str());
        // Busco el comentario
        dump(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($idJugador)->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('PENAL')));
        // Chequeo si el penal fue convertido
        //if ($this->randomp(8000 + PenaltyTaker[nTeam][nPenaltyNum].sh*100 - team[!nTeam].player[team[!nTeam].current_gk].st*100)) {
        if ($this->randomp(8000 + $this->getEquipo($indiceEquipo)->obtenerJugador($idJugador)->getJugador()->habilidad->habilidad_tiro * 100 -
                $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getJugador()->habilidad->habilidad_arquero * 100)) {
            //fprintf(comm, "%s", the_commentary().rand_comment("GOAL").c_str());;
            // Busco el comentario
            dump(ComentariosPartido::getComentario('GOL_CONVERTIDO'));
            return true;
            //PenScore[nTeam]++;
            /*fprintf(comm, "\n          ...  %s %d-%d %s...", team[0].name, PenScore[0],
                PenScore[1],  team[1].name);*/
        } else {
            $rnd = rand(1, 10);
            if ($rnd < 5) {
                dump(Str::replace('{j}', $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getJugador()->getNombreApellido(), ComentariosPartido::getComentario('ATAJADA')));
                /*fprintf(comm, "%s", the_commentary().rand_comment("SAVE", 
                                    team[!nTeam].player[team[!nTeam].current_gk].name).c_str());*/
            } else {
                dump(ComentariosPartido::getComentario('TIRO_AFUERA'));
                /*fprintf(comm, "%s", the_commentary().rand_comment("OFFTARGET", 
                                    team[!nTeam].player[team[!nTeam].current_gk].name).c_str());*/
            }
            return false;
        }
    }

    /**
     * Imprime las alineaciones iniciales en el reporte del partido
     */
    private function imprimirAlineaciones(): void
    {
        dump(ComentariosPartido::getComentario('COMENTARIO_ALINEACIONES_PARTIDO'));
        dump(Str::padLeft($this->getEquipo(1)->getEquipo()->abreviatura, 40) . ' - ' . $this->getEquipo(2)->getEquipo()->abreviatura);
        dump(Str::padLeft($this->getEquipo(1)->obtenerAlineacionNumerica(), 40) . ' - ' . $this->getEquipo(2)->obtenerAlineacionNumerica());
        $auxAlineacionEquipo1 = $this->getEquipo(1)->obtenerJugadoresIniciales(1);
        $auxAlineacionEquipo2 = $this->getEquipo(2)->obtenerJugadoresIniciales(2);
        for ($i = 1; $i <= count($auxAlineacionEquipo1); $i++) { 
            dump(Str::padLeft(Arr::get($auxAlineacionEquipo1, $i), 40) . ' - ' . Arr::get($auxAlineacionEquipo2, $i));
        }
        dump('');
    }

    /**
     * Imprime las estadísticas finales del partido en el reporte del partido
     */
    private function imprimirReporteFinal(): void
    {
        dump('');
        dump('Resultado final: ' . $this->getEquipo(1)->getEquipo()->abreviatura . ' (' . $this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) . ') - (' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) . ') ' . $this->getEquipo(2)->getEquipo()->abreviatura);
        if ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) == $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES)) {
            dump('Partido empatado');
        } elseif ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) > $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES)) {
            dump('Ganó ' . $this->getEquipo(1)->getEquipo()->nombre);
        } else {
            dump('Ganó ' . $this->getEquipo(2)->getEquipo()->nombre);
        }
        dump('');
        $this->imprimirEstadisticasEquipos();
        dump('');
        $this->imprimirEstadisticasJugadores();
    }

    /**
     * Imprime las estadísticas finales del equipo en el reporte del partido
     */
    private function imprimirEstadisticasEquipos(): void
    {
        dump(ComentariosPartido::getComentario('COMENTARIO_ESTADISTICAS_EQUIPOS_PARTIDO'));
        dump(Str::padLeft($this->getEquipo(1)->getEquipo()->abreviatura, 27) . ' - ' . $this->getEquipo(2)->getEquipo()->abreviatura);
        dump(Str::padRight('Goles:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES));
        dump(Str::padRight('Tiros:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS));
        dump(Str::padRight('Tiros al arco:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AL_ARCO), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AL_ARCO));
        dump(Str::padRight('Tiros afuera:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AFUERA), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AFUERA));
        dump(Str::padRight('Faltas:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS));
        dump(Str::padRight('Tarjetas amarillas:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS));
        dump(Str::padRight('Tarjetas rojas:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_ROJAS), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_ROJAS));
        dump(Str::padRight('Cambios realizados:', 25) . Str::padLeft($this->getEquipo(1)->getSustituciones(), 2) . ' - ' . $this->getEquipo(2)->getSustituciones());
    }

    /**
     * Imprime las estadísticas de los jugadores en el reporte del partido
     */
    private function imprimirEstadisticasJugadores()
    {
        dump(ComentariosPartido::getComentario('COMENTARIO_ESTADISTICAS_JUGADORES_PARTIDO'));
        for ($i = 1; $i <= 2; $i++) { 
            dump('Estadísticas ' . $this->getEquipo($i)->getEquipo()->nombre);
            dump(
                Str::padRight('Nombre', 40) .
                Str::padLeft('MIN', 4) .
                Str::padLeft('ATA', 4) .
                Str::padLeft('GOC', 4) .
                Str::padLeft('QUI', 4) .
                Str::padLeft('PAS', 4) .
                Str::padLeft('TIR', 4) .
                Str::padLeft('TAA', 4) .
                Str::padLeft('TAF', 4) .
                Str::padLeft('GOL', 4) .
                Str::padLeft('ASI', 4) .
                Str::padLeft('FAL', 4) .
                Str::padLeft('TAM', 4) .
                Str::padLeft('TAR', 4)
            );
            foreach ($this->getEquipo($i)->getJugadores() as $jugador) {
                dump(
                    Str::padRight($jugador->getJugador()->getNombreApellido(), 40) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::MINUTOS), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::ATAJADAS), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::GOLES_CONCEDIDOS), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::QUITES), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::PASES_CLAVE), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::TIROS), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AL_ARCO), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AFUERA), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::ASISTENCIAS), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS), 4) .
                    Str::padLeft($jugador->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_ROJAS), 4)
                );
            }
        }
    }


    /**
     *  GETTERS Y SETTERS
     */
    public function getNumeroJugadores(): int
    {
        return $this->numero_jugadores;
    }

    public function setNumeroJugadores(int $numeroJugadores)
    {
        $this->numero_jugadores = $numeroJugadores;
    }

    public function getEquipo(int $idEquipo): EquipoPartido
    {
        //return $this->equipos[$idEquipo];
        return Arr::get($this->equipos, $idEquipo);
    }
    public function getMinuto(): int
    {
        return $this->minuto;
    }
    public function setMinuto(int $minuto)
    {
        $this->minuto = $minuto;
    }
    public function getIndicadorTarjetasAmarillas(): array
    {
        return $this->indicador_tarjetas_amarilla;
    }
    public function setIndicadorTarjetasAmarillas(array $indicadorTarjetasAmarilla)
    {
        $this->indicador_tarjetas_amarilla = $indicadorTarjetasAmarilla;
    }
    public function getIndicadorTarjetasRojas(): array
    {
        return $this->indicador_tarjetas_roja;
    }
    public function setIndicadorTarjetasRojas(array $indicadorTarjetasRoja)
    {
        $this->indicador_tarjetas_roja = $indicadorTarjetasRoja;
    }
    public function getIndicadorLesiones(): array
    {
        return $this->indicador_lesiones;
    }
    public function setIndicadorLesiones(array $indicadorLesiones)
    {
        $this->indicador_lesiones = $indicadorLesiones;
    }
}
