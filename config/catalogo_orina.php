<?php

/*
 * Examen de orina tomado de la zona impresa (columnas A–J) de la hoja ORINA de 2025.xlsx.
 * Los resultados se escriben como texto, igual que en el formato actual (por ejemplo "200 Ery/μl Hemolysis").
 */

$paired = ['report_layout' => 'paired'];

return [
    'source' => '2025.xlsx',
    'exams' => [
        [
            'code' => 'ORINA',
            'name' => 'Examen de orina',
            'sheet' => 'ORINA',
            'allows_partial_request' => false,
            'sort_order' => 20,
            'sections' => [
                ['code' => 'macroscopico', 'name' => 'Macroscópico', 'settings' => $paired, 'parameters' => [
                    ['code' => 'color', 'name' => 'Color', 'type' => 'text'],
                    ['code' => 'aspecto', 'name' => 'Aspecto', 'type' => 'text'],
                ]],
                ['code' => 'quimico', 'name' => 'Químico', 'settings' => $paired, 'parameters' => [
                    ['code' => 'ph', 'name' => 'pH', 'type' => 'text'],
                    ['code' => 'densidad', 'name' => 'Densidad', 'type' => 'text'],
                    ['code' => 'urobilinogeno', 'name' => 'Urobilinógeno', 'type' => 'text'],
                    ['code' => 'sangre', 'name' => 'Sangre', 'type' => 'text'],
                    ['code' => 'leucocitos_quimico', 'name' => 'Leucocitos', 'type' => 'text'],
                    ['code' => 'nitrito', 'name' => 'Nitrito', 'type' => 'text'],
                    ['code' => 'glucosa', 'name' => 'Glucosa', 'type' => 'text'],
                    ['code' => 'acido_ascorbico', 'name' => 'Ácido ascórbico', 'type' => 'text'],
                    ['code' => 'cetonas', 'name' => 'Cetonas', 'type' => 'text'],
                    ['code' => 'calcio', 'name' => 'Calcio', 'type' => 'text'],
                ]],
                ['code' => 'microscopico', 'name' => 'Microscópico', 'settings' => $paired, 'parameters' => [
                    ['code' => 'celulas_epiteliales', 'name' => 'Cél. epiteliales', 'type' => 'text'],
                    ['code' => 'leucocitos_microscopico', 'name' => 'Leucocitos', 'type' => 'text'],
                    ['code' => 'eritrocitos', 'name' => 'Eritrocitos', 'type' => 'text'],
                    ['code' => 'bacterias', 'name' => 'Bacterias', 'type' => 'text'],
                    ['code' => 'levaduras', 'name' => 'Levaduras', 'type' => 'text'],
                    ['code' => 'cristales', 'name' => 'Cristales', 'type' => 'text'],
                    ['code' => 'otros', 'name' => 'Otros', 'type' => 'text'],
                ]],
            ],
        ],
    ],
];
