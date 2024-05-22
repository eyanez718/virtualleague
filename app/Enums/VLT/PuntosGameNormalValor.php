<?php

namespace App\Enums\VLT;

enum PuntosGameNormalValor: int
{
   case CERO = 0;
   case QUINCE = 1;
   case TREINTA = 2;
   case CUARENTA = 3;
   case VENTAJA = 4;
   case GAME = 5;

   /**
    * Indica el valor del enum consultado
    * 
    * @param string $nombre
    *
    * @return int
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

   /**
    * Indica el nombre asociado al valor de un enum
    * 
    * @param int $valor
    *
    * @return string
    */
   public static function getNombrePorValor(int $valor): string
   {
      foreach (self::cases() as $puntuacion) {
         if( $valor === $puntuacion->value ){
            return $puntuacion->name;
         }
      }
      throw new \ValueError("$valor is not a valid backing value for enum " . self::class );
   }
}
