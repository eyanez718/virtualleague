<?php

namespace App\Models\VLT\Simulador;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use App\Enums\VLT\PuntosGameNormalValor;
use App\Enums\VLT\PuntosGameNormalTexto;

class Marcador extends Model
{
    use HasFactory;

    private int $game1;
    private int $game2;
    private array $sets1 = [];
    private array $sets2 = [];
    private int $setsPartido;
    private int $setActual = 0;

    /**
     * Constructor de clase
     *
     * @param int $sets - cantidad de sets del partido
     */
    public function __construct(int $sets)
    {
        $this->iniciarNuevoGame();
        $this->iniciarNuevoSet();
        $this->setsPartido = $sets;
    }

    /**
     * GETTERS Y SETTERS
     */
    public function getGame1()
    {
        return $this->game1;
    }

    public function getGame2()
    {
        return $this->game2;
    }

    public function getSets1()
    {
        return $this->sets1;
    }

    public function getSets2()
    {
        return $this->sets2;
    }

    public function getSetActual()
    {
        return $this->setActual;
    }

    public function getSetsPartido ()
    {
        return $this->setsPartido;
    }

    /**
     * METODOS
     */

    /** 
     * Suma un punto al jugador recibido
     * 
     * @param int $jugador
     */
    public function sumarPunto(int $jugador)
    {
        if (!$this->getModoTiebreak()) {
            // Puntaje 50 = Advantage
            if ($jugador == 1) { // Punto del jugador 1
                if ($this->getGame2() == PuntosGameNormalValor::getValorPorNombre('VENTAJA')) { // Si el tanteador 2 está en ventaja, descuento
                    $this->game2 = $this->getGame2() - 1;
                } else {
                    $this->game1 = $this->getGame1() + 1;
                }
            } else { // Punto del jugador 2
                if ($this->getGame1() == PuntosGameNormalValor::getValorPorNombre('VENTAJA')) { // Si el tanteador 2 está en ventaja, descuento
                    $this->game1 = $this->getGame1() - 1;
                } else {
                    $this->game2 = $this->getGame2() + 1;
                }
            }
        } else { // El game en disputa es un tiebreak
            if ($jugador == 1) { // Punto del jugador 1
                $this->game1 = $this->getGame1() + 1;
            } else { // Punto del jugador 2
                $this->game2 = $this->getGame2() + 1;
            }
        }
    }

    /**
     * Indica si el game actual terminó
     *
     * @return boolean
     */
    public function gameTerminado()
    {
        if (!$this->getModoTiebreak()) { // Es un game normal
            if ($this->getGame1() > $this->getGame2()) { // El jugador 1 tiene más puntos que el 2
                if ($this->getGame1() == PuntosGameNormalValor::getValorPorNombre('GAME')) { // Termina el game por ventaja de 2
                    return true;
                } else {
                    if ($this->getGame1() == PuntosGameNormalValor::getValorPorNombre('VENTAJA') && $this->getGame2() <= PuntosGameNormalValor::getValorPorNombre('TREINTA'))  {
                        return true;
                    }
                }
            } else { // El jugador 2 tiene más puntos que el jugador 1
                if ($this->getGame2() == PuntosGameNormalValor::getValorPorNombre('GAME')) { // Termina el game por ventaja de 2
                    return true;
                } else {
                    if ($this->getGame2() == PuntosGameNormalValor::getValorPorNombre('VENTAJA') && $this->getGame1() <= PuntosGameNormalValor::getValorPorNombre('TREINTA'))  {
                        return true;
                    }
                }
            }
        } else { // Es un tiebreak
            if ($this->getGame1() > $this->getGame2()) { // El último en ganar un punto es el jugador 1
                if ($this->getGame1() >= 7 && ($this->getGame1() - $this->getGame2()) >= 2 ) {
                    return true;
                }
            } else { // El último en ganar un punto es el jugador 2
                if ($this->getGame2() >= 7 && ($this->getGame2() - $this->getGame1()) >= 2 ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Actualiza el set actual sumando un game al jugador recibido
     * 
     *  @param int $ganadorUltimoPunto
     */
    public function sumarGame(int $ganadorUltimoPunto)
    {
        if ($ganadorUltimoPunto == 1) {
            $this->sets1 = Arr::set($this->sets1, 'set' . $this->getSetActual(), Arr::get($this->sets1, 'set' . $this->getSetActual()) + 1);
        } else {
            $this->sets2 = Arr::set($this->sets2, 'set' . $this->getSetActual(), Arr::get($this->sets2, 'set' . $this->getSetActual()) + 1);
        }
    }

    /**
     * Indica si el set actual está terminado
     *
     * @return boolean
     */
    public function setTerminado()
    {
        // Chequeo si el jugador 1 llegó a los 6 games con diferecia de 2 o ganó 7 (tiebreak)
        if ((Arr::get($this->sets1, 'set' . $this->getSetActual()) == 6 && Arr::get($this->sets2, 'set' . $this->getSetActual()) <= 4) || Arr::get($this->sets1, 'set' . $this->getSetActual()) == 7) {
            return true;
        } else {
            // Chequeo si el jugador 2 llegó a los 6 games con diferecia de 2 o ganó 7 (tiebreak)
            if ((Arr::get($this->sets2, 'set' . $this->getSetActual()) == 6 && Arr::get($this->sets1, 'set' . $this->getSetActual()) <= 4) || Arr::get($this->sets2, 'set' . $this->getSetActual()) == 7) {
                return true;
            }
        }
    }

    /**
     * Retorna la cantidad de sets ganados por el jugador recibido
     *
     * @param int $jugador
     * 
     * @return boolean
     */
    public function getSetsGanados(int $jugador)
    {
        $auxSetsJugador1 = 0;
        $auxSetsJugador2 = 0;
        for ($i=1; $i <= $this->getSetActual() ; $i++) { 
            $auxSetTerminado = true;
            if ($i == $this->getSetActual()) { // Si estoy controlando el set actual, verifico si está terminado o no
                if (!$this->setTerminado()) { // Si el set está terminado, verifico quien lo ganó
                    $auxSetTerminado = false;
                }
            }
            if ($auxSetTerminado) { // Si el set que se está controlando está terminado, sumo al tanteador
                if (Arr::get($this->sets1, 'set' . $i) > Arr::get($this->sets2, 'set' . $i)) {
                    // Set ganado por el jugador 1
                    $auxSetsJugador1 = $auxSetsJugador1 + 1;
                } else {
                    // Set ganado por el jugador 2
                    $auxSetsJugador2 = $auxSetsJugador2 + 1;
                }
            }
        }
        if ($jugador == 1) {
            return $auxSetsJugador1;
        } else {
            return $auxSetsJugador2;
        }
    }

    /**
     * Indica si el partido está terminado
     *
     * @return boolean
     */
    public function partidoTerminado()
    {
        if ($this->getSetsGanados(1) > ($this->getSetsPartido() / 2)) { // Chequeo si el jugador 1 ganó más de la mitad de los sets
            return true;
        } else {
            if ($this->getSetsGanados(2) > ($this->getSetsPartido() / 2)) {  // Chequeo si el jugador 2 ganó más de la mitad de los sets
                return true;
            }
        }
    }

    /**
     * Inicializa los contadores de sets
     */
    public function iniciarNuevoGame()
    {
        $this->game1 = 0;
        $this->game2 = 0;
    }

    /**
     * Actualiza el set actual y lo inicializa
     */
    public function iniciarNuevoSet()
    {
        $this->setActual = $this->getSetActual() + 1;
        $this->sets1 = Arr::add($this->sets1, 'set' . $this->getSetActual() , 0);
        $this->sets2 = Arr::add($this->sets2, 'set' . $this->getSetActual() , 0);
    }

    /**
     * Indica si el game actual es un tiebreak o un game normal
     * 
     * @return boolean
     */
    public function getModoTiebreak()
    {
        if (Arr::get($this->sets1, 'set' . $this->getSetActual()) == 6 && Arr::get($this->sets2, 'set' . $this->getSetActual()) == 6) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Indica la puntuación del jugador indicado en el game actual
     * 
     * @param int $jugador
     * 
     * @return int
     */
    public function getPuntuacionGame(int $jugador)
    {
        if (!$this->getModoTiebreak()) { // Si es un game normal, retorno valores del tenis
            //PuntosGameNormalValor::getValorPorNombre('GAME')
            if ($jugador == 1) {
                return PuntosGameNormalTexto::getValorPorNombre(PuntosGameNormalValor::getNombrePorValor($this->getGame1()));
            } else {
                return PuntosGameNormalTexto::getValorPorNombre(PuntosGameNormalValor::getNombrePorValor($this->getGame2()));
            }
        } else { // Retorno simplemente el número
            if ($jugador == 1) {
                return $this->getGame1();
            } else {
                return $this->getGame2();
            }
        }
    }

    /**
     * Retorna una cadena con la puntación en sets del jugador indicado
     * 
     * @param int $jugador
     * 
     * @return string
     */
    public function getPuntuacionTotalToString($jugador)
    {
        $puntuacion = "";
        if ($jugador == 1) {
            for ($i=1; $i <= count($this->getSets1()) ; $i++) { 
                if ($i == 1) {
                    $puntuacion = $puntuacion . Arr::get($this->getSets1(), 'set' . $i);
                } else {
                    $puntuacion = $puntuacion . " - " . Arr::get($this->getSets1(), 'set' . $i);
                }
            }
        } else {
            for ($i=1; $i <= count($this->getSets2()) ; $i++) { 
                if ($i == 1) {
                    $puntuacion = $puntuacion . Arr::get($this->getSets2(), 'set' . $i);
                } else {
                    $puntuacion = $puntuacion . " - " . Arr::get($this->getSets2(), 'set' . $i);
                }
            }
        }
        return $puntuacion;
    }
}
