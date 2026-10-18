<?php

namespace App\Dominio\Economia\Enums;

/**
 * Tipos de moneda del libro de transacciones. En la Fase 1 solo se usan
 * créditos; la moneda premium se incorpora en la Fase 4 junto con los
 * rangos y las compras con moneda premium.
 */
enum Moneda: string
{
    case Creditos = 'creditos';
}
