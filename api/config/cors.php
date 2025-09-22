<?php 

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],          // qualquer método
    'allowed_origins' => ['*'],          // qualquer origem
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],          // qualquer header
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,     // deixe false já que será público
];


