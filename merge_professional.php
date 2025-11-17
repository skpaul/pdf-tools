<?php
// Professional PDF Merger using FPDI library
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
if (!file_exists('merged')) {
    mkdir('merged', 0777, true);
}

function showError($message) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error - PDF Merger</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .error { color: #e74c3c; background: #fdf2f2; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>PDF Merger - Error</h1>
            <div class='error'>$message</div>
            <a href='index.html' class='btn'>← Go Back</a>
        </div>
    </body>
    </html>";
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
        $errorMessage .= 'File size exceeds the maximum allowed limit (5 MB per file).';
    } else {
        $errorMessage .= 'Please try again with smaller files.';
    }
    showError($errorMessage);
}

// Additional file size validation (5 MB limit per file)
$maxFileSize = 5 * 1024 * 1024; // 5 MB in bytes
if ($pdf1['size'] > $maxFileSize || $pdf2['size'] > $maxFileSize) {
    showError('File size too large. Maximum allowed size is 5 MB per file.');
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

$pdf1Path = 'uploads/' . $pdf1Name;
$pdf2Path = 'uploads/' . $pdf2Name;
$mergedPath = 'merged/' . $mergedName;

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
    
    // Success page
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Success - Professional PDF Merger</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                margin: 0;
            }
            .container { 
                max-width: 800px; 
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
            .file-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                border-left: 5px solid #667eea;
            }
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            .stat-box {
                background: #e8f4fd;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #3498db;
            }
            .stat-number {
                font-size: 2rem;
                font-weight: bold;
                color: #3498db;
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
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🎉 Professional PDF Merger Success!</h1>
            <div class='success'>
                <h2>Your PDFs have been professionally merged!</h2>
                <p>All pages from both documents have been combined into a single PDF file.</p>
            </div>
            
            <div class='stats'>
                <div class='stat-box'>
                    <div class='stat-number'>" . $mergeResult['pages_pdf1'] . "</div>
                    <div>Pages from<br>First PDF</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>" . $mergeResult['pages_pdf2'] . "</div>
                    <div>Pages from<br>Second PDF</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>" . $mergeResult['total_pages'] . "</div>
                    <div>Total Pages<br>in Merged PDF</div>
                </div>
                <div class='stat-box'>
                    <div class='stat-number'>{$fileSizeMB}</div>
                    <div>File Size<br>(MB)</div>
                </div>
            </div>
            
            <div class='file-info'>
                <h3>📄 Merged File Information</h3>
                <p><strong>Input Files:</strong> " . htmlspecialchars($pdf1['name']) . " + " . htmlspecialchars($pdf2['name']) . "</p>
                <p><strong>Output File:</strong> $mergedName</p>
                <p><strong>Merger Type:</strong> Professional page-by-page combination</p>
                <p><strong>Created:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Library:</strong> FPDI Professional PDF Library</p>
            </div>
            
            <div class='pdf-preview'>
                <h3>📖 PDF Preview</h3>
                <iframe src='$mergedPath' width='100%' height='500px' style='border: none;'>
                    <p>Your browser doesn't support PDF preview. Please download the file to view it.</p>
                </iframe>
            </div>
            
            <div style='margin-top: 30px;'>
                <a href='$mergedPath' class='btn' download='$mergedName'>
                    📥 Download Merged PDF
                </a>
                <a href='view_pdf.php?file=$mergedName' class='btn btn-secondary' target='_blank'>
                    👁️ View in Browser
                </a>
                <a href='index.html' class='btn btn-secondary'>
                    🔄 Merge More PDFs
                </a>
            </div>
        </div>
    </body>
    </html>";
    
    // Clean up uploaded files after successful merge
    unlink($pdf1Path);
    unlink($pdf2Path);
    
} else {
    // Clean up uploaded files on error
    unlink($pdf1Path);
    unlink($pdf2Path);
    showError('Failed to merge PDF files: ' . ($mergeResult['error'] ?? 'Unknown error occurred. Please ensure both files are valid PDFs and try again.'));
}
?>
