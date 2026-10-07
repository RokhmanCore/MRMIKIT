<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}

$fields = [
    'tanggal_monitoring','periode','petugas',
    'kepatuhan_status','kepatuhan_catatan',
    'jaringan_lan','jaringan_wifi','jaringan_internet','jaringan_server','jaringan_catatan',
    'rme_login','rme_data_pasien','rme_soap','rme_resep','rme_pencarian','rme_catatan',
    'keluhan_status','keluhan_detail','kesimpulan','tindak_lanjut'
];

$data = [];
foreach ($fields as $field) {
    $data[$field] = trim((string)($_POST[$field] ?? ''));
}

if ($data['tanggal_monitoring'] === '' || $data['petugas'] === '') {
    header('Location: form.php?error=required');
    exit;
}

// Jika tidak ada keluhan, sistem otomatis mencatat hasil monitoring keluhan.
if ($data['keluhan_status'] !== 'Ada') {
    $data['keluhan_status'] = 'Tidak ada';
    $data['keluhan_detail'] = 'Tidak ditemukan keluhan pengguna terkait SIMRS, RME, jaringan, maupun layanan IT selama periode monitoring.';
}

$sql = 'INSERT INTO audit_monitoring_it (' . implode(',', array_keys($data)) . ')
        VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')';

$stmt = $pdo->prepare($sql);
$stmt->execute(array_values($data));
$id = (int)$pdo->lastInsertId();

$uploadDir = __DIR__ . '/uploads/' . $id;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    header('Location: detail.php?id=' . $id . '&warning=upload_dir');
    exit;
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf'
];

if (isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'])) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($_FILES['evidence']['name'] as $i => $name) {
        if (($_FILES['evidence']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmp  = $_FILES['evidence']['tmp_name'][$i];
        $size = (int)$_FILES['evidence']['size'][$i];

        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            continue;
        }

        $mime = $finfo->file($tmp);
        if (!isset($allowed[$mime])) {
            continue;
        }

        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
        $stored = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safe;

        if (move_uploaded_file($tmp, $uploadDir . '/' . $stored)) {
            $q = $pdo->prepare(
                'INSERT INTO audit_monitoring_it_evidence
                (audit_id,nama_file,file_path,file_mime,file_size)
                VALUES (?,?,?,?,?)'
            );
            $q->execute([
                $id,
                $name,
                'uploads/' . $id . '/' . $stored,
                $mime,
                $size
            ]);
        }
    }
}

header('Location: detail.php?id=' . $id . '&saved=1');
exit;
