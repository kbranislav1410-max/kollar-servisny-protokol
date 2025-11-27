/**
 * Servisný Protokol MVP - Hlavná JavaScript logika
 */

// Globálne premenné
let signaturePadTechnik = null;
let signaturePadZakaznik = null;
let uploadedPhotos = [];

/**
 * Prepnutie collapsible sekcie
 */
function toggleCollapsible(button) {
    const section = button.closest('.collapsible-section');
    section.classList.toggle('open');
}

// Inicializácia po načítaní DOM
document.addEventListener('DOMContentLoaded', function() {
    // Načítanie zákazníkov
    loadCustomers();
    
    // Inicializácia signature padov
    initSignaturePads();
    
    // Nastavenie udalostí pre formuláre
    setupFormHandlers();
    
    // Nastavenie udalostí pre nahrávanie fotiek
    setupPhotoUpload();
    
    // Aktualizácia súhrnu ak sme na poslednom kroku
    if (window.currentStep === 5) {
        updateSummary();
    }
});

/**
 * Načítanie zoznamu zákazníkov
 */
function loadCustomers() {
    fetch('index.php?action=api_customers')
        .then(response => response.json())
        .then(customers => {
            const select = document.getElementById('customer_select');
            if (!select) return;
            
            select.innerHTML = '<option value="">-- Vyberte zákazníka --</option>';
            customers.forEach(c => {
                const option = document.createElement('option');
                option.value = c.id;
                option.textContent = `${c.nazov_firmy}${c.ico ? ' (' + c.ico + ')' : ''}`;
                select.appendChild(option);
            });
            
            // Nastaviť aktuálnu hodnotu zo session
            if (window.reportData && window.reportData.customer_id) {
                select.value = window.reportData.customer_id;
                loadLocations(window.reportData.customer_id);
            }
        })
        .catch(err => console.error('Chyba pri načítaní zákazníkov:', err));
}

/**
 * Načítanie prevádzok pre zákazníka
 */
function loadLocations(customerId) {
    if (!customerId) return;
    
    fetch(`index.php?action=api_locations&customer_id=${customerId}`)
        .then(response => response.json())
        .then(locations => {
            const select = document.getElementById('location_select');
            if (!select) return;
            
            select.innerHTML = '<option value="">-- Vyberte prevádzku --</option>';
            locations.forEach(l => {
                const option = document.createElement('option');
                option.value = l.id;
                option.textContent = `${l.nazov}${l.mesto ? ' (' + l.mesto + ')' : ''}`;
                select.appendChild(option);
            });
            
            // Nastaviť aktuálnu hodnotu zo session
            if (window.reportData && window.reportData.location_id) {
                select.value = window.reportData.location_id;
            }
        })
        .catch(err => console.error('Chyba pri načítaní prevádzok:', err));
}

/**
 * Inicializácia signature padov
 */
function initSignaturePads() {
    const canvasTechnik = document.getElementById('signatureTechnik');
    const canvasZakaznik = document.getElementById('signatureZakaznik');
    
    if (canvasTechnik) {
        signaturePadTechnik = new SignaturePad(canvasTechnik, {
            lineWidth: 2,
            strokeStyle: '#1a1a2e'
        });
    }
    
    if (canvasZakaznik) {
        signaturePadZakaznik = new SignaturePad(canvasZakaznik, {
            lineWidth: 2,
            strokeStyle: '#1a1a2e'
        });
    }
}

/**
 * Vymazanie podpisu
 */
function clearSignature(type) {
    if (type === 'Technik' && signaturePadTechnik) {
        signaturePadTechnik.clear();
        document.getElementById('signatureTechnikStatus').textContent = '';
    } else if (type === 'Zakaznik' && signaturePadZakaznik) {
        signaturePadZakaznik.clear();
        document.getElementById('signatureZakaznikStatus').textContent = '';
    }
}

/**
 * Uloženie podpisu
 */
function saveSignature(type) {
    const pad = type === 'technik' ? signaturePadTechnik : signaturePadZakaznik;
    const statusEl = document.getElementById(`signature${type.charAt(0).toUpperCase() + type.slice(1)}Status`);
    
    if (!pad || pad.isBlank()) {
        if (statusEl) {
            statusEl.textContent = 'Prosím, najprv podpíšte';
            statusEl.className = 'status-text error';
        }
        return;
    }
    
    const signatureData = pad.toDataURL();
    
    const formData = new FormData();
    formData.append('action', 'upload_signature');
    formData.append('type', type);
    formData.append('signature_data', signatureData);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            if (statusEl) {
                statusEl.textContent = '✓ Podpis uložený';
                statusEl.className = 'status-text';
            }
            // Uložiť do lokálneho reportData
            if (!window.reportData) window.reportData = {};
            window.reportData['podpis_' + type] = result.filename;
        } else {
            if (statusEl) {
                statusEl.textContent = result.error || 'Chyba pri ukladaní';
                statusEl.className = 'status-text error';
            }
        }
    })
    .catch(err => {
        if (statusEl) {
            statusEl.textContent = 'Chyba pripojenia';
            statusEl.className = 'status-text error';
        }
        console.error('Chyba:', err);
    });
}

/**
 * Nastavenie event handlerov pre formuláre
 */
function setupFormHandlers() {
    // Výber zákazníka
    const customerSelect = document.getElementById('customer_select');
    if (customerSelect) {
        customerSelect.addEventListener('change', function() {
            loadLocations(this.value);
        });
    }
    
    // Pridanie nového zákazníka
    const newCustomerForm = document.getElementById('newCustomerForm');
    if (newCustomerForm) {
        newCustomerForm.addEventListener('submit', function(e) {
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
                    loadCustomers();
                    // Nastaviť nového zákazníka ako vybraného
                    setTimeout(() => {
                        customerSelect.value = result.id;
                        loadLocations(result.id);
                    }, 500);
                } else {
                    alert('Chyba: ' + (result.error || 'Neznáma chyba'));
                }
            })
            .catch(err => {
                alert('Chyba pripojenia');
                console.error(err);
            });
        });
    }
    
    // Pridanie novej prevádzky
    const newLocationForm = document.getElementById('newLocationForm');
    if (newLocationForm) {
        newLocationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const customerId = document.getElementById('customer_select').value;
            if (!customerId) {
                alert('Najprv vyberte zákazníka');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'add_location');
            formData.append('customer_id', customerId);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Prevádzka bola pridaná');
                    this.reset();
                    loadLocations(customerId);
                    // Nastaviť novú prevádzku ako vybranú
                    setTimeout(() => {
                        const locationSelect = document.getElementById('location_select');
                        locationSelect.value = result.id;
                    }, 500);
                } else {
                    alert('Chyba: ' + (result.error || 'Neznáma chyba'));
                }
            })
            .catch(err => {
                alert('Chyba pripojenia');
                console.error(err);
            });
        });
    }
}

/**
 * Nastavenie nahrávania fotiek
 */
function setupPhotoUpload() {
    const photoInput = document.getElementById('photoInput');
    if (!photoInput) return;
    
    photoInput.addEventListener('change', function() {
        const files = this.files;
        for (let i = 0; i < files.length; i++) {
            uploadPhoto(files[i]);
        }
        this.value = ''; // Reset input
    });
}

/**
 * Nahratie jednej fotky
 */
function uploadPhoto(file) {
    if (file.size > 5 * 1024 * 1024) {
        alert('Súbor ' + file.name + ' je príliš veľký (max 5MB)');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_photo');
    formData.append('photo', file);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            uploadedPhotos.push({
                filename: result.filename,
                name: file.name
            });
            updatePhotoPreview();
        } else {
            alert('Chyba: ' + (result.error || 'Nepodarilo sa nahrať súbor'));
        }
    })
    .catch(err => {
        alert('Chyba pripojenia');
        console.error(err);
    });
}

/**
 * Aktualizácia náhľadu fotiek
 */
function updatePhotoPreview() {
    const preview = document.getElementById('photoPreview');
    if (!preview) return;
    
    preview.innerHTML = '';
    uploadedPhotos.forEach((photo, index) => {
        const div = document.createElement('div');
        div.className = 'photo-item';
        div.innerHTML = `
            <img src="uploads/photos/${photo.filename}" alt="${photo.name}">
            <button class="remove-photo" onclick="removePhoto(${index})">×</button>
        `;
        preview.appendChild(div);
    });
}

/**
 * Odstránenie fotky
 */
function removePhoto(index) {
    uploadedPhotos.splice(index, 1);
    updatePhotoPreview();
}

/**
 * Prechod na ďalší krok
 */
function nextStep(currentStepIndex) {
    // Validácia a uloženie dát aktuálneho kroku
    const stepData = collectStepData(currentStepIndex);
    
    // Uloženie do session na serveri
    const formData = new FormData();
    formData.append('action', 'save_step');
    formData.append('step', currentStepIndex);
    
    for (const [key, value] of Object.entries(stepData)) {
        formData.append('data[' + key + ']', value);
    }
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Aktualizácia lokálneho stavu
            window.reportData = { ...window.reportData, ...stepData };
            window.currentStep = result.next_step;
            
            // Zobrazenie ďalšieho kroku
            showStep(result.next_step);
            updateProgressBar(result.next_step);
            
            // Ak prechádzame na krok súhrnu, aktualizovať ho
            if (result.next_step === 5) {
                updateSummary();
            }
        }
    })
    .catch(err => console.error('Chyba:', err));
}

/**
 * Prechod na predchádzajúci krok
 */
function prevStep(currentStepIndex) {
    const newStep = currentStepIndex - 1;
    if (newStep >= 0) {
        window.currentStep = newStep;
        showStep(newStep);
        updateProgressBar(newStep);
    }
}

/**
 * Zobrazenie konkrétneho kroku
 */
function showStep(stepIndex) {
    document.querySelectorAll('.step').forEach((step, i) => {
        step.classList.remove('active');
        if (step.id === `step-${stepIndex}`) {
            step.classList.add('active');
        }
    });
}

/**
 * Aktualizácia progress baru
 */
function updateProgressBar(currentStep) {
    document.querySelectorAll('.progress-step').forEach((step, i) => {
        step.classList.remove('active', 'completed');
        if (i < currentStep) {
            step.classList.add('completed');
        } else if (i === currentStep) {
            step.classList.add('active');
        }
    });
}

/**
 * Zbieranie dát z aktuálneho kroku
 */
function collectStepData(stepIndex) {
    const data = {};
    
    switch (stepIndex) {
        case 0: // Zákazník
            const customerSelect = document.getElementById('customer_select');
            if (customerSelect) {
                data.customer_id = customerSelect.value;
            }
            break;
            
        case 1: // Prevádzka
            const locationSelect = document.getElementById('location_select');
            if (locationSelect) {
                data.location_id = locationSelect.value;
            }
            break;
            
        case 2: // Zariadenie
            const deviceForm = document.getElementById('deviceForm');
            if (deviceForm) {
                const formData = new FormData(deviceForm);
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
            }
            break;
            
        case 3: // Komponenty
            const componentsForm = document.getElementById('componentsForm');
            if (componentsForm) {
                const formData = new FormData(componentsForm);
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
            }
            break;
            
        case 4: // Podpisy
            // Podpisy sa ukladajú priamo pri kliknutí na "Uložiť podpis"
            break;
    }
    
    return data;
}

/**
 * Aktualizácia súhrnu
 */
function updateSummary() {
    const summaryEl = document.getElementById('reportSummary');
    if (!summaryEl) return;
    
    const data = window.reportData || {};
    
    let html = `
        <div class="summary-section">
            <h4>Zákazník a prevádzka</h4>
            <div class="summary-row">
                <span class="summary-label">Zákazník ID:</span>
                <span class="summary-value">${data.customer_id || 'Nevybraný'}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Prevádzka ID:</span>
                <span class="summary-value">${data.location_id || 'Nevybraná'}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <h4>Zariadenie</h4>
            <div class="summary-row">
                <span class="summary-label">Názov:</span>
                <span class="summary-value">${data.device_nazov || '-'}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Typ:</span>
                <span class="summary-value">${data.device_typ || '-'}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <h4>Stav komponentov</h4>
            <div class="summary-row">
                <span class="summary-label">Klapky:</span>
                <span class="summary-value">${data.klapky_pr || '-'} / ${data.klapky_od || '-'}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Filtrácia:</span>
                <span class="summary-value">${data.filtracia_pr || '-'} / ${data.filtracia_od || '-'}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Rekuperácia:</span>
                <span class="summary-value">${data.rekuperacia || '-'}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Ventilátor:</span>
                <span class="summary-value">${data.ventilator || '-'}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <h4>Podpisy</h4>
            <div class="summary-row">
                <span class="summary-label">Technik:</span>
                <span class="summary-value">${data.podpis_technik ? '✓ Uložený' : '✗ Chýba'}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Zákazník:</span>
                <span class="summary-value">${data.podpis_zakaznik ? '✓ Uložený' : '✗ Chýba'}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <h4>Fotografie</h4>
            <div class="summary-row">
                <span class="summary-label">Počet:</span>
                <span class="summary-value">${uploadedPhotos.length} súborov</span>
            </div>
        </div>
    `;
    
    summaryEl.innerHTML = html;
}

/**
 * Dokončenie reportu
 */
function finalizeReport() {
    const btn = document.getElementById('finalizeBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Spracovávam...';
    }
    
    const formData = new FormData();
    formData.append('action', 'finalize_report');
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Zobraziť obrazovku úspechu
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step-complete').classList.add('active');
            
            // Nastaviť údaje
            document.getElementById('completedReportNumber').textContent = result.cislo_protokolu;
            document.getElementById('downloadPdfBtn').href = `index.php?action=download_pdf&report_id=${result.report_id}`;
            
            // Uložiť report ID pre email
            window.completedReportId = result.report_id;
        } else {
            alert('Chyba: ' + (result.error || 'Nepodarilo sa dokončiť report'));
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Dokončiť a vygenerovať PDF';
            }
        }
    })
    .catch(err => {
        alert('Chyba pripojenia');
        console.error(err);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Dokončiť a vygenerovať PDF';
        }
    });
}

/**
 * Zobrazenie formulára na email
 */
function showEmailForm() {
    const form = document.getElementById('emailForm');
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}

/**
 * Odoslanie emailu
 */
function sendEmail() {
    const emailTo = document.getElementById('emailTo').value;
    const statusEl = document.getElementById('emailStatus');
    
    if (!emailTo || !window.completedReportId) {
        if (statusEl) {
            statusEl.textContent = 'Vyplňte email príjemcu';
            statusEl.className = 'status-text error';
        }
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'send_email');
    formData.append('report_id', window.completedReportId);
    formData.append('to_email', emailTo);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            if (statusEl) {
                statusEl.textContent = '✓ Email bol odoslaný';
                statusEl.className = 'status-text';
            }
        } else {
            if (statusEl) {
                statusEl.textContent = result.error || 'Chyba pri odosielaní';
                statusEl.className = 'status-text error';
            }
        }
    })
    .catch(err => {
        if (statusEl) {
            statusEl.textContent = 'Chyba pripojenia';
            statusEl.className = 'status-text error';
        }
        console.error(err);
    });
}
