<?php

namespace App\Enums\VLF;

enum TarjetasPartido: string
{
   case AMARILLA = 'AMARILLA';
   case ROJA = 'ROJA';

   /**
    * Indica el valor del enum consultado
    * 
    * @param string $nombre
    *
    * @return string
    */
   public static function getValorPorNombre(string $nombre): string
   {
      foreach (self::cases() as $tarjeta) {
         if( $nombre === $tarjeta->name ){
            return $tarjeta->value;
         }
      }
      throw new \ValueError("$nombre is not a valid backing value for enum " . self::class );
   }
}
