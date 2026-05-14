<?php
date_default_timezone_set('Asia/Kolkata');
$host = 'localhost';
$db   = 'cctv_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // For production, you might want to log this instead of showing the error
     // throw new \PDOException($e->getMessage(), (int)$e->getCode());
     die("Connection failed: " . $e->getMessage());
}
?>
