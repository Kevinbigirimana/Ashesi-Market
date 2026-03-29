<?php


$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = (int) (getenv('DB_PORT') ?: 3306);
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'ashesi_market';

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
