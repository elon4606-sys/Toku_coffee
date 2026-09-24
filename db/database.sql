DROP DATABASE IF EXISTS toku_coffee;

CREATE DATABASE toku_coffee
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE toku_coffee;

-- =====================================================
-- USERS
-- =====================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
    status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    failed_attempts INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- KATEGORI
-- =====================================================

CREATE TABLE kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- PRODUK
-- =====================================================

CREATE TABLE produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_id INT NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    nama_produk VARCHAR(150) NOT NULL,
    deskripsi TEXT,
    harga DECIMAL(15,2) NOT NULL DEFAULT 0,
    harga_beli DECIMAL(15,2) NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    stok_minimum INT NOT NULL DEFAULT 0,
    satuan VARCHAR(30) DEFAULT 'Pcs',
    gambar VARCHAR(255),
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_produk_kategori
        FOREIGN KEY (kategori_id)
        REFERENCES kategori(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- =====================================================
-- SUPPLIER
-- =====================================================

CREATE TABLE supplier (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_supplier VARCHAR(150) NOT NULL,
    kontak VARCHAR(50),
    email VARCHAR(150),
    alamat TEXT,
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- GUDANG
-- =====================================================

CREATE TABLE gudang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_gudang VARCHAR(100) NOT NULL,
    alamat TEXT,
    penanggung_jawab VARCHAR(150),
    status ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- INVENTORY
-- =====================================================

CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produk_id INT NOT NULL,
    gudang_id INT NOT NULL,
    stok INT NOT NULL DEFAULT 0,
    stok_minimum INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_inventory (
        produk_id,
        gudang_id
    ),

    CONSTRAINT fk_inventory_produk
        FOREIGN KEY (produk_id)
        REFERENCES produk(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_inventory_gudang
        FOREIGN KEY (gudang_id)
        REFERENCES gudang(id)
        ON DELETE CASCADE
);

-- =====================================================
-- PESANAN
-- =====================================================

CREATE TABLE pesanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    tanggal_pesanan DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,

    status ENUM(
        'Menunggu',
        'Diproses',
        'Dikirim',
        'Selesai',
        'Dibatalkan'
    ) DEFAULT 'Menunggu',

    alamat_pengiriman TEXT,
    catatan TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_pesanan_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
);

-- =====================================================
-- DETAIL PESANAN
-- =====================================================

CREATE TABLE detail_pesanan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesanan_id INT NOT NULL,
    produk_id INT NOT NULL,
    jumlah INT NOT NULL,
    harga DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,

    CONSTRAINT fk_detail_pesanan
        FOREIGN KEY (pesanan_id)
        REFERENCES pesanan(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_detail_produk
        FOREIGN KEY (produk_id)
        REFERENCES produk(id)
        ON DELETE RESTRICT
);

-- =====================================================
-- PEMBAYARAN
-- =====================================================

CREATE TABLE pembayaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesanan_id INT NOT NULL,

    metode_pembayaran ENUM(
        'Cash',
        'Transfer Bank',
        'QRIS',
        'E-Wallet',
        'COD'
    ) NOT NULL,

    jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,

    status ENUM(
        'Menunggu',
        'Dibayar',
        'Gagal',
        'Dikembalikan'
    ) DEFAULT 'Menunggu',

    tanggal_pembayaran DATETIME NULL,
    bukti_pembayaran VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pembayaran_pesanan
        FOREIGN KEY (pesanan_id)
        REFERENCES pesanan(id)
        ON DELETE CASCADE
);

-- =====================================================
-- PEMBELIAN
-- =====================================================

CREATE TABLE pembelian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_pembelian VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    tanggal_pembelian DATE NOT NULL,
    total DECIMAL(15,2) DEFAULT 0,

    status ENUM(
        'Draft',
        'Dipesan',
        'Diterima',
        'Dibatalkan'
    ) DEFAULT 'Draft',

    catatan TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pembelian_supplier
        FOREIGN KEY (supplier_id)
        REFERENCES supplier(id)
        ON DELETE RESTRICT
);

-- =====================================================
-- DETAIL PEMBELIAN
-- =====================================================

CREATE TABLE detail_pembelian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pembelian_id INT NOT NULL,
    produk_id INT NOT NULL,
    jumlah INT NOT NULL,
    harga_beli DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,

    CONSTRAINT fk_detail_pembelian
        FOREIGN KEY (pembelian_id)
        REFERENCES pembelian(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_detail_pembelian_produk
        FOREIGN KEY (produk_id)
        REFERENCES produk(id)
        ON DELETE RESTRICT
);

-- =====================================================
-- MUTASI STOK
-- =====================================================

CREATE TABLE mutasi_stok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produk_id INT NOT NULL,
    gudang_id INT NOT NULL,
    user_id INT NULL,

    tipe ENUM(
        'Masuk',
        'Keluar',
        'Penyesuaian',
        'Transfer'
    ) NOT NULL,

    jumlah INT NOT NULL,

    stok_sebelum INT NOT NULL DEFAULT 0,
    stok_sesudah INT NOT NULL DEFAULT 0,

    keterangan TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mutasi_produk
        FOREIGN KEY (produk_id)
        REFERENCES produk(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_mutasi_gudang
        FOREIGN KEY (gudang_id)
        REFERENCES gudang(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_mutasi_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
);

-- =====================================================
-- KERANJANG
-- =====================================================

CREATE TABLE keranjang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    produk_id INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_cart (
        user_id,
        produk_id
    ),

    CONSTRAINT fk_keranjang_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_keranjang_produk
        FOREIGN KEY (produk_id)
        REFERENCES produk(id)
        ON DELETE CASCADE
);

-- =====================================================
-- DATA KATEGORI
-- =====================================================

INSERT INTO kategori
(nama_kategori, deskripsi)
VALUES
('Kopi', 'Produk kopi Toku Coffee'),
('Minuman', 'Berbagai jenis minuman'),
('Bahan Baku', 'Bahan baku untuk produksi'),
('Aksesoris', 'Aksesoris kopi'),
('Peralatan', 'Peralatan coffee shop');

-- =====================================================
-- DATA GUDANG
-- =====================================================

INSERT INTO gudang
(nama_gudang, alamat, penanggung_jawab)
VALUES
('Gudang Utama', 'Jakarta', 'Admin Gudang'),
('Gudang Jakarta', 'Jakarta Selatan', 'Staff Gudang'),
('Gudang Bandung', 'Bandung', 'Staff Gudang');

-- =====================================================
-- DATA SUPPLIER
-- =====================================================

INSERT INTO supplier
(nama_supplier, kontak, email, alamat)
VALUES
(
    'PT Kopi Nusantara',
    '081234567890',
    'info@kopinusantara.com',
    'Jakarta'
),
(
    'CV Bahan Kopi Indonesia',
    '081298765432',
    'info@bahankopi.com',
    'Bandung'
),
(
    'PT Peralatan Coffee',
    '082112223333',
    'sales@peralatancoffee.com',
    'Surabaya'
);

-- =====================================================
-- DATA USER ADMIN
-- =====================================================

INSERT INTO users
(
    full_name,
    username,
    email,
    password,
    role,
    status
)
VALUES
(
    'Administrator Toku Coffee',
    'admin',
    'admin@tokucoffee.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC5m1W3Q7z7Z5pJ8Y8u',
    'admin',
    'active'
);

-- =====================================================
-- DATA PRODUK
-- =====================================================

INSERT INTO produk
(
    kategori_id,
    sku,
    nama_produk,
    deskripsi,
    harga,
    harga_beli,
    stok,
    stok_minimum,
    satuan
)
VALUES

(
    2,
    'SKU-001',
    'Kopi Arabica Premium',
    'Kopi Arabica premium pilihan',
    85000,
    60000,
    128,
    30,
    'Pcs'
),

(
    2,
    'SKU-002',
    'Kopi Robusta Nusantara',
    'Kopi Robusta asli Nusantara',
    65000,
    45000,
    76,
    25,
    'Pcs'
),

(
    2,
    'SKU-003',
    'Teh Hijau Premium',
    'Teh hijau premium',
    45000,
    30000,
    18,
    25,
    'Pcs'
),

(
    3,
    'SKU-004',
    'Gula Aren Organik',
    'Gula aren organik',
    38000,
    25000,
    0,
    20,
    'Pcs'
),

(
    3,
    'SKU-005',
    'Cokelat Dark 70%',
    'Cokelat dark premium 70%',
    55000,
    35000,
    42,
    15,
    'Pcs'
),

(
    4,
    'SKU-006',
    'Botol Minum Stainless',
    'Botol minum stainless Toku Coffee',
    125000,
    80000,
    9,
    20,
    'Pcs'
),

(
    4,
    'SKU-007',
    'Mug Keramik ERP',
    'Mug keramik Toku Coffee',
    75000,
    45000,
    93,
    20,
    'Pcs'
),

(
    5,
    'SKU-008',
    'Mesin Grinder Kopi',
    'Mesin grinder kopi profesional',
    850000,
    650000,
    7,
    5,
    'Unit'
),

(
    5,
    'SKU-009',
    'Filter Kopi Paper',
    'Filter kopi paper',
    25000,
    15000,
    215,
    50,
    'Pack'
),

(
    3,
    'SKU-010',
    'Susu Bubuk Premium',
    'Susu bubuk premium',
    95000,
    70000,
    12,
    20,
    'Pcs'
);

-- =====================================================
-- DATA INVENTORY
-- =====================================================

INSERT INTO inventory
(produk_id, gudang_id, stok, stok_minimum)
VALUES

(1, 1, 128, 30),
(2, 1, 76, 25),
(3, 2, 18, 25),
(4, 2, 0, 20),
(5, 3, 42, 15),
(6, 1, 9, 20),
(7, 3, 93, 20),
(8, 1, 7, 5),
(9, 2, 215, 50),
(10, 1, 12, 20);
