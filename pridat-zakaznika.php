<?php
$host = 'localhost'; // alebo IP databázy ak máš inú
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

  $stmt = $pdo->prepare('INSERT INTO zakaznici (nazov_firmy, ico, dic, ic_dph, sidlo, kontakt_osoba, telefon, email) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
  $stmt->execute([
    $_POST['nazov'],
    $_POST['ico'],
    $_POST['dic'],
    $_POST['icdph'],
    $_POST['sidlo'],
    $_POST['osoba'],
    $_POST['telefon'],
    $_POST['email']
  ]);

  echo "OK";
} catch (PDOException $e) {
  http_response_code(500);
  echo "Chyba: " . $e->getMessage();
}
?>
