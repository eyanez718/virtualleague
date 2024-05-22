<?php

namespace App\Models\VLT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Jugador extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vlt_jugadores';
    
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
        return $this->getNombre() . " " . $this->getApellido();
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
