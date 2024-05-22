<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nacionalidad extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vl_nacionalidades';
    
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
     * Retorna los jugadores de la nacionalidad
     */
    public function jugadores(): HasMany
    {
        return $this->hasMany('App\Models\VLF\Jugador', 'id_nacionalidad', 'id');
    }

    /**
     * Retorna los equipos de la nacionalidad
     */
    public function equipos(): HasMany
    {
        return $this->hasMany('App\Models\VLF\Equipo', 'id_nacionalidad', 'id');
    }
}
