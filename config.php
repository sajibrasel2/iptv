<?php
// config.php

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if ($host === 'localhost' || $host === '127.0.0.1') {
    // Local Environment
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "livetv_db";
    $charset = "utf8mb4";
} else {
    // Live Server Environment
    $servername = "localhost";
    $username = "techandc_bot";
    $password = "12345Sajibs6@";
    $dbname = "techandc_livetv_db";
    $charset = "utf8mb4";
}
?>
