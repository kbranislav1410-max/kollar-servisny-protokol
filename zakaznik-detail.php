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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Chýbajúce alebo neplatné ID']);
  exit;
}

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
  $stmt = $pdo->prepare("
    SELECT 
      id,
      nazov_firmy AS nazov,
      ico,
      dic,
      ic_dph AS icdph,
      sidlo,
      kontakt_osoba AS osoba,
      telefon,
      email
    FROM zakaznici 
    WHERE id = ?
    LIMIT 1
  ");
  $stmt->execute([$_GET['id']]);
  $zakaznik = $stmt->fetch();
  if (!$zakaznik) {
    http_response_code(404);
    echo json_encode(['error' => 'Zákazník nenájdený']);
    exit;
  }
  echo json_encode($zakaznik);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
?>