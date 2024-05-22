<?php

namespace App\Enums\VLF;

enum AccionesPartido: string
{
   case HIZO_TIRO = 'HIZO_TIRO';
   case HIZO_FALTA = 'HIZO_FALTA';
   case HIZO_QUITE = 'HIZO_QUITE';
   case HIZO_ASISTENCIA = 'HIZO_ASISTENCIA';

   /**
    * Indica el valor del enum consultado
    * 
    * @param string $nombre
    *
    * @return string
    */
   public static function getValorPorNombre(string $nombre): string
   {
      foreach (self::cases() as $accion) {
         if( $nombre === $accion->name ){
            return $accion->value;
         }
      }
      throw new \ValueError("$nombre is not a valid backing value for enum " . self::class );
   }
}
