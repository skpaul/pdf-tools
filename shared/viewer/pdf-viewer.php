<?php
if (!isset($_GET['file'])) {
    header('Location: index.html');
    exit;
}

$filename = basename($_GET['file']);
$directory = '../../outputs/merged'; // default
if (isset($_GET['dir'])) {
    if ($_GET['dir'] === 'split') {
        $directory = '../../outputs/split';
    } elseif ($_GET['dir'] === 'inserted') {
        $directory = '../../outputs/inserted';
    } elseif ($_GET['dir'] === 'deleted') {
        $directory = '../../outputs/deleted';
    }
}
$filepath = $directory . '/' . $filename;

if (!file_exists($filepath)) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>File Not Found - PDF Merger</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; }
            .error { color: #e74c3c; background: #fdf2f2; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>File Not Found</h1>
            <div class='error'>The requested PDF file could not be found.</div>
            <a href='index.html' class='btn'>← Go Back</a>
        </div>
    </body>
    </html>";
    exit;
}

// Set headers for PDF display
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output the PDF file
readfile($filepath);
?>
