<?php
require_once __DIR__.'/../config/config.php'; require_once __DIR__.'/../config/auth.php'; require_login();
$id=(int)($_GET['id']??0); if(!$id) die('ID tidak valid');
$s=$pdo->prepare('SELECT * FROM audit_monitoring_it WHERE id=?'); $s->execute([$id]); $row=$s->fetch(); if(!$row) die('Data tidak ditemukan');
$e=$pdo->prepare('SELECT * FROM audit_monitoring_it_evidence WHERE audit_id=? ORDER BY id ASC'); $e->execute([$id]); $files=$e->fetchAll();
$page_title='Detail Monitoring IT'; require __DIR__.'/../partials/header.php';
$keluhanAda=($row['keluhan_status']==='Ada');
$bulan=date('F Y',strtotime($row['tanggal_monitoring']));
$bulanId=['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
$bulanTampil=($bulanId[date('F',strtotime($row['tanggal_monitoring']))]??date('F',strtotime($row['tanggal_monitoring']))).' '.date('Y',strtotime($row['tanggal_monitoring']));
$imgExt=['image/jpeg','image/png','image/webp'];
?>
<div class="no-print d-flex justify-content-between align-items-center mb-4">
  <div><h2 class="mb-1">Detail Monitoring IT #<?=$row['id']?></h2><div class="text-muted">Monitoring Bulanan <?=$bulanTampil?> · <?=htmlspecialchars($row['petugas'])?></div></div>
  <div><button onclick="window.print()" class="btn btn-primary">🖨 Cetak Laporan</button> <a class="btn btn-outline-secondary" href="./">Kembali</a></div>
</div>

<div class="print-report">
  <div class="report-header">
    <div class="report-title">FORM AUDIT / MONITORING IT</div>
    <div class="report-subtitle">Sistem Informasi, SIMRS & Rekam Medis Elektronik</div>
    <table class="meta-table">
      <tr><td>Periode</td><td><strong>Bulanan — <?=$bulanTampil?></strong></td><td>Tanggal Monitoring</td><td><strong><?=date('d/m/Y',strtotime($row['tanggal_monitoring']))?></strong></td></tr>
      <tr><td>Petugas IT</td><td colspan="3"><strong><?=htmlspecialchars($row['petugas'])?></strong></td></tr>
    </table>
  </div>

  <div class="section"><div class="section-title">1. Kepatuhan Sistem</div>
    <div class="status-box"><?=htmlspecialchars($row['kepatuhan_status'])?></div>
    <?php if(trim($row['kepatuhan_catatan']??'')): ?><div class="note"><?=nl2br(htmlspecialchars($row['kepatuhan_catatan']))?></div><?php endif; ?>
  </div>

  <div class="section"><div class="section-title">2. Kestabilan Jaringan</div>
    <table class="result-table"><thead><tr><th>LAN</th><th>Wi-Fi</th><th>Internet</th><th>Server SIMRS</th></tr></thead>
    <tbody><tr><td><?=htmlspecialchars($row['jaringan_lan'])?></td><td><?=htmlspecialchars($row['jaringan_wifi'])?></td><td><?=htmlspecialchars($row['jaringan_internet'])?></td><td><?=htmlspecialchars($row['jaringan_server'])?></td></tr></tbody></table>
    <?php if(trim($row['jaringan_catatan']??'')): ?><div class="note"><?=nl2br(htmlspecialchars($row['jaringan_catatan']))?></div><?php endif; ?>
  </div>

  <div class="section"><div class="section-title">3. Kecepatan / Loading RME</div>
    <table class="result-table"><thead><tr><th>Modul</th><th>Hasil Monitoring</th></tr></thead><tbody>
    <?php foreach(['rme_login'=>'Login','rme_data_pasien'=>'Data Pasien','rme_soap'=>'Pemeriksaan / SOAP','rme_resep'=>'Resep','rme_pencarian'=>'Pencarian Pasien'] as $k=>$label): ?>
      <tr><td><?=htmlspecialchars($label)?></td><td><?=htmlspecialchars($row[$k])?></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php if(trim($row['rme_catatan']??'')): ?><div class="note"><?=nl2br(htmlspecialchars($row['rme_catatan']))?></div><?php endif; ?>
  </div>

  <div class="section"><div class="section-title">4. Keluhan User</div>
    <?php if($keluhanAda): ?>
      <div class="finding"><strong>ADA KELUHAN</strong><br><?=nl2br(htmlspecialchars($row['keluhan_detail']??''))?></div>
    <?php else: ?>
      <div class="normal">✓ TIDAK ADA KELUHAN<br><span>Tidak ditemukan keluhan pengguna terkait SIMRS, RME, jaringan, maupun layanan IT selama periode monitoring.</span></div>
    <?php endif; ?>
  </div>

  <div class="section"><div class="section-title">5. Kesimpulan & Tindak Lanjut</div>
    <div class="label">Kesimpulan</div>
    <div class="text-block"><?=trim($row['kesimpulan']??'')?nl2br(htmlspecialchars($row['kesimpulan'])):'Monitoring berjalan sesuai checklist dan tidak ada temuan yang dicatat.'?></div>
    <div class="label">Tindak Lanjut</div>
    <div class="text-block"><?=trim($row['tindak_lanjut']??'')?nl2br(htmlspecialchars($row['tindak_lanjut'])):'Tidak ada tindak lanjut khusus.'?></div>
  </div>

  <div class="section evidence-section"><div class="section-title">6. Evidence / Bukti Pendukung (<?=count($files)?> file)</div>
    <?php if(!$files): ?><div class="muted">Belum ada evidence.</div>
    <?php else: ?>
      <div class="evidence-grid">
      <?php foreach($files as $f): ?>
        <?php if(in_array($f['file_mime'],$imgExt,true)): ?>
          <div class="evidence-item"><img src="<?=htmlspecialchars($f['file_path'])?>" alt="<?=htmlspecialchars($f['nama_file'])?>"><div><?=htmlspecialchars($f['nama_file'])?></div></div>
        <?php else: ?>
          <div class="evidence-file">📎 <?=htmlspecialchars($f['nama_file'])?><br><small>Dokumen PDF terlampir pada sistem.</small></div>
        <?php endif; ?>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="signature">
    <div class="signature-box">
      <div>Petugas IT / Pemeriksa</div>
      <div class="signature-space"></div>
      <div><strong><?=htmlspecialchars($row['petugas'])?></strong></div>
      <div>IT / SIMRS</div>
    </div>
  </div>

  <div class="report-footer">Dokumen Audit / Monitoring IT · Periode <?=$bulanTampil?> · Dicetak dari MRMIKIT</div>
</div>

<style>
.print-report{background:#fff;color:#222;max-width:1000px;margin:0 auto;padding:24px}
.report-header{border-bottom:3px solid #198754;padding-bottom:14px;margin-bottom:18px}
.report-title{text-align:center;font-size:21px;font-weight:700}.report-subtitle{text-align:center;font-size:12px;color:#666;margin:3px 0 15px}
.meta-table,.result-table{width:100%;border-collapse:collapse}.meta-table td{border:1px solid #ccc;padding:7px;font-size:12px}.meta-table td:nth-child(odd){background:#f5f5f5;font-weight:600;width:18%}
.section{margin:0 0 16px}.section-title{font-weight:700;background:#eef7f1;border-left:5px solid #198754;padding:7px 9px;margin-bottom:8px}.result-table th,.result-table td{border:1px solid #bbb;padding:7px;font-size:12px}.result-table th{background:#f3f3f3}.status-box,.normal,.finding,.note,.text-block{border:1px solid #ccc;border-radius:4px;padding:8px;font-size:12px}.normal{border-left:4px solid #198754}.normal span{font-weight:normal}.finding{border-left:4px solid #dc3545}.note{margin-top:7px;background:#fafafa}.label{font-weight:700;font-size:12px;margin:7px 0 3px}.text-block{min-height:30px}.muted{color:#777;font-size:12px}
.evidence-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.evidence-item{border:1px solid #ccc;padding:6px;text-align:center;break-inside:avoid}.evidence-item img{display:block;max-width:100%;height:auto;max-height:360px;margin:0 auto 5px;object-fit:contain}.evidence-item div{font-size:10px;word-break:break-word}.evidence-file{border:1px solid #ccc;padding:12px;font-size:12px;break-inside:avoid}
.signature{display:flex;justify-content:flex-end;margin-top:30px}.signature-box{width:280px;text-align:center;font-size:12px}.signature-space{height:80px}.report-footer{text-align:center;border-top:1px solid #ccc;margin-top:25px;padding-top:8px;font-size:9px;color:#777}
@media print{
  @page{size:A4;margin:12mm}
  body{background:#fff!important}
  .no-print,.navbar,.sidebar,header,footer{display:none!important}
  .print-report{max-width:none;padding:0}
  .section,.evidence-item,.evidence-file{break-inside:avoid}
  .evidence-section{break-before:auto}
}
</style>
<?php require __DIR__.'/../partials/footer.php'; ?>