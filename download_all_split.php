<?php
// Download all split PDF files as ZIP (with fallback for systems without ZipArchive)
if (!isset($_GET['timestamp'])) {
    header('Location: split.html');
    exit;
}

$timestamp = $_GET['timestamp'];
$splitDir = 'split/';

// Find all files with this timestamp
$files = glob($splitDir . "*_{$timestamp}.pdf");

if (empty($files)) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Files Not Found - PDF Splitter</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; }
            .error { color: #e74c3c; background: #fdf2f2; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Files Not Found</h1>
            <div class='error'>The split PDF files could not be found or may have been cleaned up.</div>
            <a href='split.html' class='btn'>← Split Another PDF</a>
        </div>
    </body>
    </html>";
    exit;
}

// Check if ZipArchive is available
if (class_exists('ZipArchive')) {
    // Use ZipArchive if available
    $zipFilename = 'split_pdfs_' . $timestamp . '.zip';
    $zip = new ZipArchive();
    $zipPath = sys_get_temp_dir() . '/' . $zipFilename;

    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        // Fall back to alternative method if ZIP creation fails
        showAlternativeDownload($files, $timestamp);
        exit;
    }

    // Add files to ZIP
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }

    $zip->close();

    // Set headers for download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Output the ZIP file and clean up
    readfile($zipPath);
    unlink($zipPath);
} else {
    // Fallback: Show download page with individual file links
    showAlternativeDownload($files, $timestamp);
}

function showAlternativeDownload($files, $timestamp) {
    $totalSize = 0;
    foreach ($files as $file) {
        $totalSize += filesize($file);
    }
    $totalSizeMB = round($totalSize / (1024 * 1024), 2);
    
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Download Split Files - PDF Splitter</title>
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
                max-width: 800px; 
                background: white; 
                padding: 40px; 
                border-radius: 15px; 
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
            }
            .info { 
                color: #f39c12; 
                background: #fef9e7; 
                padding: 20px; 
                border-radius: 10px; 
                margin-bottom: 30px;
                border: 2px solid #f39c12;
            }
            .files-list {
                text-align: left;
                margin: 20px 0;
            }
            .file-item {
                background: #f8f9fa;
                padding: 15px;
                margin: 10px 0;
                border-radius: 8px;
                border-left: 4px solid #e74c3c;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .file-info {
                flex: 1;
            }
            .file-name {
                font-weight: 600;
                color: #333;
                margin-bottom: 5px;
            }
            .file-size {
                color: #666;
                font-size: 0.9rem;
            }
            .btn { 
                display: inline-block; 
                padding: 10px 20px; 
                background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 5px;
                font-weight: 500;
            }
            .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
            }
            .btn-secondary { 
                background: linear-gradient(135deg, #3498db 0%, #5dade2 100%); 
            }
            .download-all-btn {
                background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
                padding: 15px 30px;
                font-size: 1rem;
                margin: 20px 10px;
                cursor: pointer;
                border: none;
            }
            h1 { color: #333; margin-bottom: 20px; }
            .stats {
                background: #e8f4fd;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #3498db;
            }
        </style>
        <script>
            function downloadAll() {
                const files = " . json_encode(array_map('basename', $files)) . ";
                let downloadCount = 0;
                
                function downloadNext() {
                    if (downloadCount < files.length) {
                        const link = document.createElement('a');
                        link.href = 'split/' + files[downloadCount];
                        link.download = files[downloadCount];
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        downloadCount++;
                        setTimeout(downloadNext, 1000); // 1 second delay between downloads
                    }
                }
                
                downloadNext();
            }
        </script>
    </head>
    <body>
        <div class='container'>
            <h1>📦 Download Split Files</h1>
            <div class='info'>
                <h2>ZIP Archive Not Available</h2>
                <p>Your server doesn't have ZIP support enabled. You can download each file individually or use the 'Download All' button below.</p>
            </div>
            
            <div class='stats'>
                <strong>Total Files:</strong> " . count($files) . " | 
                <strong>Total Size:</strong> {$totalSizeMB} MB
            </div>
            
            <div class='files-list'>";
    
    foreach ($files as $file) {
        $filename = basename($file);
        $fileSize = round(filesize($file) / (1024 * 1024), 2);
        echo "<div class='file-item'>
                <div class='file-info'>
                    <div class='file-name'>{$filename}</div>
                    <div class='file-size'>{$fileSize} MB</div>
                </div>
                <a href='{$file}' class='btn' download='{$filename}'>📥 Download</a>
            </div>";
    }
    
    echo "    </div>
            
            <div style='margin-top: 30px;'>
                <button onclick='downloadAll()' class='btn download-all-btn'>
                    📥 Download All Files (Sequential)
                </button>
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
}

// Optional: Clean up original split files after download
// Uncomment the lines below if you want to automatically delete split files after download
/*
foreach ($files as $file) {
    unlink($file);
}
*/
?>
