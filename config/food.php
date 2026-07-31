<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog source
    |--------------------------------------------------------------------------
    |
    | The dataset the global food catalog is bootstrapped from (D10). `source`
    | is written to every imported `FoodItem`, so provenance is queryable and a
    | re-import can find the rows it owns.
    |
    | Open Food Facts is published under the Open Database License. Attribution
    | is a licence condition, not a courtesy: it is recorded here so there is one
    | authoritative string, and it is printed by `food:import-off` at the point
    | the data enters the system. Any surface that publishes catalog-derived data
    | must carry it too.
    |
    */

    'catalog' => [

        'source' => 'open_food_facts',

        'attribution' => 'Contains information from Open Food Facts (https://world.openfoodfacts.org), '
            . 'made available under the Open Database License (ODbL) v1.0.',

        'license_url' => 'https://opendatacommons.org/licenses/odbl/1-0/',

    ],

];
