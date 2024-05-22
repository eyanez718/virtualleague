<?php

namespace App\Enums\VLT;

enum PuntosGameNormalTexto: string
{
   case CERO = '0';
   case QUINCE = '15';
   case TREINTA = '30';
   case CUARENTA = '40';
   case VENTAJA = 'Ad.';
   case GAME = "G";

   /**
    * Indica el valor del enum consultado
    * 
    * @param string $nombre
    *
    * @return string
    */
   public static function getValorPorNombre(string $nombre): string
   {
      foreach (self::cases() as $puntuacion) {
         if( $nombre === $puntuacion->name ){
            return $puntuacion->value;
         }
      }
      throw new \ValueError("$nombre is not a valid backing value for enum " . self::class );
   }
}
