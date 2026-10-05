<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../config/auth.php';require_login();
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$id=(int)($_GET['id']??0);if(!$id)die('ID tidak valid.');
$pdo->exec("CREATE TABLE IF NOT EXISTS helpdesk_bukti (id INT AUTO_INCREMENT PRIMARY KEY,insiden_id INT NOT NULL,nama_file VARCHAR(255) NOT NULL,original_name VARCHAR(255) NOT NULL,mime_type VARCHAR(150) NULL,size_bytes BIGINT NOT NULL DEFAULT 0,catatan TEXT NULL,uploaded_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(insiden_id) REFERENCES helpdesk_insiden(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$st=$pdo->prepare("SELECT * FROM helpdesk_insiden WHERE id=?");$st->execute([$id]);$r=$st->fetch();if(!$r)die('Insiden tidak ditemukan.');
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $lapor=str_replace('T',' ',trim($_POST['tanggal_lapor']??''));$sel=str_replace('T',' ',trim($_POST['tanggal_selesai']??''));$sla=max(1,(int)($_POST['sla_menit']??240));
  $dur=null;$status=$sel?'selesai':'open';$ss='belum_dinilai';if($sel){$a=new DateTime($lapor);$b=new DateTime($sel);if($b<$a)throw new RuntimeException('Waktu selesai tidak boleh lebih awal dari laporan.');$dur=round(($b->getTimestamp()-$a->getTimestamp())/60,2);$ss=$dur<=$sla?'sesuai':'tidak_sesuai';}
  $up=$pdo->prepare("UPDATE helpdesk_insiden SET nomor=?,tanggal_lapor=?,tanggal_selesai=?,unit_pelapor=?,pelapor=?,masalah=?,prioritas=?,sla_menit=?,durasi_menit=?,status=?,status_sla=?,penyelesaian=?,pic_id=?,sumber=?,catatan=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
  $up->execute([trim($_POST['nomor']),$lapor,$sel?:null,trim($_POST['unit_pelapor']??''),trim($_POST['pelapor']??''),trim($_POST['masalah']),$_POST['prioritas']??'sedang',$sla,$dur,$status,$ss,trim($_POST['penyelesaian']??''),($_POST['pic_id']??'')?:null,$_POST['sumber']??'whatsapp',trim($_POST['catatan']??''),$id]);
  $files=$_FILES['bukti']??null;if($files&&isset($files['name'])&&is_array($files['name'])){$dir=__DIR__.'/../uploads/helpdesk';if(!is_dir($dir))mkdir($dir,0775,true);$upb=$pdo->prepare("INSERT INTO helpdesk_bukti(insiden_id,nama_file,original_name,mime_type,size_bytes,catatan,uploaded_by) VALUES(?,?,?,?,?,?,?)");$allowed=['pdf','jpg','jpeg','png','doc','docx','xls','xlsx','csv','txt','zip'];for($n=0;$n<count($files['name']);$n++){if(($files['error'][$n]??4)===4)continue;if($files['error'][$n]!==0)throw new RuntimeException('Upload bukti gagal.');if($files['size'][$n]>20*1024*1024)throw new RuntimeException('Maksimal 20 MB per file.');$orig=basename($files['name'][$n]);$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));if(!in_array($ext,$allowed,true))throw new RuntimeException('Format bukti tidak didukung.');$safe=bin2hex(random_bytes(8)).'_'.preg_replace('/[^A-Za-z0-9._-]/','_',$orig);if(!move_uploaded_file($files['tmp_name'][$n],$dir.'/'.$safe))throw new RuntimeException('File gagal disimpan.');$upb->execute([$id,$safe,$orig,$files['type'][$n]??'',(int)$files['size'][$n],trim($_POST['catatan_bukti']??''),$_SESSION['user']['id']??null]);}}
  header('Location:edit.php?id='.$id.'&saved=1');exit;
 }catch(Throwable $e){$err=$e->getMessage();}
}
$be=$pdo->prepare("SELECT * FROM helpdesk_bukti WHERE insiden_id=? ORDER BY created_at DESC");$be->execute([$id]);$be=$be->fetchAll();
$pics=$pdo->query("SELECT id,nama FROM pic WHERE aktif=1 ORDER BY nama")->fetchAll();$page_title='Edit Insiden TI';require __DIR__.'/../partials/header.php';
?>
<h2>Edit Insiden TI</h2><?php if(isset($_GET['saved'])):?><div class="alert alert-success">Insiden berhasil disimpan.</div><?php endif;?><?php if(!empty($err)):?><div class="alert alert-danger"><?=h($err)?></div><?php endif;?>
<div class="card shadow-sm border-0 mb-3"><div class="card-body"><form method="post" enctype="multipart/form-data"><div class="row g-3">
<div class="col-md-3"><label class="form-label">Nomor</label><input name="nomor" class="form-control" value="<?=h($r['nomor'])?>" required></div>
<div class="col-md-3"><label class="form-label">Laporan</label><input name="tanggal_lapor" type="datetime-local" class="form-control" value="<?=h(date('Y-m-dTH:i',strtotime($r['tanggal_lapor'])))?>" required></div>
<div class="col-md-3"><label class="form-label">Selesai</label><input name="tanggal_selesai" type="datetime-local" class="form-control" value="<?=$r['tanggal_selesai']?h(date('Y-m-dTH:i',strtotime($r['tanggal_selesai']))):''?>"></div>
<div class="col-md-3"><label class="form-label">SLA menit</label><input name="sla_menit" type="number" min="1" class="form-control" value="<?=h($r['sla_menit'])?>"></div>
<div class="col-md-3"><label class="form-label">Prioritas</label><select name="prioritas" class="form-select"><?php foreach(['kritikal','tinggi','sedang','rendah'] as $x):?><option <?=$r['prioritas']===$x?'selected':''?>><?=$x?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label">Unit pelapor</label><input name="unit_pelapor" class="form-control" value="<?=h($r['unit_pelapor'])?>"></div>
<div class="col-md-3"><label class="form-label">Pelapor</label><input name="pelapor" class="form-control" value="<?=h($r['pelapor'])?>"></div>
<div class="col-md-3"><label class="form-label">PIC</label><select name="pic_id" class="form-select"><option value="">- pilih -</option><?php foreach($pics as $p):?><option value="<?=$p['id']?>" <?=$r['pic_id']==$p['id']?'selected':''?>><?=h($p['nama'])?></option><?php endforeach;?></select></div>
<div class="col-12"><label class="form-label">Masalah</label><textarea name="masalah" rows="3" class="form-control" required><?=h($r['masalah'])?></textarea></div>
<div class="col-md-6"><label class="form-label">Penyelesaian</label><textarea name="penyelesaian" rows="3" class="form-control"><?=h($r['penyelesaian'])?></textarea></div>
<div class="col-md-6"><label class="form-label">Catatan</label><textarea name="catatan" rows="3" class="form-control"><?=h($r['catatan'])?></textarea></div>
<div class="col-md-4"><label class="form-label">Sumber</label><select name="sumber" class="form-select"><?php foreach(['whatsapp','telepon','laporan','monitoring','lainnya'] as $x):?><option <?=$r['sumber']===$x?'selected':''?>><?=$x?></option><?php endforeach;?></select></div>
<div class="col-md-8"><label class="form-label">Tambah bukti</label><input name="bukti[]" type="file" multiple class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"></div>
<div class="col-12"><label class="form-label">Catatan bukti baru</label><input name="catatan_bukti" class="form-control"></div>
</div><button class="btn btn-success mt-3">Simpan Perubahan</button> <a class="btn btn-outline-secondary mt-3" href="index.php">Kembali</a></form></div></div>
<div class="card shadow-sm border-0"><div class="card-body"><h5>Bukti terlampir</h5><table class="table"><thead><tr><th>File</th><th>Catatan</th><th>Tanggal</th></tr></thead><tbody><?php foreach($be as $b):?><tr><td><a target="_blank" href="../uploads/helpdesk/<?=rawurlencode($b['nama_file'])?>"><?=h($b['original_name'])?></a></td><td><?=h($b['catatan']??'-')?></td><td><?=h(date('d-m-Y H:i',strtotime($b['created_at'])))?></td></tr><?php endforeach;if(!$be):?><tr><td colspan="3" class="text-muted">Belum ada bukti.</td></tr><?php endif;?></tbody></table></div></div>
<?php require __DIR__.'/../partials/footer.php';