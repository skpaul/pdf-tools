<?php
// Professional PDF Splitter using FPDI library
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser (we want JSON)
ini_set('log_errors', 1);

require_once '../../vendor/autoload.php';

// Set PHP configuration for large file uploads
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '256M');

use setasign\Fpdi\Fpdi;

// Create necessary directories
if (!file_exists('../../uploads')) {
    mkdir('../../uploads', 0777, true);
}
if (!file_exists('../../outputs/split')) {
    mkdir('../../outputs/split', 0777, true);
}

function showError($message) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// Global error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        showError("PHP Error: $message in $file on line $line");
    }
});

// Global exception handler
set_exception_handler(function($exception) {
    showError("PHP Exception: " . $exception->getMessage());
});

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Basic connectivity test
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'Split processor is working']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Invalid request method. Please use the upload form.');
}

if (!isset($_FILES['pdf_file'])) {
    showError('Please select a PDF file to split.');
}

$pdfFile = $_FILES['pdf_file'];

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
$fileType = mime_content_type($pdfFile['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    showError('Invalid file type. Please upload only PDF files.');
}

// Validate file extension
$fileExt = strtolower(pathinfo($pdfFile['name'], PATHINFO_EXTENSION));
if ($fileExt !== 'pdf') {
    showError('Invalid file extension. Please upload only .pdf files.');
}

// Get split parameters
$splitMode = $_POST['split_mode'] ?? 'all';
$splitValue = '';

switch ($splitMode) {
    case 'pages':
        $splitValue = $_POST['pages'] ?? '';
        break;
    case 'range':
        $startPage = $_POST['start_page'] ?? 1;
        $endPage = $_POST['end_page'] ?? 1;
        $splitValue = $startPage . '-' . $endPage;
        break;
    case 'chunks':
        $splitValue = $_POST['pages_per_chunk'] ?? 1;
        break;
    case 'all':
        $splitValue = '1';
        break;
}

// Generate unique filenames
$timestamp = time();
$originalName = pathinfo($pdfFile['name'], PATHINFO_FILENAME);
$uploadedName = 'uploaded_' . $timestamp . '.pdf';
$uploadedPath = '../../uploads/' . $uploadedName;

// Move uploaded file
if (!move_uploaded_file($pdfFile['tmp_name'], $uploadedPath)) {
    showError('Failed to save uploaded PDF file.');
}

// Professional PDF splitter using FPDI
function splitPDF($inputPath, $mode, $value, $originalName, $timestamp) {
    try {
        $results = [];
        
        // Get total page count
        $pdf = new Fpdi();
        $totalPages = $pdf->setSourceFile($inputPath);
        
        switch ($mode) {
            case 'all':
                // Split each page into separate file
                for ($i = 1; $i <= $totalPages; $i++) {
                    $pdf = new Fpdi();
                    $pdf->setSourceFile($inputPath);
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                    
                    $outputName = $originalName . "_page_{$i}_{$timestamp}.pdf";
                    $outputPath = "../../outputs/split/" . $outputName;
                    $pdf->Output($outputPath, 'F');
                    
                    $results[] = [
                        'name' => $outputName,
                        'path' => $outputPath,
                        'pages' => 1,
                        'size' => filesize($outputPath)
                    ];
                }
                break;
                
            case 'pages':
                // Split specific pages/ranges
                $pageRanges = explode(',', $value);
                $fileIndex = 1;
                
                foreach ($pageRanges as $range) {
                    $range = trim($range);
                    if (strpos($range, '-') !== false) {
                        // Range like "1-5"
                        list($start, $end) = explode('-', $range);
                        $start = max(1, intval($start));
                        $end = min($totalPages, intval($end));
                    } else {
                        // Single page
                        $start = $end = max(1, min($totalPages, intval($range)));
                    }
                    
                    if ($start <= $end && $start <= $totalPages) {
                        $pdf = new Fpdi();
                        $pdf->setSourceFile($inputPath);
                        
                        for ($i = $start; $i <= $end; $i++) {
                            $pdf->AddPage();
                            $templateId = $pdf->importPage($i);
                            $pdf->useTemplate($templateId);
                        }
                        
                        $outputName = $originalName . "_pages_{$start}-{$end}_{$timestamp}.pdf";
                        $outputPath = "../../outputs/split/" . $outputName;
                        $pdf->Output($outputPath, 'F');
                        
                        $results[] = [
                            'name' => $outputName,
                            'path' => $outputPath,
                            'pages' => $end - $start + 1,
                            'size' => filesize($outputPath)
                        ];
                    }
                    $fileIndex++;
                }
                break;
                
            case 'range':
                // Single range
                list($start, $end) = explode('-', $value);
                $start = max(1, intval($start));
                $end = min($totalPages, intval($end));
                
                if ($start <= $end) {
                    $pdf = new Fpdi();
                    $pdf->setSourceFile($inputPath);
                    
                    for ($i = $start; $i <= $end; $i++) {
                        $pdf->AddPage();
                        $templateId = $pdf->importPage($i);
                        $pdf->useTemplate($templateId);
                    }
                    
                    $outputName = $originalName . "_pages_{$start}-{$end}_{$timestamp}.pdf";
                    $outputPath = "../../outputs/split/" . $outputName;
                    $pdf->Output($outputPath, 'F');
                    
                    $results[] = [
                        'name' => $outputName,
                        'path' => $outputPath,
                        'pages' => $end - $start + 1,
                        'size' => filesize($outputPath)
                    ];
                }
                break;
                
            case 'chunks':
                // Split into chunks
                $chunkSize = max(1, intval($value));
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
                    $outputPath = "../../outputs/split/" . $outputName;
                    $pdf->Output($outputPath, 'F');
                    
                    $results[] = [
                        'name' => $outputName,
                        'path' => $outputPath,
                        'pages' => $endPage - $startPage + 1,
                        'size' => filesize($outputPath)
                    ];
                    
                    $chunkIndex++;
                }
                break;
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
            'size' => formatFileSize($file['size']),
            'download_url' => $file['path'],
            'view_url' => "../../shared/viewer/pdf-viewer.php?file=" . urlencode($file['name'])
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'files_created' => count($splitResult['results']),
        'total_files' => count($splitResult['results']),
        'files' => $files,
        'download_all_url' => $zipUrl
    ]);
    
} else {
    // Clean up uploaded file on error
    unlink($uploadedPath);
    showError('Failed to split PDF file: ' . ($splitResult['error'] ?? 'Unknown error occurred. Please ensure the file is a valid PDF and try again.'));
}
?>
