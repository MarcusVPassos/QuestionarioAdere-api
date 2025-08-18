<?php

return [
    'client_id'     => env('BLUEFLEET_CLIENT_ID'),
    'client_secret' => env('BLUEFLEET_CLIENT_SECRET'),
    'auth_url'      => env('BLUEFLEET_AUTH_URL', 'https://auth.bluefleet.com.br/connect/token'),
];
