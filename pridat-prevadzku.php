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
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
  $stmt = $pdo->prepare('INSERT INTO prevadzky (zakaznik_id, nazov, adresa, mesto, poznamka) VALUES (?, ?, ?, ?, ?)');
  $stmt->execute([
    $_POST['zakaznik_id'],
    $_POST['nazov'],
    $_POST['adresa'],
    $_POST['mesto'],
    $_POST['poznamka'] ?? ''
  ]);
  echo "OK";
} catch (PDOException $e) {
  http_response_code(500);
  echo "Chyba: " . $e->getMessage();
}