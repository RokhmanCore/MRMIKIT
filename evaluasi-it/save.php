<?php
require_once __DIR__.'/../config/config.php'; require_once __DIR__.'/../config/auth.php'; require_login();
$d=[]; foreach(['tahun','triwulan','tanggal_evaluasi','petugas','ringkasan','statistik_gangguan','evaluasi_kinerja','rekomendasi','tindak_lanjut'] as $f) $d[$f]=trim((string)($_POST[$f]??''));
if(!$d['tahun']||!$d['triwulan']||!$d['tanggal_evaluasi']||!$d['petugas']) die('Data wajib belum lengkap.');
$d['periode_label']='Q'.$d['triwulan'].' '.$d['tahun'];
$q=$pdo->prepare('INSERT INTO evaluasi_it (tahun,triwulan,periode_label,ringkasan,statistik_gangguan,evaluasi_kinerja,rekomendasi,tindak_lanjut,petugas,tanggal_evaluasi) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE ringkasan=VALUES(ringkasan),statistik_gangguan=VALUES(statistik_gangguan),evaluasi_kinerja=VALUES(evaluasi_kinerja),rekomendasi=VALUES(rekomendasi),tindak_lanjut=VALUES(tindak_lanjut),petugas=VALUES(petugas),tanggal_evaluasi=VALUES(tanggal_evaluasi)');
$q->execute([$d['tahun'],$d['triwulan'],$d['periode_label'],$d['ringkasan'],$d['statistik_gangguan'],$d['evaluasi_kinerja'],$d['rekomendasi'],$d['tindak_lanjut'],$d['petugas'],$d['tanggal_evaluasi']]);
header('Location: ./');