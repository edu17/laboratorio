<?php

/*
 * Paneles cuantitativos tomados de 2025.xlsx.
 * - Validado: el valor está en la zona impresa (columnas A–J) de una hoja del libro vigente.
 * - Pendiente: el valor solo aparece en el borrador (columnas K en adelante) o está en duda.
 * Los análisis que ya no aparecen en el libro no se cargan: el laboratorio ya no los realiza.
 *   Se guarda como candidato y no se imprime hasta que el laboratorio lo confirme
 *   (ver el documento "Pendientes de validación con el laboratorio").
 */

$pending = 'pending';

$number = fn (string $code, string $name, ?string $unit, ?string $reference, array $config = [], string $status = 'validated'): array => [
    'code' => $code,
    'name' => $name,
    'type' => 'number',
    'unit' => $unit,
    'reference' => $reference,
    'reference_config' => $config === [] ? null : $config,
    'clinical_validation_status' => $status,
];

$text = fn (string $code, string $name, ?string $unit, ?string $reference, string $status = 'validated'): array => [
    'code' => $code,
    'name' => $name,
    'type' => 'text',
    'unit' => $unit,
    'reference' => $reference,
    'clinical_validation_status' => $status,
];

$antibodyIndex = 'Negativo: Menor de 0.9 UI/mL. Dudoso: 0.9 a 1.1 UI/mL. Positivo: Mayor de 1.1 UI/mL';

return [
    'source' => '2025.xlsx',
    'category' => 'cuantitativas',
    'exams' => [
        [
            'code' => 'QUIMICA',
            'name' => 'Química sanguínea',
            'sheet' => 'QUIMICAS',
            'allows_partial_request' => true,
            'sort_order' => 300,
            'sections' => [
                ['code' => 'quimica_clinica', 'name' => 'Química clínica', 'parameters' => [
                    $number('glucosa_pre', 'Glucosa pre', 'mg/dL', '70 - 110 mg/dL', ['min' => '70', 'max' => '110'], $pending),
                    $number('glucosa_post', 'Glucosa post', 'mg/dL', '70 - 110 mg/dL', ['min' => '70', 'max' => '110'], $pending),
                    $number('colesterol_total', 'Colesterol total', 'mg/dL', '150 - 219 mg/dL', ['min' => '150', 'max' => '219'], $pending),
                    $number('trigliceridos', 'Triglicéridos', 'mg/dL', '50 - 149 mg/dL', ['min' => '50', 'max' => '149'], $pending),
                    $number('colesterol_hdl', 'Colesterol HDL', 'mg/dL', '> 35 mg/dL', ['min' => '35'], $pending),
                    $number('colesterol_ldl', 'Colesterol LDL', 'mg/dL', '< 130 mg/dL', ['max' => '130'], $pending),
                    $number('colesterol_vldl', 'Colesterol VLDL', 'mg/dL', 'Hasta 40 mg/dL', ['max' => '40'], $pending),
                    $number('acido_urico', 'Ácido úrico', 'mg/dL', null, [
                        'texto_femenino' => 'Mujeres: 3.0 - 5.5 mg/dL', 'min_femenino' => '3.0', 'max_femenino' => '5.5',
                        'texto_masculino' => 'Hombres: 4.0 - 7.0 mg/dL', 'min_masculino' => '4.0', 'max_masculino' => '7.0',
                    ], $pending),
                    $number('proteinas_totales', 'Proteínas totales', 'g/dL', '6.7 - 8.3 g/dL', ['min' => '6.7', 'max' => '8.3'], $pending),
                ]],
                ['code' => 'pruebas_renales', 'name' => 'Pruebas renales', 'parameters' => [
                    // La zona impresa solo trae el rango de hombres; el de mujeres (0.40 - 0.80) está pendiente.
                    $number('creatinina', 'Creatinina', 'mg/dL', null, [
                        'texto_masculino' => 'Hombres: 0.60 - 1.10 mg/dL', 'min_masculino' => '0.60', 'max_masculino' => '1.10',
                    ]),
                    $number('bun', 'BUN', 'mg/dL', '8.0 - 23.0 mg/dL', ['min' => '8.0', 'max' => '23.0'], $pending),
                ]],
            ],
        ],
        [
            'code' => 'HEPATICAS',
            'name' => 'Pruebas hepáticas',
            'sheet' => 'QUIMICAS',
            'allows_partial_request' => true,
            'sort_order' => 310,
            'sections' => [
                ['code' => 'pruebas_hepaticas', 'name' => 'Pruebas hepáticas', 'parameters' => [
                    $number('bilirrubina_total', 'Bilirrubina total', 'mg/dL', '0.1 - 1.2 mg/dL', ['min' => '0.1', 'max' => '1.2'], $pending),
                    $number('bilirrubina_directa', 'Bilirrubina directa', 'mg/dL', '0.1 - 0.4 mg/dL', ['min' => '0.1', 'max' => '0.4'], $pending),
                    $number('bilirrubina_indirecta', 'Bilirrubina indirecta', 'mg/dL', 'Hasta 0.9 mg/dL', ['max' => '0.9'], $pending),
                    $number('tgo', 'TGO', 'U/L', '8 - 38 U/L', ['min' => '8', 'max' => '38'], $pending),
                    $number('tgp', 'TGP', 'U/L', '4 - 44 U/L', ['min' => '4', 'max' => '44'], $pending),
                    $number('ggt', 'Gamma glutamil transferasa (GGT)', 'U/L', '16 - 73 U/L', ['min' => '16', 'max' => '73'], $pending),
                    $number('fosfatasa_alcalina', 'Fosfatasa alcalina', 'U/L', null, [
                        'texto_masculino' => 'Hombres: 40 a 129 U/L', 'min_masculino' => '40', 'max_masculino' => '129',
                    ], $pending),
                    $number('albumina', 'Albúmina', 'g/dL', '3.4 - 5.4 g/dL', ['min' => '3.4', 'max' => '5.4'], $pending),
                ]],
            ],
        ],
        [
            'code' => 'ENZIMAS',
            'name' => 'Enzimas',
            'sheet' => 'INMUNOLOGIA',
            'allows_partial_request' => true,
            'sort_order' => 320,
            'sections' => [
                ['code' => 'enzimas', 'name' => 'Enzimas', 'parameters' => [
                    $number('deshidrogenasa_lactica', 'Deshidrogenasa láctica (DHL)', 'U/L', '106 - 211 U/L', ['min' => '106', 'max' => '211']),
                    $number('amilasa', 'Amilasa', 'U/L', '37 - 125 U/L', ['min' => '37', 'max' => '125'], $pending),
                    $number('lipasa', 'Lipasa', 'U/L', '13 - 42 U/L', ['min' => '13', 'max' => '42'], $pending),
                ]],
            ],
        ],
        [
            'code' => 'ELECTROLITOS',
            'name' => 'Electrolitos y minerales',
            'sheet' => 'LITROS',
            'allows_partial_request' => true,
            'sort_order' => 330,
            'sections' => [
                ['code' => 'electrolitos', 'name' => 'Electrolitos y minerales', 'parameters' => [
                    $number('sodio', 'Sodio', 'mmol/L', '136 a 149 mmol/L', ['min' => '136', 'max' => '149']),
                    $number('potasio', 'Potasio', 'mmol/L', '3.8 a 5.0 mmol/L', ['min' => '3.8', 'max' => '5.0']),
                    $number('cloruro', 'Cloruro', 'mmol/L', '98 a 106 mmol/L', ['min' => '98', 'max' => '106']),
                    $number('calcio', 'Calcio', 'mg/dL', '8.5 - 10.5 mg/dL', ['min' => '8.5', 'max' => '10.5'], $pending),
                    $number('magnesio', 'Magnesio', 'mg/dL', 'Adultos: 1.6 a 2.6 mg/dL', ['min' => '1.6', 'max' => '2.6'], $pending),
                    $number('fosforo', 'Fósforo', 'mg/dL', 'Adultos: 2.5 a 4.5 mg/dL', ['min' => '2.5', 'max' => '4.5'], $pending),
                ]],
            ],
        ],
        [
            'code' => 'TIROIDEAS',
            'name' => 'Pruebas tiroideas',
            'sheet' => 'TIROIDEAS',
            'allows_partial_request' => true,
            'sort_order' => 340,
            'sections' => [
                ['code' => 'tiroideas', 'name' => 'Tiroideas', 'parameters' => [
                    $number('t3', 'T3', 'ng/mL', 'Valor normal 0.8 - 2.0 ng/mL', ['min' => '0.8', 'max' => '2.0']),
                    $number('t4', 'T4', 'µg/dL', 'Valor normal 6.0 - 12.0 µg/dL', ['min' => '6.0', 'max' => '12.0']),
                    $number('tsh', 'TSH', 'µUI/mL', 'Valor normal 0.5 - 5.0 µUI/mL', ['min' => '0.5', 'max' => '5.0']),
                ]],
            ],
        ],
        [
            'code' => 'TIROIDEAS_LIBRES',
            'name' => 'Pruebas tiroideas libres',
            'sheet' => 'TIROLIBRES',
            'allows_partial_request' => true,
            'sort_order' => 350,
            'sections' => [
                ['code' => 'tiroideas_libres', 'name' => 'Tiroideas', 'parameters' => [
                    $number('t3_libre', 'T3 libre', 'pg/mL', 'Adultos: de 1.82 a 4.2 pg/mL', ['min' => '1.82', 'max' => '4.2']),
                    $number('t4_libre', 'T4 libre', 'ng/dL', 'Adultos: de 0.87 a 1.85 ng/dL', ['min' => '0.87', 'max' => '1.85']),
                    $number('tsh', 'TSH', 'µUI/mL', 'Valor normal 0.5 - 5.0 µUI/mL', ['min' => '0.5', 'max' => '5.0']),
                ]],
            ],
        ],
        [
            'code' => 'COAGULACION',
            'name' => 'Coagulación',
            'sheet' => 'tiempos1',
            'allows_partial_request' => true,
            'sort_order' => 360,
            'sections' => [
                ['code' => 'coagulacion', 'name' => 'Coagulación', 'parameters' => [
                    $number('tiempo_protrombina', 'Tiempo de protrombina (TP)', 'segundos', '10 a 15 segundos', ['min' => '10', 'max' => '15']),
                    $number('actividad_protrombina', 'Actividad en %', '%', '60 a 130 %', ['min' => '60', 'max' => '130']),
                    $number('inr', 'INR', null, '0.85 a 1.30', ['min' => '0.85', 'max' => '1.30']),
                    $number('tiempo_tromboplastina_parcial', 'Tiempo de tromboplastina parcial (TTP)', 'segundos', '28 a 40 segundos', ['min' => '28', 'max' => '40']),
                ]],
            ],
        ],
        [
            'code' => 'FERROCINETICAS',
            'name' => 'Pruebas ferrocinéticas',
            'sheet' => 'ferritina',
            'allows_partial_request' => true,
            'sort_order' => 370,
            'sections' => [
                ['code' => 'ferrocineticas', 'name' => 'Pruebas ferrocinéticas', 'parameters' => [
                    $number('vitamina_b12', 'Vitamina B-12', 'pg/mL', '177 a 771 pg/mL', ['min' => '177', 'max' => '771']),
                    // La zona impresa solo trae el rango de mujeres; el de hombres (20 a 400) está pendiente.
                    $number('ferritina', 'Ferritina', 'ng/mL', null, [
                        'texto_femenino' => 'Mujeres: 13 a 150 ng/mL', 'min_femenino' => '13', 'max_femenino' => '150',
                    ]),
                    $number('hierro_serico', 'Hierro sérico', 'µg/dL', null, [
                        'texto_masculino' => 'Hombres: 61 a 157 µg/dL', 'min_masculino' => '61', 'max_masculino' => '157',
                    ], $pending),
                    $number('transferrina', 'Transferrina', 'mg/dL', '200 a 360 mg/dL', ['min' => '200', 'max' => '360'], $pending),
                ]],
            ],
        ],
        [
            'code' => 'HBA1C',
            'name' => 'Hemoglobina glicosilada (HbA1c)',
            'sheet' => 'HBA1C',
            'allows_partial_request' => false,
            'sort_order' => 380,
            'sections' => [
                ['code' => 'hba1c', 'name' => 'Pruebas cuantificadas', 'parameters' => [
                    $number('hba1c_mmol_mol', 'Hemoglobina glicosilada (HbA1c)', 'mmol/mol', '24 - 48 mmol/mol', ['min' => '24', 'max' => '48']),
                    $number('hba1c_porcentaje', 'Hemoglobina glicosilada (HbA1c)', '%', '4.5 - 6.5 %', ['min' => '4.5', 'max' => '6.5']),
                ]],
            ],
        ],
        [
            'code' => 'FACTOR_REUMATOIDEO',
            'name' => 'Factor reumatoideo (FR)',
            'sheet' => 'FR',
            'allows_partial_request' => false,
            'sort_order' => 390,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Pruebas cuantificadas', 'parameters' => [
                    $number('factor_reumatoideo', 'Factor reumatoideo (FR)', 'IU/mL', 'Hasta 15 IU/mL', ['max' => '15']),
                ]],
            ],
        ],
        [
            'code' => 'ASO',
            'name' => 'Antiestreptolisina "O" (ASO)',
            'sheet' => 'ASO',
            'allows_partial_request' => false,
            'sort_order' => 400,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Pruebas cuantificadas', 'parameters' => [
                    $number('aso', 'Antiestreptolisina "O" (ASO)', 'IU/mL', 'Hasta 160 IU/mL', ['max' => '160']),
                ]],
            ],
        ],
        [
            'code' => 'PCR',
            'name' => 'Proteína C reactiva (PCR)',
            'sheet' => 'PC1R',
            'allows_partial_request' => false,
            'sort_order' => 410,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Pruebas cuantificadas', 'parameters' => [
                    $number('pcr', 'Proteína C reactiva (PCR)', 'mg/L', '< 10 mg/L', ['max' => '10']),
                ]],
            ],
        ],
        [
            'code' => 'BHCG_CUANTITATIVA',
            'name' => 'B-HcG cuantitativa',
            'sheet' => 'BHCGCUANT',
            'allows_partial_request' => false,
            'sort_order' => 420,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Pruebas cuantificadas', 'parameters' => [
                    $number('bhcg', 'B-HcG', 'mIU/mL', 'Negativo: < 5.00 mIU/mL. Positivo: > 5.00 mIU/mL. '
                        .'Rango por semanas (mIU/mL): 3: 5 - 8; 4: 5 - 426; 5: 18 - 7,340; 6: 1,080 - 54,500; '
                        .'7 a 8: 7,650 - 229,000; 9 a 12: 25,700 - 288,000; 13 a 16: 13,300 - 254,000; '
                        .'17 a 24: 4,060 - 165,400; 25 a 40: 3,640 - 117,000'),
                ]],
            ],
        ],
        [
            'code' => 'HPYLORI_HECES',
            'name' => 'Helicobacter pylori en heces (cuantitativo)',
            'sheet' => 'PYL.CUAN.',
            'allows_partial_request' => false,
            'sort_order' => 430,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Pruebas cuantificadas', 'parameters' => [
                    // Se escribe como en el formato actual, por ejemplo "7.87 COI (Pos)".
                    $text('hpylori_heces', 'Helicobacter pylori heces', null, 'Negativo: < 1.00 COI. Positivo: > 1.00 COI'),
                ]],
            ],
        ],
        [
            'code' => 'VITAMINA_D',
            'name' => 'Vitamina D total',
            'sheet' => 'VITAMINA D',
            'allows_partial_request' => false,
            'sort_order' => 440,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Pruebas cuantificadas', 'parameters' => [
                    $number('vitamina_d', 'Vitamina D total', 'ng/mL', '< 10 ng/mL: Deficiencia. 10 - 30 ng/mL: Insuficiencia. 30 - 100 ng/mL: Suficiencia'),
                ]],
            ],
        ],
        [
            'code' => 'DHEA_SO4',
            'name' => 'Dehidroepiandrosterona sulfato (DHEA-SO4)',
            'sheet' => '(DHEA-SO4)',
            'allows_partial_request' => false,
            'sort_order' => 450,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Pruebas cuantificadas', 'parameters' => [
                    // La zona impresa solo trae la tabla de hombres; la de mujeres está pendiente.
                    $number('dhea_so4', 'Dehidroepiandrosterona sulfato (DHEA-SO4)', 'µg/dL', null, [
                        'texto_masculino' => 'Hombres: 10 a 14 años: 24.4 a 247. 15 a 19 años: 70.2 a 492. 20 a 24 años: 211 a 492. '
                            .'25 a 34 años: 160 a 449. 35 a 44 años: 88.9 a 427. 45 a 54 años: 44.3 a 331. '
                            .'55 a 64 años: 51.7 a 295. 65 a 74 años: 6 a 249. Mayor o igual a 75 años: 16.2 a 123',
                    ]),
                ]],
            ],
        ],
        [
            'code' => 'HERPES_SIMPLE',
            'name' => 'Herpes simple',
            'sheet' => 'TORCH1',
            'allows_partial_request' => true,
            'sort_order' => 470,
            'sections' => [
                ['code' => 'herpes_simple', 'name' => 'Herpes simple', 'parameters' => [
                    $number('herpes_igm_tipo_2', 'Herpes simplex IgM tipo 2', 'UI/mL', $antibodyIndex, [], $pending),
                    $number('herpes_igg_tipo_2', 'Herpes simplex IgG tipo 2', 'UI/mL', $antibodyIndex, [], $pending),
                ]],
            ],
        ],
        [
            'code' => 'VSG',
            'name' => 'Velocidad de sedimentación',
            'sheet' => 'VS1',
            'allows_partial_request' => false,
            'sort_order' => 480,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Velocidad de sedimentación', 'parameters' => [
                    $number('velocidad_sedimentacion', 'Velocidad de sedimentación', 'mm/h', '0 a 20 mm en 1 hora', ['min' => '0', 'max' => '20']),
                ]],
            ],
        ],
        [
            'code' => 'HEMATOCRITO_HEMOGLOBINA',
            'name' => 'Hematocrito y hemoglobina',
            'sheet' => 'HTCHBN',
            'allows_partial_request' => true,
            'sort_order' => 490,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Exámenes especiales', 'parameters' => [
                    $number('hematocrito', 'Hematocrito', '%', '36 - 48', ['min' => '36', 'max' => '48']),
                    $number('hemoglobina', 'Hemoglobina', 'g/dL', '12 - 16 g/dL', ['min' => '12', 'max' => '16']),
                ]],
            ],
        ],
        [
            'code' => 'MARCADORES_TUMORALES',
            'name' => 'Marcadores tumorales',
            'sheet' => 'tumorales',
            'allows_partial_request' => true,
            'sort_order' => 500,
            'sections' => [
                ['code' => 'marcadores_tumorales', 'name' => 'Marcadores tumorales', 'parameters' => [
                    $number('psa_total', 'Antígeno prostático específico total', 'ng/mL', 'Normal o bajo riesgo: 0 a 4 ng/mL. Mediano riesgo: 4 a 10 ng/mL. Alto riesgo: mayor de 10 ng/mL', ['max' => '4']),
                    $number('psa_libre', 'Antígeno prostático fracción libre', 'ng/mL', '0.26 a 4.14 ng/mL', ['min' => '0.26', 'max' => '4.14']),
                    $number('psa_relacion_libre_total', 'Relación L/T', '%', 'Mayor a 25 %: probabilidad de hiperplasia benigna. 11 a 25 %: probabilidad mediana de neoplasia prostática. Menor a 11 %: probabilidad alta de neoplasia prostática'),
                    $number('ca_15_3', 'CA 15-3 (Carcinoma mamario)', 'U/mL', 'Hasta 30 U/mL', ['max' => '30'], $pending),
                    $number('cea', 'Antígeno carcinoembrionario (CEA)', 'ng/mL', 'No fumadores: 0 a 5 ng/mL. Fumadores: 0 a 10 ng/mL', [], $pending),
                ]],
            ],
        ],
        [
            'code' => 'HORMONAS',
            'name' => 'Hormonas',
            // Solo quedan en el borrador de QUIMICAS (testosterona libre) e INMUNOLOGIA (cortisol, ACTH e insulina).
            'sheet' => 'INMUNOLOGIA',
            'allows_partial_request' => true,
            'sort_order' => 510,
            'sections' => [
                ['code' => 'fertilidad', 'name' => 'Hormonas de la fertilidad', 'parameters' => [
                    $number('testosterona_libre', 'Testosterona libre', 'pg/mL', null, [
                        'texto_masculino' => 'Hombres 18 a 60 años: 15 a 70 pg/mL',
                    ], $pending),
                ]],
                ['code' => 'suprarrenales', 'name' => 'Cortisol y ACTH', 'parameters' => [
                    $number('cortisol_am', 'Cortisol AM', 'µg/dL', '4.82 a 19.5 µg/dL', ['min' => '4.82', 'max' => '19.5'], $pending),
                    $number('acth', 'Hormona adrenocorticotrópica (ACTH)', 'pg/mL', '7.2 a 63.3 pg/mL', ['min' => '7.2', 'max' => '63.3'], $pending),
                ]],
                ['code' => 'insulina', 'name' => 'Insulina', 'parameters' => [
                    $number('insulina_preprandial', 'Insulina preprandial 0 minutos', 'µUI/mL', '2.6 a 28.4 µUI/mL', ['min' => '2.6', 'max' => '28.4'], $pending),
                ]],
            ],
        ],
        [
            'code' => 'PRO_BNP',
            'name' => 'Pro BNP-NT (Péptido natriurético tipo B)',
            'sheet' => 'ferritina',
            'allows_partial_request' => false,
            'sort_order' => 520,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Cardiacas', 'parameters' => [
                    $number('pro_bnp_nt', 'Pro BNP-NT (Péptido natriurético tipo B)', 'pg/mL', 'Enfermedad cardiaca no aguda: menor de 125 pg/mL. Enfermedad cardiaca aguda: menores de 50 años: 300 a 450; 50 a 75 años: 450 a 900; mayores de 75 años: 900 a 1800. Todos en pg/mL', [], $pending),
                ]],
            ],
        ],
    ],
];
