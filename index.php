<?php
/**
 * Main router for Servisný Protokol application
 * 
 * Handles all API endpoints for the service protocol workflow:
 * - Customers management
 * - Locations management
 * - Devices management
 * - Report workflow (start, step navigation, save, finalize)
 * - Photo uploads
 * - Signature capture
 * - PDF generation and email sending
 */

require_once __DIR__ . '/config.php';

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Set JSON content type for API responses
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Get the action from query parameter or path
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'home';

// Route to appropriate handler
try {
    switch ($action) {
        case 'home':
            handleHome();
            break;
            
        // Customer endpoints
        case 'customers':
            handleCustomers();
            break;
        case 'add_customer':
            handleAddCustomer();
            break;
            
        // Location endpoints
        case 'locations':
            handleLocations();
            break;
        case 'add_location':
            handleAddLocation();
            break;
            
        // Device endpoints
        case 'devices':
            handleDevices();
            break;
        case 'add_device':
            handleAddDevice();
            break;
            
        // Report workflow endpoints
        case 'start_report':
            handleStartReport();
            break;
        case 'report_step':
            handleReportStep();
            break;
        case 'report_save_step':
            handleReportSaveStep();
            break;
        case 'get_report':
            handleGetReport();
            break;
        case 'list_reports':
            handleListReports();
            break;
            
        // Upload endpoints
        case 'upload_photos':
            handleUploadPhotos();
            break;
        case 'save_signatures':
            handleSaveSignatures();
            break;
            
        // Finalize and send
        case 'finalize_report':
            handleFinalizeReport();
            break;
            
        default:
            sendErrorResponse('Neznáma akcia: ' . $action, 404);
    }
} catch (PDOException $e) {
    sendErrorResponse('Chyba databázy: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendErrorResponse($e->getMessage(), 500);
}

/**
 * Home endpoint - returns API info
 */
function handleHome(): void {
    sendJsonResponse([
        'app' => APP_NAME,
        'version' => APP_VERSION,
        'endpoints' => [
            'customers' => '?action=customers',
            'add_customer' => '?action=add_customer (POST)',
            'locations' => '?action=locations&customer_id=X',
            'add_location' => '?action=add_location (POST)',
            'devices' => '?action=devices&location_id=X',
            'add_device' => '?action=add_device (POST)',
            'start_report' => '?action=start_report (POST)',
            'report_step' => '?action=report_step&report_id=X&step=Y',
            'report_save_step' => '?action=report_save_step (POST)',
            'upload_photos' => '?action=upload_photos (POST multipart)',
            'save_signatures' => '?action=save_signatures (POST JSON)',
            'finalize_report' => '?action=finalize_report (POST)'
        ]
    ]);
}

/**
 * List all customers
 */
function handleCustomers(): void {
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT id, nazov_firmy, ico, dic, ic_dph, sidlo, kontakt_osoba, telefon, email
        FROM customers 
        ORDER BY nazov_firmy ASC
    ");
    sendJsonResponse($stmt->fetchAll());
}

/**
 * Add a new customer
 */
function handleAddCustomer(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Vyžaduje sa metóda POST', 405);
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO customers (nazov_firmy, ico, dic, ic_dph, sidlo, kontakt_osoba, telefon, email)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        sanitizeInput($_POST['nazov'] ?? ''),
        sanitizeInput($_POST['ico'] ?? ''),
        sanitizeInput($_POST['dic'] ?? ''),
        sanitizeInput($_POST['icdph'] ?? ''),
        sanitizeInput($_POST['sidlo'] ?? ''),
        sanitizeInput($_POST['osoba'] ?? ''),
        sanitizeInput($_POST['telefon'] ?? ''),
        sanitizeInput($_POST['email'] ?? '')
    ]);
    
    sendJsonResponse([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'message' => 'Zákazník bol úspešne pridaný'
    ]);
}

/**
 * List locations for a customer
 */
function handleLocations(): void {
    $customerId = filter_var($_GET['customer_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$customerId) {
        sendErrorResponse('Chýba parameter customer_id');
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT id, nazov, adresa, mesto, psc, poznamka
        FROM locations 
        WHERE customer_id = ?
        ORDER BY nazov ASC
    ");
    $stmt->execute([$customerId]);
    sendJsonResponse($stmt->fetchAll());
}

/**
 * Add a new location
 */
function handleAddLocation(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Vyžaduje sa metóda POST', 405);
    }
    
    $customerId = filter_var($_POST['customer_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$customerId) {
        sendErrorResponse('Chýba parameter customer_id');
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO locations (customer_id, nazov, adresa, mesto, psc, poznamka)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $customerId,
        sanitizeInput($_POST['nazov'] ?? ''),
        sanitizeInput($_POST['adresa'] ?? ''),
        sanitizeInput($_POST['mesto'] ?? ''),
        sanitizeInput($_POST['psc'] ?? ''),
        sanitizeInput($_POST['poznamka'] ?? '', 1000)
    ]);
    
    sendJsonResponse([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'message' => 'Prevádzka bola úspešne pridaná'
    ]);
}

/**
 * List devices for a location
 */
function handleDevices(): void {
    $locationId = filter_var($_GET['location_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$locationId) {
        sendErrorResponse('Chýba parameter location_id');
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT id, nazov, typ, vyrobne_cislo, poznamka
        FROM devices 
        WHERE location_id = ?
        ORDER BY nazov ASC
    ");
    $stmt->execute([$locationId]);
    sendJsonResponse($stmt->fetchAll());
}

/**
 * Add a new device
 */
function handleAddDevice(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Vyžaduje sa metóda POST', 405);
    }
    
    $locationId = filter_var($_POST['location_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$locationId) {
        sendErrorResponse('Chýba parameter location_id');
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO devices (location_id, nazov, typ, vyrobne_cislo, poznamka)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $locationId,
        sanitizeInput($_POST['nazov'] ?? ''),
        sanitizeInput($_POST['typ'] ?? ''),
        sanitizeInput($_POST['vyrobne_cislo'] ?? ''),
        sanitizeInput($_POST['poznamka'] ?? '', 1000)
    ]);
    
    sendJsonResponse([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'message' => 'Zariadenie bolo úspešne pridané'
    ]);
}

/**
 * Start a new report - creates a draft report
 */
function handleStartReport(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Vyžaduje sa metóda POST', 405);
    }
    
    $pdo = getDbConnection();
    
    // Generate unique protocol number
    $date = date('Ymd');
    $random = str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
    $cisloProtokolu = "P-{$date}-{$random}";
    
    // Ensure unique protocol number
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE cislo_protokolu = ?");
    $stmt->execute([$cisloProtokolu]);
    while ($stmt->fetchColumn() > 0) {
        $random = str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $cisloProtokolu = "P-{$date}-{$random}";
        $stmt->execute([$cisloProtokolu]);
    }
    
    $customerId = filter_var($_POST['customer_id'] ?? 0, FILTER_VALIDATE_INT);
    $locationId = filter_var($_POST['location_id'] ?? 0, FILTER_VALIDATE_INT);
    $deviceId = filter_var($_POST['device_id'] ?? 0, FILTER_VALIDATE_INT);
    
    $stmt = $pdo->prepare("
        INSERT INTO reports (cislo_protokolu, customer_id, location_id, device_id, datum, technik_meno, status, step)
        VALUES (?, ?, ?, ?, ?, ?, 'draft', 0)
    ");
    
    $stmt->execute([
        $cisloProtokolu,
        $customerId ?: null,
        $locationId ?: null,
        $deviceId ?: null,
        date('Y-m-d'),
        sanitizeInput($_POST['technik_meno'] ?? '')
    ]);
    
    $reportId = $pdo->lastInsertId();
    
    sendJsonResponse([
        'success' => true,
        'report_id' => $reportId,
        'cislo_protokolu' => $cisloProtokolu,
        'message' => 'Protokol bol vytvorený'
    ]);
}

/**
 * Get report step data
 */
function handleReportStep(): void {
    $reportId = filter_var($_GET['report_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$reportId) {
        sendErrorResponse('Chýba parameter report_id');
    }
    
    $step = filter_var($_GET['step'] ?? null, FILTER_VALIDATE_INT);
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if (!$report) {
        sendErrorResponse('Protokol nebol nájdený', 404);
    }
    
    // Get attachments
    $stmt = $pdo->prepare("SELECT id, file_path, section, original_name FROM attachments WHERE report_id = ?");
    $stmt->execute([$reportId]);
    $attachments = $stmt->fetchAll();
    
    sendJsonResponse([
        'report' => $report,
        'attachments' => $attachments,
        'current_step' => $step ?? $report['step']
    ]);
}

/**
 * Get a single report by ID
 */
function handleGetReport(): void {
    $reportId = filter_var($_GET['report_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$reportId) {
        sendErrorResponse('Chýba parameter report_id');
    }
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT r.*, 
               c.nazov_firmy, c.ico, c.dic, c.ic_dph, c.sidlo, c.kontakt_osoba, c.telefon, c.email,
               l.nazov as location_nazov, l.adresa as location_adresa, l.mesto as location_mesto,
               d.nazov as device_nazov, d.typ as device_typ, d.vyrobne_cislo
        FROM reports r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN locations l ON r.location_id = l.id
        LEFT JOIN devices d ON r.device_id = d.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if (!$report) {
        sendErrorResponse('Protokol nebol nájdený', 404);
    }
    
    // Get attachments
    $stmt = $pdo->prepare("SELECT id, file_path, section, original_name FROM attachments WHERE report_id = ?");
    $stmt->execute([$reportId]);
    $attachments = $stmt->fetchAll();
    
    $report['attachments'] = $attachments;
    
    sendJsonResponse($report);
}

/**
 * List all reports
 */
function handleListReports(): void {
    $pdo = getDbConnection();
    
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
    
    $sql = "
        SELECT r.id, r.cislo_protokolu, r.datum, r.technik_meno, r.status,
               c.nazov_firmy, l.nazov as location_nazov
        FROM reports r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN locations l ON r.location_id = l.id
    ";
    
    if ($status) {
        $sql .= " WHERE r.status = ?";
        $stmt = $pdo->prepare($sql . " ORDER BY r.created_at DESC");
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->query($sql . " ORDER BY r.created_at DESC");
    }
    
    sendJsonResponse($stmt->fetchAll());
}

/**
 * Save report step data
 */
function handleReportSaveStep(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Vyžaduje sa metóda POST', 405);
    }
    
    $reportId = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$reportId) {
        sendErrorResponse('Chýba parameter report_id');
    }
    
    $step = filter_var($_POST['step'] ?? null, FILTER_VALIDATE_INT);
    
    $pdo = getDbConnection();
    
    // Build update query based on provided fields
    $fields = [];
    $values = [];
    
    $allowedFields = [
        'customer_id', 'location_id', 'device_id', 'datum', 'technik_meno',
        'klapky_pr', 'klapky_od', 'filtracia_pr', 'filtracia_od',
        'rekuperacia', 'ventilator', 'ohri', 'horak', 'chladic', 'tlmic', 'poznamka'
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($_POST[$field])) {
            $fields[] = "$field = ?";
            if (in_array($field, ['customer_id', 'location_id', 'device_id'])) {
                $values[] = filter_var($_POST[$field] ?? 0, FILTER_VALIDATE_INT) ?: null;
            } else {
                $values[] = sanitizeInput($_POST[$field], $field === 'poznamka' ? 2000 : 255);
            }
        }
    }
    
    // Update step if provided
    if ($step !== false && $step !== null) {
        $fields[] = "step = ?";
        $values[] = $step;
    }
    
    // Update timestamp
    $fields[] = "updated_at = CURRENT_TIMESTAMP";
    
    if (empty($fields)) {
        sendErrorResponse('Žiadne dáta na uloženie');
    }
    
    $values[] = $reportId;
    
    $stmt = $pdo->prepare("UPDATE reports SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($values);
    
    sendJsonResponse([
        'success' => true,
        'message' => 'Krok bol uložený'
    ]);
}

/**
 * Handle photo uploads
 */
function handleUploadPhotos(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Vyžaduje sa metóda POST', 405);
    }
    
    $reportId = filter_var($_POST['report_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$reportId) {
        sendErrorResponse('Chýba parameter report_id');
    }
    
    $section = sanitizeInput($_POST['section'] ?? 'other');
    
    if (empty($_FILES['files'])) {
        sendErrorResponse('Žiadne súbory neboli nahrané');
    }
    
    // Ensure photos directory exists
    if (!is_dir(PHOTOS_PATH)) {
        mkdir(PHOTOS_PATH, 0755, true);
    }
    
    $pdo = getDbConnection();
    $uploadedFiles = [];
    
    // Handle multiple files
    $files = $_FILES['files'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        
        if ($error !== UPLOAD_ERR_OK) {
            continue;
        }
        
        if ($size > MAX_UPLOAD_SIZE) {
            continue;
        }
        
        // Validate file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName);
        
        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
            continue;
        }
        
        // Generate unique filename
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $newFilename = generateUniqueFilename($extension ?: 'jpg');
        $destPath = PHOTOS_PATH . '/' . $newFilename;
        
        if (move_uploaded_file($tmpName, $destPath)) {
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO attachments (report_id, file_path, file_type, section, original_name)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $reportId,
                'uploads/photos/' . $newFilename,
                $mimeType,
                $section,
                $name
            ]);
            
            $uploadedFiles[] = [
                'id' => $pdo->lastInsertId(),
                'file_path' => 'uploads/photos/' . $newFilename,
                'original_name' => $name,
                'section' => $section
            ];
        }
    }
    
    sendJsonResponse([
        'success' => true,
        'uploaded' => $uploadedFiles,
        'count' => count($uploadedFiles)
    ]);
}

/**
 * Save signatures (base64 PNG)
 */
function handleSaveSignatures(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Vyžaduje sa metóda POST', 405);
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Try form data
        $input = $_POST;
    }
    
    $reportId = filter_var($input['report_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$reportId) {
        sendErrorResponse('Chýba parameter report_id');
    }
    
    // Ensure signatures directory exists
    if (!is_dir(SIGNATURES_PATH)) {
        mkdir(SIGNATURES_PATH, 0755, true);
    }
    
    $pdo = getDbConnection();
    $savedFiles = [];
    
    // Process technician signature
    if (!empty($input['technician'])) {
        $filename = saveBase64Image($input['technician'], 'technician');
        if ($filename) {
            $savedFiles['technician'] = $filename;
            $stmt = $pdo->prepare("UPDATE reports SET signature_technician = ? WHERE id = ?");
            $stmt->execute(['uploads/signatures/' . $filename, $reportId]);
        }
    }
    
    // Process customer signature
    if (!empty($input['customer'])) {
        $filename = saveBase64Image($input['customer'], 'customer');
        if ($filename) {
            $savedFiles['customer'] = $filename;
            $stmt = $pdo->prepare("UPDATE reports SET signature_customer = ? WHERE id = ?");
            $stmt->execute(['uploads/signatures/' . $filename, $reportId]);
        }
    }
    
    sendJsonResponse([
        'success' => true,
        'files' => $savedFiles
    ]);
}

/**
 * Save base64 image to file
 */
function saveBase64Image(string $base64Data, string $prefix): ?string {
    // Remove data URL prefix if present
    if (strpos($base64Data, 'data:image') === 0) {
        $parts = explode(',', $base64Data, 2);
        $base64Data = $parts[1] ?? '';
    }
    
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        return null;
    }
    
    $filename = $prefix . '_' . generateUniqueFilename('png');
    $filePath = SIGNATURES_PATH . '/' . $filename;
    
    if (file_put_contents($filePath, $imageData) !== false) {
        return $filename;
    }
    
    return null;
}

/**
 * Finalize report - generate PDF and send email
 */
function handleFinalizeReport(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Vyžaduje sa metóda POST', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $reportId = filter_var($input['report_id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$reportId) {
        sendErrorResponse('Chýba parameter report_id');
    }
    
    $pdo = getDbConnection();
    
    // Get report data
    $stmt = $pdo->prepare("
        SELECT r.*, 
               c.nazov_firmy, c.ico, c.dic, c.ic_dph, c.sidlo, c.kontakt_osoba, c.telefon, c.email,
               l.nazov as location_nazov, l.adresa as location_adresa, l.mesto as location_mesto,
               d.nazov as device_nazov, d.typ as device_typ, d.vyrobne_cislo
        FROM reports r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN locations l ON r.location_id = l.id
        LEFT JOIN devices d ON r.device_id = d.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if (!$report) {
        sendErrorResponse('Protokol nebol nájdený', 404);
    }
    
    // Get attachments
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE report_id = ?");
    $stmt->execute([$reportId]);
    $attachments = $stmt->fetchAll();
    
    // Generate PDF
    $pdfPath = generatePdf($report, $attachments);
    
    // Update report status
    $stmt = $pdo->prepare("UPDATE reports SET status = 'finalized', pdf_path = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$pdfPath, $reportId]);
    
    // Send email if customer email is available
    $emailSent = false;
    $sendEmail = !empty($input['send_email']);
    
    if ($sendEmail && !empty($report['email'])) {
        $emailSent = sendReportEmail($report, BASE_PATH . '/' . $pdfPath);
        
        if ($emailSent) {
            $stmt = $pdo->prepare("UPDATE reports SET email_sent = 1, email_sent_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$reportId]);
        }
    }
    
    sendJsonResponse([
        'success' => true,
        'pdf_path' => $pdfPath,
        'email_sent' => $emailSent,
        'message' => 'Protokol bol úspešne dokončený'
    ]);
}

/**
 * Generate PDF from report data
 */
function generatePdf(array $report, array $attachments): string {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Ensure PDFs directory exists
    if (!is_dir(PDFS_PATH)) {
        mkdir(PDFS_PATH, 0755, true);
    }
    
    // Generate HTML content
    ob_start();
    include __DIR__ . '/report_pdf.php';
    $html = ob_get_clean();
    
    // Create Dompdf instance
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Save PDF
    $filename = 'report-' . $report['cislo_protokolu'] . '.pdf';
    $filePath = PDFS_PATH . '/' . $filename;
    file_put_contents($filePath, $dompdf->output());
    
    return 'uploads/pdfs/' . $filename;
}

/**
 * Send report email
 */
function sendReportEmail(array $report, string $pdfPath): bool {
    $to = $report['email'];
    $subject = 'Servisný protokol ' . $report['cislo_protokolu'];
    
    $boundary = md5(uniqid((string)time()));
    
    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"'
    ];
    
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=utf-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= "<html><body>";
    $message .= "<p>Dobrý deň,</p>";
    $message .= "<p>v prílohe Vám posielame servisný protokol číslo: <strong>{$report['cislo_protokolu']}</strong>.</p>";
    $message .= "<p>Dátum servisu: {$report['datum']}</p>";
    $message .= "<p>S pozdravom,<br>" . MAIL_FROM_NAME . "</p>";
    $message .= "</body></html>\r\n\r\n";
    
    // Attach PDF
    if (file_exists($pdfPath)) {
        $pdfContent = file_get_contents($pdfPath);
        $pdfBase64 = chunk_split(base64_encode($pdfContent));
        $pdfFilename = basename($pdfPath);
        
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
        $message .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= $pdfBase64 . "\r\n\r\n";
    }
    
    $message .= "--{$boundary}--";
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}
