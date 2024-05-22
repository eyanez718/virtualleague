<?php

namespace App\Models\VLF\Simulador;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;
use Config;

class TacticaEquipoPartido extends Model
{
    use HasFactory;

    private string $tactica;
    private array $jugadores; // de tipo JugadorPartido
    private bool $ok; // Indica si la táctica fue bien cargada
    private int $indexPateadorPenales; // Es la posición en el array de jugadores del pateador de penales
    private bool $localia; // Indica si este equipo está jugando de local

    public function __construct($idEquipo, $jugadores, $equipoLocal)
    {
        $aux_ok = false;
        $aux_archivo_tactica = $this->LeerArchivoTactica($idEquipo);
        if (count($aux_archivo_tactica) > 0) {
            if ($this->cargarTactica($idEquipo, $aux_archivo_tactica)) {
                if ($this->cargarJugadores($idEquipo, $jugadores, $aux_archivo_tactica)) {
                    $this->setLocalia($equipoLocal);
                    $aux_ok = true;
                }
            }
        }
        $this->setOK($aux_ok);
    }

    /**
     * Lee el archivo json de la táctica
     */
    public function LeerArchivoTactica(int $idEquipo): array
    {
        try {
            $aux_path_tactica = storage_path('tacticas/' . $idEquipo . '.json');
            if (File::exists($aux_path_tactica)) {
                $tactica = File::json($aux_path_tactica);
                return $tactica;
            } else {
                return [];
            }
        } catch (\Throwable $th) {
            return [];
        }
    }

    /**
     * Carga la táctica desde el archivo
     * 
     * @param int $idEquipo
     * @return bool
     */
    private function cargarTactica(int $idEquipo, array $arrayTactica): bool
    {
        try {
            if (Arr::has($arrayTactica, 'tactica')) {
                $this->setTactica(Arr::get($arrayTactica, 'tactica'));
            } else {
                return false;
            }
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Carga los jugadores del equipo según el archivo de táctica
     * 
     * @param int $idEquipo
     * @param <Jugador> $jugadores
     */
    private function cargarJugadores(int $idEquipo, $jugadores, array $arrayTactica): bool
    {
        try {
            // Busco el id del pateador de penales
            $aux_id_pateador_penales = 0;
            if (Arr::has($arrayTactica, 'pateador_penales')) {
                $aux_id_pateador_penales = Arr::get($arrayTactica, 'pateador_penales');
            }
            $this->setJugadores([]);
            if (Arr::has($arrayTactica, 'titulares')) {
                $auxTitulares = Arr::get($arrayTactica, 'titulares');
                if (count($auxTitulares) == Config::get('vlf.partido.numero_jugadores')) {
                    for ($i = 1; $i <= Config::get('vlf.partido.numero_jugadores'); $i++) {
                        foreach ($jugadores as $jugador) {
                            if ($jugador->id == Arr::get($auxTitulares[$i], 'id_jugador')) {
                                $aux_jugador_partido = new JugadorPartido($jugador, 1, Arr::get($auxTitulares[$i], 'posicion'), Arr::get($auxTitulares[$i], 'lado'));
                                $this->jugadores = Arr::add($this->jugadores, $i, $aux_jugador_partido);
                                if ($aux_id_pateador_penales == $jugador->id) {
                                    $this->setIndexPateadorPenales($i);
                                }
                            }
                        }
                    }
                    if ($aux_id_pateador_penales == 0) { // No se inicializó el id del pateador de penales, busco al mejor de los que quedan en cancha
                        $this->buscarPateadorPenales();
                    }
                    $auxSuplentes = Arr::get($arrayTactica, 'suplentes');
                    if (count($auxSuplentes) == Config::get('vlf.partido.numero_suplentes')) {
                        for ($i = 1 + Config::get('vlf.partido.numero_jugadores'); $i <= Config::get('vlf.partido.numero_jugadores') + Config::get('vlf.partido.numero_suplentes'); $i++) {
                            foreach ($jugadores as $jugador) {
                                if ($jugador->id == Arr::get($auxSuplentes[$i], 'id_jugador')) {
                                    $aux_jugador_partido = new JugadorPartido($jugador, 0, Arr::get($auxSuplentes[$i], 'posicion'), Arr::get($auxSuplentes[$i], 'lado'));
                                    $this->jugadores = Arr::add($this->jugadores, $i, $aux_jugador_partido);
                                }
                            }
                        }
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else { // Si no tengo titulares, corto la ejecución en false
                return false;
            }
            return true;
        } catch (\Throwable $th) {
            dump($th);
            return false;
        }
    }

    /**
     * Retorna la suma de la agresividad de los jugadores activos
     * 
     * @return int
     */
    public function calcularAgresividad (): int
    {
        $agresividad = 0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1) {
                $agresividad = $agresividad + $jugador->getJugador()->habilidad->agresividad;
            }
        }
        return $agresividad;
    }

    /**
     * Retorna la suma de quite de los jugadores activos excluyendo al arquero (AR)
     * Al cálculo le aplica el multiplicador de táctica balanceada
     * 
     * @return float
     */
    public function calcularQuite(string $tacticaEquipoRival): float
    {
        $quite = 0.0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1 && $jugador->getPosicion() != 'AR') {
                $quite = $quite + ($jugador->getContribucionQuite($this->getTactica(), $tacticaEquipoRival) * $this->calcularMultiplicadoresBalanceTactica($jugador->getPosicion()));
            }
        }
        return $quite;
    }

    /**
     * Retorna la suma de pase de los jugadores activos excluyendo al arquero (AR)
     * Al cálculo le aplica el multiplicador de táctica balanceada
     * 
     * @return float
     */
    public function calcularPase(string $tacticaEquipoRival): float
    {
        $pase = 0.0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1 && $jugador->getPosicion() != 'AR') {
                $pase = $pase + ($jugador->getContribucionPase($this->getTactica(), $tacticaEquipoRival) * $this->calcularMultiplicadoresBalanceTactica($jugador->getPosicion()));
            }
        }
        return $pase;
    }

    /**
     * Retorna la suma de tiro de los jugadores activos excluyendo al arquero (AR)
     * Al cálculo le aplica el multiplicador de táctica balanceada
     * 
     * @return float
     */
    public function calcularTiro(string $tacticaEquipoRival): float
    {
        $tiro = 0.0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1 && $jugador->getPosicion() != 'AR') {
                $tiro = $tiro + ($jugador->getContribucionTiro($this->getTactica(), $tacticaEquipoRival) * $this->calcularMultiplicadoresBalanceTactica($jugador->getPosicion()));
            }
        }
        return $tiro;
    }

    /**
     * Aplica una reducción de fatiga a todos los jugadores activos
     */
    public function recalcularFatiga()
    {
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1) {
                $jugador->recalcularFatiga();
            }
        }
    }

    /**
     * Retorna un array con los balances de cada posición
     * Por cada posición
     * 1 - Si la posición está balanceada y con laterales, el modificador de la posición es 1
     * 2 - Si la posición está desbalanceada, se calcula en base al desbalance
     * 3 - Si la posición está balanceada pero sin laterales, el modificador de la posición es 0.87
     * 
     * @return array
     */
    public function calcularMultiplicadoresBalanceTactica(string $posicion): float
    {
        $aux_contador_izquierda = 0;
        $aux_contador_centro = 0;
        $aux_contador_derecha = 0;
        $aux_multiplicador = 1;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1 && $jugador->getPosicion() == $posicion) {
                switch ($jugador->getLado()) {
                    case 'L':
                        $aux_contador_izquierda = $aux_contador_izquierda + 1;
                        break;
                    case 'C':
                        $aux_contador_centro = $aux_contador_centro + 1;
                        break;
                    case 'R':
                        $aux_contador_derecha = $aux_contador_derecha + 1;
                        break;
                    default:
                        # code...
                        break;
                }
            }
        }
        if ($aux_contador_izquierda != $aux_contador_derecha) { // Tengo diferente cantidad de laterales
            $aux_ratio_posicion = 0.25 * abs($aux_contador_derecha - $aux_contador_izquierda) / ($aux_contador_derecha + $aux_contador_izquierda);
            $aux_multiplicador = 1 - $aux_ratio_posicion;
        } elseif ($aux_contador_izquierda == 0 && $aux_contador_derecha == 0 && $aux_contador_centro > 3) { // No tengo laterales y tengo más de 3 centrales
            $aux_multiplicador = 0.87;
        }
        return $aux_multiplicador;
    }

    /**
     * Retorna al jugador encargado de patear los penales
     * 
     * @return JugadorPartido
     */
    public function obtenerPateadorPenales(): JugadorPartido
    {
        return Arr::get($this->getJugadores(), $this->getIndexPateadorPenales());
    }

    /**
     * Obtiene el indice en el array de jugadores del mejor pateador (el que más valor de tiro tiene) de los jugadores activos
     */
    public function buscarPateadorPenales(): void
    {
        $aux_indice_pateador = 0;
        $aux_habilidad_tiro_pateador = 0;
        for ($i = 1; $i <= count($this->getJugadores()); $i++) { 
            if (Arr::get($this->getJugadores(), $i)->getJugador()->habilidad->habilidad_tiro > $aux_habilidad_tiro_pateador) {
                $aux_indice_pateador = $i;
                $aux_habilidad_tiro_pateador = Arr::get($this->getJugadores(), $i)->getJugador()->habilidad->habilidad_tiro;
            }
        }
        $this->setIndexPateadorPenales($aux_indice_pateador);
    }

    /**
     * Retorna la probabilidad de tiro teniendo en cuenta los jugadores activos excluyendo al arquero (AR)
     * 
     * @return float
     */
    public function calcularProbabilidadTiro(string $tacticaEquipoRival): float
    {
        $aux_probabilidad_tiro = 0.0;
        $aux_total_quite = $this->calcularQuite($tacticaEquipoRival);
        $aux_total_tiro = $this->calcularTiro($tacticaEquipoRival);
        $aux_total_pase = $this->calcularPase($tacticaEquipoRival);
        // Note: 1.0 is added to tackling, to avoid singularity when the
        // team tackling is 0
        $aux_probabilidad_tiro = 1.8*($this->calcularAgresividad()/50.0 + 800.0 *
                                 pow(((1.0/3.0*$aux_total_tiro + 2.0/3.0*$aux_total_pase)
                                / ($aux_total_quite + 1.0)), 2));
        if ($this->getLocalia() == true) { // Si el equipo está jugando de local, sumo el bonus
            $aux_probabilidad_tiro += Config::get('vlf.partido.bonus_localia');
        }
        return $aux_probabilidad_tiro;
    }
    /**
     * GETTERS Y SETTERS
     */
    public function getTactica(): string
    {
        return $this->tactica;
    }
    public function setTactica(string $tactica)
    {
        $this->tactica = $tactica;
    }
    public function getJugadores(): array
    {
        return $this->jugadores;
    }
    public function setJugadores(array $jugadores)
    {
        $this->jugadores = $jugadores;
    }
    public function getIndexPateadorPenales(): int
    {
        return $this->indexPateadorPenales;
    }
    public function setIndexPateadorPenales(int $indexPateadorPenales)
    {
        $this->indexPateadorPenales = $indexPateadorPenales;
    }
    public function getOK(): bool
    {
        return $this->ok;
    }
    public function setOK(bool $ok)
    {
        $this->ok = $ok;
    }
    public function getLocalia(): bool
    {
        return $this->localia;
    }
    public function setLocalia(bool $localia)
    {
        $this->localia = $localia;
    }
}
