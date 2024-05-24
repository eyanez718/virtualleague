<?php

namespace App\Enums\VLF;
use Illuminate\Support\Arr;

enum ComentariosPartido: string
{
   // Chance de gol
   // {m} = minuto, {e} = abreviatura, {j} = jugador
   case CHANCE_INDIVIDUAL_1 = 'Mín. {m} :({e}) {j} con el regate';
   case CHANCE_INDIVIDUAL_2 = 'Mín. {m} :({e}) {j} toma posesión';
   case CHANCE_INDIVIDUAL_3 = 'Mín. {m} :({e}) {j} atraviesa la defensa';
   case CHANCE_INDIVIDUAL_4 = 'Mín. {m} :({e}) {j} encuentra un hueco en la defensa';
   case CHANCE_INDIVIDUAL_5 = 'Mín. {m} :({e}) {j} aprovecha un error defensivo';
   case CHANCE_INDIVIDUAL_6 = 'Mín. {m} :({e}) {j} encuentra el camino';
   case CHANCE_INDIVIDUAL_7 = 'Mín. {m} :({e}) {j} esquiva a su marcador';
   case CHANCE_INDIVIDUAL_8 = 'Mín. {m} :({e}) {j} con un movimiento llamativo';
   case CHANCE_INDIVIDUAL_9 = 'Mín. {m} :({e}) {j} supera a su marcador';
   case CHANCE_INDIVIDUAL_10 = 'Mín. {m} :({e}) {j} con una gran habilidad';
   case CHANCE_INDIVIDUAL_11 = 'Mín. {m} :({e}) {j} avanza hacia adelante';
   case CHANCE_INDIVIDUAL_12 = 'Mín. {m} :({e}) {j} se encuentra en una buena posición';
   // Chance de gol asistida
   // {m} = minuto, {e} = abreviatura, {j} = jugador, {a} = asistidor
   case CHANCE_ASISTIDA_1 = 'Min. {m}: ({e}) {a} le pasa el balón a {j}';
   case CHANCE_ASISTIDA_2 = 'Min. {m}: ({e}) {a} con un pase inteligente a {j}';
   case CHANCE_ASISTIDA_3 = 'Min. {m}: ({e}) {a} encuentra {j} en el area';
   case CHANCE_ASISTIDA_4 = 'Min. {m}: ({e}) {a} con un pase preciso a {j}';
   case CHANCE_ASISTIDA_5 = 'Min. {m}: ({e}) {a} cabecea el balón hacia {j}';
   case CHANCE_ASISTIDA_6 = 'Min. {m}: ({e}) {a} desliza la pelota hacia {j}';
   case CHANCE_ASISTIDA_7 = 'Min. {m}: ({e}) {a} le corta el balón a {j}';
   case CHANCE_ASISTIDA_8 = 'Min. {m}: ({e}) {a} con el taco pasa a {j}';
   case CHANCE_ASISTIDA_9 = 'Min. {m}: ({e}) {a} le juega un balón largo a {j}';
   case CHANCE_ASISTIDA_10 = 'Min. {m}: ({e}) {a} con un glorioso pase largo a {j}';
   // Quite
   // {j} = jugador
   case QUITE_1 = '           ... Borrado por {j}';
   case QUITE_2 = '           ... Bloqueado por {j}';
   case QUITE_3 = '           ... {j} gana el balón con una entrada clara';
   case QUITE_4 = '           ... Interceptado por {j}';
   case QUITE_5 = '           ... {j} se interpone y gana el balón';
   case QUITE_6 = '           ... Pero {j} despeja el peligro';
   case QUITE_7 = '           ... Pero {j} despeja el balón a un lugar seguro';
   case QUITE_8 = '           ... Pero {j} gana el balón con una barrida';
   case QUITE_9 = '           ... Pero {j} gana la disputa';
   case QUITE_10 = '           ... Pero {j} lee bien la situación y gana el balón';
   // Tiro
   // {j} = jugador
   case TIRO_1 = '           ... ¡Un disparo potente de {j}!';
   case TIRO_2 = '           ... ¡{j} intenta vencer al portero!';
   case TIRO_3 = '           ... {j} con el remate!';
   case TIRO_4 = '           ... ¡{j} dispara hacia la portería!';
   case TIRO_5 = '           ... ¡{j} intenta pasar el balón por encima del portero!';
   case TIRO_6 = '           ... {j} con el tiro!';
   case TIRO_7 = '           ... {j} lo intenta, ¡debe anotar!';
   case TIRO_8 = '           ... {j} mano a mano con el portero, ¡dispara!';
   case TIRO_9 = '           ... ¡{j} va a portería!';
   case TIRO_10 = '           ... ¡Un disparo fuerte de {j}!';
   // Atajada
   // {j} = jugador
   case ATAJADA_1 = '           ... Atajado por {j}';
   case ATAJADA_2 = '           ... {j} la ataja cómodamente';
   case ATAJADA_3 = '           ... {j} realiza una cómoda atajada';
   case ATAJADA_4 = '           ... Pero {j} ataja bien';
   case ATAJADA_5 = '           ... {j} hace una buena atajada';
   case ATAJADA_6 = '           ... {j} la detiene';
   case ATAJADA_7 = '           ... {j} hace una atajada difícil';
   case ATAJADA_8 = '           ... Pero {j} con la difícil atajada';
   case ATAJADA_9 = '           ... Pero {j} alcanza la pelota. Buena atajada';
   case ATAJADA_10 = '           ... Pero {j} la despeja con un puñetazo';
   // Tiros afuera
   case TIRO_AFUERA_1 = '           ... Pero se va desviado';
   case TIRO_AFUERA_2 = '           ... Pero se sale de la cancha';
   case TIRO_AFUERA_3 = '           ... Sobre el travesaño';
   case TIRO_AFUERA_4 = '           ... Se va desviado para un saque de arco';
   case TIRO_AFUERA_5 = '           ... Pero recorta el tiro y se desvía';
   case TIRO_AFUERA_6 = '           ... Pero roza el palo';
   case TIRO_AFUERA_7 = '           ... Pero se pasa un poco';
   case TIRO_AFUERA_8 = '           ... Pero se le va ancha';
   case TIRO_AFUERA_9 = '           ... Pero la tira a la tribuna';
   case TIRO_AFUERA_10 = '           ... Fuera del arco';
   // Gol
   case GOL_CONVERTIDO_1 = '           ... GOL!!!';
   case GOL_CONVERTIDO_2 = '           ... Golazo!!!';
   case GOL_CONVERTIDO_3 = '           ... Que pedazo de gol!!!';
   case GOL_CONVERTIDO_4 = '           ... La clavó, gol!';
   // Gol anulado
   case GOL_ANULADO_1 = '           ... Pero el gol fué anulado. El juez de línea levantó su bandera';
   case GOL_ANULADO_2 = '           ... Pero el gol fué anulado. El árbitro vio algo';
   case GOL_ANULADO_3 = '           ... Pero el gol fué anulado. El juez de línea señala fuera de juego';
   case GOL_ANULADO_4 = '           ... Pero el gol fué anulado por el VAR. La pelota no había ingresado';
   case GOL_ANULADO_5 = '           ... Pero el gol fué anulado por el VAR. El jugador estaba en fuera de juego';
   // Falta
   // {m} = minuto, {e} = equipo, {j} = jugador
   case FALTA_1 = 'Mín. {m} :({e}) {j} con la falta';
   case FALTA_2 = 'Mín. {m} :({e}) {j} con una falta fuerte';
   case FALTA_3 = 'Mín. {m} :({e}) {j} comete otra faltas';
   case FALTA_4 = 'Mín. {m} :({e}) {j} con el agarrón';
   case FALTA_5 = 'Mín. {m} :({e}) {j} con una falta fea';
   // Penal
   // {j} = jugador
   case PENAL_1 = '           ... PENAL! {j} lo pateará';
   case PENAL_2 = '           ... PENAL! {j} asume la responsabilidad';
   case PENAL_3 = '           ... PENAL! {j} agarra la pelota';
   // Advertencia jugador
   case ADVERTENCIA_1 = '           ... Es advertido por el árbitro';
   case ADVERTENCIA_2 = '           ... El árbitro lo llama para charlar';
   case ADVERTENCIA_3 = '           ... El árbitro lo advierte';
   // Lesion
   // {m} = minuto, {e} = equipo, {j} = jugador
   case LESION_1 = 'Mín. {m}: ({e}) {j} herido. No puede continuar';
   case LESION_2 = 'Mín. {m}: ({e}) {j} herido. Lo han sacado en camilla';
   case LESION_3 = 'Mín. {m}: ({e}) {j} cae mal. No puede continuar';
   // Cambiar posición
   // {m} = minuto, {e} = equipo, {j} = jugador, {p} = posicion
   case CAMBIO_POSICION_1 = 'Min. {m} :({e}) {j} jugará ahora de {p}';
   // Cambio de jugador
   // {m} = minuto, {e} = equipo, {j} = jugador, {c} = cambio, {p} = posicion
   case SUSTITUCION_1 = 'Min. {m}: ({e}) {c} reemplazará {j} y jugará de {p}';
   // Equipo sin sustituciones
   case NO_QUEDAN_SUSTITUCIONES_1 = '          ...  El equipo se quedó sin cambios. Jugarán con un jugador menos';
   // Tarjeta amarilla
   case TARJETA_AMARILLA_1 = '           ... Recibe una tarjeta amarilla';
   case TARJETA_AMARILLA_2 = '           ... Se le muestra una tarjeta amarilla';
   case TARJETA_AMARILLA_3 = '           ... El árbitro le muestra la tarjeta amarilla';
   // Segunda amarilla (expulsado)
   case SEGUNDA_AMARILLA_1 = '           ... ¡Es su segunda amarilla! ¡Expulsado!';
   case SEGUNDA_AMARILLA_2 = '           ... ¡Su segunda amarilla en el partido! ¡Expulsado!';
   case SEGUNDA_AMARILLA_3 = '           ... ¡Recibe la segunda amarilla en el partido! ¡Expulsado!';
   // Tarjeta roja
   case TARJETA_ROJA_1 = '           ... ¡El árbitro lo expulsa de la cancha!';
   case TARJETA_ROJA_2 = '           ... ¡Es tarjeta roja! Fin del partido para él';
   case TARJETA_ROJA_3 = '           ... ¡Le muestran la tarjeta roja!';
   // Comentarios genéricos
   case COMENTARIO_INICIO_PARTIDO_1 = '**********  INICIO DE PARTIDO  ***********';
   case COMENTARIO_MITAD_PARTIDO_1 = '*************  ENTRETIEMPO  **************';
   case COMENTARIO_FIN_PARTIDO_1 = '**********  FINAL DEL PARTIDO  ***********';
   case COMENTARIO_RESULTADO_1 = '           ... {e1} ({g1}) - ({g2}) {e2}';
   case COMENTARIO_TIEMPO_ANADIDO_1 = 'El árbitro agrega {m} minutos de añadido';

   // Comentarios aún no usados
   // {m} = minuto, {e} = equipo, {t} = táctica
   case CAMBIO_TACTICA_1 = 'Min. {m} :({e}) {e} will now play {t}';
   // Comentarios genéricos
   //[COMM_SHOTSOFFTARGET] {\nShots off target}
   //[COMM_SHOTSONTARGET] {\nShots on target}
   case COMENTARIO_TANDA_PENALES_1 = '***********  TANDA DE PENALES  ************';
   case GANADOR_TANTA_PENALES_1 = '           ...  {e} gana la tanda de penales'; // {e} = equipo
   //[COMM_SCORE] {\nScore}
   //[COMM_STATISTICS] {Player statistics for: %s}

   /**
    * Indica el valor del enum consultado
    * 
    * @param string $accion
    *
    * @return string
    */
   public static function getComentario(string $accion): string
   {
      $aux_comentarios = [];
      $aux_comentario = "";
      $i = 0;
      foreach (self::cases() as $comentario) {
         if(str_contains($comentario->name, $accion . '_')){
            $i += 1;
            $aux_comentarios = Arr::add($aux_comentarios, $i, $comentario->value);
         }
      }
      if (count($aux_comentarios) > 0) {
         $aux_comentario = Arr::random($aux_comentarios);
      }
      return $aux_comentario;
      throw new \ValueError("$nombre is not a valid backing value for enum " . self::class );
   }
}
