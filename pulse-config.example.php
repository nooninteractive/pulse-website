<?php
declare(strict_types=1);
// Copia como /home/TU_USUARIO/pulse-config.php. Nunca dentro de public_html.
return [
    'origin' => 'https://pulse.nooninteractive.com',
    'dsn' => 'mysql:host=localhost;dbname=TU_USUARIO_pulse;charset=utf8mb4',
    'db_user' => 'TU_USUARIO_pulse',
    'db_password' => 'CHANGE_ME',
    'app_key' => 'CHANGE_ME_GENERATE_64_HEX_CHARACTERS',
    'storage_path' => '/home/TU_USUARIO/pulse-storage',
];
