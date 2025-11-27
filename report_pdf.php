<?php
/**
 * PDF Template for Service Protocol Report
 * 
 * This file is included by index.php when generating PDF.
 * Variables available: $report, $attachments
 */

// Ensure we have report data
if (!isset($report)) {
    $report = [];
}
if (!isset($attachments)) {
    $attachments = [];
}

// Helper function to get absolute path for images with path traversal protection
$getImagePath = function($relativePath) {
    // Sanitize path - remove any directory traversal attempts
    $sanitizedPath = str_replace(['../', '..\\', '..'], '', $relativePath);
    
    // Ensure path starts with expected directories only
    $allowedPrefixes = ['uploads/photos/', 'uploads/signatures/'];
    $isAllowed = false;
    foreach ($allowedPrefixes as $prefix) {
        if (strpos($sanitizedPath, $prefix) === 0) {
            $isAllowed = true;
            break;
        }
    }
    
    if (!$isAllowed) {
        return '';
    }
    
    $fullPath = BASE_PATH . '/' . $sanitizedPath;
    
    // Verify the resolved path is within allowed directories
    $realPath = realpath($fullPath);
    $uploadsDir = realpath(BASE_PATH . '/uploads');
    
    if ($realPath === false || $uploadsDir === false || strpos($realPath, $uploadsDir) !== 0) {
        return '';
    }
    
    if (file_exists($fullPath)) {
        $type = pathinfo($fullPath, PATHINFO_EXTENSION);
        $data = file_get_contents($fullPath);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return '';
};

// Group attachments by section
$photosBefore = [];
$photosAfter = [];
$photosOther = [];

foreach ($attachments as $attachment) {
    switch ($attachment['section']) {
        case 'before':
            $photosBefore[] = $attachment;
            break;
        case 'after':
            $photosAfter[] = $attachment;
            break;
        default:
            $photosOther[] = $attachment;
    }
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Servisný protokol <?= htmlspecialchars($report['cislo_protokolu'] ?? '') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        .container {
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 18pt;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .header .protocol-number {
            font-size: 12pt;
            color: #666;
        }
        .section {
            margin-bottom: 15px;
        }
        .section-title {
            background-color: #2c3e50;
            color: white;
            padding: 5px 10px;
            font-size: 11pt;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table td, table th {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }
        table th {
            background-color: #f4f4f4;
            font-weight: bold;
            width: 30%;
        }
        .two-column {
            width: 100%;
        }
        .two-column td {
            width: 50%;
            vertical-align: top;
        }
        .component-table th {
            width: 40%;
        }
        .note-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            min-height: 50px;
        }
        .photos-section {
            margin-top: 10px;
        }
        .photos-grid {
            display: block;
        }
        .photo-item {
            display: inline-block;
            width: 45%;
            margin: 5px;
            text-align: center;
        }
        .photo-item img {
            max-width: 100%;
            max-height: 150px;
            border: 1px solid #ddd;
        }
        .photo-caption {
            font-size: 8pt;
            color: #666;
            margin-top: 3px;
        }
        .signatures {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signatures table {
            border: none;
        }
        .signatures td {
            border: none;
            width: 50%;
            text-align: center;
            padding: 10px;
        }
        .signature-box {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 5px;
        }
        .signature-img {
            max-height: 60px;
            max-width: 150px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SERVISNÝ PROTOKOL</h1>
            <div class="protocol-number">Číslo: <?= htmlspecialchars($report['cislo_protokolu'] ?? '-') ?></div>
            <div>Dátum: <?= htmlspecialchars($report['datum'] ?? date('Y-m-d')) ?></div>
        </div>

        <div class="section">
            <div class="section-title">Údaje o zákazníkovi</div>
            <table>
                <tr>
                    <th>Názov firmy</th>
                    <td><?= htmlspecialchars($report['nazov_firmy'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>IČO</th>
                    <td><?= htmlspecialchars($report['ico'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>DIČ</th>
                    <td><?= htmlspecialchars($report['dic'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>IČ DPH</th>
                    <td><?= htmlspecialchars($report['ic_dph'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Sídlo</th>
                    <td><?= htmlspecialchars($report['sidlo'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Kontaktná osoba</th>
                    <td><?= htmlspecialchars($report['kontakt_osoba'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Telefón</th>
                    <td><?= htmlspecialchars($report['telefon'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= htmlspecialchars($report['email'] ?? '-') ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Prevádzka a zariadenie</div>
            <table>
                <tr>
                    <th>Prevádzka</th>
                    <td><?= htmlspecialchars(($report['location_nazov'] ?? '') . ' - ' . ($report['location_mesto'] ?? '')) ?></td>
                </tr>
                <tr>
                    <th>Adresa prevádzky</th>
                    <td><?= htmlspecialchars($report['location_adresa'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Zariadenie</th>
                    <td><?= htmlspecialchars($report['device_nazov'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Typ zariadenia</th>
                    <td><?= htmlspecialchars($report['device_typ'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Výrobné číslo</th>
                    <td><?= htmlspecialchars($report['vyrobne_cislo'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Technik</th>
                    <td><?= htmlspecialchars($report['technik_meno'] ?? '-') ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Technické údaje - stav komponentov</div>
            <table class="component-table">
                <tr>
                    <th>Klapka - prívod</th>
                    <td><?= htmlspecialchars($report['klapky_pr'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Klapka - odvod</th>
                    <td><?= htmlspecialchars($report['klapky_od'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Filtrácia - prívod</th>
                    <td><?= htmlspecialchars($report['filtracia_pr'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Filtrácia - odvod</th>
                    <td><?= htmlspecialchars($report['filtracia_od'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Rekuperácia</th>
                    <td><?= htmlspecialchars($report['rekuperacia'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Ventilátor</th>
                    <td><?= htmlspecialchars($report['ventilator'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Ohrievač</th>
                    <td><?= htmlspecialchars($report['ohri'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Plynový horák</th>
                    <td><?= htmlspecialchars($report['horak'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Chladič</th>
                    <td><?= htmlspecialchars($report['chladic'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Zvukový tlmič</th>
                    <td><?= htmlspecialchars($report['tlmic'] ?? '-') ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Poznámka</div>
            <div class="note-box">
                <?= nl2br(htmlspecialchars($report['poznamka'] ?? '')) ?>
            </div>
        </div>

        <?php if (!empty($photosBefore)): ?>
        <div class="section photos-section">
            <div class="section-title">Fotografie - pred servisom</div>
            <div class="photos-grid">
                <?php foreach ($photosBefore as $photo): ?>
                <div class="photo-item">
                    <img src="<?= $getImagePath($photo['file_path']) ?>" alt="<?= htmlspecialchars($photo['original_name'] ?? '') ?>">
                    <div class="photo-caption"><?= htmlspecialchars($photo['original_name'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($photosAfter)): ?>
        <div class="section photos-section">
            <div class="section-title">Fotografie - po servise</div>
            <div class="photos-grid">
                <?php foreach ($photosAfter as $photo): ?>
                <div class="photo-item">
                    <img src="<?= $getImagePath($photo['file_path']) ?>" alt="<?= htmlspecialchars($photo['original_name'] ?? '') ?>">
                    <div class="photo-caption"><?= htmlspecialchars($photo['original_name'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($photosOther)): ?>
        <div class="section photos-section">
            <div class="section-title">Ďalšie fotografie</div>
            <div class="photos-grid">
                <?php foreach ($photosOther as $photo): ?>
                <div class="photo-item">
                    <img src="<?= $getImagePath($photo['file_path']) ?>" alt="<?= htmlspecialchars($photo['original_name'] ?? '') ?>">
                    <div class="photo-caption"><?= htmlspecialchars($photo['original_name'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="signatures">
            <table>
                <tr>
                    <td>
                        <?php if (!empty($report['signature_technician'])): ?>
                        <img src="<?= $getImagePath($report['signature_technician']) ?>" class="signature-img" alt="Podpis technika">
                        <?php endif; ?>
                        <div class="signature-box">Podpis technika</div>
                    </td>
                    <td>
                        <?php if (!empty($report['signature_customer'])): ?>
                        <img src="<?= $getImagePath($report['signature_customer']) ?>" class="signature-img" alt="Podpis zákazníka">
                        <?php endif; ?>
                        <div class="signature-box">Podpis zákazníka</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            Protokol vygenerovaný: <?= date('d.m.Y H:i') ?> | <?= APP_NAME ?> v<?= APP_VERSION ?>
        </div>
    </div>
</body>
</html>
