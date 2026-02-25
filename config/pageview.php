<?php

return [
    'geolocation' => [
        // Valores aceitos: maxmind, api
        'driver' => env('PAGEVIEW_GEOLOCATION_DRIVER', env('PAGEVIEW_GEOLOCATIION_DRIVE', 'api')),

        // Fallback quando o driver principal falhar.
        'fallback' => env('PAGEVIEW_GEOLOCATION_FALLBACK', 'api'),

        'maxmind' => [
            'city_db_path' => env('MAXMIND_CITY_DB_PATH', storage_path('maxmind/GeoLite2-City.mmdb')),
            'asn_db_path' => env('MAXMIND_ASN_DB_PATH', storage_path('maxmind/GeoLite2-ASN.mmdb')),
        ],
    ],
];
