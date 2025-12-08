<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Servisný protokol <?= htmlspecialchars($report['cislo_protokolu'] ?? '') ?></title>
<?php
/**
 * Pomocná funkcia pre konverziu obrázka na data URI pre Dompdf
 */
function getImageDataUri($path) {
    if (!file_exists($path)) {
        return null;
    }
    $imageData = file_get_contents($path);
    if ($imageData === false) {
        return null;
    }
    $mimeType = mime_content_type($path);
    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
}

/**
 * Preklad stavu komponentu
 */
function getStavLabel($stav) {
    $stavy = [
        'cisty' => 'Čistý',
        'mierne_znecisteny' => 'Mierne znečistený',
        'znecisteny' => 'Znečistený',
        'silno_znecisteny' => 'Silno znečistený',
        'poskodeny' => 'Poškodený'
    ];
    return $stavy[$stav] ?? $stav;
}

/**
 * Preklad typu komponentu
 */
function getTypLabel($key, $value) {
    $typyKlapky = [
        'bez_servopohonu' => 'Bez servopohonu',
        'so_servopohonom' => 'So servopohonom'
    ];
    $typyFilter = [
        'bez_filtra' => 'Bez filtra',
        'kapsovy' => 'Kapsový',
        'kazetovy' => 'Kazetový',
        'firon' => 'Fíron'
    ];
    $typyRekuperator = [
        'doskovy' => 'Doskový',
        'rotacny' => 'Rotačný'
    ];
    $typyRecirkulacia = [
        's_recirkulaciou' => 'S recirkuláciou',
        'bez_recirkulacie' => 'Bez recirkulácie'
    ];
    $typyPohon = [
        'napriamo' => 'Napriamo',
        'sprevodovany' => 'Sprevodovaný'
    ];
    $typyChladic = [
        'vodny' => 'Vodný',
        'priamy' => 'Priamy'
    ];
    $typyOhrievac = [
        'vodny' => 'Vodný',
        'elektricky' => 'Elektrický',
        'plynovy' => 'Plynový'
    ];
    $typyTermostat = [
        'ma' => 'Má',
        'nema' => 'Nemá'
    ];
    $typyBypass = [
        's_bypasom' => 'S bypasom',
        'bez_bypasu' => 'Bez bypasu'
    ];
    $typyServo = [
        'so_servopohonom' => 'So servopohonom',
        'bez_servopohonu' => 'Bez servopohonu'
    ];
    
    // Match based on key
    if (strpos($key, 'klapky') !== false) return $typyKlapky[$value] ?? $value;
    if (strpos($key, 'filter') !== false) return $typyFilter[$value] ?? $value;
    if (strpos($key, 'rekuperator') !== false) return $typyRekuperator[$value] ?? $value;
    if (strpos($key, 'recirkulacia') !== false) return $typyRecirkulacia[$value] ?? $value;
    if (strpos($key, 'pohon') !== false) return $typyPohon[$value] ?? $value;
    if (strpos($key, 'chladic') !== false) return $typyChladic[$value] ?? $value;
    if (strpos($key, 'ohrievac') !== false) return $typyOhrievac[$value] ?? $value;
    if (strpos($key, 'termostat') !== false) return $typyTermostat[$value] ?? $value;
    if (strpos($key, 'bypass') !== false && strpos($key, 'servo') === false) return $typyBypass[$value] ?? $value;
    if (strpos($key, 'servo') !== false) return $typyServo[$value] ?? $value;
    
    return $value;
}

// Defensive initialization of variables that should be passed from including file
if (!isset($sekcieData)) {
    $sekcieData = [];
}
if (!isset($photosBefore)) {
    $photosBefore = [];
}
if (!isset($photosAfter)) {
    $photosAfter = [];
}
if (!isset($photosGeneral)) {
    $photosGeneral = [];
}
if (!isset($componentPhotos)) {
    $componentPhotos = [];
}

/**
 * Render a single photo as an <img> tag with inline styles for PDF layout
 * Uses data URI from getImageDataUri() and includes fallback for failed loads
 * 
 * Note: Requires getImageDataUri($path) function to be defined (see lines 10-20)
 * which converts image files to base64 data URIs for PDF embedding
 * 
 * @param string $photoPath Full path to photo file
 * @param string $fileName Optional filename for fallback display
 * @return string HTML img tag with inline styles or fallback message
 */
function renderPhotoImgTag($photoPath, $fileName = '') {
    $dataUri = getImageDataUri($photoPath);
    
    if ($dataUri) {
        // Return img tag with sensible inline styles for PDF layout
        return '<img src="' . $dataUri . '" alt="' . htmlspecialchars($fileName) . '" style="max-width: 100%; max-height: 90px; margin: 2px; border: 1px solid #e0e0e0; object-fit: contain;">';
    } else {
        // Fallback when image cannot be loaded - show filename
        $displayName = $fileName ?: basename($photoPath);
        return '<div style="background: #f0f0f0; padding: 10px; font-size: 7pt; text-align: center;">' . htmlspecialchars($displayName) . '</div>';
    }
}

/**
 * Render photos section with title and photo grid
 * 
 * @param string $title Section title (e.g., "Fotografie PRED servisom")
 * @param array $photosArray Array of photo entries (can be strings or arrays with path/filename)
 * @return string HTML for the complete photo section
 */
function renderPhotoSection($title, $photosArray) {
    if (empty($photosArray)) {
        return '';
    }
    
    $html = '<div class="photos-section">';
    $html .= '<div class="photos-row">';
    $html .= '<div class="photos-row-title">' . htmlspecialchars($title) . '</div>';
    $html .= '<div class="photos-grid">';
    
    foreach ($photosArray as $photo) {
        // Support both string paths and array entries with ['path', 'filename'] or ['file_path', 'file_name']
        if (is_string($photo)) {
            $photoPath = BASE_PATH . '/' . $photo;
            $fileName = basename($photo);
        } elseif (is_array($photo)) {
            // Support both 'path' and 'file_path' keys
            $relativePath = $photo['file_path'] ?? $photo['path'] ?? '';
            $photoPath = BASE_PATH . '/' . $relativePath;
            $fileName = $photo['file_name'] ?? $photo['filename'] ?? basename($relativePath);
        } else {
            continue; // Skip invalid entries
        }
        
        $html .= '<div class="photo-item">';
        $html .= renderPhotoImgTag($photoPath, $fileName);
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Helper function to render component photos
 * Iterates through component photos and uses renderPhotoImgTag for each
 * Supports photo entries that are either strings (path) or arrays with ['path','filename']
 * Returns empty string with fallback message when no photos exist
 */
function renderComponentPhotos($componentKey) {
    global $componentPhotos;
    
    // Return empty string if no photos for this component
    if (empty($componentPhotos[$componentKey])) {
        return '';
    }
    
    $html = '<div class="component-photos-section">';
    $html .= '<div class="component-photos-title">Fotografie komponentu</div>';
    $html .= '<div class="photos-grid">';
    
    foreach ($componentPhotos[$componentKey] as $photo) {
        // Support both string paths and array entries
        if (is_string($photo)) {
            $photoPath = BASE_PATH . '/' . $photo;
            $fileName = basename($photo);
        } elseif (is_array($photo)) {
            // Support both 'path' and 'file_path' keys for flexibility
            $relativePath = $photo['file_path'] ?? $photo['path'] ?? '';
            $photoPath = BASE_PATH . '/' . $relativePath;
            $fileName = $photo['file_name'] ?? $photo['filename'] ?? basename($relativePath);
        } else {
            continue; // Skip invalid entries
        }
        
        $html .= '<div class="photo-item">';
        $html .= renderPhotoImgTag($photoPath, $fileName);
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
            color: #333;
            padding: 15px;
            background: #fff;
        }
        
        /* Hlavička protokolu */
        .protocol-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 3px solid #2c3e50;
        }
        .protocol-header h1 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
            color: #2c3e50;
        }
        .protocol-number {
            font-size: 11pt;
            font-weight: bold;
            color: #34495e;
        }
        
        /* Moderná sekcia s oblými tvarmi */
        .info-section {
            margin-bottom: 12px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }
        .info-card {
            display: table-cell;
            background: #f8f9fa;
            padding: 10px 12px;
            vertical-align: top;
        }
        .info-card-left {
            width: 48%;
            background: #e8f4fc;
        }
        .info-card-right {
            width: 48%;
            background: #f0f7eb;
        }
        .info-card-separator {
            display: table-cell;
            width: 4%;
        }
        .info-card-full {
            width: 100%;
            background: #f8f9fa;
        }
        .info-label {
            font-weight: bold;
            color: #555;
            font-size: 7pt;
            margin-bottom: 2px;
        }
        .info-value {
            font-size: 8pt;
            color: #222;
        }
        .info-item {
            margin-bottom: 6px;
        }
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        /* Sekcia nadpis - moderný štýl */
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            background: #2c3e50;
            color: #fff;
            padding: 6px 10px;
            margin: 12px 0 8px 0;
        }
        
        /* Komponent karta */
        .component-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .component-header {
            background: #34495e;
            color: #fff;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 9pt;
        }
        .component-body {
            padding: 8px 10px;
        }
        .component-specs {
            background: #f8f9fa;
            padding: 6px 8px;
            margin-bottom: 6px;
            font-size: 8pt;
        }
        .component-specs-row {
            display: table;
            width: 100%;
        }
        .component-spec-item {
            display: table-cell;
            width: 33%;
            padding-right: 10px;
        }
        .spec-label {
            font-weight: bold;
            color: #666;
            font-size: 7pt;
        }
        .spec-value {
            font-size: 8pt;
            color: #222;
        }
        .priv-odv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        .priv-odv-table th {
            background: #ecf0f1;
            padding: 5px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
            width: 50%;
        }
        .priv-odv-table td {
            padding: 5px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .state-badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 7pt;
            font-weight: bold;
        }
        .state-cisty { background: #d4edda; color: #155724; }
        .state-mierne_znecisteny { background: #fff3cd; color: #856404; }
        .state-znecisteny { background: #ffe0b2; color: #e65100; }
        .state-silno_znecisteny { background: #ffcdd2; color: #c62828; }
        .state-poskodeny { background: #f8d7da; color: #721c24; }
        .component-note {
            font-size: 7pt;
            color: #666;
            font-style: italic;
            margin-top: 3px;
        }
        
        /* Komponenty len s jedným stavom */
        .single-component {
            background: #fff;
            border: 1px solid #e0e0e0;
            margin-bottom: 6px;
            padding: 8px 10px;
        }
        .single-component-title {
            font-weight: bold;
            font-size: 9pt;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        .single-component-content {
            display: table;
            width: 100%;
        }
        .single-component-specs {
            display: table-cell;
            width: 60%;
            font-size: 8pt;
        }
        .single-component-state {
            display: table-cell;
            width: 40%;
            text-align: right;
        }
        
        /* Component evaluation section */
        .component-evaluation {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #ddd;
        }
        .component-evaluation-row {
            display: table;
            width: 100%;
        }
        .component-eval-item {
            display: table-cell;
            width: 50%;
            padding-right: 10px;
            vertical-align: top;
        }
        .eval-label {
            font-weight: bold;
            font-size: 7pt;
            color: #555;
            margin-bottom: 2px;
        }
        .eval-value {
            font-size: 7pt;
            color: #333;
        }
        
        /* Poznámky - moderný štýl */
        .notes-section {
            margin-bottom: 12px;
        }
        .notes-box {
            background: #fafbfc;
            padding: 8px 10px;
            min-height: 30px;
            border-left: 3px solid #3498db;
            font-size: 8pt;
        }
        .notes-label {
            font-weight: bold;
            margin-bottom: 4px;
            color: #2c3e50;
            font-size: 8pt;
        }
        
        /* Fotografie - moderný štýl */
        .photos-section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .component-photos-section {
            margin-top: 10px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        .component-photos-title {
            font-weight: bold;
            font-size: 7pt;
            margin-bottom: 6px;
            color: #2c3e50;
        }
        .photos-grid {
            display: block;
        }
        .photos-row {
            margin-bottom: 8px;
        }
        .photos-row-title {
            font-weight: bold;
            font-size: 8pt;
            margin-bottom: 6px;
            padding: 4px 8px;
            background: #ecf0f1;
            color: #2c3e50;
        }
        .photo-item {
            display: inline-block;
            width: 23%;
            margin: 1%;
            vertical-align: top;
            text-align: center;
        }
        .photo-item img {
            max-width: 100%;
            max-height: 90px;
            border: 1px solid #e0e0e0;
        }
        .photo-caption {
            font-size: 6pt;
            color: #666;
            margin-top: 2px;
            word-break: break-all;
        }
        
        /* Podpisy - moderný štýl */
        .signatures-section {
            margin-top: 15px;
            page-break-inside: avoid;
        }
        .signatures-row {
            display: table;
            width: 100%;
        }
        .signature-card {
            display: table-cell;
            width: 48%;
            background: #f8f9fa;
            padding: 10px;
            vertical-align: top;
        }
        .signature-separator {
            display: table-cell;
            width: 4%;
        }
        .signature-label {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 8pt;
            color: #2c3e50;
        }
        .signature-box {
            min-height: 50px;
            border: 1px dashed #bdc3c7;
            margin: 6px 0;
            text-align: center;
            padding: 4px;
            background: #fff;
        }
        .signature-box img {
            max-width: 120px;
            max-height: 45px;
        }
        .signature-line {
            border-top: 1px solid #bdc3c7;
            margin-top: 6px;
            padding-top: 4px;
            font-size: 7pt;
            text-align: center;
            color: #7f8c8d;
        }
        
        /* Dolná sekcia - dátum a miesto */
        .footer-info {
            margin-top: 12px;
            padding: 8px 10px;
            background: #f4f6f7;
        }
        .footer-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-info-table td {
            padding: 2px 4px;
            font-size: 7pt;
        }
        .footer-info-table .label {
            font-weight: bold;
            width: 20%;
            color: #555;
        }
        
        /* Footer */
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #e0e0e0;
            font-size: 6pt;
            color: #999;
            text-align: center;
        }
        
        /* Page break */
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- Hlavička protokolu -->
    <div class="protocol-header">
        <h1>Protokol k servisnému výkonu</h1>
        <div class="protocol-number">č. <?= htmlspecialchars($report['cislo_protokolu'] ?? 'N/A') ?></div>
        <?php 
        $typServisu = $report['typ_servisu'] ?? 'pravidelny';
        $typLabel = ($typServisu === 'porucha') ? 'PORUCHA / OPRAVA' : 'PRAVIDELNÝ SERVIS';
        $typColor = ($typServisu === 'porucha') ? '#e74c3c' : '#27ae60';
        ?>
        <div class="service-type" style="margin-top: 8px; padding: 4px 15px; background: <?= $typColor ?>; color: white; display: inline-block; border-radius: 15px; font-size: 9pt; font-weight: bold;">
            <?= $typLabel ?>
        </div>
        <?php if (!empty($report['platnost_do']) && $typServisu === 'pravidelny'): ?>
        <div class="validity-info" style="margin-top: 6px; font-size: 8pt; color: #666;">
            Platnosť prehliadky do: <strong><?= htmlspecialchars($report['platnost_do']) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sekcia 1: Prevádzka a Interné označenie zariadenia -->
    <div class="info-section">
        <div class="info-row">
            <div class="info-card info-card-left">
                <div class="info-item">
                    <div class="info-label">Prevádzka:</div>
                    <div class="info-value"><?= htmlspecialchars($report['location_nazov'] ?? '-') ?><?= !empty($report['location_adresa']) ? ', ' . htmlspecialchars($report['location_adresa']) : '' ?><?= !empty($report['location_mesto']) ? ', ' . htmlspecialchars($report['location_mesto']) : '' ?></div>
                </div>
            </div>
            <div class="info-card-separator"></div>
            <div class="info-card info-card-right">
                <div class="info-item">
                    <div class="info-label">Interné označenie zariadenia:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_interne_oznacenie'] ?? $report['interne_oznacenie'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sekcia 2: Informácie o zákazníkovi (vľavo) a zariadení (vpravo) -->
    <div class="info-section">
        <div class="info-row">
            <div class="info-card info-card-left">
                <div class="info-item">
                    <div class="info-label">Prevádzkovateľ:</div>
                    <div class="info-value"><?= htmlspecialchars($report['nazov_firmy'] ?? '-') ?><?= !empty($report['sidlo']) ? ', ' . htmlspecialchars($report['sidlo']) : '' ?></div>
                </div>
                <?php if (!empty($report['objednavatel']) || !empty($report['kontakt_osoba'])): ?>
                <div class="info-item">
                    <div class="info-label">Objednávateľ:</div>
                    <div class="info-value"><?= htmlspecialchars($report['objednavatel'] ?? $report['kontakt_osoba'] ?? '-') ?></div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">Kontakt:</div>
                    <div class="info-value"><?= htmlspecialchars($report['telefon'] ?? '-') ?><?= !empty($report['email']) ? ' / ' . htmlspecialchars($report['email']) : '' ?></div>
                </div>
            </div>
            <div class="info-card-separator"></div>
            <div class="info-card info-card-right">
                <div class="info-item">
                    <div class="info-label">Výrobné číslo:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_vyrobne_cislo'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Rok výroby:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_rok_vyroby'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Typ / Model:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_typ'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Prevedenie:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_prevedenie'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Výrobca:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_vyrobca'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sekcia 3: Servisné stredisko -->
    <div class="info-section">
        <div class="info-row">
            <div class="info-card info-card-left">
                <div class="info-item">
                    <div class="info-label">Servisné stredisko:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_servisne_stredisko'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Distribúcia pre SR:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_distribucia'] ?? '-') ?></div>
                </div>
            </div>
            <div class="info-card-separator"></div>
            <div class="info-card info-card-right">
                <div class="info-item">
                    <div class="info-label">Kontakt:</div>
                    <div class="info-value"><?= htmlspecialchars($report['device_servisne_stredisko_tel'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dátum servisu:</div>
                    <div class="info-value"><?= htmlspecialchars($report['datum'] ?? date('d.m.Y')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sekcia: Zistený stav komponentov - PRÍVOD a ODVOD -->
    <div class="section-title">Zistený stav komponentov</div>

    <?php
    // KLAPKY
    $klapky = $sekcieData['klapky'] ?? [];
    if (!empty($klapky['typ']) || !empty($klapky['privod_stav']) || !empty($klapky['odvod_stav'])):
    ?>
    <div class="component-card">
        <div class="component-header">Klapky</div>
        <div class="component-body">
            <?php if (!empty($klapky['typ'])): ?>
            <div class="component-specs">
                <div class="component-specs-row">
                    <div class="component-spec-item">
                        <div class="spec-label">Typ:</div>
                        <div class="spec-value"><?= htmlspecialchars(getTypLabel('klapky', $klapky['typ'])) ?></div>
                    </div>
                    <?php if ($klapky['typ'] === 'so_servopohonom' && !empty($klapky['servo_typ'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Servopohon:</div>
                        <div class="spec-value"><?= htmlspecialchars($klapky['servo_typ']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($klapky['moment'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Moment:</div>
                        <div class="spec-value"><?= htmlspecialchars($klapky['moment']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($klapky['poznamka'])): ?>
            <div class="component-note" style="margin-bottom: 8px;">
                <strong>Poznámka:</strong> <?= htmlspecialchars($klapky['poznamka']) ?>
            </div>
            <?php endif; ?>
            <table class="priv-odv-table">
                <tr>
                    <th>PRÍVOD</th>
                    <th>ODVOD</th>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($klapky['privod_stav'])): ?>
                        <span class="state-badge state-<?= $klapky['privod_stav'] ?>"><?= htmlspecialchars(getStavLabel($klapky['privod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($klapky['privod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($klapky['privod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($klapky['privod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($klapky['privod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($klapky['privod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($klapky['privod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($klapky['privod_stav']) && empty($klapky['privod_vykonany_servis']) && empty($klapky['privod_zhodnotenie']) && empty($klapky['privod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($klapky['odvod_stav'])): ?>
                        <span class="state-badge state-<?= $klapky['odvod_stav'] ?>"><?= htmlspecialchars(getStavLabel($klapky['odvod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($klapky['odvod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($klapky['odvod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($klapky['odvod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($klapky['odvod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($klapky['odvod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($klapky['odvod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($klapky['odvod_stav']) && empty($klapky['odvod_vykonany_servis']) && empty($klapky['odvod_zhodnotenie']) && empty($klapky['odvod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php echo renderComponentPhotos('klapky'); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // FILTER
    $filter = $sekcieData['filter'] ?? [];
    if (!empty($filter['typ']) || !empty($filter['privod_stav']) || !empty($filter['odvod_stav'])):
    ?>
    <div class="component-card">
        <div class="component-header">Filter</div>
        <div class="component-body">
            <?php if (!empty($filter['typ']) || !empty($filter['rozmer'])): ?>
            <div class="component-specs">
                <div class="component-specs-row">
                    <?php if (!empty($filter['typ'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Typ:</div>
                        <div class="spec-value"><?= htmlspecialchars(getTypLabel('filter', $filter['typ'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($filter['rozmer'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Rozmer:</div>
                        <div class="spec-value"><?= htmlspecialchars($filter['rozmer']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($filter['poznamka'])): ?>
            <div class="component-note" style="margin-bottom: 8px;">
                <strong>Poznámka:</strong> <?= htmlspecialchars($filter['poznamka']) ?>
            </div>
            <?php endif; ?>
            <table class="priv-odv-table">
                <tr>
                    <th>PRÍVOD</th>
                    <th>ODVOD</th>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($filter['privod_stav'])): ?>
                        <span class="state-badge state-<?= $filter['privod_stav'] ?>"><?= htmlspecialchars(getStavLabel($filter['privod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($filter['privod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($filter['privod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($filter['privod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($filter['privod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($filter['privod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($filter['privod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($filter['privod_stav']) && empty($filter['privod_vykonany_servis']) && empty($filter['privod_zhodnotenie']) && empty($filter['privod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($filter['odvod_stav'])): ?>
                        <span class="state-badge state-<?= $filter['odvod_stav'] ?>"><?= htmlspecialchars(getStavLabel($filter['odvod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($filter['odvod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($filter['odvod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($filter['odvod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($filter['odvod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($filter['odvod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($filter['odvod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($filter['odvod_stav']) && empty($filter['odvod_vykonany_servis']) && empty($filter['odvod_zhodnotenie']) && empty($filter['odvod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php echo renderComponentPhotos('filter'); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // REKUPERÁTOR
    $rekuperator = $sekcieData['rekuperator'] ?? [];
    if (!empty($rekuperator['typ']) || !empty($rekuperator['privod_stav']) || !empty($rekuperator['odvod_stav'])):
    ?>
    <div class="component-card">
        <div class="component-header">Rekuperátor</div>
        <div class="component-body">
            <?php if (!empty($rekuperator['typ'])): ?>
            <div class="component-specs">
                <div class="component-specs-row">
                    <div class="component-spec-item">
                        <div class="spec-label">Typ:</div>
                        <div class="spec-value"><?= htmlspecialchars(getTypLabel('rekuperator', $rekuperator['typ'])) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($rekuperator['poznamka'])): ?>
            <div class="component-note" style="margin-bottom: 8px;">
                <strong>Poznámka:</strong> <?= htmlspecialchars($rekuperator['poznamka']) ?>
            </div>
            <?php endif; ?>
            <table class="priv-odv-table">
                <tr>
                    <th>PRÍVOD</th>
                    <th>ODVOD</th>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($rekuperator['privod_stav'])): ?>
                        <span class="state-badge state-<?= $rekuperator['privod_stav'] ?>"><?= htmlspecialchars(getStavLabel($rekuperator['privod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($rekuperator['privod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($rekuperator['privod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rekuperator['privod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($rekuperator['privod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rekuperator['privod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($rekuperator['privod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($rekuperator['privod_stav']) && empty($rekuperator['privod_vykonany_servis']) && empty($rekuperator['privod_zhodnotenie']) && empty($rekuperator['privod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($rekuperator['odvod_stav'])): ?>
                        <span class="state-badge state-<?= $rekuperator['odvod_stav'] ?>"><?= htmlspecialchars(getStavLabel($rekuperator['odvod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($rekuperator['odvod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($rekuperator['odvod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rekuperator['odvod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($rekuperator['odvod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($rekuperator['odvod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($rekuperator['odvod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($rekuperator['odvod_stav']) && empty($rekuperator['odvod_vykonany_servis']) && empty($rekuperator['odvod_zhodnotenie']) && empty($rekuperator['odvod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php echo renderComponentPhotos('rekuperator'); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // RECIRKULÁCIA
    $recirkulacia = $sekcieData['recirkulacia'] ?? [];
    if (!empty($recirkulacia['typ']) || !empty($recirkulacia['privod_stav']) || !empty($recirkulacia['odvod_stav'])):
    ?>
    <div class="component-card">
        <div class="component-header">Recirkulácia</div>
        <div class="component-body">
            <?php if (!empty($recirkulacia['typ'])): ?>
            <div class="component-specs">
                <div class="component-specs-row">
                    <div class="component-spec-item">
                        <div class="spec-label">Typ:</div>
                        <div class="spec-value"><?= htmlspecialchars(getTypLabel('recirkulacia', $recirkulacia['typ'])) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($recirkulacia['poznamka'])): ?>
            <div class="component-note" style="margin-bottom: 8px;">
                <strong>Poznámka:</strong> <?= htmlspecialchars($recirkulacia['poznamka']) ?>
            </div>
            <?php endif; ?>
            <table class="priv-odv-table">
                <tr>
                    <th>PRÍVOD</th>
                    <th>ODVOD</th>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($recirkulacia['privod_stav'])): ?>
                        <span class="state-badge state-<?= $recirkulacia['privod_stav'] ?>"><?= htmlspecialchars(getStavLabel($recirkulacia['privod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($recirkulacia['privod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($recirkulacia['privod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($recirkulacia['privod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($recirkulacia['privod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($recirkulacia['privod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($recirkulacia['privod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($recirkulacia['privod_stav']) && empty($recirkulacia['privod_vykonany_servis']) && empty($recirkulacia['privod_zhodnotenie']) && empty($recirkulacia['privod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($recirkulacia['odvod_stav'])): ?>
                        <span class="state-badge state-<?= $recirkulacia['odvod_stav'] ?>"><?= htmlspecialchars(getStavLabel($recirkulacia['odvod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($recirkulacia['odvod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($recirkulacia['odvod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($recirkulacia['odvod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($recirkulacia['odvod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($recirkulacia['odvod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($recirkulacia['odvod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($recirkulacia['odvod_stav']) && empty($recirkulacia['odvod_vykonany_servis']) && empty($recirkulacia['odvod_zhodnotenie']) && empty($recirkulacia['odvod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php echo renderComponentPhotos('recirkulacia'); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // VENTILÁTOR
    $ventilator = $sekcieData['ventilator'] ?? [];
    if (!empty($ventilator['typ']) || !empty($ventilator['privod_stav']) || !empty($ventilator['odvod_stav'])):
    ?>
    <div class="component-card">
        <div class="component-header">Ventilátor</div>
        <div class="component-body">
            <?php if (!empty($ventilator['typ']) || !empty($ventilator['pohon'])): ?>
            <div class="component-specs">
                <div class="component-specs-row">
                    <?php if (!empty($ventilator['typ'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Typ:</div>
                        <div class="spec-value"><?= htmlspecialchars($ventilator['typ']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ventilator['pohon'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Pohon:</div>
                        <div class="spec-value"><?= htmlspecialchars(getTypLabel('pohon', $ventilator['pohon'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ventilator['remenica_typ'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Remenica:</div>
                        <div class="spec-value"><?= htmlspecialchars($ventilator['remenica_typ']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($ventilator['poznamka'])): ?>
            <div class="component-note" style="margin-bottom: 8px;">
                <strong>Poznámka:</strong> <?= htmlspecialchars($ventilator['poznamka']) ?>
            </div>
            <?php endif; ?>
            <table class="priv-odv-table">
                <tr>
                    <th>PRÍVOD</th>
                    <th>ODVOD</th>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($ventilator['privod_stav'])): ?>
                        <span class="state-badge state-<?= $ventilator['privod_stav'] ?>"><?= htmlspecialchars(getStavLabel($ventilator['privod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($ventilator['privod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($ventilator['privod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($ventilator['privod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($ventilator['privod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($ventilator['privod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($ventilator['privod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($ventilator['privod_stav']) && empty($ventilator['privod_vykonany_servis']) && empty($ventilator['privod_zhodnotenie']) && empty($ventilator['privod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($ventilator['odvod_stav'])): ?>
                        <span class="state-badge state-<?= $ventilator['odvod_stav'] ?>"><?= htmlspecialchars(getStavLabel($ventilator['odvod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($ventilator['odvod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($ventilator['odvod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($ventilator['odvod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($ventilator['odvod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($ventilator['odvod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($ventilator['odvod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($ventilator['odvod_stav']) && empty($ventilator['odvod_vykonany_servis']) && empty($ventilator['odvod_zhodnotenie']) && empty($ventilator['odvod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php echo renderComponentPhotos('ventilator'); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ELEKTRO MOTOR
    $motor = $sekcieData['el_motor'] ?? [];
    if (!empty($motor['vykon']) || !empty($motor['privod_stav']) || !empty($motor['odvod_stav'])):
    ?>
    <div class="component-card">
        <div class="component-header">Elektro motor</div>
        <div class="component-body">
            <?php if (!empty($motor['vykon']) || !empty($motor['pohon'])): ?>
            <div class="component-specs">
                <div class="component-specs-row">
                    <?php if (!empty($motor['vykon'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Výkon/Príkon:</div>
                        <div class="spec-value"><?= htmlspecialchars($motor['vykon']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($motor['pohon'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Pohon:</div>
                        <div class="spec-value"><?= htmlspecialchars(getTypLabel('pohon', $motor['pohon'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($motor['remenica_typ'])): ?>
                    <div class="component-spec-item">
                        <div class="spec-label">Remenica:</div>
                        <div class="spec-value"><?= htmlspecialchars($motor['remenica_typ']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($motor['poznamka'])): ?>
            <div class="component-note" style="margin-bottom: 8px;">
                <strong>Poznámka:</strong> <?= htmlspecialchars($motor['poznamka']) ?>
            </div>
            <?php endif; ?>
            <table class="priv-odv-table">
                <tr>
                    <th>PRÍVOD</th>
                    <th>ODVOD</th>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($motor['privod_stav'])): ?>
                        <span class="state-badge state-<?= $motor['privod_stav'] ?>"><?= htmlspecialchars(getStavLabel($motor['privod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($motor['privod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($motor['privod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($motor['privod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($motor['privod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($motor['privod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($motor['privod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($motor['privod_stav']) && empty($motor['privod_vykonany_servis']) && empty($motor['privod_zhodnotenie']) && empty($motor['privod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($motor['odvod_stav'])): ?>
                        <span class="state-badge state-<?= $motor['odvod_stav'] ?>"><?= htmlspecialchars(getStavLabel($motor['odvod_stav'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($motor['odvod_vykonany_servis'])): ?>
                        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($motor['odvod_vykonany_servis']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($motor['odvod_zhodnotenie'])): ?>
                        <div class="component-note"><strong>Zhodnotenie:</strong> <?= htmlspecialchars($motor['odvod_zhodnotenie']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($motor['odvod_odporucanie'])): ?>
                        <div class="component-note"><strong>Odporúčanie:</strong> <?= htmlspecialchars($motor['odvod_odporucanie']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($motor['odvod_stav']) && empty($motor['odvod_vykonany_servis']) && empty($motor['odvod_zhodnotenie']) && empty($motor['odvod_odporucanie'])): ?>-<?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php echo renderComponentPhotos('el_motor'); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sekcia: Komponenty len na PRÍVODE -->
    <div class="section-title">Komponenty - len prívod</div>

    <?php
    // CHLADIČ
    $chladic = $sekcieData['chladic'] ?? [];
    if (!empty($chladic['typ']) || !empty($chladic['stav'])):
    ?>
    <div class="single-component">
        <div class="single-component-title">Chladič</div>
        <div class="single-component-content">
            <div class="single-component-specs">
                <?php if (!empty($chladic['typ'])): ?>
                <strong>Typ:</strong> <?= htmlspecialchars(getTypLabel('chladic', $chladic['typ'])) ?>
                <?php endif; ?>
                <?php if (!empty($chladic['spec'])): ?>
                | <strong>Model:</strong> <?= htmlspecialchars($chladic['spec']) ?>
                <?php endif; ?>
                <?php if (!empty($chladic['vykon'])): ?>
                | <strong>Výkon:</strong> <?= htmlspecialchars($chladic['vykon']) ?>
                <?php endif; ?>
            </div>
            <div class="single-component-state">
                <?php if (!empty($chladic['stav'])): ?>
                <span class="state-badge state-<?= $chladic['stav'] ?>"><?= htmlspecialchars(getStavLabel($chladic['stav'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($chladic['poznamka'])): ?>
        <div class="component-note"><?= htmlspecialchars($chladic['poznamka']) ?></div>
        <?php endif; ?>
        <?php if (!empty($chladic['vykonany_servis'])): ?>
        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($chladic['vykonany_servis']) ?></div>
        <?php endif; ?>
        <?php if (!empty($chladic['zhodnotenie']) || !empty($chladic['odporucanie'])): ?>
        <div class="component-evaluation">
            <div class="component-evaluation-row">
                <?php if (!empty($chladic['zhodnotenie'])): ?>
                <div class="component-eval-item">
                    <div class="eval-label">Zhodnotenie:</div>
                    <div class="eval-value"><?= htmlspecialchars($chladic['zhodnotenie']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($chladic['odporucanie'])): ?>
                <div class="component-eval-item">
                    <div class="eval-label">Odporúčanie:</div>
                    <div class="eval-value"><?= htmlspecialchars($chladic['odporucanie']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php echo renderComponentPhotos('chladic'); ?>
    </div>
    <?php endif; ?>

    <?php
    // OHRIEVAČ
    $ohrievac = $sekcieData['ohrievac'] ?? [];
    if (!empty($ohrievac['typ']) || !empty($ohrievac['stav'])):
    ?>
    <div class="single-component">
        <div class="single-component-title">Ohrievač</div>
        <div class="single-component-content">
            <div class="single-component-specs">
                <?php if (!empty($ohrievac['typ'])): ?>
                <strong>Typ:</strong> <?= htmlspecialchars(getTypLabel('ohrievac', $ohrievac['typ'])) ?>
                <?php endif; ?>
                <?php if (!empty($ohrievac['vykon'])): ?>
                | <strong>Výkon:</strong> <?= htmlspecialchars($ohrievac['vykon']) ?>
                <?php endif; ?>
                <?php if ($ohrievac['typ'] === 'plynovy'): ?>
                    <?php if (!empty($ohrievac['plyn_typ'])): ?>
                    | <strong>Typ horáka:</strong> <?= htmlspecialchars($ohrievac['plyn_typ']) ?>
                    <?php endif; ?>
                    <?php if (!empty($ohrievac['bypass'])): ?>
                    | <strong>Bypass:</strong> <?= htmlspecialchars(getTypLabel('bypass', $ohrievac['bypass'])) ?>
                    <?php endif; ?>
                    <?php if ($ohrievac['bypass'] === 's_bypasom' && !empty($ohrievac['bypass_servo'])): ?>
                    | <strong>Servopohon:</strong> <?= htmlspecialchars(getTypLabel('servo', $ohrievac['bypass_servo'])) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="single-component-state">
                <?php if (!empty($ohrievac['stav'])): ?>
                <span class="state-badge state-<?= $ohrievac['stav'] ?>"><?= htmlspecialchars(getStavLabel($ohrievac['stav'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($ohrievac['poznamka'])): ?>
        <div class="component-note"><?= htmlspecialchars($ohrievac['poznamka']) ?></div>
        <?php endif; ?>
        <?php if (!empty($ohrievac['vykonany_servis'])): ?>
        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($ohrievac['vykonany_servis']) ?></div>
        <?php endif; ?>
        <?php if (!empty($ohrievac['zhodnotenie']) || !empty($ohrievac['odporucanie'])): ?>
        <div class="component-evaluation">
            <div class="component-evaluation-row">
                <?php if (!empty($ohrievac['zhodnotenie'])): ?>
                <div class="component-eval-item">
                    <div class="eval-label">Zhodnotenie:</div>
                    <div class="eval-value"><?= htmlspecialchars($ohrievac['zhodnotenie']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($ohrievac['odporucanie'])): ?>
                <div class="component-eval-item">
                    <div class="eval-label">Odporúčanie:</div>
                    <div class="eval-value"><?= htmlspecialchars($ohrievac['odporucanie']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php echo renderComponentPhotos('ohrievac'); ?>
    </div>
    <?php endif; ?>

    <?php
    // KOMÍNOVÝ TERMOSTAT
    $termostat = $sekcieData['kominovy_termostat'] ?? [];
    if (!empty($termostat['typ']) || !empty($termostat['stav'])):
    ?>
    <div class="single-component">
        <div class="single-component-title">Komínový termostat</div>
        <div class="single-component-content">
            <div class="single-component-specs">
                <?php if (!empty($termostat['typ'])): ?>
                <strong>Stav:</strong> <?= htmlspecialchars(getTypLabel('termostat', $termostat['typ'])) ?>
                <?php endif; ?>
            </div>
            <div class="single-component-state">
                <?php if (!empty($termostat['stav'])): ?>
                <span class="state-badge state-<?= $termostat['stav'] ?>"><?= htmlspecialchars(getStavLabel($termostat['stav'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($termostat['poznamka'])): ?>
        <div class="component-note"><?= htmlspecialchars($termostat['poznamka']) ?></div>
        <?php endif; ?>
        <?php if (!empty($termostat['vykonany_servis'])): ?>
        <div class="component-note"><strong>Vykonaný servis:</strong> <?= htmlspecialchars($termostat['vykonany_servis']) ?></div>
        <?php endif; ?>
        <?php if (!empty($termostat['zhodnotenie']) || !empty($termostat['odporucanie'])): ?>
        <div class="component-evaluation">
            <div class="component-evaluation-row">
                <?php if (!empty($termostat['zhodnotenie'])): ?>
                <div class="component-eval-item">
                    <div class="eval-label">Zhodnotenie:</div>
                    <div class="eval-value"><?= htmlspecialchars($termostat['zhodnotenie']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($termostat['odporucanie'])): ?>
                <div class="component-eval-item">
                    <div class="eval-label">Odporúčanie:</div>
                    <div class="eval-value"><?= htmlspecialchars($termostat['odporucanie']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php echo renderComponentPhotos('kominovy_termostat'); ?>
    </div>
    <?php endif; ?>

    <!-- Všeobecná poznámka -->
    <div class="notes-section">
        <?php if (!empty($report['poznamka'])): ?>
        <div class="notes-label">Všeobecná poznámka:</div>
        <div class="notes-box"><?= nl2br(htmlspecialchars($report['poznamka'])) ?></div>
        <?php endif; ?>
    </div>

    <!-- Fotografie - PRED servisom -->
    <?php echo renderPhotoSection('Fotografie PRED servisom', $photosBefore); ?>

    <!-- Fotografie - PO servise -->
    <?php echo renderPhotoSection('Fotografie PO servise', $photosAfter); ?>

    <!-- Fotografie - všeobecné (legacy) -->
    <?php echo renderPhotoSection('Fotografie zariadenia', $photosGeneral); ?>

    <!-- Podpisy -->
    <div class="signatures-section">
        <div class="section-title">Podpisy</div>
        <div class="signatures-row">
            <div class="signature-card">
                <div class="signature-label">Servis vykonal:</div>
                <div style="font-size: 9pt; margin-bottom: 5px; color: #333;"><?= htmlspecialchars($report['servis_vykonal'] ?? '-') ?></div>
                <div class="signature-box">
                    <?php 
                    $technikPath = SIGNATURES_PATH . '/' . ($report['podpis_technik'] ?? '');
                    $technikDataUri = !empty($report['podpis_technik']) ? getImageDataUri($technikPath) : null;
                    if ($technikDataUri): ?>
                    <img src="<?= $technikDataUri ?>" alt="Podpis technika">
                    <?php else: ?>
                    <span style="color: #bdc3c7; font-size: 8pt;">Bez podpisu</span>
                    <?php endif; ?>
                </div>
                <div class="signature-line">podpis technika</div>
            </div>
            <div class="signature-separator"></div>
            <div class="signature-card">
                <div class="signature-label">Skontroloval a prevzal:</div>
                <div style="font-size: 9pt; margin-bottom: 5px; color: #333;"><?= htmlspecialchars($report['skontroloval_prevzal'] ?? $report['kontakt_osoba'] ?? '-') ?></div>
                <div class="signature-box">
                    <?php 
                    $zakaznikPath = SIGNATURES_PATH . '/' . ($report['podpis_zakaznik'] ?? '');
                    $zakaznikDataUri = !empty($report['podpis_zakaznik']) ? getImageDataUri($zakaznikPath) : null;
                    if ($zakaznikDataUri): ?>
                    <img src="<?= $zakaznikDataUri ?>" alt="Podpis zákazníka">
                    <?php else: ?>
                    <span style="color: #bdc3c7; font-size: 8pt;">Bez podpisu</span>
                    <?php endif; ?>
                </div>
                <div class="signature-line">podpis zákazníka</div>
            </div>
        </div>
    </div>

    <!-- Miesto a dátum -->
    <div class="footer-info">
        <table class="footer-info-table">
            <tr>
                <td class="label">Miesto a dátum:</td>
                <td><?= htmlspecialchars($report['miesto_datum'] ?? ($report['location_mesto'] ?? '') . ', ' . date('d.m.Y', strtotime($report['datum'] ?? 'now'))) ?></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Vygenerované: <?= date('d.m.Y H:i:s') ?> | Servisný Protokol
    </div>
</body>
</html>
