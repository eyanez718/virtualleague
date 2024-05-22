<?php

namespace App\Models\VLF\Simulador;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Config;
use App\Models\VLF\Jugador;
use App\Models\VLF\Simulador\EstadisticasJugadorPartido;

class JugadorPartido extends Model
{
    use HasFactory;

    private Jugador $jugador;
    private EstadisticasJugadorPartido $estadisticas;

    private bool $activo; // true = jugando, false = en el banco
    private bool $disponible; // true = disponible para sustitución, false = no puede jugar (ya jugó)
    private bool $lesionado;
    private float $fatiga;
    private string $posicion;
    private string $lado;

    public function __construct($jugador, $activo, $posicion, $lado, $disponible) {
        $this->setJugador($jugador);
        $this->setActivo($activo);
        $this->setDisponible($disponible);
        $this->setLesionado(0);
        // Inicializo fatiga
        $this->setFatiga($this->getJugador()->habilidad->fisico / 100.0);
        $this->setPosicion($posicion);
        $this->setLado($lado);
        // Asigno estadísticas del partido
        $this->setEstadisticas(new EstadisticasJugadorPartido);
    }
    /**
     * Each player has a nominal_fatigue_per_minute rating that's
     * calculated once, based on his stamina.
     * 
     * I'd like the average rating be 0.031 - so that an average player
     * (stamina = 50) will lose 30 fitness points during a full game.
     * 
     * The range is approximately 50 - 10 points, and the stamina range
     * is 1-99. So, first the ratio is normalized and then subtracted
     * from the average 0.031 (which, times 90 minutes, is 0.279).
     * The formula for each player is:
     * 
     * fatigue            stamina - 50
     * ------- = 0.0031 - ------------  * 0.0022
     *  minute                 50
     * 
     * 
     * This gives (approximately) 30 lost fitness points for average players,
     * 50 for the worse stamina and 10 for the best stamina.
     * 
     * A small random factor is added each minute, so the exact numbers are
     * not deterministic.
     * 
     */
    public function getFatigaNominalPorMinuto(): float
    {
        $ratio_resistencia_normalizado = ($this->getJugador()->habilidad->resistencia - 50) / 50.0;
        return 0.0031 - $ratio_resistencia_normalizado * 0.0022;
    }

    /**
     * Descuenta la fatiga al jugador según la fatiga nominal
     */
    public function recalcularFatiga()
    {
        $deduccion_fatiga = $this->getFatigaNominalPorMinuto();
        $random = rand(0, 100);
        $deduccion_fatiga = $deduccion_fatiga + (($random - 50) / 50.0 * 0.003);
        $this->setFatiga($this->getFatiga() - $deduccion_fatiga);
        if ($this->getFatiga() < 0.10) {
            $this->setFatiga(0.10);
        }
    }

    /**
     * Indica si el jugador está ubicado en una posición que le guste (D) derecha (C) centro (I) izquierda
     * 
     * @return bool
     */
    public function getLadoComodo(): bool
    {
        if (str_contains($this->getJugador()->habilidad->lado_preferido, $this->getLado())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Retorna la contribución en cuanto a quite del jugador
     * El valor se calcula a partir del modificador de la posición para quite sumado al bonus de la posición para quite ->
     * multiplicado por el multiplicador de lado, por la habilidad de quite y la fatiga
     * 
     * @param string $tacticaEquipo
     * @param string $tacticaEquipoRival
     * @return float
     */
    public function getContribucionQuite(string $tacticaEquipo, string $tacticaEquipoRival): float
    {
        $aux_contribucion = 0;
        // Busco el multiplicador de la posición
        $aux_multi_posicion = Config::get('vlf.partido.tacticas.' . $tacticaEquipo . '.modificadores.' . $this->getPosicion() . '.MULTI.mod_quite');
        if ($aux_multi_posicion == null) {
            $aux_multi_posicion = 0;
        }
        // Busco si la posición tiene un bonus contra la táctica rival
        $aux_bonus_posicion = Config::get('vlf.partido.tacticas.' . $tacticaEquipo . '.modificadores.' . $this->getPosicion() . '.BONUS_' . $tacticaEquipoRival . '.mod_quite');
        if ($aux_bonus_posicion == null) {
            $aux_bonus_posicion = 0;
        }
        $aux_multi_lado = 0;
        if ($this->getLadoComodo()) {
            $aux_multi_lado = 1;
        } else {
            $aux_multi_lado = 0.75;
        }
        $aux_contribucion = ($aux_multi_posicion + $aux_bonus_posicion) * $aux_multi_lado * $this->getJugador()->habilidad->habilidad_quite * $this->getFatiga();
        return $aux_contribucion;
    }

    /**
     * Retorna la contribución en cuanto a pase del jugador
     * El valor se calcula a partir del modificador de la posición para pase sumado al bonus de la posición para pase ->
     * multiplicado por el multiplicador de lado, por la habilidad de pase y la fatiga
     * 
     * @param string $tacticaEquipo
     * @param string $tacticaEquipoRival
     * @return float
     */
    public function getContribucionPase(string $tacticaEquipo, string $tacticaEquipoRival, float $multiplicadorBalanceTactica): float
    {
        $aux_contribucion = 0;
        // Busco el multiplicador de la posición
        $aux_multi_posicion = Config::get('vlf.partido.tacticas.' . $tacticaEquipo . '.modificadores.' . $this->getPosicion() . '.MULTI.mod_pase');
        if ($aux_multi_posicion == null) {
            $aux_multi_posicion = 0;
        }
        // Busco si la posición tiene un bonus contra la táctica rival
        $aux_bonus_posicion = Config::get('vlf.partido.tacticas.' . $tacticaEquipo . '.modificadores.' . $this->getPosicion() . '.BONUS_' . $tacticaEquipoRival . '.mod_pase');
        if ($aux_bonus_posicion == null) {
            $aux_bonus_posicion = 0;
        }
        $aux_multi_lado = 0;
        if ($this->getLadoComodo()) {
            $aux_multi_lado = 1;
        } else {
            $aux_multi_lado = 0.75;
        }
        $aux_contribucion = ($aux_multi_posicion + $aux_bonus_posicion) * $aux_multi_lado * $this->getJugador()->habilidad->habilidad_pase * $this->getFatiga() * $multiplicadorBalanceTactica;
        return $aux_contribucion;
    }

    /**
     * Retorna la contribución en cuanto a tiro del jugador
     * El valor se calcula a partir del modificador de la posición para tiro sumado al bonus de la posición para tiro ->
     * multiplicado por el multiplicador de lado, por la habilidad de pase y la fatiga
     * 
     * @param string $tacticaEquipo
     * @param string $tacticaEquipoRival
     * @return float
     */
    public function getContribucionTiro(string $tacticaEquipo, string $tacticaEquipoRival): float
    {
        $aux_contribucion = 0;
        // Busco el multiplicador de la posición
        $aux_multi_posicion = Config::get('vlf.partido.tacticas.' . $tacticaEquipo . '.modificadores.' . $this->getPosicion() . '.MULTI.mod_tiro');
        if ($aux_multi_posicion == null) {
            $aux_multi_posicion = 0;
        }
        // Busco si la posición tiene un bonus contra la táctica rival
        $aux_bonus_posicion = Config::get('vlf.partido.tacticas.' . $tacticaEquipo . '.modificadores.' . $this->getPosicion() . '.BONUS_' . $tacticaEquipoRival . '.mod_tiro');
        if ($aux_bonus_posicion == null) {
            $aux_bonus_posicion = 0;
        }
        $aux_multi_lado = 0;
        if ($this->getLadoComodo()) {
            $aux_multi_lado = 1;
        } else {
            $aux_multi_lado = 0.75;
        }
        $aux_contribucion = ($aux_multi_posicion + $aux_bonus_posicion) * $aux_multi_lado * $this->getJugador()->habilidad->habilidad_tiro * $this->getFatiga();
        return $aux_contribucion;
    }

    /**
     * GETTERS Y SETTERS
     */
    public function getJugador(): Jugador
    {
        return $this->jugador;
    }
    public function setJugador(Jugador $jugador)
    {
        $this->jugador = $jugador;
    }
    public function getEstadisticas(): EstadisticasJugadorPartido
    {
        return $this->estadisticas;
    }
    public function setEstadisticas($estadisticas)
    {
        $this->estadisticas = $estadisticas;
    }
    public function getActivo()
    {
        return $this->activo;
    }
    public function setActivo(bool $activo)
    {
        $this->activo = $activo;
    }
    public function getDisponible(): bool
    {
        return $this->disponible;
    }
    public function setDisponible(bool $disponible)
    {
        $this->disponible = $disponible;
    }
    public function getFatiga(): float
    {
        return $this->fatiga;
    }
    public function setFatiga(float $fatiga)
    {
        $this->fatiga = $fatiga;
    }
    public function getPosicion(): string
    {
        return $this->posicion;
    }
    public function setPosicion(string $posicion)
    {
        $this->posicion = $posicion;
    }
    public function getLado(): string
    {
        return $this->lado;
    }
    public function setLado(string $lado)
    {
        $this->lado = $lado;
    }
    public function getLesionado(): bool
    {
        return $this->lesionado;
    }
    public function setLesionado(bool $lesionado)
    {
        $this->lesionado = $lesionado;
    }
}
