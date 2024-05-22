<?php

namespace App\Models\VLF;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Jugador extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vlf_jugadores';
    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Retorna las habilidades asociadas al jugador.
     */
    public function habilidad(): HasOne
    {
        return $this->hasOne('App\Models\VLF\HabilidadesJugador', 'id_jugador', 'id');
    }

    /**
     * Retorna el equipo al que pertenece el jugador
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo('App\Models\VLF\Equipo', 'id_equipo', 'id');
    }

    /**
     * Retorna la nacionalidad del jugador
     */
    public function nacionalidad(): BelongsTo
    {
        return $this->belongsTo('App\Models\Nacionalidad', 'id_nacionalidad', 'id');
    }

    /**
     * Retorno de nombre en distintos formatos
     */
    public function getNombre(): string
    {
        return Str::of(Str::substr($this->nombre, Str::position($this->nombre, ',') + 1))->trim();
    }

    public function getApellido(): string
    {
        return Str::of(Str::substr($this->nombre, 0, Str::position($this->nombre, ',')))->trim();
    }

    public function getNombreApellido(): string
    {
        return Str::upper($this->getNombre() . " " . $this->getApellido());
    }

    public function getApellidoNombre(): string
    {
        return Str::upper($this->getApellido()) . ", " . $this->getNombre();
    }

    public function getNApellido(): string
    {
        return Str::charAt($this->getNombre(), 0) . ". " . $this->getApellido();
    }
}
