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

  $stmt = $pdo->query("
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
    ORDER BY nazov_firmy ASC
  ");

  echo json_encode($stmt->fetchAll());

} catch (PDOException $e) {
  http_response_code(500);
  echo "Chyba: " . $e->getMessage();
}
?>


