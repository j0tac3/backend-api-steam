<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */
    // 1. Asegúrate de que incluya 'api/*'
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    // 2. Permitir todos los métodos (GET, POST, PUT, DELETE)
    'allowed_methods' => ['*'],
    // 3. ¡IMPORTANTE! Para desarrollo, pon '*'
    // Cuando subas Angular a Vercel, cambiaremos esto por la URL de Vercel
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    // 4. Permitir todos los headers
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,

];
