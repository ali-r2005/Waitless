<?php

return [
    'paths' => ['api/*', 'broadcasting/auth', 'sanctum/csrf-cookie'], // Add 'broadcasting/auth'
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:3000'], // Be specific for security
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // If you use cookies/session
];
