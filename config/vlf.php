<?php

return [
    'partido' => [
        'numero_jugadores' => 11,
        'numero_suplentes' => 5,
        'bonus_localia' => 200,
        'sustituciones' => 3,
        'suplementario' => true,
        'penales' => true,
        'posiciones_jugador' => [
            'AR' => ['nombre' => 'Arquero'],
            'DF' => ['nombre' => 'Defensor'],
            'MD' => ['nombre' => 'Mediocampista defensivo'],
            'MC' => ['nombre' => 'Mediocampista'],
            'MO' => ['nombre' => 'Mediocampista ofensivo'],
            'DL' => ['nombre' => 'Delantero'],
        ],
        'tacticas' => [
            'N' => [
                'nombre' => 'Normal',
                'modificadores' => [
                    'DF' => [
                        'MULTI' => ['mod_quite' => 1.0, 'mod_pase' => 0.5, 'mod_tiro' => 0.3],
                    ],
                    'MD' => [
                        'MULTI' => ['mod_quite' => 0.85, 'mod_pase' => 0.75, 'mod_tiro' => 0.3],
                    ],
                    'MC' => [
                        'MULTI' => ['mod_quite' => 0.3, 'mod_pase' => 1.0, 'mod_tiro' => 0.3],
                    ],
                    'MO' => [
                        'MULTI' => ['mod_quite' => 0.3, 'mod_pase' => 0.85, 'mod_tiro' => 0.85]
                    ],
                    'DL' => [
                        'MULTI' => ['mod_quite' => 0.3, 'mod_pase' => 0.3, 'mod_tiro' => 1.0]
                    ],
                ],
            ],
            'D' => [
                'nombre' => 'Defensiva',
                'modificadores' => [
                    'DF' => [
                        'MULTI' => ['mod_quite' => 1.25, 'mod_pase' => 0.5, 'mod_tiro' => 0.25],
                        'BONUS_L' => ['mod_quite' => 0.25, 'mod_pase' => 0, 'mod_tiro' => 0],
                    ],
                    'MD' => [
                        'MULTI' => ['mod_quite' => 1.13, 'mod_pase' => 0.68, 'mod_tiro' => 0.25]
                    ],
                    'MC' => [
                        'MULTI' => ['mod_quite' => 1.0, 'mod_pase' => 0.75, 'mod_tiro' => 0.25]
                    ],
                    'MO' => [
                        'MULTI' => ['mod_quite' => 0.75, 'mod_pase' => 0.65, 'mod_tiro' => 0.5]
                    ],
                    'DL' => [
                        'MULTI' => ['mod_quite' => 0.5, 'mod_pase' => 0.25, 'mod_tiro' => 0.75]
                    ],
                ],
            ],
            'O' => [
                'nombre' => 'Ofensiva',
                'modificadores' => [
                    'DF' => [
                        'MULTI' => ['mod_quite' => 1.0, 'mod_pase' => 0.5, 'mod_tiro' => 0.5],
                    ],
                    'MD' => [
                        'MULTI' => ['mod_quite' => 0.5, 'mod_pase' => 0.75, 'mod_tiro' => 0.68],
                    ],
                    'MC' => [
                        'MULTI' => ['mod_quite' => 0.15, 'mod_pase' => 1.0, 'mod_tiro' => 0.75],
                    ],
                    'MO' => [
                        'MULTI' => ['mod_quite' => 0.0, 'mod_pase' => 0.87, 'mod_tiro' => 1.13],
                    ],
                    'DL' => [
                        'MULTI' => ['mod_quite' => 0.0, 'mod_pase' => 0.75, 'mod_tiro' => 1.5],
                    ],
                ],
            ],
            'P' => [
                'nombre' => 'Pases',
                'modificadores' => [
                    'DF' => [
                        'MULTI' => ['mod_quite' => 1.0, 'mod_pase' => 0.75, 'mod_tiro' => 0.3],
                    ],
                    'MD' => [
                        'MULTI' => ['mod_quite' => 0.87, 'mod_pase' => 0.87, 'mod_tiro' => 0.28],
                    ],
                    'MC' => [
                        'MULTI' => ['mod_quite' => 0.25, 'mod_pase' => 1.0, 'mod_tiro' => 0.25],
                        'BONUS_L' => ['mod_quite' => 0.5, 'mod_pase' => 0.0, 'mod_tiro' => 0.5],
                    ],
                    'MO' => [
                        'MULTI' => ['mod_quite' => 0.25, 'mod_pase' => 0.87, 'mod_tiro' => 0.68],
                    ],
                    'DL' => [
                        'MULTI' => ['mod_quite' => 0.25, 'mod_pase' => 0.75, 'mod_tiro' => 1.0],
                        'BONUS_L' => ['mod_quite' => 0.0, 'mod_pase' => 0.0, 'mod_tiro' => 0.25],
                    ],                    
                ],
            ], 
            'C' => [
                'nombre' => 'Contrataque',
                'modificadores' => [
                    'DF' => [
                        'MULTI' => ['mod_quite' => 1.0, 'mod_pase' => 0.5, 'mod_tiro' => 0.25],
                        'BONUS_O' => ['mod_quite' => 0.0, 'mod_pase' => 0.25, 'mod_tiro' => 0.25],
                        'BONUS_P' => ['mod_quite' => 0.0, 'mod_pase' => 0.25, 'mod_tiro' => 0.25],
                    ],
                    'MD' => [
                        'MULTI' => ['mod_quite' => 0.85, 'mod_pase' => 0.85, 'mod_tiro' => 0.25],
                    ],
                    'MC' => [
                        'MULTI' => ['mod_quite' => 0.5, 'mod_pase' => 1.0, 'mod_tiro' => 0.25],
                        'BONUS_O' => ['mod_quite' => 0.0, 'mod_pase' => 0.0, 'mod_tiro' => 0.5],
                        'BONUS_P' => ['mod_quite' => 0.0, 'mod_pase' => 0.0, 'mod_tiro' => 0.5],
                    ],
                    'MO' => [
                        'MULTI' => ['mod_quite' => 0.5, 'mod_pase' => 0.85, 'mod_tiro' => 0.65],
                    ],
                    'DL' => [
                        'MULTI' => ['mod_quite' => 0.5, 'mod_pase' => 0.5, 'mod_tiro' => 1.0],
                    ],
                ],
            ],
            'L' => [
                'nombre' => 'Juego largo',
                'modificadores' => [
                    'DF' => [
                        'MULTI' => ['mod_quite' => 1.0, 'mod_pase' => 0.25, 'mod_tiro' => 0.25],
                        'BONUS_C' => ['mod_quite' => 0.25, 'mod_pase' => 0.5, 'mod_tiro' => 0.0],
                    ],
                    'MD' => [
                        'MULTI' => ['mod_quite' => 0.75, 'mod_pase' => 0.85, 'mod_tiro' => 0.38],
                    ],
                    'MC' => [
                        'MULTI' => ['mod_quite' => 0.5, 'mod_pase' => 1.0, 'mod_tiro' => 0.5 ],
                    ],
                    'MO' => [
                        'MULTI' => ['mod_quite' => 0.45, 'mod_pase' => 0.85, 'mod_tiro' => 0.9],
                    ],
                    'DL' => [
                        'MULTI' => ['mod_quite' => 0.25, 'mod_pase' => 0.5, 'mod_tiro' => 1.3],
                    ],
                ],
            ],
        ],
    ],
];