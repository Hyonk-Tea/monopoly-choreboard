
<?php
date_default_timezone_set('America/Chicago');

header('Content-Type: application/json');

echo json_encode([
    "php_server_time_local" => date("r"),
    "php_server_time_iso"   => gmdate("c"),
    "php_timezone"          => date_default_timezone_get()
]);
