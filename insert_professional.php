<?php
// Professional PDF Inserter using FPDI library
require_once 'vendor/autoload.php';

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
if (!file_exists('inserted')) {
    mkdir('inserted', 0777, true);
}

function showError($message) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error - PDF Inserter</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .error { color: #e74c3c; background: #fdf2f2; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>PDF Inserter - Error</h1>
            <div class='error'>$message</div>
            <a href='insert.html' class='btn'>← Go Back</a>
        </div>
    </body>
    </html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Invalid request method. Please use the upload form.');
}

if (!isset($_FILES['base_pdf']) || !isset($_FILES['insert_pdf'])) {
    showError('Please select both PDF files.');
}

$basePdf = $_FILES['base_pdf'];
$insertPdf = $_FILES['insert_pdf'];
$insertMode = $_POST['insert_mode'] ?? 'position';
$insertPosition = $_POST['insert_position'] ?? '';
$pagesToInsert = $_POST['pages_to_insert'] ?? '';
$insertPositionPages = $_POST['insert_position_pages'] ?? '';

// Validate file uploads
if ($basePdf['error'] !== UPLOAD_ERR_OK || $insertPdf['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'File upload error: ';
    if ($basePdf['error'] === UPLOAD_ERR_FORM_SIZE || $insertPdf['error'] === UPLOAD_ERR_FORM_SIZE || 
        $basePdf['error'] === UPLOAD_ERR_INI_SIZE || $insertPdf['error'] === UPLOAD_ERR_INI_SIZE) {
        $errorMessage .= 'File size exceeds the maximum allowed limit (50 MB per file).';
    } else {
        $errorMessage .= 'Please try again with smaller files.';
    }
    showError($errorMessage);
}

// Additional file size validation (50 MB limit per file)
$maxFileSize = 50 * 1024 * 1024; // 50 MB in bytes
if ($basePdf['size'] > $maxFileSize || $insertPdf['size'] > $maxFileSize) {
    showError('File size too large. Maximum allowed size is 50 MB per file.');
}

// Validate file types
$allowedTypes = ['application/pdf'];
$basePdfType = mime_content_type($basePdf['tmp_name']);
$insertPdfType = mime_content_type($insertPdf['tmp_name']);

if (!in_array($basePdfType, $allowedTypes) || !in_array($insertPdfType, $allowedTypes)) {
    showError('Invalid file type. Please upload only PDF files.');
}

// Validate file extensions
$basePdfExt = strtolower(pathinfo($basePdf['name'], PATHINFO_EXTENSION));
$insertPdfExt = strtolower(pathinfo($insertPdf['name'], PATHINFO_EXTENSION));

if ($basePdfExt !== 'pdf' || $insertPdfExt !== 'pdf') {
    showError('Invalid file extension. Please upload only .pdf files.');
}

// Generate unique filenames
$timestamp = time();
$baseOriginalName = pathinfo($basePdf['name'], PATHINFO_FILENAME);
$insertOriginalName = pathinfo($insertPdf['name'], PATHINFO_FILENAME);
$basePdfName = 'base_' . $timestamp . '.pdf';
$insertPdfName = 'insert_' . $timestamp . '.pdf';
$resultName = 'inserted_' . $baseOriginalName . '_' . $timestamp . '.pdf';

$basePdfPath = 'uploads/' . $basePdfName;
$insertPdfPath = 'uploads/' . $insertPdfName;
$resultPath = 'inserted/' . $resultName;

// Move uploaded files
if (!move_uploaded_file($basePdf['tmp_name'], $basePdfPath)) {
    showError('Failed to save base PDF file.');
}

if (!move_uploaded_file($insertPdf['tmp_name'], $insertPdfPath)) {
    unlink($basePdfPath);
    showError('Failed to save insert PDF file.');
}

// Professional PDF inserter using FPDI
function insertPDF($basePath, $insertPath, $mode, $position, $pagesToInsert, $positionPages, $baseOriginalName, $insertOriginalName, $timestamp) {
    try {
        $pdf = new Fpdi();
        
        // Get page counts
        $baseTotalPages = $pdf->setSourceFile($basePath);
        $insertTotalPages = $pdf->setSourceFile($insertPath);
        
        $result = [
            'base_pages' => $baseTotalPages,
            'insert_pages' => $insertTotalPages,
            'mode' => $mode
        ];
        
        // Parse pages to insert for specific page mode
        $pagesToInsertArray = [];
        if ($mode === 'pages') {
            $ranges = explode(',', $pagesToInsert);
            foreach ($ranges as $range) {
                $range = trim($range);
                if (empty($range)) continue;
                
                if (strpos($range, '-') !== false) {
                    list($start, $end) = explode('-', $range);
                    $start = max(1, intval(trim($start)));
                    $end = min($insertTotalPages, intval(trim($end)));
                    for ($i = $start; $i <= $end; $i++) {
                        $pagesToInsertArray[] = $i;
                    }
                } else {
                    $pageNum = max(1, min($insertTotalPages, intval($range)));
                    $pagesToInsertArray[] = $pageNum;
                }
            }
            $result['pages_to_insert'] = $pagesToInsertArray;
            $result['inserted_pages'] = count($pagesToInsertArray);
        }
        
        // Determine insertion logic based on mode
        switch ($mode) {
            case 'position':
                $insertPos = max(1, min($baseTotalPages + 1, intval($position)));
                $result['insert_position'] = $insertPos;
                $result['inserted_pages'] = $insertTotalPages;
                
                // Add base PDF pages before insertion point
                $pdf->setSourceFile($basePath);
                for ($i = 1; $i < $insertPos; $i++) {
                    if ($i <= $baseTotalPages) {
                        $pdf->AddPage();
                        $templateId = $pdf->importPage($i);
                        $pdf->useTemplate($templateId);
                    }
                }
                
                // Add all pages from insert PDF
                $pdf->setSourceFile($insertPath);
                for ($i = 1; $i <= $insertTotalPages; $i++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                }
                
                // Add remaining base PDF pages after insertion point
                $pdf->setSourceFile($basePath);
                for ($i = $insertPos; $i <= $baseTotalPages; $i++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                }
                break;
                
            case 'pages':
                $insertPos = max(1, min($baseTotalPages + 1, intval($positionPages)));
                $result['insert_position'] = $insertPos;
                
                // Add base PDF pages before insertion point
                $pdf->setSourceFile($basePath);
                for ($i = 1; $i < $insertPos; $i++) {
                    if ($i <= $baseTotalPages) {
                        $pdf->AddPage();
                        $templateId = $pdf->importPage($i);
                        $pdf->useTemplate($templateId);
                    }
                }
                
                // Add specific pages from insert PDF
                $pdf->setSourceFile($insertPath);
                foreach ($pagesToInsertArray as $pageNum) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($pageNum);
                    $pdf->useTemplate($templateId);
                }
                
                // Add remaining base PDF pages after insertion point
                $pdf->setSourceFile($basePath);
                for ($i = $insertPos; $i <= $baseTotalPages; $i++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                }
                break;
                
            case 'append':
                $result['insert_position'] = $baseTotalPages + 1;
                $result['inserted_pages'] = $insertTotalPages;
                
                // Add all base PDF pages first
                $pdf->setSourceFile($basePath);
                for ($i = 1; $i <= $baseTotalPages; $i++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                }
                
                // Add all pages from insert PDF at the end
                $pdf->setSourceFile($insertPath);
                for ($i = 1; $i <= $insertTotalPages; $i++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                }
                break;
                
            case 'prepend':
                $result['insert_position'] = 1;
                $result['inserted_pages'] = $insertTotalPages;
                
                // Add all pages from insert PDF first
                $pdf->setSourceFile($insertPath);
                for ($i = 1; $i <= $insertTotalPages; $i++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                }
                
                // Add all base PDF pages after
                $pdf->setSourceFile($basePath);
                for ($i = 1; $i <= $baseTotalPages; $i++) {
                    $pdf->AddPage();
                    $templateId = $pdf->importPage($i);
                    $pdf->useTemplate($templateId);
                }
                break;
        }
        
        // Save the result
        $outputPath = 'inserted/' . 'inserted_' . $baseOriginalName . '_' . $timestamp . '.pdf';
        $pdf->Output($outputPath, 'F');
        
        $result['success'] = true;
        $result['output_file'] = basename($outputPath);
        $result['output_path'] = $outputPath;
        $result['total_pages'] = $baseTotalPages + ($result['inserted_pages'] ?? $insertTotalPages);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("PDF insert error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Validate insertion parameters
if ($insertMode === 'position' && (empty($insertPosition) || intval($insertPosition) < 1)) {
    unlink($basePdfPath);
    unlink($insertPdfPath);
    showError('Please specify a valid insertion position (1 or greater).');
}

if ($insertMode === 'pages') {
    if (empty($pagesToInsert)) {
        unlink($basePdfPath);
        unlink($insertPdfPath);
        showError('Please specify which pages to insert (e.g., "1,3-5").');
    }
    if (empty($insertPositionPages) || intval($insertPositionPages) < 1) {
        unlink($basePdfPath);
        unlink($insertPdfPath);
        showError('Please specify a valid insertion position.');
    }
}

// Attempt to insert PDF
$insertResult = insertPDF($basePdfPath, $insertPdfPath, $insertMode, $insertPosition, $pagesToInsert, $insertPositionPages, $baseOriginalName, $insertOriginalName, $timestamp);

if ($insertResult['success']) {
    $fileSize = filesize($insertResult['output_path']);
    $fileSizeMB = round($fileSize / (1024 * 1024), 2);
    
    // Success page
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Success - Professional PDF Inserter</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
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
                background: #f4f0f7;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #9b59b6;
            }
            .stat-number {
                font-size: 2rem;
                font-weight: bold;
                color: #9b59b6;
            }
            .file-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                border-left: 5px solid #9b59b6;
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
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>📝 Professional PDF Inserter Success!</h1>
            <div class='success'>
                <h2>Pages have been successfully inserted!</h2>
                <p>The PDF insertion operation has been completed with professional quality.</p>
            </div>
            
            <div class='stats'>
                <div class='stat-box'>
                    <div class='stat-number'>" . $insertResult['base_pages'] . "</div>
                    <div>Base PDF<br>Pages</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>" . ($insertResult['inserted_pages'] ?? $insertResult['insert_pages']) . "</div>
                    <div>Pages<br>Inserted</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>" . $insertResult['total_pages'] . "</div>
                    <div>Total Pages<br>in Result</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>{$fileSizeMB}</div>
                    <div>File Size<br>(MB)</div>
                </div>
            </div>
            
            <div class='operation-details'>
                <h3>📋 Operation Details</h3>
                <p><strong>Base Document:</strong> " . htmlspecialchars($basePdf['name']) . " (" . $insertResult['base_pages'] . " pages)</p>
                <p><strong>Insert Document:</strong> " . htmlspecialchars($insertPdf['name']) . " (" . $insertResult['insert_pages'] . " pages)</p>
                <p><strong>Insert Method:</strong> " . ucfirst($insertResult['mode']) . "</p>";
                
    if (isset($insertResult['insert_position'])) {
        echo "<p><strong>Insert Position:</strong> After page " . ($insertResult['insert_position'] - 1) . "</p>";
    }
    
    if (isset($insertResult['pages_to_insert'])) {
        echo "<p><strong>Pages Inserted:</strong> " . implode(', ', $insertResult['pages_to_insert']) . "</p>";
    }
                
    echo "      <p><strong>Created:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Library:</strong> FPDI Professional PDF Library</p>
            </div>
            
            <div class='file-info'>
                <h3>📄 Result File Information</h3>
                <p><strong>Output File:</strong> " . $insertResult['output_file'] . "</p>
                <p><strong>Total Pages:</strong> " . $insertResult['total_pages'] . "</p>
                <p><strong>File Size:</strong> {$fileSizeMB} MB</p>
            </div>
            
            <div class='pdf-preview'>
                <h3>📖 PDF Preview</h3>
                <iframe src='" . $insertResult['output_path'] . "' width='100%' height='500px' style='border: none;'>
                    <p>Your browser doesn't support PDF preview. Please download the file to view it.</p>
                </iframe>
            </div>
            
            <div style='margin-top: 30px;'>
                <a href='" . $insertResult['output_path'] . "' class='btn' download='" . $insertResult['output_file'] . "'>
                    📥 Download Result PDF
                </a>
                <a href='view_pdf.php?file=" . urlencode($insertResult['output_file']) . "&dir=inserted' class='btn btn-secondary' target='_blank'>
                    👁️ View in Browser
                </a>
                <a href='insert.html' class='btn btn-secondary'>
                    📝 Insert More Pages
                </a>
            </div>
        </div>
    </body>
    </html>";
    
    // Clean up uploaded files after successful insertion
    unlink($basePdfPath);
    unlink($insertPdfPath);
    
} else {
    // Clean up uploaded files on error
    unlink($basePdfPath);
    unlink($insertPdfPath);
    showError('Failed to insert PDF pages: ' . ($insertResult['error'] ?? 'Unknown error occurred. Please ensure both files are valid PDFs and try again.'));
}
?>
