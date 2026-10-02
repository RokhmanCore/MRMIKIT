CREATE DATABASE IF NOT EXISTS mrmiKit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mrmiKit;

CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, nama VARCHAR(150) NOT NULL, role VARCHAR(30) NOT NULL DEFAULT 'admin', aktif TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
INSERT INTO users(username,password_hash,nama,role) VALUES ('admin', '$2y$12$Ha.uANREfvtDU40P.dbyteFioAFpu9HS/zS2SH693O3uOW.AMfDRS', 'Administrator IT', 'admin');

CREATE TABLE elemen_penilaian (id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30), kode VARCHAR(30) UNIQUE NOT NULL, kategori VARCHAR(100), judul VARCHAR(255) NOT NULL, deskripsi TEXT, urutan INT NOT NULL DEFAULT 0);
INSERT INTO elemen_penilaian(kode,kategori,judul,deskripsi,urutan) VALUES
('MRMIK 13.a','MRMIK 13','Regulasi penyelenggaraan TI kesehatan','Regulasi internal penyelenggaraan teknologi informasi kesehatan.',1),
('MRMIK 13.b','MRMIK 13','Penerapan SIMRS','Penerapan sistem informasi manajemen rumah sakit.',2),
('MRMIK 13.c','MRMIK 13','Unit penyelenggara SIMRS dan staf kompeten','Unit, penanggung jawab, SDM, kompetensi dan uraian tugas.',3),
('MRMIK 13.d','MRMIK 13','Integrasi data klinis dan nonklinis','Peta dan bukti integrasi sistem informasi.',4),
('MRMIK 13.e','MRMIK 13','Evaluasi efektivitas RME dan perbaikan','Evaluasi efektivitas RME, temuan dan tindak lanjut.',5),
('MRMIK 13.1.a','MRMIK 13.1','Prosedur downtime','Prosedur saat sistem tidak tersedia.',6),
('MRMIK 13.1.b','MRMIK 13.1','Pelatihan staf downtime','Pelatihan dan pemahaman staf terkait downtime.',7),
('MRMIK 13.1.c','MRMIK 13.1','Evaluasi setelah downtime','Evaluasi kejadian downtime dan perbaikan.',8);

CREATE TABLE dokumen (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(255) NOT NULL, kategori VARCHAR(100), versi VARCHAR(30), tanggal_berlaku DATE NULL, tanggal_review DATE NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255), status ENUM('lengkap','perlu_review','belum_lengkap') DEFAULT 'lengkap', created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE dokumen_ep (dokumen_id INT NOT NULL, ep_id INT NOT NULL, PRIMARY KEY(dokumen_id,ep_id), CONSTRAINT fk_de_d FOREIGN KEY(dokumen_id) REFERENCES dokumen(id) ON DELETE CASCADE, CONSTRAINT fk_de_ep FOREIGN KEY(ep_id) REFERENCES elemen_penilaian(id) ON DELETE CASCADE);
CREATE TABLE bukti_implementasi (id INT AUTO_INCREMENT PRIMARY KEY, jenis ENUM('R','D','O') NOT NULL, judul VARCHAR(255) NOT NULL, tanggal_bukti DATE NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255), created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE downtime (id INT AUTO_INCREMENT PRIMARY KEY, mulai DATETIME NOT NULL, selesai DATETIME NULL, jenis VARCHAR(30) NOT NULL, penyebab TEXT, unit_terdampak TEXT, tindakan TEXT, evaluasi TEXT, tindak_lanjut TEXT, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE audit_log (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, aksi VARCHAR(100), objek VARCHAR(100), objek_id INT NULL, detail TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

-- Konfigurasi contoh hak akses file direktori.
