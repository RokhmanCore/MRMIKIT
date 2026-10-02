<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/archive_helper.php';
require_login();

$batchId = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM import_batch WHERE id=?');
$st->execute([$batchId]);
$batch = $st->fetch();

if (!$batch) {
    http_response_code(404);
    exit('Batch tidak ditemukan.');
}

$archive = resolve_import_archive($pdo, $batch);
if (!$archive || !is_file($archive)) {
    http_response_code(404);
    exit('Arsip ZIP sumber tidak ditemukan.');
}

while (ob_get_level()) {
    ob_end_clean();
}

$name = basename((string)$batch['filename_zip']);
$name = preg_replace('/[\x00-\x1F\x7F"\\]/u', '_', $name);
$name = $name ?: ('akreditasi_' . $batchId . '.zip');

header('Content-Type: application/zip');
header('Content-Length: ' . filesize($archive));
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
readfile($archive);
exit;
