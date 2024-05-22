<?php

namespace App\Models\VLF\Simulador;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\VLF\EstadisticasEquipoPartido as ENUMEstadisticas;

class EstadisticasEquipoPartido extends Model
{
    use HasFactory;

    private int $goles = 0;
    private int $tiros_al_arco = 0;
    private int $tiros_afuera = 0;
    private int $faltas = 0;
    private int $sustituciones = 0;
    private int $lesiones = 0;

    public function __construct() {
        
    }

    /**
     * Suma un valor a la estadística del equipo
     * 
     * @param ENUMEstadisticas(EstadisticaEquipoPartido) $estadistica
     * @param int $valor - Si no se envía valor, toma por defecto 1
     */
    public function sumarEstadistica(ENUMEstadisticas $estadistica, int $valor = 1)
    {
        switch ($estadistica) {
            case ENUMEstadisticas::GOLES:
                $this->goles += $valor;
                break;
            case ENUMEstadisticas::TIROS_AL_ARCO:
                $this->tiros_al_arco += $valor;
                break;
            case ENUMEstadisticas::TIROS_AFUERA:
                $this->tiros_afuera += $valor;
                break;
            case ENUMEstadisticas::FALTAS:
                $this->faltas += $valor;
                break;
            case ENUMEstadisticas::SUSTITUCIONES:
                $this->sustituciones += $valor;
                break;
            case ENUMEstadisticas::LESIONES:
                $this->lesiones += $valor;
                break;
            default:
                break;
        }
    }
}