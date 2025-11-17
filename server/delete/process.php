<?php
// FORCE PHP configuration settings - multiple methods
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '256M');

// Try alternative configuration method
@ini_alter('upload_max_filesize', '50M');
@ini_alter('post_max_size', '100M');

// Check if we can detect the actual issue
$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');
error_log("Current upload_max_filesize: $upload_max, post_max_size: $post_max");

// Professional PDF Page Deleter using FPDI library
require_once '../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Create necessary directories
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}
if (!file_exists('deleted')) {
    mkdir('deleted', 0777, true);
}

function showError($message) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error - PDF Page Deleter</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .error { color: #e74c3c; background: #fdf2f2; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>PDF Page Deleter - Error</h1>
            <div class='error'>$message</div>
            <a href='delete.html' class='btn'>← Go Back</a>
        </div>
    </body>
    </html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Invalid request method. Please use the upload form.');
}

if (!isset($_FILES['pdf_file'])) {
    showError('Please select a PDF file.');
}

$pdfFile = $_FILES['pdf_file'];
$deleteMode = $_POST['delete_mode'] ?? 'pages';
$pagesToDelete = $_POST['pages_to_delete'] ?? '';
$startPage = $_POST['start_page'] ?? '';
$endPage = $_POST['end_page'] ?? '';
$pagesToKeep = $_POST['pages_to_keep'] ?? '';
$deleteFirst = $_POST['delete_first'] ?? '';
$deleteLast = $_POST['delete_last'] ?? '';

// Validate file upload
if ($pdfFile['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'File upload error: ';
    switch ($pdfFile['error']) {
        case UPLOAD_ERR_FORM_SIZE:
            $errorMessage .= 'File size exceeds HTML form limit.';
            break;
        case UPLOAD_ERR_INI_SIZE:
            $errorMessage .= 'File size exceeds PHP configuration limit (upload_max_filesize: ' . ini_get('upload_max_filesize') . ').';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMessage .= 'File was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMessage .= 'No file was uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMessage .= 'Missing temporary folder.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMessage .= 'Failed to write file to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMessage .= 'Upload stopped by PHP extension.';
            break;
        default:
            $errorMessage .= 'Unknown upload error.';
    }
    $errorMessage .= ' (Error code: ' . $pdfFile['error'] . ', File size: ' . number_format($pdfFile['size']) . ' bytes, Current limits: upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . ')';
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
$resultName = 'deleted_' . $originalName . '_' . $timestamp . '.pdf';

$uploadedPath = 'uploads/' . $uploadedName;
$resultPath = 'deleted/' . $resultName;

// Move uploaded file
if (!move_uploaded_file($pdfFile['tmp_name'], $uploadedPath)) {
    showError('Failed to save uploaded PDF file.');
}

// Professional PDF page deleter using FPDI
function deletePagesFromPDF($inputPath, $mode, $pagesToDelete, $startPage, $endPage, $pagesToKeep, $deleteFirst, $deleteLast, $originalName, $timestamp) {
    try {
        $pdf = new Fpdi();
        
        // Get total page count
        $totalPages = $pdf->setSourceFile($inputPath);
        
        $result = [
            'original_pages' => $totalPages,
            'mode' => $mode
        ];
        
        // Determine which pages to keep based on the deletion mode
        $pagesToKeepArray = [];
        
        switch ($mode) {
            case 'pages':
                // Parse pages to delete and create keep array
                $pagesToDeleteArray = [];
                $ranges = explode(',', $pagesToDelete);
                foreach ($ranges as $range) {
                    $range = trim($range);
                    if (empty($range)) continue;
                    
                    if (strpos($range, '-') !== false) {
                        list($start, $end) = explode('-', $range);
                        $start = max(1, intval(trim($start)));
                        $end = min($totalPages, intval(trim($end)));
                        for ($i = $start; $i <= $end; $i++) {
                            $pagesToDeleteArray[] = $i;
                        }
                    } else {
                        $pageNum = max(1, min($totalPages, intval($range)));
                        $pagesToDeleteArray[] = $pageNum;
                    }
                }
                
                // Create keep array (all pages except deleted ones)
                for ($i = 1; $i <= $totalPages; $i++) {
                    if (!in_array($i, $pagesToDeleteArray)) {
                        $pagesToKeepArray[] = $i;
                    }
                }
                
                $result['pages_deleted'] = $pagesToDeleteArray;
                $result['deleted_count'] = count($pagesToDeleteArray);
                break;
                
            case 'range':
                $start = max(1, intval($startPage));
                $end = min($totalPages, intval($endPage));
                
                // Keep pages before and after the range
                for ($i = 1; $i <= $totalPages; $i++) {
                    if ($i < $start || $i > $end) {
                        $pagesToKeepArray[] = $i;
                    }
                }
                
                $result['deleted_range'] = [$start, $end];
                $result['deleted_count'] = $end - $start + 1;
                break;
                
            case 'keep':
                // Parse pages to keep
                $ranges = explode(',', $pagesToKeep);
                foreach ($ranges as $range) {
                    $range = trim($range);
                    if (empty($range)) continue;
                    
                    if (strpos($range, '-') !== false) {
                        list($start, $end) = explode('-', $range);
                        $start = max(1, intval(trim($start)));
                        $end = min($totalPages, intval(trim($end)));
                        for ($i = $start; $i <= $end; $i++) {
                            $pagesToKeepArray[] = $i;
                        }
                    } else {
                        $pageNum = max(1, min($totalPages, intval($range)));
                        $pagesToKeepArray[] = $pageNum;
                    }
                }
                
                // Remove duplicates and sort
                $pagesToKeepArray = array_unique($pagesToKeepArray);
                sort($pagesToKeepArray);
                
                $result['pages_kept'] = $pagesToKeepArray;
                $result['deleted_count'] = $totalPages - count($pagesToKeepArray);
                break;
                
            case 'first-last':
                $deleteFirstNum = max(0, intval($deleteFirst));
                $deleteLastNum = max(0, intval($deleteLast));
                
                // Keep pages in the middle
                for ($i = 1; $i <= $totalPages; $i++) {
                    if ($i > $deleteFirstNum && $i <= ($totalPages - $deleteLastNum)) {
                        $pagesToKeepArray[] = $i;
                    }
                }
                
                $result['deleted_first'] = $deleteFirstNum;
                $result['deleted_last'] = $deleteLastNum;
                $result['deleted_count'] = $deleteFirstNum + $deleteLastNum;
                break;
        }
        
        // Validate that we have pages to keep
        if (empty($pagesToKeepArray)) {
            return ['success' => false, 'error' => 'All pages would be deleted. Please adjust your selection.'];
        }
        
        if (count($pagesToKeepArray) >= $totalPages) {
            return ['success' => false, 'error' => 'No pages would be deleted with this selection.'];
        }
        
        // Create new PDF with kept pages
        $outputPdf = new Fpdi();
        $outputPdf->setSourceFile($inputPath);
        
        foreach ($pagesToKeepArray as $pageNum) {
            $outputPdf->AddPage();
            $templateId = $outputPdf->importPage($pageNum);
            $outputPdf->useTemplate($templateId);
        }
        
        // Save the result
        $outputPath = 'deleted/' . 'deleted_' . $originalName . '_' . $timestamp . '.pdf';
        $outputPdf->Output($outputPath, 'F');
        
        $result['success'] = true;
        $result['output_file'] = basename($outputPath);
        $result['output_path'] = $outputPath;
        $result['remaining_pages'] = count($pagesToKeepArray);
        $result['pages_kept_array'] = $pagesToKeepArray;
        
        return $result;
        
    } catch (Exception $e) {
        error_log("PDF delete error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Validate deletion parameters
if ($deleteMode === 'pages' && empty($pagesToDelete)) {
    unlink($uploadedPath);
    showError('Please specify which pages to delete (e.g., "1,3,5-7").');
}

if ($deleteMode === 'range') {
    if (empty($startPage) || empty($endPage) || intval($startPage) < 1 || intval($endPage) < 1) {
        unlink($uploadedPath);
        showError('Please specify valid start and end page numbers.');
    }
    if (intval($startPage) > intval($endPage)) {
        unlink($uploadedPath);
        showError('Start page cannot be greater than end page.');
    }
}

if ($deleteMode === 'keep' && empty($pagesToKeep)) {
    unlink($uploadedPath);
    showError('Please specify which pages to keep (e.g., "1,3,5-7").');
}

if ($deleteMode === 'first-last') {
    $deleteFirstNum = intval($deleteFirst);
    $deleteLastNum = intval($deleteLast);
    if ($deleteFirstNum < 0 || $deleteLastNum < 0) {
        unlink($uploadedPath);
        showError('Page numbers cannot be negative.');
    }
    if ($deleteFirstNum === 0 && $deleteLastNum === 0) {
        unlink($uploadedPath);
        showError('Please specify at least one page to delete from the beginning or end.');
    }
}

// Attempt to delete pages
$deleteResult = deletePagesFromPDF($uploadedPath, $deleteMode, $pagesToDelete, $startPage, $endPage, $pagesToKeep, $deleteFirst, $deleteLast, $originalName, $timestamp);

if ($deleteResult['success']) {
    $fileSize = filesize($deleteResult['output_path']);
    $fileSizeMB = round($fileSize / (1024 * 1024), 2);
    
    // Success page
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Success - Professional PDF Page Deleter</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                margin: 0;
            }
            .container { 
                max-width: 900px; 
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
                background: #fdf6f0;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #e67e22;
            }
            .stat-number {
                font-size: 2rem;
                font-weight: bold;
                color: #e67e22;
            }
            .file-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                border-left: 5px solid #e67e22;
            }
            .btn { 
                display: inline-block; 
                padding: 15px 30px; 
                background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); 
                color: white; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 10px;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
            }
            .btn-secondary { 
                background: linear-gradient(135deg, #3498db 0%, #5dade2 100%); 
            }
            .btn-secondary:hover {
                box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
            }
            h1 { color: #333; margin-bottom: 20px; }
            .pdf-preview {
                margin: 20px 0;
                border: 2px solid #ddd;
                border-radius: 10px;
                overflow: hidden;
                background: #f9f9f9;
            }
            .operation-details {
                background: #e8f4fd;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #3498db;
                text-align: left;
            }
            .pages-summary {
                background: #fff3cd;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #ffc107;
                text-align: left;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🗑️ PDF Page Deletion Success!</h1>
            <div class='success'>
                <h2>Pages have been successfully deleted!</h2>
                <p>The specified pages have been removed from your PDF document.</p>
            </div>
            
            <div class='stats'>
                <div class='stat-box'>
                    <div class='stat-number'>" . $deleteResult['original_pages'] . "</div>
                    <div>Original<br>Pages</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>" . $deleteResult['deleted_count'] . "</div>
                    <div>Pages<br>Deleted</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>" . $deleteResult['remaining_pages'] . "</div>
                    <div>Pages<br>Remaining</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>{$fileSizeMB}</div>
                    <div>File Size<br>(MB)</div>
                </div>
            </div>
            
            <div class='operation-details'>
                <h3>📋 Operation Details</h3>
                <p><strong>Original Document:</strong> " . htmlspecialchars($pdfFile['name']) . " (" . $deleteResult['original_pages'] . " pages)</p>
                <p><strong>Deletion Method:</strong> " . ucfirst($deleteResult['mode']) . "</p>";
                
    // Add specific details based on mode
    switch ($deleteResult['mode']) {
        case 'pages':
            if (isset($deleteResult['pages_deleted'])) {
                echo "<p><strong>Pages Deleted:</strong> " . implode(', ', $deleteResult['pages_deleted']) . "</p>";
            }
            break;
        case 'range':
            if (isset($deleteResult['deleted_range'])) {
                echo "<p><strong>Deleted Range:</strong> Pages " . $deleteResult['deleted_range'][0] . " to " . $deleteResult['deleted_range'][1] . "</p>";
            }
            break;
        case 'keep':
            if (isset($deleteResult['pages_kept'])) {
                echo "<p><strong>Pages Kept:</strong> " . implode(', ', $deleteResult['pages_kept']) . "</p>";
            }
            break;
        case 'first-last':
            if (isset($deleteResult['deleted_first']) && isset($deleteResult['deleted_last'])) {
                echo "<p><strong>Deleted:</strong> First " . $deleteResult['deleted_first'] . " pages, Last " . $deleteResult['deleted_last'] . " pages</p>";
            }
            break;
    }
                
    echo "      <p><strong>Created:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Library:</strong> FPDI Professional PDF Library</p>
            </div>
            
            <div class='pages-summary'>
                <h3>📄 Pages Summary</h3>
                <p><strong>Remaining Pages:</strong> " . implode(', ', array_slice($deleteResult['pages_kept_array'], 0, 20)) . 
                (count($deleteResult['pages_kept_array']) > 20 ? '...' : '') . "</p>
                <p><strong>Total Remaining:</strong> " . $deleteResult['remaining_pages'] . " pages</p>
            </div>
            
            <div class='file-info'>
                <h3>📄 Result File Information</h3>
                <p><strong>Output File:</strong> " . $deleteResult['output_file'] . "</p>
                <p><strong>Pages Count:</strong> " . $deleteResult['remaining_pages'] . "</p>
                <p><strong>File Size:</strong> {$fileSizeMB} MB</p>
            </div>
            
            <div class='pdf-preview'>
                <h3>📖 PDF Preview</h3>
                <iframe src='" . $deleteResult['output_path'] . "' width='100%' height='500px' style='border: none;'>
                    <p>Your browser doesn't support PDF preview. Please download the file to view it.</p>
                </iframe>
            </div>
            
            <div style='margin-top: 30px;'>
                <a href='" . $deleteResult['output_path'] . "' class='btn' download='" . $deleteResult['output_file'] . "'>
                    📥 Download Result PDF
                </a>
                <a href='view_pdf.php?file=" . urlencode($deleteResult['output_file']) . "&dir=deleted' class='btn btn-secondary' target='_blank'>
                    👁️ View in Browser
                </a>
                <a href='delete.html' class='btn btn-secondary'>
                    🗑️ Delete More Pages
                </a>
            </div>
        </div>
    </body>
    </html>";
    
    // Clean up uploaded file after successful deletion
    unlink($uploadedPath);
    
} else {
    // Clean up uploaded file on error
    unlink($uploadedPath);
    showError('Failed to delete PDF pages: ' . ($deleteResult['error'] ?? 'Unknown error occurred. Please ensure the file is a valid PDF and try again.'));
}
?>
