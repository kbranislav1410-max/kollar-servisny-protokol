<?php
$host = 'localhost';
$db   = 'Kollar-zakaznici';
$user = 'Kollar-zakaznici';
$pass = '<bPzS4`.?GZuF0@v9a]-';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
header('Content-Type: application/json; charset=utf-8');
if (!isset($_GET['zakaznik_id'])) { echo json_encode([]); exit; }
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
  $stmt = $pdo->prepare('SELECT * FROM prevadzky WHERE zakaznik_id = ?');
  $stmt->execute([$_GET['zakaznik_id']]);
  echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}