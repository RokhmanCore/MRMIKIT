USE mrmiKit;

CREATE TABLE IF NOT EXISTS mutu_bukti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    capaian_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NULL,
    size_bytes BIGINT NOT NULL DEFAULT 0,
    catatan TEXT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (capaian_id) REFERENCES mutu_capaian(id) ON DELETE CASCADE
);