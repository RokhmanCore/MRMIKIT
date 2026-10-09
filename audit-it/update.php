<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: ./');exit;}
$id=(int)($_POST['id']??0);
$s=$pdo->prepare('SELECT id FROM audit_monitoring_it WHERE id=?'); $s->execute([$id]);
if(!$id||!$s->fetch()) die('Data monitoring tidak ditemukan.');
$fields=['tanggal_monitoring','periode','petugas','kepatuhan_status','kepatuhan_catatan','jaringan_lan','jaringan_wifi','jaringan_internet','jaringan_server','jaringan_catatan','rme_login','rme_data_pasien','rme_soap','rme_resep','rme_pencarian','rme_catatan','keluhan_status','keluhan_detail','kesimpulan','tindak_lanjut'];
$data=[]; foreach($fields as $f){$data[$f]=trim((string)($_POST[$f]??''));}
if($data['tanggal_monitoring']===''||$data['petugas']===''){header('Location: edit.php?id='.$id);exit;}
if($data['keluhan_status']!=='Ada'){$data['keluhan_status']='Tidak ada';if($data['keluhan_detail']==='')$data['keluhan_detail']='Tidak ditemukan keluhan pengguna selama periode monitoring.';}
$sets=implode(',',array_map(fn($f)=>$f.'=?',array_keys($data)));
$st=$pdo->prepare('UPDATE audit_monitoring_it SET '.$sets.' WHERE id=?');$st->execute([...array_values($data),$id]);
$uploadDir=__DIR__.'/uploads/'.$id;
if(isset($_FILES['evidence'])&&is_array($_FILES['evidence']['name'])){
 if(!is_dir($uploadDir)) @mkdir($uploadDir,0775,true);
 $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
 $fi=new finfo(FILEINFO_MIME_TYPE);
 foreach($_FILES['evidence']['name'] as $i=>$name){
  if(($_FILES['evidence']['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;
  $tmp=$_FILES['evidence']['tmp_name'][$i];$size=(int)$_FILES['evidence']['size'][$i];if($size<=0||$size>10*1024*1024)continue;
  $mime=$fi->file($tmp);if(!isset($allowed[$mime]))continue;
  $safe=preg_replace('/[^A-Za-z0-9._-]/','_',basename($name));$stored=date('YmdHis').'_'.bin2hex(random_bytes(4)).'_'.$safe;
  if(move_uploaded_file($tmp,$uploadDir.'/'.$stored)){
   $q=$pdo->prepare('INSERT INTO audit_monitoring_it_evidence (audit_id,nama_file,file_path,file_mime,file_size) VALUES (?,?,?,?,?)');
   $q->execute([$id,$name,'uploads/'.$id.'/'.$stored,$mime,$size]);
  }
 }
}
header('Location: detail.php?id='.$id.'&updated=1');exit;
