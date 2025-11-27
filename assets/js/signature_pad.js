/**
 * Servisný Protokol MVP - Jednoduchá implementácia Signature Pad
 */

class SignaturePad {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.isDrawing = false;
        this.lastX = 0;
        this.lastY = 0;
        this.isEmpty = true;
        
        // Nastavenia
        this.options = {
            lineWidth: options.lineWidth || 2,
            strokeStyle: options.strokeStyle || '#000',
            backgroundColor: options.backgroundColor || '#fff',
            ...options
        };
        
        this.init();
    }
    
    init() {
        // Nastavenie pozadia
        this.clear();
        
        // Event listeners pre myš
        this.canvas.addEventListener('mousedown', this.startDrawing.bind(this));
        this.canvas.addEventListener('mousemove', this.draw.bind(this));
        this.canvas.addEventListener('mouseup', this.stopDrawing.bind(this));
        this.canvas.addEventListener('mouseout', this.stopDrawing.bind(this));
        
        // Event listeners pre dotyk (mobil/tablet)
        this.canvas.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
        this.canvas.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        this.canvas.addEventListener('touchend', this.stopDrawing.bind(this));
    }
    
    /**
     * Kontrola či je dotyk vo vnútri canvasu
     */
    isTouchInsideCanvas(touch) {
        const rect = this.canvas.getBoundingClientRect();
        return (
            touch.clientX >= rect.left &&
            touch.clientX <= rect.right &&
            touch.clientY >= rect.top &&
            touch.clientY <= rect.bottom
        );
    }
    
    getCoordinates(e) {
        const rect = this.canvas.getBoundingClientRect();
        const scaleX = this.canvas.width / rect.width;
        const scaleY = this.canvas.height / rect.height;
        
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
    }
    
    getTouchCoordinates(e) {
        const touch = e.touches[0];
        const rect = this.canvas.getBoundingClientRect();
        const scaleX = this.canvas.width / rect.width;
        const scaleY = this.canvas.height / rect.height;
        
        return {
            x: (touch.clientX - rect.left) * scaleX,
            y: (touch.clientY - rect.top) * scaleY
        };
    }
    
    startDrawing(e) {
        this.isDrawing = true;
        const coords = this.getCoordinates(e);
        this.lastX = coords.x;
        this.lastY = coords.y;
    }
    
    handleTouchStart(e) {
        const touch = e.touches[0];
        if (this.isTouchInsideCanvas(touch)) {
            e.preventDefault();
            this.isDrawing = true;
            const coords = this.getTouchCoordinates(e);
            this.lastX = coords.x;
            this.lastY = coords.y;
        }
    }
    
    draw(e) {
        if (!this.isDrawing) return;
        
        this.isEmpty = false;
        const coords = this.getCoordinates(e);
        
        this.ctx.beginPath();
        this.ctx.strokeStyle = this.options.strokeStyle;
        this.ctx.lineWidth = this.options.lineWidth;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.moveTo(this.lastX, this.lastY);
        this.ctx.lineTo(coords.x, coords.y);
        this.ctx.stroke();
        
        this.lastX = coords.x;
        this.lastY = coords.y;
    }
    
    handleTouchMove(e) {
        if (!this.isDrawing) return;
        
        const touch = e.touches[0];
        if (this.isTouchInsideCanvas(touch)) {
            e.preventDefault();
            this.isEmpty = false;
            const coords = this.getTouchCoordinates(e);
            
            this.ctx.beginPath();
            this.ctx.strokeStyle = this.options.strokeStyle;
            this.ctx.lineWidth = this.options.lineWidth;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.moveTo(this.lastX, this.lastY);
            this.ctx.lineTo(coords.x, coords.y);
            this.ctx.stroke();
            
            this.lastX = coords.x;
            this.lastY = coords.y;
        }
    }
    
    stopDrawing() {
        this.isDrawing = false;
    }
    
    clear() {
        this.ctx.fillStyle = this.options.backgroundColor;
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        this.isEmpty = true;
    }
    
    isBlank() {
        return this.isEmpty;
    }
    
    toDataURL(type = 'image/png') {
        return this.canvas.toDataURL(type);
    }
    
    fromDataURL(dataURL) {
        const img = new Image();
        img.onload = () => {
            this.ctx.drawImage(img, 0, 0);
            this.isEmpty = false;
        };
        img.src = dataURL;
    }
}

// Export pre globálne použitie
window.SignaturePad = SignaturePad;
