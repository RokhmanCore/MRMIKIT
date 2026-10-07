<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$page_title='Audit & Monitoring IT';

$rows = [
    [
        'aspek' => 'Kepatuhan Sistem',
        'kode' => 'kepatuhan',
        'indikator' => 'SIMRS/RME digunakan sesuai alur dan kewenangan',
        'hasil' => 'Memenuhi',
        'catatan' => 'Diisi berdasarkan hasil pemeriksaan'
    ],
    [
        'aspek' => 'Kestabilan Jaringan',
        'kode' => 'jaringan',
        'indikator' => 'LAN/Wi-Fi/Internet dan koneksi ke server stabil',
        'hasil' => 'Memenuhi',
        'catatan' => 'Lampirkan hasil/screenshot monitoring jaringan'
    ],
    [
        'aspek' => 'Kecepatan / Loading RME',
        'kode' => 'rme',
        'indikator' => 'Akses modul RME tidak menghambat pelayanan',
        'hasil' => 'Memenuhi',
        'catatan' => 'Isi hasil pengamatan/pengukuran aktual'
    ],
    [
        'aspek' => 'Keluhan User',
        'kode' => 'keluhan',
        'indikator' => 'Keluhan pengguna dicatat dan ditindaklanjuti',
        'hasil' => 'Memenuhi',
        'catatan' => 'Isi jumlah keluhan dan tindak lanjut aktual'
    ],
];

require __DIR__.'/../partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>Audit &amp; Monitoring IT</h2>
        <div class="text-muted">Form monitoring 4 fokus: kepatuhan sistem, jaringan, RME, dan keluhan user.</div>
    </div>
    <a class="btn btn-success" href="form.php">+ Input Monitoring</a>
</div>

<div class="row g-3 mb-4">
<?php foreach ($rows as $r): ?>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="small text-muted"><?=$r['aspek']?></div>
                <h5 class="mt-1"><?=htmlspecialchars($r['indikator'])?></h5>
                <span class="badge text-bg-success">Contoh status: <?=htmlspecialchars($r['hasil'])?></span>
                <p class="small text-muted mt-3 mb-0"><?=htmlspecialchars($r['catatan'])?></p>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5>Struktur Form Monitoring</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Aspek</th><th>Pemeriksaan</th><th>Hasil</th><th>Bukti</th><th>Tindak Lanjut</th></tr></thead>
                <tbody>
                <tr><td>Kepatuhan Sistem</td><td>SIMRS/RME sesuai alur dan kewenangan</td><td>Memenuhi/Tidak</td><td>Upload bukti</td><td>Opsional</td></tr>
                <tr><td>Kestabilan Jaringan</td><td>LAN, Wi-Fi, Internet, koneksi server</td><td>Stabil/Tidak stabil</td><td>Upload screenshot/log</td><td>Opsional</td></tr>
                <tr><td>Kecepatan/Loading RME</td><td>Login, data pasien, SOAP, resep, pencarian</td><td>Normal/Lambat</td><td>Upload bukti hasil</td><td>Opsional</td></tr>
                <tr><td>Keluhan User</td><td>Keluhan dan hasil penanganan</td><td>Ada/Tidak</td><td>Upload bukti keluhan</td><td>Isi bila ada</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__.'/../partials/footer.php';