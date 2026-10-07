<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/auth.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ./'); exit; }
$fields=['tanggal_monitoring','periode','petugas','kepatuhan_status','kepatuhan_catatan','jaringan_lan','jaringan_wifi','jaringan_internet','jaringan_server','jaringan_catatan','rme_login','rme_data_pasien','rme_soap','rme_resep','rme_pencarian','rme_catatan','keluhan_status','keluhan_detail','kesimpulan','tindak_lanjut'];
$data=[]; foreach($fields as $f){$data[$f]=trim((string)($_POST[$f]??''));}
if($data['tanggal_monitoring']===''||$data['petugas']===''){header('Location: form.php?error=required');exit;}
$sql='INSERT INTO audit_monitoring_it ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')';
$stmt=$pdo->prepare($sql); $stmt->execute(array_values($data));
$id=(int)$pdo->lastInsertId();
$uploadDir=__DIR__.'/uploads/'.$id; if(!is_dir($uploadDir)) @mkdir($uploadDir,0775,true);
if(isset($_FILES['evidence']) && is_array($_FILES['evidence']['name'])){
 foreach($_FILES['evidence']['name'] as $i=>$name){ if($_FILES['evidence']['error'][$i]!==UPLOAD_ERR_OK) continue; $tmp=$_FILES['evidence']['tmp_name'][$i]; $safe=preg_replace('/[^A-Za-z0-9._-]/','_',basename($name)); $stored=date('YmdHis').'_'.$safe; if(move_uploaded_file($tmp,$uploadDir.'/'.$stored)){ $q=$pdo->prepare('INSERT INTO audit_monitoring_it_evidence (audit_id,nama_file,file_path,file_mime,file_size) VALUES (?,?,?,?,?)'); $q->execute([$id,$name,'uploads/'.$id.'/'.$stored,$_FILES['evidence']['type'][$i]??null,(int)$_FILES['evidence']['size'][$i]]); } }
}
header('Location: detail.php?id='.$id.'&saved=1'); exit;
