<?php
// Устанавливаем уровень ошибок
error_reporting(E_ERROR | E_PARSE);

$host = 'db';
$db = 'real_estate';
$user = 'admin';
$pass = 'admin123';
$dsn = "pgsql:host=$host;port=5432;dbname=$db;";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
