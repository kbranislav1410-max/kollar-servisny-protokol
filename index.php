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
    
    // Kontrola veľkosti
    if ($file['size'] > MAX_FILE_SIZE) {
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
    
    // Generovanie čísla protokolu
    $cisloProtokolu = 'P-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Uloženie zariadenia ak existuje
    $deviceId = null;
    if (!empty($report['device_nazov'])) {
        $stmt = $pdo->prepare("
            INSERT INTO devices (location_id, nazov, typ, vyrobne_cislo, poznamka)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $report['location_id'] ?? null,
            $report['device_nazov'],
            $report['device_typ'] ?? '',
            $report['device_vyrobne_cislo'] ?? '',
            $report['device_poznamka'] ?? '',
        ]);
        $deviceId = $pdo->lastInsertId();
    }
    
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
    case 'api_reports':
        getReports();
        break;
    case 'download_pdf':
        downloadPdf();
        break;
    case 'new_report':
        // Reset session pre nový report
        unset($_SESSION['report']);
        $_SESSION['current_step'] = 0;
        break;
}

// Získanie aktuálnych dát pre zobrazenie
$currentStep = $_SESSION['current_step'] ?? 0;
$reportData = $_SESSION['report'] ?? [];

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
            <li><a href="index.html">Pôvodný protokol</a></li>
            <li><a href="index.php">MVP Protokol</a></li>
            <li><a href="index.php?action=new_report">Nový report</a></li>
            <li><a href="zoznam-zakaznikov.html">Zoznam zákazníkov</a></li>
        </ul>
    </nav>

    <main class="content">
        <h1>Servisný Protokol MVP</h1>

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

            <div class="divider">alebo</div>

            <h3>Pridať nového zákazníka</h3>
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

            <div class="divider">alebo</div>

            <h3>Pridať novú prevádzku</h3>
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

            <div class="navigation">
                <button type="button" class="btn btn-outline" onclick="prevStep(1)">Späť</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(1)">Ďalej</button>
            </div>
        </div>

        <!-- Step 2: Zariadenie -->
        <div class="step <?= $currentStep === 2 ? 'active' : '' ?>" id="step-2">
            <h2>Krok 3: Údaje o zariadení (voliteľné)</h2>
            
            <form id="deviceForm">
                <div class="form-group">
                    <input type="text" name="device_nazov" placeholder="Názov zariadenia">
                </div>
                <div class="form-group">
                    <input type="text" name="device_typ" placeholder="Typ zariadenia">
                </div>
                <div class="form-group">
                    <input type="text" name="device_vyrobne_cislo" placeholder="Výrobné číslo">
                </div>
                <div class="form-group">
                    <textarea name="device_poznamka" placeholder="Poznámka k zariadeniu"></textarea>
                </div>
            </form>

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
    </main>

    <script src="assets/js/signature_pad.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Inicializácia s dátami zo session
        window.reportData = <?= json_encode($reportData) ?>;
        window.currentStep = <?= $currentStep ?>;
    </script>
</body>
</html>
