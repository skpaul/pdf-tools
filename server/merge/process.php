<?php
// Professional PDF Merger using FPDI library
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
if (!file_exists('../../outputs/merged')) {
    mkdir('../../outputs/merged', 0777, true);
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

if (!isset($_FILES['pdf1']) || !isset($_FILES['pdf2'])) {
    showError('Please select both PDF files.');
}

$pdf1 = $_FILES['pdf1'];
$pdf2 = $_FILES['pdf2'];

// Validate file uploads
if ($pdf1['error'] !== UPLOAD_ERR_OK || $pdf2['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'File upload error: ';
    if ($pdf1['error'] === UPLOAD_ERR_FORM_SIZE || $pdf2['error'] === UPLOAD_ERR_FORM_SIZE || 
        $pdf1['error'] === UPLOAD_ERR_INI_SIZE || $pdf2['error'] === UPLOAD_ERR_INI_SIZE) {
        $errorMessage .= 'File size exceeds the maximum allowed limit (50 MB per file).';
    } else {
        $errorMessage .= 'Please try again with smaller files.';
    }
    showError($errorMessage);
}

// Additional file size validation (50 MB limit per file)
$maxFileSize = 50 * 1024 * 1024; // 50 MB in bytes
if ($pdf1['size'] > $maxFileSize || $pdf2['size'] > $maxFileSize) {
    showError('File size too large. Maximum allowed size is 50 MB per file.');
}

// Validate file types
$allowedTypes = ['application/pdf'];
$pdf1Type = mime_content_type($pdf1['tmp_name']);
$pdf2Type = mime_content_type($pdf2['tmp_name']);

if (!in_array($pdf1Type, $allowedTypes) || !in_array($pdf2Type, $allowedTypes)) {
    showError('Invalid file type. Please upload only PDF files.');
}

// Validate file extensions
$pdf1Ext = strtolower(pathinfo($pdf1['name'], PATHINFO_EXTENSION));
$pdf2Ext = strtolower(pathinfo($pdf2['name'], PATHINFO_EXTENSION));

if ($pdf1Ext !== 'pdf' || $pdf2Ext !== 'pdf') {
    showError('Invalid file extension. Please upload only .pdf files.');
}

// Generate unique filenames
$timestamp = time();
$pdf1Name = 'pdf1_' . $timestamp . '.pdf';
$pdf2Name = 'pdf2_' . $timestamp . '.pdf';
$mergedName = 'merged_' . $timestamp . '.pdf';

$pdf1Path = '../../uploads/' . $pdf1Name;
$pdf2Path = '../../uploads/' . $pdf2Name;
$mergedPath = '../../outputs/merged/' . $mergedName;

// Move uploaded files
if (!move_uploaded_file($pdf1['tmp_name'], $pdf1Path)) {
    showError('Failed to save first PDF file.');
}

if (!move_uploaded_file($pdf2['tmp_name'], $pdf2Path)) {
    unlink($pdf1Path); // Clean up first file
    showError('Failed to save second PDF file.');
}

// Professional PDF merger using FPDI
function mergePDFs($pdf1Path, $pdf2Path, $outputPath) {
    try {
        // Create new PDF instance
        $pdf = new Fpdi();
        
        // Add pages from first PDF
        $pageCount1 = $pdf->setSourceFile($pdf1Path);
        for ($i = 1; $i <= $pageCount1; $i++) {
            $pdf->AddPage();
            $templateId = $pdf->importPage($i);
            $pdf->useTemplate($templateId);
        }
        
        // Add pages from second PDF
        $pageCount2 = $pdf->setSourceFile($pdf2Path);
        for ($i = 1; $i <= $pageCount2; $i++) {
            $pdf->AddPage();
            $templateId = $pdf->importPage($i);
            $pdf->useTemplate($templateId);
        }
        
        // Save merged PDF
        $pdf->Output($outputPath, 'F');
        
        return [
            'success' => true,
            'pages_pdf1' => $pageCount1,
            'pages_pdf2' => $pageCount2,
            'total_pages' => $pageCount1 + $pageCount2
        ];
        
    } catch (Exception $e) {
        error_log("PDF merge error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Attempt to merge PDFs
$mergeResult = mergePDFs($pdf1Path, $pdf2Path, $mergedPath);

if ($mergeResult['success']) {
    $fileSize = filesize($mergedPath);
    $fileSizeMB = round($fileSize / (1024 * 1024), 2);
    
    // Clean up uploaded files after successful merge
    unlink($pdf1Path);
    unlink($pdf2Path);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'filename' => $mergedName,
        'total_pages' => $mergeResult['total_pages'],
        'pages_pdf1' => $mergeResult['pages_pdf1'],
        'pages_pdf2' => $mergeResult['pages_pdf2'],
        'file_size' => $fileSizeMB . ' MB',
        'download_url' => $mergedPath,
        'view_url' => '../../shared/viewer/pdf-viewer.php?file=' . urlencode($mergedName)
    ]);
    
} else {
    // Clean up uploaded files on error
    unlink($pdf1Path);
    unlink($pdf2Path);
    showError('Failed to merge PDF files: ' . ($mergeResult['error'] ?? 'Unknown error occurred. Please ensure both files are valid PDFs and try again.'));
}
?>
