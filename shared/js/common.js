/* Common JavaScript for PDF Tools Suite */

// Common utility functions
class PDFToolsUtils {
    // Format file size
    static formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Validate PDF file
    static validatePDFFile(file, maxSizeMB = 50) {
        if (!file) {
            return { valid: false, error: 'No file selected' };
        }

        if (file.type !== 'application/pdf') {
            return { valid: false, error: 'Please select a PDF file' };
        }

        const maxSizeBytes = maxSizeMB * 1024 * 1024;
        if (file.size > maxSizeBytes) {
            return { valid: false, error: `File size must be less than ${maxSizeMB}MB` };
        }

        return { valid: true };
    }

    // Show status message
    static showStatus(element, message, type = 'info') {
        element.className = `status show ${type}`;
        element.innerHTML = message;
        element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Show progress
    static showProgress(progressElement, percentage = 0) {
        progressElement.classList.add('active');
        const progressBar = progressElement.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = percentage + '%';
        }
    }

    // Hide progress
    static hideProgress(progressElement) {
        progressElement.classList.remove('active');
        const progressBar = progressElement.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = '0%';
        }
    }

    // Parse page ranges (e.g., "1,3,5-8" -> [1,3,5,6,7,8])
    static parsePageRanges(rangeString, maxPages) {
        const pages = new Set();
        const ranges = rangeString.split(',').map(s => s.trim());

        for (const range of ranges) {
            if (range.includes('-')) {
                const [start, end] = range.split('-').map(n => parseInt(n.trim()));
                if (isNaN(start) || isNaN(end) || start < 1 || end > maxPages || start > end) {
                    throw new Error(`Invalid page range: ${range}`);
                }
                for (let i = start; i <= end; i++) {
                    pages.add(i);
                }
            } else {
                const page = parseInt(range);
                if (isNaN(page) || page < 1 || page > maxPages) {
                    throw new Error(`Invalid page number: ${range}`);
                }
                pages.add(page);
            }
        }

        return Array.from(pages).sort((a, b) => a - b);
    }

    // Generate unique filename
    static generateUniqueFilename(originalName, suffix = '') {
        const timestamp = Date.now();
        const nameWithoutExt = originalName.replace(/\.pdf$/i, '');
        return `${nameWithoutExt}${suffix}_${timestamp}.pdf`;
    }

    // Download file
    static downloadFile(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Create breadcrumb navigation
    static createBreadcrumb(items) {
        const breadcrumb = document.createElement('div');
        breadcrumb.className = 'breadcrumb';
        
        const container = document.createElement('div');
        container.className = 'container';
        
        const nav = document.createElement('nav');
        nav.className = 'breadcrumb-nav';

        items.forEach((item, index) => {
            if (index > 0) {
                const separator = document.createElement('span');
                separator.className = 'breadcrumb-separator';
                separator.textContent = '/';
                nav.appendChild(separator);
            }

            if (item.url) {
                const link = document.createElement('a');
                link.href = item.url;
                link.textContent = item.title;
                nav.appendChild(link);
            } else {
                const span = document.createElement('span');
                span.textContent = item.title;
                nav.appendChild(span);
            }
        });

        container.appendChild(nav);
        breadcrumb.appendChild(container);
        
        return breadcrumb;
    }
}

// Drag and drop functionality
class DragDropHandler {
    constructor(element, callback, options = {}) {
        this.element = element;
        this.callback = callback;
        this.options = {
            allowMultiple: options.allowMultiple || false,
            acceptedTypes: options.acceptedTypes || ['.pdf']
        };

        this.init();
    }

    init() {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.element.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
        });

        // Highlight drop area when dragging over it
        ['dragenter', 'dragover'].forEach(eventName => {
            this.element.addEventListener(eventName, () => {
                this.element.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.element.addEventListener(eventName, () => {
                this.element.classList.remove('dragover');
            }, false);
        });

        // Handle dropped files
        this.element.addEventListener('drop', this.handleDrop.bind(this), false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    handleDrop(e) {
        const files = Array.from(e.dataTransfer.files);
        const pdfFiles = files.filter(file => file.type === 'application/pdf');

        if (pdfFiles.length === 0) {
            alert('Please drop PDF files only.');
            return;
        }

        if (!this.options.allowMultiple && pdfFiles.length > 1) {
            alert('Please drop only one PDF file.');
            return;
        }

        this.callback(this.options.allowMultiple ? pdfFiles : pdfFiles[0]);
    }
}

// File list management
class FileListManager {
    constructor(container) {
        this.container = container;
        this.files = [];
    }

    addFile(file) {
        const validation = PDFToolsUtils.validatePDFFile(file);
        if (!validation.valid) {
            throw new Error(validation.error);
        }

        this.files.push(file);
        this.render();
    }

    addFiles(files) {
        for (const file of files) {
            this.addFile(file);
        }
    }

    removeFile(index) {
        this.files.splice(index, 1);
        this.render();
    }

    clear() {
        this.files = [];
        this.render();
    }

    getFiles() {
        return this.files;
    }

    render() {
        if (this.files.length === 0) {
            this.container.style.display = 'none';
            return;
        }

        this.container.style.display = 'block';
        this.container.innerHTML = this.files.map((file, index) => `
            <div class="file-item">
                <div class="file-info">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">(${PDFToolsUtils.formatFileSize(file.size)})</span>
                </div>
                <button class="file-remove" onclick="fileManager.removeFile(${index})">Remove</button>
            </div>
        `).join('');
    }
}

// Initialize common functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add breadcrumb if we're in a subdirectory
    const path = window.location.pathname;
    const segments = path.split('/').filter(segment => segment);
    
    if (segments.length > 1) {
        const breadcrumbItems = [
            { title: '🏠 Home', url: '/pdf-tools/' }
        ];

        // Add intermediate segments
        let currentPath = '/pdf-tools';
        for (let i = segments.length - 2; i >= 0; i--) {
            const segment = segments[i];
            if (segment !== 'pdf-tools') {
                currentPath += '/' + segment;
                breadcrumbItems.push({
                    title: segment.charAt(0).toUpperCase() + segment.slice(1),
                    url: currentPath + '/'
                });
            }
        }

        // Add current page
        const currentPage = segments[segments.length - 1];
        if (currentPage && currentPage !== 'index.html') {
            breadcrumbItems.push({
                title: currentPage.replace('.html', '').charAt(0).toUpperCase() + currentPage.replace('.html', '').slice(1)
            });
        }

        if (breadcrumbItems.length > 1) {
            const breadcrumb = PDFToolsUtils.createBreadcrumb(breadcrumbItems);
            document.body.insertBefore(breadcrumb, document.body.firstChild);
        }
    }

    // Initialize smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PDFToolsUtils, DragDropHandler, FileListManager };
}
