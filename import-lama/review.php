<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();

$fid = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare(
    'SELECT f.*, b.nama_batch, b.tahun_sumber,
            e.kode AS ep_kode, e.judul AS ep_judul
     FROM import_file f
     JOIN import_batch b ON b.id = f.batch_id
     LEFT JOIN elemen_penilaian e ON e.id = f.ep_id
     WHERE f.id = ?'
);
$st->execute([$fid]);
$f = $st->fetch();

if (!$f) {
    die('File import tidak ditemukan');
}

$eps = $pdo->query(
    'SELECT id, kode, judul FROM elemen_penilaian ORDER BY urutan'
)->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['catatan'] ?? '');
    $ep = (int)($_POST['ep_id'] ?? 0);

    // Simpan pilihan EP juga ketika memilih Perlu Revisi/Tidak Berlaku.
    if ($ep > 0) {
        $pdo->prepare(
            "UPDATE import_file SET ep_id = ?, status = 'dipetakan', catatan = ? WHERE id = ?"
        )->execute([$ep, $note, $fid]);

        // Refresh data agar label EP yang dipilih langsung benar.
        $st->execute([$fid]);
        $f = $st->fetch();
    }

    if ($action === 'aktif') {
        if ($ep <= 0) {
            $error = 'Pilih EP MRMIK terlebih dahulu sebelum mengaktifkan dokumen.';
        } else {
            $path = __DIR__ . '/../storage/import';
            $sourceFile = null;

            foreach (glob($path . '/*/archive.zip') as $zipPath) {
                $z = new ZipArchive();

                if ($z->open($zipPath) === true) {
                    $idx = $z->locateName($f['relative_path']);

                    if ($idx !== false) {
                        $dir = __DIR__ . '/../uploads';

                        if (!is_dir($dir)) {
                            mkdir($dir, 0775, true);
                        }

                        $ext = strtolower(
                            pathinfo($f['original_name'], PATHINFO_EXTENSION)
                        );

                        if ($ext === '') {
                            $ext = strtolower(
                                pathinfo($f['relative_path'], PATHINFO_EXTENSION)
                            );
                        }

                        $new = uniqid('legacy_', true) .
                            ($ext !== '' ? '.' . $ext : '');

                        $z->extractTo($dir, [$f['relative_path']]);
                        $extracted = $dir . '/' . $f['relative_path'];

                        if (is_file($extracted)) {
                            rename($extracted, $dir . '/' . $new);
                            $sourceFile = $new;
                        }

                        $z->close();

                        if ($sourceFile) {
                            break;
                        }
                    }

                    $z->close();
                }
            }

            if ($sourceFile) {
                $pdo->beginTransaction();

                $stDoc = $pdo->prepare(
                    'INSERT INTO dokumen
                    (nama, kategori, versi, tanggal_review, filename,
                     original_name, status, status_akreditasi, tahun_aktif,
                     created_by, reviewed_by, reviewed_at, review_catatan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stDoc->execute([
                    $f['original_name'],
                    'Arsip Akreditasi Lama',
                    '1.0',
                    null,
                    $sourceFile,
                    $f['original_name'],
                    'lengkap',
                    'aktif_2026',
                    2026,
                    $_SESSION['user']['id'],
                    $_SESSION['user']['id'],
                    date('Y-m-d H:i:s'),
                    $note
                ]);

                $did = (int)$pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO dokumen_ep (dokumen_id, ep_id) VALUES (?, ?)'
                )->execute([$did, $ep]);

                $pdo->prepare(
                    'INSERT INTO dokumen_sumber
                    (dokumen_id, tahun_sumber, batch_id, sumber)
                    VALUES (?, ?, ?, ?)'
                )->execute([
                    $did,
                    $f['tahun_sumber'],
                    $f['batch_id'],
                    'akreditasi_lama'
                ]);

                $pdo->prepare(
                    'INSERT INTO dokumen_versi
                    (dokumen_id, versi, filename, original_name, catatan, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $did,
                    '1.0',
                    $sourceFile,
                    $f['original_name'],
                    'Dipromosikan dari arsip akreditasi ' .
                    $f['tahun_sumber'] . ': ' . $note,
                    $_SESSION['user']['id']
                ]);

                $pdo->prepare(
                    "UPDATE import_file
                     SET dokumen_id = ?, review_status = 'disetujui',
                         reviewed_by = ?, reviewed_at = NOW(),
                         catatan = ?, status = 'dipetakan', ep_id = ?
                     WHERE id = ?"
                )->execute([
                    $did,
                    $_SESSION['user']['id'],
                    $note,
                    $ep,
                    $fid
                ]);

                $pdo->commit();

                header(
                    'Location: /MRMIKIT/mode-survei/detail.php?kode=' .
                    urlencode($f['ep_kode'])
                );
                exit;
            }

            $error = 'File tidak ditemukan di arsip ZIP.';
        }
    } elseif ($action === 'revisi' || $action === 'tolak') {
        $status = $action === 'revisi' ? 'perlu_revisi' : 'ditolak';

        $pdo->prepare(
            'UPDATE import_file
             SET review_status = ?, reviewed_by = ?, reviewed_at = NOW(),
                 catatan = ?, ep_id = ?
             WHERE id = ?'
        )->execute([
            $status,
            $_SESSION['user']['id'],
            $note,
            $ep > 0 ? $ep : null,
            $fid
        ]);

        header('Location: mapping.php?id=' . $f['batch_id']);
        exit;
    }
}

$page_title = 'Review Dokumen Lama';
require __DIR__ . '/../partials/header.php';
?>

<h2>Review Dokumen Akreditasi Lama</h2>

<div class="card shadow-sm border-0">
    <div class="card-body">

        <dl class="row">
            <dt class="col-sm-3">File</dt>
            <dd class="col-sm-9">
                <?= htmlspecialchars($f['relative_path']) ?>
            </dd>

            <dt class="col-sm-3">Sumber</dt>
            <dd class="col-sm-9">
                Akreditasi <?= htmlspecialchars($f['tahun_sumber']) ?>
            </dd>

            <dt class="col-sm-3">EP MRMIK</dt>
            <dd class="col-sm-9">
                <?= htmlspecialchars(
                    ($f['ep_kode'] ?? 'Belum dipilih') .
                    ' — ' .
                    ($f['ep_judul'] ?? '')
                ) ?>
            </dd>
        </dl>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">

            <div class="mb-3">
                <label class="form-label fw-bold">
                    Pilih EP MRMIK
                </label>

                <select
                    name="ep_id"
                    class="form-select"
                    required
                >
                    <option value="">
                        — Pilih EP MRMIK —
                    </option>

                    <?php foreach ($eps as $e): ?>
                        <option
                            value="<?= (int)$e['id'] ?>"
                            <?= ((int)$f['ep_id'] === (int)$e['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $e['kode'] . ' — ' . $e['judul']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="form-text">
                    Pilih EP yang benar-benar didukung oleh dokumen ini.
                    Satu dokumen lama tidak otomatis dianggap berlaku untuk 2026.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    Catatan Review
                </label>

                <textarea
                    class="form-control"
                    name="catatan"
                    rows="4"
                    placeholder="Contoh: masih sesuai kondisi SIMRS 2026; nomor dokumen tetap; review berikutnya..."
                ><?= htmlspecialchars($f['catatan'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button
                    name="action"
                    value="aktif"
                    class="btn btn-success"
                >
                    Jadikan Dokumen Aktif 2026
                </button>

                <button
                    name="action"
                    value="revisi"
                    class="btn btn-warning"
                >
                    Perlu Revisi
                </button>

                <button
                    name="action"
                    value="tolak"
                    class="btn btn-outline-danger"
                >
                    Tidak Berlaku
                </button>
            </div>

        </form>

    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>