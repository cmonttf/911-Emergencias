<?php

namespace App\Dominio\Economia\Enums;

/**
 * Motivos de movimiento de créditos contemplados en la Fase 1. Cada motivo
 * corresponde a una regla de negocio concreta de otro contexto de dominio.
 */
enum MotivoTransaccion: string
{
    case CreditoInicial = 'credito_inicial';
    case ConstruccionEdificio = 'construccion_edificio';
    case CompraVehiculo = 'compra_vehiculo';
    case RecompensaMision = 'recompensa_mision';
}
