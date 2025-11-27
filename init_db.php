<?php
/**
 * Inicializácia databázy a adresárov pre Servisný Protokol MVP
 * Spustiť raz na začiatku: php init_db.php
 */

require_once __DIR__ . '/config.php';

echo "=== Inicializácia Servisného Protokolu ===\n\n";

// Vytvorenie adresárov
$directories = [
    BASE_PATH . '/data',
    UPLOADS_PATH,
    SIGNATURES_PATH,
    PHOTOS_PATH,
    PDFS_PATH,
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Vytvorený adresár: $dir\n";
        } else {
            echo "✗ Nepodarilo sa vytvoriť adresár: $dir\n";
            exit(1);
        }
    } else {
        echo "- Adresár už existuje: $dir\n";
    }
}

// Vytvorenie .htaccess pre ochranu data/ adresára
// Kompatibilné s Apache 2.2 aj 2.4+
$htaccessPath = BASE_PATH . '/data/.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccessContent = <<<HTACCESS
# Apache 2.4+
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

# Apache 2.2
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS;
    file_put_contents($htaccessPath, $htaccessContent);
    echo "✓ Vytvorený .htaccess pre ochranu data/\n";
}

echo "\n=== Inicializácia databázy ===\n\n";

try {
    $pdo = getDbConnection();
    
    // Tabuľka customers (zákazníci)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nazov_firmy TEXT NOT NULL,
            ico TEXT,
            dic TEXT,
            ic_dph TEXT,
            sidlo TEXT,
            kontakt_osoba TEXT,
            telefon TEXT,
            email TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Tabuľka 'customers' vytvorená/existuje\n";
    
    // Tabuľka locations (prevádzky)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS locations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            nazov TEXT NOT NULL,
            adresa TEXT,
            mesto TEXT,
            poznamka TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Tabuľka 'locations' vytvorená/existuje\n";
    
    // Tabuľka devices (zariadenia)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            location_id INTEGER,
            nazov TEXT NOT NULL,
            typ TEXT,
            vyrobne_cislo TEXT,
            poznamka TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
        )
    ");
    echo "✓ Tabuľka 'devices' vytvorená/existuje\n";
    
    // Tabuľka reports (servisné protokoly)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cislo_protokolu TEXT NOT NULL UNIQUE,
            customer_id INTEGER,
            location_id INTEGER,
            device_id INTEGER,
            datum DATE,
            klapky_pr TEXT,
            klapky_od TEXT,
            filtracia_pr TEXT,
            filtracia_od TEXT,
            rekuperacia TEXT,
            ventilator TEXT,
            ohrievac TEXT,
            plynovy_horak TEXT,
            chladic TEXT,
            zvukovy_tlmic TEXT,
            poznamka TEXT,
            podpis_technik TEXT,
            podpis_zakaznik TEXT,
            pdf_path TEXT,
            email_sent INTEGER DEFAULT 0,
            status TEXT DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
        )
    ");
    echo "✓ Tabuľka 'reports' vytvorená/existuje\n";
    
    // Tabuľka attachments (prílohy - fotky)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER NOT NULL,
            file_path TEXT NOT NULL,
            file_name TEXT NOT NULL,
            file_type TEXT,
            file_size INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Tabuľka 'attachments' vytvorená/existuje\n";
    
    echo "\n=== Inicializácia dokončená úspešne! ===\n";
    echo "\nMôžete spustiť aplikáciu pomocou:\n";
    echo "  php -S localhost:8000\n";
    echo "\nA otvoriť v prehliadači:\n";
    echo "  http://localhost:8000/index.php\n";

} catch (PDOException $e) {
    echo "✗ Chyba pri inicializácii databázy: " . $e->getMessage() . "\n";
    exit(1);
}
