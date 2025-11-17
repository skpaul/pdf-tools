<?php
// Professional PDF Splitter using FPDI library
require_once '../../vendor/autoload.php';

// Set PHP configuration for large file uploads
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '256M');

use setasign\Fpdi\Fpdi;

// Create necessary directories
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}
if (!file_exists('split')) {
    mkdir('split', 0777, true);
}

function showError($message) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Invalid request method. Please use the upload form.');
}

if (!isset($_FILES['pdf_file'])) {
    showError('Please select a PDF file to split.');
}

$pdfFile = $_FILES['pdf_file'];
$splitMode = $_POST['split_mode'] ?? 'pages';
$splitValue = $_POST['split_value'] ?? '';

// Validate file upload
if ($pdfFile['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'File upload error: ';
    if ($pdfFile['error'] === UPLOAD_ERR_FORM_SIZE || $pdfFile['error'] === UPLOAD_ERR_INI_SIZE) {
        $errorMessage .= 'File size exceeds the maximum allowed limit (50 MB).';
    } else {
        $errorMessage .= 'Please try again with a smaller file.';
    }
    showError($errorMessage);
}

// Additional file size validation (50 MB limit)
$maxFileSize = 50 * 1024 * 1024; // 50 MB in bytes
if ($pdfFile['size'] > $maxFileSize) {
    showError('File size too large. Maximum allowed size is 50 MB.');
}

// Validate file type
$allowedTypes = ['application/pdf'];
$pdfType = mime_content_type($pdfFile['tmp_name']);

if (!in_array($pdfType, $allowedTypes)) {
    showError('Invalid file type. Please upload only PDF files.');
}

// Validate file extension
$pdfExt = strtolower(pathinfo($pdfFile['name'], PATHINFO_EXTENSION));
if ($pdfExt !== 'pdf') {
    showError('Invalid file extension. Please upload only .pdf files.');
}

// Generate unique filename
$timestamp = time();
$originalName = pathinfo($pdfFile['name'], PATHINFO_FILENAME);
$uploadedName = 'uploaded_' . $timestamp . '.pdf';
$uploadedPath = 'uploads/' . $uploadedName;

// Move uploaded file
if (!move_uploaded_file($pdfFile['tmp_name'], $uploadedPath)) {
    showError('Failed to save uploaded PDF file.');
}

// Professional PDF splitter using FPDI
function splitPDF($inputPath, $splitMode, $splitValue, $originalName, $timestamp) {
    try {
        $results = [];
        
        // First, get total page count
        $pdf = new Fpdi();
        $totalPages = $pdf->setSourceFile($inputPath);
        
        if ($splitMode === 'pages') {
            // Split by page ranges (e.g., "1-3,5,7-9")
            $ranges = explode(',', $splitValue);
            $fileIndex = 1;
            
            foreach ($ranges as $range) {
                $range = trim($range);
                if (empty($range)) continue;
                
                $pdf = new Fpdi();
                
                if (strpos($range, '-') !== false) {
                    // Range like "1-3"
                    list($start, $end) = explode('-', $range);
                    $start = max(1, intval(trim($start)));
                    $end = min($totalPages, intval(trim($end)));
                    
                    $pdf->setSourceFile($inputPath);
                    for ($i = $start; $i <= $end; $i++) {
                        $pdf->AddPage();
                        $templateId = $pdf->importPage($i);
                        $pdf->useTemplate($templateId);
                    }
                    
                    $outputName = $originalName . "_pages_{$start}-{$end}_{$timestamp}.pdf";
                    $pageCount = $end - $start + 1;
                } else {
                    // Single page
                    $pageNum = max(1, min($totalPages, intval($range)));
                    $pdf->setSourceFile($inputPath);
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($pageNum);
                    $pdf->useTemplate($templateId);
                    
                    $outputName = $originalName . "_page_{$pageNum}_{$timestamp}.pdf";
                    $pageCount = 1;
                }
                
                $outputPath = 'split/' . $outputName;
                $pdf->Output($outputPath, 'F');
                
                $results[] = [
                    'filename' => $outputName,
                    'path' => $outputPath,
                    'pages' => $pageCount,
                    'size' => filesize($outputPath)
                ];
                
                $fileIndex++;
            }
        } elseif ($splitMode === 'individual') {
            // Split into individual pages
            for ($i = 1; $i <= $totalPages; $i++) {
                $pdf = new Fpdi();
                $pdf->setSourceFile($inputPath);
                $pdf->AddPage();
                $templateId = $pdf->importPage($i);
                $pdf->useTemplate($templateId);
                
                $outputName = $originalName . "_page_{$i}_{$timestamp}.pdf";
                $outputPath = 'split/' . $outputName;
                $pdf->Output($outputPath, 'F');
                
                $results[] = [
                    'filename' => $outputName,
                    'path' => $outputPath,
                    'pages' => 1,
                    'size' => filesize($outputPath)
                ];
            }
        } elseif ($splitMode === 'chunks') {
            // Split into chunks of N pages
            $chunkSize = max(1, intval($splitValue));
            $chunkIndex = 1;
            
            for ($startPage = 1; $startPage <= $totalPages; $startPage += $chunkSize) {
                $pdf = new Fpdi();
                $pdf->setSourceFile($inputPath);
                $endPage = min($startPage + $chunkSize - 1, $totalPages);
                
                for ($i = $startPage; $i <= $endPage; $i++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                }
                
                $outputName = $originalName . "_chunk_{$chunkIndex}_{$timestamp}.pdf";
                $outputPath = 'split/' . $outputName;
                $pdf->Output($outputPath, 'F');
                
                $results[] = [
                    'filename' => $outputName,
                    'path' => $outputPath,
                    'pages' => $endPage - $startPage + 1,
                    'size' => filesize($outputPath)
                ];
                
                $chunkIndex++;
            }
        }
        
        return [
            'success' => true,
            'results' => $results,
            'total_pages' => $totalPages,
            'total_files' => count($results)
        ];
        
    } catch (Exception $e) {
        error_log("PDF split error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Validate split parameters
if ($splitMode === 'pages' && empty($splitValue)) {
    unlink($uploadedPath);
    showError('Please specify page ranges (e.g., "1-3,5,7-9").');
}

if ($splitMode === 'chunks' && (empty($splitValue) || intval($splitValue) < 1)) {
    unlink($uploadedPath);
    showError('Please specify a valid chunk size (number of pages per file).');
}

// Attempt to split PDF
$splitResult = splitPDF($uploadedPath, $splitMode, $splitValue, $originalName, $timestamp);

if ($splitResult['success']) {
    $totalSize = array_sum(array_column($splitResult['results'], 'size'));
    $totalSizeMB = round($totalSize / (1024 * 1024), 2);
    
    // Clean up uploaded file
    unlink($uploadedPath);
    
    // Prepare file data for JSON response
    $files = [];
    $zipUrl = null;
    
    if (count($splitResult['results']) > 1) {
        // Create ZIP file for multiple files
        $zipName = "split_files_{$timestamp}.zip";
        $zipPath = "../../outputs/split/{$zipName}";
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($splitResult['results'] as $file) {
                $zip->addFile($file['path'], $file['name']);
            }
            $zip->close();
            $zipUrl = $zipPath;
        }
    }
    
    foreach ($splitResult['results'] as $file) {
        $files[] = [
            'name' => $file['name'],
            'pages' => $file['pages'],
            'size' => PDFToolsUtils::formatFileSize($file['size']),
            'download_url' => $file['path'],
            'view_url' => "../../shared/viewer/pdf-viewer.php?file=" . urlencode($file['name'])
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'files_created' => count($splitResult['results']),
        'total_size' => $totalSizeMB . ' MB',
        'files' => $files,
        'download_all_url' => $zipUrl
    ]);
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Success - Professional PDF Splitter</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                margin: 0;
            }
            .container { 
                max-width: 1000px; 
                background: white; 
                padding: 40px; 
                border-radius: 15px; 
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
            }
            .success { 
                color: #27ae60; 
                background: #f8fff8; 
                padding: 20px; 
                border-radius: 10px; 
                margin-bottom: 30px;
                border: 2px solid #27ae60;
            }
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            .stat-box {
                background: #fdf2f2;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #e74c3c;
            }
            .stat-number {
                font-size: 2rem;
                font-weight: bold;
                color: #e74c3c;
            }
            .files-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            .file-card {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid #e74c3c;
                text-align: left;
            }
            .file-card h4 {
                color: #333;
                margin: 0 0 10px 0;
                font-size: 0.9rem;
                word-break: break-all;
            }
            .file-info {
                font-size: 0.8rem;
                color: #666;
                margin: 5px 0;
            }
            .btn { 
                display: inline-block; 
                padding: 8px 15px; 
                background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 5px;
                font-size: 0.8rem;
                font-weight: 500;
            }
            .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
            }
            .btn-secondary { 
                background: linear-gradient(135deg, #3498db 0%, #5dade2 100%); 
            }
            .btn-download-all {
                background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
                padding: 15px 30px;
                font-size: 1rem;
                margin: 20px 10px;
            }
            h1 { color: #333; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>✂️ Professional PDF Splitter Success!</h1>
            <div class='success'>
                <h2>Your PDF has been successfully split!</h2>
                <p>The original document has been divided into {$splitResult['total_files']} separate files.</p>
            </div>
            
            <div class='stats'>
                <div class='stat-box'>
                    <div class='stat-number'>{$splitResult['total_pages']}</div>
                    <div>Original<br>Pages</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>{$splitResult['total_files']}</div>
                    <div>Split<br>Files</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>{$totalSizeMB}</div>
                    <div>Total Size<br>(MB)</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>" . ucfirst($splitMode) . "</div>
                    <div>Split<br>Method</div>
                </div>
            </div>
            
            <div class='files-grid'>";
    
    foreach ($splitResult['results'] as $file) {
        $fileSizeMB = round($file['size'] / (1024 * 1024), 2);
        echo "<div class='file-card'>
                <h4>{$file['filename']}</h4>
                <div class='file-info'>Pages: {$file['pages']}</div>
                <div class='file-info'>Size: {$fileSizeMB} MB</div>
                <div style='margin-top: 10px;'>
                    <a href='{$file['path']}' class='btn' download='{$file['filename']}'>📥 Download</a>
                    <a href='view_pdf.php?file=" . urlencode(basename($file['path'])) . "&dir=split' class='btn btn-secondary' target='_blank'>👁️ View</a>
                </div>
            </div>";
    }
    
    echo "    </div>
            
            <div style='margin-top: 30px;'>
                <a href='download_all_split.php?timestamp={$timestamp}' class='btn btn-download-all'>
                    📦 Download All Files (ZIP)
                </a>
                <a href='split.html' class='btn btn-secondary' style='padding: 15px 30px; font-size: 1rem;'>
                    ✂️ Split Another PDF
                </a>
                <a href='index.html' class='btn btn-secondary' style='padding: 15px 30px; font-size: 1rem;'>
                    🔄 Merge PDFs
                </a>
            </div>
        </div>
    </body>
    </html>";
    
    // Clean up uploaded file
    unlink($uploadedPath);
    
} else {
    // Clean up uploaded file on error
    unlink($uploadedPath);
    showError('Failed to split PDF file: ' . ($splitResult['error'] ?? 'Unknown error occurred. Please ensure the file is a valid PDF and try again.'));
}
?>
