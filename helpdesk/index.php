<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$pdo->exec("CREATE TABLE IF NOT EXISTS helpdesk_insiden (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nomor VARCHAR(50) NOT NULL UNIQUE,
 tanggal_lapor DATETIME NOT NULL,
 tanggal_selesai DATETIME NULL,
 unit_pelapor VARCHAR(150) NULL,
 pelapor VARCHAR(150) NULL,
 masalah TEXT NOT NULL,
 prioritas ENUM('kritikal','tinggi','sedang','rendah') NOT NULL DEFAULT 'sedang',
 sla_menit INT NOT NULL DEFAULT 240,
 durasi_menit DECIMAL(12,2) NULL,
 status ENUM('open','selesai','batal') NOT NULL DEFAULT 'open',
 status_sla ENUM('sesuai','tidak_sesuai','belum_dinilai') NOT NULL DEFAULT 'belum_dinilai',
 penyelesaian TEXT NULL,
 pic_id INT NULL,
 sumber VARCHAR(50) NOT NULL DEFAULT 'whatsapp',
 catatan TEXT NULL,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_helpdesk_tanggal(tanggal_lapor),
 INDEX idx_helpdesk_status_sla(status_sla)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS helpdesk_bukti (
 id INT AUTO_INCREMENT PRIMARY KEY,
 insiden_id INT NOT NULL,
 nama_file VARCHAR(255) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(150) NULL,
 size_bytes BIGINT NOT NULL DEFAULT 0,
 catatan TEXT NULL,
 uploaded_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(insiden_id) REFERENCES helpdesk_insiden(id) ON DELETE CASCADE,
 INDEX idx_helpdesk_bukti(insiden_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$year=(int)($_GET['tahun']??date('Y'));
if($year<2020||$year>2100)$year=(int)date('Y');
$rows=$pdo->prepare("SELECT h.*,p.nama pic_nama FROM helpdesk_insiden h LEFT JOIN pic p ON p.id=h.pic_id WHERE YEAR(h.tanggal_lapor)=? ORDER BY h.tanggal_lapor DESC,h.id DESC");
$rows->execute([$year]);$rows=$rows->fetchAll();
$page_title='Helpdesk IT & SLA';
require __DIR__.'/../partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
 <div><h2 class="mb-1">Helpdesk IT & SLA</h2><div class="text-muted">Pencatatan insiden TI, batas SLA, penyelesaian, dan bukti pendukung untuk IM-IT-05.</div></div>
 <div class="d-flex gap-2"><a class="btn btn-success" href="tambah.php">+ Tambah Insiden</a><a class="btn btn-primary" href="import.php">📥 Import Word Maintenance</a><a class="btn btn-outline-primary" href="../indikator-mutu/?detail=<?=((int)($pdo->query("SELECT id FROM mutu_indikator WHERE kode='IM-IT-05' LIMIT 1")->fetchColumn()))?>&tahun=<?=$year?>">IM-IT-05</a></div>
</div>
<div class="alert alert-info"><strong>Catatan:</strong> WhatsApp boleh menjadi sumber bukti. Simpan screenshot percakapan yang menunjukkan waktu laporan dan penyelesaian, lalu lampirkan pada insiden ini. Jangan membuat waktu yang tidak ada di bukti.</div>
<form class="row g-2 mb-3"><div class="col-auto"><select name="tahun" class="form-select" onchange="this.form.submit()"><?php for($y=date('Y')-2;$y<=date('Y')+1;$y++):?><option <?=$y===$year?'selected':''?>><?=$y?></option><?php endfor;?></select></div></form>
<div class="card shadow-sm border-0"><div class="card-body table-responsive">
<table class="table table-hover align-middle"><thead><tr><th>No</th><th>Lapor</th><th>Masalah</th><th>Prioritas</th><th>SLA</th><th>Selesai</th><th>Durasi</th><th>Status SLA</th><th>PIC</th><th>Bukti</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($rows as $r):
$cls=$r['status_sla']==='sesuai'?'success':($r['status_sla']==='tidak_sesuai'?'danger':'secondary');
$st=$pdo->prepare("SELECT COUNT(*) FROM helpdesk_bukti WHERE insiden_id=?");$st->execute([$r['id']]);$bc=(int)$st->fetchColumn();
?>
<tr>
<td><strong><?=h($r['nomor'])?></strong></td><td><?=h(date('d-m-Y H:i',strtotime($r['tanggal_lapor'])))?></td>
<td><?=nl2br(h($r['masalah']))?><div class="small text-muted"><?=h($r['unit_pelapor']??'')?></div></td>
<td><?=h(ucfirst($r['prioritas']))?></td><td><?=h($r['sla_menit'])?> menit</td>
<td><?=!empty($r['tanggal_selesai'])?h(date('d-m-Y H:i',strtotime($r['tanggal_selesai']))):'-'?></td>
<td><?=($r['durasi_menit']!==null?h(round((float)$r['durasi_menit'],2).' menit'):'-')?></td>
<td><span class="badge text-bg-<?=$cls?>"><?=strtoupper(str_replace('_',' ',$r['status_sla']))?></span></td>
<td><?=h($r['pic_nama']??'-')?></td><td>📎 <?=$bc?></td>
<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?=$r['id']?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="hapus.php?id=<?=$r['id']?>" onclick="return confirm('Hapus insiden ini beserta bukti?')">Hapus</a></td>
</tr>
<?php endforeach;if(!$rows):?><tr><td colspan="11" class="text-center text-muted">Belum ada insiden TI tahun <?=h($year)?>.</td></tr><?php endif;?>
</tbody></table></div></div>
<?php require __DIR__.'/../partials/footer.php';