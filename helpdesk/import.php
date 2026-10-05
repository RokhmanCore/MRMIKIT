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
 sumber VARCHAR(50) NOT NULL DEFAULT 'laporan',
 catatan TEXT NULL,
 source_waktu ENUM('tercatat','konfirmasi_petugas','perkiraan','tidak_tersedia') NOT NULL DEFAULT 'tidak_tersedia',
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_helpdesk_tanggal(tanggal_lapor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
try{$pdo->exec("ALTER TABLE helpdesk_insiden ADD COLUMN source_waktu ENUM('tercatat','konfirmasi_petugas','perkiraan','tidak_tersedia') NOT NULL DEFAULT 'tidak_tersedia' AFTER catatan");}catch(Throwable $e){}

function norm($s){
    $s=trim(mb_strtolower((string)$s,'UTF-8'));
    $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','/'=>' ','-'=>' ','_'=>' ']);
    return preg_replace('/\s+/',' ',$s);
}
function pickCol($headers,$patterns){
    foreach($headers as $i=>$v){
        $n=norm($v);
        foreach($patterns as $p) if(strpos($n,$p)!==false) return $i;
    }
    return null;
}
function parseDateOnly($v){
    $v=trim((string)$v);
    if($v==='') return null;
    if(is_numeric($v)){
        $n=(float)$v;
        if($n>20000 && $n<60000){
            $ts=(int)round(($n-25569)*86400);
            return date('Y-m-d',$ts);
        }
    }
    $v=str_replace(['/','.'],'-',$v);
    $ts=strtotime($v);
    return $ts?date('Y-m-d',$ts):null;
}
function parseTimeOnly($v){
    $v=trim((string)$v);
    if($v==='') return null;
    if(is_numeric($v)){
        $n=(float)$v;
        if($n>=0 && $n<1){
            $seconds=(int)round($n*86400);
            return gmdate('H:i:s',$seconds);
        }
    }
    if(preg_match('/^(\d{1,2})[.:](\d{2})(?:[.:](\d{2}))?$/',$v,$m)){
        $h=(int)$m[1];$i=(int)$m[2];$s=isset($m[3])?(int)$m[3]:0;
        if($h<24&&$i<60&&$s<60)return sprintf('%02d:%02d:%02d',$h,$i,$s);
    }
    $ts=strtotime($v);
    return $ts?date('H:i:s',$ts):null;
}
function parseDateTimeValue($v){
    $v=trim((string)$v);
    if($v==='')return null;
    if(is_numeric($v)){
        $n=(float)$v;
        if($n>20000&&$n<60000){
            $ts=(int)round(($n-25569)*86400);
            return date('Y-m-d H:i:s',$ts);
        }
    }
    $v=str_replace(['/','.'],'-',$v);
    $ts=strtotime($v);
    return $ts?date('Y-m-d H:i:s',$ts):null;
}
function combineDateTime($date,$time){
    $d=parseDateOnly($date);
    if(!$d)return null;
    $t=parseTimeOnly($time);
    return $d.' '.($t?:'00:00:00');
}
function parseMinutes($v){
    $v=trim((string)$v);
    if($v==='')return null;
    if(is_numeric($v))return round((float)$v,2);
    if(preg_match('/(\d+(?:[\.,]\d+)?)\s*(jam|hour|hours)/iu',$v,$m))
        return round((float)str_replace(',','.',$m[1])*60,2);
    if(preg_match('/(\d+(?:[\.,]\d+)?)\s*(menit|minute|minutes)/iu',$v,$m))
        return round((float)str_replace(',','.',$m[1]),2);
    if(preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/',$v,$m))
        return round(((int)$m[1]*60)+(int)$m[2]+((int)($m[3]??0)/60),2);
    return null;
}
function mapSlaStatus($v){
    $n=norm($v);
    if($n==='')return null;
    if(strpos($n,'sesuai')!==false||strpos($n,'tercapai')!==false||strpos($n,'berhasil')!==false||$n==='ok')return 'sesuai';
    if(strpos($n,'tidak')!==false||strpos($n,'gagal')!==false||strpos($n,'terlambat')!==false||strpos($n,'tidak sesuai')!==false)return 'tidak_sesuai';
    return 'belum_dinilai';
}
function docxTables($file){
    if(!class_exists('ZipArchive'))throw new RuntimeException('PHP ZipArchive belum aktif. Aktifkan extension=zip pada php.ini XAMPP.');
    $z=new ZipArchive();
    if($z->open($file)!==true)throw new RuntimeException('File DOCX tidak dapat dibuka.');
    $xml=$z->getFromName('word/document.xml');$z->close();
    if($xml===false)throw new RuntimeException('Dokumen Word tidak memiliki document.xml.');
    $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadXML($xml);libxml_clear_errors();
    $xp=new DOMXPath($dom);$xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $tables=[];
    foreach($xp->query('//w:tbl') as $tbl){
        $rows=[];
        foreach($xp->query('./w:tr',$tbl) as $tr){
            $cells=[];
            foreach($xp->query('./w:tc',$tr) as $tc){
                $parts=[];foreach($xp->query('.//w:t',$tc) as $t)$parts[]=$t->nodeValue;
                $cells[]=trim(preg_replace('/\s+/',' ',implode(' ',$parts)));
            }
            if(array_filter($cells,fn($x)=>$x!==''))$rows[]=$cells;
        }
        if(count($rows)>=2)$tables[]=$rows;
    }
    return $tables;
}
function mapRows($tables){
    foreach($tables as $rows){
        $headers=$rows[0];
        $date=pickCol($headers,['tanggal','tgl','date']);
        $dateTime=pickCol($headers,['tanggal lapor','waktu lapor','datetime lapor']);
        $timeReport=pickCol($headers,['jam lapor','waktu lapor']);
        $issue=pickCol($headers,['jenis kerusakan','kerusakan','masalah','keluhan','gangguan','permasalahan','uraian']);
        $unit=pickCol($headers,['unit','ruangan','bagian','lokasi']);
        $reporter=pickCol($headers,['pelapor','pemohon','user']);
        $item=pickCol($headers,['nama barang','perangkat','barang','sistem']);
        $action=pickCol($headers,['tindak lanjut','tindakan','penanganan','perbaikan','solusi']);
        $startDateTime=pickCol($headers,['tanggal mulai','waktu mulai','datetime mulai']);
        $startTime=pickCol($headers,['jam mulai','waktu mulai','mulai','start','ditangani']);
        $finishDateTime=pickCol($headers,['tanggal selesai','waktu selesai','datetime selesai']);
        $finishTime=pickCol($headers,['jam selesai','waktu selesai','selesai','finish']);
        $duration=pickCol($headers,['durasi','duration']);
        $sla=pickCol($headers,['sla menit','batas sla','sla']);
        $statusSla=pickCol($headers,['status sla','status']);
        $priority=pickCol($headers,['prioritas','priority']);
        $pic=pickCol($headers,['pic','petugas','teknisi']);
        $evidence=pickCol($headers,['bukti','evidence','keterangan','catatan']);
        $sourceWaktu=pickCol($headers,['sumber waktu','sumber jam','asal waktu']);
        if($date===null && $dateTime===null)continue;
        if($issue===null)continue;

        $out=[];
        foreach(array_slice($rows,1) as $row){
            $v=function($idx)use($row){return $idx!==null?trim((string)($row[$idx]??'')):'';};
            $rawDate=$v($dateTime);
            $tanggalLapor=$rawDate!==''?parseDateTimeValue($rawDate):combineDateTime($v($date),$v($timeReport));
            $masalah=$v($issue);
            if(!$tanggalLapor||$masalah==='')continue;

            $rawFinish=$v($finishDateTime);
            $tanggalSelesai=$rawFinish!==''?parseDateTimeValue($rawFinish):combineDateTime($v($date),$v($finishTime));
            $rawStart=$v($startDateTime);
            $tanggalMulai=$rawStart!==''?parseDateTimeValue($rawStart):combineDateTime($v($date),$v($startTime));
            $dur=parseMinutes($v($duration));
            if($dur===null && $tanggalSelesai){
                $a=new DateTime($tanggalLapor);$b=new DateTime($tanggalSelesai);
                $dur=round(($b->getTimestamp()-$a->getTimestamp())/60,2);
            }
            $slaVal=parseMinutes($v($sla));
            $status=mapSlaStatus($v($statusSla));

            $out[]=[
                'tanggal_lapor'=>$tanggalLapor,
                'tanggal_mulai'=>$tanggalMulai,
                'tanggal_selesai'=>$tanggalSelesai,
                'unit_pelapor'=>$v($unit),
                'pelapor'=>$v($reporter),
                'barang'=>$v($item),
                'masalah'=>$masalah,
                'prioritas'=>$v($priority)?:'sedang',
                'durasi_menit'=>$dur,
                'sla_menit'=>$slaVal,
                'status_sla'=>$status,
                'penyelesaian'=>$v($action),
                'pic_text'=>$v($pic),
                'bukti'=>$v($evidence),
                'sumber'=>'laporan',
                'source_waktu'=>$v($sourceWaktu)?:($tanggalMulai&&$tanggalSelesai?'tercatat':'tidak_tersedia')
            ];
        }
        return $out;
    }
    return [];
}

$err=null;$preview=$_SESSION['helpdesk_import_preview']??[];$saved=0;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $act=$_POST['action']??'upload';
        if($act==='upload'){
            if(empty($_FILES['word']['tmp_name'])||$_FILES['word']['error']!==0)throw new RuntimeException('Pilih file Word .docx terlebih dahulu.');
            $ext=strtolower(pathinfo($_FILES['word']['name'],PATHINFO_EXTENSION));
            if($ext!=='docx')throw new RuntimeException('Untuk tahap ini gunakan Word modern berformat .docx.');
            if($_FILES['word']['size']>20*1024*1024)throw new RuntimeException('Maksimal 20 MB.');
            $tables=docxTables($_FILES['word']['tmp_name']);
            $preview=mapRows($tables);
            if(!$preview)throw new RuntimeException('Tabel tidak terbaca. Pastikan ada kolom Tanggal serta Jenis Kerusakan/Masalah.');
            $_SESSION['helpdesk_import_preview']=$preview;
        }elseif($act==='save'){
            $preview=$_SESSION['helpdesk_import_preview']??[];
            if(!$preview)throw new RuntimeException('Data import sudah kosong. Upload ulang.');
            $defaultSla=max(1,(int)($_POST['default_sla']??240));
            $selected=$_POST['selected']??[];
            $ins=$pdo->prepare("INSERT INTO helpdesk_insiden(nomor,tanggal_lapor,tanggal_selesai,unit_pelapor,pelapor,masalah,prioritas,sla_menit,durasi_menit,status,status_sla,penyelesaian,pic_id,sumber,catatan,source_waktu,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

            $picRows=[];
            try{$picRows=$pdo->query("SELECT id,nama FROM pic WHERE aktif=1 ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
            foreach($selected as $idx){
                $idx=(int)$idx;if(!isset($preview[$idx]))continue;$r=$preview[$idx];

                $manualLapor=trim((string)($_POST['jam_lapor'][$idx]??''));
                $manualStart=trim((string)($_POST['jam_mulai'][$idx]??''));
                $manualFinish=trim((string)($_POST['jam_selesai'][$idx]??''));
                $sourceWaktu=trim((string)($_POST['source_waktu'][$idx]??''));
                $lapor=$manualLapor!==''?parseDateTimeValue(str_replace('T',' ',$manualLapor).':00'):$r['tanggal_lapor'];
                $start=$manualStart!==''?parseDateTimeValue(str_replace('T',' ',$manualStart).':00'):$r['tanggal_mulai'];
                $sel=$manualFinish!==''?parseDateTimeValue(str_replace('T',' ',$manualFinish).':00'):$r['tanggal_selesai'];
                $dur=$r['durasi_menit'];
                $sla=$r['sla_menit']?:$defaultSla;
                $ss=$r['status_sla'];
                $status=$sel?'selesai':'open';

                if($sourceWaktu===''||!in_array($sourceWaktu,['tercatat','konfirmasi_petugas','perkiraan','tidak_tersedia'],true)){
                    $sourceWaktu=$r['source_waktu']??'tidak_tersedia';
                }
                if($start && $sel){
                    $a=new DateTime($start);$b=new DateTime($sel);
                    $dur=round(($b->getTimestamp()-$a->getTimestamp())/60,2);
                }
                if($dur!==null){
                    // Durasi aktual/rekonstruksi menjadi sumber penilaian SLA.
                    $ss=$dur<=$sla?'sesuai':'tidak_sesuai';
                }else{
                    $ss='belum_dinilai';
                }
                if($dur!==null && $dur<0){$dur=null;$sel=null;$status='open';$ss='belum_dinilai';}

                $picId=null;
                $picText=trim((string)$r['pic_text']);
                if($picText!==''){
                    foreach($picRows as $pr){
                        if(norm($pr['nama'])===norm($picText)){$picId=(int)$pr['id'];break;}
                    }
                }

                $catatan=[];
                if($start)$catatan[]='Jam mulai: '.$start;
                if($sourceWaktu==='konfirmasi_petugas')$catatan[]='Sumber waktu: Konfirmasi petugas';
                elseif($sourceWaktu==='perkiraan')$catatan[]='Sumber waktu: Perkiraan/rekonstruksi';
                elseif($sourceWaktu==='tercatat')$catatan[]='Sumber waktu: Tercatat di laporan';
                else $catatan[]='Sumber waktu: Tidak tersedia';
                if($r['barang'])$catatan[]='Perangkat: '.$r['barang'];
                if($r['bukti'])$catatan[]='Bukti/Keterangan: '.$r['bukti'];
                if($picText && !$picId)$catatan[]='PIC dari Word: '.$picText;
                $catatan[]='Import laporan maintenance Word';
                $catatanText=implode(' | ',$catatan);

                $nomor='MTN-'.date('YmdHis').'-'.random_int(10,99);
                $ins->execute([
                    $nomor,$lapor,$sel,$r['unit_pelapor'],$r['pelapor'],$r['masalah'],
                    $r['prioritas'],$sla,$dur,$status,$ss,$r['penyelesaian'],$picId,
                    'laporan',$catatanText,$sourceWaktu,$_SESSION['user']['id']??null
                ]);
                $saved++;
            }
            unset($_SESSION['helpdesk_import_preview']);$preview=[];
        }
    }catch(Throwable $e){$err=$e->getMessage();}
}
$page_title='Import Laporan Maintenance';require __DIR__.'/../partials/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
 <div><h2 class="mb-1">Import Laporan Maintenance</h2><div class="text-muted">Import Word ke Helpdesk IM-IT-05 dengan pembacaan tanggal, jam, durasi, SLA, PIC dan status tanpa membuat data yang tidak ada.</div></div>
 <a class="btn btn-outline-secondary" href="index.php">Kembali Helpdesk</a>
</div>
<?php if($err):?><div class="alert alert-danger"><?=h($err)?></div><?php endif;?>
<?php if($saved):?><div class="alert alert-success"><strong><?=$saved?> data berhasil dimasukkan.</strong> Kolom yang tidak tersedia di Word tetap kosong/Belum Dinilai.</div><?php endif;?>
<div class="alert alert-warning"><strong>Format Word yang didukung:</strong> Tanggal, Jam Lapor, Unit, Nama Barang, Jenis Kerusakan, Prioritas, Jam Mulai, Jam Selesai, Durasi (menit), SLA (menit), Status SLA, Tindak Lanjut, PIC, Bukti/Keterangan. Nama kolom boleh sedikit berbeda.</div>
<div class="card shadow-sm border-0 mb-3"><div class="card-body">
<form method="post" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="action" value="upload">
<div class="col-md-8"><label class="form-label">File laporan maintenance (.docx)</label><input class="form-control" type="file" name="word" accept=".docx" required></div>
<div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary">📥 Baca &amp; Tampilkan Data</button></div>
</form></div></div>
<?php if($preview):?>
<div class="card shadow-sm border-0"><div class="card-body">
<h5>Preview <?=count($preview)?> baris</h5>
<form method="post">
<input type="hidden" name="action" value="save">
<div class="row g-2 mb-3">
 <div class="col-md-3"><label class="form-label">SLA default (menit)</label><input name="default_sla" type="number" min="1" value="240" class="form-control"></div>
 <div class="col-md-9 small text-muted d-flex align-items-end">Jam Lapor, Jam Mulai, dan Jam Selesai dapat dikoreksi langsung pada tabel Preview. Jika waktunya berasal dari ingatan/konfirmasi petugas, pilih sumber waktu yang sesuai. Jika Word tidak memiliki SLA, nilai ini dipakai. Jika Word sudah memiliki SLA, nilai Word diprioritaskan. Status SLA dari Word juga dipertahankan.</div>
</div>
<div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr>
<th><input type="checkbox" checked onclick="document.querySelectorAll('.pick').forEach(x=>x.checked=this.checked)"></th>
<th>Tanggal/Jam Lapor</th><th>Jam Mulai</th><th>Jam Selesai</th><th>Unit</th><th>Masalah</th><th>Durasi</th><th>SLA</th><th>PIC</th><th>Status SLA</th><th>Sumber Waktu</th><th>Tindakan</th>
</tr></thead><tbody>
<?php foreach($preview as $n=>$r):?>
<tr>
<td><input class="pick" type="checkbox" name="selected[]" value="<?=$n?>" checked></td>
<td><input class="form-control form-control-sm" type="datetime-local" name="jam_lapor[<?=$n?>]" value="<?=h($r['tanggal_lapor']?date('Y-m-d\\TH:i',strtotime($r['tanggal_lapor'])):'')?>"></td>
<td><input class="form-control form-control-sm" type="datetime-local" name="jam_mulai[<?=$n?>]" value="<?=h($r['tanggal_mulai']?date('Y-m-d\\TH:i',strtotime($r['tanggal_mulai'])):'')?>"></td>
<td><input class="form-control form-control-sm" type="datetime-local" name="jam_selesai[<?=$n?>]" value="<?=h($r['tanggal_selesai']?date('Y-m-d\\TH:i',strtotime($r['tanggal_selesai'])):'')?>"></td>
<td><?=h($r['unit_pelapor'])?></td><td><?=h($r['masalah'])?></td>
<td><?=h($r['durasi_menit']!==null?$r['durasi_menit'].' menit':'')?></td>
<td><?=h($r['sla_menit']!==null?$r['sla_menit'].' menit':'default')?></td>
<td><?=h($r['pic_text'])?></td>
<td><?=h($r['status_sla']??'BELUM DINILAI')?></td>
<td>
<select class="form-select form-select-sm" name="source_waktu[<?=$n?>]">
<option value="tercatat" <?=($r['source_waktu']??'')==='tercatat'?'selected':''?>>Tercatat di laporan</option>
<option value="konfirmasi_petugas" <?=($r['source_waktu']??'')==='konfirmasi_petugas'?'selected':''?>>Konfirmasi petugas</option>
<option value="perkiraan" <?=($r['source_waktu']??'')==='perkiraan'?'selected':''?>>Perkiraan / rekonstruksi</option>
<option value="tidak_tersedia" <?=($r['source_waktu']??'')==='tidak_tersedia'?'selected':''?>>Tidak tersedia</option>
</select>
</td>
<td><?=h($r['penyelesaian'])?></td>
</tr>
<?php endforeach;?>
</tbody></table></div>
<button class="btn btn-success">💾 Masukkan ke Helpdesk</button>
<a class="btn btn-outline-secondary" href="import.php">Batalkan Preview</a>
</form></div></div>
<?php endif;?>
<?php require __DIR__.'/../partials/footer.php';