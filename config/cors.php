<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [ 'http://localhost:3000',
   'http://127.0.0.1:3000','http://localhost:5173','http://127.0.0.1:5173',
        'https://fosika.mesupres.edu.mg','https://www.fosika.mesupres.edu.mg'
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Authorization', 'Content-Type'],
    'max_age' => 0,
    'supports_credentials' => true, 
];
