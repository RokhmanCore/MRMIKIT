<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../config/auth.php';require_login();
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$pdo->exec("CREATE TABLE IF NOT EXISTS helpdesk_insiden (id INT AUTO_INCREMENT PRIMARY KEY,nomor VARCHAR(50) NOT NULL UNIQUE,tanggal_lapor DATETIME NOT NULL,tanggal_selesai DATETIME NULL,unit_pelapor VARCHAR(150) NULL,pelapor VARCHAR(150) NULL,masalah TEXT NOT NULL,prioritas ENUM('kritikal','tinggi','sedang','rendah') NOT NULL DEFAULT 'sedang',sla_menit INT NOT NULL DEFAULT 240,durasi_menit DECIMAL(12,2) NULL,status ENUM('open','selesai','batal') NOT NULL DEFAULT 'open',status_sla ENUM('sesuai','tidak_sesuai','belum_dinilai') NOT NULL DEFAULT 'belum_dinilai',penyelesaian TEXT NULL,pic_id INT NULL,sumber VARCHAR(50) NOT NULL DEFAULT 'whatsapp',catatan TEXT NULL,created_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS helpdesk_bukti (id INT AUTO_INCREMENT PRIMARY KEY,insiden_id INT NOT NULL,nama_file VARCHAR(255) NOT NULL,original_name VARCHAR(255) NOT NULL,mime_type VARCHAR(150) NULL,size_bytes BIGINT NOT NULL DEFAULT 0,catatan TEXT NULL,uploaded_by INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(insiden_id) REFERENCES helpdesk_insiden(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $lapor=str_replace('T',' ',trim($_POST['tanggal_lapor']??''));$sel=str_replace('T',' ',trim($_POST['tanggal_selesai']??''));
  if(!$lapor||!$_POST['masalah'])throw new RuntimeException('Tanggal laporan dan masalah wajib diisi.');
  $sla=max(1,(int)($_POST['sla_menit']??240));$status=$sel?'selesai':'open';$dur=null;$slaStatus='belum_dinilai';
  if($sel){$a=new DateTime($lapor);$b=new DateTime($sel);if($b<$a)throw new RuntimeException('Waktu selesai tidak boleh lebih awal dari waktu laporan.');$dur=round(($b->getTimestamp()-$a->getTimestamp())/60,2);$slaStatus=$dur<=$sla?'sesuai':'tidak_sesuai';}
  $nomor=trim($_POST['nomor']??'');if(!$nomor){$nomor='INC-'.date('YmdHis').'-'.random_int(10,99);}
  $st=$pdo->prepare("INSERT INTO helpdesk_insiden(nomor,tanggal_lapor,tanggal_selesai,unit_pelapor,pelapor,masalah,prioritas,sla_menit,durasi_menit,status,status_sla,penyelesaian,pic_id,sumber,catatan,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  $st->execute([$nomor,$lapor,$sel?:null,trim($_POST['unit_pelapor']??''),trim($_POST['pelapor']??''),trim($_POST['masalah']),$_POST['prioritas']??'sedang',$sla,$dur,$status,$slaStatus,trim($_POST['penyelesaian']??''),($_POST['pic_id']??'')?:null,$_POST['sumber']??'whatsapp',trim($_POST['catatan']??''),$_SESSION['user']['id']??null]);
  $id=(int)$pdo->lastInsertId();
  $files=$_FILES['bukti']??null;if($files&&isset($files['name'])&&is_array($files['name'])){
   $dir=__DIR__.'/../uploads/helpdesk';if(!is_dir($dir))mkdir($dir,0775,true);
   $up=$pdo->prepare("INSERT INTO helpdesk_bukti(insiden_id,nama_file,original_name,mime_type,size_bytes,catatan,uploaded_by) VALUES(?,?,?,?,?,?,?)");
   $allowed=['pdf','jpg','jpeg','png','doc','docx','xls','xlsx','csv','txt','zip'];$count=count($files['name']);if($count>20)throw new RuntimeException('Maksimal 20 file sekali upload.');
   for($n=0;$n<$count;$n++){if(($files['error'][$n]??4)===4)continue;if($files['error'][$n]!==0)throw new RuntimeException('Upload bukti gagal.');if($files['size'][$n]>20*1024*1024)throw new RuntimeException('Maksimal 20 MB per file.');$orig=basename($files['name'][$n]);$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));if(!in_array($ext,$allowed,true))throw new RuntimeException('Format bukti tidak didukung.');$safe=bin2hex(random_bytes(8)).'_'.preg_replace('/[^A-Za-z0-9._-]/','_',$orig);if(!move_uploaded_file($files['tmp_name'][$n],$dir.'/'.$safe))throw new RuntimeException('File gagal disimpan.');$up->execute([$id,$safe,$orig,$files['type'][$n]??'',(int)$files['size'][$n],trim($_POST['catatan_bukti']??''),$_SESSION['user']['id']??null]);}
  }
  header('Location:edit.php?id='.$id.'&saved=1');exit;
 }catch(Throwable $e){$err=$e->getMessage();}
}
$pics=$pdo->query("SELECT id,nama FROM pic WHERE aktif=1 ORDER BY nama")->fetchAll();$page_title='Tambah Insiden TI';require __DIR__.'/../partials/header.php';
?>
<h2>Tambah Insiden TI</h2><div class="alert alert-info">Sumber dapat dipilih <strong>WhatsApp</strong> jika laporan awal berasal dari chat. Lampirkan screenshot yang memperlihatkan waktu laporan dan penyelesaian.</div>
<div class="card shadow-sm border-0"><div class="card-body"><form method="post" enctype="multipart/form-data">
<div class="row g-3">
<div class="col-md-3"><label class="form-label">Nomor insiden</label><input name="nomor" class="form-control" placeholder="Otomatis jika kosong"></div>
<div class="col-md-3"><label class="form-label">Tanggal/jam laporan *</label><input name="tanggal_lapor" type="datetime-local" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">Tanggal/jam selesai</label><input name="tanggal_selesai" type="datetime-local" class="form-control"></div>
<div class="col-md-3"><label class="form-label">SLA (menit) *</label><input name="sla_menit" type="number" min="1" class="form-control" value="240" required></div>
<div class="col-md-3"><label class="form-label">Prioritas</label><select name="prioritas" class="form-select"><option value="kritikal">Kritikal</option><option value="tinggi">Tinggi</option><option value="sedang" selected>Sedang</option><option value="rendah">Rendah</option></select></div>
<div class="col-md-3"><label class="form-label">Unit pelapor</label><input name="unit_pelapor" class="form-control"></div>
<div class="col-md-3"><label class="form-label">Nama pelapor</label><input name="pelapor" class="form-control"></div>
<div class="col-md-3"><label class="form-label">PIC IT</label><select name="pic_id" class="form-select"><option value="">- pilih -</option><?php foreach($pics as $p):?><option value="<?=$p['id']?>"><?=h($p['nama'])?></option><?php endforeach;?></select></div>
<div class="col-12"><label class="form-label">Masalah *</label><textarea name="masalah" rows="3" class="form-control" required></textarea></div>
<div class="col-md-6"><label class="form-label">Penyelesaian</label><textarea name="penyelesaian" rows="3" class="form-control"></textarea></div>
<div class="col-md-6"><label class="form-label">Catatan</label><textarea name="catatan" rows="3" class="form-control"></textarea></div>
<div class="col-md-4"><label class="form-label">Sumber</label><select name="sumber" class="form-select"><option value="whatsapp">WhatsApp</option><option value="telepon">Telepon</option><option value="laporan">Laporan unit</option><option value="monitoring">Monitoring IT</option><option value="lainnya">Lainnya</option></select></div>
<div class="col-md-8"><label class="form-label">Bukti pendukung (bisa banyak)</label><input name="bukti[]" type="file" multiple class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"><div class="form-text">Contoh: screenshot WhatsApp, screenshot error, foto perangkat, log. Maks. 20 file × 20 MB.</div></div>
<div class="col-12"><label class="form-label">Catatan bukti</label><input name="catatan_bukti" class="form-control" placeholder="Contoh: SS WhatsApp menunjukkan laporan dan waktu penyelesaian"></div>
</div><button class="btn btn-success mt-3">Simpan Insiden</button> <a class="btn btn-outline-secondary mt-3" href="index.php">Batal</a>
</form></div></div>
<?php require __DIR__.'/../partials/footer.php';