<?php

namespace App\Enums\VLF;

enum EstadisticasJugadorPartido: string
{
   case MINUTOS = 'MINUTOS';
   case ATAJADAS = 'ATAJADAS';
   case QUITES = 'QUITES';
   case PASES_CLAVE = 'PASES_CLAVE';
   case TIROS = 'TIROS';
   case GOLES = 'GOLES';
   case FALTAS = 'FALTAS';
   case ASISTENCIAS = 'ASISTENCIAS';
   case TARJETAS_AMARILLAS = 'TARJETAS_AMARILLAS';
   case TARJETAS_ROJAS = 'TARJETAS_ROJAS';
   case TIROS_AL_ARCO = 'TIROS_AL_ARCO';
   case TIROS_AFUERA = 'TIROS_AFUERA';
   case GOLES_CONCEDIDOS = 'GOLES_CONCEDIDOS';
   case PROGRESO_ARQUERO = 'PROGRESO_ARQUERO';
   case PROGRESO_QUITE = 'PROGRESO_QUITE';
   case PROGRESO_PASE = 'PROGRESO_PASE';
   case PROGRESO_TIRO = 'PROGRESO_TIRO';
   
   /**
    * Indica el valor del enum consultado
    * 
    * @param string $nombre
    *
    * @return string
    */
   public static function getValorPorNombre(string $nombre): string
   {
      foreach (self::cases() as $estadistica) {
         if( $nombre === $estadistica->name ){
            return $estadistica->value;
         }
      }
      throw new \ValueError("$nombre is not a valid backing value for enum " . self::class );
   }
}
