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
            padding: 20px;
            background: #fff;
        }
        
        /* Hlavička protokolu */
        .protocol-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #3498db;
        }
        .protocol-header h1 {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        .protocol-number {
            font-size: 12pt;
            font-weight: bold;
            color: #3498db;
        }
        
        /* Moderná sekcia s oblými tvarmi */
        .info-section {
            margin-bottom: 15px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .info-card {
            display: table-cell;
            background: #f8f9fa;
            padding: 12px 15px;
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
            font-size: 8pt;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 9pt;
            color: #222;
        }
        .info-item {
            margin-bottom: 8px;
        }
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        /* Sekcia nadpis - moderný štýl */
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
            padding: 8px 12px;
            margin: 15px 0 10px 0;
        }
        
        /* Hlavná tabuľka komponentov - moderný štýl */
        .components-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 15px;
            font-size: 8pt;
        }
        .components-table th {
            background: #34495e;
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 8px 6px;
        }
        .components-table th:first-child {
        }
        .components-table th:last-child {
        }
        .components-table td {
            padding: 6px 8px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e0e0e0;
        }
        .components-table .col-component {
            width: 18%;
            font-weight: bold;
            color: #2c3e50;
            background: #f4f6f7;
        }
        .components-table .col-type {
            width: 22%;
            background: #fafbfc;
        }
        .components-table .col-state {
            width: 30%;
        }
        .components-table tr:nth-child(even) td {
            background-color: #f9fafb;
        }
        .components-table tr:nth-child(even) td.col-component {
            background: #ecf0f1;
        }
        .components-table tr:hover td {
            background-color: #eef5fb;
        }
        
        /* Poznámky - moderný štýl */
        .notes-section {
            margin-bottom: 15px;
        }
        .notes-box {
            background: #fafbfc;
            padding: 10px 12px;
            min-height: 40px;
            border-left: 3px solid #3498db;
        }
        .notes-label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        /* Fotografie - moderný štýl */
        .photos-section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .photos-grid {
            display: block;
        }
        .photos-row {
            margin-bottom: 10px;
        }
        .photos-row-title {
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 8px;
            padding: 6px 10px;
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
            max-height: 100px;
            border: 2px solid #e0e0e0;
        }
        .photo-caption {
            font-size: 7pt;
            color: #666;
            margin-top: 3px;
            word-break: break-all;
        }
        
        /* Podpisy - moderný štýl */
        .signatures-section {
            margin-top: 20px;
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
            padding: 12px;
            vertical-align: top;
        }
        .signature-separator {
            display: table-cell;
            width: 4%;
        }
        .signature-label {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 9pt;
            color: #2c3e50;
        }
        .signature-box {
            min-height: 60px;
            border: 2px dashed #bdc3c7;
            margin: 8px 0;
            text-align: center;
            padding: 5px;
            background: #fff;
        }
        .signature-box img {
            max-width: 150px;
            max-height: 55px;
        }
        .signature-line {
            border-top: 1px solid #bdc3c7;
            margin-top: 8px;
            padding-top: 5px;
            font-size: 8pt;
            text-align: center;
            color: #7f8c8d;
        }
        
        /* Dolná sekcia - dátum a miesto */
        .footer-info {
            margin-top: 15px;
            padding: 10px 12px;
            background: #f4f6f7;
        }
        .footer-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-info-table td {
            padding: 3px 5px;
            font-size: 8pt;
        }
        .footer-info-table .label {
            font-weight: bold;
            width: 20%;
            color: #555;
        }
        
        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            font-size: 7pt;
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

    <!-- Tabuľka stavu komponentov -->
    <div class="section-title">Zistený stav komponentov</div>
    <table class="components-table">
        <thead>
            <tr>
                <th class="col-component">Komponent</th>
                <th class="col-type">Popis / Typ</th>
                <th class="col-state">Stav prívod - zistený stav</th>
                <th class="col-state">Stav odvod - zistený stav</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Definícia komponentov
            $komponenty = [
                ['key' => 'klapky', 'name' => 'Klapky', 'dual' => true],
                ['key' => 'filtracia', 'name' => 'Filtrácia', 'dual' => true],
                ['key' => 'recirkulacia', 'name' => 'Recirkulácia', 'dual' => true],
                ['key' => 'rekuperacia', 'name' => 'Rekuperácia', 'dual' => true],
                ['key' => 'ventilator', 'name' => 'Ventilátor', 'dual' => true],
                ['key' => 'el_motor', 'name' => 'El. motor', 'dual' => true],
                ['key' => 'remene', 'name' => 'Klinové remeňe', 'dual' => true],
                ['key' => 'chladic', 'name' => 'Chladič', 'dual' => true],
                ['key' => 'ohrievac', 'name' => 'Ohrievač', 'dual' => true],
                ['key' => 'bypass_klapka', 'name' => 'Klapka bypassu', 'dual' => false],
                ['key' => 'bypass_servo', 'name' => 'Servopohon bypassu', 'dual' => false],
                ['key' => 'termostat', 'name' => 'Termostat', 'dual' => false],
                ['key' => 'plynovy_horak', 'name' => 'Plynový horák', 'dual' => false],
                ['key' => 'mar', 'name' => 'MaR systém', 'dual' => false],
            ];
            
            foreach ($komponenty as $komp):
                $data = $sekcieData[$komp['key']] ?? [];
            ?>
            <tr>
                <td class="col-component"><?= htmlspecialchars($komp['name']) ?></td>
                <td><?= htmlspecialchars($data['typ'] ?? '-') ?></td>
                <?php if ($komp['dual']): ?>
                <td><?= htmlspecialchars($data['privod'] ?? '-') ?></td>
                <td><?= htmlspecialchars($data['odvod'] ?? '-') ?></td>
                <?php else: ?>
                <td colspan="2"><?= htmlspecialchars($data['stav'] ?? '-') ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Poznámky, zhodnotenie, odporúčania -->
    <div class="notes-section">
        <?php if (!empty($report['zhodnotenie'])): ?>
        <div class="notes-label">Zhodnotenie stavu:</div>
        <div class="notes-box"><?= nl2br(htmlspecialchars($report['zhodnotenie'])) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($report['odporucania'])): ?>
        <div class="notes-label" style="margin-top: 8px;">Odporúčania:</div>
        <div class="notes-box"><?= nl2br(htmlspecialchars($report['odporucania'])) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($report['poznamka'])): ?>
        <div class="notes-label" style="margin-top: 8px;">Poznámka:</div>
        <div class="notes-box"><?= nl2br(htmlspecialchars($report['poznamka'])) ?></div>
        <?php endif; ?>
    </div>

    <!-- Fotografie - PRED servisom -->
    <?php if (!empty($photosBefore)): ?>
    <div class="photos-section">
        <div class="photos-row">
            <div class="photos-row-title">Fotografie PRED servisom</div>
            <div class="photos-grid">
                <?php foreach ($photosBefore as $att): 
                    $photoPath = BASE_PATH . '/' . $att['file_path'];
                    $photoDataUri = getImageDataUri($photoPath);
                ?>
                <div class="photo-item">
                    <?php if ($photoDataUri): ?>
                    <img src="<?= $photoDataUri ?>" alt="<?= htmlspecialchars($att['file_name']) ?>">
                    <?php else: ?>
                    <div style="background: #f0f0f0; padding: 10px; font-size: 7pt;"><?= htmlspecialchars($att['file_name']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fotografie - PO servise -->
    <?php if (!empty($photosAfter)): ?>
    <div class="photos-section">
        <div class="photos-row">
            <div class="photos-row-title">Fotografie PO servise</div>
            <div class="photos-grid">
                <?php foreach ($photosAfter as $att): 
                    $photoPath = BASE_PATH . '/' . $att['file_path'];
                    $photoDataUri = getImageDataUri($photoPath);
                ?>
                <div class="photo-item">
                    <?php if ($photoDataUri): ?>
                    <img src="<?= $photoDataUri ?>" alt="<?= htmlspecialchars($att['file_name']) ?>">
                    <?php else: ?>
                    <div style="background: #f0f0f0; padding: 10px; font-size: 7pt;"><?= htmlspecialchars($att['file_name']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fotografie - všeobecné (legacy) -->
    <?php if (!empty($photosGeneral)): ?>
    <div class="photos-section">
        <div class="photos-row">
            <div class="photos-row-title">Fotografie zariadenia</div>
            <div class="photos-grid">
                <?php foreach ($photosGeneral as $att): 
                    $photoPath = BASE_PATH . '/' . $att['file_path'];
                    $photoDataUri = getImageDataUri($photoPath);
                ?>
                <div class="photo-item">
                    <?php if ($photoDataUri): ?>
                    <img src="<?= $photoDataUri ?>" alt="<?= htmlspecialchars($att['file_name']) ?>">
                    <?php else: ?>
                    <div style="background: #f0f0f0; padding: 10px; font-size: 7pt;"><?= htmlspecialchars($att['file_name']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
