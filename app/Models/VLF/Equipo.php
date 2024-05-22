<?php

namespace App\Models\VLF;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipo extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vlf_equipos';
    
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
     * Retorna los jugadores que pertenecen al equipo
     */
    public function jugadores(): HasMany
    {
        return $this->hasMany('App\Models\VLF\Jugador', 'id_equipo', 'id');
    }

    /**
     * Retorna la nacionalidad del equipo
     */
    public function nacionalidad(): BelongsTo
    {
        return $this->belongsTo('App\Models\Nacionalidad', 'id_nacionalidad', 'id');
    }
}
