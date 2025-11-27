# Servisný Protokol

Aplikácia pre servisných technikov na správu zákazníkov, prevádzok, zariadení a generovanie servisných protokolov.

## Funkcie

- **Správa zákazníkov** - pridávanie a prehliadanie zákazníkov
- **Správa prevádzok** - prevádzky priradené k zákazníkom
- **Správa zariadení** - zariadenia na jednotlivých prevádzkach
- **Krokový workflow** - vyplnenie servisného protokolu krok po kroku
- **Fotodokumentácia** - nahrávanie fotografií pred a po servise
- **Podpisy** - zachytenie podpisov technika a zákazníka na tablete
- **Generovanie PDF** - automatické vygenerovanie PDF protokolu
- **E-mail** - odoslanie protokolu zákazníkovi e-mailom

## Inštalácia

1. Naklonovať repozitár
2. Nainštalovať závislosti pomocou Composer:
   ```bash
   composer install
   ```
3. Inicializovať databázu:
   ```bash
   php init_db.php
   ```
4. Nastaviť webový server (Apache/Nginx) s PHP 8.0+
5. Upraviť nastavenia v `config.php` podľa potreby (SMTP, cesty, atď.)

## Použitie

### Webové rozhranie

Pre plnú funkcionalitu použite **`index.php`** ako hlavný vstupný bod:
- `http://localhost/index.php` - API a hlavný router

Existujúce HTML súbory ostávajú funkčné pre základnú prácu:
- `index.html` - pôvodný formulár servisného protokolu
- `zoznam-zakaznikov.html` - zoznam zákazníkov
- `pridaniezakaznika.html` - pridanie nového zákazníka
- `zakaznik-detail.html` - detail zákazníka s prevádzkami

### API Endpointy (index.php)

| Akcia | Metóda | Popis |
|-------|--------|-------|
| `?action=home` | GET | Informácie o API |
| `?action=customers` | GET | Zoznam zákazníkov |
| `?action=add_customer` | POST | Pridať zákazníka |
| `?action=locations&customer_id=X` | GET | Prevádzky zákazníka |
| `?action=add_location` | POST | Pridať prevádzku |
| `?action=devices&location_id=X` | GET | Zariadenia na prevádzke |
| `?action=add_device` | POST | Pridať zariadenie |
| `?action=start_report` | POST | Začať nový protokol |
| `?action=report_step&report_id=X&step=Y` | GET | Dáta kroku protokolu |
| `?action=report_save_step` | POST | Uložiť krok protokolu |
| `?action=upload_photos` | POST | Nahrať fotografie |
| `?action=save_signatures` | POST | Uložiť podpisy |
| `?action=finalize_report` | POST | Dokončiť a generovať PDF |

### Štruktúra databázy (SQLite)

- **customers** - zákazníci (názov, IČO, DIČ, kontakt, atď.)
- **locations** - prevádzky (priradené k zákazníkovi)
- **devices** - zariadenia (priradené k prevádzke)
- **reports** - servisné protokoly
- **attachments** - prílohy (fotografie)

## Technológie

- **PHP 8.0+** - backend
- **SQLite** - databáza
- **Dompdf** - generovanie PDF
- **SignaturePad** - zachytávanie podpisov
- **Vanilla JavaScript** - frontend

## Adresárová štruktúra

```
├── assets/
│   ├── css/
│   │   └── style.css          # Základné štýly
│   └── js/
│       ├── app.js             # Hlavná JS aplikácia
│       └── signature_pad.min.js # Knižnica pre podpisy
├── data/
│   └── zakaznici.db           # SQLite databáza
├── uploads/
│   ├── photos/                # Nahrané fotografie
│   ├── pdfs/                  # Vygenerované PDF
│   └── signatures/            # Podpisy
├── vendor/                    # Composer závislosti
├── config.php                 # Konfigurácia
├── index.php                  # Hlavný router/API
├── init_db.php               # Inicializácia databázy
├── report_pdf.php            # Šablóna pre PDF
└── *.html                    # Pôvodné HTML súbory
```

## Konfigurácia

Upravte `config.php` pre nastavenie:
- Cesta k SQLite databáze
- Cesty pre uploady (fotky, podpisy, PDF)
- Nastavenia e-mailu (SMTP)
- Maximálna veľkosť nahrávaných súborov

## Licencia

Súkromný projekt

## Autor

Kollar Servisný Protokol
