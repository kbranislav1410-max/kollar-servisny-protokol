<?php
/**
 * Servisný Protokol MVP - Hlavný router a krokový formulár
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

ensureSession();

// Router - určenie akcie
$action = get('action', 'home');

// Spracovanie POST akcií
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action', '');
    
    switch ($postAction) {
        case 'save_step':
            saveStepData();
            break;
        case 'add_customer':
            addCustomer();
            break;
        case 'add_location':
            addLocation();
            break;
        case 'add_location_from_customer':
            addLocationFromCustomer();
            break;
        case 'add_device':
            addDevice();
            break;
        case 'upload_signature':
            uploadSignature();
            break;
        case 'upload_photo':
            uploadPhoto();
            break;
        case 'finalize_report':
            finalizeReport();
            break;
        case 'send_email':
            sendReportEmail();
            break;
    }
}

// Funkcie pre spracovanie akcií

function saveStepData(): void
{
    $step = (int)post('step', 0);
    $data = post('data', []);
    
    if (!isset($_SESSION['report'])) {
        $_SESSION['report'] = [];
    }
    
    $_SESSION['report'] = array_merge($_SESSION['report'], $data);
    $_SESSION['current_step'] = $step + 1;
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'next_step' => $step + 1]);
    exit;
}

function addCustomer(): void
{
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO customers (nazov_firmy, ico, dic, ic_dph, sidlo, kontakt_osoba, telefon, email)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        post('nazov_firmy'),
        post('ico'),
        post('dic'),
        post('ic_dph'),
        post('sidlo'),
        post('kontakt_osoba'),
        post('telefon'),
        post('email'),
    ]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

function addLocation(): void
{
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO locations (customer_id, nazov, adresa, mesto, poznamka)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        post('customer_id'),
        post('nazov'),
        post('adresa'),
        post('mesto'),
        post('poznamka', ''),
    ]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

function addLocationFromCustomer(): void
{
    $pdo = getDbConnection();
    $customerId = (int)post('customer_id', 0);
    
    if (!$customerId) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Chýba ID zákazníka']);
        exit;
    }
    
    // Načítanie dát zákazníka
    $stmt = $pdo->prepare("SELECT nazov_firmy, sidlo FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Zákazník nebol nájdený']);
        exit;
    }
    
    // Vytvorenie prevádzky s údajmi zákazníka
    $stmt = $pdo->prepare("
        INSERT INTO locations (customer_id, nazov, adresa, mesto, poznamka)
        VALUES (?, ?, ?, '', 'Údaje prevádzky sú rovnaké ako údaje spoločnosti')
    ");
    
    $stmt->execute([
        $customerId,
        $customer['nazov_firmy'],
        $customer['sidlo'] ?? '',
    ]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

function addDevice(): void
{
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO devices (location_id, nazov, typ, vyrobne_cislo, poznamka)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        post('location_id'),
        post('nazov'),
        post('typ'),
        post('vyrobne_cislo'),
        post('poznamka', ''),
    ]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

function uploadSignature(): void
{
    $type = post('type', 'technik'); // 'technik' alebo 'zakaznik'
    $imageData = post('signature_data', '');
    
    if (empty($imageData)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Chýbajú dáta podpisu']);
        exit;
    }
    
    // Dekódovanie base64
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $decodedData = base64_decode($imageData);
    
    if ($decodedData === false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Neplatné dáta podpisu']);
        exit;
    }
    
    // Generovanie názvu súboru
    $filename = 'signature_' . $type . '_' . date('Ymd_His') . '_' . uniqid() . '.png';
    $filepath = SIGNATURES_PATH . '/' . $filename;
    
    if (!is_dir(SIGNATURES_PATH)) {
        mkdir(SIGNATURES_PATH, 0755, true);
    }
    
    if (file_put_contents($filepath, $decodedData)) {
        if (!isset($_SESSION['report'])) {
            $_SESSION['report'] = [];
        }
        $_SESSION['report']['podpis_' . $type] = $filename;
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Nepodarilo sa uložiť podpis']);
    }
    exit;
}

function uploadPhoto(): void
{
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Chyba pri nahrávaní súboru']);
        exit;
    }
    
    $file = $_FILES['photo'];
    
    // Kontrola veľkosti - použitie skutočnej veľkosti súboru
    $actualFileSize = filesize($file['tmp_name']);
    if ($actualFileSize === false || $actualFileSize > MAX_FILE_SIZE) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Súbor je príliš veľký (max 5MB)']);
        exit;
    }
    
    // Kontrola MIME typu
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Nepovolený typ súboru']);
        exit;
    }
    
    // Generovanie názvu súboru
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'photo_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    $filepath = PHOTOS_PATH . '/' . $filename;
    
    if (!is_dir(PHOTOS_PATH)) {
        mkdir(PHOTOS_PATH, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        if (!isset($_SESSION['report'])) {
            $_SESSION['report'] = [];
        }
        if (!isset($_SESSION['report']['photos'])) {
            $_SESSION['report']['photos'] = [];
        }
        $_SESSION['report']['photos'][] = [
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $mimeType,
        ];
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Nepodarilo sa uložiť súbor']);
    }
    exit;
}

function finalizeReport(): void
{
    if (!isset($_SESSION['report']) || empty($_SESSION['report'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Žiadne dáta reportu']);
        exit;
    }
    
    $report = $_SESSION['report'];
    $pdo = getDbConnection();
    
    // Generovanie unikátneho čísla protokolu
    $maxAttempts = 10;
    $cisloProtokolu = null;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $candidate = 'P-' . date('Ymd') . '-' . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE cislo_protokolu = ?");
        $stmt->execute([$candidate]);
        if ($stmt->fetchColumn() == 0) {
            $cisloProtokolu = $candidate;
            break;
        }
    }
    
    if (!$cisloProtokolu) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Nepodarilo sa vygenerovať unikátne číslo protokolu']);
        exit;
    }
    
    // Zariadenie - použijeme existujúce ID z výberu
    $deviceId = !empty($report['device_id']) ? (int)$report['device_id'] : null;
    
    // Uloženie reportu
    $stmt = $pdo->prepare("
        INSERT INTO reports (
            cislo_protokolu, customer_id, location_id, device_id, datum,
            klapky_pr, klapky_od, filtracia_pr, filtracia_od, rekuperacia,
            ventilator, ohrievac, plynovy_horak, chladic, zvukovy_tlmic,
            poznamka, podpis_technik, podpis_zakaznik, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
    ");
    
    $stmt->execute([
        $cisloProtokolu,
        $report['customer_id'] ?? null,
        $report['location_id'] ?? null,
        $deviceId,
        $report['datum'] ?? date('Y-m-d'),
        $report['klapky_pr'] ?? '',
        $report['klapky_od'] ?? '',
        $report['filtracia_pr'] ?? '',
        $report['filtracia_od'] ?? '',
        $report['rekuperacia'] ?? '',
        $report['ventilator'] ?? '',
        $report['ohrievac'] ?? '',
        $report['plynovy_horak'] ?? '',
        $report['chladic'] ?? '',
        $report['zvukovy_tlmic'] ?? '',
        $report['poznamka'] ?? '',
        $report['podpis_technik'] ?? '',
        $report['podpis_zakaznik'] ?? '',
    ]);
    
    $reportId = $pdo->lastInsertId();
    
    // Uloženie príloh
    if (!empty($report['photos'])) {
        $stmt = $pdo->prepare("
            INSERT INTO attachments (report_id, file_path, file_name, file_type, file_size)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($report['photos'] as $photo) {
            $stmt->execute([
                $reportId,
                'uploads/photos/' . $photo['filename'],
                $photo['original_name'],
                $photo['type'],
                $photo['size'],
            ]);
        }
    }
    
    // Generovanie PDF
    $pdfPath = generatePdf($reportId, $cisloProtokolu);
    
    // Aktualizácia cesty k PDF
    if ($pdfPath) {
        $stmt = $pdo->prepare("UPDATE reports SET pdf_path = ? WHERE id = ?");
        $stmt->execute([$pdfPath, $reportId]);
    }
    
    // Vyčistenie session
    unset($_SESSION['report']);
    unset($_SESSION['current_step']);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'report_id' => $reportId,
        'cislo_protokolu' => $cisloProtokolu,
        'pdf_path' => $pdfPath,
    ]);
    exit;
}

function generatePdf(int $reportId, string $cisloProtokolu): ?string
{
    $pdo = getDbConnection();
    
    // Načítanie dát reportu
    $stmt = $pdo->prepare("
        SELECT r.*, 
               c.nazov_firmy, c.ico, c.dic, c.ic_dph, c.sidlo, c.kontakt_osoba, c.telefon, c.email,
               l.nazov as location_nazov, l.adresa as location_adresa, l.mesto as location_mesto,
               d.nazov as device_nazov, d.typ as device_typ, d.vyrobne_cislo as device_vyrobne_cislo
        FROM reports r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN locations l ON r.location_id = l.id
        LEFT JOIN devices d ON r.device_id = d.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if (!$report) {
        return null;
    }
    
    // Načítanie príloh
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE report_id = ?");
    $stmt->execute([$reportId]);
    $attachments = $stmt->fetchAll();
    
    // Generovanie HTML
    ob_start();
    include __DIR__ . '/templates/report_pdf.php';
    $html = ob_get_clean();
    
    // Vytvorenie PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Uloženie PDF
    $filename = 'report-' . $cisloProtokolu . '.pdf';
    $filepath = PDFS_PATH . '/' . $filename;
    
    if (!is_dir(PDFS_PATH)) {
        mkdir(PDFS_PATH, 0755, true);
    }
    
    file_put_contents($filepath, $dompdf->output());
    
    return 'uploads/pdfs/' . $filename;
}

function sendReportEmail(): void
{
    $reportId = (int)post('report_id', 0);
    $toEmail = post('to_email', '');
    
    if (!$reportId || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Neplatné parametre']);
        exit;
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if (!$report || !$report['pdf_path']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Report alebo PDF neexistuje']);
        exit;
    }
    
    $pdfPath = BASE_PATH . '/' . $report['pdf_path'];
    
    try {
        $mail = new PHPMailer(true);
        
        // Nastavenia servera
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Odosielateľ a príjemca
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail);
        
        // Obsah
        $mail->isHTML(true);
        $mail->Subject = 'Servisný protokol ' . $report['cislo_protokolu'];
        $mail->Body = '<p>Dobrý deň,</p>'
            . '<p>v prílohe nájdete servisný protokol č. <strong>' . h($report['cislo_protokolu']) . '</strong>.</p>'
            . '<p>S pozdravom,<br>' . MAIL_FROM_NAME . '</p>';
        
        // Príloha
        if (file_exists($pdfPath)) {
            $mail->addAttachment($pdfPath);
        }
        
        $mail->send();
        
        // Aktualizácia stavu
        $stmt = $pdo->prepare("UPDATE reports SET email_sent = 1 WHERE id = ?");
        $stmt->execute([$reportId]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PHPMailerException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Chyba pri odosielaní: ' . $mail->ErrorInfo]);
    }
    exit;
}

// API endpointy pre GET požiadavky
function getCustomers(): void
{
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY nazov_firmy ASC");
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

function getLocations(): void
{
    $customerId = (int)get('customer_id', 0);
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE customer_id = ? ORDER BY nazov ASC");
    $stmt->execute([$customerId]);
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

function getDevices(): void
{
    $locationId = (int)get('location_id', 0);
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE location_id = ? ORDER BY nazov ASC");
    $stmt->execute([$locationId]);
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

function getReports(): void
{
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT r.*, c.nazov_firmy 
        FROM reports r 
        LEFT JOIN customers c ON r.customer_id = c.id 
        ORDER BY r.created_at DESC 
        LIMIT 50
    ");
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

function downloadPdf(): void
{
    $reportId = (int)get('report_id', 0);
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT pdf_path, cislo_protokolu FROM reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if (!$report || !$report['pdf_path']) {
        http_response_code(404);
        echo 'PDF not found';
        exit;
    }
    
    $filepath = BASE_PATH . '/' . $report['pdf_path'];
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $report['cislo_protokolu'] . '.pdf"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// Spracovanie GET akcií
switch ($action) {
    case 'api_customers':
        getCustomers();
        break;
    case 'api_locations':
        getLocations();
        break;
    case 'api_devices':
        getDevices();
        break;
    case 'api_reports':
        getReports();
        break;
    case 'api_customer_detail':
        getCustomerDetail();
        break;
    case 'api_customer_reports':
        getCustomerReports();
        break;
    case 'api_stats':
        getStats();
        break;
    case 'download_pdf':
        downloadPdf();
        break;
    case 'new_report':
    case 'protocol':
        // Reset session pre nový report
        if ($action === 'new_report') {
            unset($_SESSION['report']);
            $_SESSION['current_step'] = 0;
        }
        $pageView = 'protocol';
        break;
    case 'customers':
        $pageView = 'customers';
        break;
    case 'customer_detail':
        $pageView = 'customer_detail';
        break;
    case 'home':
    default:
        $pageView = 'home';
        break;
}

// API funkcie pre nové endpointy
function getCustomerDetail(): void
{
    $customerId = (int)get('customer_id', 0);
    
    $pdo = getDbConnection();
    
    // Zákazník
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Customer not found']);
        exit;
    }
    
    // Prevádzky
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE customer_id = ? ORDER BY nazov ASC");
    $stmt->execute([$customerId]);
    $locations = $stmt->fetchAll();
    
    // Zariadenia pre každú prevádzku
    foreach ($locations as &$loc) {
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE location_id = ? ORDER BY nazov ASC");
        $stmt->execute([$loc['id']]);
        $loc['devices'] = $stmt->fetchAll();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'customer' => $customer,
        'locations' => $locations
    ]);
    exit;
}

function getCustomerReports(): void
{
    $customerId = (int)get('customer_id', 0);
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT r.*, l.nazov as location_name, d.nazov as device_name
        FROM reports r
        LEFT JOIN locations l ON r.location_id = l.id
        LEFT JOIN devices d ON r.device_id = d.id
        WHERE r.customer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$customerId]);
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

function getStats(): void
{
    $pdo = getDbConnection();
    
    // Počet zákazníkov
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $customersCount = $stmt->fetch()['count'];
    
    // Počet prevádzok
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM locations");
    $locationsCount = $stmt->fetch()['count'];
    
    // Počet zariadení
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM devices");
    $devicesCount = $stmt->fetch()['count'];
    
    // Počet protokolov
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports");
    $reportsCount = $stmt->fetch()['count'];
    
    // Posledné protokoly
    $stmt = $pdo->query("
        SELECT r.id, r.cislo_protokolu, r.datum, r.created_at, c.nazov_firmy
        FROM reports r
        LEFT JOIN customers c ON r.customer_id = c.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $recentReports = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode([
        'customers' => $customersCount,
        'locations' => $locationsCount,
        'devices' => $devicesCount,
        'reports' => $reportsCount,
        'recent_reports' => $recentReports
    ]);
    exit;
}

// Získanie aktuálnych dát pre zobrazenie
$currentStep = $_SESSION['current_step'] ?? 0;
$reportData = $_SESSION['report'] ?? [];
$pageView = $pageView ?? 'home';

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servisný Protokol MVP</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="sidebar">
        <h2>Menu</h2>
        <ul>
            <li><a href="index.php" class="<?= $pageView === 'home' ? 'active' : '' ?>">🏠 Domov</a></li>
            <li><a href="index.php?action=new_report" class="<?= $pageView === 'protocol' ? 'active' : '' ?>">📝 Servisný protokol</a></li>
            <li><a href="index.php?action=customers" class="<?= $pageView === 'customers' || $pageView === 'customer_detail' ? 'active' : '' ?>">👥 Zákazníci</a></li>
        </ul>
    </nav>

    <main class="content">
        <?php if ($pageView === 'home'): ?>
        <!-- HOMEPAGE -->
        <h1>🏠 Prehľad</h1>
        
        <div class="dashboard">
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number" id="statCustomers">-</div>
                    <div class="stat-label">Zákazníci</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-number" id="statLocations">-</div>
                    <div class="stat-label">Prevádzky</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⚙️</div>
                    <div class="stat-number" id="statDevices">-</div>
                    <div class="stat-label">Zariadenia</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-number" id="statReports">-</div>
                    <div class="stat-label">Protokoly</div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>🚀 Rýchle akcie</h3>
                    <div class="quick-actions">
                        <a href="index.php?action=new_report" class="action-btn primary">
                            <span class="action-icon">📝</span>
                            <span>Nový servisný protokol</span>
                        </a>
                        <a href="index.php?action=customers" class="action-btn secondary">
                            <span class="action-icon">👥</span>
                            <span>Zoznam zákazníkov</span>
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3>📋 Posledné protokoly</h3>
                    <div class="recent-reports" id="recentReports">
                        <p class="loading">Načítavam...</p>
                    </div>
                </div>
                
                <div class="dashboard-card full-width">
                    <h3>📅 Nadchádzajúce úlohy</h3>
                    <div class="upcoming-tasks">
                        <p class="placeholder-text">Tu sa budú zobrazovať nadchádzajúce servisné úlohy a pripomienky.</p>
                        <ul class="task-list placeholder">
                            <li class="task-item">
                                <span class="task-icon">🔧</span>
                                <span class="task-text">Pravidelná údržba - Firma ABC s.r.o.</span>
                                <span class="task-date">Čoskoro</span>
                            </li>
                            <li class="task-item">
                                <span class="task-icon">📞</span>
                                <span class="task-text">Kontaktovať zákazníka - XYZ a.s.</span>
                                <span class="task-date">Čoskoro</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif ($pageView === 'customers'): ?>
        <!-- CUSTOMERS LIST -->
        <h1>👥 Zoznam zákazníkov</h1>
        
        <div class="customers-page">
            <div class="page-actions">
                <button class="btn btn-primary" onclick="toggleAddCustomerForm()">+ Pridať zákazníka</button>
            </div>
            
            <div class="collapsible-section" id="addCustomerSection">
                <div class="collapsible-content" style="display: none;">
                    <h3>Nový zákazník</h3>
                    <form id="addCustomerForm">
                        <div class="form-group">
                            <input type="text" name="nazov_firmy" placeholder="Názov firmy *" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="text" name="ico" placeholder="IČO">
                            </div>
                            <div class="form-group">
                                <input type="text" name="dic" placeholder="DIČ">
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="text" name="ic_dph" placeholder="IČ DPH">
                        </div>
                        <div class="form-group">
                            <input type="text" name="sidlo" placeholder="Sídlo">
                        </div>
                        <div class="form-group">
                            <input type="text" name="kontakt_osoba" placeholder="Kontaktná osoba">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="tel" name="telefon" placeholder="Telefón">
                            </div>
                            <div class="form-group">
                                <input type="email" name="email" placeholder="Email">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-secondary">Pridať zákazníka</button>
                    </form>
                </div>
            </div>
            
            <div class="search-box">
                <input type="text" id="customerSearch" placeholder="🔍 Hľadať zákazníka..." onkeyup="filterCustomers()">
            </div>
            
            <div class="customers-list" id="customersList">
                <p class="loading">Načítavam zákazníkov...</p>
            </div>
        </div>
        
        <?php elseif ($pageView === 'customer_detail'): ?>
        <!-- CUSTOMER DETAIL -->
        <div class="customer-detail-page">
            <div class="page-header">
                <a href="index.php?action=customers" class="back-link">← Späť na zoznam</a>
                <h1 id="customerName">Načítavam...</h1>
            </div>
            
            <div class="customer-info" id="customerInfo">
                <p class="loading">Načítavam údaje zákazníka...</p>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('locations')">🏢 Prevádzky</button>
                <button class="tab-btn" onclick="showTab('reports')">📋 Protokoly</button>
            </div>
            
            <div class="tab-content active" id="tab-locations">
                <h3>Prevádzky a zariadenia</h3>
                <div id="locationsContent">
                    <p class="loading">Načítavam...</p>
                </div>
            </div>
            
            <div class="tab-content" id="tab-reports">
                <h3>História servisných protokolov</h3>
                <div id="reportsContent">
                    <p class="loading">Načítavam...</p>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- PROTOCOL FORM -->
        <h1>📝 Servisný Protokol</h1>

        <!-- Progress bar -->
        <div class="progress-bar">
            <div class="progress-steps">
                <?php
                $steps = ['Zákazník', 'Prevádzka', 'Zariadenie', 'Komponenty', 'Podpisy', 'Súhrn'];
                foreach ($steps as $i => $stepName):
                    $class = $i < $currentStep ? 'completed' : ($i === $currentStep ? 'active' : '');
                ?>
                <div class="progress-step <?= $class ?>">
                    <span class="step-number"><?= $i + 1 ?></span>
                    <span class="step-name"><?= $stepName ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 0: Zákazník -->
        <div class="step <?= $currentStep === 0 ? 'active' : '' ?>" id="step-0">
            <h2>Krok 1: Výber/pridanie zákazníka</h2>
            
            <div class="form-group">
                <label for="customer_select">Existujúci zákazník:</label>
                <select id="customer_select" name="customer_id">
                    <option value="">-- Vyberte zákazníka --</option>
                </select>
            </div>

            <div class="collapsible-section">
                <button type="button" class="collapsible-toggle" onclick="toggleCollapsible(this)">
                    <span class="toggle-icon">+</span> Pridať nového zákazníka
                </button>
                <div class="collapsible-content">
                    <form id="newCustomerForm">
                        <div class="form-group">
                            <input type="text" name="nazov_firmy" placeholder="Názov firmy *" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="text" name="ico" placeholder="IČO">
                            </div>
                            <div class="form-group">
                                <input type="text" name="dic" placeholder="DIČ">
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="text" name="ic_dph" placeholder="IČ DPH">
                        </div>
                        <div class="form-group">
                            <input type="text" name="sidlo" placeholder="Sídlo">
                        </div>
                        <div class="form-group">
                            <input type="text" name="kontakt_osoba" placeholder="Kontaktná osoba">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="tel" name="telefon" placeholder="Telefón">
                            </div>
                            <div class="form-group">
                                <input type="email" name="email" placeholder="Email">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-secondary">Pridať zákazníka</button>
                    </form>
                </div>
            </div>

            <div class="navigation">
                <div></div>
                <button type="button" class="btn btn-primary" onclick="nextStep(0)">Ďalej</button>
            </div>
        </div>

        <!-- Step 1: Prevádzka -->
        <div class="step <?= $currentStep === 1 ? 'active' : '' ?>" id="step-1">
            <h2>Krok 2: Výber/pridanie prevádzky</h2>
            
            <div class="form-group">
                <label for="location_select">Existujúca prevádzka:</label>
                <select id="location_select" name="location_id">
                    <option value="">-- Vyberte prevádzku --</option>
                </select>
            </div>

            <div class="same-as-customer-section">
                <button type="button" class="btn btn-outline" id="sameAsCustomerBtn" onclick="useCustomerAsLocation()">
                    📋 Použiť údaje spoločnosti ako prevádzku
                </button>
                <p class="help-text">Ak má zákazník len jednu prevádzku s rovnakou adresou ako sídlo firmy.</p>
            </div>

            <div class="collapsible-section">
                <button type="button" class="collapsible-toggle" onclick="toggleCollapsible(this)">
                    <span class="toggle-icon">+</span> Pridať novú prevádzku manuálne
                </button>
                <div class="collapsible-content">
                    <form id="newLocationForm">
                        <div class="form-group">
                            <input type="text" name="nazov" placeholder="Názov prevádzky *" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="adresa" placeholder="Adresa">
                        </div>
                        <div class="form-group">
                            <input type="text" name="mesto" placeholder="Mesto">
                        </div>
                        <div class="form-group">
                            <textarea name="poznamka" placeholder="Poznámka"></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary">Pridať prevádzku</button>
                    </form>
                </div>
            </div>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(1)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(1)">Ďalej</button>
            </div>
        </div>

        <!-- Step 2: Zariadenie -->
        <div class="step <?= $currentStep === 2 ? 'active' : '' ?>" id="step-2">
            <h2>Krok 3: Výber/pridanie zariadenia</h2>
            
            <div class="form-group">
                <label for="device_select">Existujúce zariadenie na prevádzke:</label>
                <select id="device_select" name="device_id">
                    <option value="">-- Vyberte zariadenie (voliteľné) --</option>
                </select>
            </div>

            <div class="collapsible-section">
                <button type="button" class="collapsible-toggle" onclick="toggleCollapsible(this)">
                    <span class="toggle-icon">+</span> Pridať nové zariadenie
                </button>
                <div class="collapsible-content">
                    <form id="newDeviceForm">
                        <div class="form-group">
                            <input type="text" name="nazov" placeholder="Názov zariadenia *" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="typ" placeholder="Typ zariadenia">
                        </div>
                        <div class="form-group">
                            <input type="text" name="vyrobne_cislo" placeholder="Výrobné číslo">
                        </div>
                        <div class="form-group">
                            <textarea name="poznamka" placeholder="Poznámka k zariadeniu"></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary">Pridať zariadenie</button>
                    </form>
                </div>
            </div>

            <p class="help-text">Zariadenie je voliteľné. Ak nechcete vybrať ani pridať zariadenie, pokračujte ďalej.</p>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(2)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(2)">Ďalej</button>
            </div>
        </div>

        <!-- Step 3: Komponenty -->
        <div class="step <?= $currentStep === 3 ? 'active' : '' ?>" id="step-3">
            <h2>Krok 4: Stav komponentov</h2>
            
            <form id="componentsForm">
                <div class="form-group">
                    <input type="date" name="datum" value="<?= date('Y-m-d') ?>">
                    <label>Dátum servisu</label>
                </div>

                <h3>Klapky</h3>
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="klapky_pr" placeholder="Stav - prívod">
                    </div>
                    <div class="form-group">
                        <input type="text" name="klapky_od" placeholder="Stav - odvod">
                    </div>
                </div>

                <h3>Filtrácia</h3>
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="filtracia_pr" placeholder="Stav - prívod">
                    </div>
                    <div class="form-group">
                        <input type="text" name="filtracia_od" placeholder="Stav - odvod">
                    </div>
                </div>

                <h3>Ostatné komponenty</h3>
                <div class="form-group">
                    <input type="text" name="rekuperacia" placeholder="Stav rekuperácie">
                </div>
                <div class="form-group">
                    <input type="text" name="ventilator" placeholder="Stav ventilátora">
                </div>
                <div class="form-group">
                    <input type="text" name="ohrievac" placeholder="Stav ohrievača">
                </div>
                <div class="form-group">
                    <input type="text" name="plynovy_horak" placeholder="Stav plynového horáka">
                </div>
                <div class="form-group">
                    <input type="text" name="chladic" placeholder="Stav chladiča">
                </div>
                <div class="form-group">
                    <input type="text" name="zvukovy_tlmic" placeholder="Stav zvukového tlmiča">
                </div>

                <h3>Poznámka</h3>
                <div class="form-group">
                    <textarea name="poznamka" placeholder="Poznámka k servisu"></textarea>
                </div>

                <h3>Fotografie</h3>
                <div class="form-group">
                    <input type="file" id="photoInput" accept="image/*" multiple>
                    <div id="photoPreview" class="photo-preview"></div>
                </div>
            </form>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(3)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(3)">Ďalej</button>
            </div>
        </div>

        <!-- Step 4: Podpisy -->
        <div class="step <?= $currentStep === 4 ? 'active' : '' ?>" id="step-4">
            <h2>Krok 5: Podpisy</h2>
            
            <div class="signature-section">
                <h3>Podpis technika</h3>
                <div class="signature-container">
                    <canvas id="signatureTechnik" width="400" height="200"></canvas>
                    <div class="signature-controls">
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearSignature('Technik')">Vymazať</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="saveSignature('technik')">Uložiť podpis</button>
                    </div>
                    <span id="signatureTechnikStatus" class="status-text"></span>
                </div>
            </div>

            <div class="signature-section">
                <h3>Podpis zákazníka</h3>
                <div class="signature-container">
                    <canvas id="signatureZakaznik" width="400" height="200"></canvas>
                    <div class="signature-controls">
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearSignature('Zakaznik')">Vymazať</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="saveSignature('zakaznik')">Uložiť podpis</button>
                    </div>
                    <span id="signatureZakaznikStatus" class="status-text"></span>
                </div>
            </div>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(4)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(4)">Ďalej</button>
            </div>
        </div>

        <!-- Step 5: Súhrn a dokončenie -->
        <div class="step <?= $currentStep === 5 ? 'active' : '' ?>" id="step-5">
            <h2>Krok 6: Súhrn a dokončenie</h2>
            
            <div id="reportSummary" class="summary-box">
                <!-- Vyplnené JavaScriptom -->
            </div>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(5)">Späť</button>
                <button type="button" class="btn btn-success" id="finalizeBtn" onclick="finalizeReport()">
                    Dokončiť a vygenerovať PDF
                </button>
            </div>
        </div>

        <!-- Úspešné dokončenie -->
        <div class="step" id="step-complete">
            <div class="success-box">
                <h2>✓ Report bol úspešne vytvorený!</h2>
                <p>Číslo protokolu: <strong id="completedReportNumber"></strong></p>
                <div class="action-buttons">
                    <a id="downloadPdfBtn" href="#" class="btn btn-primary" download>Stiahnuť PDF</a>
                    <button type="button" class="btn btn-secondary" onclick="showEmailForm()">Odoslať emailom</button>
                    <a href="index.php?action=new_report" class="btn btn-outline">Vytvoriť nový report</a>
                </div>
                
                <div id="emailForm" class="email-form" style="display:none;">
                    <h3>Odoslať protokol emailom</h3>
                    <div class="form-group">
                        <input type="email" id="emailTo" placeholder="Email príjemcu">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="sendEmail()">Odoslať</button>
                    <span id="emailStatus" class="status-text"></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script src="assets/js/signature_pad.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Inicializácia s dátami zo session
        window.reportData = <?= json_encode($reportData) ?>;
        window.currentStep = <?= $currentStep ?>;
        window.pageView = '<?= $pageView ?>';
        
        // Inicializácia podľa stránky
        document.addEventListener('DOMContentLoaded', function() {
            if (window.pageView === 'home') {
                loadDashboardStats();
            } else if (window.pageView === 'customers') {
                loadCustomersList();
            } else if (window.pageView === 'customer_detail') {
                const urlParams = new URLSearchParams(window.location.search);
                const customerId = urlParams.get('customer_id');
                if (customerId) {
                    loadCustomerDetail(customerId);
                }
            }
        });
        
        // Dashboard funkcie
        function loadDashboardStats() {
            fetch('index.php?action=api_stats')
                .then(response => response.json())
                .then(stats => {
                    document.getElementById('statCustomers').textContent = stats.customers;
                    document.getElementById('statLocations').textContent = stats.locations;
                    document.getElementById('statDevices').textContent = stats.devices;
                    document.getElementById('statReports').textContent = stats.reports;
                    
                    // Posledné protokoly
                    const recentEl = document.getElementById('recentReports');
                    if (stats.recent_reports.length === 0) {
                        recentEl.innerHTML = '<p class="no-data">Zatiaľ žiadne protokoly</p>';
                    } else {
                        let html = '<ul class="report-list">';
                        stats.recent_reports.forEach(r => {
                            html += `<li class="report-item">
                                <span class="report-number">${r.cislo_protokolu}</span>
                                <span class="report-customer">${r.nazov_firmy || 'N/A'}</span>
                                <span class="report-date">${r.datum}</span>
                            </li>`;
                        });
                        html += '</ul>';
                        recentEl.innerHTML = html;
                    }
                })
                .catch(err => console.error('Chyba:', err));
        }
        
        // Customers list funkcie
        function loadCustomersList() {
            fetch('index.php?action=api_customers')
                .then(response => response.json())
                .then(customers => {
                    window.allCustomers = customers;
                    renderCustomersList(customers);
                })
                .catch(err => console.error('Chyba:', err));
        }
        
        function renderCustomersList(customers) {
            const container = document.getElementById('customersList');
            if (customers.length === 0) {
                container.innerHTML = '<p class="no-data">Zatiaľ žiadni zákazníci</p>';
                return;
            }
            
            let html = '<div class="customers-grid">';
            customers.forEach(c => {
                html += `
                    <div class="customer-card" onclick="window.location.href='index.php?action=customer_detail&customer_id=${c.id}'">
                        <div class="customer-card-header">
                            <h3>${escapeHtml(c.nazov_firmy)}</h3>
                            ${c.ico ? '<span class="customer-ico">IČO: ' + escapeHtml(c.ico) + '</span>' : ''}
                        </div>
                        <div class="customer-card-body">
                            ${c.sidlo ? '<p><strong>Sídlo:</strong> ' + escapeHtml(c.sidlo) + '</p>' : ''}
                            ${c.kontakt_osoba ? '<p><strong>Kontakt:</strong> ' + escapeHtml(c.kontakt_osoba) + '</p>' : ''}
                            ${c.telefon ? '<p><strong>Tel:</strong> ' + escapeHtml(c.telefon) + '</p>' : ''}
                        </div>
                        <div class="customer-card-footer">
                            <span class="view-detail">Zobraziť detail →</span>
                        </div>
                    </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        }
        
        function filterCustomers() {
            const query = document.getElementById('customerSearch').value.toLowerCase();
            const filtered = window.allCustomers.filter(c => 
                c.nazov_firmy.toLowerCase().includes(query) ||
                (c.ico && c.ico.includes(query)) ||
                (c.sidlo && c.sidlo.toLowerCase().includes(query))
            );
            renderCustomersList(filtered);
        }
        
        function toggleAddCustomerForm() {
            const content = document.querySelector('#addCustomerSection .collapsible-content');
            content.style.display = content.style.display === 'none' ? 'block' : 'none';
        }
        
        // Customer detail funkcie
        function loadCustomerDetail(customerId) {
            // Načítanie detailu zákazníka
            fetch('index.php?action=api_customer_detail&customer_id=' + customerId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Zákazník nebol nájdený');
                        return;
                    }
                    
                    const c = data.customer;
                    document.getElementById('customerName').textContent = c.nazov_firmy;
                    
                    let infoHtml = '<div class="info-grid">';
                    infoHtml += `<div class="info-item"><strong>IČO:</strong> ${c.ico || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>DIČ:</strong> ${c.dic || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>IČ DPH:</strong> ${c.ic_dph || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Sídlo:</strong> ${c.sidlo || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Kontakt:</strong> ${c.kontakt_osoba || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Telefón:</strong> ${c.telefon || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Email:</strong> ${c.email || '-'}</div>`;
                    infoHtml += '</div>';
                    document.getElementById('customerInfo').innerHTML = infoHtml;
                    
                    // Prevádzky a zariadenia
                    let locHtml = '';
                    if (data.locations.length === 0) {
                        locHtml = '<p class="no-data">Žiadne prevádzky</p>';
                    } else {
                        data.locations.forEach(loc => {
                            locHtml += `
                                <div class="location-card">
                                    <h4>🏢 ${escapeHtml(loc.nazov)}</h4>
                                    <p>${loc.adresa || ''} ${loc.mesto || ''}</p>
                                    ${loc.poznamka ? '<p class="note">' + escapeHtml(loc.poznamka) + '</p>' : ''}
                                    <div class="devices-list">
                                        <h5>Zariadenia:</h5>`;
                            
                            if (loc.devices.length === 0) {
                                locHtml += '<p class="no-data small">Žiadne zariadenia</p>';
                            } else {
                                locHtml += '<ul>';
                                loc.devices.forEach(d => {
                                    locHtml += `<li>⚙️ ${escapeHtml(d.nazov)} ${d.typ ? '(' + escapeHtml(d.typ) + ')' : ''}</li>`;
                                });
                                locHtml += '</ul>';
                            }
                            
                            locHtml += '</div></div>';
                        });
                    }
                    document.getElementById('locationsContent').innerHTML = locHtml;
                })
                .catch(err => console.error('Chyba:', err));
            
            // Načítanie protokolov zákazníka
            fetch('index.php?action=api_customer_reports&customer_id=' + customerId)
                .then(response => response.json())
                .then(reports => {
                    let html = '';
                    if (reports.length === 0) {
                        html = '<p class="no-data">Žiadne protokoly</p>';
                    } else {
                        html = '<div class="reports-table"><table>';
                        html += '<thead><tr><th>Číslo</th><th>Dátum</th><th>Prevádzka</th><th>Zariadenie</th><th>Akcia</th></tr></thead>';
                        html += '<tbody>';
                        reports.forEach(r => {
                            html += `<tr>
                                <td>${r.cislo_protokolu}</td>
                                <td>${r.datum}</td>
                                <td>${r.location_name || '-'}</td>
                                <td>${r.device_name || '-'}</td>
                                <td><a href="index.php?action=download_pdf&report_id=${r.id}" class="btn btn-sm">📄 PDF</a></td>
                            </tr>`;
                        });
                        html += '</tbody></table></div>';
                    }
                    document.getElementById('reportsContent').innerHTML = html;
                })
                .catch(err => console.error('Chyba:', err));
        }
        
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Add customer form handler pro customers page
        const addCustomerFormEl = document.getElementById('addCustomerForm');
        if (addCustomerFormEl) {
            addCustomerFormEl.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'add_customer');
                
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Zákazník bol pridaný');
                        this.reset();
                        toggleAddCustomerForm();
                        loadCustomersList();
                    } else {
                        alert('Chyba: ' + (result.error || 'Neznáma chyba'));
                    }
                })
                .catch(err => alert('Chyba pripojenia'));
            });
        }
    </script>
</body>
</html>
