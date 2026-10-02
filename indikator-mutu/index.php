<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();

$page_title='Indikator Mutu IT';
$msg=''; $err='';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $action=$_POST['action']??'';

        if ($action==='add_indicator') {
            $edit_post=(int)($_POST['edit_id']??0);
            $st=$pdo->prepare("INSERT INTO mutu_indikator
                (kode,nama,definisi_operasional,numerator_label,denominator_label,formula,target,satuan,arah,frekuensi,sumber_data,metode_pengumpulan,pic_id,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if ($edit_post) {
                $st=$pdo->prepare("UPDATE mutu_indikator SET kode=?,nama=?,definisi_operasional=?,numerator_label=?,denominator_label=?,formula=?,target=?,satuan=?,arah=?,frekuensi=?,sumber_data=?,metode_pengumpulan=?,pic_id=? WHERE id=?");
                $st->execute([trim($_POST['kode']),trim($_POST['nama']),trim($_POST['definisi_operasional']??''),trim($_POST['numerator_label']??''),trim($_POST['denominator_label']??''),trim($_POST['formula']??''),($_POST['target']!==''?$_POST['target']:null),trim($_POST['satuan']??'%'),$_POST['arah']??'sesuai_target',$_POST['frekuensi']??'bulanan',trim($_POST['sumber_data']??''),trim($_POST['metode_pengumpulan']??''),($_POST['pic_id']!==''?$_POST['pic_id']:null),$edit_post]);
                $msg='Indikator berhasil diperbarui.';
            } else {
            $st->execute([
                trim($_POST['kode']),trim($_POST['nama']),trim($_POST['definisi_operasional']??''),
                trim($_POST['numerator_label']??''),trim($_POST['denominator_label']??''),trim($_POST['formula']??''),
                ($_POST['target']!==''?$_POST['target']:null),trim($_POST['satuan']??'%'),
                $_POST['arah']??'sesuai_target',$_POST['frekuensi']??'bulanan',trim($_POST['sumber_data']??''),
                trim($_POST['metode_pengumpulan']??''),($_POST['pic_id']!==''?$_POST['pic_id']:null),$_SESSION['user']['id']??null
            ]);
            $msg='Indikator berhasil ditambahkan.';
            }
        }

        if ($action==='delete_capaian') {
            $id=(int)($_POST['capaian_id']??0);
            if(!$id) throw new RuntimeException('Capaian tidak valid.');
            $st=$pdo->prepare("SELECT id FROM mutu_capaian WHERE id=?");
            $st->execute([$id]);
            if(!$st->fetch()) throw new RuntimeException('Capaian tidak ditemukan.');
            $pdo->prepare("DELETE FROM mutu_capaian WHERE id=?")->execute([$id]);
            $msg='Capaian dan bukti terkait berhasil dihapus.';
        }

        if ($action==='save_capaian') {
            $indikator_id=(int)$_POST['indikator_id'];
            $periode=($_POST['periode']??'').' -01';
            $periode=str_replace(' ','',$periode);
            $num=($_POST['numerator']!==''?$_POST['numerator']:null);
            $den=($_POST['denominator']!==''?$_POST['denominator']:null);
            $target=($_POST['target_snapshot']!==''?$_POST['target_snapshot']:null);
            $cap=($_POST['capaian']!==''?$_POST['capaian']:null);
            if ($cap===null && $num!==null && $den!==null && (float)$den!=0) $cap=((float)$num/(float)$den)*100;

            $st=$pdo->prepare("SELECT arah,target FROM mutu_indikator WHERE id=?");
            $st->execute([$indikator_id]); $ind=$st->fetch();
            if ($target===null && $ind) $target=$ind['target'];

            $status='belum_dinilai';
            if ($cap!==null && $target!==null) {
                $status=((float)$cap >= (float)$target) ? 'tercapai' : 'tidak_tercapai';
                if (($ind['arah']??'sesuai_target')==='turun') {
                    $status=((float)$cap <= (float)$target) ? 'tercapai' : 'tidak_tercapai';
                }
            }
            $editCapaian=(int)($_POST['edit_capaian_id']??0);
            if($editCapaian){
                $st=$pdo->prepare("UPDATE mutu_capaian SET periode=?,numerator=?,denominator=?,capaian=?,target_snapshot=?,analisis=?,tindak_lanjut=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND indikator_id=?");
                $st->execute([$periode,$num,$den,$cap,$target,trim($_POST['analisis']??''),trim($_POST['tindak_lanjut']??''),$status,$editCapaian,$indikator_id]);
                $msg='Capaian berhasil diperbarui.';
            } else {
                $st=$pdo->prepare("INSERT INTO mutu_capaian
                    (indikator_id,periode,numerator,denominator,capaian,target_snapshot,analisis,tindak_lanjut,status,created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE numerator=VALUES(numerator),denominator=VALUES(denominator),capaian=VALUES(capaian),
                    target_snapshot=VALUES(target_snapshot),analisis=VALUES(analisis),tindak_lanjut=VALUES(tindak_lanjut),
                    status=VALUES(status),updated_at=CURRENT_TIMESTAMP");
                $st->execute([$indikator_id,$periode,$num,$den,$cap,$target,trim($_POST['analisis']??''),trim($_POST['tindak_lanjut']??''),$status,$_SESSION['user']['id']??null]);
                $msg='Capaian periode berhasil disimpan.';
            }
        }

        if ($action==='upload_bukti') {
            $capaian_id=(int)($_POST['capaian_id']??0);
            if (!$capaian_id || empty($_FILES['bukti_file']) || $_FILES['bukti_file']['error']!==UPLOAD_ERR_OK) {
                throw new RuntimeException('File bukti belum dipilih atau gagal diunggah.');
            }
            $st=$pdo->prepare("SELECT c.id,c.indikator_id,c.periode,i.kode FROM mutu_capaian c JOIN mutu_indikator i ON i.id=c.indikator_id WHERE c.id=?");
            $st->execute([$capaian_id]); $caprow=$st->fetch();
            if (!$caprow) throw new RuntimeException('Data capaian tidak ditemukan.');

            $f=$_FILES['bukti_file'];
            $max=20*1024*1024;
            if ($f['size']>$max) throw new RuntimeException('Ukuran file maksimal 20 MB.');
            $allowed=['pdf','doc','docx','xls','xlsx','csv','jpg','jpeg','png','zip'];
            $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
            if (!in_array($ext,$allowed,true)) throw new RuntimeException('Format file tidak didukung. Gunakan PDF, Office, CSV, JPG/PNG atau ZIP.');

            $dir=__DIR__.'/../uploads/mutu-indikator';
            if (!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Folder upload tidak dapat dibuat.');
            $safe=bin2hex(random_bytes(8)).'_'.preg_replace('/[^A-Za-z0-9._-]/','_',basename($f['name']));
            $dest=$dir.'/'.$safe;
            if (!move_uploaded_file($f['tmp_name'],$dest)) throw new RuntimeException('File gagal disimpan.');

            $st=$pdo->prepare("INSERT INTO mutu_bukti(capaian_id,nama_file,original_name,mime_type,size_bytes,catatan,uploaded_by) VALUES(?,?,?,?,?,?,?)");
            $st->execute([$capaian_id,$safe,$f['name'],$f['type']??'',(int)$f['size'],trim($_POST['catatan_bukti']??''),$_SESSION['user']['id']??null]);
            $msg='Bukti indikator berhasil diunggah.';
        }

        if ($action==='map_ep') {
            $indikator_id=(int)$_POST['indikator_id'];
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM mutu_indikator_ep WHERE indikator_id=?")->execute([$indikator_id]);
            foreach (($_POST['ep_ids']??[]) as $ep_id) {
                $pdo->prepare("INSERT INTO mutu_indikator_ep(indikator_id,ep_id) VALUES(?,?)")->execute([$indikator_id,(int)$ep_id]);
            }
            $pdo->commit();
            $msg='Pemetaan indikator ke EP berhasil disimpan.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err='Gagal menyimpan: '.$e->getMessage();
    }
}

$editId=(int)($_GET['edit']??0);
$edit=null;
if($editId){$st=$pdo->prepare("SELECT * FROM mutu_indikator WHERE id=?");$st->execute([$editId]);$edit=$st->fetch();}

$pics=$pdo->query("SELECT id,nama FROM pic WHERE aktif=1 ORDER BY nama")->fetchAll();
$eps=$pdo->query("SELECT id,kode,judul FROM elemen_penilaian ORDER BY urutan")->fetchAll();

$indikators=$pdo->query("
 SELECT i.*,p.nama pic_nama,
   (SELECT COUNT(*) FROM mutu_capaian c WHERE c.indikator_id=i.id) jumlah_periode,
   (SELECT c.status FROM mutu_capaian c WHERE c.indikator_id=i.id ORDER BY c.periode DESC LIMIT 1) status_terakhir,
   (SELECT c.capaian FROM mutu_capaian c WHERE c.indikator_id=i.id ORDER BY c.periode DESC LIMIT 1) capaian_terakhir
 FROM mutu_indikator i LEFT JOIN pic p ON p.id=i.pic_id
 WHERE i.aktif=1 ORDER BY i.kode")->fetchAll();

$selectedMap=[];
$mapId=(int)($_GET['map']??0);
if($mapId){
 $st=$pdo->prepare("SELECT ep_id FROM mutu_indikator_ep WHERE indikator_id=?");$st->execute([$mapId]);
 foreach($st as $r)$selectedMap[]=(int)$r['ep_id'];
}

$editCapaianId=(int)($_GET['edit_capaian']??0);
$editCapaian=null;
if($editCapaianId){
 $st=$pdo->prepare("SELECT * FROM mutu_capaian WHERE id=?");
 $st->execute([$editCapaianId]); $editCapaian=$st->fetch();
}

$detailId=(int)($_GET['detail']??0);
$detail=null;$rows=[];
if($detailId){
 $st=$pdo->prepare("SELECT i.*,p.nama pic_nama FROM mutu_indikator i LEFT JOIN pic p ON p.id=i.pic_id WHERE i.id=?");
 $st->execute([$detailId]);$detail=$st->fetch();
 if($detail){
   $st=$pdo->prepare("SELECT c.*,DATE_FORMAT(c.periode,'%Y-%m') periode_label FROM mutu_capaian c WHERE c.indikator_id=? ORDER BY c.periode DESC");
   $st->execute([$detailId]);$rows=$st->fetchAll();
   $buktiByCapaian=[];
   if($rows){ $ids=array_map(fn($r)=>(int)$r['id'],$rows); $ph=implode(',',array_fill(0,count($ids),'?')); $bs=$pdo->prepare("SELECT * FROM mutu_bukti WHERE capaian_id IN ($ph) ORDER BY created_at DESC"); $bs->execute($ids); foreach($bs as $b)$buktiByCapaian[(int)$b['capaian_id']][]=$b; }
 }
}

require __DIR__.'/../partials/header.php';
?>
<style>
.mutu-hero{background:linear-gradient(135deg,#123f34,#198754);color:#fff;border-radius:18px;padding:22px;margin-bottom:18px}
.mutu-card{border:0;border-radius:16px}
.status-pill{display:inline-block;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:700}
.status-tercapai{background:#198754;color:#fff}.status-perhatian{background:#ffc107;color:#212529}.status-tidak{background:#dc3545;color:#fff}.status-belum{background:#6c757d;color:#fff}
.kpi-number{font-size:26px;font-weight:800}
.progress-mini{height:8px}
</style>

<div class="mutu-hero">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
  <div><div class="small opacity-75">MRMIKIT · MUTU TEKNOLOGI INFORMASI</div><h2 class="mb-1">Indikator Mutu IT</h2><div>Kelola indikator, capaian bulanan, analisis, tindak lanjut, dan pemetaan ke EP MRMIK.</div></div>
  <div class="text-end"><div class="small opacity-75">Total indikator</div><div class="display-6 fw-bold"><?=count($indikators)?></div></div>
 </div>
</div>

<?php if($msg):?><div class="alert alert-success"><?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="alert alert-danger"><?=h($err)?></div><?php endif;?>

<div class="alert alert-warning"><strong>Catatan:</strong> indikator dan target di modul ini adalah indikator mutu internal IT. Target harus ditetapkan/disahkan oleh RS sesuai kebijakan dan metode pengukuran yang berlaku; jangan menganggap angka contoh sebagai target nasional.</div>

<div class="card shadow-sm mutu-card mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><h5 class="mb-0">Daftar Indikator</h5><button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#formIndikator">+ Tambah indikator</button></div>
 <div class="table-responsive"><table class="table align-middle">
 <thead><tr><th>Kode</th><th>Indikator</th><th>Target</th><th>PIC</th><th>Capaian terakhir</th><th>Status</th><th>Aksi</th></tr></thead>
 <tbody>
 <?php foreach($indikators as $i): ?>
 <tr>
  <td><strong><?=h($i['kode'])?></strong></td><td><?=h($i['nama'])?><div class="small text-muted"><?=$i['jumlah_periode']?> periode</div></td>
  <td><?= $i['target']!==null ? h($i['target']).' '.h($i['satuan']) : '<span class="text-muted">Belum diisi</span>'?></td>
  <td><?=h($i['pic_nama']??'-')?></td>
  <td><?= $i['capaian_terakhir']!==null ? h(round((float)$i['capaian_terakhir'],2)).' '.h($i['satuan']) : '-'?></td>
  <td><?php $s=$i['status_terakhir']??'belum_dinilai';?><span class="status-pill <?=($s==='tercapai'?'status-tercapai':($s==='tidak_tercapai'?'status-tidak':($s==='perlu_perhatian'?'status-perhatian':'status-belum')))?>"><?=strtoupper(str_replace('_',' ',$s))?></span></td>
  <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?detail=<?=$i['id']?>">Capaian</a> <a class="btn btn-sm btn-outline-success" href="?map=<?=$i['id']?>">EP</a> <a class="btn btn-sm btn-outline-secondary" href="?edit=<?=$i['id']?>">Edit</a></td>
 </tr>
 <?php endforeach;?>
 </tbody></table></div>
</div></div>

<div class="collapse <?=($edit?'show':'')?>" id="formIndikator"><div class="card shadow-sm mutu-card mb-4"><div class="card-body">
<h5><?= $edit?'Edit indikator':'Tambah indikator baru'?></h5>
<form method="post"><input type="hidden" name="action" value="add_indicator"><input type="hidden" name="edit_id" value="<?=h($edit['id']??0)?>">
<div class="row g-3">
<div class="col-md-3"><label class="form-label">Kode</label><input name="kode" class="form-control" required value="<?=h($edit['kode']??'')?>"></div>
<div class="col-md-9"><label class="form-label">Nama indikator</label><input name="nama" class="form-control" required value="<?=h($edit['nama']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Definisi operasional</label><textarea name="definisi_operasional" class="form-control" rows="3"><?=h($edit['definisi_operasional']??'')?></textarea></div>
<div class="col-md-3"><label class="form-label">Target</label><input type="number" step="0.0001" name="target" class="form-control" value="<?=h($edit['target']??'')?>"></div>
<div class="col-md-3"><label class="form-label">Satuan</label><input name="satuan" class="form-control" value="<?=h($edit['satuan']??'%')?>"></div>
<div class="col-md-3"><label class="form-label">Numerator</label><input name="numerator_label" class="form-control" value="<?=h($edit['numerator_label']??'')?>"></div>
<div class="col-md-3"><label class="form-label">Denominator</label><input name="denominator_label" class="form-control" value="<?=h($edit['denominator_label']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Formula</label><input name="formula" class="form-control" placeholder="Contoh: numerator / denominator × 100" value="<?=h($edit['formula']??'')?>"></div>
<div class="col-md-3"><label class="form-label">Arah target</label><select name="arah" class="form-select"><option value="naik" <?=($edit['arah']??'')==='naik'?'selected':''?>>Semakin tinggi semakin baik</option><option value="turun" <?=($edit['arah']??'')==='turun'?'selected':''?>>Semakin rendah semakin baik</option><option value="sesuai_target" <?=($edit['arah']??'')==='sesuai_target'?'selected':''?>>Sesuai target</option></select></div>
<div class="col-md-3"><label class="form-label">Frekuensi</label><select name="frekuensi" class="form-select"><?php foreach(['bulanan','triwulan','semester','tahunan'] as $f):?><option <?=($edit['frekuensi']??'bulanan')===$f?'selected':''?>><?=$f?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label">Sumber data</label><input name="sumber_data" class="form-control" value="<?=h($edit['sumber_data']??'')?>" placeholder="SIMRS, monitoring server, tiket IT..."></div>
<div class="col-md-5"><label class="form-label">Metode pengumpulan</label><input name="metode_pengumpulan" class="form-control" value="<?=h($edit['metode_pengumpulan']??'')?>"></div>
<div class="col-md-3"><label class="form-label">PIC</label><select name="pic_id" class="form-select"><option value="">- pilih -</option><?php foreach($pics as $p):?><option value="<?=$p['id']?>" <?=((string)($edit['pic_id']??'')===(string)$p['id'])?'selected':''?>><?=h($p['nama'])?></option><?php endforeach;?></select></div>
</div><button class="btn btn-success mt-3">Simpan indikator</button></form>
</div></div></div>

<?php if($mapId): ?>
<div class="card shadow-sm mutu-card mb-4"><div class="card-body"><h5>Pemetaan indikator ke EP MRMIK</h5>
<form method="post"><input type="hidden" name="action" value="map_ep"><input type="hidden" name="indikator_id" value="<?=$mapId?>">
<div class="row"><?php foreach($eps as $e):?><div class="col-md-6 col-lg-4 mb-2"><label class="border rounded p-2 d-block"><input type="checkbox" name="ep_ids[]" value="<?=$e['id']?>" <?=in_array((int)$e['id'],$selectedMap,true)?'checked':''?>> <strong><?=h($e['kode'])?></strong><br><small><?=h($e['judul'])?></small></label></div><?php endforeach;?></div>
<button class="btn btn-success mt-2">Simpan Pemetaan</button></form></div></div>
<?php endif;?>

<?php if($detail): ?>
<div class="card shadow-sm mutu-card mb-4"><div class="card-body">
<h5><?= $editCapaian ? 'Edit capaian' : 'Tambah capaian' ?>: <?=h($detail['kode'])?> — <?=h($detail['nama'])?></h5>
<div class="small text-muted mb-3">Target: <?= $detail['target']!==null?h($detail['target'].' '.$detail['satuan']):'belum ditetapkan'?> · Arah: <?=h($detail['arah'])?></div>
<form method="post" class="border rounded p-3 mb-4"><input type="hidden" name="action" value="save_capaian"><input type="hidden" name="indikator_id" value="<?=$detail['id']?>"><input type="hidden" name="edit_capaian_id" value="<?=h($editCapaian['id']??0)?>">
<div class="row g-3">
<div class="col-md-2"><label class="form-label">Periode</label><input type="month" name="periode" class="form-control" required value="<?=h($editCapaian ? substr($editCapaian['periode'],0,7) : date('Y-m'))?>"></div>
<div class="col-md-2"><label class="form-label">Numerator</label><input type="number" step="0.0001" name="numerator" class="form-control" value="<?=h($editCapaian['numerator']??'')?>"></div>
<div class="col-md-2"><label class="form-label">Denominator</label><input type="number" step="0.0001" name="denominator" class="form-control" value="<?=h($editCapaian['denominator']??'')?>"></div>
<div class="col-md-2"><label class="form-label">Capaian</label><input type="number" step="0.0001" name="capaian" class="form-control" placeholder="otomatis bila N/D" value="<?=h($editCapaian['capaian']??'')?>"></div>
<div class="col-md-2"><label class="form-label">Target periode</label><input type="number" step="0.0001" name="target_snapshot" class="form-control" value="<?=h($editCapaian['target_snapshot'] ?? $detail['target'] ?? '')?>"></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-success w-100">Simpan</button></div>
<div class="col-12"><label class="form-label">Analisis</label><textarea name="analisis" class="form-control" rows="2"><?=h($editCapaian['analisis']??'')?></textarea></div>
<div class="col-12"><label class="form-label">Tindak lanjut</label><textarea name="tindak_lanjut" class="form-control" rows="2"><?=h($editCapaian['tindak_lanjut']??'')?></textarea></div>
</div></form>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Periode</th><th>N</th><th>D</th><th>Capaian</th><th>Target</th><th>Status</th><th>Analisis / Tindak lanjut</th><th>Aksi</th><th>Bukti</th></tr></thead><tbody>
<?php foreach($rows as $r):$s=$r['status'];?><tr><td><?=h($r['periode_label'])?></td><td><?=h($r['numerator'])?></td><td><?=h($r['denominator'])?></td><td><strong><?= $r['capaian']!==null?h(round((float)$r['capaian'],2).' '.$detail['satuan']):'-'?></strong></td><td><?=h($r['target_snapshot'])?></td><td><span class="status-pill <?=($s==='tercapai'?'status-tercapai':($s==='tidak_tercapai'?'status-tidak':'status-belum'))?>"><?=h(strtoupper(str_replace('_',' ',$s)))?></span></td><td><div><?=h($r['analisis']??'-')?></div><small class="text-muted"><?=h($r['tindak_lanjut']??'')?></small></td>
<td class="text-nowrap">
 <a class="btn btn-sm btn-outline-secondary mb-1" href="?detail=<?=$detail['id']?>&edit_capaian=<?=$r['id']?>">Edit</a>
 <form method="post" class="d-inline" onsubmit="return confirm('Hapus capaian periode <?=h($r['periode_label'])?> beserta bukti yang terhubung?');">
  <input type="hidden" name="action" value="delete_capaian"><input type="hidden" name="capaian_id" value="<?=$r['id']?>">
  <button class="btn btn-sm btn-outline-danger mb-1">Hapus</button>
 </form>
</td>
<td style="min-width:260px">
 <?php foreach(($buktiByCapaian[(int)$r['id']]??[]) as $b): ?>
   <div class="mb-1"><a href="download.php?id=<?=$b['id']?>" target="_blank"><?=h($b['original_name'])?></a> <small class="text-muted">(<?=round($b['size_bytes']/1024,1)?> KB)</small></div>
 <?php endforeach; ?>
 <form method="post" enctype="multipart/form-data" class="mt-2">
  <input type="hidden" name="action" value="upload_bukti"><input type="hidden" name="capaian_id" value="<?=$r['id']?>">
  <input type="file" name="bukti_file" class="form-control form-control-sm mb-1" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.zip">
  <input type="text" name="catatan_bukti" class="form-control form-control-sm mb-1" placeholder="Catatan bukti (opsional)">
  <button class="btn btn-sm btn-outline-success">📎 Upload bukti</button>
 </form>
</td></tr><?php endforeach;?>
</tbody></table></div>
</div></div>
<?php endif;?>

<?php require __DIR__.'/../partials/footer.php';