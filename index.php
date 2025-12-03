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
        INSERT INTO devices (location_id, nazov, typ, vyrobne_cislo, rok_vyroby, prevedenie, vyrobca, distribucia, servisne_stredisko, servisne_stredisko_tel, interne_oznacenie, poznamka)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        post('location_id'),
        post('nazov'),
        post('typ'),
        post('vyrobne_cislo'),
        post('rok_vyroby', ''),
        post('prevedenie', ''),
        post('vyrobca', ''),
        post('distribucia', ''),
        post('servisne_stredisko', ''),
        post('servisne_stredisko_tel', ''),
        post('interne_oznacenie', ''),
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
    $photoType = post('photo_type', 'general'); // 'before', 'after', or 'general'
    $sectionKey = post('section_key', '');
    
    // Validácia photo_type
    $allowedTypes = ['before', 'after', 'general'];
    if (!in_array($photoType, $allowedTypes)) {
        $photoType = 'general';
    }
    
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
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Sanitizácia prípony
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $ext = 'jpg';
    }
    $filename = 'photo_' . $photoType . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
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
            'size' => $actualFileSize,
            'type' => $mimeType,
            'photo_type' => $photoType,
            'section_key' => $sectionKey,
        ];
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'filename' => $filename,
            'photo_type' => $photoType,
            'section_key' => $sectionKey
        ]);
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
    
    // Získať interné označenie zo zariadenia
    $interneOznacenie = '';
    if ($deviceId) {
        $stmt = $pdo->prepare("SELECT interne_oznacenie FROM devices WHERE id = ?");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch();
        $interneOznacenie = $device['interne_oznacenie'] ?? '';
    }
    
    // Pripraviť sekcie JSON pre uloženie všetkých komponentov s detailnými údajmi vrátane zhodnotenia a odporúčaní
    $sekcieData = [
        'klapky' => [
            'typ' => $report['klapky_typ'] ?? '',
            'servo_typ' => $report['klapky_servo_typ'] ?? '',
            'moment' => $report['klapky_moment'] ?? '',
            'privod_stav' => $report['klapky_pr_stav'] ?? '',
            'privod_poznamka' => $report['klapky_pr_poznamka'] ?? '',
            'odvod_stav' => $report['klapky_od_stav'] ?? '',
            'odvod_poznamka' => $report['klapky_od_poznamka'] ?? '',
            'zhodnotenie' => $report['klapky_zhodnotenie'] ?? '',
            'odporucanie' => $report['klapky_odporucanie'] ?? ''
        ],
        'filter' => [
            'typ' => $report['filter_typ'] ?? '',
            'rozmer' => $report['filter_rozmer'] ?? '',
            'privod_stav' => $report['filter_pr_stav'] ?? '',
            'privod_poznamka' => $report['filter_pr_poznamka'] ?? '',
            'odvod_stav' => $report['filter_od_stav'] ?? '',
            'odvod_poznamka' => $report['filter_od_poznamka'] ?? '',
            'zhodnotenie' => $report['filter_zhodnotenie'] ?? '',
            'odporucanie' => $report['filter_odporucanie'] ?? ''
        ],
        'rekuperator' => [
            'typ' => $report['rekuperator_typ'] ?? '',
            'privod_stav' => $report['rekuperator_pr_stav'] ?? '',
            'privod_poznamka' => $report['rekuperator_pr_poznamka'] ?? '',
            'odvod_stav' => $report['rekuperator_od_stav'] ?? '',
            'odvod_poznamka' => $report['rekuperator_od_poznamka'] ?? '',
            'zhodnotenie' => $report['rekuperator_zhodnotenie'] ?? '',
            'odporucanie' => $report['rekuperator_odporucanie'] ?? ''
        ],
        'recirkulacia' => [
            'typ' => $report['recirkulacia_typ'] ?? '',
            'privod_stav' => $report['recirkulacia_pr_stav'] ?? '',
            'privod_poznamka' => $report['recirkulacia_pr_poznamka'] ?? '',
            'odvod_stav' => $report['recirkulacia_od_stav'] ?? '',
            'odvod_poznamka' => $report['recirkulacia_od_poznamka'] ?? '',
            'zhodnotenie' => $report['recirkulacia_zhodnotenie'] ?? '',
            'odporucanie' => $report['recirkulacia_odporucanie'] ?? ''
        ],
        'ventilator' => [
            'typ' => $report['ventilator_typ'] ?? '',
            'pohon' => $report['ventilator_pohon'] ?? '',
            'remenica_typ' => $report['ventilator_remenica_typ'] ?? '',
            'privod_stav' => $report['ventilator_pr_stav'] ?? '',
            'privod_poznamka' => $report['ventilator_pr_poznamka'] ?? '',
            'odvod_stav' => $report['ventilator_od_stav'] ?? '',
            'odvod_poznamka' => $report['ventilator_od_poznamka'] ?? '',
            'zhodnotenie' => $report['ventilator_zhodnotenie'] ?? '',
            'odporucanie' => $report['ventilator_odporucanie'] ?? ''
        ],
        'el_motor' => [
            'vykon' => $report['motor_vykon'] ?? '',
            'pohon' => $report['motor_pohon'] ?? '',
            'remenica_typ' => $report['motor_remenica_typ'] ?? '',
            'privod_stav' => $report['motor_pr_stav'] ?? '',
            'privod_poznamka' => $report['motor_pr_poznamka'] ?? '',
            'odvod_stav' => $report['motor_od_stav'] ?? '',
            'odvod_poznamka' => $report['motor_od_poznamka'] ?? '',
            'zhodnotenie' => $report['motor_zhodnotenie'] ?? '',
            'odporucanie' => $report['motor_odporucanie'] ?? ''
        ],
        'chladic' => [
            'typ' => $report['chladic_typ'] ?? '',
            'spec' => $report['chladic_spec'] ?? '',
            'vykon' => $report['chladic_vykon'] ?? '',
            'stav' => $report['chladic_stav'] ?? '',
            'poznamka' => $report['chladic_poznamka'] ?? '',
            'zhodnotenie' => $report['chladic_zhodnotenie'] ?? '',
            'odporucanie' => $report['chladic_odporucanie'] ?? ''
        ],
        'ohrievac' => [
            'typ' => $report['ohrievac_typ'] ?? '',
            'vykon' => $report['ohrievac_vykon'] ?? '',
            'plyn_typ' => $report['ohrievac_plyn_typ'] ?? '',
            'bypass' => $report['ohrievac_bypass'] ?? '',
            'bypass_servo' => $report['ohrievac_bypass_servo'] ?? '',
            'stav' => $report['ohrievac_stav'] ?? '',
            'poznamka' => $report['ohrievac_poznamka'] ?? '',
            'zhodnotenie' => $report['ohrievac_zhodnotenie'] ?? '',
            'odporucanie' => $report['ohrievac_odporucanie'] ?? ''
        ],
        'kominovy_termostat' => [
            'typ' => $report['kominovy_termostat'] ?? '',
            'stav' => $report['kominovy_termostat_stav'] ?? '',
            'poznamka' => $report['kominovy_termostat_poznamka'] ?? '',
            'zhodnotenie' => $report['termostat_zhodnotenie'] ?? '',
            'odporucanie' => $report['termostat_odporucanie'] ?? ''
        ]
    ];
    
    $sekcieJson = json_encode($sekcieData, JSON_UNESCAPED_UNICODE);
    
    // Uloženie reportu s rozšírenými poliami
    $stmt = $pdo->prepare("
        INSERT INTO reports (
            cislo_protokolu, customer_id, location_id, device_id, datum,
            interne_oznacenie, objednavatel, servis_vykonal, skontroloval_prevzal,
            sekcie_json,
            klapky_pr, klapky_od, filtracia_pr, filtracia_od, rekuperacia,
            ventilator, ohrievac, plynovy_horak, chladic, zvukovy_tlmic,
            poznamka, odporucania, zhodnotenie,
            podpis_technik, podpis_zakaznik, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
    ");
    
    $stmt->execute([
        $cisloProtokolu,
        $report['customer_id'] ?? null,
        $report['location_id'] ?? null,
        $deviceId,
        $report['datum'] ?? date('Y-m-d'),
        $interneOznacenie,
        $report['objednavatel'] ?? '',
        $report['servis_vykonal'] ?? '',
        $report['skontroloval_prevzal'] ?? '',
        $sekcieJson,
        $report['klapky_pr'] ?? '',
        $report['klapky_od'] ?? '',
        $report['filtracia_pr'] ?? '',
        $report['filtracia_od'] ?? '',
        $report['rekuperacia_pr'] ?? '',
        $report['ventilator_pr'] ?? '',
        $report['ohrievac_pr'] ?? '',
        $report['plynovy_horak_stav'] ?? '',
        $report['chladic_pr'] ?? '',
        '', // zvukovy_tlmic - legacy, not used in new form
        $report['poznamka'] ?? '',
        $report['odporucania'] ?? '',
        $report['zhodnotenie'] ?? '',
        $report['podpis_technik'] ?? '',
        $report['podpis_zakaznik'] ?? '',
    ]);
    
    $reportId = $pdo->lastInsertId();
    
    // Uloženie príloh s photo_type a section_key
    if (!empty($report['photos'])) {
        $stmt = $pdo->prepare("
            INSERT INTO attachments (report_id, section_key, photo_type, file_path, file_name, file_type, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($report['photos'] as $photo) {
            $stmt->execute([
                $reportId,
                $photo['section_key'] ?? '',
                $photo['photo_type'] ?? 'general',
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
    
    // Načítanie dát reportu s rozšírenými device poliami
    $stmt = $pdo->prepare("
        SELECT r.*, 
               c.nazov_firmy, c.ico, c.dic, c.ic_dph, c.sidlo, c.kontakt_osoba, c.telefon, c.email,
               l.nazov as location_nazov, l.adresa as location_adresa, l.mesto as location_mesto,
               d.nazov as device_nazov, d.typ as device_typ, d.vyrobne_cislo as device_vyrobne_cislo,
               d.rok_vyroby as device_rok_vyroby, d.prevedenie as device_prevedenie,
               d.vyrobca as device_vyrobca, d.distribucia as device_distribucia,
               d.servisne_stredisko as device_servisne_stredisko, d.servisne_stredisko_tel as device_servisne_stredisko_tel,
               d.interne_oznacenie as device_interne_oznacenie
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
    
    // Dekódovanie sekcie JSON
    $sekcieData = [];
    if (!empty($report['sekcie_json'])) {
        $sekcieData = json_decode($report['sekcie_json'], true) ?: [];
    }
    
    // Načítanie príloh - rozdelených podľa photo_type
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE report_id = ? ORDER BY photo_type, created_at");
    $stmt->execute([$reportId]);
    $attachments = $stmt->fetchAll();
    
    // Rozdelenie príloh podľa typu
    $photosBefore = [];
    $photosAfter = [];
    $photosGeneral = [];
    foreach ($attachments as $att) {
        $photoType = $att['photo_type'] ?? 'general';
        if ($photoType === 'before') {
            $photosBefore[] = $att;
        } elseif ($photoType === 'after') {
            $photosAfter[] = $att;
        } else {
            $photosGeneral[] = $att;
        }
    }
    
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
    case 'api_location_detail':
        getLocationDetail();
        break;
    case 'api_device_detail':
        getDeviceDetail();
        break;
    case 'api_device_reports':
        getDeviceReports();
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
    case 'location_detail':
        $pageView = 'location_detail';
        break;
    case 'device_detail':
        $pageView = 'device_detail';
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

function getLocationDetail(): void
{
    $locationId = (int)get('location_id', 0);
    
    $pdo = getDbConnection();
    
    // Prevádzka
    $stmt = $pdo->prepare("
        SELECT l.*, c.nazov_firmy as customer_name, c.id as customer_id
        FROM locations l
        LEFT JOIN customers c ON l.customer_id = c.id
        WHERE l.id = ?
    ");
    $stmt->execute([$locationId]);
    $location = $stmt->fetch();
    
    if (!$location) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Location not found']);
        exit;
    }
    
    // Zariadenia
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE location_id = ? ORDER BY nazov ASC");
    $stmt->execute([$locationId]);
    $devices = $stmt->fetchAll();
    
    // Počet protokolov pre každé zariadenie
    foreach ($devices as &$device) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reports WHERE device_id = ?");
        $stmt->execute([$device['id']]);
        $device['reports_count'] = $stmt->fetch()['count'];
    }
    
    // Protokoly pre túto prevádzku
    $stmt = $pdo->prepare("
        SELECT r.*, d.nazov as device_name
        FROM reports r
        LEFT JOIN devices d ON r.device_id = d.id
        WHERE r.location_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$locationId]);
    $reports = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode([
        'location' => $location,
        'devices' => $devices,
        'reports' => $reports
    ]);
    exit;
}

function getDeviceDetail(): void
{
    $deviceId = (int)get('device_id', 0);
    
    $pdo = getDbConnection();
    
    // Zariadenie
    $stmt = $pdo->prepare("
        SELECT d.*, l.nazov as location_name, l.adresa as location_adresa, l.mesto as location_mesto,
               c.nazov_firmy as customer_name, c.id as customer_id
        FROM devices d
        LEFT JOIN locations l ON d.location_id = l.id
        LEFT JOIN customers c ON l.customer_id = c.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();
    
    if (!$device) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Device not found']);
        exit;
    }
    
    // Protokoly pre toto zariadenie
    $stmt = $pdo->prepare("
        SELECT r.*
        FROM reports r
        WHERE r.device_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$deviceId]);
    $reports = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode([
        'device' => $device,
        'reports' => $reports
    ]);
    exit;
}

function getDeviceReports(): void
{
    $deviceId = (int)get('device_id', 0);
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT r.*, l.nazov as location_name
        FROM reports r
        LEFT JOIN locations l ON r.location_id = l.id
        WHERE r.device_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$deviceId]);
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
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
            <li><a href="index.php" class="<?= $pageView === 'home' ? 'active' : '' ?>">Domov</a></li>
            <li><a href="index.php?action=new_report" class="<?= $pageView === 'protocol' ? 'active' : '' ?>">Servisný protokol</a></li>
            <li><a href="index.php?action=customers" class="<?= $pageView === 'customers' || $pageView === 'customer_detail' || $pageView === 'location_detail' || $pageView === 'device_detail' ? 'active' : '' ?>">Zákazníci</a></li>
        </ul>
    </nav>

    <main class="content">
        <?php if ($pageView === 'home'): ?>
        <!-- HOMEPAGE -->
        <h1>Prehľad</h1>
        
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
                    <h3>Rýchle akcie</h3>
                    <div class="quick-actions">
                        <a href="index.php?action=new_report" class="action-btn primary">
                            <span>Nový servisný protokol</span>
                        </a>
                        <a href="index.php?action=customers" class="action-btn secondary">
                            <span>Zoznam zákazníkov</span>
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3>Posledné protokoly</h3>
                    <div class="recent-reports" id="recentReports">
                        <p class="loading">Načítavam...</p>
                    </div>
                </div>
                
                <div class="dashboard-card full-width">
                    <h3>Nadchádzajúce úlohy</h3>
                    <div class="upcoming-tasks">
                        <p class="placeholder-text">Tu sa budú zobrazovať nadchádzajúce servisné úlohy a pripomienky.</p>
                        <ul class="task-list placeholder">
                            <li class="task-item">
                                <span class="task-text">Pravidelná údržba - Firma ABC s.r.o.</span>
                                <span class="task-date">Čoskoro</span>
                            </li>
                            <li class="task-item">
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
        <h1>Zoznam zákazníkov</h1>
        
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
                <div class="page-title-section">
                    <span class="page-type-label">Detail zákazníka</span>
                    <h1 id="customerName">Načítavam...</h1>
                </div>
            </div>
            
            <div class="customer-info" id="customerInfo">
                <p class="loading">Načítavam údaje zákazníka...</p>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('locations')">Prevádzky a zariadenia</button>
                <button class="tab-btn" onclick="showTab('reports')">Všetky protokoly</button>
            </div>
            
            <div class="tab-content active" id="tab-locations">
                <h3>Prevádzky a zariadenia</h3>
                <p class="help-text">Kliknite na prevádzku alebo zariadenie pre zobrazenie detailov a protokolov.</p>
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
        
        <?php elseif ($pageView === 'location_detail'): ?>
        <!-- LOCATION DETAIL -->
        <div class="location-detail-page">
            <div class="page-header">
                <a href="#" id="backToCustomerLink" class="back-link">← Späť na zákazníka</a>
                <div class="page-title-section">
                    <span class="page-type-label">Detail prevádzky</span>
                    <h1 id="locationName">Načítavam...</h1>
                </div>
            </div>
            
            <div class="location-info" id="locationInfo">
                <p class="loading">Načítavam údaje prevádzky...</p>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="showLocationTab('devices')">Zariadenia</button>
                <button class="tab-btn" onclick="showLocationTab('reports')">Protokoly prevádzky</button>
            </div>
            
            <div class="tab-content active" id="tab-devices">
                <h3>Zariadenia na prevádzke</h3>
                <p class="help-text">Kliknite na zariadenie pre zobrazenie protokolov.</p>
                <div id="devicesContent">
                    <p class="loading">Načítavam...</p>
                </div>
            </div>
            
            <div class="tab-content" id="tab-reports">
                <h3>Protokoly pre túto prevádzku</h3>
                <div id="locationReportsContent">
                    <p class="loading">Načítavam...</p>
                </div>
            </div>
        </div>
        
        <?php elseif ($pageView === 'device_detail'): ?>
        <!-- DEVICE DETAIL -->
        <div class="device-detail-page">
            <div class="page-header">
                <a href="#" id="backToLocationLink" class="back-link">← Späť na prevádzku</a>
                <div class="page-title-section">
                    <span class="page-type-label">Detail zariadenia</span>
                    <h1 id="deviceName">Načítavam...</h1>
                </div>
            </div>
            
            <div class="device-info" id="deviceInfo">
                <p class="loading">Načítavam údaje zariadenia...</p>
            </div>
            
            <h3>Protokoly pre toto zariadenie</h3>
            <div id="deviceReportsContent">
                <p class="loading">Načítavam...</p>
            </div>
        </div>
        
        <?php else: ?>
        <!-- PROTOCOL FORM -->
        <h1>Servisný Protokol</h1>

        <!-- Progress bar -->
        <div class="progress-bar">
            <div class="progress-steps">
                <?php
                $steps = ['Zákazník', 'Prevádzka', 'Zariadenie', 'Fotky pred', 'Komponenty', 'Fotky po', 'Podpisy', 'Súhrn'];
                foreach ($steps as $i => $stepName):
                    $class = $i < $currentStep ? 'completed' : ($i === $currentStep ? 'active' : '');
                    $clickable = $i < $currentStep ? 'clickable' : '';
                ?>
                <div class="progress-step <?= $class ?> <?= $clickable ?>" data-step="<?= $i ?>" onclick="goToStep(<?= $i ?>)">
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
                    Použiť údaje spoločnosti ako prevádzku
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
                <label for="device_select">Existujúce zariadenie na prevádzke: *</label>
                <select id="device_select" name="device_id" required>
                    <option value="">-- Vyberte zariadenie --</option>
                </select>
            </div>

            <div class="collapsible-section">
                <button type="button" class="collapsible-toggle" onclick="toggleCollapsible(this)">
                    <span class="toggle-icon">+</span> Pridať nové zariadenie
                </button>
                <div class="collapsible-content">
                    <form id="newDeviceForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Názov zariadenia *</label>
                                <input type="text" name="nazov" placeholder="Napr. Vzduchotechnická jednotka" required>
                            </div>
                            <div class="form-group">
                                <label>Interné označenie *</label>
                                <input type="text" name="interne_oznacenie" placeholder="Napr. VZT-01, KLIMA-1" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Typ / Model</label>
                                <input type="text" name="typ" placeholder="Model zariadenia">
                            </div>
                            <div class="form-group">
                                <label>Prevedenie</label>
                                <select name="prevedenie">
                                    <option value="">-- Vyberte prevedenie --</option>
                                    <option value="interierove">Interiérové</option>
                                    <option value="exterierove">Exteriérové</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Výrobné číslo</label>
                                <input type="text" name="vyrobne_cislo" placeholder="S/N">
                            </div>
                            <div class="form-group">
                                <label>Rok výroby</label>
                                <input type="text" name="rok_vyroby" placeholder="Napr. 2020">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Výrobca</label>
                                <input type="text" name="vyrobca" placeholder="Výrobca zariadenia">
                            </div>
                            <div class="form-group">
                                <label>Distribúcia a techn. podpora pre SR</label>
                                <input type="text" name="distribucia" placeholder="Distribútor v SR">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Servisné stredisko</label>
                                <input type="text" name="servisne_stredisko" placeholder="Názov strediska">
                            </div>
                            <div class="form-group">
                                <label>Tel. servisného strediska</label>
                                <input type="tel" name="servisne_stredisko_tel" placeholder="+421...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Poznámka k zariadeniu</label>
                            <textarea name="poznamka" placeholder="Ďalšie poznámky"></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary">Pridať zariadenie</button>
                    </form>
                </div>
            </div>

            <p class="help-text required-note">Zariadenie je povinné. Vyberte existujúce zariadenie alebo pridajte nové.</p>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(2)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(2)">Ďalej</button>
            </div>
        </div>

        <!-- Step 3: Fotky pred servisom (NEW) -->
        <div class="step <?= $currentStep === 3 ? 'active' : '' ?>" id="step-3">
            <h2>Krok 4: Fotografie PRED servisom</h2>
            
            <div class="section-header">
                <h3>Dokumentácia stavu pred servisom</h3>
                <p class="help-text">Nafotografujte zariadenie a jeho komponenty pred začiatkom servisu. Tieto fotografie slúžia ako dôkaz pôvodného stavu.</p>
            </div>
            
            <div class="photo-upload-section full-width">
                <div class="photo-section" data-photo-type="before">
                    <div class="photo-buttons">
                        <button type="button" class="btn btn-camera btn-lg" onclick="openCameraForType('before')">
                            Odfotiť
                        </button>
                        <input type="file" id="cameraInputBefore" accept="image/*" capture="environment" style="display: none;" data-photo-type="before">
                        
                        <button type="button" class="btn btn-outline btn-lg" onclick="document.getElementById('galleryInputBefore').click()">
                            Vybrať z galérie
                        </button>
                        <input type="file" id="galleryInputBefore" accept="image/*" multiple style="display: none;" data-photo-type="before">
                    </div>
                    <div id="photoPreviewBefore" class="photo-preview"></div>
                    <div id="photoCountBefore" class="photo-count"></div>
                </div>
            </div>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(3)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(3)">Ďalej</button>
            </div>
        </div>

        <!-- Step 4: Komponenty a detaily -->
        <div class="step <?= $currentStep === 4 ? 'active' : '' ?>" id="step-4">
            <h2>Krok 5: Servisné údaje a stav komponentov</h2>
            
            <form id="componentsForm">
                <!-- Základné údaje servisu -->
                <div class="section-header">
                    <h3>Základné údaje</h3>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Dátum servisu</label>
                        <input type="date" name="datum" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Servis vykonal (meno technika)</label>
                        <input type="text" name="servis_vykonal" placeholder="Meno a priezvisko technika">
                    </div>
                </div>
                <div class="form-group">
                    <label>Objednávateľ</label>
                    <input type="text" name="objednavatel" placeholder="Meno objednávateľa">
                </div>

                <!-- Komponenty s prívodom aj odvodom -->
                <div class="section-header">
                    <h3>Komponenty - PRÍVOD aj ODVOD</h3>
                    <p class="help-text">Pre každý komponent vyplňte konfiguráciu, stav a odporúčania.</p>
                </div>

                <!-- KLAPKY -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Klapky</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Typ klapiek</label>
                                <select name="klapky_typ" onchange="toggleKlapkyServo(this)">
                                    <option value="">-- Vyberte --</option>
                                    <option value="bez_servopohonu">Bez servopohonu</option>
                                    <option value="so_servopohonom">So servopohonom</option>
                                </select>
                            </div>
                            <div class="form-group conditional-field" id="klapky_servo_fields" style="display:none;">
                                <label>Typ servopohonu</label>
                                <input type="text" name="klapky_servo_typ" placeholder="Typ servopohonu">
                            </div>
                            <div class="form-group conditional-field" id="klapky_moment_field" style="display:none;">
                                <label>Moment (Nm)</label>
                                <input type="text" name="klapky_moment" placeholder="Napr. 10 Nm">
                            </div>
                        </div>
                        <div class="priv-odv-section">
                            <div class="priv-section">
                                <h5>PRÍVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="klapky_pr_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="klapky_pr_poznamka" placeholder="Poznámka k prívodu">
                                    </div>
                                </div>
                            </div>
                            <div class="odv-section">
                                <h5>ODVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="klapky_od_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="klapky_od_poznamka" placeholder="Poznámka k odvodu">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="klapky_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="klapky_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FILTER -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Filter</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Typ filtra</label>
                                <select name="filter_typ">
                                    <option value="">-- Vyberte --</option>
                                    <option value="bez_filtra">Bez filtra</option>
                                    <option value="kapsovy">Kapsový</option>
                                    <option value="kazetovy">Kazetový</option>
                                    <option value="firon">Fíron</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Rozmer filtra</label>
                                <input type="text" name="filter_rozmer" placeholder="Napr. 592x592x300">
                            </div>
                        </div>
                        <div class="priv-odv-section">
                            <div class="priv-section">
                                <h5>PRÍVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="filter_pr_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="filter_pr_poznamka" placeholder="Poznámka k prívodu">
                                    </div>
                                </div>
                            </div>
                            <div class="odv-section">
                                <h5>ODVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="filter_od_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="filter_od_poznamka" placeholder="Poznámka k odvodu">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="filter_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="filter_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- REKUPERÁTOR -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Rekuperátor</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Typ rekuperátora</label>
                                <select name="rekuperator_typ">
                                    <option value="">-- Vyberte --</option>
                                    <option value="doskovy">Doskový</option>
                                    <option value="rotacny">Rotačný</option>
                                </select>
                            </div>
                        </div>
                        <div class="priv-odv-section">
                            <div class="priv-section">
                                <h5>PRÍVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="rekuperator_pr_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="rekuperator_pr_poznamka" placeholder="Poznámka k prívodu">
                                    </div>
                                </div>
                            </div>
                            <div class="odv-section">
                                <h5>ODVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="rekuperator_od_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="rekuperator_od_poznamka" placeholder="Poznámka k odvodu">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="rekuperator_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="rekuperator_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RECIRKULÁCIA -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Recirkulácia</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Recirkulácia</label>
                                <select name="recirkulacia_typ">
                                    <option value="">-- Vyberte --</option>
                                    <option value="s_recirkulaciou">S recirkuláciou</option>
                                    <option value="bez_recirkulacie">Bez recirkulácie</option>
                                </select>
                            </div>
                        </div>
                        <div class="priv-odv-section">
                            <div class="priv-section">
                                <h5>PRÍVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="recirkulacia_pr_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="recirkulacia_pr_poznamka" placeholder="Poznámka k prívodu">
                                    </div>
                                </div>
                            </div>
                            <div class="odv-section">
                                <h5>ODVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="recirkulacia_od_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="recirkulacia_od_poznamka" placeholder="Poznámka k odvodu">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="recirkulacia_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="recirkulacia_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VENTILÁTOR -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Ventilátor</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Typ ventilátora</label>
                                <input type="text" name="ventilator_typ" placeholder="Typ ventilátora">
                            </div>
                            <div class="form-group">
                                <label>Vzduchový výkon</label>
                                <select name="ventilator_pohon" onchange="toggleVentilatorRemenica(this)">
                                    <option value="">-- Vyberte --</option>
                                    <option value="napriamo">Napriamo</option>
                                    <option value="sprevodovany">Sprevodovaný</option>
                                </select>
                            </div>
                            <div class="form-group conditional-field" id="ventilator_remenica_field" style="display:none;">
                                <label>Remenica - typ</label>
                                <input type="text" name="ventilator_remenica_typ" placeholder="Typ remenice">
                            </div>
                        </div>
                        <div class="priv-odv-section">
                            <div class="priv-section">
                                <h5>PRÍVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="ventilator_pr_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="ventilator_pr_poznamka" placeholder="Poznámka k prívodu">
                                    </div>
                                </div>
                            </div>
                            <div class="odv-section">
                                <h5>ODVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="ventilator_od_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="ventilator_od_poznamka" placeholder="Poznámka k odvodu">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="ventilator_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="ventilator_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ELEKTRO MOTOR -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Elektro motor</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Výkon / Príkon</label>
                                <input type="text" name="motor_vykon" placeholder="Napr. 2.2 kW">
                            </div>
                            <div class="form-group">
                                <label>Pohon</label>
                                <select name="motor_pohon" onchange="toggleMotorRemenica(this)">
                                    <option value="">-- Vyberte --</option>
                                    <option value="napriamo">Napriamo</option>
                                    <option value="sprevodovany">Sprevodovaný</option>
                                </select>
                            </div>
                            <div class="form-group conditional-field" id="motor_remenica_field" style="display:none;">
                                <label>Remenica</label>
                                <input type="text" name="motor_remenica" placeholder="Typ remenice">
                            </div>
                        </div>
                        <div class="priv-odv-section">
                            <div class="priv-section">
                                <h5>PRÍVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="motor_pr_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="motor_pr_poznamka" placeholder="Poznámka k prívodu">
                                    </div>
                                </div>
                            </div>
                            <div class="odv-section">
                                <h5>ODVOD</h5>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Stav</label>
                                        <select name="motor_od_stav">
                                            <option value="">-- Vyberte stav --</option>
                                            <option value="cisty">Čistý</option>
                                            <option value="mierne_znecisteny">Mierne znečistený</option>
                                            <option value="znecisteny">Znečistený</option>
                                            <option value="silno_znecisteny">Silno znečistený</option>
                                            <option value="poskodeny">Poškodený</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Poznámka</label>
                                        <input type="text" name="motor_od_poznamka" placeholder="Poznámka k odvodu">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="motor_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="motor_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Komponenty len na PRÍVOD -->
                <div class="section-header">
                    <h3>Komponenty - LEN PRÍVOD</h3>
                    <p class="help-text">Tieto komponenty sa nachádzajú len na prívodnej časti jednotky.</p>
                </div>

                <!-- CHLADIČ -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Chladič</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Typ chladiča</label>
                                <select name="chladic_typ">
                                    <option value="">-- Vyberte --</option>
                                    <option value="vodny">Vodný</option>
                                    <option value="priamy">Priamy (DX)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Bližšia špecifikácia</label>
                                <input type="text" name="chladic_spec" placeholder="Typ / Model">
                            </div>
                            <div class="form-group">
                                <label>Výkon</label>
                                <input type="text" name="chladic_vykon" placeholder="Napr. 15 kW">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Stav</label>
                                <select name="chladic_stav">
                                    <option value="">-- Vyberte stav --</option>
                                    <option value="cisty">Čistý</option>
                                    <option value="mierne_znecisteny">Mierne znečistený</option>
                                    <option value="znecisteny">Znečistený</option>
                                    <option value="silno_znecisteny">Silno znečistený</option>
                                    <option value="poskodeny">Poškodený</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Poznámka</label>
                                <input type="text" name="chladic_poznamka" placeholder="Poznámka k chladiču">
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="chladic_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="chladic_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- OHRIEVAČ -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Ohrievač</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Typ ohrievača</label>
                                <select name="ohrievac_typ" onchange="toggleOhrievacFields(this)">
                                    <option value="">-- Vyberte --</option>
                                    <option value="vodny">Vodný</option>
                                    <option value="elektricky">Elektrický</option>
                                    <option value="plynovy">Plynový</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Výkon</label>
                                <input type="text" name="ohrievac_vykon" placeholder="Napr. 20 kW">
                            </div>
                        </div>
                        
                        <!-- Plynový ohrievač - dodatočné polia -->
                        <div class="conditional-section" id="ohrievac_plynovy_fields" style="display:none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Typ plynového horáka</label>
                                    <input type="text" name="ohrievac_plyn_typ" placeholder="Typ horáka">
                                </div>
                                <div class="form-group">
                                    <label>Bypass</label>
                                    <select name="ohrievac_bypass" onchange="toggleOhrievacBypass(this)">
                                        <option value="">-- Vyberte --</option>
                                        <option value="bez_bypasu">Bez bypasu</option>
                                        <option value="s_bypasom">S bypasom</option>
                                    </select>
                                </div>
                            </div>
                            <div class="conditional-section" id="ohrievac_bypass_servo_field" style="display:none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Servopohon bypasu</label>
                                        <select name="ohrievac_bypass_servo">
                                            <option value="">-- Vyberte --</option>
                                            <option value="bez_servopohonu">Bez servopohonu</option>
                                            <option value="so_servopohonom">So servopohonom</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Stav</label>
                                <select name="ohrievac_stav">
                                    <option value="">-- Vyberte stav --</option>
                                    <option value="cisty">Čistý</option>
                                    <option value="mierne_znecisteny">Mierne znečistený</option>
                                    <option value="znecisteny">Znečistený</option>
                                    <option value="silno_znecisteny">Silno znečistený</option>
                                    <option value="poskodeny">Poškodený</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Poznámka</label>
                                <input type="text" name="ohrievac_poznamka" placeholder="Poznámka k ohrievaču">
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="ohrievac_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="ohrievac_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KOMÍNOVÝ TERMOSTAT -->
                <div class="component-card">
                    <div class="component-header">
                        <h4>Komínový termostat</h4>
                    </div>
                    <div class="component-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Komínový termostat</label>
                                <select name="kominovy_termostat">
                                    <option value="">-- Vyberte --</option>
                                    <option value="ma">Má</option>
                                    <option value="nema">Nemá</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Stav</label>
                                <select name="kominovy_termostat_stav">
                                    <option value="">-- Vyberte stav --</option>
                                    <option value="cisty">Čistý</option>
                                    <option value="mierne_znecisteny">Mierne znečistený</option>
                                    <option value="znecisteny">Znečistený</option>
                                    <option value="silno_znecisteny">Silno znečistený</option>
                                    <option value="poskodeny">Poškodený</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Poznámka</label>
                                <input type="text" name="kominovy_termostat_poznamka" placeholder="Poznámka">
                            </div>
                        </div>
                        <!-- Zhodnotenie a odporúčanie pre komponent -->
                        <div class="component-evaluation">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zhodnotenie stavu</label>
                                    <textarea name="termostat_zhodnotenie" placeholder="Zhodnotenie stavu komponentu..." rows="2"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Odporúčanie</label>
                                    <textarea name="termostat_odporucanie" placeholder="Odporúčanie pre zákazníka..." rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Všeobecná poznámka k servisu -->
                <div class="section-header">
                    <h3>Všeobecná poznámka</h3>
                </div>
                <div class="form-group">
                    <label>Ďalšie poznámky k servisu</label>
                    <textarea name="poznamka" placeholder="Ďalšie poznámky k servisu..." rows="3"></textarea>
                </div>

                <!-- Legacy photo inputs for backward compatibility -->
                <input type="file" id="cameraInput" accept="image/*" capture="environment" style="display: none;">
                <input type="file" id="galleryInput" accept="image/*" multiple style="display: none;">
                <div id="photoPreview" class="photo-preview" style="display: none;"></div>
                <div id="photoCount" class="photo-count" style="display: none;"></div>
            </form>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(4)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(4)">Ďalej</button>
            </div>
        </div>

        <!-- Step 5: Fotky po servise (NEW) -->
        <div class="step <?= $currentStep === 5 ? 'active' : '' ?>" id="step-5">
            <h2>Krok 6: Fotografie PO servise</h2>
            
            <div class="section-header">
                <h3>Dokumentácia stavu po servise</h3>
                <p class="help-text">Nafotografujte zariadenie a jeho komponenty po dokončení servisu. Tieto fotografie dokumentujú výsledok vykonanej práce.</p>
            </div>
            
            <div class="photo-upload-section full-width">
                <div class="photo-section" data-photo-type="after">
                    <div class="photo-buttons">
                        <button type="button" class="btn btn-camera btn-lg" onclick="openCameraForType('after')">
                            Odfotiť
                        </button>
                        <input type="file" id="cameraInputAfter" accept="image/*" capture="environment" style="display: none;" data-photo-type="after">
                        
                        <button type="button" class="btn btn-outline btn-lg" onclick="document.getElementById('galleryInputAfter').click()">
                            Vybrať z galérie
                        </button>
                        <input type="file" id="galleryInputAfter" accept="image/*" multiple style="display: none;" data-photo-type="after">
                    </div>
                    <div id="photoPreviewAfter" class="photo-preview"></div>
                    <div id="photoCountAfter" class="photo-count"></div>
                </div>
            </div>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(5)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(5)">Ďalej</button>
            </div>
        </div>

        <!-- Step 6: Podpisy -->
        <div class="step <?= $currentStep === 6 ? 'active' : '' ?>" id="step-6">
            <h2>Krok 7: Podpisy a odovzdanie</h2>
            
            <form id="signaturesForm">
                <!-- Miesto a dátum -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Miesto a dátum odovzdania</label>
                        <input type="text" name="miesto_datum" placeholder="Napr. Bratislava, <?= date('d.m.Y') ?>">
                    </div>
                </div>
                
                <!-- Technik -->
                <div class="signature-section">
                    <h3>Servisný technik</h3>
                    <div class="signature-container">
                        <canvas id="signatureTechnik" width="400" height="200"></canvas>
                        <div class="signature-controls">
                            <button type="button" class="btn btn-outline btn-sm" onclick="clearSignature('Technik')">Vymazať</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="saveSignature('technik')">Uložiť podpis</button>
                        </div>
                        <span id="signatureTechnikStatus" class="status-text"></span>
                    </div>
                </div>

                <!-- Zákazník -->
                <div class="signature-section">
                    <h3>Skontroloval a prevzal (zákazník)</h3>
                    <div class="form-group">
                        <label>Meno a priezvisko zákazníka</label>
                        <input type="text" name="skontroloval_prevzal" placeholder="Meno osoby, ktorá prevzala prácu">
                    </div>
                    <div class="signature-container">
                        <canvas id="signatureZakaznik" width="400" height="200"></canvas>
                        <div class="signature-controls">
                            <button type="button" class="btn btn-outline btn-sm" onclick="clearSignature('Zakaznik')">Vymazať</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="saveSignature('zakaznik')">Uložiť podpis</button>
                        </div>
                        <span id="signatureZakaznikStatus" class="status-text"></span>
                    </div>
                </div>
            </form>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(6)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(6)">Ďalej</button>
            </div>
        </div>

        <!-- Step 7: Súhrn a dokončenie -->
        <div class="step <?= $currentStep === 7 ? 'active' : '' ?>" id="step-7">
            <h2>Krok 8: Súhrn a dokončenie</h2>
            
            <div id="reportSummary" class="summary-box">
                <!-- Vyplnené JavaScriptom -->
            </div>

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(7)">Späť</button>
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
            } else if (window.pageView === 'location_detail') {
                const urlParams = new URLSearchParams(window.location.search);
                const locationId = urlParams.get('location_id');
                if (locationId) {
                    loadLocationDetail(locationId);
                }
            } else if (window.pageView === 'device_detail') {
                const urlParams = new URLSearchParams(window.location.search);
                const deviceId = urlParams.get('device_id');
                if (deviceId) {
                    loadDeviceDetail(deviceId);
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
                    
                    // Prevádzky a zariadenia - s klikateľnými kartami
                    let locHtml = '';
                    if (data.locations.length === 0) {
                        locHtml = '<p class="no-data">Žiadne prevádzky</p>';
                    } else {
                        locHtml = '<div class="expandable-list">';
                        data.locations.forEach(loc => {
                            locHtml += `
                                <div class="expandable-card location-expandable">
                                    <div class="card-header clickable" onclick="window.location.href='index.php?action=location_detail&location_id=${loc.id}'">
                                        <div class="card-title">
                                            <h4>${escapeHtml(loc.nazov)}</h4>
                                            <span class="card-subtitle">${loc.adresa || ''} ${loc.mesto || ''}</span>
                                        </div>
                                        <div class="card-meta">
                                            <span class="badge">${loc.devices.length} zariadení</span>
                                            <span class="arrow">→</span>
                                        </div>
                                    </div>
                                    <div class="card-devices">`;
                            
                            if (loc.devices.length === 0) {
                                locHtml += '<p class="no-data small">Žiadne zariadenia na tejto prevádzke</p>';
                            } else {
                                locHtml += '<div class="devices-grid">';
                                loc.devices.forEach(d => {
                                    locHtml += `
                                        <div class="device-mini-card clickable" onclick="event.stopPropagation(); window.location.href='index.php?action=device_detail&device_id=${d.id}'">
                                            <div class="device-info">
                                                <strong>${escapeHtml(d.nazov)}</strong>
                                                ${d.typ ? '<span class="device-type">' + escapeHtml(d.typ) + '</span>' : ''}
                                            </div>
                                            <span class="arrow">→</span>
                                        </div>`;
                                });
                                locHtml += '</div>';
                            }
                            
                            locHtml += '</div></div>';
                        });
                        locHtml += '</div>';
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
                                <td><a href="index.php?action=download_pdf&report_id=${r.id}" class="btn btn-sm">PDF</a></td>
                            </tr>`;
                        });
                        html += '</tbody></table></div>';
                    }
                    document.getElementById('reportsContent').innerHTML = html;
                })
                .catch(err => console.error('Chyba:', err));
        }
        
        // Location detail funkcie
        function loadLocationDetail(locationId) {
            fetch('index.php?action=api_location_detail&location_id=' + locationId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Prevádzka nebola nájdená');
                        return;
                    }
                    
                    const loc = data.location;
                    document.getElementById('locationName').textContent = loc.nazov;
                    document.getElementById('backToCustomerLink').href = 'index.php?action=customer_detail&customer_id=' + loc.customer_id;
                    
                    let infoHtml = '<div class="info-grid">';
                    infoHtml += `<div class="info-item"><strong>Zákazník:</strong> ${loc.customer_name || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Adresa:</strong> ${loc.adresa || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Mesto:</strong> ${loc.mesto || '-'}</div>`;
                    if (loc.poznamka) {
                        infoHtml += `<div class="info-item full-width"><strong>Poznámka:</strong> ${loc.poznamka}</div>`;
                    }
                    infoHtml += '</div>';
                    document.getElementById('locationInfo').innerHTML = infoHtml;
                    
                    // Zariadenia
                    let devHtml = '';
                    if (data.devices.length === 0) {
                        devHtml = '<p class="no-data">Žiadne zariadenia na tejto prevádzke</p>';
                    } else {
                        devHtml = '<div class="devices-list-cards">';
                        data.devices.forEach(d => {
                            devHtml += `
                                <div class="device-card clickable" onclick="window.location.href='index.php?action=device_detail&device_id=${d.id}'">
                                    <div class="device-card-content">
                                        <h4>${escapeHtml(d.nazov)}</h4>
                                        <p>${d.typ || ''} ${d.vyrobne_cislo ? '| S/N: ' + escapeHtml(d.vyrobne_cislo) : ''}</p>
                                        ${d.vyrobca ? '<p class="small">Výrobca: ' + escapeHtml(d.vyrobca) + '</p>' : ''}
                                    </div>
                                    <div class="device-card-meta">
                                        <span class="badge">${d.reports_count || 0} protokolov</span>
                                        <span class="arrow">→</span>
                                    </div>
                                </div>`;
                        });
                        devHtml += '</div>';
                    }
                    document.getElementById('devicesContent').innerHTML = devHtml;
                    
                    // Protokoly prevádzky
                    let repHtml = '';
                    if (data.reports.length === 0) {
                        repHtml = '<p class="no-data">Žiadne protokoly pre túto prevádzku</p>';
                    } else {
                        repHtml = '<div class="reports-table"><table>';
                        repHtml += '<thead><tr><th>Číslo</th><th>Dátum</th><th>Zariadenie</th><th>Akcia</th></tr></thead>';
                        repHtml += '<tbody>';
                        data.reports.forEach(r => {
                            repHtml += `<tr>
                                <td>${r.cislo_protokolu}</td>
                                <td>${r.datum}</td>
                                <td>${r.device_name || '-'}</td>
                                <td><a href="index.php?action=download_pdf&report_id=${r.id}" class="btn btn-sm">PDF</a></td>
                            </tr>`;
                        });
                        repHtml += '</tbody></table></div>';
                    }
                    document.getElementById('locationReportsContent').innerHTML = repHtml;
                })
                .catch(err => console.error('Chyba:', err));
        }
        
        function showLocationTab(tabName) {
            document.querySelectorAll('.location-detail-page .tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.location-detail-page .tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Device detail funkcie
        function loadDeviceDetail(deviceId) {
            fetch('index.php?action=api_device_detail&device_id=' + deviceId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Zariadenie nebolo nájdené');
                        return;
                    }
                    
                    const d = data.device;
                    document.getElementById('deviceName').textContent = d.nazov;
                    document.getElementById('backToLocationLink').href = 'index.php?action=location_detail&location_id=' + d.location_id;
                    
                    let infoHtml = '<div class="info-grid">';
                    infoHtml += `<div class="info-item"><strong>Zákazník:</strong> ${d.customer_name || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Prevádzka:</strong> ${d.location_name || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Typ/Model:</strong> ${d.typ || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Výrobné číslo:</strong> ${d.vyrobne_cislo || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Rok výroby:</strong> ${d.rok_vyroby || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Prevedenie:</strong> ${d.prevedenie || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Výrobca:</strong> ${d.vyrobca || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Distribúcia SR:</strong> ${d.distribucia || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Servisné stredisko:</strong> ${d.servisne_stredisko || '-'}</div>`;
                    infoHtml += `<div class="info-item"><strong>Tel. servisu:</strong> ${d.servisne_stredisko_tel || '-'}</div>`;
                    infoHtml += '</div>';
                    document.getElementById('deviceInfo').innerHTML = infoHtml;
                    
                    // Protokoly zariadenia
                    let repHtml = '';
                    if (data.reports.length === 0) {
                        repHtml = '<p class="no-data">Žiadne protokoly pre toto zariadenie</p>';
                    } else {
                        repHtml = '<div class="reports-table"><table>';
                        repHtml += '<thead><tr><th>Číslo protokolu</th><th>Dátum</th><th>Servis vykonal</th><th>Akcia</th></tr></thead>';
                        repHtml += '<tbody>';
                        data.reports.forEach(r => {
                            repHtml += `<tr>
                                <td>${r.cislo_protokolu}</td>
                                <td>${r.datum}</td>
                                <td>${r.servis_vykonal || '-'}</td>
                                <td><a href="index.php?action=download_pdf&report_id=${r.id}" class="btn btn-sm">PDF</a></td>
                            </tr>`;
                        });
                        repHtml += '</tbody></table></div>';
                    }
                    document.getElementById('deviceReportsContent').innerHTML = repHtml;
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
