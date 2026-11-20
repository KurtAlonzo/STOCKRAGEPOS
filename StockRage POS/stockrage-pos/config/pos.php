<?php

/*
 | POS size rules. Keys are the size name as a slug ("Family Tray" = family-tray).
 */
return [
    // Discount in percent for buying a bigger size (Solo has none). Change the numbers to taste, 0 = no discount.
    'discounts' => [
        'barkada' => 5,
        'family-tray' => 8,
        'party-tray' => 10,
    ],

    // Fewest pieces ONE flavor can have in a mix.
    'min_pieces' => [
        'barkada' => 2,
        'family-tray' => 5,
        'party-tray' => 10,
    ],
    'default_min_pieces' => 5,
];
