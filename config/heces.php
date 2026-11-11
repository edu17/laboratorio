<?php

return [
    'exam_name' => 'Examen de heces',
    'category' => 'heces_orina',
    'source' => 'Hoja HECES del archivo 2025.xlsx',
    'clinical_validation_status' => 'pending',
    'sections' => [
        [
            'id' => 'heces.macroscopico',
            'code' => 'macroscopico',
            'name' => 'MACROSCÓPICO',
            'parameters' => [
                ['id' => 'color', 'name' => 'Color', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 10],
                ['id' => 'aspecto', 'name' => 'Aspecto', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 20],
                ['id' => 'restos_alimenticios', 'name' => 'R. alimenticios', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 30],
                ['id' => 'moco', 'name' => 'Moco', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 40],
                ['id' => 'sangre', 'name' => 'Sangre', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 50],
                ['id' => 'pseudoparasitos', 'name' => 'Pseudoparásitos', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 60],
            ],
        ],
        [
            'id' => 'heces.microscopico',
            'code' => 'microscopico',
            'name' => 'MICROSCÓPICO',
            'parameters' => [
                ['id' => 'celulas_vegetales', 'name' => 'C. vegetal', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 10],
                ['id' => 'jabon', 'name' => 'Jabón', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 20],
                ['id' => 'grasa', 'name' => 'Grasa', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 30],
                ['id' => 'almidon', 'name' => 'Almidón', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 40],
                ['id' => 'levaduras', 'name' => 'Levaduras', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 50],
                ['id' => 'eritrocitos', 'name' => 'Eritrocitos', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 60],
                ['id' => 'leucocitos', 'name' => 'Leucocitos', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 70],
                ['id' => 'cristales_charcot', 'name' => 'Crist. de charcot.', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 80],
                ['id' => 'fibra_muscular', 'name' => 'Fibra muscular', 'type' => 'text', 'unit' => null, 'reference' => null, 'order' => 90],
            ],
        ],
    ],
    'parasitological_matrix' => [
        'id' => 'heces.parasitologico',
        'code' => 'parasitologico',
        'name' => 'MATRIZ PARASITOLÓGICA',
        'columns' => ['Organismo', 'Quiste', 'Trofozoíto', 'Huevo', 'Larva'],
        'organisms' => [
            'Entamoeba histolytica',
            'Entamoeba coli',
            'Endolimax nana',
            'Trichomonas hominis',
            'Giardia lamblia',
            'Iodamoeba bütschlii',
            'Ascaris lumbricoides',
            'Retortamonas intestinalis',
            'Blastocystis hominis',
            'Chilomastix mesnili',
            'Otros',
        ],
    ],
];
