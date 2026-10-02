<?php
function resolve_import_archive(PDO $pdo, array $batch): ?string
{
    $baseDir = realpath(__DIR__ . '/../storage/import');
    if (!$baseDir || !is_dir($baseDir)) {
        return null;
    }

    $batchId = (int)($batch['id'] ?? 0);
    $created = strtotime((string)($batch['created_at'] ?? ''));
    $expectedCount = (int)($batch['total_file'] ?? 0);

    $candidates = [];
    foreach (glob($baseDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'archive.zip') ?: [] as $archive) {
        $dir = dirname($archive);
        $marker = $dir . DIRECTORY_SEPARATOR . 'batch.json';

        if (is_file($marker)) {
            $meta = json_decode((string)@file_get_contents($marker), true);
            if (is_array($meta) && (int)($meta['batch_id'] ?? 0) === $batchId) {
                return $archive;
            }
        }

        $mtime = @filemtime($archive) ?: 0;
        $count = 0;
        $zip = new ZipArchive();
        if ($zip->open($archive) === true) {
            $count = $zip->numFiles;
            $zip->close();
        }

        $score = 0;
        if ($expectedCount > 0 && $count === $expectedCount) {
            $score += 1000000;
        }
        if ($created && $mtime) {
            $score -= abs($mtime - $created);
        }

        $candidates[] = ['path' => $archive, 'score' => $score];
    }

    if (!$candidates) {
        return null;
    }

    usort($candidates, static fn($a, $b) => $b['score'] <=> $a['score']);
    return $candidates[0]['path'];
}

function zip_content_type(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => 'application/pdf',
        'csv' => 'text/csv; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'zip' => 'application/zip',
        default => 'application/octet-stream',
    };
}
