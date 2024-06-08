<?php

namespace App\Models\VLF\Simulador;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\VLF\EstadisticasJugadorPartido as ENUMEstaditicas;

class EstadisticasJugadorPartido extends Model
{
    use HasFactory;

    private int $minutos = 0;
    private int $atajadas = 0;
    private int $quites = 0;
    private int $pases_clave = 0;
    private int $tiros = 0;
    private int $goles = 0;
    private int $faltas = 0;
    private int $asistencias = 0;
    private int $tarjetas_amarillas = 0;
    private int $tarjetas_rojas = 0;

    /**
     * Variables axiliares, usadas para calcular habilidad
     */
    private int $tiros_al_arco = 0;
    private int $tiros_afuera = 0;
    private int $goles_concedidos = 0;

    /**
     * Cambios de habilidad del jugador durante el partido
     */
    private int $progreso_arquero = 0;
    private int $progreso_quite = 0;
    private int $progreso_pase = 0;
    private int $progreso_tiro = 0;

    public function __construct()
    {
        
    }

    /**
     * Suma un valor a la estadística del jugador
     * 
     * @param ENUMEstaditicas<EstadisticaJugadorPartido> $estadistica
     * @param int $valor - Si no se envía valor, toma por defecto 1
     */
    public function sumarEstadistica(ENUMEstaditicas $estadistica, int $valor = 1): void
    {
        switch ($estadistica) {
            case ENUMEstaditicas::MINUTOS:
                $this->minutos += $valor;
                break;
            case ENUMEstaditicas::ATAJADAS:
                $this->atajadas += $valor;
                break;
            case ENUMEstaditicas::QUITES:
                $this->quites += $valor;
                break;
            case ENUMEstaditicas::PASES_CLAVE:
                $this->pases_clave += $valor;
                break;
            case ENUMEstaditicas::TIROS:
                $this->tiros += $valor;
                break;
            case ENUMEstaditicas::GOLES:
                $this->goles += $valor;
                break;
            case ENUMEstaditicas::FALTAS:
                $this->faltas += $valor;
                break;
            case ENUMEstaditicas::ASISTENCIAS:
                $this->asistencias += $valor;
                break;
            case ENUMEstaditicas::TARJETAS_AMARILLAS:
                $this->tarjetas_amarillas += $valor;
                break;
            case ENUMEstaditicas::TARJETAS_ROJAS:
                $this->tarjetas_rojas += $valor;
                break;
            case ENUMEstaditicas::TIROS_AL_ARCO:
                $this->tiros_al_arco += $valor;
                break;
            case ENUMEstaditicas::TIROS_AFUERA:
                $this->tiros_afuera += $valor;
                break;
            case ENUMEstaditicas::GOLES_CONCEDIDOS:
                $this->goles_concedidos += $valor;
                break;
            case ENUMEstaditicas::PROGRESO_ARQUERO:
                $this->progreso_arquero += $valor;
                break;
            case ENUMEstaditicas::PROGRESO_QUITE:
                $this->progreso_quite += $valor;
                break;
            case ENUMEstaditicas::PROGRESO_PASE:
                $this->progreso_pase += $valor;
                break;
            case ENUMEstaditicas::PROGRESO_TIRO:
                $this->progreso_tiro += $valor;
                break;
            default:
                break;
        }
    }

    /**
     * Retorna el valor de una $estadistica del jugador
     * 
     * @param ENUMEstaditicas<EstadisticaJugadorPartido> $estadistica
     * @return int
     */
    public function obtenerEstadistica(ENUMEstaditicas $estadistica): int
    {
        switch ($estadistica) {
            case ENUMEstaditicas::MINUTOS:
                return $this->minutos;
                break;
            case ENUMEstaditicas::ATAJADAS:
                return $this->atajadas;
                break;
            case ENUMEstaditicas::QUITES:
                return $this->quites;
                break;
            case ENUMEstaditicas::PASES_CLAVE:
                return $this->pases_clave;
                break;
            case ENUMEstaditicas::TIROS:
                return $this->tiros;
                break;
            case ENUMEstaditicas::GOLES:
                return $this->goles;
                break;
            case ENUMEstaditicas::FALTAS:
                return $this->faltas;
                break;
            case ENUMEstaditicas::ASISTENCIAS:
                return $this->asistencias;
                break;
            case ENUMEstaditicas::TARJETAS_AMARILLAS:
                return $this->tarjetas_amarillas;
                break;
            case ENUMEstaditicas::TARJETAS_ROJAS:
                return $this->tarjetas_rojas;
                break;
            case ENUMEstaditicas::TIROS_AL_ARCO:
                return $this->tiros_al_arco;
                break;
            case ENUMEstaditicas::TIROS_AFUERA:
                return $this->tiros_afuera;
                break;
            case ENUMEstaditicas::GOLES_CONCEDIDOS:
                return $this->goles_concedidos;
                break;
            case ENUMEstaditicas::PROGRESO_ARQUERO:
                return $this->progreso_arquero;
                break;
            case ENUMEstaditicas::PROGRESO_QUITE:
                return $this->progreso_quite;
                break;
            case ENUMEstaditicas::PROGRESO_PASE:
                return $this->progreso_pase;
                break;
            case ENUMEstaditicas::PROGRESO_TIRO:
                return $this->progreso_tiro;
                break;
            default:
                break;
        }
    }

    /**
     * Retorna el objeto en formato array
     * 
     * @return array
     */
    public function toArray()
    {
        return [
            'minutos' => $this->minutos,
            'atajadas' => $this->atajadas,
            'quites' => $this->quites,
            'pases_clave' => $this->pases_clave,
            'tiros' => $this->tiros,
            'goles' => $this->goles,
            'faltas' => $this->faltas,
            'asistencias' => $this->asistencias,
            'tarjetas_amarillas' => $this->tarjetas_amarillas,
            'tarjetas_rojas' => $this->tarjetas_rojas,
            'tiros_al_arco' => $this->tiros_al_arco,
            'tiros_afuera' => $this->tiros_afuera,
            'goles_concedidos' => $this->goles_concedidos,
            'progreso_arquero' => $this->progreso_arquero,
            'progreso_quite' => $this->progreso_quite,
            'progreso_pase' => $this->progreso_pase,
            'progreso_tiro' => $this->progreso_tiro,
        ];
    }
}
