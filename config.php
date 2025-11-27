<?php
/**
 * Konfiguračný súbor pre Servisný Protokol MVP
 */

// Základ cesty k súborom (relatívna k tomuto súboru)
define('BASE_PATH', __DIR__);

// Databáza SQLite
define('DB_PATH', BASE_PATH . '/data/servisny_protokol.db');

// Upload adresáre
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('SIGNATURES_PATH', UPLOADS_PATH . '/signatures');
define('PHOTOS_PATH', UPLOADS_PATH . '/photos');
define('PDFS_PATH', UPLOADS_PATH . '/pdfs');

// Upload limity
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Nastavenia emailu (PHPMailer)
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@example.com');
define('MAIL_PASSWORD', 'your-password');
define('MAIL_ENCRYPTION', 'tls'); // 'tls' alebo 'ssl'
define('MAIL_FROM_ADDRESS', 'noreply@example.com');
define('MAIL_FROM_NAME', 'Servisný Protokol');

// Nastavenie timezone
date_default_timezone_set('Europe/Bratislava');

// Prostredie: 'development' alebo 'production'
define('APP_ENV', 'development');

// Error reporting - automaticky podľa prostredia
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

/**
 * Získanie PDO spojenia s databázou
 * @return PDO
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            die('Chyba pripojenia k databáze: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Session štart ak ešte nebeží
 */
function ensureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Bezpečné získanie hodnoty z $_GET
 */
function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Bezpečné získanie hodnoty z $_POST
 */
function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/**
 * Sanitize string pre výstup
 */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
