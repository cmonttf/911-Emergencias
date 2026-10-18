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
    'tope_misiones_simultaneas' => (int) env('JUEGO_TOPE_MISIONES_SIMULTANEAS', 3),

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
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'http://127.0.0.1:8080'),
    ],

];
