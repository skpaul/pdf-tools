<?php
// Temporary diagnostic script to check PHP upload limits
// DELETE THIS FILE after checking your settings for security

echo "<h2>PHP Upload Configuration</h2>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Bytes</th></tr>";

$settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'memory_limit' => ini_get('memory_limit'),
    'max_file_uploads' => ini_get('max_file_uploads')
];

foreach ($settings as $setting => $value) {
    $bytes = '';
    if (in_array($setting, ['upload_max_filesize', 'post_max_size', 'memory_limit'])) {
        $bytes = return_bytes($value);
    }
    echo "<tr><td>$setting</td><td>$value</td><td>$bytes</td></tr>";
}

echo "</table>";

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return number_format($val) . ' bytes';
}

echo "<h3>Your 4MB file should be: " . number_format(4 * 1024 * 1024) . " bytes</h3>";
echo "<p><strong>If upload_max_filesize or post_max_size is less than 4MB, that's your problem!</strong></p>";
?>
