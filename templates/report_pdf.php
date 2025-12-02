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
            line-height: 1.3;
            color: #000;
            padding: 15px;
        }
        
        /* Hlavička protokolu */
        .protocol-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .protocol-header h1 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .protocol-number {
            font-size: 11pt;
            font-weight: bold;
        }
        
        /* Hlavná informačná tabuľka */
        .info-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-header-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
            font-size: 8pt;
        }
        .info-header-table .label {
            font-weight: bold;
            width: 25%;
            background-color: #f0f0f0;
        }
        .info-header-table .value {
            width: 25%;
        }
        
        /* Sekcia nadpis */
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            background: #e0e0e0;
            padding: 5px 8px;
            margin: 10px 0 5px 0;
            border: 1px solid #000;
        }
        
        /* Hlavná tabuľka komponentov */
        .components-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 8pt;
        }
        .components-table th,
        .components-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        .components-table th {
            background: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }
        .components-table .col-component {
            width: 18%;
            font-weight: bold;
        }
        .components-table .col-type {
            width: 22%;
        }
        .components-table .col-state {
            width: 30%;
        }
        .components-table tr:nth-child(even) {
            background-color: #fafafa;
        }
        
        /* Poznámky */
        .notes-section {
            margin-bottom: 15px;
        }
        .notes-box {
            border: 1px solid #000;
            padding: 8px;
            min-height: 40px;
            background: #fff;
        }
        .notes-label {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        /* Fotografie */
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
            margin-bottom: 5px;
            padding: 3px 5px;
            background: #f0f0f0;
            border: 1px solid #000;
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
            border: 1px solid #000;
        }
        .photo-caption {
            font-size: 7pt;
            color: #333;
            margin-top: 2px;
            word-break: break-all;
        }
        
        /* Podpisy */
        .signatures-section {
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signatures-table td {
            border: 1px solid #000;
            padding: 8px;
            width: 50%;
            vertical-align: top;
        }
        .signature-label {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 9pt;
        }
        .signature-box {
            min-height: 60px;
            border: 1px dashed #999;
            margin: 5px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }
        .signature-box img {
            max-width: 150px;
            max-height: 55px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 5px;
            padding-top: 3px;
            font-size: 8pt;
            text-align: center;
        }
        
        /* Dolná sekcia - dátum a miesto */
        .footer-info {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #000;
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
        }
        
        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            font-size: 7pt;
            color: #666;
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

    <!-- Základné informácie - horná tabuľka -->
    <table class="info-header-table">
        <tr>
            <td class="label">Prevádzka:</td>
            <td class="value"><?= htmlspecialchars($report['location_nazov'] ?? '-') ?></td>
            <td class="label">Výrobné číslo:</td>
            <td class="value"><?= htmlspecialchars($report['device_vyrobne_cislo'] ?? '-') ?></td>
        </tr>
        <tr>
            <td class="label">Interné označenie:</td>
            <td class="value"><?= htmlspecialchars($report['interne_oznacenie'] ?? '-') ?></td>
            <td class="label">Rok výroby:</td>
            <td class="value"><?= htmlspecialchars($report['device_rok_vyroby'] ?? '-') ?></td>
        </tr>
        <tr>
            <td class="label">Výrobca:</td>
            <td class="value"><?= htmlspecialchars($report['device_vyrobca'] ?? '-') ?></td>
            <td class="label">Typ / Model:</td>
            <td class="value"><?= htmlspecialchars($report['device_typ'] ?? '-') ?></td>
        </tr>
        <tr>
            <td class="label">Distribúcia pre SR:</td>
            <td class="value"><?= htmlspecialchars($report['device_distribucia'] ?? '-') ?></td>
            <td class="label">Prevedenie:</td>
            <td class="value"><?= htmlspecialchars($report['device_prevedenie'] ?? '-') ?></td>
        </tr>
        <tr>
            <td class="label">Servisné stredisko:</td>
            <td class="value"><?= htmlspecialchars($report['device_servisne_stredisko'] ?? '-') ?> <?= !empty($report['device_servisne_stredisko_tel']) ? '(tel: ' . htmlspecialchars($report['device_servisne_stredisko_tel']) . ')' : '' ?></td>
            <td class="label">Dátum servisu:</td>
            <td class="value"><?= htmlspecialchars($report['datum'] ?? date('d.m.Y')) ?></td>
        </tr>
    </table>

    <!-- Informácie o zákazníkovi a prevádzke -->
    <table class="info-header-table">
        <tr>
            <td class="label">Prevádzkovateľ:</td>
            <td class="value" colspan="3"><?= htmlspecialchars($report['nazov_firmy'] ?? '-') ?><?= !empty($report['sidlo']) ? ', ' . htmlspecialchars($report['sidlo']) : '' ?></td>
        </tr>
        <tr>
            <td class="label">Zariadenie (adresa):</td>
            <td class="value" colspan="3"><?= htmlspecialchars($report['location_adresa'] ?? '-') ?><?= !empty($report['location_mesto']) ? ', ' . htmlspecialchars($report['location_mesto']) : '' ?></td>
        </tr>
        <tr>
            <td class="label">Objednávateľ:</td>
            <td class="value"><?= htmlspecialchars($report['objednavatel'] ?? $report['kontakt_osoba'] ?? '-') ?></td>
            <td class="label">Kontakt:</td>
            <td class="value"><?= htmlspecialchars($report['telefon'] ?? '-') ?><?= !empty($report['email']) ? ' / ' . htmlspecialchars($report['email']) : '' ?></td>
        </tr>
    </table>

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
            <div class="photos-row-title">📷 Fotografie PRED servisom</div>
            <div class="photos-grid">
                <?php foreach ($photosBefore as $att): 
                    $photoPath = BASE_PATH . '/' . $att['file_path'];
                    $photoDataUri = getImageDataUri($photoPath);
                ?>
                <div class="photo-item">
                    <?php if ($photoDataUri): ?>
                    <img src="<?= $photoDataUri ?>" alt="<?= htmlspecialchars($att['file_name']) ?>">
                    <?php else: ?>
                    <div style="background: #f0f0f0; padding: 10px; font-size: 7pt;">📷 <?= htmlspecialchars($att['file_name']) ?></div>
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
            <div class="photos-row-title">📷 Fotografie PO servise</div>
            <div class="photos-grid">
                <?php foreach ($photosAfter as $att): 
                    $photoPath = BASE_PATH . '/' . $att['file_path'];
                    $photoDataUri = getImageDataUri($photoPath);
                ?>
                <div class="photo-item">
                    <?php if ($photoDataUri): ?>
                    <img src="<?= $photoDataUri ?>" alt="<?= htmlspecialchars($att['file_name']) ?>">
                    <?php else: ?>
                    <div style="background: #f0f0f0; padding: 10px; font-size: 7pt;">📷 <?= htmlspecialchars($att['file_name']) ?></div>
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
            <div class="photos-row-title">📷 Fotografie zariadenia</div>
            <div class="photos-grid">
                <?php foreach ($photosGeneral as $att): 
                    $photoPath = BASE_PATH . '/' . $att['file_path'];
                    $photoDataUri = getImageDataUri($photoPath);
                ?>
                <div class="photo-item">
                    <?php if ($photoDataUri): ?>
                    <img src="<?= $photoDataUri ?>" alt="<?= htmlspecialchars($att['file_name']) ?>">
                    <?php else: ?>
                    <div style="background: #f0f0f0; padding: 10px; font-size: 7pt;">📷 <?= htmlspecialchars($att['file_name']) ?></div>
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
        <table class="signatures-table">
            <tr>
                <td>
                    <div class="signature-label">Servis vykonal:</div>
                    <div style="font-size: 9pt; margin-bottom: 5px;"><?= htmlspecialchars($report['servis_vykonal'] ?? '-') ?></div>
                    <div class="signature-box">
                        <?php 
                        $technikPath = SIGNATURES_PATH . '/' . ($report['podpis_technik'] ?? '');
                        $technikDataUri = !empty($report['podpis_technik']) ? getImageDataUri($technikPath) : null;
                        if ($technikDataUri): ?>
                        <img src="<?= $technikDataUri ?>" alt="Podpis technika">
                        <?php else: ?>
                        <span style="color: #999; font-size: 8pt;">Bez podpisu</span>
                        <?php endif; ?>
                    </div>
                    <div class="signature-line">podpis technika</div>
                </td>
                <td>
                    <div class="signature-label">Skontroloval a prevzal:</div>
                    <div style="font-size: 9pt; margin-bottom: 5px;"><?= htmlspecialchars($report['skontroloval_prevzal'] ?? $report['kontakt_osoba'] ?? '-') ?></div>
                    <div class="signature-box">
                        <?php 
                        $zakaznikPath = SIGNATURES_PATH . '/' . ($report['podpis_zakaznik'] ?? '');
                        $zakaznikDataUri = !empty($report['podpis_zakaznik']) ? getImageDataUri($zakaznikPath) : null;
                        if ($zakaznikDataUri): ?>
                        <img src="<?= $zakaznikDataUri ?>" alt="Podpis zákazníka">
                        <?php else: ?>
                        <span style="color: #999; font-size: 8pt;">Bez podpisu</span>
                        <?php endif; ?>
                    </div>
                    <div class="signature-line">podpis zákazníka</div>
                </td>
            </tr>
        </table>
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
