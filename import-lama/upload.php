<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_batch'] ?? '');
    $tahun = (int) ($_POST['tahun_sumber'] ?? 0);

    if ($nama === '' || $tahun < 2000 || empty($_FILES['zip']['name'])) {
        $error = 'Nama, tahun dan ZIP wajib diisi.';
    } else {
        $ext = strtolower(pathinfo($_FILES['zip']['name'], PATHINFO_EXTENSION));

        if ($ext !== 'zip') {
            $error = 'File harus berformat ZIP.';
        } elseif (!isset($_FILES['zip']['tmp_name']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload ZIP gagal. Kode error: ' . (int) ($_FILES['zip']['error'] ?? -1);
        } else {
            $baseDir = __DIR__ . '/../storage/import';

            if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true)) {
                $error = 'Folder storage/import tidak dapat dibuat.';
            } else {
                $dir = $baseDir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));

                if (!mkdir($dir, 0775, true)) {
                    $error = 'Folder penyimpanan import tidak dapat dibuat.';
                } else {
                    $zipPath = $dir . '/archive.zip';

                    if (!move_uploaded_file($_FILES['zip']['tmp_name'], $zipPath)) {
                        $error = 'Gagal menyimpan file ZIP.';
                    } else {
                        $zip = new ZipArchive();
                        $opened = $zip->open($zipPath);

                        if ($opened === true) {
                            try {
                                $pdo->beginTransaction();

                                $batch = $pdo->prepare(
                                    'INSERT INTO import_batch
                                    (nama_batch, tahun_sumber, filename_zip, total_file, created_by)
                                    VALUES (?, ?, ?, ?, ?)'
                                );

                                $batch->execute([
                                    $nama,
                                    $tahun,
                                    $_FILES['zip']['name'],
                                    $zip->numFiles,
                                    $_SESSION['user']['id']
                                ]);

                                $batchId = (int) $pdo->lastInsertId();

                                $insertFile = $pdo->prepare(
                                    'INSERT INTO import_file
                                    (batch_id, relative_path, original_name, extension, size_bytes, tahun_sumber)
                                    VALUES (?, ?, ?, ?, ?, ?)'
                                );

                                $fileCount = 0;

                                for ($i = 0; $i < $zip->numFiles; $i++) {
                                    $entry = $zip->statIndex($i);

                                    if (!$entry || !isset($entry['name'])) {
                                        continue;
                                    }

                                    $path = $entry['name'];

                                    // Lewati folder kosong/directory entry.
                                    if (substr($path, -1) === '/') {
                                        continue;
                                    }

                                    $original = basename($path);
                                    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

                                    $insertFile->execute([
                                        $batchId,
                                        $path,
                                        $original,
                                        $extension,
                                        (int) ($entry['size'] ?? 0),
                                        $tahun
                                    ]);

                                    $fileCount++;
                                }

                                $pdo->prepare(
                                    'UPDATE import_batch SET total_file = ? WHERE id = ?'
                                )->execute([$fileCount, $batchId]);

                                $pdo->commit();

                                // Penanda lokal agar arsip dapat ditemukan kembali untuk download per dokumen.
                                @file_put_contents(
                                    $dir . '/batch.json',
                                    json_encode([
                                        'batch_id' => $batchId,
                                        'filename_zip' => $_FILES['zip']['name'],
                                        'created_at' => date('c')
                                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                                );

                                $zip->close();

                                header('Location: mapping.php?id=' . $batchId);
                                exit;
                            } catch (Throwable $e) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }

                                $zip->close();
                                $error = 'Import gagal: ' . $e->getMessage();
                            }
                        } else {
                            $error = 'ZIP tidak dapat dibaca. Kode: ' . (int) $opened;
                        }
                    }
                }
            }
        }
    }
}

$page_title = 'Import ZIP';
require __DIR__ . '/../partials/header.php';
?>

<h2>Import ZIP Akreditasi Lama</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label>Nama Batch</label>
                <input
                    class="form-control"
                    name="nama_batch"
                    placeholder="Akreditasi 2022 - MRMIK IT"
                    required
                >
            </div>

            <div class="mb-3">
                <label>Tahun sumber</label>
                <input
                    class="form-control"
                    type="number"
                    name="tahun_sumber"
                    value="2022"
                    min="2000"
                    max="<?= date('Y') ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label>File ZIP</label>
                <input
                    class="form-control"
                    type="file"
                    name="zip"
                    accept=".zip"
                    required
                >
            </div>

            <button class="btn btn-success" type="submit">
                Upload &amp; Baca ZIP
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>