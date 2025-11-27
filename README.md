# Servisný Protokol MVP

Kompletné MVP pre servisného technika — krokový webový formulár, ukladanie do SQLite, zachytávanie podpisov (canvas), upload fotiek, generovanie PDF (Dompdf) a odosielanie e-mailov (PHPMailer).

## 🚀 Funkcie

- **Krokový formulár** - 6 krokov pre kompletný servisný protokol
- **Správa zákazníkov a prevádzok** - CRUD operácie s SQLite databázou
- **Správa zariadení** - Voliteľné pridanie zariadenia k protokolu
- **Stav komponentov** - Záznam stavu všetkých servisovaných komponentov
- **Podpisy** - Digitálne podpisy technika a zákazníka (canvas)
- **Upload fotiek** - Podpora pre viacero fotiek s validáciou (max 5MB)
- **Generovanie PDF** - Automatické generovanie PDF protokolu (Dompdf)
- **Email** - Odoslanie protokolu emailom (PHPMailer)

## 📋 Požiadavky

- PHP 7.4 alebo vyššia verzia
- Composer
- SQLite3 rozšírenie pre PHP
- GD rozšírenie pre PHP (pre prácu s obrázkami)

## 🛠️ Inštalácia

### 1. Klonovanie repozitára

```bash
git clone https://github.com/kbranislav1410-max/kollar-servisny-protokol.git
cd kollar-servisny-protokol
```

### 2. Inštalácia závislostí cez Composer

```bash
composer install
```

### 3. Inicializácia databázy a adresárov

```bash
php init_db.php
```

Tento príkaz vytvorí:
- `data/` - adresár pre SQLite databázu
- `uploads/` - adresár pre nahrané súbory
  - `uploads/signatures/` - podpisy
  - `uploads/photos/` - fotografie
  - `uploads/pdfs/` - vygenerované PDF súbory
- SQLite databázu s tabuľkami: `customers`, `locations`, `devices`, `reports`, `attachments`

### 4. Konfigurácia emailu (voliteľné)

Upravte súbor `config.php` a nastavte SMTP parametre pre odosielanie emailov:

```php
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@example.com');
define('MAIL_PASSWORD', 'your-password');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'noreply@example.com');
define('MAIL_FROM_NAME', 'Servisný Protokol');
```

### 5. Spustenie vývojového servera

```bash
php -S localhost:8000
```

### 6. Otvorenie v prehliadači

Navigujte na:
- **MVP Protokol**: http://localhost:8000/index.php
- **Pôvodný formulár**: http://localhost:8000/index.html

## 📁 Štruktúra projektu

```
kollar-servisny-protokol/
├── assets/
│   ├── css/
│   │   └── style.css          # Hlavné štýly
│   └── js/
│       ├── app.js             # Hlavná JavaScript logika
│       └── signature_pad.js   # Implementácia signature pad
├── data/                      # SQLite databáza (vytvorená init_db.php)
├── templates/
│   └── report_pdf.php         # HTML šablóna pre PDF
├── uploads/                   # Nahrané súbory (vytvorené init_db.php)
│   ├── pdfs/
│   ├── photos/
│   └── signatures/
├── vendor/                    # Composer závislosti
├── composer.json              # Composer konfigurácia
├── config.php                 # Konfigurácia aplikácie
├── index.html                 # Pôvodný formulár
├── index.php                  # MVP router a formulár
├── init_db.php                # Inicializácia databázy
└── README.md                  # Tento súbor
```

## 🔧 Použitie

### Vytvorenie nového protokolu

1. Otvorte http://localhost:8000/index.php
2. Kliknite na "Nový report" v menu
3. Prejdite 6 krokovým formulárom:
   - **Krok 1**: Výber/pridanie zákazníka
   - **Krok 2**: Výber/pridanie prevádzky
   - **Krok 3**: Údaje o zariadení (voliteľné)
   - **Krok 4**: Stav komponentov + upload fotiek
   - **Krok 5**: Digitálne podpisy
   - **Krok 6**: Súhrn a dokončenie
4. Po dokončení sa vygeneruje PDF a môžete ho stiahnuť alebo odoslať emailom

### API Endpointy

- `GET index.php?action=api_customers` - Zoznam zákazníkov
- `GET index.php?action=api_locations&customer_id=X` - Zoznam prevádzok zákazníka
- `GET index.php?action=api_reports` - Zoznam protokolov
- `GET index.php?action=download_pdf&report_id=X` - Stiahnutie PDF

### POST Akcie

- `action=add_customer` - Pridanie zákazníka
- `action=add_location` - Pridanie prevádzky
- `action=upload_signature` - Uloženie podpisu
- `action=upload_photo` - Nahranie fotky
- `action=save_step` - Uloženie dát kroku
- `action=finalize_report` - Dokončenie protokolu
- `action=send_email` - Odoslanie emailu

## 🔒 Bezpečnosť

- Všetky databázové operácie používajú PDO prepared statements
- Upload súborov je validovaný (MIME typ, veľkosť)
- Adresár `data/` je chránený .htaccess
- Foreign keys sú zapnuté pre referenčnú integritu

## 📝 Poznámky

- Pôvodné súbory (`index.html`, `*.php`) zostávajú nezmenené
- MVP funkcionalita je prístupná cez `index.php`
- Sidebar obsahuje odkazy na obe verzie

## 📄 Licencia

MIT License
