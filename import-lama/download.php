<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/archive_helper.php';
require_login();

$fileId = (int)($_GET['id'] ?? 0);
if ($fileId <= 0) {
    http_response_code(400);
    exit('File tidak valid.');
}

$st = $pdo->prepare('SELECT f.*, b.nama_batch, b.created_at AS batch_created_at, b.total_file FROM import_file f INNER JOIN import_batch b ON b.id=f.batch_id WHERE f.id=?');
$st->execute([$fileId]);
$file = $st->fetch();

if (!$file) {
    http_response_code(404);
    exit('Dokumen import tidak ditemukan.');
}

$batch = [
    'id' => $file['batch_id'],
    'created_at' => $file['batch_created_at'],
    'total_file' => $file['total_file'],
];

$archive = resolve_import_archive($pdo, $batch);
if (!$archive || !is_file($archive)) {
    http_response_code(404);
    exit('Arsip ZIP sumber tidak ditemukan.');
}

$zip = new ZipArchive();
if ($zip->open($archive) !== true) {
    http_response_code(500);
    exit('Arsip ZIP tidak dapat dibuka.');
}

$entryName = (string)$file['relative_path'];
$index = $zip->locateName($entryName, ZipArchive::FL_NOCASE);

if ($index === false) {
    $zip->close();
    http_response_code(404);
    exit('Dokumen tidak ditemukan di dalam ZIP.');
}

$stat = $zip->statIndex($index);
$stream = $zip->getStream($entryName);
if (!$stream) {
    $zip->close();
    http_response_code(500);
    exit('Dokumen tidak dapat dibaca dari ZIP.');
}

$downloadName = basename((string)$file['original_name']);
$downloadName = preg_replace('/[\x00-\x1F\x7F"\\]/u', '_', $downloadName);
$downloadName = $downloadName ?: 'dokumen';

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . zip_content_type($downloadName));
header('Content-Length: ' . (int)($stat['size'] ?? $file['size_bytes']));
$fallbackName = preg_replace('/[^\\x20-\\x7E]/', '_', $downloadName);
$fallbackName = str_replace(['\\', '"'], '_', $fallbackName);
if ($fallbackName === '') {
    $fallbackName = 'dokumen' . (pathinfo($downloadName, PATHINFO_EXTENSION) ? '.' . pathinfo($downloadName, PATHINFO_EXTENSION) : '');
}
header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');

while (!feof($stream)) {
    echo fread($stream, 1024 * 1024);
    flush();
}

fclose($stream);
$zip->close();
exit;
