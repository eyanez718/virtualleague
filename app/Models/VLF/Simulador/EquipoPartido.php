<?php

namespace App\Models\VLF\Simulador;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;
use Config;
use App\Models\VLF\Equipo;
use App\Enums\VLF\EstadisticasJugadorPartido as ENUMEstaditicas;

class EquipoPartido extends Model
{
    use HasFactory;

    private Equipo $equipo;
    private string $tactica;
    private array $jugadores; // de tipo JugadorPartido
    private int $indexPateadorPenales; // Es la posición en el array de jugadores del pateador de penales
    private bool $localia; // Indica si este equipo está jugando de local
    private int $sustituciones; // Contabiliza la cantidad de cambios que realizó el equipo
    private bool $ok; //Indica si el equipo fue buen cargado
    private float $score; //calculo de score

    public function __construct(int $idEquipo, bool $localia)
    {
        try {
            $this->setEquipo(Equipo::with('jugadores')
                                ->find($idEquipo));
            $aux_ok = false;
            $aux_archivo_tactica = $this->LeerArchivoTactica($idEquipo);
            if (count($aux_archivo_tactica) > 0) {
                if ($this->cargarTactica($aux_archivo_tactica)) {
                    if ($this->cargarJugadores($aux_archivo_tactica)) {
                        $this->setLocalia($localia);
                        $this->setSustituciones(0);
                        $aux_ok = true;
                    }
                }
            }
            $this->setOK($aux_ok);
        } catch (\Throwable $th) {
            dump($th);
            $this->setOK(false);
        }
    }

    /**
     * Lee el archivo json de la táctica
     * 
     * @param int $idEquipo
     * @return array
     */
    public function LeerArchivoTactica(int $idEquipo): array
    {
        try {
            $aux_path_tactica = storage_path('tacticas/' . $idEquipo . '.json');
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

    /**
     * Carga la táctica desde el archivo
     * 
     * @param array $arrayTactica
     * @return bool
     */
    private function cargarTactica(array $arrayTactica): bool
    {
        try {
            if (Arr::has($arrayTactica, 'tactica')) {
                $this->setTactica(Arr::get($arrayTactica, 'tactica'));
            } else {
                return false;
            }
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Carga los jugadores del equipo según el archivo de táctica
     * 
     * @param array $arrayTactica
     * @return bool
     */
    private function cargarJugadores(array $arrayTactica): bool
    {
        try {
            // Busco el id del pateador de penales
            $aux_id_pateador_penales = 0;
            if (Arr::has($arrayTactica, 'pateador_penales')) {
                $aux_id_pateador_penales = Arr::get($arrayTactica, 'pateador_penales');
            }
            $this->setJugadores([]);
            if (Arr::has($arrayTactica, 'titulares')) {
                $auxTitulares = Arr::get($arrayTactica, 'titulares');
                if (count($auxTitulares) == Config::get('vlf.partido.numero_jugadores')) {
                    for ($i = 1; $i <= Config::get('vlf.partido.numero_jugadores'); $i++) {
                        foreach ($this->getEquipo()->jugadores as $jugador) {
                            if ($jugador->id == Arr::get($auxTitulares[$i], 'id_jugador')) {
                                $aux_jugador_partido = new JugadorPartido($jugador, true, Arr::get($auxTitulares[$i], 'posicion'), Arr::get($auxTitulares[$i], 'lado'), false);
                                $this->jugadores = Arr::add($this->jugadores, $i, $aux_jugador_partido);
                                if ($aux_id_pateador_penales == $jugador->id) { // Si el idPateadorPenales de la táctica está entre los titulares
                                    $this->setIdPateadorPenales($jugador->id);
                                }
                            }
                        }
                    }
                    if ($aux_id_pateador_penales == 0) { // No se inicializó el id del pateador de penales, busco al mejor de los que quedan en cancha
                        $this->buscarPateadorPenales();
                    }
                    $auxSuplentes = Arr::get($arrayTactica, 'suplentes');
                    if (count($auxSuplentes) == Config::get('vlf.partido.numero_suplentes')) {
                        for ($i = 1 + Config::get('vlf.partido.numero_jugadores'); $i <= Config::get('vlf.partido.numero_jugadores') + Config::get('vlf.partido.numero_suplentes'); $i++) {
                            foreach ($this->getEquipo()->jugadores as $jugador) {
                                if ($jugador->id == Arr::get($auxSuplentes[$i], 'id_jugador')) {
                                    $aux_jugador_partido = new JugadorPartido($jugador, false, Arr::get($auxSuplentes[$i], 'posicion'), Arr::get($auxSuplentes[$i], 'lado'), true);
                                    $this->jugadores = Arr::add($this->jugadores, $i, $aux_jugador_partido);
                                }
                            }
                        }
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else { // Si no tengo titulares, corto la ejecución en false
                return false;
            }
            return true;
        } catch (\Throwable $th) {
            dump("error cargar jugadores");
            dump($th);
            return false;
        }
    }

    /**
     * Retorna la suma de la agresividad de los jugadores activos
     * 
     * @return int
     */
    public function calcularAgresividad(): int
    {
        $agresividad = 0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1) {
                $agresividad = $agresividad + $jugador->getJugador()->habilidad->agresividad;
            }
        }
        return $agresividad;
    }

    /**
     * Retorna la suma de quite de los jugadores activos excluyendo al arquero (AR)
     * Al cálculo le aplica el multiplicador de táctica balanceada
     * 
     * @param string $tacticaEquipoRival
     * @return float
     */
    public function calcularQuite(string $tacticaEquipoRival): float
    {
        $quite = 0.0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1 && $jugador->getPosicion() != 'AR') {
                $quite = $quite + ($jugador->getContribucionQuite($this->getTactica(), $tacticaEquipoRival) * $this->calcularMultiplicadoresBalanceTactica($jugador->getPosicion()));
            }
        }
        return $quite;
    }

    /**
     * Retorna la suma de pase de los jugadores activos excluyendo al arquero (AR)
     * Al cálculo le aplica el multiplicador de táctica balanceada
     * 
     * @param string $tacticaEquipoRival
     * @return float
     */
    public function calcularPase(string $tacticaEquipoRival): float
    {
        $pase = 0.0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1 && $jugador->getPosicion() != 'AR') {
                $pase = $pase + ($jugador->getContribucionPase($this->getTactica(), $tacticaEquipoRival, $this->calcularMultiplicadoresBalanceTactica($jugador->getPosicion())));
            }
        }
        return $pase;
    }

    /**
     * Retorna la suma de tiro de los jugadores activos excluyendo al arquero (AR)
     * Al cálculo le aplica el multiplicador de táctica balanceada
     * 
     * @param string $tacticaEquipoRival
     * @return float
     */
    public function calcularTiro(string $tacticaEquipoRival): float
    {
        $tiro = 0.0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1 && $jugador->getPosicion() != 'AR') {
                $tiro = $tiro + ($jugador->getContribucionTiro($this->getTactica(), $tacticaEquipoRival) * $this->calcularMultiplicadoresBalanceTactica($jugador->getPosicion()));
            }
        }
        return $tiro;
    }

    /**
     * Aplica una reducción de fatiga a todos los jugadores activos
     */
    public function recalcularFatiga(): void
    {
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1) {
                $jugador->recalcularFatiga();
            }
        }
    }

    /**
     * Retorna un float con el multiplicador del balance de la táctica
     * Por cada posición
     * 1 - Si la posición está balanceada y con laterales, el modificador de la posición es 1
     * 2 - Si la posición está desbalanceada, se calcula en base al desbalance
     * 3 - Si la posición está balanceada pero sin laterales, el modificador de la posición es 0.87
     * 
     * @param string $posicion
     * @return float
     */
    public function calcularMultiplicadoresBalanceTactica(string $posicion): float
    {
        $aux_contador_izquierda = 0;
        $aux_contador_centro = 0;
        $aux_contador_derecha = 0;
        $aux_multiplicador = 1;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == 1 && $jugador->getPosicion() == $posicion) {
                switch ($jugador->getLado()) {
                    case 'L':
                        $aux_contador_izquierda = $aux_contador_izquierda + 1;
                        break;
                    case 'C':
                        $aux_contador_centro = $aux_contador_centro + 1;
                        break;
                    case 'R':
                        $aux_contador_derecha = $aux_contador_derecha + 1;
                        break;
                    default:
                        # code...
                        break;
                }
            }
        }
        if ($aux_contador_izquierda != $aux_contador_derecha) { // Tengo diferente cantidad de laterales
            $aux_ratio_posicion = 0.25 * abs($aux_contador_derecha - $aux_contador_izquierda) / ($aux_contador_derecha + $aux_contador_izquierda);
            $aux_multiplicador = 1 - $aux_ratio_posicion;
        } elseif ($aux_contador_izquierda == 0 && $aux_contador_derecha == 0 && $aux_contador_centro > 3) { // No tengo laterales y tengo más de 3 centrales
            $aux_multiplicador = 0.87;
        }
        return $aux_multiplicador;
    }

    /**
     * Retorna la probabilidad de tiro teniendo en cuenta los jugadores activos excluyendo al arquero (AR)
     * 
     * @param string $tacticaEquipoRival
     * @return float
     */
    public function calcularProbabilidadTiro(string $tacticaEquipoRival): float
    {
        $aux_probabilidad_tiro = 0.0;
        $aux_total_quite = $this->calcularQuite($tacticaEquipoRival);
        $aux_total_tiro = $this->calcularTiro($tacticaEquipoRival);
        $aux_total_pase = $this->calcularPase($tacticaEquipoRival);

        // Nota: Se agrega 1.0 al quite, para evitar singularidad cuando el quite del equipo es 0
        $aux_probabilidad_tiro = 1.8*($this->calcularAgresividad()/50.0 + 800.0 *
                                 pow(((1.0/3.0*$aux_total_tiro + 2.0/3.0*$aux_total_pase)
                                / ($aux_total_quite + 1.0)), 2));
        if ($this->getLocalia() == true) { // Si el equipo está jugando de local, sumo el bonus
            $aux_probabilidad_tiro += Config::get('vlf.partido.bonus_localia');
        }
        return $aux_probabilidad_tiro;
    }

    /**
     * Retorna al jugador buscado según el id, recorre el array de jugadores
     * 
     * @param int $idJugador
     * @return JugadorPartido
     */
    public function obtenerJugador(int $idJugador): JugadorPartido
    {
        for ($i = 1; $i <= count($this->getJugadores()); $i++) { 
            if (Arr::get($this->getJugadores(), $i)->getJugador()->id == $idJugador) {
                return Arr::get($this->getJugadores(), $i);
            }
        }
    }

    /**
     * Retorna al jugador buscado según el indice en el array de jugadores
     * 
     * @param int $indiceJugador
     * @return JugadorPartido
     */
    public function obtenerJugadorIndice(int $indiceJugador): JugadorPartido
    {
        return Arr::get($this->getJugadores(), $indiceJugador);
    }

    /**
     * Retorna al arquero actual del equipo
     * 
     * @return JugadorPartido
     */
    public function obtenerArquero(): ?JugadorPartido
    {
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getActivo() == true && $jugador->getPosicion() == 'AR') {
                return $jugador;
            }
        }
        return null;
    }

    /**
     * Retorna un jugador para reemplazar a un jugador de campo lesionado o al arquero lesionado/expulsado
     * 
     * @param int $idJugadorReemplazar
     * @param bool $arqueroAlternativo
     * @return int id del reeplazante
     */
    public function buscarReemplazante(int $idJugadorReemplazar, bool $buscoArqueroAlternativo = false): int
    {
        $aux_id_reemplazante = 0;
        $aux_sumatoria_habilidad = 0;
        if ($this->getSustituciones() >= Config::get('vlf.partido.sustituciones') || $buscoArqueroAlternativo == true) { // El equipo no tiene más sustituciones
            if ($this->obtenerJugador($idJugadorReemplazar)->getPosicion() == 'AR' || $buscoArqueroAlternativo == true) {
                // Si el equipo no tiene cambios y $idJugadorReemplazar, busco un reemplazante en el resto de los jugadores
                // si $idJugadorReemplazar no es arquero, no busco reemplazante
                foreach ($this->getJugadores() as $jugador) {
                    if ($jugador->getActivo() == true && $jugador->getPosicion() != 'AR') {
                        if (($jugador->getJugador()->habilidad->habilidad_arquero + ($jugador->getJugador()->habilidad->habilidad_quite / 2)) > $aux_sumatoria_habilidad) {
                            $aux_sumatoria_habilidad = $jugador->getJugador()->habilidad->habilidad_arquero + ($jugador->getJugador()->habilidad->habilidad_quite / 2);
                            $aux_id_reemplazante = $jugador->getJugador()->id;
                        }
                        
                    }
                }
            }
        } else { // Aún le quedan cambios al equipo, busco un reemplazante
            // Control si busco arquero suplente o jugador de campo
            if ($this->obtenerJugador($idJugadorReemplazar)->getPosicion() == 'AR') { // Tengo que reemplazar un arquero
                // Busco arquero suplente
                foreach ($this->getJugadores() as $jugador) {
                    if ($jugador->getActivo() == false && $jugador->getDisponible() == true && $jugador->getPosicion() == 'AR') {
                        $aux_id_reemplazante = $jugador->getJugador()->id;
                    }
                }
                // Si no encontré arquero suplente, busco un jugador suplente "de campo" para que ataje
                if ($aux_id_reemplazante == 0) { 
                    foreach ($this->getJugadores() as $jugador) {
                        if ($jugador->getActivo() == false && $jugador->getDisponible() == true && $jugador->getPosicion() != 'AR') {
                            if (($jugador->getJugador()->habilidad->habilidad_arquero + ($jugador->getJugador()->habilidad->habilidad_quite / 2)) > $aux_sumatoria_habilidad) {
                                $aux_sumatoria_habilidad = $jugador->getJugador()->habilidad->habilidad_arquero + ($jugador->getJugador()->habilidad->habilidad_quite / 2);
                                $aux_id_reemplazante = $jugador->getJugador()->id;
                            }
                            
                        }
                    }
                }
            } else { // Tengo que reemplazar un jugador de campo
                // Busco un jugador que juege en la misma posición y lado
                foreach ($this->getJugadores() as $jugador) {
                    if ($jugador->getActivo() == false && $jugador->getDisponible() == true && $jugador->getPosicion() == $this->obtenerJugador($idJugadorReemplazar)->getPosicion() && $jugador->getLado() == $this->obtenerJugador($idJugadorReemplazar)->getLado()) {
                        // Encontré jugador que coincida con la posición y el lado del jugador a reemplazar
                        $aux_id_reemplazante = $jugador->getJugador()->id;
                        break;
                    }
                }
                if ($aux_id_reemplazante == 0) { // Si aún no encontré reemplazante
                    // Busco un jugador que juege en la misma posición
                    foreach ($this->getJugadores() as $jugador) {
                        if ($jugador->getActivo() == false && $jugador->getDisponible() == true && $jugador->getPosicion() == $this->obtenerJugador($idJugadorReemplazar)->getPosicion()) {
                            // Encontré jugador que coincida con la posición y el lado del jugador a reemplazar
                            $aux_id_reemplazante = $jugador->getJugador()->id;
                            break;
                        }
                    }
                }
                // Busco un jugador que se adecue a la posición
                if ($aux_id_reemplazante == 0) { // Si aún no encontré reemplazante
                    // Busco un jugador que se adecúe a la posción
                    foreach ($this->getJugadores() as $jugador) {
                        if ($jugador->getActivo() == false && $jugador->getDisponible() == true) {
                            switch ($this->obtenerJugador($idJugadorReemplazar)->getPosicion()) {
                                case 'DF':
                                    if (($jugador->getJugador()->habilidad->habilidad_quite + ($jugador->getJugador()->habilidad->habilidad_pase / 2)) > $aux_sumatoria_habilidad) {
                                        $aux_sumatoria_habilidad = $jugador->getJugador()->habilidad->habilidad_quite + ($jugador->getJugador()->habilidad->habilidad_pase / 2);
                                        $aux_id_reemplazante = $jugador->getJugador()->id;
                                    }
                                    break;
                                case 'MD':
                                    if (($jugador->getJugador()->habilidad->habilidad_pase + ($jugador->getJugador()->habilidad->habilidad_quite / 2)) > $aux_sumatoria_habilidad) {
                                        $aux_sumatoria_habilidad = $jugador->getJugador()->habilidad->habilidad_pase + ($jugador->getJugador()->habilidad->habilidad_quite / 2);
                                        $aux_id_reemplazante = $jugador->getJugador()->id;
                                    }
                                    break;
                                case 'MC':
                                    if (($jugador->getJugador()->habilidad->habilidad_pase + (($jugador->getJugador()->habilidad->habilidad_quite + $jugador->getJugador()->habilidad->habilidad_tiro) / 2)) > $aux_sumatoria_habilidad) {
                                        $aux_sumatoria_habilidad = $jugador->getJugador()->habilidad->habilidad_pase + (($jugador->getJugador()->habilidad->habilidad_quite + $jugador->getJugador()->habilidad->habilidad_tiro) / 2);
                                        $aux_id_reemplazante = $jugador->getJugador()->id;
                                    }
                                    break;
                                case 'MO':
                                    if (($jugador->getJugador()->habilidad->habilidad_pase + ($jugador->getJugador()->habilidad->habilidad_tiro / 2)) > $aux_sumatoria_habilidad) {
                                        $aux_sumatoria_habilidad = $jugador->getJugador()->habilidad->habilidad_pase + ($jugador->getJugador()->habilidad->habilidad_tiro / 2);
                                        $aux_id_reemplazante = $jugador->getJugador()->id;
                                    }
                                    break;
                                case 'DL':
                                    if (($jugador->getJugador()->habilidad->habilidad_tiro + ($jugador->getJugador()->habilidad->habilidad_pase / 2)) > $aux_sumatoria_habilidad) {
                                        $aux_sumatoria_habilidad = $jugador->getJugador()->habilidad->habilidad_tiro + ($jugador->getJugador()->habilidad->habilidad_pase / 2);
                                        $aux_id_reemplazante = $jugador->getJugador()->id;
                                    }
                                    break;
                                default:
                                    # code...
                                    break;
                            }
                        }
                    }
                }
            }
        }
        return $aux_id_reemplazante;
    }

    /**
     * Retorna al jugador encargado de patear los penales
     * 
     * @return JugadorPartido
     */
    public function obtenerPateadorPenales(): JugadorPartido
    {
        return $this->obtenerJugador($this->getIdPateadorPenales());
    }

    /**
     * Obtiene el indice en el array de jugadores del mejor pateador (el que más valor de tiro tiene) de los jugadores activos
     */
    public function buscarPateadorPenales(): void
    {
        $aux_id_pateador = 0;
        $aux_habilidad_tiro_pateador = 0;
        foreach ($this->getJugadores() as $jugador) {  // Recorro los jugadores
            if ($jugador->getActivo()) { // Controlo si está activo
                if ($jugador->getJugador()->habilidad->habilidad_tiro * $jugador->getFatiga() > $aux_habilidad_tiro_pateador) { //Controlo la habilidad
                    $aux_id_pateador = $jugador->getJugador()->id;
                    $aux_habilidad_tiro_pateador = $jugador->getJugador()->habilidad->habilidad_tiro * $jugador->getFatiga();
                }
            }
        }
        $this->setIdPateadorPenales($aux_id_pateador);
    }

    /**
     * Reemplaza al jugador $idReemplazado del equipo por $idReemplazante, asignandole la $posicion
     * 
     * @param int $idReemplazado
     * @param int $idReemplazante
     * @param string $posicion
     * @param string $lado
     * @param int $tipoCambio - 0 = normal, 1 = por expulsión (arquero), 2 = por lesión
     * @return bool
     */
    public function sustituirJugador(int $idReemplazado, int $idReemplazante, string $posicion = null, string $lado = null, int $tipoCambio = 0): bool
    {
        // Si no recibo $posicion, asigno la posicion del reemplazado
        if ($posicion != null && $lado != null) { // Si recibí posición y lado, lo asigno
            $this->obtenerJugador($idReemplazante)->setPosicion($posicion);
            $this->obtenerJugador($idReemplazante)->setLado($lado);
        } else { // No recibí posición o lado, asigno el del suplente
            $this->obtenerJugador($idReemplazante)->setPosicion($this->obtenerJugador($idReemplazado)->getPosicion());
            $this->obtenerJugador($idReemplazante)->setLado($this->obtenerJugador($idReemplazado)->getLado());
        }
        $this->obtenerJugador($idReemplazante)->setActivo(true);
        // Quito al jugador reemplazado
        $this->quitarJugador($idReemplazado, $tipoCambio);
        $this->setSustituciones($this->getSustituciones() + 1);
        return true;
    }

    /**
     * Deshabilita un jugador y dependiendo de la deshabilitación suma estadística
     * 
     * @param int $idJugador
     * @param int $tipoDeshabilitacion: 0 = cambio normal, 1 = expulsión, 2 = lesion
     * @return bool
     */
    public function quitarJugador(int $idJugador, int $tipoDeshabilitacion): bool
    {
        if ($tipoDeshabilitacion == 1) { // Expulsión
            //
        } elseif ($tipoDeshabilitacion == 2) { // Lesión    
            $this->obtenerJugador($idJugador)->setLesionado(true);
        }
        $this->obtenerJugador($idJugador)->setActivo(false);
        $this->obtenerJugador($idJugador)->setDisponible(false);
        return true;
    }

    /**
     * Mueve al jugador $idJugador a una nueva $posicion y $lado, retorna true si pudo hacer el cambio
     * 
     * @param int $idJugador
     * @param string $nuevaPosicion
     * @param string $nuevoLado
     * @return bool
     */
    public function cambiarPosicionJugador(int $idJugador, string $nuevaPosicion, string $nuevoLado): bool
    {
        $aux_posicion = Config::get('vlf.partido.posiciones_jugador.'. $nuevaPosicion);
        $aux_cambio_valido = true;
        if ($nuevaPosicion == 'AR') { // Si la posición es arquero, valido que no haya otro activo
            if ($this->obtenerArquero() != null) { // Si existe arquero activo, el cambio no es válido
                $aux_cambio_valido = false;
            }
        }
        if ($aux_cambio_valido) {
            if ($aux_posicion != null) {
                $this->obtenerJugador($idJugador)->setPosicion($nuevaPosicion);
                $this->obtenerJugador($idJugador)->setLado($nuevoLado);
                return true;
            }
        }
        return false;
    }

    /**
     * Retorna la cantidad de jugadores que juegan en la $posicion recibida
     * 
     * @param string $posicion
     * @return int
     */
    public function obtenerCantidadJugadoresPosicion(string $posicion): int
    {
        $cantidad_jugadores = 0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == $posicion && $jugador->getActivo()) {
                $cantidad_jugadores += 1;
            }
        }
        return $cantidad_jugadores;
    }

    /**
     * Retorna un string con la alineación en formato numérico del equipo
     * 
     * @return string
     */
    public function obtenerAlineacionNumerica(): string
    {
        $aux_alineacion = "";
        $aux_cantidad_df = $this->obtenerCantidadJugadoresPosicion('DF');
        $aux_cantidad_md = $this->obtenerCantidadJugadoresPosicion('MD');
        $aux_cantidad_mc = $this->obtenerCantidadJugadoresPosicion('MC');
        $aux_cantidad_mo = $this->obtenerCantidadJugadoresPosicion('MO');
        $aux_cantidad_dl = $this->obtenerCantidadJugadoresPosicion('DL');
        if ($aux_cantidad_df) {
            $aux_alineacion = $aux_alineacion . $aux_cantidad_df;
        }
        if ($aux_cantidad_md) {
            $aux_alineacion = $aux_alineacion . ' ' . $aux_cantidad_md;
        }
        if ($aux_cantidad_mc) {
            $aux_alineacion = $aux_alineacion . ' ' . $aux_cantidad_mc;
        }
        if ($aux_cantidad_mo) {
            $aux_alineacion = $aux_alineacion . ' ' . $aux_cantidad_mo;
        }
        if ($aux_cantidad_dl) {
            $aux_alineacion = $aux_alineacion . ' ' . $aux_cantidad_dl;
        }
        return $aux_alineacion;
    }

    /**
     * Retorna un array con los jugadores iniciales del equipo
     * 
     * @return array
     */
    public function obtenerJugadoresIniciales(): array
    {
        $aux_jugadores = [];
        $aux_contador = 0;
        // Busco arquero
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'AR' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco laterales izquierdos
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'DF' && $jugador->getLado() == 'I' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco defensores centrales
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'DF' && $jugador->getLado() == 'C' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco laterales derechos
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'DF' && $jugador->getLado() == 'D' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco medicampistas defensivos
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'MD' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . 'C ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco medicampistas izquierdos
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'MC' && $jugador->getLado() == 'I' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco medicampistas centrales
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'MC' && $jugador->getLado() == 'C' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco medicampistas derechos
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'MC' && $jugador->getLado() == 'D' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco medicampistas ofensivos
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'MO' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . 'C ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco delanteros izquierdos
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'DL' && $jugador->getLado() == 'I' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco delanteros centrales
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'DL' && $jugador->getLado() == 'C' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        // Busco delanteros derechos
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getPosicion() == 'DL' && $jugador->getLado() == 'D' && $jugador->getActivo()) {
                $aux_contador += 1;
                $aux_jugadores = Arr::add($aux_jugadores, $aux_contador, $jugador->getPosicion() . $jugador->getLado() . ' ' . $jugador->getJugador()->getNombreApellido());
            }
        }
        return $aux_jugadores;
    }

    /**
     * Retorna la sumatoria de una estadística del equipo
     * 
     * @param EstadisticasJugadorPartido (ENUM) $estadistica
     * @return int
     */
    public function obtenerEstadistica(ENUMEstaditicas $estadistica): int
    {
        $aux_suma_estaditica = 0;
        foreach ($this->getJugadores() as $jugador) {
            switch ($estadistica) {
                case ENUMEstaditicas::MINUTOS:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::MINUTOS);
                    break;
                case ENUMEstaditicas::ATAJADAS:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::ATAJADAS);
                    break;
                case ENUMEstaditicas::QUITES:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::QUITES);
                    break;
                case ENUMEstaditicas::PASES_CLAVE:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::PASES_CLAVE);
                    break;
                case ENUMEstaditicas::TIROS:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::TIROS);
                    break;
                case ENUMEstaditicas::GOLES:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::GOLES);
                    break;
                case ENUMEstaditicas::FALTAS:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::FALTAS);
                    break;
                case ENUMEstaditicas::ASISTENCIAS:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::ASISTENCIAS);
                    break;
                case ENUMEstaditicas::TARJETAS_AMARILLAS:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::TARJETAS_AMARILLAS);
                    break;
                case ENUMEstaditicas::TARJETAS_ROJAS:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::TARJETAS_ROJAS);
                    break;
                case ENUMEstaditicas::TIROS_AL_ARCO:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::TIROS_AL_ARCO);
                    break;
                case ENUMEstaditicas::TIROS_AFUERA:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::TIROS_AFUERA);
                    break;
                case ENUMEstaditicas::GOLES_CONCEDIDOS:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::GOLES_CONCEDIDOS);
                    break;
                case ENUMEstaditicas::PROGRESO_ARQUERO:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::PROGRESO_ARQUERO);
                    break;
                case ENUMEstaditicas::PROGRESO_QUITE:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::PROGRESO_QUITE);
                    break;
                case ENUMEstaditicas::PROGRESO_PASE:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::PROGRESO_PASE);
                    break;
                case ENUMEstaditicas::PROGRESO_TIRO:
                    $aux_suma_estaditica += $jugador->getEstadisticas()->obtenerEstadistica(ENUMEstaditicas::PROGRESO_TIRO);
                    break;
                default:
                    break;
            }
        }   
        return $aux_suma_estaditica;
    }

    /**
     * Retorna la cantidad de jugadores que se lesionaron en el equipo
     * 
     * @return int
     */
    public function obtenerCantidadLesionados(): int
    {
        $aux_contador = 0;
        foreach ($this->getJugadores() as $jugador) {
            if ($jugador->getLesionado()) {
                $aux_contador += 1;
            }
        }
        return $aux_contador;
    }

    /**
     * GETTERS Y SETTERS
     */
    public function getEquipo(): Equipo
    {
        return $this->equipo;
    }
    public function setEquipo(Equipo $equipo)
    {
        $this->equipo = $equipo;
    }
    public function getTactica(): string
    {
        return $this->tactica;
    }
    public function setTactica(string $tactica)
    {
        $this->tactica = $tactica;
    }
    public function getJugadores(): array
    {
        return $this->jugadores;
    }
    public function setJugadores(array $jugadores)
    {
        $this->jugadores = $jugadores;
    }
    public function getIdPateadorPenales(): int
    {
        return $this->idPateadorPenales;
    }
    public function setIdPateadorPenales(int $idPateadorPenales)
    {
        $this->idPateadorPenales = $idPateadorPenales;
    }
    public function getOK(): bool
    {
        return $this->ok;
    }
    public function setOK(bool $ok)
    {
        $this->ok = $ok;
    }
    public function getLocalia(): bool
    {
        return $this->localia;
    }
    public function setLocalia(bool $localia)
    {
        $this->localia = $localia;
    }
    public function getScore(): float
    {
        return $this->score;
    }
    public function setScore(bool $score)
    {
        $this->score = $score;
    }
    public function getSustituciones(): int
    {
        return $this->sustituciones;
    }
    public function setSustituciones(bool $sustituciones)
    {
        $this->sustituciones = $sustituciones;
    }
}
