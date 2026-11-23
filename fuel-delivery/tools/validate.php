<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$errors = 0;
$count = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $output = [];
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $status);
    $count++;
    if ($status !== 0) {
        $errors++;
        echo implode(PHP_EOL,$output) . PHP_EOL;
    }
}
echo $count . ' PHP files checked; ' . $errors . ' syntax errors.' . PHP_EOL;
foreach (['pdo_mysql','mbstring','fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        echo 'Missing PHP extension: ' . $extension . PHP_EOL;
        $errors++;
    }
}
exit($errors ? 1 : 0);
