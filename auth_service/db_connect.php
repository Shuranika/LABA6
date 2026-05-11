<?php
$host = 'mysql_db';
$db   = 'lab_db';
$user = 'root';
$pass = 'root_pass';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(["message" => "Ошибка подключения: " . $e->getMessage()]);
    exit;
}