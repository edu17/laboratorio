<?php

/*
 * Exámenes cualitativos tomados de la zona impresa (columnas A–J) de las hojas vigentes (1–39) de 2025.xlsx.
 * El laboratorio usa estos formatos actualmente, por eso se cargan como validados.
 */

$negativoPositivo = ['Negativo', 'Positivo'];

return [
    'source' => '2025.xlsx',
    'exams' => [
        [
            'code' => 'VIH',
            'name' => 'Análisis VIH',
            'sheet' => 'VIH1',
            'allows_partial_request' => false,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Resultado', 'parameters' => [
                    ['code' => 'vih', 'name' => 'VIH', 'type' => 'select', 'options' => ['No reactivo', 'Reactivo']],
                ]],
            ],
        ],
        [
            'code' => 'VDRL',
            'name' => 'VDRL (Sífilis)',
            'sheet' => 'VDRL',
            'allows_partial_request' => false,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Resultado', 'parameters' => [
                    ['code' => 'vdrl', 'name' => 'VDRL (Sífilis)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'HCG_EMBARAZO',
            'name' => 'HCG (Embarazo)',
            'sheet' => 'HCG',
            'allows_partial_request' => false,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Resultado', 'parameters' => [
                    ['code' => 'hcg', 'name' => 'HCG (Embarazo)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'GRUPO_RH',
            'name' => 'Grupo sanguíneo y factor Rh',
            'sheet' => 'FACTORH',
            'allows_partial_request' => false,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Exámenes especiales', 'parameters' => [
                    ['code' => 'grupo_sanguineo', 'name' => 'Grupo sanguíneo', 'type' => 'select', 'options' => ['A', 'B', 'AB', 'O']],
                    ['code' => 'factor_rh', 'name' => 'Factor Rh', 'type' => 'positive_negative', 'options' => ['Positivo', 'Negativo']],
                ]],
            ],
        ],
        [
            'code' => 'ROTA_ADENO',
            'name' => 'Rotavirus / Adenovirus',
            'sheet' => 'ROTA',
            'allows_partial_request' => true,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Resultado', 'parameters' => [
                    ['code' => 'rotavirus', 'name' => 'Rotavirus', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'adenovirus', 'name' => 'Adenovirus', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'DENGUE',
            'name' => 'Dengue (Sangre)',
            'sheet' => 'DENGUE1',
            'allows_partial_request' => true,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Resultado', 'parameters' => [
                    ['code' => 'dengue_igg', 'name' => 'IgG', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'dengue_igm', 'name' => 'IgM', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'dengue_ns1', 'name' => 'NS1', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'HEPATITIS',
            'name' => 'Hepatitis',
            'sheet' => 'HEPATITIS AA',
            'allows_partial_request' => true,
            'sections' => [
                ['code' => 'hepatitis_c', 'name' => 'Hepatitis C', 'parameters' => [
                    ['code' => 'hepatitis_c_igm', 'name' => 'IgM', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
                ['code' => 'hepatitis_a', 'name' => 'Hepatitis A', 'parameters' => [
                    ['code' => 'hepatitis_a_igm', 'name' => 'IgM', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'HPYLORI_SANGRE',
            'name' => 'Helicobacter pylori (Sangre)',
            'sheet' => 'PYLORI S',
            'allows_partial_request' => false,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Resultado', 'parameters' => [
                    ['code' => 'hpylori_sangre', 'name' => 'Helicobacter pylori en sangre', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'SANGRE_OCULTA',
            'name' => 'Sangre oculta en heces',
            'sheet' => 'PYLORI H',
            'allows_partial_request' => false,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Resultado', 'parameters' => [
                    ['code' => 'sangre_oculta', 'name' => 'Sangre oculta en heces', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'TORCH_IGM',
            'name' => 'TORCH - IgM',
            'sheet' => 'TORCH1',
            'allows_partial_request' => true,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Exámenes especiales', 'parameters' => [
                    ['code' => 'toxoplasma_igm', 'name' => '(TOXO) Toxoplasma IgM', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'rubeola_igm', 'name' => '(RUB) Rubéola IgM', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'citomegalovirus_igm', 'name' => '(CMV) Citomegalovirus IgM', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'herpes_simple_igm', 'name' => '(HSV 1/2) Herpes simple 1/2 IgM', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'TOXICOLOGICO',
            'name' => 'Toxicológico',
            'sheet' => 'TOXICOLOGICO1',
            'allows_partial_request' => true,
            'sections' => [
                ['code' => 'panel_drogas', 'name' => 'Panel de drogas', 'parameters' => [
                    ['code' => 'amfetaminas', 'name' => 'Amfetaminas (AMP)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'barbituricos', 'name' => 'Barbitúricos (BAR 300)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'benzodiacepinas', 'name' => 'Benzodiacepinas (BZO 200)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'cocaina', 'name' => 'Cocaína (COC)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'mariguana', 'name' => 'Mariguana (THC)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'metanfetamina', 'name' => 'Metanfetamina (MET 500)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'mdma', 'name' => 'Metilendioximetanfetamina (MDMA 500)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'morfina', 'name' => 'Morfina (MOP 300)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'metadona', 'name' => 'Metadona (MTD 300)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                    ['code' => 'antidepresivos_triciclicos', 'name' => 'Antidepresivos tricíclicos (TCA)', 'type' => 'positive_negative', 'options' => $negativoPositivo],
                ]],
            ],
        ],
        [
            'code' => 'FANA',
            'name' => 'Autoinmunidad',
            'sheet' => 'FOB',
            'allows_partial_request' => false,
            'sections' => [
                ['code' => 'resultado', 'name' => 'Autoinmunidad', 'parameters' => [
                    [
                        'code' => 'fana',
                        'name' => 'Ac. Anti-Nucleares (FANA Fluorescencia)',
                        'type' => 'text',
                        'reference' => 'Negativo: Menor a Dilución 1:80 Sustrato HEP-2000',
                    ],
                ]],
            ],
        ],
    ],
];
