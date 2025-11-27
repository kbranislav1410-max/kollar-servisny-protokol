/**
 * Servisný Protokol - Main Application JavaScript
 * 
 * Handles:
 * - Photo uploads with drag and drop
 * - Signature capture using SignaturePad
 * - Fetch API calls to backend endpoints
 * - Step-by-step workflow navigation
 */

(function() {
    'use strict';

    // API base URL
    const API_BASE = 'index.php';
    
    // Current report state
    let currentReportId = null;
    let currentStep = 0;
    
    // Signature pad instances
    let technicianSignaturePad = null;
    let customerSignaturePad = null;

    /**
     * Initialize the application
     */
    function init() {
        // Initialize signature pads if elements exist
        initSignaturePads();
        
        // Initialize photo upload handlers
        initPhotoUpload();
        
        // Load customers if on the main form
        loadCustomers();
    }

    /**
     * Make API request
     */
    async function apiRequest(action, method = 'GET', data = null, queryParams = null) {
        let url = `${API_BASE}?action=${encodeURIComponent(action)}`;
        
        // Add query parameters for GET requests
        if (queryParams && typeof queryParams === 'object') {
            for (const [key, value] of Object.entries(queryParams)) {
                url += `&${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
            }
        }
        
        const options = {
            method: method,
            headers: {}
        };
        
        if (method === 'POST' && data) {
            if (data instanceof FormData) {
                options.body = data;
            } else if (typeof data === 'object') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        }
        
        try {
            const response = await fetch(url, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || 'Nastala chyba');
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    /**
     * Load customers from API
     */
    async function loadCustomers() {
        const select = document.getElementById('customer-select');
        if (!select) return;
        
        try {
            const customers = await apiRequest('customers');
            select.innerHTML = '<option value="">-- Vyberte zákazníka --</option>';
            
            customers.forEach(customer => {
                const option = document.createElement('option');
                option.value = customer.id;
                option.textContent = `${customer.nazov_firmy} (${customer.ico})`;
                option.dataset.customer = JSON.stringify(customer);
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Failed to load customers:', error);
        }
    }

    /**
     * Load locations for selected customer
     */
    async function loadLocations(customerId) {
        const select = document.getElementById('location-select');
        if (!select || !customerId) return;
        
        try {
            const locations = await apiRequest('locations', 'GET', null, { customer_id: customerId });
            select.innerHTML = '<option value="">-- Vyberte prevádzku --</option>';
            
            locations.forEach(location => {
                const option = document.createElement('option');
                option.value = location.id;
                option.textContent = `${location.nazov} (${location.mesto})`;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Failed to load locations:', error);
        }
    }

    /**
     * Load devices for selected location
     */
    async function loadDevices(locationId) {
        const select = document.getElementById('device-select');
        if (!select || !locationId) return;
        
        try {
            const devices = await apiRequest('devices', 'GET', null, { location_id: locationId });
            select.innerHTML = '<option value="">-- Vyberte zariadenie --</option>';
            select.innerHTML += '<option value="new">+ Pridať nové zariadenie</option>';
            
            devices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.id;
                option.textContent = `${device.nazov} (${device.typ || 'bez typu'})`;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Failed to load devices:', error);
        }
    }

    /**
     * Start a new report
     */
    async function startReport(customerId, locationId, deviceId, technikMeno) {
        try {
            const formData = new FormData();
            if (customerId) formData.append('customer_id', customerId);
            if (locationId) formData.append('location_id', locationId);
            if (deviceId) formData.append('device_id', deviceId);
            if (technikMeno) formData.append('technik_meno', technikMeno);
            
            const result = await apiRequest('start_report', 'POST', formData);
            currentReportId = result.report_id;
            
            // Store in session storage for page reloads
            sessionStorage.setItem('currentReportId', currentReportId);
            sessionStorage.setItem('cisloProtokolu', result.cislo_protokolu);
            
            return result;
        } catch (error) {
            console.error('Failed to start report:', error);
            throw error;
        }
    }

    /**
     * Save current step data
     */
    async function saveStep(stepData) {
        if (!currentReportId) {
            throw new Error('No active report');
        }
        
        try {
            const formData = new FormData();
            formData.append('report_id', currentReportId);
            formData.append('step', currentStep);
            
            for (const [key, value] of Object.entries(stepData)) {
                formData.append(key, value);
            }
            
            return await apiRequest('report_save_step', 'POST', formData);
        } catch (error) {
            console.error('Failed to save step:', error);
            throw error;
        }
    }

    /**
     * Initialize signature pads
     */
    function initSignaturePads() {
        const technicianCanvas = document.getElementById('signature-technician');
        const customerCanvas = document.getElementById('signature-customer');
        
        if (technicianCanvas && typeof SignaturePad !== 'undefined') {
            technicianSignaturePad = new SignaturePad(technicianCanvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)'
            });
            resizeCanvas(technicianCanvas);
        }
        
        if (customerCanvas && typeof SignaturePad !== 'undefined') {
            customerSignaturePad = new SignaturePad(customerCanvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)'
            });
            resizeCanvas(customerCanvas);
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (technicianCanvas) resizeCanvas(technicianCanvas);
            if (customerCanvas) resizeCanvas(customerCanvas);
        });
    }

    /**
     * Resize canvas to fit container
     */
    function resizeCanvas(canvas) {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
    }

    /**
     * Clear signature pad
     */
    function clearSignature(type) {
        if (type === 'technician' && technicianSignaturePad) {
            technicianSignaturePad.clear();
        } else if (type === 'customer' && customerSignaturePad) {
            customerSignaturePad.clear();
        }
    }

    /**
     * Save signatures to server
     */
    async function saveSignatures() {
        if (!currentReportId) {
            throw new Error('No active report');
        }
        
        const signatures = {
            report_id: currentReportId
        };
        
        if (technicianSignaturePad && !technicianSignaturePad.isEmpty()) {
            signatures.technician = technicianSignaturePad.toDataURL();
        }
        
        if (customerSignaturePad && !customerSignaturePad.isEmpty()) {
            signatures.customer = customerSignaturePad.toDataURL();
        }
        
        try {
            return await apiRequest('save_signatures', 'POST', signatures);
        } catch (error) {
            console.error('Failed to save signatures:', error);
            throw error;
        }
    }

    /**
     * Initialize photo upload functionality
     */
    function initPhotoUpload() {
        const dropzones = document.querySelectorAll('.photo-dropzone');
        
        dropzones.forEach(dropzone => {
            const input = dropzone.querySelector('input[type="file"]');
            const preview = dropzone.parentElement.querySelector('.photo-preview');
            const section = dropzone.dataset.section || 'other';
            
            if (!input) return;
            
            // Click to select files
            dropzone.addEventListener('click', () => input.click());
            
            // Drag and drop events
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
            
            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('dragover');
            });
            
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
                const files = e.dataTransfer.files;
                handleFiles(files, section, preview);
            });
            
            // File input change
            input.addEventListener('change', () => {
                handleFiles(input.files, section, preview);
            });
        });
    }

    /**
     * Handle uploaded files
     */
    async function handleFiles(files, section, previewContainer) {
        if (!currentReportId) {
            alert('Najprv začnite nový protokol');
            return;
        }
        
        const formData = new FormData();
        formData.append('report_id', currentReportId);
        formData.append('section', section);
        
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }
        
        try {
            const result = await apiRequest('upload_photos', 'POST', formData);
            
            if (result.uploaded && previewContainer) {
                result.uploaded.forEach(file => {
                    addPreviewImage(file, previewContainer);
                });
            }
            
            showMessage('success', `Nahraných ${result.count} súborov`);
        } catch (error) {
            showMessage('error', 'Chyba pri nahrávaní súborov');
        }
    }

    /**
     * Add preview image to container
     */
    function addPreviewImage(file, container) {
        const item = document.createElement('div');
        item.className = 'photo-preview-item';
        item.dataset.fileId = file.id;
        
        const img = document.createElement('img');
        img.src = file.file_path;
        img.alt = file.original_name;
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-btn';
        removeBtn.innerHTML = '×';
        removeBtn.addEventListener('click', () => {
            item.remove();
            // TODO: Add API call to delete attachment
        });
        
        item.appendChild(img);
        item.appendChild(removeBtn);
        container.appendChild(item);
    }

    /**
     * Finalize report and generate PDF
     */
    async function finalizeReport(sendEmail = false) {
        if (!currentReportId) {
            throw new Error('No active report');
        }
        
        try {
            // First save signatures
            await saveSignatures();
            
            // Then finalize
            const result = await apiRequest('finalize_report', 'POST', {
                report_id: currentReportId,
                send_email: sendEmail
            });
            
            if (result.success) {
                showMessage('success', 'Protokol bol úspešne dokončený!');
                
                // Open PDF in new tab
                if (result.pdf_path) {
                    window.open(result.pdf_path, '_blank');
                }
                
                // Clear session
                sessionStorage.removeItem('currentReportId');
                sessionStorage.removeItem('cisloProtokolu');
            }
            
            return result;
        } catch (error) {
            console.error('Failed to finalize report:', error);
            throw error;
        }
    }

    /**
     * Show message to user
     */
    function showMessage(type, message) {
        const container = document.getElementById('message-container');
        if (!container) {
            alert(message);
            return;
        }
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        
        container.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    /**
     * Navigate to next step
     */
    function nextStep() {
        const steps = document.querySelectorAll('.step');
        if (currentStep < steps.length - 1) {
            // Save current step data before moving
            saveCurrentStepData().then(() => {
                steps[currentStep].classList.remove('active');
                currentStep++;
                steps[currentStep].classList.add('active');
                updateStepIndicator();
            }).catch(err => {
                console.error('Failed to save step:', err);
            });
        }
    }

    /**
     * Navigate to previous step
     */
    function prevStep() {
        const steps = document.querySelectorAll('.step');
        if (currentStep > 0) {
            steps[currentStep].classList.remove('active');
            currentStep--;
            steps[currentStep].classList.add('active');
            updateStepIndicator();
        }
    }

    /**
     * Go to specific step
     */
    function goToStep(stepIndex) {
        const steps = document.querySelectorAll('.step');
        if (stepIndex >= 0 && stepIndex < steps.length) {
            steps[currentStep].classList.remove('active');
            currentStep = stepIndex;
            steps[currentStep].classList.add('active');
            updateStepIndicator();
        }
    }

    /**
     * Update step indicator
     */
    function updateStepIndicator() {
        const dots = document.querySelectorAll('.step-indicator .step-dot');
        dots.forEach((dot, index) => {
            dot.classList.remove('active', 'completed');
            if (index < currentStep) {
                dot.classList.add('completed');
            } else if (index === currentStep) {
                dot.classList.add('active');
            }
        });
    }

    /**
     * Save current step data from form
     */
    async function saveCurrentStepData() {
        const step = document.querySelector('.step.active');
        if (!step) return;
        
        const inputs = step.querySelectorAll('input, textarea, select');
        const data = {};
        
        inputs.forEach(input => {
            if (input.name) {
                data[input.name] = input.value;
            }
        });
        
        if (Object.keys(data).length > 0 && currentReportId) {
            return saveStep(data);
        }
    }

    /**
     * Restore report from session storage
     */
    function restoreSession() {
        const savedReportId = sessionStorage.getItem('currentReportId');
        if (savedReportId) {
            currentReportId = parseInt(savedReportId, 10);
            const cislo = sessionStorage.getItem('cisloProtokolu');
            const cisloInput = document.getElementById('cislo');
            if (cisloInput && cislo) {
                cisloInput.value = cislo;
            }
        }
    }

    // Expose functions globally
    window.ServisnyProtokol = {
        init: init,
        loadCustomers: loadCustomers,
        loadLocations: loadLocations,
        loadDevices: loadDevices,
        startReport: startReport,
        saveStep: saveStep,
        saveSignatures: saveSignatures,
        clearSignature: clearSignature,
        finalizeReport: finalizeReport,
        nextStep: nextStep,
        prevStep: prevStep,
        goToStep: goToStep,
        showMessage: showMessage,
        restoreSession: restoreSession,
        get currentReportId() { return currentReportId; },
        set currentReportId(id) { currentReportId = id; }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
