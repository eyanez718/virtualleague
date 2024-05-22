<?php

namespace App\Enums\VLF;

enum IndicadoresPartido: string
{
   case TARJETA_AMARILLA = 'TARJETA_AMARILLA';
   case TARJETA_ROJA = 'TARJETA_ROJA';
   case LESION = 'LESION';

   /**
    * Indica el valor del enum consultado
    * 
    * @param string $nombre
    *
    * @return string
    */
   public static function getValorPorNombre(string $nombre): string
   {
      foreach (self::cases() as $indicador) {
         if( $nombre === $indicador->name ){
            return $indicador->value;
         }
      }
      throw new \ValueError("$nombre is not a valid backing value for enum " . self::class );
   }
}
