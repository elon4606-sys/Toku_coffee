<?php

/**
 * Toku Coffee ERP - Setup Sales / Pengiriman
 *
 * Fungsi:
 * - Membuat tabel kategori_pengiriman
 * - Membuat tabel jasa_pengiriman
 * - Membuat tabel pengiriman_sales
 * - Membuat tabel pengiriman_tracking
 * - Menambahkan kolom fitur pengiriman jika belum ada
 * - Menyediakan kategori Reguler
 * - Menyediakan jasa: GoSend, ShopeeFood, GoFood, GrabExpress
 * - Menyediakan koordinat GPS demo
 *
 * Catatan:
 * Koordinat GPS di file ini adalah koordinat DEMO untuk kebutuhan sistem.
 * Bukan koneksi GPS asli dari Gojek, Shopee, GoFood, atau Grab.
 */

if (!function_exists('setupSalesPengiriman')) {

    /**
     * Mengecek apakah tabel tersedia.
     */
    function salesTableExists(mysqli $conn, string $table): bool
    {
        $table = $conn->real_escape_string($table);

        $result = $conn->query("SHOW TABLES LIKE '{$table}'");

        return $result && $result->num_rows > 0;
    }

    /**
     * Mengecek apakah kolom tersedia.
     */
    function salesColumnExists(mysqli $conn, string $table, string $column): bool
    {
        $table = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);

        $result = $conn->query(
            "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'"
        );

        return $result && $result->num_rows > 0;
    }

    /**
     * Menambahkan kolom jika belum ada.
     */
    function salesAddColumn(
        mysqli $conn,
        string $table,
        string $column,
        string $definition
    ): void {
        if (!salesColumnExists($conn, $table, $column)) {
            $conn->query(
                "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}"
            );
        }
    }

    /**
     * Menyiapkan seluruh struktur Sales / Pengiriman.
     */
    function setupSalesPengiriman(mysqli $conn): void
    {
        /* =====================================================
           1. KATEGORI PENGIRIMAN
        ===================================================== */

        $conn->query("
            CREATE TABLE IF NOT EXISTS kategori_pengiriman (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kode VARCHAR(20) NOT NULL UNIQUE,
                nama_kategori VARCHAR(100) NOT NULL,
                deskripsi TEXT NULL,
                ikon VARCHAR(100) DEFAULT 'fa-truck',
                estimasi VARCHAR(100) DEFAULT '1-2 hari',
                tarif_mulai DECIMAL(15,2) DEFAULT 0,
                status ENUM('aktif','nonaktif') DEFAULT 'aktif',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        /* =====================================================
           2. JASA PENGIRIMAN
        ===================================================== */

        $conn->query("
            CREATE TABLE IF NOT EXISTS jasa_pengiriman (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kategori_id INT NOT NULL,
                nama_jasa VARCHAR(100) NOT NULL,
                telepon VARCHAR(30) NULL,
                kendaraan VARCHAR(100) NULL,
                plat_nomor VARCHAR(30) NULL,
                tarif_dasar DECIMAL(15,2) DEFAULT 0,
                gps_terdaftar TINYINT(1) DEFAULT 0,
                gps_device_id VARCHAR(100) DEFAULT NULL,
                gps_status ENUM('online','offline') DEFAULT 'offline',
                gps_lat DECIMAL(10,7) DEFAULT NULL,
                gps_lng DECIMAL(10,7) DEFAULT NULL,
                gps_update DATETIME DEFAULT NULL,
                status ENUM('aktif','nonaktif') DEFAULT 'aktif',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                CONSTRAINT fk_jasa_kategori
                    FOREIGN KEY (kategori_id)
                    REFERENCES kategori_pengiriman(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        /* =====================================================
           3. PENGIRIMAN SALES
        ===================================================== */

        $conn->query("
            CREATE TABLE IF NOT EXISTS pengiriman_sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pesanan_id INT NOT NULL,
                jasa_id INT NOT NULL,
                ditugaskan_oleh INT DEFAULT NULL,

                status ENUM(
                    'Dijadwalkan',
                    'Dalam Perjalanan',
                    'Terkirim',
                    'Dibatalkan'
                ) DEFAULT 'Dijadwalkan',

                resi VARCHAR(80) DEFAULT NULL,
                estimasi_tiba DATE DEFAULT NULL,
                kurir_nama VARCHAR(100) DEFAULT NULL,
                kurir_telepon VARCHAR(30) DEFAULT NULL,

                tujuan_lat DECIMAL(10,7) DEFAULT NULL,
                tujuan_lng DECIMAL(10,7) DEFAULT NULL,
                tujuan_label VARCHAR(255) DEFAULT NULL,

                picked_up_at DATETIME DEFAULT NULL,
                delivered_at DATETIME DEFAULT NULL,

                catatan VARCHAR(500) DEFAULT NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                CONSTRAINT fk_pengiriman_pesanan
                    FOREIGN KEY (pesanan_id)
                    REFERENCES pesanan(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE,

                CONSTRAINT fk_pengiriman_jasa
                    FOREIGN KEY (jasa_id)
                    REFERENCES jasa_pengiriman(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT,

                CONSTRAINT fk_pengiriman_user
                    FOREIGN KEY (ditugaskan_oleh)
                    REFERENCES users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL,

                UNIQUE KEY uq_pengiriman_resi (resi),

                KEY idx_pengiriman_pesanan (pesanan_id),
                KEY idx_pengiriman_status (status)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        /* =====================================================
           4. TAMBAHAN KOLOM UNTUK DATABASE LAMA
        ===================================================== */

        if (salesTableExists($conn, 'jasa_pengiriman')) {

            salesAddColumn(
                $conn,
                'jasa_pengiriman',
                'plat_nomor',
                'VARCHAR(30) NULL AFTER kendaraan'
            );

            salesAddColumn(
                $conn,
                'jasa_pengiriman',
                'gps_lat',
                'DECIMAL(10,7) DEFAULT NULL AFTER gps_device_id'
            );

            salesAddColumn(
                $conn,
                'jasa_pengiriman',
                'gps_lng',
                'DECIMAL(10,7) DEFAULT NULL AFTER gps_lat'
            );

            salesAddColumn(
                $conn,
                'jasa_pengiriman',
                'gps_update',
                'DATETIME DEFAULT NULL AFTER gps_lng'
            );
        }

        if (salesTableExists($conn, 'pengiriman_sales')) {

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'resi',
                'VARCHAR(80) DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'estimasi_tiba',
                'DATE DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'kurir_nama',
                'VARCHAR(100) DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'kurir_telepon',
                'VARCHAR(30) DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'tujuan_lat',
                'DECIMAL(10,7) DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'tujuan_lng',
                'DECIMAL(10,7) DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'tujuan_label',
                'VARCHAR(255) DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'picked_up_at',
                'DATETIME DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'delivered_at',
                'DATETIME DEFAULT NULL'
            );

            salesAddColumn(
                $conn,
                'pengiriman_sales',
                'catatan',
                'VARCHAR(500) DEFAULT NULL'
            );
        }

        /* =====================================================
           5. TRACKING PENGIRIMAN
        ===================================================== */

        $conn->query("
            CREATE TABLE IF NOT EXISTS pengiriman_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pengiriman_id INT NOT NULL,

                status VARCHAR(50) NOT NULL,
                judul VARCHAR(150) NOT NULL,
                keterangan VARCHAR(500) DEFAULT NULL,
                lokasi VARCHAR(255) DEFAULT NULL,

                latitude DECIMAL(10,7) DEFAULT NULL,
                longitude DECIMAL(10,7) DEFAULT NULL,

                waktu_event DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                dibuat_oleh INT DEFAULT NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_tracking_pengiriman
                    FOREIGN KEY (pengiriman_id)
                    REFERENCES pengiriman_sales(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE,

                CONSTRAINT fk_tracking_user
                    FOREIGN KEY (dibuat_oleh)
                    REFERENCES users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL,

                KEY idx_tracking_pengiriman_waktu
                    (pengiriman_id, waktu_event)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        /* =====================================================
           6. KATEGORI DEFAULT
        ===================================================== */

        $conn->query("
            INSERT IGNORE INTO kategori_pengiriman
            (
                kode,
                nama_kategori,
                deskripsi,
                ikon,
                estimasi,
                tarif_mulai,
                status
            )
            VALUES
            (
                'REG',
                'Reguler',
                'Pengiriman pesanan Toku Coffee.',
                'fa-truck',
                '1-2 hari',
                10000,
                'aktif'
            )
        ");

        /* =====================================================
           7. AMBIL ID KATEGORI REGULER
        ===================================================== */

        $kategoriId = 0;

        $result = $conn->query("
            SELECT id
            FROM kategori_pengiriman
            WHERE kode = 'REG'
            LIMIT 1
        ");

        if ($result && ($row = $result->fetch_assoc())) {
            $kategoriId = (int)$row['id'];
        }

        if ($kategoriId <= 0) {
            throw new RuntimeException(
                'Kategori pengiriman REG belum tersedia.'
            );
        }

        /* =====================================================
           8. HAPUS JASA LAMA YANG SUDAH TIDAK DIPAKAI

           Hanya menghapus jasa lama jika belum digunakan
           dalam pengiriman_sales.
        ===================================================== */

        $namaLama = [
            'J&T Express',
            'JNE',
            'SiCepat'
        ];

        foreach ($namaLama as $namaJasaLama) {

            $stmt = $conn->prepare("
                DELETE FROM jasa_pengiriman
                WHERE nama_jasa = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM pengiriman_sales ps
                    WHERE ps.jasa_id = jasa_pengiriman.id
                )
            ");

            if ($stmt) {

                $stmt->bind_param(
                    's',
                    $namaJasaLama
                );

                $stmt->execute();
                $stmt->close();
            }
        }

        /* =====================================================
           9. JASA PENGIRIMAN DEFAULT
        ===================================================== */

        $jasaDemo = [

            [
                'nama' => 'GoSend',
                'telepon' => null,
                'kendaraan' => 'Motor',
                'plat' => 'B-1234-TKU',
                'tarif' => 10000,
                'device' => 'GOSEND-TOKU-001',
                'lat' => -6.2000000,
                'lng' => 106.8166660
            ],

            [
                'nama' => 'ShopeeFood',
                'telepon' => null,
                'kendaraan' => 'Motor',
                'plat' => 'B-2345-TKU',
                'tarif' => 10000,
                'device' => 'SHOPEEFOOD-TOKU-001',
                'lat' => -6.2088000,
                'lng' => 106.8456000
            ],

            [
                'nama' => 'GoFood',
                'telepon' => null,
                'kendaraan' => 'Motor',
                'plat' => 'B-3456-TKU',
                'tarif' => 10000,
                'device' => 'GOFOOD-TOKU-001',
                'lat' => -6.1754000,
                'lng' => 106.8272000
            ],

            [
                'nama' => 'GrabExpress',
                'telepon' => null,
                'kendaraan' => 'Motor',
                'plat' => 'B-4567-TKU',
                'tarif' => 12000,
                'device' => 'GRABEXPRESS-TOKU-001',
                'lat' => -6.2297000,
                'lng' => 106.6894000
            ]

        ];

        foreach ($jasaDemo as $jasa) {

            /* =================================================
               CEK JASA SUDAH ADA ATAU BELUM
            ================================================= */

            $stmt = $conn->prepare("
                SELECT id
                FROM jasa_pengiriman
                WHERE nama_jasa = ?
                LIMIT 1
            ");

            if (!$stmt) {
                continue;
            }

            $stmt->bind_param(
                's',
                $jasa['nama']
            );

            $stmt->execute();

            $existingResult = $stmt->get_result();

            $existing = $existingResult
                ? $existingResult->fetch_assoc()
                : null;

            $stmt->close();

            /* =================================================
               INSERT JIKA BELUM ADA
            ================================================= */

            if (!$existing) {

                $stmt = $conn->prepare("
                    INSERT INTO jasa_pengiriman
                    (
                        kategori_id,
                        nama_jasa,
                        telepon,
                        kendaraan,
                        plat_nomor,
                        tarif_dasar,
                        gps_terdaftar,
                        gps_device_id,
                        gps_status,
                        gps_lat,
                        gps_lng,
                        gps_update,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        1,
                        ?,
                        'online',
                        ?,
                        ?,
                        NOW(),
                        'aktif'
                    )
                ");

                if ($stmt) {

                    $stmt->bind_param(
                        'issssdsdd',
                        $kategoriId,
                        $jasa['nama'],
                        $jasa['telepon'],
                        $jasa['kendaraan'],
                        $jasa['plat'],
                        $jasa['tarif'],
                        $jasa['device'],
                        $jasa['lat'],
                        $jasa['lng']
                    );

                    $stmt->execute();
                    $stmt->close();
                }
            } else {

                /* =================================================
                   UPDATE JASA YANG SUDAH ADA
                ================================================= */

                $idJasa = (int)$existing['id'];

                $stmt = $conn->prepare("
                    UPDATE jasa_pengiriman
                    SET
                        kategori_id = ?,
                        telepon = ?,
                        kendaraan = ?,
                        plat_nomor = ?,
                        tarif_dasar = ?,
                        gps_terdaftar = 1,
                        gps_device_id = ?,
                        gps_status = 'online',
                        gps_lat = ?,
                        gps_lng = ?,
                        gps_update = NOW(),
                        status = 'aktif'
                    WHERE id = ?
                ");

                if ($stmt) {

                    $stmt->bind_param(
                        'isssdsddi',
                        $kategoriId,
                        $jasa['telepon'],
                        $jasa['kendaraan'],
                        $jasa['plat'],
                        $jasa['tarif'],
                        $jasa['device'],
                        $jasa['lat'],
                        $jasa['lng'],
                        $idJasa
                    );

                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        /* =====================================================
           10. PASTIKAN TARIF KATEGORI REGULER
        ===================================================== */

        $stmt = $conn->prepare("
            UPDATE kategori_pengiriman
            SET
                tarif_mulai = 10000,
                estimasi = '1-2 hari',
                status = 'aktif'
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                'i',
                $kategoriId
            );

            $stmt->execute();
            $stmt->close();
        }
    }
}
