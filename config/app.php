<?php
return [
    'app_name' => getenv('APP_NAME') ?: 'Blumi Studio',
    'base_url' => rtrim(getenv('APP_URL') ?: 'http://localhost/blumi/blumi_studio/public', '/'),
    'session_name' => getenv('SESSION_NAME') ?: 'blumi_studio_session',
    'environment' => getenv('APP_ENV') ?: 'development',
];
