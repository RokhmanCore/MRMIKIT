<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare('SELECT * FROM import_batch WHERE id = ?');
$st->execute([$id]);
$batch = $st->fetch();

if (!$batch) {
    die('Batch tidak ditemukan');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['ep_id'] ?? [] as $fid => $epid) {
        $fid = (int)$fid;
        $epid = (int)$epid;

        if ($epid > 0) {
            $pdo->prepare(
                'UPDATE import_file
                 SET ep_id = ?, status = \'dipetakan\', catatan = ?
                 WHERE id = ? AND batch_id = ?'
            )->execute([
                $epid,
                $_POST['catatan'][$fid] ?? null,
                $fid,
                $id
            ]);
        } else {
            $pdo->prepare(
                'UPDATE import_file
                 SET status = \'diabaikan\', catatan = ?
                 WHERE id = ? AND batch_id = ?'
            )->execute([
                $_POST['catatan'][$fid] ?? null,
                $fid,
                $id
            ]);
        }
    }

    $pdo->prepare('UPDATE import_batch SET status = \'dipetakan\' WHERE id = ?')
        ->execute([$id]);

    header('Location: mapping.php?id=' . $id . '&saved=1');
    exit;
}

$eps = $pdo->query(
    'SELECT id, kode, judul FROM elemen_penilaian ORDER BY urutan'
)->fetchAll();

$st = $pdo->prepare(
    'SELECT * FROM import_file WHERE batch_id = ? ORDER BY relative_path'
);
$st->execute([$id]);
$files = $st->fetchAll();

$total = count($files);
$active = 0;
$mapped = 0;
$review = 0;
$ignored = 0;

foreach ($files as $f) {
    if (($f['review_status'] ?? '') === 'disetujui' || !empty($f['dokumen_id'])) {
        $active++;
    } elseif (($f['review_status'] ?? '') === 'perlu_revisi') {
        $review++;
    } elseif ((int)($f['ep_id'] ?? 0) > 0) {
        $mapped++;
    } else {
        $ignored++;
    }
}

$page_title = 'Pemetaan Dokumen Lama';
require __DIR__ . '/../partials/header.php';
?>

<style>
.legacy-hero {
    background: linear-gradient(135deg, #063f32 0%, #087f5b 100%);
    color: #fff;
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 18px;
    box-shadow: 0 10px 28px rgba(0,0,0,.10);
}
.legacy-hero h2 { margin-bottom: 5px; font-weight: 700; }
.legacy-hero p { margin: 0; opacity: .85; }

.stat-card {
    border: 0;
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(0,0,0,.06);
    height: 100%;
}
.stat-number { font-size: 25px; font-weight: 700; line-height: 1; }
.status-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    margin-right: 6px;
}
.row-active { background: #ecfdf5 !important; }
.row-review { background: #fff8e1 !important; }
.row-ignored { background: #f8f9fa !important; opacity: .82; }

.file-name {
    font-weight: 600;
    color: #183b34;
    word-break: break-word;
}
.file-source {
    font-size: 12px;
    color: #7a8790;
    margin-top: 3px;
}
.status-badge {
    white-space: nowrap;
    font-size: 12px;
    padding: 6px 9px;
    border-radius: 999px;
}
.mapping-table thead th {
    background: #f7faf9;
    white-space: nowrap;
    border-bottom: 2px solid #dee7e3;
}
.mapping-table tbody tr { transition: background .15s ease; }
.mapping-table tbody tr:hover { background: #f4fbf8 !important; }
.action-stack { display: flex; gap: 6px; flex-wrap: wrap; }
</style>

<div class="legacy-hero d-flex justify-content-between align-items-center gap-3">
    <div>
        <div class="small text-uppercase fw-bold mb-1">MRMIKIT · Import Akreditasi Lama</div>
        <h2>Pemetaan Dokumen Lama</h2>
        <p>
            <?= htmlspecialchars($batch['nama_batch']) ?>
            · Tahun <?= htmlspecialchars($batch['tahun_sumber']) ?>
            · <?= (int)$batch['total_file'] ?> file
        </p>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light" href="download-zip.php?id=<?= $batch['id'] ?>">
            ⬇ Download ZIP
        </a>
        <span class="badge text-bg-warning align-self-center px-3 py-2">
            Review wajib
        </span>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Total File</div>
                <div class="stat-number mt-2"><?= $total ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Aktif 2026</div>
                <div class="stat-number text-success mt-2"><?= $active ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Sudah Dipetakan</div>
                <div class="stat-number text-primary mt-2"><?= $mapped ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Perlu Review</div>
                <div class="stat-number text-warning mt-2"><?= $review ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success border-0 shadow-sm">
        <strong>✓ Pemetaan tersimpan.</strong>
        Status dokumen yang sudah aktif tetap aman.
    </div>
<?php endif; ?>

<div class="alert alert-warning border-0 shadow-sm">
    <strong>Perhatian:</strong>
    File ZIP hanya dianggap sebagai arsip. Dokumen lama
    <strong>tidak otomatis dianggap berlaku untuk 2026</strong>.
    Dokumen harus dipetakan ke EP dan melalui review sebelum menjadi aktif.
</div>

<form method="post">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 mapping-table">
                    <thead>
                        <tr>
                            <th class="ps-4">Dokumen</th>
                            <th>Ukuran</th>
                            <th>EP MRMIK</th>
                            <th>Status</th>
                            <th>Catatan Review</th>
                            <th>Download</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($files as $f): ?>
                        <?php
                        $isActive =
                            (($f['review_status'] ?? '') === 'disetujui')
                            || !empty($f['dokumen_id']);

                        $isRevision =
                            (($f['review_status'] ?? '') === 'perlu_revisi');

                        $isIgnored =
                            (($f['status'] ?? '') === 'diabaikan')
                            && !$isActive;

                        $rowClass = $isActive
                            ? 'row-active'
                            : ($isRevision ? 'row-review' : ($isIgnored ? 'row-ignored' : ''));

                        if ($isActive) {
                            $statusHtml = '<span class="badge text-bg-success status-badge">✓ AKTIF 2026</span>';
                        } elseif ($isRevision) {
                            $statusHtml = '<span class="badge text-bg-warning status-badge">⚠ PERLU REVISI</span>';
                        } elseif ((int)($f['ep_id'] ?? 0) > 0) {
                            $statusHtml = '<span class="badge text-bg-primary status-badge">● DIPETAKAN</span>';
                        } elseif ($isIgnored) {
                            $statusHtml = '<span class="badge text-bg-secondary status-badge">ARSIP UMUM</span>';
                        } else {
                            $statusHtml = '<span class="badge text-bg-light border status-badge">BELUM DIREVIEW</span>';
                        }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="ps-4" style="min-width:280px">
                                <div class="file-name">
                                    <?= htmlspecialchars($f['relative_path']) ?>
                                </div>
                                <div class="file-source">
                                    Sumber <?= htmlspecialchars($f['tahun_sumber'] ?? $batch['tahun_sumber']) ?>
                                </div>
                            </td>

                            <td style="white-space:nowrap">
                                <?= number_format($f['size_bytes'] / 1024, 1) ?> KB
                            </td>

                            <td style="min-width:250px">
                                <select
                                    class="form-select"
                                    name="ep_id[<?= $f['id'] ?>]"
                                    <?= $isActive ? 'disabled' : '' ?>
                                >
                                    <option value="0">— Abaikan / arsip umum —</option>

                                    <?php foreach ($eps as $e): ?>
                                        <option
                                            value="<?= $e['id'] ?>"
                                            <?= ((int)$f['ep_id'] === (int)$e['id']) ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($e['kode'] . ' — ' . $e['judul']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <?php if ($isActive): ?>
                                    <input type="hidden" name="ep_id[<?= $f['id'] ?>]" value="<?= (int)$f['ep_id'] ?>">
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= $statusHtml ?>
                            </td>

                            <td style="min-width:190px">
                                <input
                                    class="form-control"
                                    name="catatan[<?= $f['id'] ?>]"
                                    value="<?= htmlspecialchars($f['catatan'] ?? '') ?>"
                                    placeholder="Catatan review..."
                                    <?= $isActive ? 'readonly' : '' ?>
                                >
                            </td>

                            <td>
                                <a
                                    class="btn btn-sm btn-outline-success"
                                    href="download.php?id=<?= $f['id'] ?>"
                                    title="Download file asli"
                                >
                                    ⬇ Download
                                </a>
                            </td>

                            <td>
                                <div class="action-stack">
                                    <a
                                        class="btn btn-sm <?= $isActive ? 'btn-success' : 'btn-outline-primary' ?>"
                                        href="review.php?id=<?= $f['id'] ?>"
                                    >
                                        <?= $isActive ? '✓ Lihat Aktif' : 'Review / Aktifkan' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="small text-muted">
            <span class="status-dot bg-success"></span>Aktif 2026
            &nbsp;&nbsp;
            <span class="status-dot bg-warning"></span>Perlu revisi
            &nbsp;&nbsp;
            <span class="status-dot bg-primary"></span>Sudah dipetakan
        </div>

        <button class="btn btn-success px-4">
            ✓ Simpan Pemetaan
        </button>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>