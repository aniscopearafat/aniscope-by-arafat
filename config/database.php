<?php
declare(strict_types=1);

return [
    // InfinityFree example values look like:
    // host: sqlXXX.infinityfree.com
    // name/user: if0_XXXXXXXX_aniscope / if0_XXXXXXXX
    'host' => env_value('DB_HOST', 'localhost'),
    'port' => env_value('DB_PORT', '3306'),
    'name' => env_value('DB_NAME', ''),
    'user' => env_value('DB_USER', ''),
    'pass' => env_value('DB_PASS', ''),
    'charset' => env_value('DB_CHARSET', 'utf8mb4'),
];
