<?php

namespace App\Models\VLT\Simulador;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\VLT\Jugador;
use App\Models\VLT\Simulador\Marcador;
use Illuminate\Support\Arr;

class PartidoSingles extends Model
{
    use HasFactory;

    private Jugador $jugador_1;
    private Jugador $jugador_2;
    private Marcador $marcador;
    private int $jugador_al_saque = 0;

    /**
     * Constructor de clase
     *
     * @param Jugador $j1 - jugador 1
     * @param Jugador $j2 - jugador 2
     * @param int $cantidadSets
     */
    public function __construct(Jugador $j1, Jugador $j2, int $cantidadSets) {
        $this->jugador_1 = $j1;
        $this->jugador_2 = $j2;
        $this->marcador = new Marcador($cantidadSets);
    }

    /**
     * GETTERS Y SETTERS
     */
    public function getJugador1(): Jugador
    {
        return $this->jugador_1;
    }

    public function getJugador2(): Jugador
    {
        return $this->jugador_2;
    }

    public function getMarcador(): Marcador
    {
        return $this->marcador;
    }

    public function getJugadorAlSaque(): int
    {
        return $this->jugador_al_saque;
    }

    /**
     * METODOS
     */

    /**
     * Retorna al jugador (objeto) que está sacando
     */
    public function obtenerJugadorAlSaque(): Jugador
    {
        if ($this->getJugadorAlSaque() == 1) {
            return $this->getJugador1();
        } else {
            return $this->getJugador2();
        }
    }

    /**
     * Sortea el jugador que empezará sacando en el partido
     */
    public function sortearSaque(): void
    {
        if ($this->getJugadorAlSaque() == 0) { // No hay jugador al saque
            $this->jugador_al_saque = Arr::random([1, 2]);
        }
    }
}
