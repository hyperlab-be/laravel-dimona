<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | The endpoints for the Dimona API.
    |
    */

    'endpoint' => env('DIMONA_ENDPOINT', 'https://services.socialsecurity.be/REST/dimona/v2'),

    'oauth_endpoint' => env('DIMONA_OAUTH_ENDPOINT', 'https://services.socialsecurity.be/REST/oauth/v5/token'),

    /*
    |--------------------------------------------------------------------------
    | Default Client
    |--------------------------------------------------------------------------
    |
    | The default client to use when no client is specified.
    |
    */

    'default_client' => env('DIMONA_DEFAULT_CLIENT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | API Clients
    |--------------------------------------------------------------------------
    |
    | Configure multiple API clients with different credentials.
    | Each client has its own client_id, private_key_path, and enterprise_number.
    |
    */

    'clients' => [

        'default' => [
            'client_id' => env('DIMONA_CLIENT_ID'),
            'private_key_path' => env('DIMONA_PRIVATE_KEY_PATH'),
        ],

        // Add more clients as needed:
        // 'client2' => [
        //     'client_id' => env('DIMONA_CLIENT2_ID'),
        //     'private_key_path' => env('DIMONA_CLIENT2_PRIVATE_KEY_PATH'),
        // ],

    ],

];
