<?php

namespace App\Models\VLF;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabilidadesJugador extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vlf_habilidades_jugadores';
    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_jugador';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Retorna el jugador al que pertenece la habilidad (NO SE DEBERÍA USAR)
     */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'id', 'id_jugador');
    }
}
