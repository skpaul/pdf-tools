# Professional PDF Tools

A comprehensive web application suite for PDF manipulation with professional-grade features.

## 🔄 Features

### PDF Merger
- **Professional Merging** - True page-by-page combination using FPDI library
- **File Validation** - Comprehensive validation for file type, size, and format
- **Modern UI** - Beautiful, responsive interface with professional styling
- **Detailed Statistics** - Shows page counts, file sizes, and merge information
- **PDF Preview** - Built-in PDF viewer for immediate result verification

### PDF Splitter ✂️
- **Multiple Split Methods** - Individual pages, custom ranges, or equal chunks
- **Flexible Page Selection** - Extract specific pages or ranges (e.g., "1-3,5,7-10")
- **Batch Download** - Download all split files as a convenient ZIP archive
- **Smart Chunking** - Split large PDFs into smaller files with specified page counts
- **Visual Preview** - View split results before downloading

### PDF Inserter 📝 *NEW*
- **Position-Based Insertion** - Insert pages at specific positions in the base PDF
- **Selective Page Insertion** - Choose specific pages from source PDF to insert
- **Multiple Insert Modes** - Insert at position, append, prepend, or custom ranges
- **Precise Control** - Insert after any page number with automatic reordering
- **Professional Quality** - Maintains original PDF formatting and structure

## 🛡️ Security & Performance
- **Auto Cleanup** - Automatic removal of temporary files after processing
- **Secure File Handling** - Proper validation and sanitization
- **Large File Support** - Handles PDFs up to 50MB with optimized memory usage
- **Professional Libraries** - Uses industry-standard FPDI/FPDF libraries

## 📋 Requirements
- PHP 8.0 or higher
- Web server (Apache/Nginx with proper upload configuration)
- Composer for dependency management
- ZipArchive extension (for split file downloads)

## 🚀 Quick Start

### Installation
```bash
# Clone the project
git clone [repository-url]
cd pdf-merger-app

# Install dependencies
composer install

# Configure web server and access via browser
```

### Usage

#### Merge PDFs
1. Go to `index.html`
2. Select two PDF files (up to 5MB each)
3. Click "Merge PDFs" 
4. View result with statistics and download

#### Split PDFs
1. Go to `split.html` or click "PDF Splitter" from merger page
2. Upload a PDF file (up to 50MB)
3. Choose split method:
   - **Individual Pages**: One file per page
   - **Page Ranges**: Custom selection (e.g., "1-3,5,7-10")
   - **Equal Chunks**: Specify pages per file
4. Download individual files or all as ZIP

#### Insert PDF Pages
1. Go to `insert.html` or click "PDF Inserter" from any page
2. Upload base PDF and source PDF (up to 50MB each)
3. Choose insertion method:
   - **At Position**: Insert all pages at specific position
   - **Specific Pages**: Insert selected pages at position
   - **Append**: Add to end of base PDF
   - **Prepend**: Add to beginning of base PDF
4. Download the combined result PDF

## 📁 Project Structure
```
pdf-tools/
├── welcome.html              # Landing page with tool selection
├── index.html                # PDF Merger interface
├── split.html                # PDF Splitter interface
├── insert.html               # PDF Inserter interface         📝 NEW
├── merge_professional.php    # Professional PDF merger (FPDI-based)
├── split_professional.php    # Professional PDF splitter (FPDI-based)
├── insert_professional.php   # Professional PDF inserter      📝 NEW
├── view_pdf.php             # Universal PDF viewer (supports all outputs)
├── download_all_split.php   # ZIP download for split files
├── composer.json            # Dependencies (FPDI/FPDF)
├── .htaccess                # Apache configuration
├── uploads/                 # Temporary uploads (auto-cleaned)
├── merged/                  # Merged PDF outputs
├── split/                   # Split PDF outputs
├── inserted/                # Inserted PDF outputs           📁 NEW
└── vendor/                  # Composer dependencies
```

## ⚙️ Server Configuration

### PHP Settings (php.ini)
```ini
upload_max_filesize = 50M
post_max_size = 100M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

### Apache (.htaccess included)
```apache
php_value upload_max_filesize 50M
php_value post_max_size 100M
php_value max_execution_time 300
```

### Nginx
```nginx
client_max_body_size 50M;
client_body_timeout 300s;
client_header_timeout 300s;
```

## 📦 Dependencies
- **setasign/fpdi** - Professional PDF manipulation library
- **setasign/fpdf** - PDF generation library (FPDI dependency)

## 🔧 Advanced Features

### Merger Capabilities
- Combines all pages from multiple PDFs in sequence
- Preserves original PDF quality and formatting
- Handles complex PDF structures and embedded content
- Automatic file cleanup after successful merge

### Splitter Capabilities
- **Individual Pages**: Creates separate PDF for each page
- **Custom Ranges**: Extract specific pages using flexible syntax:
  - Single pages: `1,3,5`
  - Page ranges: `1-10,15-20`
  - Mixed: `1,3-5,8,10-15`
- **Equal Chunks**: Divide PDF into files of specified page count
- **ZIP Download**: Bundle all split files for easy download
- **Preview Support**: View split files directly in browser

## 🎨 UI Features
- **Drag & Drop Support** - Intuitive file selection
- **Real-time Validation** - Immediate feedback on file selection
- **Progress Indicators** - Visual feedback during processing
- **Responsive Design** - Works on desktop and mobile devices
- **Professional Styling** - Modern gradient backgrounds and smooth animations

## 🔒 Security
- File type validation (PDF only)
- File size limits to prevent abuse
- Temporary file cleanup
- Input sanitization
- Directory traversal protection

## 📄 License
This project is open source and available under the MIT License.

## 🆘 Support
For issues or feature requests, please check the documentation or contact support.
