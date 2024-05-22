<?php

namespace App\Enums\VLF;

enum EstadisticasEquipoPartido: string
{
   case GOLES = 'GOLES';
   case TIROS_AL_ARCO = 'TIROS_AL_ARCO';
   case TIROS_AFUERA = 'TIROS_AFUERA';
   case FALTAS = 'FALTAS';
   case SUSTITUCIONES = 'SUSTITUCIONES';
   case LESIONES = 'LESIONES';

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
