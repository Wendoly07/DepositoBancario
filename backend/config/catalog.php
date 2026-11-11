<?php
/**
 * Catálogo de países y bancos (modo sin base de datos).
 * Los IDs coinciden con database/schema.sql para migrar sin cambios.
 * Para ocultar un banco: 'active' => false
 */
return [
    'countries' => [
        ['id' => 1, 'code' => 'HN', 'slug' => 'honduras',    'name' => 'Honduras',    'language' => 'es', 'currency' => 'HNL'],
        ['id' => 2, 'code' => 'SV', 'slug' => 'el-salvador', 'name' => 'El Salvador', 'language' => 'es', 'currency' => 'USD'],
        ['id' => 3, 'code' => 'NI', 'slug' => 'nicaragua',   'name' => 'Nicaragua',   'language' => 'es', 'currency' => 'NIO'],
        ['id' => 4, 'code' => 'GY', 'slug' => 'guyana',      'name' => 'Guyana',      'language' => 'en', 'currency' => 'GYD'],
        ['id' => 5, 'code' => 'SR', 'slug' => 'suriname',    'name' => 'Suriname',    'language' => 'en', 'currency' => 'SRD'],
        ['id' => 6, 'code' => 'LC', 'slug' => 'st-lucia',    'name' => 'St. Lucia',   'language' => 'en', 'currency' => 'XCD'],
    ],
    'banks' => [
        // Honduras (lista del formulario)
        1  => ['country' => 'honduras', 'name' => 'Banco Atlántida'],
        2  => ['country' => 'honduras', 'name' => 'BAC Credomatic Honduras'],
        3  => ['country' => 'honduras', 'name' => 'Banco Ficohsa'],
        4  => ['country' => 'honduras', 'name' => 'Banpaís'],
        // El Salvador (VALIDAR con el negocio)
        5  => ['country' => 'el-salvador', 'name' => 'Banco Agrícola'],
        6  => ['country' => 'el-salvador', 'name' => 'BAC Credomatic El Salvador'],
        7  => ['country' => 'el-salvador', 'name' => 'Banco Cuscatlán'],
        8  => ['country' => 'el-salvador', 'name' => 'Banco Davivienda'],
        // Nicaragua (VALIDAR)
        9  => ['country' => 'nicaragua', 'name' => 'BANPRO'],
        10 => ['country' => 'nicaragua', 'name' => 'BAC Credomatic Nicaragua'],
        11 => ['country' => 'nicaragua', 'name' => 'LAFISE Bancentro'],
        12 => ['country' => 'nicaragua', 'name' => 'Banco Ficohsa Nicaragua'],
        13 => ['country' => 'nicaragua', 'name' => 'BDF'],
        // Guyana (VALIDAR)
        14 => ['country' => 'guyana', 'name' => 'Republic Bank (Guyana)'],
        15 => ['country' => 'guyana', 'name' => 'GBTI'],
        16 => ['country' => 'guyana', 'name' => 'Demerara Bank'],
        17 => ['country' => 'guyana', 'name' => 'Citizens Bank Guyana'],
        // Suriname (VALIDAR)
        18 => ['country' => 'suriname', 'name' => 'De Surinaamsche Bank (DSB)'],
        19 => ['country' => 'suriname', 'name' => 'Hakrinbank'],
        20 => ['country' => 'suriname', 'name' => 'Finabank'],
        21 => ['country' => 'suriname', 'name' => 'Republic Bank Suriname'],
        // St. Lucia (VALIDAR)
        22 => ['country' => 'st-lucia', 'name' => 'Bank of Saint Lucia'],
        23 => ['country' => 'st-lucia', 'name' => '1st National Bank St. Lucia'],
        24 => ['country' => 'st-lucia', 'name' => 'Republic Bank (EC)'],
        25 => ['country' => 'st-lucia', 'name' => 'CIBC Caribbean'],
    ],
];
