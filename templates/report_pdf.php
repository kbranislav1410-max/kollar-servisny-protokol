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
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2c3e50;
        }
        .header h1 {
            font-size: 18pt;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .header .protocol-number {
            font-size: 14pt;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #2c3e50;
            background: #ecf0f1;
            padding: 8px 10px;
            margin-bottom: 10px;
            border-left: 4px solid #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #bdc3c7;
            padding: 8px 10px;
            text-align: left;
        }
        table th {
            background: #f8f9fa;
            font-weight: bold;
            width: 35%;
        }
        .info-table td {
            vertical-align: top;
        }
        .components-table th {
            width: 50%;
        }
        .components-table td:first-child {
            font-weight: bold;
        }
        .note-box {
            background: #f8f9fa;
            border: 1px solid #bdc3c7;
            padding: 12px;
            min-height: 60px;
        }
        .signatures {
            display: table;
            width: 100%;
            margin-top: 30px;
        }
        .signature-box {
            display: table-cell;
            width: 48%;
            text-align: center;
            padding: 10px;
        }
        .signature-box:first-child {
            border-right: 1px solid #fff;
        }
        .signature-label {
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .signature-image {
            border: 1px solid #bdc3c7;
            background: #fff;
            min-height: 100px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .signature-image img {
            max-width: 180px;
            max-height: 90px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 10px;
            padding-top: 5px;
            font-size: 9pt;
            color: #666;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #bdc3c7;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
        .date-info {
            text-align: right;
            margin-bottom: 15px;
            font-size: 10pt;
        }
        .attachments {
            margin-top: 20px;
        }
        .attachments ul {
            list-style: none;
            padding-left: 15px;
        }
        .attachments li {
            padding: 3px 0;
        }
        .attachments li:before {
            content: "• ";
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SERVISNÝ PROTOKOL</h1>
        <div class="protocol-number">č. <?= htmlspecialchars($report['cislo_protokolu'] ?? 'N/A') ?></div>
    </div>

    <div class="date-info">
        <strong>Dátum servisu:</strong> <?= htmlspecialchars($report['datum'] ?? date('d.m.Y')) ?>
    </div>

    <div class="section">
        <div class="section-title">Údaje o zákazníkovi</div>
        <table class="info-table">
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

    <?php if (!empty($report['location_nazov'])): ?>
    <div class="section">
        <div class="section-title">Miesto prevádzky</div>
        <table class="info-table">
            <tr>
                <th>Názov prevádzky</th>
                <td><?= htmlspecialchars($report['location_nazov'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Adresa</th>
                <td><?= htmlspecialchars($report['location_adresa'] ?? '-') ?>, <?= htmlspecialchars($report['location_mesto'] ?? '') ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($report['device_nazov'])): ?>
    <div class="section">
        <div class="section-title">Servisované zariadenie</div>
        <table class="info-table">
            <tr>
                <th>Názov zariadenia</th>
                <td><?= htmlspecialchars($report['device_nazov'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Typ</th>
                <td><?= htmlspecialchars($report['device_typ'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>Výrobné číslo</th>
                <td><?= htmlspecialchars($report['device_vyrobne_cislo'] ?? '-') ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Stav komponentov</div>
        <table class="components-table">
            <tr>
                <th>Komponent</th>
                <th>Stav</th>
            </tr>
            <tr>
                <td>Klapky - prívod</td>
                <td><?= htmlspecialchars($report['klapky_pr'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Klapky - odvod</td>
                <td><?= htmlspecialchars($report['klapky_od'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Filtrácia - prívod</td>
                <td><?= htmlspecialchars($report['filtracia_pr'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Filtrácia - odvod</td>
                <td><?= htmlspecialchars($report['filtracia_od'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Rekuperácia</td>
                <td><?= htmlspecialchars($report['rekuperacia'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Ventilátor</td>
                <td><?= htmlspecialchars($report['ventilator'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Ohrievač</td>
                <td><?= htmlspecialchars($report['ohrievac'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Plynový horák</td>
                <td><?= htmlspecialchars($report['plynovy_horak'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Chladič</td>
                <td><?= htmlspecialchars($report['chladic'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Zvukový tlmič</td>
                <td><?= htmlspecialchars($report['zvukovy_tlmic'] ?? '-') ?></td>
            </tr>
        </table>
    </div>

    <?php if (!empty($report['poznamka'])): ?>
    <div class="section">
        <div class="section-title">Poznámka</div>
        <div class="note-box">
            <?= nl2br(htmlspecialchars($report['poznamka'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($attachments)): ?>
    <div class="section attachments">
        <div class="section-title">Prílohy (fotografie)</div>
        <ul>
            <?php foreach ($attachments as $att): ?>
            <li><?= htmlspecialchars($att['file_name']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-label">Podpis technika</div>
            <div class="signature-image">
                <?php if (!empty($report['podpis_technik']) && file_exists(SIGNATURES_PATH . '/' . $report['podpis_technik'])): ?>
                <img src="<?= SIGNATURES_PATH . '/' . $report['podpis_technik'] ?>" alt="Podpis technika">
                <?php else: ?>
                <span style="color: #999;">Bez podpisu</span>
                <?php endif; ?>
            </div>
            <div class="signature-line">Servisný technik</div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Podpis zákazníka</div>
            <div class="signature-image">
                <?php if (!empty($report['podpis_zakaznik']) && file_exists(SIGNATURES_PATH . '/' . $report['podpis_zakaznik'])): ?>
                <img src="<?= SIGNATURES_PATH . '/' . $report['podpis_zakaznik'] ?>" alt="Podpis zákazníka">
                <?php else: ?>
                <span style="color: #999;">Bez podpisu</span>
                <?php endif; ?>
            </div>
            <div class="signature-line">Zákazník</div>
        </div>
    </div>

    <div class="footer">
        Vygenerované: <?= date('d.m.Y H:i:s') ?> | Servisný Protokol MVP
    </div>
</body>
</html>
