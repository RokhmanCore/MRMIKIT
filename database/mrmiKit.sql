CREATE DATABASE IF NOT EXISTS mrmiKit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mrmiKit;

CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, nama VARCHAR(150) NOT NULL, role VARCHAR(30) NOT NULL DEFAULT 'admin', aktif TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
INSERT IGNORE INTO users(username,password_hash,nama,role) VALUES ('admin', '$2y$12$Ha.uANREfvtDU40P.dbyteFioAFpu9HS/zS2SH693O3uOW.AMfDRS', 'Administrator IT', 'admin');

CREATE TABLE IF NOT EXISTS elemen_penilaian (id INT AUTO_INCREMENT PRIMARY KEY, kode VARCHAR(30) UNIQUE NOT NULL, kategori VARCHAR(100), judul VARCHAR(255) NOT NULL, deskripsi TEXT, urutan INT NOT NULL DEFAULT 0);
INSERT IGNORE INTO elemen_penilaian(kode,kategori,judul,deskripsi,urutan) VALUES
('MRMIK 13.a','MRMIK 13','Regulasi penyelenggaraan TI kesehatan','Regulasi internal penyelenggaraan teknologi informasi kesehatan.',1),
('MRMIK 13.b','MRMIK 13','Penerapan SIMRS','Penerapan sistem informasi manajemen rumah sakit.',2),
('MRMIK 13.c','MRMIK 13','Unit penyelenggara SIMRS dan staf kompeten','Unit, penanggung jawab, SDM, kompetensi dan uraian tugas.',3),
('MRMIK 13.d','MRMIK 13','Integrasi data klinis dan nonklinis','Peta dan bukti integrasi sistem informasi.',4),
('MRMIK 13.e','MRMIK 13','Evaluasi efektivitas RME dan perbaikan','Evaluasi efektivitas RME, temuan dan tindak lanjut.',5),
('MRMIK 13.1.a','MRMIK 13.1','Prosedur downtime','Prosedur saat sistem tidak tersedia.',6),
('MRMIK 13.1.b','MRMIK 13.1','Pelatihan staf downtime','Pelatihan dan pemahaman staf terkait downtime.',7),
('MRMIK 13.1.c','MRMIK 13.1','Evaluasi setelah downtime','Evaluasi kejadian downtime dan perbaikan.',8);

CREATE TABLE IF NOT EXISTS evidence_requirement (id INT AUTO_INCREMENT PRIMARY KEY, ep_id INT NOT NULL, kode VARCHAR(30) UNIQUE NOT NULL, nama VARCHAR(255) NOT NULL, jenis ENUM('R','D','O') NOT NULL, wajib TINYINT(1) DEFAULT 1, urutan INT DEFAULT 0, FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS pic (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(150) NOT NULL, unit VARCHAR(150), jabatan VARCHAR(150), email VARCHAR(150), aktif TINYINT(1) DEFAULT 1);
CREATE TABLE IF NOT EXISTS dokumen (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(255) NOT NULL, kategori VARCHAR(100), nomor VARCHAR(100), versi VARCHAR(30) DEFAULT '1.0', tanggal_berlaku DATE NULL, tanggal_review DATE NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255), status ENUM('lengkap','perlu_review','belum_lengkap') DEFAULT 'lengkap', pic_id INT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(pic_id) REFERENCES pic(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS dokumen_versi (id INT AUTO_INCREMENT PRIMARY KEY, dokumen_id INT NOT NULL, versi VARCHAR(30) NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255), catatan TEXT, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS dokumen_ep (dokumen_id INT NOT NULL, ep_id INT NOT NULL, PRIMARY KEY(dokumen_id,ep_id), FOREIGN KEY(dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE, FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS bukti_implementasi (id INT AUTO_INCREMENT PRIMARY KEY, jenis ENUM('R','D','O') NOT NULL, judul VARCHAR(255) NOT NULL, deskripsi TEXT, tanggal_bukti DATE NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255), pic_id INT NULL, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(pic_id) REFERENCES pic(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS bukti_ep (bukti_id INT NOT NULL, ep_id INT NOT NULL, requirement_id INT NULL, PRIMARY KEY(bukti_id,ep_id), FOREIGN KEY(bukti_id) REFERENCES bukti_implementasi(id) ON DELETE CASCADE, FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE CASCADE, FOREIGN KEY(requirement_id) REFERENCES evidence_requirement(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS downtime (id INT AUTO_INCREMENT PRIMARY KEY, mulai DATETIME NOT NULL, selesai DATETIME NULL, jenis VARCHAR(30) NOT NULL, penyebab TEXT, unit_terdampak TEXT, tindakan TEXT, evaluasi TEXT, tindak_lanjut TEXT, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS audit_log (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, aksi VARCHAR(100), objek VARCHAR(100), objek_id INT NULL, detail TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

INSERT IGNORE INTO pic(nama,unit,jabatan) VALUES ('Administrator IT','IT','Administrator');

INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13a-R1','Regulasi/kebijakan penyelenggaraan TI kesehatan','R',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.a';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13b-D1','Bukti penerapan SIMRS','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.b';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13c-R1','SK/struktur unit penyelenggara SIMRS','R',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.c';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13c-D1','Bukti kompetensi dan uraian tugas staf','D',1,2 FROM elemen_penilaian WHERE kode='MRMIK 13.c';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13d-D1','Diagram/peta integrasi sistem','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.d';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13d-O1','Bukti implementasi integrasi','O',1,2 FROM elemen_penilaian WHERE kode='MRMIK 13.d';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'13e-D1','Evaluasi efektivitas RME dan tindak lanjut','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.e';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'131a-R1','SOP/prosedur downtime','R',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.1.a';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'131b-D1','Bukti pelatihan staf downtime','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.1.b';
INSERT IGNORE INTO evidence_requirement(ep_id,kode,nama,jenis,wajib,urutan) SELECT id,'131c-D1','Evaluasi kejadian downtime dan tindak lanjut','D',1,1 FROM elemen_penilaian WHERE kode='MRMIK 13.1.c';
