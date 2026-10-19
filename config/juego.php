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

];
