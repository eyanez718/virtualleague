<?php

namespace App\Enums\VLF;

enum ComentariosPartido: string
{
   case FALTA_1 = '{j1} agarra de la camiseta a {j2}';
   case FALTA_2 = '{j2} es barrido por {j1}';
   case FALTA_3 = '{j1} obstruye el paso de {j2}';
   case FALTA_4 = '{j1} baja de atrás a {j1}';
   case GOL_1 = '1';
   case GOL_2 = '2';
   case GOL_3 = '3';
   case GOL_4 = '5';

   /**
    * Indica el valor del enum consultado
    * 
    * @param string $nombre
    *
    * @return string
    */
   public static function getComentarioFalta(): string
   {
      foreach (self::cases() as $accion) {
         if(str_contains($accion->name, 'FALTA')){
            return $accion->value;
         }
      }
      throw new \ValueError("$nombre is not a valid backing value for enum " . self::class );
   }
}
