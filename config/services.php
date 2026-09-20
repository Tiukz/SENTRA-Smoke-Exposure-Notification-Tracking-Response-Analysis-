<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firms' => [
        'map_key' => env('FIRMS_MAP_KEY'),
        'base_url' => env('FIRMS_BASE_URL', 'https://firms.modaps.eosdis.nasa.gov/api/area/csv'),
        'source' => env('FIRMS_SOURCE', 'VIIRS_NOAA21_NRT'),
        'area' => env('FIRMS_AREA', '110.70,-3.60,115.90,0.80'),
        'day_range' => env('FIRMS_DAY_RANGE', 1),
        'minimum_confidence' => env('FIRMS_MIN_CONFIDENCE', 30),
        'recent_hours' => env('FIRMS_RECENT_HOURS', 24),
        'minimum_separation_km' => env('FIRMS_MIN_SEPARATION_KM', 5),
        'max_representative_hotspots' => env('FIRMS_MAX_REPRESENTATIVE_HOTSPOTS', 15),
    ],

    'real_snapshot' => [
        'path' => storage_path('app/datasets/real-snapshot.json'),
    ],

    'open_meteo' => [
        'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
    ],

    'overpass' => [
        'base_url' => env('OVERPASS_BASE_URL', 'https://overpass-api.de/api/interpreter'),
        'radius_metres' => env('OVERPASS_RADIUS_METRES', 75000),
        'max_facilities' => env('OVERPASS_MAX_FACILITIES', 12),
        'cache_seconds' => env('OVERPASS_CACHE_SECONDS', 21600),
    ],

    'osrm' => [
        'base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org/route/v1/driving'),
        'timeout_seconds' => env('OSRM_TIMEOUT_SECONDS', 20),
        'max_routes' => env('OSRM_MAX_ROUTES', 3),
    ],

    'route_exposure' => [
        'sample_step_meters' => env('ROUTE_EXPOSURE_SAMPLE_STEP_METERS', 250),
        'corridor_width_profile' => [
            [0, 0], [0.08, 0.25], [0.18, 0.65], [0.32, 1.4],
            [0.5, 2.7], [0.68, 4.3], [0.85, 6.2], [1, 8],
        ],
        'minimum_overlap_reduction_points' => env('ROUTE_MINIMUM_OVERLAP_REDUCTION_POINTS', 10),
        'maximum_duration_increase_percent' => env('ROUTE_MAXIMUM_DURATION_INCREASE_PERCENT', 35),
    ],

    'situation_summary' => [
        'cache_seconds' => env('SENTRA_SITUATION_CACHE_SECONDS', 300),
    ],

];
