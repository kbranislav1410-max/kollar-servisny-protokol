<?php
/**
 * Database initialization script
 * 
 * Creates the SQLite database and required tables for the service protocol application.
 * Run this script once to set up the database.
 */

require_once __DIR__ . '/config.php';

// Create data directory if it doesn't exist
$dataDir = dirname(DB_PATH);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

try {
    $pdo = getDbConnection();
    
    // Enable foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Create customers table
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create locations (prevádzky) table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS locations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            nazov TEXT NOT NULL,
            adresa TEXT,
            mesto TEXT,
            psc TEXT,
            poznamka TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        )
    ");
    
    // Create devices (zariadenia) table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            location_id INTEGER NOT NULL,
            nazov TEXT NOT NULL,
            typ TEXT,
            vyrobne_cislo TEXT,
            poznamka TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
        )
    ");
    
    // Create reports (servisné protokoly) table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cislo_protokolu TEXT NOT NULL UNIQUE,
            customer_id INTEGER,
            location_id INTEGER,
            device_id INTEGER,
            datum DATE,
            technik_meno TEXT,
            
            -- Technical data (krokový workflow)
            klapky_pr TEXT,
            klapky_od TEXT,
            filtracia_pr TEXT,
            filtracia_od TEXT,
            rekuperacia TEXT,
            ventilator TEXT,
            ohri TEXT,
            horak TEXT,
            chladic TEXT,
            tlmic TEXT,
            poznamka TEXT,
            
            -- Workflow state
            step INTEGER DEFAULT 0,
            status TEXT DEFAULT 'draft',
            
            -- Signatures (stored as file paths)
            signature_technician TEXT,
            signature_customer TEXT,
            
            -- PDF path
            pdf_path TEXT,
            
            -- Email tracking
            email_sent INTEGER DEFAULT 0,
            email_sent_at DATETIME,
            
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
            FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
        )
    ");
    
    // Create attachments (photos before/after) table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER NOT NULL,
            file_path TEXT NOT NULL,
            file_type TEXT,
            section TEXT DEFAULT 'other',
            original_name TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
        )
    ");
    
    // Create index for faster lookups
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_locations_customer ON locations(customer_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_devices_location ON devices(location_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_customer ON reports(customer_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_location ON reports(location_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attachments_report ON attachments(report_id)");
    
    echo "Databáza bola úspešne inicializovaná!\n";
    echo "Cesta k databáze: " . DB_PATH . "\n";
    echo "Vytvorené tabuľky: customers, locations, devices, reports, attachments\n";
    
} catch (PDOException $e) {
    echo "Chyba pri inicializácii databázy: " . $e->getMessage() . "\n";
    exit(1);
}
