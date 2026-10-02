<?php
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/config/auth.php';
require_login();

$page_title = 'Dashboard';

$total = (int)$pdo->query('SELECT COUNT(*) FROM dokumen')->fetchColumn();
$lengkap = (int)$pdo->query("SELECT COUNT(*) FROM dokumen WHERE status='lengkap'")->fetchColumn();
$review = (int)$pdo->query("SELECT COUNT(*) FROM dokumen WHERE status='perlu_review'")->fetchColumn();
$belum = (int)$pdo->query("SELECT COUNT(*) FROM dokumen WHERE status='belum_lengkap'")->fetchColumn();

$eps = $pdo->query("
    SELECT
        ep.kode,
        ep.judul,
        COUNT(de.dokumen_id) AS jumlah,
        SUM(
            CASE
                WHEN d.status_akreditasi = 'aktif_2026'
                 AND d.tahun_aktif = 2026
                THEN 1 ELSE 0
            END
        ) AS aktif_2026,
        SUM(
            CASE
                WHEN d.status = 'perlu_review'
                THEN 1 ELSE 0
            END
        ) AS perlu_review
    FROM elemen_penilaian ep
    LEFT JOIN dokumen_ep de ON de.ep_id = ep.id
    LEFT JOIN dokumen d ON d.id = de.dokumen_id
    GROUP BY ep.id
    ORDER BY ep.urutan
")->fetchAll();

require __DIR__.'/partials/header.php';
?>

<style>
.dashboard-legend {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.dashboard-legend .badge {
    padding:8px 11px;
    border-radius:999px;
    font-weight:600;
}
.ep-row {
    transition:background .15s ease, transform .15s ease;
}
.ep-row:hover {
    transform:translateX(2px);
}
.ep-row-active {
    background:#ecfdf5;
}
.ep-row-review {
    background:#fff8e1;
}
.ep-row-empty {
    background:#fff5f5;
}
.ep-status {
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:5px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    white-space:nowrap;
}
.ep-status-active {
    background:#198754;
    color:#fff;
}
.ep-status-review {
    background:#ffc107;
    color:#212529;
}
.ep-status-empty {
    background:#dc3545;
    color:#fff;
}
.ep-count {
    font-size:18px;
    font-weight:700;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Dashboard MRMIK IT</h2>
        <div class="text-muted">Kelengkapan dokumen dan bukti implementasi</div>
    </div>
    <span class="badge text-bg-light"><?=htmlspecialchars($_SESSION['user']['nama'])?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat shadow-sm">
            <div class="card-body">
                <div class="text-muted">Total Dokumen</div>
                <div class="display-6 fw-bold"><?=$total?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat shadow-sm">
            <div class="card-body">
                <div class="text-muted">Lengkap</div>
                <div class="display-6 fw-bold text-success"><?=$lengkap?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat shadow-sm">
            <div class="card-body">
                <div class="text-muted">Perlu Review</div>
                <div class="display-6 fw-bold text-warning"><?=$review?></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat shadow-sm">
            <div class="card-body">
                <div class="text-muted">Belum Lengkap</div>
                <div class="display-6 fw-bold text-danger"><?=$belum?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Elemen Penilaian</h5>

            <div class="dashboard-legend mb-0">
                <span class="badge text-bg-success">● Ada dokumen aktif</span>
                <span class="badge text-bg-warning">● Perlu review</span>
                <span class="badge text-bg-danger">● Belum ada dokumen</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>EP</th>
                        <th>Fokus</th>
                        <th>Dokumen Terkait</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach($eps as $ep): ?>
                    <?php
                    $aktif = (int)($ep['aktif_2026'] ?? 0);
                    $reviewEp = (int)($ep['perlu_review'] ?? 0);
                    $jumlah = (int)$ep['jumlah'];

                    if ($aktif > 0) {
                        $rowClass = 'ep-row-active';
                        $statusClass = 'ep-status-active';
                        $statusText = '✓ AKTIF 2026';
                    } elseif ($reviewEp > 0 || $jumlah > 0) {
                        $rowClass = 'ep-row-review';
                        $statusClass = 'ep-status-review';
                        $statusText = '⚠ PERLU REVIEW';
                    } else {
                        $rowClass = 'ep-row-empty';
                        $statusClass = 'ep-status-empty';
                        $statusText = '✕ BELUM ADA BUKTI';
                    }
                    ?>

                    <tr class="ep-row <?=$rowClass?>">
                        <td>
                            <strong><?=htmlspecialchars($ep['kode'])?></strong>
                        </td>

                        <td>
                            <?=htmlspecialchars($ep['judul'])?>
                        </td>

                        <td>
                            <span class="ep-count"><?=$jumlah?></span>
                        </td>

                        <td>
                            <span class="ep-status <?=$statusClass?>">
                                <?=$statusText?>
                            </span>
                        </td>

                        <td>
                            <a
                                class="btn btn-sm btn-outline-success"
                                href="/MRMIKIT/mode-survei/detail.php?kode=<?=urlencode($ep['kode'])?>"
                            >
                                Lihat Bukti
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__.'/partials/footer.php';