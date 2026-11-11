<?php
/**
 * Configuración del frontend.
 * api_base: URL donde está publicada la carpeta backend/api
 *   - Azure App Service o local (php -S):  /backend/api
 *   - Backend en otro App Service:         https://mi-backend.azurewebsites.net/backend/api
 */
return [
    'api_base' => getenv('API_BASE') ?: '/backend/api',
    'brand'    => 'Apostemos',
    'timezone' => 'America/Tegucigalpa',
    'countries' => [
        'honduras'    => ['name' => 'Honduras',    'lang' => 'es', 'currency' => 'HNL', 'symbol' => 'L',   'flag' => '🇭🇳'],
        'el-salvador' => ['name' => 'El Salvador', 'lang' => 'es', 'currency' => 'USD', 'symbol' => '$',   'flag' => '🇸🇻'],
        'nicaragua'   => ['name' => 'Nicaragua',   'lang' => 'es', 'currency' => 'NIO', 'symbol' => 'C$',  'flag' => '🇳🇮'],
        'guyana'      => ['name' => 'Guyana',      'lang' => 'en', 'currency' => 'GYD', 'symbol' => 'G$',  'flag' => '🇬🇾'],
        'suriname'    => ['name' => 'Suriname',    'lang' => 'en', 'currency' => 'SRD', 'symbol' => 'SRD', 'flag' => '🇸🇷'],
        'st-lucia'    => ['name' => 'St. Lucia',   'lang' => 'en', 'currency' => 'XCD', 'symbol' => 'EC$', 'flag' => '🇱🇨'],
    ],
];
