<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();

$page_title = 'Pencarian Dokumen';
$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    $stmt = $pdo->prepare(
        "SELECT d.id, d.nama AS judul, d.kategori AS subjudul, d.status_akreditasi AS status,
                'Dokumen IT' AS sumber, CONCAT('/MRMIKIT/dokumen/view.php?id=', d.id) AS url
         FROM dokumen d
         LEFT JOIN pic p ON p.id = d.pic_id
         WHERE d.nama LIKE ? OR d.original_name LIKE ? OR d.kategori LIKE ?
            OR d.nomor LIKE ? OR d.versi LIKE ? OR p.nama LIKE ?
         ORDER BY d.updated_at DESC"
    );
    $stmt->execute([$like,$like,$like,$like,$like,$like]);
    foreach ($stmt->fetchAll() as $row) $results[] = $row;

    $stmt = $pdo->prepare(
        "SELECT b.id, b.judul, CONCAT(b.jenis, ' · ', COALESCE(b.deskripsi,'')) AS subjudul,
                DATE_FORMAT(b.tanggal_bukti,'%Y-%m-%d') AS status,
                'Bukti Implementasi' AS sumber,
                CONCAT('/MRMIKIT/uploads/', b.filename) AS url
         FROM bukti_implementasi b
         LEFT JOIN pic p ON p.id = b.pic_id
         WHERE b.judul LIKE ? OR b.original_name LIKE ? OR b.jenis LIKE ?
            OR b.deskripsi LIKE ? OR p.nama LIKE ?
         ORDER BY b.created_at DESC"
    );
    $stmt->execute([$like,$like,$like,$like,$like]);
    foreach ($stmt->fetchAll() as $row) $results[] = $row;

    $stmt = $pdo->prepare(
        "SELECT id, CONCAT('Downtime ', DATE_FORMAT(mulai,'%Y-%m-%d %H:%i')) AS judul,
                CONCAT(jenis, ' · ', COALESCE(penyebab,'')) AS subjudul,
                COALESCE(tindak_lanjut,'-') AS status,
                'Downtime' AS sumber,
                '/MRMIKIT/downtime/' AS url
         FROM downtime
         WHERE jenis LIKE ? OR penyebab LIKE ? OR unit_terdampak LIKE ?
            OR tindakan LIKE ? OR evaluasi LIKE ? OR tindak_lanjut LIKE ?
         ORDER BY mulai DESC"
    );
    $stmt->execute([$like,$like,$like,$like,$like,$like]);
    foreach ($stmt->fetchAll() as $row) $results[] = $row;

    $stmt = $pdo->prepare(
        "SELECT id, CONCAT(kode, ' — ', judul) AS judul,
                CONCAT(kategori, ' · ', COALESCE(deskripsi,'')) AS subjudul,
                kode AS status,
                'EP MRMIK' AS sumber,
                CONCAT('/MRMIKIT/mode-survei/detail.php?kode=', REPLACE(kode,' ','%20')) AS url
         FROM elemen_penilaian
         WHERE kode LIKE ? OR kategori LIKE ? OR judul LIKE ? OR deskripsi LIKE ?
         ORDER BY urutan"
    );
    $stmt->execute([$like,$like,$like,$like]);
    foreach ($stmt->fetchAll() as $row) $results[] = $row;

    $stmt = $pdo->prepare(
        "SELECT f.id, f.original_name AS judul,
                CONCAT('Batch: ', b.nama_batch, ' · Tahun ', f.tahun_sumber) AS subjudul,
                f.status AS status,
                'Arsip Import' AS sumber,
                CONCAT('/MRMIKIT/import-lama/mapping.php?id=', f.batch_id) AS url
         FROM import_file f
         INNER JOIN import_batch b ON b.id = f.batch_id
         WHERE f.original_name LIKE ? OR f.relative_path LIKE ? OR b.nama_batch LIKE ?
         ORDER BY b.created_at DESC, f.original_name"
    );
    $stmt->execute([$like,$like,$like]);
    foreach ($stmt->fetchAll() as $row) $results[] = $row;
}

require __DIR__ . '/../partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>Pencarian Dokumen</h2>
        <div class="text-muted">Cari satu kata atau nama file di seluruh modul MRMIKIT.</div>
    </div>
</div>

<?php if ($q === ''): ?>
    <div class="alert alert-info">
        Ketik nama dokumen, kata pada isi metadata, kode EP, nama bukti, downtime, atau nama file pada kotak pencarian di atas.
    </div>
<?php else: ?>
    <div class="mb-3">
        <strong><?=count($results)?></strong> hasil untuk:
        <span class="badge text-bg-light"><?=htmlspecialchars($q)?></span>
    </div>

    <?php if (!$results): ?>
        <div class="alert alert-warning">Tidak ada data yang cocok dengan pencarian.</div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Sumber</th>
                            <th>Dokumen / Data</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><span class="badge badge-soft"><?=htmlspecialchars($r['sumber'])?></span></td>
                            <td><?=htmlspecialchars($r['judul'])?></td>
                            <td><?=htmlspecialchars($r['subjudul'] ?? '-')?></td>
                            <td><?=htmlspecialchars($r['status'] ?? '-')?></td>
                            <td><a class="btn btn-sm btn-outline-success" href="<?=htmlspecialchars($r['url'])?>" target="_blank">Buka</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>