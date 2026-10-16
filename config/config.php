<?php
// Configuración local de BeatCell. Se puede sobrescribir con variables de entorno.
return [
    'database' => [
        'host' => getenv('BEATCELL_DB_HOST') ?: 'localhost',
        'name' => getenv('BEATCELL_DB_NAME') ?: 'beatcell',
        'username' => getenv('BEATCELL_DB_USER') ?: 'root',
        'password' => getenv('BEATCELL_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
];
