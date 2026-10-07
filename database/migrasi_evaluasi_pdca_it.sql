-- Jalankan sekali pada database mrmiKit
CREATE TABLE IF NOT EXISTS evaluasi_it (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tahun SMALLINT NOT NULL,
 triwulan TINYINT NOT NULL,
 periode_label VARCHAR(30) NOT NULL,
 ringkasan TEXT NULL,
 statistik_gangguan TEXT NULL,
 evaluasi_kinerja TEXT NULL,
 rekomendasi TEXT NULL,
 tindak_lanjut TEXT NULL,
 petugas VARCHAR(150) NOT NULL,
 tanggal_evaluasi DATE NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 UNIQUE KEY uq_evaluasi_periode(tahun,triwulan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pdca_it (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tanggal_temuan DATE NOT NULL,
 sumber VARCHAR(100) NOT NULL DEFAULT 'Monitoring IT',
 masalah TEXT NOT NULL,
 analisis_penyebab TEXT NULL,
 rencana_tindakan TEXT NULL,
 tindakan_perbaikan TEXT NULL,
 pic VARCHAR(150) NOT NULL,
 target_selesai DATE NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'Open',
 hasil_verifikasi TEXT NULL,
 bukti TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(id),
 KEY idx_pdca_tanggal(tanggal_temuan),
 KEY idx_pdca_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;