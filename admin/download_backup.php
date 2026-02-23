<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$name = isset($_GET['name']) ? $_GET['name'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (!preg_match('/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}$/', $name)) {
    header('Location: export.php');
    exit();
}

$baseDir = realpath('../backup/' . $name);
if ($baseDir === false || strpos($baseDir, realpath('../backup')) !== 0) {
    header('Location: export.php');
    exit();
}

if ($file === 'sql') {
    $sqlPath = $baseDir . '/database_backup.sql';
    if (!is_file($sqlPath)) {
        header('Location: export.php');
        exit();
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $name . '_database.sql"');
    header('Content-Length: ' . filesize($sqlPath));
    readfile($sqlPath);
    exit();
}

if ($file === 'zip' && class_exists('ZipArchive')) {
    $zipPath = sys_get_temp_dir() . '/' . $name . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        header('Location: export.php?error=zip');
        exit();
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = $name . '/' . substr($filePath, strlen($baseDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '.zip"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit();
}

header('Location: export.php');
