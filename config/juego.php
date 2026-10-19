<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comunas disponibles para el registro (Fase 1)
    |--------------------------------------------------------------------------
    |
    | El extracto de OpenStreetMap importado en esta fase cubre solo la Región
    | del Biobío, así que el jugador únicamente puede elegir una comuna de esa
    | lista como punto de partida. Se amplía a otras regiones cuando se
    | importen sus extractos.
    */
    'comunas_disponibles' => [
        'Concepción',
        'Talcahuano',
        'Hualpén',
        'Chiguayante',
        'San Pedro de la Paz',
        'Coronel',
        'Penco',
        'Tomé',
        'Los Ángeles',
        'Chillán',
    ],

    /*
    |--------------------------------------------------------------------------
    | Economía
    |--------------------------------------------------------------------------
    */
    'creditos_iniciales' => (int) env('JUEGO_CREDITOS_INICIALES', 5000),

    /*
    |--------------------------------------------------------------------------
    | Misiones
    |--------------------------------------------------------------------------
    */
    'radio_generacion_mision_metros' => (int) env('JUEGO_RADIO_GENERACION_MISION_METROS', 3000),

    /*
    | Tope de misiones simultáneas por jugador (RF-21): la cantidad de
    | edificios que tiene, más este número extra. Un jugador con 2 edificios
    | y el valor por defecto (1) puede tener hasta 3 misiones activas a la
    | vez. En fases futuras el tope "crece con el rango del jugador" (ERS);
    | por ahora crece con sus edificios.
    */
    'tope_misiones_extra_por_jugador' => (int) env('JUEGO_TOPE_MISIONES_EXTRA_POR_JUGADOR', 1),

    /*
    | Cantidad mínima de edificios que necesita un jugador para que se le
    | generen misiones que exigen un vehículo especial (cualquier
    | especialidad distinta de "agua": rescate, escala, materiales
    | peligrosos o mando). Con menos edificios que este número, solo se le
    | generan misiones que se cubren con el carro de agua básico.
    */
    'edificios_minimos_mision_especial' => (int) env('JUEGO_EDIFICIOS_MINIMOS_MISION_ESPECIAL', 3),

    /*
    | Límites globales de la recompensa en créditos de cualquier misión
    | (RF-23): ningún tipo de misión puede pagar fuera de este rango, sin
    | importar lo que diga su `creditos_min`/`creditos_max` propio ni cuánto
    | haya tardado en completarse. Ver Mision::calcularRecompensaCreditos().
    */
    'recompensa_mision_minima' => (int) env('JUEGO_RECOMPENSA_MISION_MINIMA', 250),
    'recompensa_mision_maxima' => (int) env('JUEGO_RECOMPENSA_MISION_MAXIMA', 500),

    /*
    | Tiempo de resolución (RF-24): apenas llega la primera unidad de un
    | tipo que la misión pide —cubra o no todos sus requisitos todavía—
    | arranca este cronómetro fijo de 3 minutos para todas las misiones
    | por igual.
    |
    | Mientras siga faltando alguna unidad solicitada, se suma este extra
    | por cada una que falte (p. ej. una misión que pide 2 y solo tiene 1
    | presente arranca con 3 min + 1 min = 4 min). Una vez que llegan las
    | que faltaban y la misión queda totalmente cubierta por primera vez,
    | en vez de recalcular desde cero se recorta el tiempo que quedaba en
    | este porcentaje. Ya cubierta, cada unidad de más que llegue después
    | descuenta un tramo fijo de segundos al tiempo que falta. Un piso
    | mínimo evita que se resuelva al instante en cualquiera de los casos.
    | Ver Mision::calcularTiempoResolucionSegundos() y
    | Mision::calcularResuelveEnTrasLlegada().
    */
    'tiempo_resolucion_base_segundos' => (int) env('JUEGO_TIEMPO_RESOLUCION_BASE_SEGUNDOS', 180),
    'tiempo_extra_por_unidad_faltante_segundos' => (int) env('JUEGO_TIEMPO_EXTRA_POR_UNIDAD_FALTANTE_SEGUNDOS', 60),
    'descuento_al_completar_cobertura' => (float) env('JUEGO_DESCUENTO_AL_COMPLETAR_COBERTURA', 0.3),
    'descuento_tiempo_resolucion_por_unidad_segundos' => (int) env('JUEGO_DESCUENTO_TIEMPO_RESOLUCION_POR_UNIDAD_SEGUNDOS', 20),
    'tiempo_resolucion_minimo_segundos' => (int) env('JUEGO_TIEMPO_RESOLUCION_MINIMO_SEGUNDOS', 30),

    /*
    |--------------------------------------------------------------------------
    | Factor de velocidad del tiempo de juego
    |--------------------------------------------------------------------------
    |
    | Multiplica la velocidad a la que transcurren los tiempos de la
    | simulación (límites de misión, resolución, viajes). Usado en pruebas y
    | demostraciones para acelerar el juego sin cambiar las reglas.
    */
    'factor_velocidad_tiempo' => (float) env('JUEGO_FACTOR_VELOCIDAD_TIEMPO', 1),

    /*
    |--------------------------------------------------------------------------
    | Servicios geoespaciales autoalojados
    |--------------------------------------------------------------------------
    */
    'osrm' => [
        'base_url' => env('OSRM_BASE_URL', 'http://127.0.0.1:5000'),

        /*
        | Las unidades de emergencia circulan más rápido que el tránsito
        | normal. En vez de usar la duración "de tránsito normal" que
        | devuelve OSRM, se recalcula tramo a tramo con la velocidad que
        | OSRM asume para cada calle (según su tipo y límite) más este
        | extra, en km/h.
        */
        'velocidad_extra_kmh' => (float) env('JUEGO_OSRM_VELOCIDAD_EXTRA_KMH', 10),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'http://127.0.0.1:8080'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Infraestructura
    |--------------------------------------------------------------------------
    |
    | Un edificio empieza con los estacionamientos base de su tipo (ver
    | tipos_edificio.estacionamientos_base, hoy 2 para el Cuartel de
    | Bomberos) y puede ampliarlos de a 1 por vez hasta este tope, pagando
    | este costo por cada ampliación (Edificio::estacionamientosTotales()).
    */
    'estacionamientos_maximos' => (int) env('JUEGO_ESTACIONAMIENTOS_MAXIMOS', 15),
    'costo_ampliacion_estacionamiento' => (int) env('JUEGO_COSTO_AMPLIACION_ESTACIONAMIENTO', 1000),

];
