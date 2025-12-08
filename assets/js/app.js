/**
 * Servisný Protokol MVP - Hlavná JavaScript logika
 */

// Globálne premenné
let signaturePadTechnik = null;
let signaturePadZakaznik = null;
let uploadedPhotos = [];
let uploadedPhotosBefore = [];
let uploadedPhotosAfter = [];

/**
 * API helper function to make GET requests to backend endpoints
 * Uses existing backend endpoints via index.php?action=...
 * 
 * @param {string} action - The action parameter for index.php (e.g., 'api_customers', 'api_report_detail')
 * @param {Object} params - Additional query parameters (e.g., {customer_id: 123, report_id: 456})
 * @returns {Promise} - Promise that resolves with parsed JSON response
 * 
 * @example
 * // Load customers list
 * apiGet('api_customers').then(customers => console.log(customers));
 * 
 * // Load customer detail
 * apiGet('api_customer_detail', {customer_id: 123}).then(data => console.log(data));
 * 
 * // Load report detail
 * apiGet('api_report_detail', {report_id: 456}).then(report => console.log(report));
 */
function apiGet(action, params = {}) {
    // Build query string from params
    const queryParams = new URLSearchParams({ action, ...params });
    const url = `index.php?${queryParams.toString()}`;
    
    return fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('API request failed:', error);
            throw error;
        });
}

/**
 * SPA Helper: Populate customers view container
 * Loads customers and displays them in a table/grid with click handlers
 * Note: This function is designed to work with containers like #customersView or #customersList
 * 
 * @param {string} containerId - The ID of the container element (default: 'customersList')
 */
function populateCustomersView(containerId = 'customersList') {
    const container = document.getElementById(containerId);
    if (!container) {
        console.warn(`Container #${containerId} not found`);
        return;
    }
    
    container.innerHTML = '<p class="loading">Načítavam zákazníkov...</p>';
    
    apiGet('api_customers')
        .then(customers => {
            if (!customers || customers.length === 0) {
                container.innerHTML = '<p class="no-data">Žiadni zákazníci</p>';
                return;
            }
            
            // Store for filtering
            window.allCustomers = customers;
            
            // Render customers table/grid
            let html = '<div class="customers-grid">';
            customers.forEach(c => {
                html += `
                    <div class="customer-card" onclick="openCustomerDetail(${c.id})">
                        <div class="customer-card-header">
                            <h3>${escapeHtml(c.nazov_firmy)}</h3>
                            ${c.ico ? '<span class="customer-ico">IČO: ' + escapeHtml(c.ico) + '</span>' : ''}
                        </div>
                        <div class="customer-card-body">
                            ${c.sidlo ? '<p><strong>Sídlo:</strong> ' + escapeHtml(c.sidlo) + '</p>' : ''}
                            ${c.kontakt_osoba ? '<p><strong>Kontakt:</strong> ' + escapeHtml(c.kontakt_osoba) + '</p>' : ''}
                            ${c.telefon ? '<p><strong>Tel:</strong> ' + escapeHtml(c.telefon) + '</p>' : ''}
                        </div>
                        <div class="customer-card-footer">
                            <span class="view-detail">Zobraziť detail →</span>
                        </div>
                    </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(error => {
            container.innerHTML = '<p class="error">Chyba pri načítaní zákazníkov</p>';
            console.error('Error loading customers:', error);
        });
}

/**
 * SPA Helper: Open customer detail in detail view
 * Navigates to customer detail page showing locations, branches, devices, and reports
 * 
 * @param {number} customerId - The ID of the customer to display
 */
function openCustomerDetail(customerId) {
    // Navigate to customer detail page
    window.location.href = `index.php?action=customer_detail&customer_id=${customerId}`;
}

/**
 * SPA Helper: Open report detail in detail view
 * Displays report information including components, photos, and Download PDF button
 * 
 * @param {number} reportId - The ID of the report to display
 * @param {string} containerId - The ID of the container element (default: 'detailView')
 */
function openReportDetail(reportId, containerId = 'detailView') {
    // If containerId exists, use SPA-style loading
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = '<p class="loading">Načítavam protokol...</p>';
        
        apiGet('api_report_detail', { report_id: reportId })
            .then(report => {
                renderReportDetail(report, container);
            })
            .catch(error => {
                container.innerHTML = '<p class="error">Chyba pri načítaní protokolu</p>';
                console.error('Error loading report:', error);
            });
    } else {
        // Otherwise navigate to report detail page
        window.location.href = `index.php?action=report_detail&report_id=${reportId}`;
    }
}

/**
 * SPA Helper: Render report detail view
 * Displays components, photos, and Download PDF button
 * Uses data URIs provided by backend (p.dataUri) or URL/path (p.url/p.path)
 * 
 * @param {Object} report - The report data object
 * @param {HTMLElement} container - The container element to render into
 */
function renderReportDetail(report, container) {
    if (!report || report.error) {
        container.innerHTML = '<p class="error">Protokol nebol nájdený</p>';
        return;
    }
    
    let html = `
        <div class="report-detail">
            <div class="report-header">
                <h2>Protokol č. ${escapeHtml(report.cislo_protokolu || 'N/A')}</h2>
                <button class="btn btn-primary" onclick="downloadReportPDF(${report.id})">
                    📥 Stiahnuť PDF
                </button>
            </div>
            
            <div class="report-info">
                <h3>Základné údaje</h3>
                <div class="info-grid">
                    <div class="info-item"><strong>Dátum:</strong> ${escapeHtml(report.datum || '-')}</div>
                    <div class="info-item"><strong>Zákazník:</strong> ${escapeHtml(report.nazov_firmy || '-')}</div>
                    <div class="info-item"><strong>Zariadenie:</strong> ${escapeHtml(report.device_name || '-')}</div>
                </div>
            </div>`;
    
    // Render components if available
    if (report.sekcie_data) {
        html += '<div class="report-components"><h3>Komponenty</h3>';
        html += renderComponents(report.sekcie_data);
        html += '</div>';
    }
    
    // Render photos if available
    html += '<div class="report-photos"><h3>Fotografie</h3>';
    
    if (report.photos_before && report.photos_before.length > 0) {
        html += '<h4>Fotografie PRED servisom</h4>';
        html += renderPhotosGrid(report.photos_before);
    }
    
    if (report.photos_after && report.photos_after.length > 0) {
        html += '<h4>Fotografie PO servise</h4>';
        html += renderPhotosGrid(report.photos_after);
    }
    
    if (report.photos_general && report.photos_general.length > 0) {
        html += '<h4>Ostatné fotografie</h4>';
        html += renderPhotosGrid(report.photos_general);
    }
    
    if (report.component_photos && Object.keys(report.component_photos).length > 0) {
        html += '<h4>Fotografie komponentov</h4>';
        for (const [key, photos] of Object.entries(report.component_photos)) {
            html += `<h5>${escapeHtml(key)}</h5>`;
            html += renderPhotosGrid(photos);
        }
    }
    
    html += '</div></div>';
    
    container.innerHTML = html;
}

/**
 * Helper: Render photos grid for browser display
 * Uses data URIs (p.dataUri) or URL/path (p.url or p.path) from backend
 * 
 * @param {Array} photos - Array of photo objects
 * @returns {string} HTML for photos grid
 */
function renderPhotosGrid(photos) {
    if (!photos || photos.length === 0) {
        return '<p class="no-data">Žiadne fotografie</p>';
    }
    
    let html = '<div class="photos-grid">';
    photos.forEach(p => {
        // Support multiple formats: dataUri, url, or path
        const imgSrc = p.dataUri || p.url || p.path || '';
        const fileName = p.file_name || p.filename || 'Fotografia';
        
        if (imgSrc) {
            html += `
                <div class="photo-item">
                    <img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(fileName)}" loading="lazy">
                    <div class="photo-caption">${escapeHtml(fileName)}</div>
                </div>`;
        }
    });
    html += '</div>';
    return html;
}

/**
 * Helper: Render components data
 * 
 * @param {Object} sekcieData - Components data object
 * @returns {string} HTML for components display
 */
function renderComponents(sekcieData) {
    if (!sekcieData || Object.keys(sekcieData).length === 0) {
        return '<p class="no-data">Žiadne údaje o komponentoch</p>';
    }
    
    let html = '<div class="components-list">';
    for (const [key, data] of Object.entries(sekcieData)) {
        if (!data || (typeof data === 'object' && Object.keys(data).filter(k => data[k]).length === 0)) {
            continue;
        }
        
        html += `<div class="component-card"><h4>${escapeHtml(key)}</h4>`;
        html += '<div class="component-info">';
        
        if (typeof data === 'object') {
            for (const [field, value] of Object.entries(data)) {
                if (value && value !== '') {
                    html += `<div class="info-item"><strong>${escapeHtml(field)}:</strong> ${escapeHtml(value)}</div>`;
                }
            }
        } else {
            html += `<div class="info-item">${escapeHtml(data)}</div>`;
        }
        
        html += '</div></div>';
    }
    html += '</div>';
    return html;
}

/**
 * Helper: Download report PDF
 * Navigates to downloadReportPDF endpoint with report ID
 * 
 * @param {number} reportId - The ID of the report to download
 */
function downloadReportPDF(reportId) {
    window.location.href = `index.php?action=download_pdf&report_id=${reportId}`;
}

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
 * Načítanie zariadení pre prevádzku
 */
function loadDevices(locationId) {
    if (!locationId) return;
    
    fetch(`index.php?action=api_devices&location_id=${locationId}`)
        .then(response => response.json())
        .then(devices => {
            const select = document.getElementById('device_select');
            if (!select) return;
            
            select.innerHTML = '<option value="">-- Vyberte zariadenie --</option>';
            devices.forEach(d => {
                const option = document.createElement('option');
                option.value = d.id;
                // Zobrazíme interné označenie ak existuje, inak typ
                let label = d.nazov;
                if (d.interne_oznacenie) {
                    label += ` [${d.interne_oznacenie}]`;
                }
                if (d.typ) {
                    label += ` (${d.typ})`;
                }
                option.textContent = label;
                select.appendChild(option);
            });
            
            // Nastaviť aktuálnu hodnotu zo session
            if (window.reportData && window.reportData.device_id) {
                select.value = window.reportData.device_id;
            }
        })
        .catch(err => console.error('Chyba pri načítaní zariadení:', err));
}

/**
 * Použitie údajov zákazníka ako prevádzky
 */
function useCustomerAsLocation() {
    const customerId = document.getElementById('customer_select').value;
    if (!customerId) {
        alert('Najprv vyberte zákazníka v kroku 1');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_location_from_customer');
    formData.append('customer_id', customerId);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Prevádzka bola vytvorená s údajmi spoločnosti');
            loadLocations(customerId);
            // Nastaviť novú prevádzku ako vybranú
            setTimeout(() => {
                const locationSelect = document.getElementById('location_select');
                locationSelect.value = result.id;
                loadDevices(result.id);
            }, 500);
        } else {
            alert('Chyba: ' + (result.error || 'Neznáma chyba'));
        }
    })
    .catch(err => {
        alert('Chyba pripojenia');
        console.error(err);
    });
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
    
    // Výber prevádzky - načítanie zariadení
    const locationSelect = document.getElementById('location_select');
    if (locationSelect) {
        locationSelect.addEventListener('change', function() {
            loadDevices(this.value);
        });
    }
    
    // Pridanie nového zariadenia
    const newDeviceForm = document.getElementById('newDeviceForm');
    if (newDeviceForm) {
        newDeviceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const locationId = document.getElementById('location_select').value;
            if (!locationId) {
                alert('Najprv vyberte prevádzku v kroku 2');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'add_device');
            formData.append('location_id', locationId);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Zariadenie bolo pridané');
                    this.reset();
                    loadDevices(locationId);
                    // Nastaviť nové zariadenie ako vybrané
                    setTimeout(() => {
                        const deviceSelect = document.getElementById('device_select');
                        deviceSelect.value = result.id;
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
    // Camera input - opens device camera directly (legacy)
    const cameraInput = document.getElementById('cameraInput');
    if (cameraInput) {
        cameraInput.addEventListener('change', function() {
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                uploadPhoto(files[i], 'general');
            }
            this.value = ''; // Reset input
        });
    }
    
    // Gallery input - for selecting existing photos (legacy)
    const galleryInput = document.getElementById('galleryInput');
    if (galleryInput) {
        galleryInput.addEventListener('change', function() {
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                uploadPhoto(files[i], 'general');
            }
            this.value = ''; // Reset input
        });
    }
    
    // Before photos - camera
    const cameraInputBefore = document.getElementById('cameraInputBefore');
    if (cameraInputBefore) {
        cameraInputBefore.addEventListener('change', function() {
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                uploadPhoto(files[i], 'before');
            }
            this.value = '';
        });
    }
    
    // Before photos - gallery
    const galleryInputBefore = document.getElementById('galleryInputBefore');
    if (galleryInputBefore) {
        galleryInputBefore.addEventListener('change', function() {
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                uploadPhoto(files[i], 'before');
            }
            this.value = '';
        });
    }
    
    // After photos - camera
    const cameraInputAfter = document.getElementById('cameraInputAfter');
    if (cameraInputAfter) {
        cameraInputAfter.addEventListener('change', function() {
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                uploadPhoto(files[i], 'after');
            }
            this.value = '';
        });
    }
    
    // After photos - gallery
    const galleryInputAfter = document.getElementById('galleryInputAfter');
    if (galleryInputAfter) {
        galleryInputAfter.addEventListener('change', function() {
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                uploadPhoto(files[i], 'after');
            }
            this.value = '';
        });
    }
    
    // Legacy support for old photoInput
    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function() {
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                uploadPhoto(files[i], 'general');
            }
            this.value = ''; // Reset input
        });
    }
}

/**
 * Otvorenie fotoaparátu (legacy)
 */
function openCamera() {
    const cameraInput = document.getElementById('cameraInput');
    if (cameraInput) {
        cameraInput.click();
    }
}

/**
 * Otvorenie fotoaparátu pre konkrétny typ
 */
function openCameraForType(photoType) {
    let inputId = 'cameraInput';
    if (photoType === 'before') {
        inputId = 'cameraInputBefore';
    } else if (photoType === 'after') {
        inputId = 'cameraInputAfter';
    }
    
    const cameraInput = document.getElementById(inputId);
    if (cameraInput) {
        cameraInput.click();
    }
}

/**
 * Nahratie jednej fotky s typom
 */
function uploadPhoto(file, photoType = 'general') {
    if (file.size > 5 * 1024 * 1024) {
        alert('Súbor ' + file.name + ' je príliš veľký (max 5MB)');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_photo');
    formData.append('photo', file);
    formData.append('photo_type', photoType);
    
    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            const photoData = {
                filename: result.filename,
                name: file.name,
                photo_type: photoType
            };
            
            // Uloženie do správneho zoznamu
            if (photoType === 'before') {
                uploadedPhotosBefore.push(photoData);
                updatePhotoPreviewForType('before');
            } else if (photoType === 'after') {
                uploadedPhotosAfter.push(photoData);
                updatePhotoPreviewForType('after');
            } else {
                uploadedPhotos.push(photoData);
                updatePhotoPreview();
            }
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
 * Aktualizácia náhľadu fotiek pre konkrétny typ
 */
function updatePhotoPreviewForType(photoType) {
    let preview, countEl, photos;
    
    if (photoType === 'before') {
        preview = document.getElementById('photoPreviewBefore');
        countEl = document.getElementById('photoCountBefore');
        photos = uploadedPhotosBefore;
    } else if (photoType === 'after') {
        preview = document.getElementById('photoPreviewAfter');
        countEl = document.getElementById('photoCountAfter');
        photos = uploadedPhotosAfter;
    } else {
        return updatePhotoPreview();
    }
    
    if (!preview) return;
    
    preview.innerHTML = '';
    photos.forEach((photo, index) => {
        const div = document.createElement('div');
        div.className = 'photo-item';
        div.innerHTML = `
            <img src="uploads/photos/${escapeHtml(photo.filename)}" alt="${escapeHtml(photo.name)}">
            <button type="button" class="remove-photo" onclick="removePhotoByType('${photoType}', ${index})">×</button>
        `;
        preview.appendChild(div);
    });
    
    if (countEl) {
        if (photos.length > 0) {
            countEl.textContent = `${photos.length} ${photos.length === 1 ? 'fotografia' : (photos.length < 5 ? 'fotografie' : 'fotografií')} nahraných`;
            countEl.style.display = 'block';
        } else {
            countEl.style.display = 'none';
        }
    }
}

/**
 * Aktualizácia náhľadu fotiek (legacy)
 */
function updatePhotoPreview() {
    const preview = document.getElementById('photoPreview');
    if (!preview) return;
    
    preview.innerHTML = '';
    uploadedPhotos.forEach((photo, index) => {
        const div = document.createElement('div');
        div.className = 'photo-item';
        div.innerHTML = `
            <img src="uploads/photos/${escapeHtml(photo.filename)}" alt="${escapeHtml(photo.name)}">
            <button type="button" class="remove-photo" onclick="removePhoto(${index})">×</button>
        `;
        preview.appendChild(div);
    });
    
    // Update photo count
    const countEl = document.getElementById('photoCount');
    if (countEl) {
        if (uploadedPhotos.length > 0) {
            countEl.textContent = `${uploadedPhotos.length} ${uploadedPhotos.length === 1 ? 'fotografia' : (uploadedPhotos.length < 5 ? 'fotografie' : 'fotografií')} nahraných`;
            countEl.style.display = 'block';
        } else {
            countEl.style.display = 'none';
        }
    }
}

/**
 * Odstránenie fotky (legacy)
 */
function removePhoto(index) {
    uploadedPhotos.splice(index, 1);
    updatePhotoPreview();
}

/**
 * Odstránenie fotky podľa typu
 */
function removePhotoByType(photoType, index) {
    if (photoType === 'before') {
        uploadedPhotosBefore.splice(index, 1);
        updatePhotoPreviewForType('before');
    } else if (photoType === 'after') {
        uploadedPhotosAfter.splice(index, 1);
        updatePhotoPreviewForType('after');
    } else {
        removePhoto(index);
    }
}

/**
 * Helper function to escape HTML - using string replacement for better performance
 */
function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function(match) {
        const escapeMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#x27;'
        };
        return escapeMap[match];
    });
}

/**
 * Prechod na ďalší krok
 */
function nextStep(currentStepIndex) {
    // Validácia dát aktuálneho kroku
    if (!validateStep(currentStepIndex)) {
        return;
    }
    
    // Zbieranie dát aktuálneho kroku
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
            if (result.next_step === 7) {
                updateSummary();
            }
        }
    })
    .catch(err => console.error('Chyba:', err));
}

/**
 * Validácia dát aktuálneho kroku
 */
function validateStep(stepIndex) {
    switch (stepIndex) {
        case 0: // Zákazník
            const customerSelect = document.getElementById('customer_select');
            if (!customerSelect || !customerSelect.value) {
                alert('Prosím vyberte zákazníka');
                return false;
            }
            break;
            
        case 1: // Prevádzka
            const locationSelect = document.getElementById('location_select');
            if (!locationSelect || !locationSelect.value) {
                alert('Prosím vyberte prevádzku');
                return false;
            }
            break;
            
        case 2: // Zariadenie - POVINNÉ
            const deviceSelect = document.getElementById('device_select');
            if (!deviceSelect || !deviceSelect.value) {
                alert('Prosím vyberte zariadenie. Zariadenie je povinné pre vytvorenie protokolu.');
                return false;
            }
            break;
        // Steps 3, 4, 5 (Fotky pred, Komponenty, Fotky po) - no required validation
    }
    return true;
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
 * Prechod na konkrétny krok (kliknutím na číslo v progress bare)
 */
function goToStep(targetStep) {
    // Môžeme ísť len na dokončené kroky alebo aktuálny
    if (targetStep > window.currentStep) {
        return; // Nemôžeme preskakovať dopredu
    }
    
    if (targetStep === window.currentStep) {
        return; // Už sme na tomto kroku
    }
    
    // Prechod na požadovaný krok
    window.currentStep = targetStep;
    showStep(targetStep);
    updateProgressBar(targetStep);
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
        step.classList.remove('active', 'completed', 'clickable');
        if (i < currentStep) {
            step.classList.add('completed', 'clickable');
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
            const deviceSelect = document.getElementById('device_select');
            if (deviceSelect) {
                data.device_id = deviceSelect.value;
            }
            break;
            
        case 3: // Fotky pred servisom
            // Fotky sa ukladajú priamo pri výbere/fotení
            break;
            
        case 4: // Komponenty
            const componentsForm = document.getElementById('componentsForm');
            if (componentsForm) {
                const formData = new FormData(componentsForm);
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
            }
            break;
            
        case 5: // Fotky po servise
            // Fotky sa ukladajú priamo pri výbere/fotení
            break;
            
        case 6: // Podpisy a odovzdanie
            // Podpisy sa ukladajú priamo pri kliknutí na "Uložiť podpis"
            // Ale nové textové polia treba uložiť
            const signaturesForm = document.getElementById('signaturesForm');
            if (signaturesForm) {
                const formData = new FormData(signaturesForm);
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
            }
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
    const totalPhotos = uploadedPhotos.length + uploadedPhotosBefore.length + uploadedPhotosAfter.length;
    
    // Získať názov vybraného zariadenia z dropdownu
    let deviceDisplay = 'Nevybrané';
    const deviceSelect = document.getElementById('device_select');
    if (deviceSelect && deviceSelect.value) {
        const selectedOption = deviceSelect.options[deviceSelect.selectedIndex];
        if (selectedOption) {
            deviceDisplay = selectedOption.textContent;
        }
    }
    
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
                <span class="summary-label">Zariadenie:</span>
                <span class="summary-value">${deviceDisplay}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <h4>Servisné údaje</h4>
            <div class="summary-row">
                <span class="summary-label">Dátum servisu:</span>
                <span class="summary-value">${data.datum || '-'}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Servis vykonal:</span>
                <span class="summary-value">${data.servis_vykonal || '-'}</span>
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
                <span class="summary-value">${data.rekuperacia_pr || '-'} / ${data.rekuperacia_od || '-'}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Ventilátor:</span>
                <span class="summary-value">${data.ventilator_pr || '-'} / ${data.ventilator_od || '-'}</span>
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
                <span class="summary-label">Pred servisom:</span>
                <span class="summary-value">${uploadedPhotosBefore.length} súborov</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Po servise:</span>
                <span class="summary-value">${uploadedPhotosAfter.length} súborov</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Celkom:</span>
                <span class="summary-value">${totalPhotos} súborov</span>
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

/**
 * Conditional field toggles for Step 4 components
 */

// Toggle Klapky servo fields
function toggleKlapkyServo(selectEl) {
    const servoFields = document.getElementById('klapky_servo_fields');
    const momentField = document.getElementById('klapky_moment_field');
    
    if (selectEl.value === 'so_servopohonom') {
        if (servoFields) servoFields.style.display = 'block';
        if (momentField) momentField.style.display = 'block';
    } else {
        if (servoFields) servoFields.style.display = 'none';
        if (momentField) momentField.style.display = 'none';
    }
}

// Toggle Ventilátor remenica field
function toggleVentilatorRemenica(selectEl) {
    const remenicaField = document.getElementById('ventilator_remenica_field');
    
    if (selectEl.value === 'sprevodovany') {
        if (remenicaField) remenicaField.style.display = 'block';
    } else {
        if (remenicaField) remenicaField.style.display = 'none';
    }
}

// Toggle Motor remenica field
function toggleMotorRemenica(selectEl) {
    const remenicaField = document.getElementById('motor_remenica_field');
    
    if (selectEl.value === 'sprevodovany') {
        if (remenicaField) remenicaField.style.display = 'block';
    } else {
        if (remenicaField) remenicaField.style.display = 'none';
    }
}

// Toggle Ohrievač fields based on type
function toggleOhrievacFields(selectEl) {
    const plynovyFields = document.getElementById('ohrievac_plynovy_fields');
    
    if (selectEl.value === 'plynovy') {
        if (plynovyFields) plynovyFields.style.display = 'block';
    } else {
        if (plynovyFields) plynovyFields.style.display = 'none';
    }
}

// Toggle Ohrievač bypass servo field
function toggleOhrievacBypass(selectEl) {
    const bypassServoField = document.getElementById('ohrievac_bypass_servo_field');
    
    if (selectEl.value === 's_bypasom') {
        if (bypassServoField) bypassServoField.style.display = 'block';
    } else {
        if (bypassServoField) bypassServoField.style.display = 'none';
    }
}
