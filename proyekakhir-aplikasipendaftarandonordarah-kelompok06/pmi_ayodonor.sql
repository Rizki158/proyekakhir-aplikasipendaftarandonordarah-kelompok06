CREATE DATABASE IF NOT EXISTS pmi_ayodonor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pmi_ayodonor;

CREATE TABLE provinsi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_provinsi VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_provinsi INT NOT NULL,
    nama_kota VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_provinsi) REFERENCES provinsi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pendonor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    kode_donor VARCHAR(50) NOT NULL,
    tempat_lahir VARCHAR(100) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    jenis_kelamin ENUM('Laki-laki','Perempuan') NOT NULL,
    pekerjaan VARCHAR(100) NOT NULL,
    keterangan_pekerjaan TEXT DEFAULT NULL,
    alamat TEXT NOT NULL,
    id_provinsi INT DEFAULT NULL,
    id_kota INT DEFAULT NULL,
    telepon VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    status_donor ENUM('Sudah pernah','Belum pernah') NOT NULL,
    tanggal_donor_terakhir DATE DEFAULT NULL,
    persentase_donor VARCHAR(5) DEFAULT NULL,
    berat_badan VARCHAR(10) NOT NULL,
    penyakit_bawaan TEXT DEFAULT NULL,
    konsumsi_obat ENUM('Ada','Tidak') NOT NULL DEFAULT 'Tidak',
    nama_obat TEXT DEFAULT NULL,
    riwayat_operasi TINYINT(1) DEFAULT 0,
    riwayat_vaksinasi TINYINT(1) DEFAULT 0,
    kondisi_wanita VARCHAR(100) DEFAULT NULL,
    konfirmasi TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (id_provinsi) REFERENCES provinsi(id) ON DELETE SET NULL,
    FOREIGN KEY (id_kota) REFERENCES kota(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stok_darah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_kota INT NOT NULL,
    golongan_darah ENUM('A','B','O','AB') NOT NULL,
    jumlah INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kota) REFERENCES kota(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE log_stok (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    id_kota        INT  NOT NULL,
    golongan_darah ENUM('A','B','O','AB') NOT NULL,
    perubahan      INT  NOT NULL,
    jenis          ENUM('tambah','kurang') NOT NULL,
    keterangan     VARCHAR(255) DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kota) REFERENCES kota(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS jadwal_konsultasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT DEFAULT NULL,
    nama VARCHAR(100) NOT NULL,
    tgl_lahir DATE NOT NULL,
    telepon VARCHAR(20) NOT NULL,
    rekam_medis TEXT DEFAULT NULL,
    tanggal_tes DATE NOT NULL,
    jam_tes TIME NOT NULL,
    lokasi VARCHAR(255) NOT NULL,
    puasa VARCHAR(255) DEFAULT NULL,
    keluhan TEXT DEFAULT NULL,
    obat TEXT DEFAULT NULL,
    alergi TEXT DEFAULT NULL,
    kondisi_khusus TEXT DEFAULT NULL,
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    catatan_admin TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE INDEX idx_jadwal_user ON jadwal_konsultasi(id_user);
CREATE INDEX idx_jadwal_tgl ON jadwal_konsultasi(tanggal_tes);
CREATE INDEX idx_jadwal_status ON jadwal_konsultasi(status);


-- Data provinsi
INSERT INTO provinsi (nama_provinsi) VALUES
('DKI Jakarta'),('Jawa Barat'),('Jawa Tengah'),('Jawa Timur'),
('Banten'),('Bali'),('Sumatera Utara'),('Sumatera Selatan'),
('Kalimantan Barat'),('Sulawesi Selatan');

-- Data kota
INSERT INTO kota (id_provinsi, nama_kota) VALUES
(1,'Jakarta Pusat'),(1,'Jakarta Selatan'),(1,'Jakarta Barat'),
(2,'Bandung'),(2,'Bogor'),(2,'Bekasi'),
(3,'Semarang'),(3,'Surakarta'),
(4,'Surabaya'),(4,'Malang'),(4,'Nganjuk'),
(5,'Tangerang'),(5,'Serang'),
(6,'Denpasar'),(6,'Badung'),
(7,'Medan'),(8,'Palembang'),
(9,'Pontianak'),(10,'Makassar');

-- Stok darah awal per kota
INSERT INTO stok_darah (id_kota, golongan_darah, jumlah)
SELECT k.id, g.gol, 0
FROM kota k
CROSS JOIN (SELECT 'A' as gol UNION SELECT 'B' UNION SELECT 'O' UNION SELECT 'AB') g;

-- Admin default (password: admin123)
INSERT INTO users (nama, email, password, role) VALUES
('Admin PMI', 'admin@pmi.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

