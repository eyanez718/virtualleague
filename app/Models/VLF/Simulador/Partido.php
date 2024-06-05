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
use Response;

class Partido //extends Model
{
    use HasFactory;

    /**
     * Array de los equipos del partido
     */
    private array $equipos = [];

    /**
     * Minuto "bruto" de juego
     *
     * De 1 a 45 + prórroga en la primera parte, y de 46 a 90 + prórroga en la segunda parte.
     * Incluye el "tiempo extra" añadido por el árbitro al final de cada tiempo por lesiones/retrasos.
     */
    private int $minuto;

    /*
     * Minuto "neto" de juego
     *
     * De 1 a 45 en la primera mitad, de 46 a 90 en la segunda mitad: se utiliza para estadísticas de juego/jugador.
     */
    private int $minuto_formal;

    /**
     * Estos indicadores se utilizan para los condicionales LESIONES, TARJETAS ROJAS y TARJETAS AMARILLAS,
     * cada uno es una matriz, [1] para el equipo 1, [2] para el equipo 2 y contiene el número del jugador que se lesionó o recibió una tarjeta en ese minuto,
     * o -1 si no existe tal jugador
     */
    private array $indicador_tarjetas_amarilla = [];
    private array $indicador_tarjetas_roja = [];
    private array $indicador_lesiones = [];

    private int $numero_jugadores;

    /**
     * Guarda los comentarios que surgen a lo largo del partido
     */
    private array $reporte_partido = [];

    /**
     * Constructor del partido
     * 
     * @param int $idEquipo1
     * @param int $idEquipo2
     */
    /*public function __construct(int $idEquipo1, int $idEquipo2)
    {
        $this->setNumeroJugadores(Config::get('vlf.partido.numero_jugadores') + Config::get('vlf.partido.numero_suplentes'));
        $this->inicializarEquipos($idEquipo1, $idEquipo2);
    }*/

    /**
     * Constructor del partido "generalizado"
     */
    public function __construct($equipo1, $equipo2)
    {
        $this->setNumeroJugadores(Config::get('vlf.partido.numero_jugadores') + Config::get('vlf.partido.numero_suplentes'));
        $this->inicializarEquipos(json_decode($equipo1, true), json_decode($equipo2, true));
        $this->setMinuto(0);
    }

    /**
     * Carga los equipos desde la base y desde la táctica, retorna true si se pueden cargar correctamente
     * 
     * @param int $idEquipo1
     * @param int $idEquipo2
     * @return boolean
     */
    /*private function inicializarEquipos(int $idEquipo1, int $idEquipo2): bool
    {
        // Inicializo equipos
        $this->equipos = Arr::add($this->equipos, 1, new EquipoPartido($idEquipo1, false));
        $this->equipos = Arr::add($this->equipos, 2, new EquipoPartido($idEquipo2, false));
        if ($this->getEquipo(1)->getOK() && $this->getEquipo(2)->getOK()) {
            return true;
        } else {
            dump('error al cargar el equipo');
            return false;
        }
    }*/
    private function inicializarEquipos(array $equipo1, array $equipo2)
    {
        $this->setEquipo(1, new EquipoPartido($equipo1, false));
        $this->setEquipo(2, new EquipoPartido($equipo2, false));
    }
    
    /**
     * El bucle de ejecución del partido
     * 
     * La lógica del tiempo es la siguiente:
     * 
     * El juego se divide en dos mitades estructuralmente idénticas. La diferencia entre las mitades son sus minutos de inicio.
     * 
     * Por cada tiempo se suma un tiempo de descuento. Este tiempo va al contador de minutos,
     * pero no al contador de minutos formales (que es necesario para los informes).
     */
    public function simularPartido(): void
    {
        $aux_tiempo_juego_mitad = 45;
        $this->reportarAlineaciones();
        // Genero variables auxiliares utilizables para el el cálculo de los minutos añadidos
        $aux_sustituciones = $aux_lesiones = $aux_faltas = 0;
        // For de cada mitad
        for ($aux_inicio_mitad = 1; $aux_inicio_mitad < 2 * $aux_tiempo_juego_mitad ; $aux_inicio_mitad += $aux_tiempo_juego_mitad) { 
            if ($aux_inicio_mitad == 1) {
                // Guardo comentario
                $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_INICIO_PARTIDO'));
                $aux_mitad = 1;
            } else {
                // Guardo comentario
                $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_MITAD_PARTIDO'));
                $aux_mitad = 2;
            }
            $aux_ultimo_minuto_mitad = $aux_inicio_mitad + $aux_tiempo_juego_mitad - 1;
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

                    // Guardo comentario
                    $this->guardarComentario(Str::replace('{ma}', $aux_minutos_anadidos, Str::replace('{m}', $this->getMinuto(), ComentariosPartido::getComentario('COMENTARIO_TIEMPO_ANADIDO'))));
                }
            }
        } // Fin de partido
        // Compruebo si el partido terminó empatado y está habilitado el suplementario
        if ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) == $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) && Config::get('vlf.partido.suplementario')) {
            // Guardo comentario
            $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_FIN_PARTIDO'));
            $aux_tiempo_juego_mitad_suplementario = 15;
            for ($aux_inicio_mitad ; $aux_inicio_mitad < (2 * $aux_tiempo_juego_mitad_suplementario) + 90 ; $aux_inicio_mitad += $aux_tiempo_juego_mitad_suplementario) { 
                if ($aux_inicio_mitad == 91) {
                    // Guardo comentario
                    $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_INICIO_SUPLEMENTARIO'));
                    $aux_mitad = 1;
                } else {
                    // Guardo comentario
                    $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_MITAD_SUPLEMENTARIO'));
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
    
                        // Guardo comentario
                        $this->guardarComentario(Str::replace('{ma}', $aux_minutos_anadidos, Str::replace('{m}', $this->getMinuto(), ComentariosPartido::getComentario('COMENTARIO_TIEMPO_ANADIDO'))));
                    }
                }
            }
        } // Fin de suplementario
        // Compruebo si el partido terminó empatado y están habilitados los penales
        if ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) == $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) && Config::get('vlf.partido.penales')) {
            // Guardo comentario
            $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_FIN_PARTIDO'));
            $this->ejecutarTandaPenales();
        } // Fin de penales
        // Guardo comentario
        $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_FIN_PARTIDO'));
        // Guardo en el reporte del partido las estadísticas finales
        $this->reportarEstadisticasFinales();
        // Imprimo el reporte del partido
        $this->imprimirReportePartido();
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

                // Guardo comentario
                $this->guardarComentario(Str::replace('{a}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_asistidor)->getNombreApellido(), Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getNombreApellido(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getAbreviatura(), Str::replace('{m}', $this->getMinuto(), ComentariosPartido::getComentario('CHANCE_ASISTIDA'))))));
                
                // Sumo el pase clave al jugador asistidor
                $this->getEquipo($indiceEquipo)->obtenerJugador($aux_asistidor)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::PASES_CLAVE);
            } else {
                $aux_tirador = $this->quienHizoEso($indiceEquipo, AccionesPartido::HIZO_TIRO);

                $chance_asistido = 0;
                $aux_asistidor = 0;

                // Guardo comentario
                $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getNombreApellido(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getAbreviatura(), Str::replace('{m}', $this->getMinuto(), ComentariosPartido::getComentario('CHANCE_INDIVIDUAL')))));
            }

            $chance_quite = (int) (4000.0 * (($this->getEquipo($indiceEquipoRival)->calcularQuite($aux_tactica_equipo) * 3.0) / ($this->getEquipo($indiceEquipo)->calcularPase($aux_tactica_equipo_rival) * 2.0 + $this->getEquipo($indiceEquipo)->calcularTiro($aux_tactica_equipo_rival))));

            // Chequeo si la chance fue obstruida 
            if ($this->randomp($chance_quite)) {
                $aux_quitador = $this->quienHizoEso($indiceEquipoRival, AccionesPartido::HIZO_QUITE);
                
                // Guardo comentario
                $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipoRival)->obtenerJugador($aux_quitador)->getNombreApellido(), ComentariosPartido::getComentario('QUITE')));
                
                // Sumo el quite al jugador quitador rival
                $this->getEquipo($indiceEquipoRival)->obtenerJugador($aux_quitador)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::QUITES);
            } else { // La chance no fue interceptada (quite), será un tiro al arco
                // Guardo comentario
                $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getNombreApellido(), ComentariosPartido::getComentario('TIRO')));
                
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
                        // Guardo comentario
                        $this->guardarComentario(ComentariosPartido::getComentario('GOL_CONVERTIDO'));
                        
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
                            
                            // Guardo comentario
                            $this->guardarComentario(Str::replace('{e1}', $this->getEquipo(1)->getAbreviatura(), Str::replace('{g1}', $this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), Str::replace('{g2}', $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), Str::replace('{e2}', $this->getEquipo(2)->getAbreviatura(), ComentariosPartido::getComentario('COMENTARIO_RESULTADO'))))));
                            
                            // reportar evento
                            /*report_event* an_event = new report_event_goal(team[a].player[shooter].name,
                                                    team[a].name, formal_minute_str().c_str());

                            report_vec.push_back(an_event);*/
                        } else {
                            // Guardo comentario
                            $this->guardarComentario(ComentariosPartido::getComentario('GOL_ANULADO'));
                        }
                    } else { // El arquero rival atajó el tiro
                        // Guardo comentario
                        $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getNombreApellido(), ComentariosPartido::getComentario('ATAJADA')));
                        
                        // Sumo la atajada al arquero rival
                        $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::ATAJADAS);
                    }
                } else { // El tiro fue afuera
                    // Sumo el tiro errado
                    $this->getEquipo($indiceEquipo)->obtenerJugador($aux_tirador)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::TIROS_AFUERA);
                    
                    // Guardo comentario
                    $this->guardarComentario(ComentariosPartido::getComentario('TIRO_AFUERA'));
                    
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
        $aux_temp = $this->getEquipo($indiceEquipo)->obtenerJugador($auxTirador)->getJugador()['habilidad']['habilidad_tiro'] *
                    $this->getEquipo($indiceEquipo)->obtenerJugador($auxTirador)->getFatiga() * 200 -
                    $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getJugador()['habilidad']['habilidad_arquero'] * 200 + 3500;

        if ($aux_temp > 9000) { // Como máximo $aux_temp puede ser 9000
            $aux_temp = 9000;
        }
        if ($aux_temp < 1000) { // Como mínimo $aux_temp puede ser 1000
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
    private function siHayFalta(int $indiceEquipo): void
    {
        if ($indiceEquipo == 1) {
            $indiceEquipoRival = 2;
        } else {
            $indiceEquipoRival = 1;
        }
        $aux_infractor = 0;

        if ($this->randomp((int) $this->getEquipo($indiceEquipo)->calcularAgresividad() * 3 / 4)) {
            $aux_infractor = $this->quienHizoEso($indiceEquipo, AccionesPartido::HIZO_FALTA);
            
            // Guardo comentario
            $this->guardarComentario(Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getAbreviatura(), Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_infractor)->getNombreApellido(), ComentariosPartido::getComentario('FALTA')))));
            
            //REVISAR SI HAY QUE SUMAR ESTADISTICAS EN EL EQUIPO O OBTENER LA SUMA DE LOS JUGADORES
            //team[a].finalfouls++;         /* For final stats */

            // Sumo la falta al jugador infractor
            $this->getEquipo($indiceEquipo)->obtenerJugador($aux_infractor)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::FALTAS);
            
            $aux_infractor_arquero = false; // Indica si el infractor es el arquero
            if ($aux_infractor == $this->getEquipo($indiceEquipo)->obtenerArquero()['id']) {
                $aux_infractor_arquero = true; // Resguardo la condición por si es expulsado, para ver si fue penal
            }
            // Calculo la chancede que la falta sea sancionada con tarjeta amarilla o tarjeta roja
            if ($this->randomp(6000)) {
                $this->anotarTarjeta($indiceEquipo, $aux_infractor, TarjetasPartido::AMARILLA);
            } else if ($this->randomp(400)) {
                $this->anotarTarjeta($indiceEquipo, $aux_infractor, TarjetasPartido::ROJA);
            } else {
                // Guardo comentario
                $this->guardarComentario(ComentariosPartido::getComentario('ADVERTENCIA'));
            }
                
            // Compruebo si la falta fue un penal (si el arquero fue el $aux_infractor o un random)
            if ($aux_infractor_arquero || ($this->randomp(500))) {
                // Si el pateador asignado no está activo, elige al mejor tirador para patear el penal.
                if ($this->getEquipo($indiceEquipoRival)->obtenerPateadorPenales()->getActivo() == false) {
                    $this->getEquipo($indiceEquipoRival)->buscarPateadorPenales();
                }
                
                // Guardo comentario
                $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipoRival)->obtenerPateadorPenales()->getNombreApellido(), ComentariosPartido::getComentario('PENAL')));
                
                // Si es penal, ¿es gol?
                if ($this->randomp(8000 + $this->getEquipo($indiceEquipoRival)->obtenerPateadorPenales()->getJugador()['habilidad']['habilidad_tiro'] * 100 -
                    $this->getEquipo($indiceEquipo)->obtenerArquero()->getJugador()['habilidad']['habilidad_arquero'] * 100)) {
                    // Guardo comentario
                    $this->guardarComentario(ComentariosPartido::getComentario('GOL_CONVERTIDO'));
                    
                    //REVISAR SI HAY QUE SUMAR ESTADISTICAS EN EL EQUIPO O OBTENER LA SUMA DE LOS JUGADORES
                    //team[!a].score++;

                    // Sumo el gol al pateador rival
                    $this->getEquipo($indiceEquipoRival)->obtenerPateadorPenales()->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::GOLES);
                    
                    // Sumo el gol concedido al arquero
                    $this->getEquipo($indiceEquipo)->obtenerArquero()->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::GOLES_CONCEDIDOS);
                    
                    // Guardo comentario
                    $this->guardarComentario(Str::replace('{e1}', $this->getEquipo(1)->getEquipo()['abreviatura'], Str::replace('{g1}', $this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), Str::replace('{g2}', $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), Str::replace('{e2}', $this->getEquipo(2)->getEquipo()['abreviatura'], ComentariosPartido::getComentario('COMENTARIO_RESULTADO'))))));
                    
                    // reportar evento
                    /*report_event* an_event = new report_event_penalty(team[!a].player[team[!a].penalty_taker].name,
                                            team[!a].name, formal_minute_str().c_str());
                    report_vec.push_back(an_event);*/

                } else { // Si el pateador no convirtió el penal
                    // Chequeo si lo atajó el arquero o la tiró afuera
                    if ($this->randomp(7500)) { // Atajado
                        // Guardo comentario
                        $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerArquero()->getNombreApellido(), ComentariosPartido::getComentario('ATAJADA')));
                    } else { // Lo tiró afuera
                        // Guardo comentario
                        $this->guardarComentario(ComentariosPartido::getComentario('TIRO_AFUERA'));
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
    private function anotarTarjeta(int $indiceEquipo, int $auxInfractor, TarjetasPartido $colorTarjeta): void
    {
        if ($colorTarjeta == TarjetasPartido::AMARILLA) { // La tarjeta es amarilla
            // Guardo comentario
            $this->guardarComentario(ComentariosPartido::getComentario('TARJETA_AMARILLA'));

            $this->getEquipo($indiceEquipo)->obtenerJugador($auxInfractor)->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS);

            // Una segunda tarjeta amarilla es igual a una tarjeta roja
            if ($this->getEquipo($indiceEquipo)->obtenerJugador($auxInfractor)->getEstadisticas()->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS) == 2) {
                // Guardo comentario
                $this->guardarComentario(ComentariosPartido::getComentario('SEGUNDA_AMARILLA'));
                
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
            // Guardo comentario
            $this->guardarComentario(ComentariosPartido::getComentario('TARJETA_ROJA'));
            
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
        foreach ($this->getEquipo($indiceEquipo)->getJugadoresConvocados() as $jugador) {
            if ($jugador->getActivo()) {
                switch($accion) {
                    case AccionesPartido::HIZO_TIRO:
                        $aux_fuerza += $jugador->getContribucionTiro($aux_tactica_equipo, $aux_tactica_equipo_rival, $this->getEquipo($indiceEquipo)->calcularMultiplicadoresBalanceTactica($jugador->getPosicion())) * 100.0;
                        $aux_total = $this->getEquipo($indiceEquipo)->calcularTiro($aux_tactica_equipo_rival) * 100.0;
                        break;
                    case AccionesPartido::HIZO_FALTA:
                        $aux_fuerza += $jugador->getJugador()['habilidad']['agresividad'];
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
                $array_jugadores = Arr::add($array_jugadores, $aux_indice_jugador, ['id' => $jugador->getJugador()['id'], 'valor' => $aux_fuerza]);
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
    private function sumarIndicador(int $indiceEquipo, IndicadoresPartido $indicador, int $idJugador = 1): void
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
     * @param int $idExpulsado
     */
    private function expulsarJugador(int $indiceEquipo, int $idExpulsado): void
    {
        // Controlo si el expulsado es el arquero
        if ($this->getEquipo($indiceEquipo)->obtenerJugador($idExpulsado)->getPosicion() == 'AR') {
            // Quito al jugador expulsado
            $this->getEquipo($indiceEquipo)->quitarJugador($idExpulsado, 1);

            // Busco arquero entre los jugadores de campo y lo pongo de arquero
            $aux_arquero_alternativo = $this->getEquipo($indiceEquipo)->buscarReemplazante($idExpulsado, true);
            $this->getEquipo($indiceEquipo)->cambiarPosicionJugador($aux_arquero_alternativo, 'AR', ''); // La función retorna un bool, se podría mejorar usandolo
            
            // Guardo comentario
            $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getNombreApellido(), Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()['abreviatura'], Str::replace('{p}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getPosicion() . $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getLado(), ComentariosPartido::getComentario('CAMBIO_POSICION'))))));
            if ($this->getEquipo($indiceEquipo)->getSustituciones() < Config::get('vlf.partido.sustituciones')) { // No le quedan sustituciones al equipo
                // Busco arquero entre los suplentes y lo pongo a jugar
                $aux_suplente = $this->getEquipo($indiceEquipo)->buscarReemplazante($aux_arquero_alternativo, false);
                $this->getEquipo($indiceEquipo)->sustituirJugador($aux_arquero_alternativo, $aux_suplente, null, null, 1);
                
                // Guardo comentario
                $this->guardarComentario(Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()['abreviatura'], Str::replace('{c}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getNombreApellido(), Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($idExpulsado)->getNombreApellido(), Str::replace('{p}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getPosicion() . $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getLado(), ComentariosPartido::getComentario('SUSTITUCION')))))));
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
    private function randomLesion(int $indiceEquipo): void
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
                $aux_lesionado = $this->getEquipo($indiceEquipo)->obtenerJugadorIndice($aux_indice_lesionado)->getJugador()['id'];
            }
            while ($aux_indice_lesionado == 0 || $this->getEquipo($indiceEquipo)->obtenerJugador($aux_lesionado)->getActivo() == false);
            
            // Guardo comentario
            $this->guardarComentario(Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getAbreviatura(), Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_lesionado)->getNombreApellido(), ComentariosPartido::getComentario('LESION')))));
            
            // reportar evento
            /*report_event* an_event = new report_event_injury(team[a].player[injured].name,
                                    team[a].name, formal_minute_str().c_str());
            report_vec.push_back(an_event);*/

            $this->sumarIndicador($indiceEquipo, IndicadoresPartido::LESION, $aux_lesionado);

            // Busco la cantidad de sustituciones permitidas y las comparo con los cambios realizados por el equipo del $aux_lesionado
            if ($this->getEquipo($indiceEquipo)->getSustituciones() >= Config::get('vlf.partido.sustituciones')) { // No le quedan sustituciones al equipo
                //Desactivo al jugador lesionado
                $this->getEquipo($indiceEquipo)->quitarJugador($aux_lesionado, 2);
                
                // Guardo comentario
                $this->guardarComentario(ComentariosPartido::getComentario('NO_QUEDAN_SUSTITUCIONES'));
                
                // Reviso si el lesionado es arquero
                if ($this->getEquipo($indiceEquipo)->obtenerJugador($aux_lesionado)->getPosicion() == 'AR') {
                     // El lesionado es arquero, elijo un jugador de campo para sustituirlo (parámetro false)
                    $aux_arquero_alternativo = $this->getEquipo($indiceEquipo)->buscarReemplazante($aux_lesionado);
                    
                    $this->getEquipo($indiceEquipo)->cambiarPosicionJugador($aux_arquero_alternativo, 'AR', ''); // La función retorna un bool, se podría mejorar usandolo
                    
                    // Guardo comentario
                    $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getNombreApellido(), Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getEquipo()['abreviatura'], Str::replace('{p}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getPosicion() . $this->getEquipo($indiceEquipo)->obtenerJugador($aux_arquero_alternativo)->getLado(), ComentariosPartido::getComentario('CAMBIO_POSICION'))))));
                }
            } else { // El equipo tiene sustituciones disponibles, realizo una
                $aux_suplente = $this->getEquipo($indiceEquipo)->buscarReemplazante($aux_lesionado);
                $this->getEquipo($indiceEquipo)->sustituirJugador($aux_lesionado, $aux_suplente, null, null, 2);
                
                // Guardo comentario
                $this->guardarComentario(Str::replace('{m}', $this->getMinuto(), Str::replace('{e}', $this->getEquipo($indiceEquipo)->getAbreviatura(), Str::replace('{c}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getNombreApellido(), Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_lesionado)->getNombreApellido(), Str::replace('{p}', $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getPosicion() . $this->getEquipo($indiceEquipo)->obtenerJugador($aux_suplente)->getLado(), ComentariosPartido::getComentario('SUSTITUCION')))))));
            }
        }
    }

    /**
     * Incrementa la estadística de minutos de todos los jugadores activos de ambos equipos
     */
    private function actualizarMinutosJugadores(): void
    {
        for ($i = 1; $i <= 2; $i++) { 
            foreach ($this->getEquipo($i)->getJugadoresConvocados() as $jugador) {
                if ($jugador->getActivo()) { // Si el jugador está activo, sumo un minuto
                    $jugador->getEstadisticas()->sumarEstadistica(EstadisticasJugadorPartido::MINUTOS);
                }
            }
        }
    }

    /**
     * Recibe la cantidad de cambios, lesiones y faltas de ambos equipos y en base a ello calcula los minutos añadidos
     * 
     * @param int $cantidadSustituciones
     * @param int $cantidadLesiones
     * @param int $cantidadFaltas
     * @return int
     */
    private function calcularMinutosAnadidos(int $cantidadSustituciones, int $cantidadLesiones, int $cantidadFaltas): int
    {
        return (int) ceil($cantidadSustituciones * 0.5 + $cantidadLesiones * 0.5 + $cantidadFaltas * 0.5);
    }

    /**
     * Simula una tanda completa de penales
     */
    private function ejecutarTandaPenales(): void
    {
        // Guardo comentario
        $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_TANDA_PENALES'));
        $aux_cantidad_pateadores = 0;
        // Busco los pateadores de penales
        $aux_pateadores_equipo_1 = $this->buscarPateadoresParaTandaPenales(1);
        $aux_pateadores_equipo_2 = $this->buscarPateadoresParaTandaPenales(2);
        $aux_goles_equipo_1 = $aux_goles_equipo_2 = 0;
        // Cuento la cantidad de pateadores, buscarPateadoresParaTandaPenales() retorna la misma cantidad para ambos equipos
        $aux_cantidad_pateadores = count($aux_pateadores_equipo_1);
        /**
         * Bucle principal de penales
         * Cada equipo patea 5 penales (la tanda se detendrá si es obvio que un equipo ganó en algún momento antes del final,
         * es decir, un equipo lidera 4-1, etc.)
         */
        $auxNumeroPenal = 0;
        for ($auxNumeroPenal = 1; $auxNumeroPenal <= 5; $auxNumeroPenal++) {
            // Guardo comentario
            $this->guardarComentario(Str::replace('{n}', $auxNumeroPenal, ComentariosPartido::getComentario('RONDA_TANTA_PENALES')));
            for ($auxIndiceEquipo = 1; $auxIndiceEquipo <= 2; $auxIndiceEquipo++) {
                if ($auxIndiceEquipo == 1) {
                    if ($this->ejecutarPenal($auxIndiceEquipo, Arr::get($aux_pateadores_equipo_1, $auxNumeroPenal))) {
                        $aux_goles_equipo_1 += 1;
                    }
                } else {
                    if ($this->ejecutarPenal($auxIndiceEquipo, Arr::get($aux_pateadores_equipo_2, $auxNumeroPenal))) {
                        $aux_goles_equipo_2 += 1;
                    }
                }
                // Guardo comentario
                $this->guardarComentario(Str::replace('{e1}', $this->getEquipo(1)->getAbreviatura(), Str::replace('{g1}', $aux_goles_equipo_1, Str::replace('{g2}', $aux_goles_equipo_2, Str::replace('{e2}', $this->getEquipo(2)->getAbreviatura(), ComentariosPartido::getComentario('COMENTARIO_RESULTADO'))))));
                
                /**
                 * Condición especial para detener la tanda de penales
                 * Algoritmo: si después de un determinado tiro, la cantidad de tiros que le quedan al equipo A
                 * es menor que el puntaje de B - A no tiene sentido continuar con los enales, ya que el equipo A pierde de todos modos.
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
        /**
         * Si todavía hay un empate después de 5 penales pateados por cada equipo,
         * ambos equipos ejecutarán un penal a la vez hasta que un equipo saque ventaja (después de que cada equipo haya realizado una cantidad igual de tiros).
         */
        if ($aux_diferencia_goles == 0) {
            // Flag que indica cuando los penales finalizan
            $aux_partido_terminado = false;
            // Mientras la tanda de penales no termine (un equipo gana después de que ambos equipos realizaron la misma cantidad de tiros)
            while (!$aux_partido_terminado) {
                // Guardo comentario
                $this->guardarComentario(Str::replace('{n}', $auxNumeroPenal, ComentariosPartido::getComentario('RONDA_TANTA_PENALES')));
                for ($auxIndiceEquipo = 1; $auxIndiceEquipo <= 2; $auxIndiceEquipo++) {
                    if ($auxIndiceEquipo == 1) {
                        if ($this->ejecutarPenal($auxIndiceEquipo, Arr::get($aux_pateadores_equipo_1, $auxNumeroPenal))) {
                            $aux_goles_equipo_1 += 1;
                        }
                    } else {
                        if ($this->ejecutarPenal($auxIndiceEquipo, Arr::get($aux_pateadores_equipo_2, $auxNumeroPenal))) {
                            $aux_goles_equipo_2 += 1;
                        }
                    }
                    // Guardo comentario
                    $this->guardarComentario(Str::replace('{e1}', $this->getEquipo(1)->getEquipo()['abreviatura'], Str::replace('{g1}', $aux_goles_equipo_1, Str::replace('{g2}', $aux_goles_equipo_2, Str::replace('{e2}', $this->getEquipo(2)->getEquipo()['abreviatura'], ComentariosPartido::getComentario('COMENTARIO_RESULTADO'))))));
                }
                $aux_diferencia_goles = $aux_goles_equipo_1 - $aux_goles_equipo_2;
                // Chequeo si la tanda de penales fue terminado
                if ($aux_diferencia_goles != 0) {
                    $aux_partido_terminado = true;
                } else {
                    $aux_partido_terminado = false;
                
                    // Preparo los siguientes pateadores de cada equipo
                    if ($auxNumeroPenal == $aux_cantidad_pateadores)
                        $auxNumeroPenal = 1;
                    else {
                        $auxNumeroPenal++;
                    }
                }
            }
        }
        if ($aux_diferencia_goles > 0) {
            // Guardo comentario
            $this->guardarComentario(Str::replace('{e}', $this->getEquipo(1)->getNombre(), ComentariosPartido::getComentario('GANADOR_TANTA_PENALES')));
        } else {
            // Guardo comentario
            $this->guardarComentario(Str::replace('{e}', $this->getEquipo(2)->getNombre(), ComentariosPartido::getComentario('GANADOR_TANTA_PENALES')));
        }
    }

    /**
     * Retorna un array con los pateadores del $idEquipo para la tanda de penales, controlando el máximo de pateadores y ordenandolos según habilidad de tiro
     * 
     * @param int $idEquipo
     * @return array
     */
    private function buscarPateadoresParaTandaPenales(int $idEquipo): array
    {
        $aux_pateadores_equipo_1 = [];
        $aux_pateadores_equipo_2 = [];
        // Obtengo los pateadores de penales de cada equipo
        for ($i = 1; $i <= 2; $i++) { 
            foreach ($this->getEquipo($i)->getJugadoresConvocados() as $jugador) {
                if ($i == 1) {
                    if ($jugador->getActivo()) {
                        $aux_pateadores_equipo_1 = Arr::set($aux_pateadores_equipo_1, $jugador->getJugador()['id'], $jugador->getJugador()['habilidad']['habilidad_tiro']);
                    }
                } else {
                    if ($jugador->getActivo()) {
                        $aux_pateadores_equipo_2 = Arr::set($aux_pateadores_equipo_2, $jugador->getJugador()['id'], $jugador->getJugador()['habilidad']['habilidad_tiro']);
                    }
                }
            }
        }
        // Controlo las cantidades de pateadores de cada equipo
        $aux_cantidad_pateadores = 0;
        if (count($aux_pateadores_equipo_1) == count($aux_pateadores_equipo_2)) {
            $aux_cantidad_pateadores = count($aux_pateadores_equipo_1);
        } elseif (count($aux_pateadores_equipo_1) < count($aux_pateadores_equipo_2)) {
            $aux_cantidad_pateadores = count($aux_pateadores_equipo_1);
        } else {
            $aux_cantidad_pateadores = count($aux_pateadores_equipo_2);
        }
        // Ordeno los pateadores del equipo que deseo
        $aux_pateadores_ordenados = [];
        if ($idEquipo == 1) {
            $aux_pateadores_ordenados = Arr::sortDesc($aux_pateadores_equipo_1);
        } else {
            $aux_pateadores_ordenados = Arr::sortDesc($aux_pateadores_equipo_2);
        }
        // Filtro los pateadores según la cantidad necesaria
        $aux_pateadores_final = [];
        $aux_contador = 0;
        foreach ($aux_pateadores_ordenados as $key => $value) {
            if ($aux_contador < $aux_cantidad_pateadores) {
                $aux_contador += 1;
                $aux_pateadores_final = Arr::add($aux_pateadores_final, $aux_contador, $key);
            }
        }
        return $aux_pateadores_final;
    }

    /**
     * Simula la ejecución de un penal del $indiceEquipo realizado por el $idJugador, retorna true si es gol
     * 
     * @param int $indiceEquipo
     * @param int $idJugador
     * @return bool
    */
    private function ejecutarPenal(int $indiceEquipo, int $idJugador): bool
    {
        if ($indiceEquipo == 1) {
            $indiceEquipoRival = 2;
        } else {
            $indiceEquipoRival = 1;
        }
        // Guardo comentario
        $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipo)->obtenerJugador($idJugador)->getNombreApellido(), ComentariosPartido::getComentario('PENAL')));
        // Chequeo si el penal fue convertido
        if ($this->randomp(8000 + $this->getEquipo($indiceEquipo)->obtenerJugador($idJugador)->getJugador()['habilidad']['habilidad_tiro'] * 100 -
            $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getJugador()['habilidad']['habilidad_arquero'] * 100)) {
            // Guardo comentario
            $this->guardarComentario(ComentariosPartido::getComentario('GOL_CONVERTIDO'));
            return true;
        } else {
            $rnd = rand(1, 10);
            if ($rnd < 5) {
                // Guardo comentario
                $this->guardarComentario(Str::replace('{j}', $this->getEquipo($indiceEquipoRival)->obtenerArquero()->getNombreApellido(), ComentariosPartido::getComentario('ATAJADA')));
            } else {
                //Guardo comentario
                $this->guardarComentario(ComentariosPartido::getComentario('TIRO_AFUERA'));
            }
            return false;
        }
    }

    /**
     * Guarda un comentario del partido en el reporte del mismo
     * 
     * @param string $comentario
     */
    private function guardarComentario(string $comentario): void
    {
        $this->setReportePartido(Arr::add($this->getReportePartido(), count($this->getReportePartido()), $comentario));
    }

    /**
     * Guarda las alineaciones iniciales en el reporte del partido
     */
    private function reportarAlineaciones(): void
    {
        $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_ALINEACIONES_PARTIDO'));
        $this->guardarComentario(Str::padLeft($this->getEquipo(1)->getAbreviatura(), 40) . ' - ' . $this->getEquipo(2)->getAbreviatura());
        $this->guardarComentario(Str::padLeft($this->getEquipo(1)->obtenerAlineacionNumerica(), 40) . ' - ' . $this->getEquipo(2)->obtenerAlineacionNumerica());
        $auxAlineacionEquipo1 = $this->getEquipo(1)->obtenerJugadoresIniciales(1);
        $auxAlineacionEquipo2 = $this->getEquipo(2)->obtenerJugadoresIniciales(2);
        for ($i = 1; $i <= count($auxAlineacionEquipo1); $i++) { 
            $this->guardarComentario(Str::padLeft(Arr::get($auxAlineacionEquipo1, $i), 40) . ' - ' . Arr::get($auxAlineacionEquipo2, $i));
        }
        $this->guardarComentario('');
    }

    /**
     * Guarda las estadísticas finales del partido en el reporte del partido
     */
    private function reportarEstadisticasFinales(): void
    {
        $this->guardarComentario('');
        $this->guardarComentario('Resultado final: ' . $this->getEquipo(1)->getAbreviatura() . ' (' . $this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) . ') - (' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) . ') ' . $this->getEquipo(2)->getAbreviatura());
        if ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) == $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES)) {
            $this->guardarComentario('Partido empatado');
        } elseif ($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES) > $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES)) {
            $this->guardarComentario('Ganó ' . $this->getEquipo(1)->getNombre());
        } else {
            $this->guardarComentario('Ganó ' . $this->getEquipo(2)->getNombre());
        }
        $this->guardarComentario('');
        $this->reportarEstadisticasEquipos();
        $this->guardarComentario('');
        $this->reportarEstadisticasJugadores();
    }

    /**
     * Guarda las estadísticas finales del equipo en el reporte del partido
     */
    private function reportarEstadisticasEquipos(): void
    {
        $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_ESTADISTICAS_EQUIPOS_PARTIDO'));
        $this->guardarComentario(Str::padLeft($this->getEquipo(1)->getAbreviatura(), 27) . ' - ' . $this->getEquipo(2)->getAbreviatura());
        $this->guardarComentario(Str::padRight('Goles:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::GOLES));
        $this->guardarComentario(Str::padRight('Tiros:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS));
        $this->guardarComentario(Str::padRight('Tiros al arco:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AL_ARCO), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AL_ARCO));
        $this->guardarComentario(Str::padRight('Tiros afuera:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AFUERA), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TIROS_AFUERA));
        $this->guardarComentario(Str::padRight('Faltas:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::FALTAS));
        $this->guardarComentario(Str::padRight('Tarjetas amarillas:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_AMARILLAS));
        $this->guardarComentario(Str::padRight('Tarjetas rojas:', 25) . Str::padLeft($this->getEquipo(1)->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_ROJAS), 2) . ' - ' . $this->getEquipo(2)->obtenerEstadistica(EstadisticasJugadorPartido::TARJETAS_ROJAS));
        $this->guardarComentario(Str::padRight('Cambios realizados:', 25) . Str::padLeft($this->getEquipo(1)->getSustituciones(), 2) . ' - ' . $this->getEquipo(2)->getSustituciones());
    }

    /**
     * Guarda las estadísticas de los jugadores en el reporte del partido
     */
    private function reportarEstadisticasJugadores(): void
    {
        $this->guardarComentario(ComentariosPartido::getComentario('COMENTARIO_ESTADISTICAS_JUGADORES_PARTIDO'));
        for ($i = 1; $i <= 2; $i++) { 
            $this->guardarComentario('Estadísticas ' . $this->getEquipo($i)->getNombre());
            $this->guardarComentario(
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
            foreach ($this->getEquipo($i)->getJugadoresConvocados() as $jugador) {
                $this->guardarComentario(
                    Str::padRight($jugador->getNombreApellido(), 40) .
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
     * Muestra en pantalla el reporte del partido
     */
    private function imprimirReportePartido(): void
    {
        foreach ($this->getReportePartido() as $comentario) {
            dump($comentario);
        }
    }

    /**
     * Retorna un JSON con el estado del partido
     */
    public function toJson()
    {
        return response()->json(array(
            'minuto' => $this->getMinuto(),
            'equipo1' => $this->getEquipo(1)->toArray(),
            'equipo2' => $this->getEquipo(2)->toArray(),
        ));
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
        return Arr::get($this->equipos, $idEquipo);
    }
    public function setEquipo(int $idEquipo, $equipo)
    {
        $this->equipos = Arr::add($this->equipos, $idEquipo, $equipo);
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
    public function getReportePartido(): array
    {
        return $this->reporte_partido;
    }
    public function setReportePartido(array $reporte)
    {
        $this->reporte_partido = $reporte;
    }
}
