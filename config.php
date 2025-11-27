<?php
/**
 * Configuration file for Servisný Protokol application
 * 
 * Contains paths and settings for database, uploads, and mail.
 */

// Base path configuration
define('BASE_PATH', __DIR__);

// SQLite Database Configuration
define('DB_PATH', BASE_PATH . '/data/zakaznici.db');

// Uploads Configuration
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('PHOTOS_PATH', UPLOADS_PATH . '/photos');
define('SIGNATURES_PATH', UPLOADS_PATH . '/signatures');
define('PDFS_PATH', UPLOADS_PATH . '/pdfs');

// Maximum upload size (5MB)
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

// Allowed image types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Mail Configuration (basic settings - adjust for production)
define('MAIL_FROM', 'servis@example.com');
define('MAIL_FROM_NAME', 'Servisný Protokol');

// SMTP Configuration (set to true to use SMTP instead of PHP mail())
define('SMTP_ENABLED', false);
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

// Application settings
define('APP_NAME', 'Servisný Protokol');
define('APP_VERSION', '1.0.0');

/**
 * Get PDO connection for SQLite database
 * 
 * @return PDO Database connection
 */
function getDbConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    
    return $pdo;
}

/**
 * Generate a unique filename
 * 
 * @param string $extension File extension
 * @return string Unique filename
 */
function generateUniqueFilename(string $extension): string {
    return uniqid('', true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
}

/**
 * Send JSON response
 * 
 * @param mixed $data Data to send
 * @param int $statusCode HTTP status code
 */
function sendJsonResponse($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 * 
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 */
function sendErrorResponse(string $message, int $statusCode = 400): void {
    sendJsonResponse(['error' => $message], $statusCode);
}

/**
 * Validate and sanitize input
 * 
 * @param string $input Input to sanitize
 * @param int $maxLength Maximum length
 * @return string Sanitized input
 */
function sanitizeInput(string $input, int $maxLength = 255): string {
    return mb_substr(trim(strip_tags($input)), 0, $maxLength);
}
