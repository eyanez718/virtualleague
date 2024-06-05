<?php

namespace App\Models\VLF;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

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

    /**
     * Retorna un json con la estructura del equipo para el partido
     */
    public function preparacionPartido(): array
    {
        $aux_equipo = [];
        // Preparo los datos del equipo
        $aux_equipo = Arr::add($aux_equipo, 'id', $this->id);
        $aux_equipo = Arr::add($aux_equipo, 'nombre', $this->nombre);
        $aux_equipo = Arr::add($aux_equipo, 'abreviatura', $this->abreviatura);
        // Busco jugadores con sus habilidades
        $aux_equipo = Arr::add($aux_equipo, 'jugadores', $this->jugadores()->with('habilidad')->get());
        // Preparo la táctica
        $aux_equipo = Arr::add($aux_equipo, 'tactica', $this->leerArchivoTactica(), true);
        return $aux_equipo;
    }

    /**
     * Lee el archivo json de la táctica
     * 
     * @param int $idEquipo
     * @return array
     */
    private function leerArchivoTactica(): array
    {
        try {
            $aux_path_tactica = storage_path('tacticas/' . $this->id . '.json');
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
}
