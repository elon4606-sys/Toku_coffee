<?php

require_once "config/koneksi.php";
require_once "config/session.php";
require_once "config/sales_setup.php";

requireRole(['admin', 'staff']);

/* =========================================================
   FUNGSI BANTUAN
========================================================= */

function rupiahSales($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function eSales($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function statusClassSales($status)
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

function waktuGps($waktu)
{
    if (empty($waktu)) {
        return '-';
    }

    $ts = strtotime($waktu);

    return $ts ? date('d/m/Y H:i', $ts) : '-';
}


/* =========================================================
   FITUR TAMBAHAN PENGIRIMAN
   - Resi otomatis
   - ETA otomatis
   - Data kurir
   - Timeline tracking
   - Update status pengiriman
   - Update GPS kurir
========================================================= */

function generateResiSales()
{
    return 'TKU-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function estimasiHariSales($estimasi)
{
    $estimasi = strtolower(trim((string)$estimasi));

    if (strpos($estimasi, 'hari ini') !== false || strpos($estimasi, 'same day') !== false) {
        return 0;
    }

    if (preg_match('/(\d+)\s*-\s*(\d+)\s*hari/', $estimasi, $m)) {
        return (int)$m[2];
    }

    if (preg_match('/(\d+)\s*hari/', $estimasi, $m)) {
        return (int)$m[1];
    }

    return 2;
}

function addTrackingEventSales(
    mysqli $conn,
    $pengirimanId,
    $status,
    $judul,
    $keterangan = '',
    $lokasi = null,
    $latitude = null,
    $longitude = null,
    $userId = null
) {
    $stmt = $conn->prepare("
        INSERT INTO pengiriman_tracking
        (pengiriman_id, status, judul, keterangan, lokasi, latitude, longitude, dibuat_oleh)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('Tracking query tidak dapat dibuat');
    }

    $stmt->bind_param(
        'issssddi',
        $pengirimanId,
        $status,
        $judul,
        $keterangan,
        $lokasi,
        $latitude,
        $longitude,
        $userId
    );

    $stmt->execute();
    $stmt->close();
}

function updatePesananStatusSales(mysqli $conn, $pesananId, $statusPengiriman)
{
    $mapping = [
        'Dijadwalkan'      => 'Diproses',
        'Dalam Perjalanan' => 'Dikirim',
        'Terkirim'         => 'Selesai',
        'Dibatalkan'       => 'Dibatalkan'
    ];

    if (!isset($mapping[$statusPengiriman])) {
        return;
    }

    $statusPesanan = $mapping[$statusPengiriman];

    $stmt = $conn->prepare("UPDATE pesanan SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $statusPesanan, $pesananId);
    $stmt->execute();
    $stmt->close();
}

/* =========================================================
   INFORMASI USER
========================================================= */

$namaUser = $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Admin ERP';

$roleUser = $_SESSION['role']
    ?? 'Administrator';

$avatarName = urlencode($namaUser);

/* =========================================================
   SIAPKAN TABEL FITUR SALES (HANYA TABEL BARU)
========================================================= */

try {
    setupSalesPengiriman($conn);

    /* =========================================================
       DATA JASA PENGIRIMAN TOKU COFFEE
       - Sedikit saja agar daftar tidak terlalu penuh
       - J&T lama diarahkan menjadi GoSend
    ========================================================= */

    $stmtKategori = $conn->prepare("
        SELECT id
        FROM kategori_pengiriman
        WHERE kode = 'REG'
        LIMIT 1
    ");
    $stmtKategori->execute();
    $kategoriRow = $stmtKategori->get_result()->fetch_assoc();
    $stmtKategori->close();

    $kategoriRegId = $kategoriRow ? (int)$kategoriRow['id'] : 0;

    if ($kategoriRegId > 0) {

        /* J&T lama diubah menjadi GoSend agar tidak menambah data berlebihan */
        $stmtRename = $conn->prepare("
            UPDATE jasa_pengiriman
            SET nama_jasa = 'GoSend',
                telepon = '1500702',
                kendaraan = 'Motor',
                tarif_dasar = 10000,
                gps_terdaftar = 1,
                gps_device_id = 'GOSEND-TOKU-001'
            WHERE nama_jasa = 'J&T Express'
            LIMIT 1
        ");
        $stmtRename->execute();
        $stmtRename->close();

        $jasaDemo = [
            [
                'nama' => 'GoSend',
                'telepon' => '1500702',
                'kendaraan' => 'Motor',
                'tarif' => 10000,
                'device' => 'GOSEND-TOKU-001'
            ],
            [
                'nama' => 'ShopeeFood',
                'telepon' => '08001500100',
                'kendaraan' => 'Motor',
                'tarif' => 10000,
                'device' => 'SHOPEEFOOD-TOKU-001'
            ],
            [
                'nama' => 'GoFood',
                'telepon' => '1500171',
                'kendaraan' => 'Motor',
                'tarif' => 10000,
                'device' => 'GOFOOD-TOKU-001'
            ],
            [
                'nama' => 'GrabExpress',
                'telepon' => '021-2350-7000',
                'kendaraan' => 'Motor',
                'tarif' => 12000,
                'device' => 'GRABEXPRESS-TOKU-001'
            ]
        ];

        foreach ($jasaDemo as $demo) {

            $stmtCek = $conn->prepare("
                SELECT id
                FROM jasa_pengiriman
                WHERE nama_jasa = ?
                LIMIT 1
            ");
            $stmtCek->bind_param('s', $demo['nama']);
            $stmtCek->execute();
            $sudahAda = $stmtCek->get_result()->num_rows > 0;
            $stmtCek->close();

            if (!$sudahAda) {
                $stmtInsert = $conn->prepare("
                    INSERT INTO jasa_pengiriman
                    (
                        kategori_id,
                        nama_jasa,
                        telepon,
                        kendaraan,
                        tarif_dasar,
                        gps_terdaftar,
                        gps_device_id,
                        gps_status,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, 1, ?, 'offline', 'aktif')
                ");

                $stmtInsert->bind_param(
                    'isssds',
                    $kategoriRegId,
                    $demo['nama'],
                    $demo['telepon'],
                    $demo['kendaraan'],
                    $demo['tarif'],
                    $demo['device']
                );

                $stmtInsert->execute();
                $stmtInsert->close();
            }
        }
    }
} catch (Throwable $e) {
    // dilanjutkan; pesan galat ditampilkan di bawah bila query gagal
}

/* =========================================================
   TOKEN KEAMANAN FORM
========================================================= */

if (empty($_SESSION['csrf_sales'])) {
    $_SESSION['csrf_sales'] = bin2hex(random_bytes(16));
}

/* =========================================================
   AKSI PENGIRIMAN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');
    $token  = (string)($_POST['csrf'] ?? '');

    if (!hash_equals($_SESSION['csrf_sales'], $token)) {
        header("Location: sales.php?error=" . urlencode("Sesi form tidak valid, silakan ulangi"));
        exit;
    }

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    /* =====================================================
       1. TUGASKAN JASA PENGIRIMAN
    ===================================================== */

    if ($action === 'tugaskan') {

        $pesananId = (int)($_POST['pesanan_id'] ?? 0);
        $jasaId    = (int)($_POST['jasa_id'] ?? 0);
        $catatan   = trim((string)($_POST['catatan'] ?? ''));
        $catatan   = function_exists('mb_substr')
            ? mb_substr($catatan, 0, 500)
            : substr($catatan, 0, 500);

        if ($pesananId <= 0 || $jasaId <= 0) {
            header("Location: sales.php?error=" . urlencode("Pilih jasa pengiriman dan pesanan terlebih dahulu"));
            exit;
        }

        try {
            /* Jasa harus aktif dan GPS-nya terdaftar */
            $stmt = $conn->prepare("
                SELECT
                    j.id,
                    j.nama_jasa,
                    j.telepon,
                    k.nama_kategori,
                    k.estimasi
                FROM jasa_pengiriman j
                INNER JOIN kategori_pengiriman k
                    ON k.id = j.kategori_id
                WHERE j.id = ?
                AND j.status = 'aktif'
                AND j.gps_terdaftar = 1
                AND j.gps_device_id IS NOT NULL
                LIMIT 1
            ");

            $stmt->bind_param('i', $jasaId);
            $stmt->execute();
            $jasaResult = $stmt->get_result();
            $jasa = $jasaResult->fetch_assoc();
            $stmt->close();

            if (!$jasa) {
                header("Location: sales.php?error=" . urlencode("Jasa pengiriman belum terdaftar GPS atau tidak aktif"));
                exit;
            }

            /* Pesanan harus ada dan belum punya penugasan aktif */
            $stmt = $conn->prepare("
                SELECT
                    p.id,
                    p.alamat_pengiriman,
                    p.status
                FROM pesanan p
                WHERE p.id = ?
                AND p.status IN ('Menunggu', 'Diproses')
                AND NOT EXISTS (
                    SELECT 1
                    FROM pengiriman_sales ps
                    WHERE ps.pesanan_id = p.id
                    AND ps.status <> 'Dibatalkan'
                )
                LIMIT 1
            ");

            $stmt->bind_param('i', $pesananId);
            $stmt->execute();
            $pesanan = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$pesanan) {
                header("Location: sales.php?error=" . urlencode("Pesanan tidak tersedia atau sudah memiliki jasa pengiriman"));
                exit;
            }

            $hariEstimasi = estimasiHariSales($jasa['estimasi']);
            $estimasiTiba = date('Y-m-d', strtotime('+' . $hariEstimasi . ' days'));
            $resi = generateResiSales();
            $kurirNama = $jasa['nama_jasa'];
            $kurirTelepon = $jasa['telepon'];
            $tujuanLabel = trim((string)$pesanan['alamat_pengiriman']);

            /* Simpan penugasan lengkap */
            $stmt = $conn->prepare("
                INSERT INTO pengiriman_sales
                (
                    pesanan_id,
                    jasa_id,
                    ditugaskan_oleh,
                    status,
                    resi,
                    estimasi_tiba,
                    kurir_nama,
                    kurir_telepon,
                    tujuan_label,
                    catatan
                )
                VALUES (?, ?, ?, 'Dijadwalkan', ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'iiissssss',
                $pesananId,
                $jasaId,
                $userId,
                $resi,
                $estimasiTiba,
                $kurirNama,
                $kurirTelepon,
                $tujuanLabel,
                $catatan
            );

            $berhasil = $stmt->execute();
            $pengirimanId = (int)$conn->insert_id;
            $stmt->close();

            if (!$berhasil || $pengirimanId <= 0) {
                header("Location: sales.php?error=" . urlencode("Gagal menyimpan penugasan pengiriman"));
                exit;
            }

            updatePesananStatusSales($conn, $pesananId, 'Dijadwalkan');

            addTrackingEventSales(
                $conn,
                $pengirimanId,
                'Dijadwalkan',
                'Pengiriman dijadwalkan',
                'Jasa ' . $jasa['nama_jasa'] . ' ditugaskan ke pesanan. Resi: ' . $resi,
                $tujuanLabel !== '' ? $tujuanLabel : null,
                null,
                null,
                $userId
            );

            header("Location: sales.php?success=" . urlencode("Pengiriman berhasil dibuat. Resi: " . $resi));
            exit;
        } catch (Throwable $e) {
            header("Location: sales.php?error=" . urlencode("Query penugasan tidak dapat diproses"));
            exit;
        }
    }

    /* =====================================================
       2. UPDATE STATUS PENGIRIMAN
    ===================================================== */

    if ($action === 'update_status') {

        $pengirimanId = (int)($_POST['pengiriman_id'] ?? 0);
        $statusBaru   = trim((string)($_POST['status_pengiriman'] ?? ''));
        $keterangan  = trim((string)($_POST['keterangan'] ?? ''));
        $keterangan  = function_exists('mb_substr')
            ? mb_substr($keterangan, 0, 500)
            : substr($keterangan, 0, 500);

        $allowedStatus = [
            'Dijadwalkan',
            'Dalam Perjalanan',
            'Terkirim',
            'Dibatalkan'
        ];

        if ($pengirimanId <= 0 || !in_array($statusBaru, $allowedStatus, true)) {
            header("Location: sales.php?error=" . urlencode("Status pengiriman tidak valid"));
            exit;
        }

        try {
            $stmt = $conn->prepare("
                SELECT
                    ps.id,
                    ps.pesanan_id,
                    ps.jasa_id,
                    ps.status,
                    ps.resi,
                    ps.tujuan_label,
                    j.nama_jasa
                FROM pengiriman_sales ps
                INNER JOIN jasa_pengiriman j
                    ON j.id = ps.jasa_id
                WHERE ps.id = ?
                LIMIT 1
            ");
            $stmt->bind_param('i', $pengirimanId);
            $stmt->execute();
            $pengiriman = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$pengiriman) {
                header("Location: sales.php?error=" . urlencode("Data pengiriman tidak ditemukan"));
                exit;
            }

            $pickedUpSql = '';
            $deliveredSql = '';

            if ($statusBaru === 'Dalam Perjalanan') {
                $pickedUpSql = ', picked_up_at = COALESCE(picked_up_at, NOW())';
            }

            if ($statusBaru === 'Terkirim') {
                $deliveredSql = ', delivered_at = NOW()';
            }

            $stmt = $conn->prepare(
                "UPDATE pengiriman_sales
                 SET status = ?, updated_at = CURRENT_TIMESTAMP {$pickedUpSql} {$deliveredSql}
                 WHERE id = ?"
            );
            $stmt->bind_param('si', $statusBaru, $pengirimanId);
            $stmt->execute();
            $stmt->close();

            updatePesananStatusSales($conn, (int)$pengiriman['pesanan_id'], $statusBaru);

            $judulMap = [
                'Dijadwalkan'      => 'Pengiriman dijadwalkan',
                'Dalam Perjalanan' => 'Paket dalam perjalanan',
                'Terkirim'         => 'Paket telah terkirim',
                'Dibatalkan'       => 'Pengiriman dibatalkan'
            ];

            if ($keterangan === '') {
                $keterangan = 'Status pengiriman diperbarui oleh admin/staff.';
            }

            addTrackingEventSales(
                $conn,
                $pengirimanId,
                $statusBaru,
                $judulMap[$statusBaru],
                $keterangan,
                $pengiriman['tujuan_label'] ?: null,
                null,
                null,
                $userId
            );

            header("Location: sales.php?success=" . urlencode("Status pengiriman berhasil diubah menjadi {$statusBaru}"));
            exit;
        } catch (Throwable $e) {
            header("Location: sales.php?error=" . urlencode("Status pengiriman gagal diperbarui"));
            exit;
        }
    }

    /* =====================================================
       3. UPDATE GPS KURIR
    ===================================================== */

    if ($action === 'update_gps') {

        $jasaId = (int)($_POST['jasa_id'] ?? 0);
        $lat    = (float)($_POST['gps_lat'] ?? 0);
        $lng    = (float)($_POST['gps_lng'] ?? 0);

        if ($jasaId <= 0 || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            header("Location: sales.php?error=" . urlencode("Koordinat GPS tidak valid"));
            exit;
        }

        try {
            $stmt = $conn->prepare("
                UPDATE jasa_pengiriman
                SET gps_status = 'online',
                    gps_lat = ?,
                    gps_lng = ?,
                    gps_update = NOW()
                WHERE id = ?
                AND status = 'aktif'
                AND gps_terdaftar = 1
            ");
            $stmt->bind_param('ddi', $lat, $lng, $jasaId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected < 0) {
                header("Location: sales.php?error=" . urlencode("GPS gagal diperbarui"));
                exit;
            }

            /* Simpan posisi GPS terbaru ke shipment aktif yang memakai jasa tersebut */
            $result = $conn->query("
                SELECT id, resi, tujuan_label
                FROM pengiriman_sales
                WHERE jasa_id = " . $jasaId . "
                AND status IN ('Dijadwalkan', 'Dalam Perjalanan')
            ");

            while ($row = $result->fetch_assoc()) {
                addTrackingEventSales(
                    $conn,
                    (int)$row['id'],
                    'GPS',
                    'Lokasi kurir diperbarui',
                    'Koordinat GPS kurir diperbarui melalui halaman Sales.',
                    'GPS ' . number_format($lat, 7, '.', '') . ', ' . number_format($lng, 7, '.', ''),
                    $lat,
                    $lng,
                    $userId
                );
            }

            header("Location: sales.php?success=" . urlencode("Lokasi GPS kurir berhasil diperbarui"));
            exit;
        } catch (Throwable $e) {
            header("Location: sales.php?error=" . urlencode("GPS kurir gagal diperbarui"));
            exit;
        }
    }
}

/* =========================================================
   DATA HALAMAN
========================================================= */

$kategoriList   = [];
$jasaList       = [];
$pesananList    = [];
$riwayatList    = [];
$pengirimanList  = [];

$totalGps       = 0;
$gpsOnline      = 0;
$totalKategori  = 0;
$penugasanAktif = 0;
$belumGps       = 0;

$dbError = '';

try {

    /* Kategori + jumlah jasa ber-GPS */

    $result = $conn->query("
        SELECT
            k.*,
            (
                SELECT COUNT(*)
                FROM jasa_pengiriman j
                WHERE j.kategori_id = k.id
                AND j.status = 'aktif'
                AND j.gps_terdaftar = 1
            ) AS jumlah_gps
        FROM kategori_pengiriman k
        WHERE k.status = 'aktif'
        ORDER BY k.id ASC
    ");

    while ($row = $result->fetch_assoc()) {
        $kategoriList[] = $row;
    }

    $totalKategori = count($kategoriList);

    /* Jasa pengiriman */

    $result = $conn->query("
        SELECT
            j.*,
            k.nama_kategori,
            k.kode,
            k.estimasi
        FROM jasa_pengiriman j
        INNER JOIN kategori_pengiriman k
            ON k.id = j.kategori_id
        WHERE j.status = 'aktif'
        ORDER BY
            j.gps_terdaftar DESC,
            (j.gps_status = 'online') DESC,
            j.nama_jasa ASC
    ");

    while ($row = $result->fetch_assoc()) {

        $jasaList[] = $row;

        if ((int)$row['gps_terdaftar'] === 1) {

            $totalGps++;

            if ($row['gps_status'] === 'online') {
                $gpsOnline++;
            }
        } else {
            $belumGps++;
        }
    }

    /* Penugasan aktif */

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM pengiriman_sales
        WHERE status IN ('Dijadwalkan', 'Dalam Perjalanan')
    ");

    $penugasanAktif = (int)$result->fetch_assoc()['total'];

    /* Pesanan yang belum punya jasa pengiriman */

    $result = $conn->query("
        SELECT
            p.id,
            p.invoice,
            p.total,
            p.alamat_pengiriman,
            u.full_name
        FROM pesanan p
        INNER JOIN users u
            ON u.id = p.user_id
        WHERE p.status IN ('Menunggu', 'Diproses')
        AND NOT EXISTS (
            SELECT 1
            FROM pengiriman_sales ps
            WHERE ps.pesanan_id = p.id
            AND ps.status <> 'Dibatalkan'
        )
        ORDER BY p.tanggal_pesanan DESC
        LIMIT 100
    ");

    while ($row = $result->fetch_assoc()) {
        $pesananList[] = $row;
    }

    /* Riwayat penugasan terbaru */

    $result = $conn->query("
        SELECT
            ps.id,
            ps.status,
            ps.created_at,
            p.invoice,
            j.nama_jasa,
            j.gps_device_id,
            k.nama_kategori
        FROM pengiriman_sales ps
        INNER JOIN pesanan p
            ON p.id = ps.pesanan_id
        INNER JOIN jasa_pengiriman j
            ON j.id = ps.jasa_id
        INNER JOIN kategori_pengiriman k
            ON k.id = j.kategori_id
        ORDER BY ps.id DESC
        LIMIT 8
    ");

    while ($row = $result->fetch_assoc()) {
        $riwayatList[] = $row;
    }


    /* Pengiriman yang dapat dikelola */

    $result = $conn->query("
        SELECT
            ps.id,
            ps.pesanan_id,
            ps.status,
            ps.resi,
            ps.estimasi_tiba,
            ps.kurir_nama,
            ps.kurir_telepon,
            ps.tujuan_label,
            ps.created_at,
            ps.updated_at,
            p.invoice,
            p.total,
            u.full_name,
            j.nama_jasa
        FROM pengiriman_sales ps
        INNER JOIN pesanan p
            ON p.id = ps.pesanan_id
        INNER JOIN users u
            ON u.id = p.user_id
        INNER JOIN jasa_pengiriman j
            ON j.id = ps.jasa_id
        ORDER BY ps.id DESC
        LIMIT 50
    ");

    while ($row = $result->fetch_assoc()) {
        $pengirimanList[] = $row;
    }
} catch (Throwable $e) {
    $dbError = 'Data pengiriman belum dapat dimuat. Pastikan database toku_coffee aktif dan tabel pesanan sudah ada.';
}

$successMessage = $_GET['success'] ?? '';
$errorMessage   = $_GET['error'] ?? '';

?>
<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Sales Pengiriman - Toku Coffee ERP
    </title>


    <!-- GOOGLE FONT -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600&display=swap"
        rel="stylesheet">


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <style>
        /* =====================================================
           ROOT
        ===================================================== */

        :root {

            --main-color: #443;

            --border-radius:
                95% 4% 97% 5% / 4% 94% 3% 95%;

            --border-radius-hover:
                4% 95% 6% 95% / 95% 4% 92% 5%;

            --border:
                .2rem solid var(--main-color);

            --border-hover:
                .2rem dashed var(--main-color);

            --bg: #faf9f5;

            --white: #fff;

            --green: #527853;

            --orange: #c68b3c;

            --red: #a94442;

            --blue: #557a95;

            --gray: #888;

        }


        /* =====================================================
           RESET
        ===================================================== */

        * {

            font-family:
                'Poppins',
                sans-serif;

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            outline: none;

            border: none;

            text-decoration: none;

            transition:
                all .2s linear;

        }


        html {

            font-size: 62.5%;

            overflow-x: hidden;

            scroll-behavior: smooth;

        }


        body {

            background:
                var(--bg);

            color:
                var(--main-color);

        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {

            position: fixed;

            top: 0;

            left: 0;

            width: 26rem;

            height: 100vh;

            background: #fff;

            border-right:
                .1rem solid #eee;

            padding:
                2.5rem 1.5rem;

            z-index: 1000;

            overflow-y: auto;

            transition:
                left .35s ease,
                transform .35s ease;

        }


        .logo {

            display: block;

            text-align: center;

            color:
                var(--main-color);

            font-size: 2.6rem;

            font-weight: 600;

            margin-bottom: 3rem;

        }


        .logo:hover {

            transform:
                scale(1.03);

        }


        .logo i {

            margin-right: .5rem;

        }


        .menu-title {

            font-size: 1.1rem;

            color: #aaa;

            padding:
                0 1.5rem;

            margin-bottom: 1rem;

            text-transform: uppercase;

        }


        .sidebar a {

            display: flex;

            align-items: center;

            gap: 1.3rem;

            padding:
                1.3rem 1.5rem;

            margin-bottom: .7rem;

            color:
                var(--main-color);

            font-size: 1.5rem;

            border-radius:
                var(--border-radius);

            position: relative;

            overflow: hidden;

        }


        .sidebar a i {

            width: 2rem;

            font-size: 1.7rem;

            text-align: center;

        }


        .sidebar a:hover i {

            transform:
                translateX(.3rem) scale(1.08);

        }


        .sidebar a:hover,
        .sidebar a.active {

            background:
                #f3f0e8;

            border:
                var(--border);

            transform:
                translateX(.3rem);

        }


        /* =====================================================
           MAIN
        ===================================================== */

        .main {

            margin-left: 26rem;

            min-height: 100vh;

        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {

            height: 8rem;

            background: #fff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 4rem;

            box-shadow:
                0 .3rem 1rem rgba(0, 0, 0, .05);

            position: sticky;

            top: 0;

            z-index: 900;

        }


        .topbar-left {

            display: flex;

            align-items: center;

            gap: 1.5rem;

        }


        .page-title h2 {

            font-size: 2.4rem;

            animation:
                fadeDown .5s ease;

        }


        .page-title p {

            font-size: 1.3rem;

            color: #999;

        }


        #menu-btn {

            display: none;

            font-size: 2.5rem;

            cursor: pointer;

        }


        #menu-btn:hover {

            transform:
                rotate(5deg) scale(1.1);

        }


        .topbar-right {

            display: flex;

            align-items: center;

            gap: 2rem;

        }


        /* =====================================================
           NOTIFICATION
        ===================================================== */

        .notification {

            position: relative;

            font-size: 2rem;

            cursor: pointer;

        }


        .notification:hover i {

            transform:
                rotate(-12deg) scale(1.08);

        }


        .notification span {

            position: absolute;

            top: -1rem;

            right: -1rem;

            width: 1.8rem;

            height: 1.8rem;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background:
                var(--red);

            color: #fff;

            font-size: 1rem;

            animation:
                pulse 2s infinite;

        }


        /* =====================================================
           PROFILE
        ===================================================== */

        .profile {

            display: flex;

            align-items: center;

            gap: 1rem;

        }


        .profile img {

            width: 4rem;

            height: 4rem;

            border-radius: 50%;

            border:
                .2rem solid var(--main-color);

        }


        .profile:hover img {

            transform:
                scale(1.08) rotate(3deg);

        }


        .profile h4 {

            font-size: 1.4rem;

        }


        .profile p {

            font-size: 1.1rem;

            color: #999;

        }


        /* =====================================================
           CONTENT
        ===================================================== */

        .content {

            padding:
                3rem 4rem;

            animation:
                fadeUp .5s ease;

        }


        /* =====================================================
           PAGE ACTION
        ===================================================== */

        .page-actions {

            display: flex;

            justify-content: flex-end;

            gap: 1rem;

            margin-bottom: 2rem;

        }


        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: .7rem;

            padding:
                1rem 1.7rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            background: none;

            cursor: pointer;

            font-size: 1.3rem;

        }


        .btn:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

            background:
                #f7f4ec;

            transform:
                translateY(-.2rem);

        }


        /* =====================================================
           STATISTICS
        ===================================================== */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 1.8rem;

            margin-bottom: 3rem;

        }


        .stat {

            background: #fff;

            padding: 2rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            display: flex;

            align-items: center;

            gap: 1.5rem;

            animation:
                cardAppear .6s ease both;

        }


        .stat:nth-child(1) {
            animation-delay: .05s;
        }

        .stat:nth-child(2) {
            animation-delay: .10s;
        }

        .stat:nth-child(3) {
            animation-delay: .15s;
        }

        .stat:nth-child(4) {
            animation-delay: .20s;
        }


        .stat:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

            transform:
                translateY(-.5rem);

            box-shadow:
                0 1rem 2rem rgba(68, 68, 51, .08);

        }


        .stat-icon {

            width: 5rem;

            height: 5rem;

            flex:
                0 0 5rem;

            display: flex;

            align-items: center;

            justify-content: center;

            border:
                .15rem solid var(--main-color);

            border-radius:
                45% 55% 60% 40%;

            font-size: 2rem;

        }


        .stat:hover .stat-icon {

            transform:
                rotate(-5deg) scale(1.08);

        }


        .stat h3 {

            font-size: 2rem;

            line-height: 1.3;

        }


        .stat p {

            font-size: 1.2rem;

            color: #888;

        }


        .stat small {

            display: block;

            color:
                var(--green);

            font-size: 1rem;

            margin-top: .4rem;

        }


        /* =====================================================
           PANEL
        ===================================================== */

        .panel {

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding: 2.5rem;

            margin-bottom: 2rem;

        }


        .panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .panel-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 1rem;

            margin-bottom: 2rem;

        }


        .panel-header h2 {

            font-size: 1.9rem;

        }


        .panel-header a {

            font-size: 1.2rem;

            color:
                var(--main-color);

            border-bottom:
                .1rem dashed var(--main-color);

        }


        .panel-header a:hover {

            padding-right: .5rem;

        }


        /* =====================================================
           FILTER
        ===================================================== */

        .filter-box {

            display: grid;

            grid-template-columns:
                2fr 1fr auto;

            gap: 1rem;

            margin-bottom: 2rem;

        }


        .input-group {

            position: relative;

        }


        .input-group i {

            position: absolute;

            left: 1.3rem;

            top: 50%;

            transform:
                translateY(-50%);

            color: #999;

            font-size: 1.4rem;

        }


        .input-control,
        .select-control {

            width: 100%;

            padding:
                1.2rem 1.3rem 1.2rem 4rem;

            border:
                .15rem solid #ddd;

            border-radius:
                var(--border-radius);

            background: #fff;

            color:
                var(--main-color);

            font-size: 1.2rem;

        }


        .select-control {

            padding-left: 1.3rem;

            cursor: pointer;

        }


        .input-control:focus,
        .select-control:focus {

            border-color:
                var(--main-color);

        }


        .filter-btn {

            padding:
                1rem 1.5rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            background: none;

            color:
                var(--main-color);

            cursor: pointer;

            font-size: 1.2rem;

        }


        .filter-btn:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

            background:
                #f7f4ec;

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        table {

            width: 100%;

            min-width: 95rem;

            border-collapse: collapse;

        }


        thead tr {

            border-bottom:
                .2rem solid var(--main-color);

        }


        th {

            text-align: left;

            padding:
                1.5rem 1rem;

            font-size: 1.2rem;

            color:
                var(--main-color);

            white-space: nowrap;

        }


        td {

            padding:
                1.5rem 1rem;

            border-bottom:
                .1rem solid #eee;

            font-size: 1.2rem;

            vertical-align: middle;

        }


        tbody tr {

            animation:
                cardAppear .4s ease both;

        }


        tbody tr:hover {

            background:
                #faf8f1;

            transform:
                translateX(.3rem);

        }


        .invoice {

            font-weight: 600;

            color:
                var(--main-color);

        }


        .customer-name {

            font-weight: 500;

        }


        .customer-email {

            display: block;

            color: #999;

            font-size: 1rem;

            margin-top: .2rem;

        }


        .order-total {

            font-weight: 600;

            white-space: nowrap;

        }

        /* =====================================================
   LOGOUT
===================================================== */

        .sidebar .logout-menu {

            color: var(--red);

        }

        .sidebar .logout-menu i {

            color: var(--red);

        }

        .sidebar .logout-menu:hover {

            background: #fff0ef;

            border:
                var(--border);

            transform:
                translateX(.3rem);

        }

        .sidebar .logout-menu:hover i {

            transform:
                translateX(.3rem) scale(1.08);

        }

        /* =====================================================
           STATUS
        ===================================================== */

        .status {

            display: inline-flex;

            align-items: center;

            padding:
                .5rem 1rem;

            border:
                .1rem solid currentColor;

            border-radius:
                var(--border-radius);

            font-size: 1rem;

            white-space: nowrap;

        }


        .status.selesai {

            color:
                var(--green);

            background:
                #f0f7f0;

        }


        .status.diproses {

            color:
                var(--orange);

            background:
                #fff7e9;

        }


        .status.dikirim {

            color:
                var(--blue);

            background:
                #eef5f9;

        }


        .status.menunggu {

            color:
                var(--red);

            background:
                #fff0ef;

        }


        .status.dibatalkan {

            color:
                #777;

            background:
                #f1f1f1;

        }


        /* =====================================================
           ACTION
        ===================================================== */

        .action-group {

            display: flex;

            align-items: center;

            gap: .6rem;

        }


        .action-btn {

            width: 3.5rem;

            height: 3.5rem;

            display: flex;

            align-items: center;

            justify-content: center;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            background: #fff;

            color:
                var(--main-color);

            cursor: pointer;

        }


        .action-btn:hover {

            border:
                var(--border);

            border-radius:
                var(--border-radius-hover);

            background:
                #f7f4ec;

            transform:
                translateY(-.2rem);

        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            text-align: center;

            padding: 4rem 1rem;

            color: #999;

            font-size: 1.2rem;

        }


        .empty i {

            display: block;

            font-size: 3.5rem;

            margin-bottom: 1rem;

        }


        /* =====================================================
           MODAL
        ===================================================== */

        .modal {

            position: fixed;

            inset: 0;

            background:
                rgba(68, 68, 51, .35);

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 2rem;

            z-index: 2000;

            opacity: 0;

            visibility: hidden;

            transition:
                all .25s ease;

        }


        .modal.active {

            opacity: 1;

            visibility: visible;

        }


        .modal-box {

            width: 100%;

            max-width: 55rem;

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding: 2.5rem;

            transform:
                translateY(2rem) scale(.97);

            transition:
                all .25s ease;

        }


        .modal.active .modal-box {

            transform:
                translateY(0) scale(1);

        }


        .modal-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 2rem;

        }


        .modal-header h2 {

            font-size: 2rem;

        }


        .close-modal {

            width: 3.5rem;

            height: 3.5rem;

            border:
                .1rem solid #ddd;

            border-radius: 50%;

            background: #fff;

            color:
                var(--main-color);

            cursor: pointer;

            font-size: 1.5rem;

        }


        .close-modal:hover {

            transform:
                rotate(90deg);

            border:
                var(--border);

        }


        .detail-list {

            display: flex;

            flex-direction: column;

            gap: 1rem;

        }


        .detail-item {

            padding:
                1.2rem;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

        }


        .detail-item strong {

            display: block;

            font-size: 1.1rem;

            color: #999;

            margin-bottom: .3rem;

        }


        .detail-item span {

            font-size: 1.3rem;

        }


        .status-form {

            margin-top: 2rem;

            padding-top: 2rem;

            border-top:
                .1rem solid #eee;

        }


        .status-form label {

            display: block;

            font-size: 1.2rem;

            margin-bottom: .7rem;

        }


        .status-form select {

            width: 100%;

            padding:
                1.2rem;

            border:
                .15rem solid #ddd;

            border-radius:
                var(--border-radius);

            font-size: 1.2rem;

            color:
                var(--main-color);

        }


        .modal-actions {

            display: flex;

            justify-content: flex-end;

            gap: 1rem;

            margin-top: 1.5rem;

        }


        /* =====================================================
           FLASH
        ===================================================== */

        .flash {

            padding:
                1.3rem 1.5rem;

            margin-bottom: 2rem;

            border:
                .1rem solid currentColor;

            border-radius:
                var(--border-radius);

            font-size: 1.2rem;

            animation:
                fadeDown .4s ease;

        }


        .flash.success {

            color:
                var(--green);

            background:
                #f0f7f0;

        }


        .flash.error {

            color:
                var(--red);

            background:
                #fff0ef;

        }


        /* =====================================================
           ANIMATION
        ===================================================== */

        @keyframes fadeUp {

            from {

                opacity: 0;

                transform:
                    translateY(1.5rem);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0);

            }

        }


        @keyframes fadeDown {

            from {

                opacity: 0;

                transform:
                    translateY(-1rem);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0);

            }

        }


        @keyframes cardAppear {

            from {

                opacity: 0;

                transform:
                    translateY(1.5rem);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0);

            }

        }


        @keyframes pulse {

            0% {

                transform:
                    scale(1);

            }

            50% {

                transform:
                    scale(1.15);

            }

            100% {

                transform:
                    scale(1);

            }

        }


        /* =====================================================
           RESPONSIVE 1200
        ===================================================== */

        @media (max-width: 1200px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        /* =====================================================
           RESPONSIVE 900
        ===================================================== */

        @media (max-width: 900px) {

            .sidebar {

                left: -27rem;

                box-shadow:
                    .5rem 0 2rem rgba(0, 0, 0, .08);

            }


            .sidebar.active {

                left: 0;

            }


            .main {

                margin-left: 0;

            }


            #menu-btn {

                display: block;

            }


            .content {

                padding:
                    2rem;

            }


            .topbar {

                padding:
                    0 2rem;

            }


            .profile-info {

                display: none;

            }


            .filter-box {

                grid-template-columns:
                    1fr;

            }

        }


        /* =====================================================
           RESPONSIVE 550
        ===================================================== */

        @media (max-width: 550px) {

            html {

                font-size: 50%;

            }


            .stats {

                grid-template-columns:
                    1fr;

            }


            .content {

                padding:
                    1.5rem;

            }


            .panel {

                padding:
                    1.5rem;

            }


            .page-actions {

                justify-content:
                    stretch;

            }


            .page-actions .btn {

                width: 100%;

            }


            .page-title h2 {

                font-size:
                    2rem;

            }


            .page-title p {

                display: none;

            }


            .modal {

                padding: 1rem;

            }


            .modal-box {

                padding: 1.8rem;

            }

        }


        /* =====================================================
           REDUCED MOTION
        ===================================================== */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                animation-duration: .01ms !important;

                animation-iteration-count: 1 !important;

                transition-duration: .01ms !important;

                scroll-behavior: auto !important;

            }

        }

        /* =====================================================
           SALES - KATEGORI JASA PENGIRIMAN
        ===================================================== */

        .kategori-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(19rem, 1fr));
            gap: 1.5rem;
        }

        .kategori-card {
            background: #fff;
            color: var(--main-color);
            text-align: left;
            cursor: pointer;
            padding: 2rem;
            border: var(--border);
            border-radius: var(--border-radius);
            display: flex;
            flex-direction: column;
            gap: .6rem;
            position: relative;
        }

        .kategori-card:hover {
            border: var(--border-hover);
            border-radius: var(--border-radius-hover);
            background: #f7f4ec;
            transform: translateY(-.4rem);
        }

        .kategori-card.active {
            background: var(--main-color);
            color: #fff;
        }

        .kategori-card.active small,
        .kategori-card.active p {
            color: #e8e4d6;
        }

        .kategori-card .kategori-ikon {
            width: 4.6rem;
            height: 4.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: .15rem solid currentColor;
            border-radius: 45% 55% 60% 40%;
            font-size: 1.9rem;
            margin-bottom: .6rem;
        }

        .kategori-card h3 {
            font-size: 1.6rem;
        }

        .kategori-card p {
            font-size: 1.15rem;
            color: #888;
            line-height: 1.5;
        }

        .kategori-card small {
            font-size: 1.05rem;
            color: #888;
        }

        .kategori-card .kategori-jumlah {
            position: absolute;
            top: 1.4rem;
            right: 1.4rem;
            font-size: 1.05rem;
            padding: .3rem .9rem;
            border: .1rem solid currentColor;
            border-radius: var(--border-radius);
        }

        /* =====================================================
           SALES - FILTER KURIR
        ===================================================== */

        .filter-box.filter-sales {
            grid-template-columns: 2fr 1fr auto;
        }

        .status.online {
            color: var(--green);
            background: #f0f7f0;
        }

        .status.offline {
            color: #777;
            background: #f1f1f1;
        }

        .status.belum {
            color: var(--red);
            background: #fff0ef;
        }

        .status.dijadwalkan {
            color: var(--orange);
            background: #fff7e9;
        }

        .status.dalam-perjalanan {
            color: var(--blue);
            background: #eef5f9;
        }

        .status.terkirim {
            color: var(--green);
            background: #f0f7f0;
        }

        .gps-dot {
            display: inline-block;
            width: .8rem;
            height: .8rem;
            border-radius: 50%;
            background: currentColor;
            margin-right: .6rem;
        }

        .status.online .gps-dot {
            animation: gpsPulse 1.6s ease infinite;
        }

        @keyframes gpsPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(82, 120, 83, .5);
            }

            100% {
                box-shadow: 0 0 0 .8rem rgba(82, 120, 83, 0);
            }
        }

        .sub-info {
            display: block;
            font-size: 1.05rem;
            color: #999;
            margin-top: .3rem;
        }

        .map-link {
            color: var(--blue);
            border-bottom: .1rem dashed var(--blue);
            font-size: 1.1rem;
        }

        tr.jasa-row.terpilih {
            background: #f3f0e8;
        }

        tr.jasa-row.belum-gps {
            opacity: .6;
        }

        .btn[disabled] {
            opacity: .45;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* =====================================================
           SALES - FORM PENUGASAN
        ===================================================== */

        .tugas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .tugas-grid .full {
            grid-column: 1 / -1;
        }

        .jasa-terpilih-box {
            padding: 1.6rem 2rem;
            border: .15rem dashed var(--main-color);
            border-radius: var(--border-radius);
            background: #faf8f1;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 1.4rem;
        }

        .jasa-terpilih-box i {
            font-size: 2.2rem;
        }

        .jasa-terpilih-box small {
            display: block;
            color: #888;
            font-size: 1.1rem;
            margin-top: .3rem;
        }

        .form-label {
            display: block;
            font-size: 1.2rem;
            margin-bottom: .7rem;
        }

        .select-control.no-icon,
        textarea.input-control {
            padding-left: 1.3rem;
        }

        textarea.input-control {
            resize: vertical;
            min-height: 8rem;
            font-family: inherit;
        }

        .tugas-aksi {
            display: flex;
            justify-content: flex-end;
        }

        .hasil-kosong {
            display: none;
            text-align: center;
            padding: 3rem 1rem;
            color: #888;
            font-size: 1.3rem;
        }

        .hasil-kosong i {
            display: block;
            font-size: 3.4rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 900px) {

            .filter-box.filter-sales,
            .tugas-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

</head>

<body>


    <!-- =====================================================
     SIDEBAR
===================================================== -->

    <aside
        class="sidebar"
        id="sidebar">


        <a
            href="dashboar.php"
            class="logo">

            <i class="fas fa-mug-hot"></i>

            TOKU COFFEE

        </a>


        <div class="menu-title">

            Menu Utama

        </div>


        <a href="dashboar.php">

            <i class="fas fa-chart-pie"></i>

            Dashboard

        </a>


        <a
            href="orders.php">

            <i class="fas fa-shopping-bag"></i>

            Pesanan

        </a>


        <a href="../REKAYASA_E_BISNIS/produk/products.php">

            <i class="fas fa-box"></i>

            Produk

        </a>


        <a href="customers.php">

            <i class="fas fa-users"></i>

            Pelanggan

        </a>


        <a
            href="sales.php"
            class="active">

            <i class="fas fa-truck-fast"></i>

            Pengiriman

        </a>


        <div
            class="menu-title"
            style="margin-top:2rem;">

            Manajemen

        </div>


        <a href="inventory.php">

            <i class="fas fa-warehouse"></i>

            Inventory

        </a>


        <a href="suppliers.php">

            <i class="fas fa-truck"></i>

            Supplier

        </a>


        <a href="finance.php">

            <i class="fas fa-wallet"></i>

            Keuangan

        </a>


        <a href="reports.php">

            <i class="fas fa-file-lines"></i>

            Laporan

        </a>


        <div
            class="menu-title"
            style="margin-top:2rem;">

            Sistem

        </div>


        <a href="settings.php">

            <i class="fas fa-gear"></i>

            Pengaturan

        </a>
        <!-- LOGOUT -->

        <div
            class="menu-title"
            style="margin-top:2rem;">

            Akun

        </div>


        <a
            href="../REKAYASA_E_BISNIS/login/logout.php"
            class="logout-menu"
            onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">

            <i class="fas fa-right-from-bracket"></i>

            Logout

        </a>

    </aside>


    <!-- =====================================================
     MAIN
===================================================== -->

    <main class="main">


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="topbar-left">


                <i
                    class="fas fa-bars"
                    id="menu-btn">
                </i>


                <div class="page-title">

                    <h2>
                        Sales
                    </h2>

                    <p>
                        Pilih kategori jasa pengiriman barang yang sudah terdaftar GPS
                    </p>

                </div>


            </div>


            <div class="topbar-right">


                <div class="profile">


                    <img
                        src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff"
                        alt="Profile">


                    <div class="profile-info">

                        <h4>

                            <?= eSales($namaUser) ?>

                        </h4>

                        <p>

                            <?= eSales(ucfirst($roleUser)) ?>

                        </p>

                    </div>


                </div>


            </div>


        </header>


        <!-- =====================================================
         CONTENT
    ===================================================== -->

        <section class="content">


            <!-- FLASH -->

            <?php if ($successMessage): ?>

                <div class="flash success">

                    <i class="fas fa-circle-check"></i>

                    <?= eSales($successMessage) ?>

                </div>

            <?php endif; ?>


            <?php if ($errorMessage): ?>

                <div class="flash error">

                    <i class="fas fa-circle-exclamation"></i>

                    <?= eSales($errorMessage) ?>

                </div>

            <?php endif; ?>


            <?php if ($dbError): ?>

                <div class="flash error">

                    <i class="fas fa-circle-exclamation"></i>

                    <?= eSales($dbError) ?>

                </div>

            <?php endif; ?>


            <!-- =====================================================
             STATISTIK
        ===================================================== -->

            <div class="stats">


                <div class="stat">

                    <div class="stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>

                    <div>
                        <h3><?= number_format($totalKategori) ?></h3>
                        <p>Kategori Pengiriman</p>
                        <small>Layanan aktif</small>
                    </div>

                </div>


                <div class="stat">

                    <div class="stat-icon">
                        <i class="fas fa-location-crosshairs"></i>
                    </div>

                    <div>
                        <h3><?= number_format($totalGps) ?></h3>
                        <p>Jasa Terdaftar GPS</p>
                        <small><?= number_format($belumGps) ?> belum terdaftar</small>
                    </div>

                </div>


                <div class="stat">

                    <div class="stat-icon">
                        <i class="fas fa-satellite-dish"></i>
                    </div>

                    <div>
                        <h3><?= number_format($gpsOnline) ?></h3>
                        <p>GPS Online</p>
                        <small>Sinyal aktif sekarang</small>
                    </div>

                </div>


                <div class="stat">

                    <div class="stat-icon">
                        <i class="fas fa-route"></i>
                    </div>

                    <div>
                        <h3><?= number_format($penugasanAktif) ?></h3>
                        <p>Penugasan Aktif</p>
                        <small>Dijadwalkan / di jalan</small>
                    </div>

                </div>


            </div>


            <!-- =====================================================
             PILIH KATEGORI
        ===================================================== -->

            <section class="panel">

                <div class="panel-header">

                    <h2>
                        Pilih Kategori Jasa Pengiriman
                    </h2>

                    <span
                        id="kategoriInfo"
                        style="font-size:1.2rem; color:#888;">
                        Semua kategori
                    </span>

                </div>


                <div
                    class="kategori-grid"
                    id="kategoriGrid">


                    <button
                        type="button"
                        class="kategori-card active"
                        data-kategori="all"
                        data-nama="Semua kategori">

                        <span class="kategori-jumlah">
                            <?= number_format($totalGps) ?> GPS
                        </span>

                        <span class="kategori-ikon">
                            <i class="fas fa-border-all"></i>
                        </span>

                        <h3>Semua Kategori</h3>

                        <p>Tampilkan seluruh jasa pengiriman yang sudah terdaftar GPS.</p>

                    </button>


                    <?php foreach ($kategoriList as $kategori): ?>

                        <button
                            type="button"
                            class="kategori-card"
                            data-kategori="<?= (int)$kategori['id'] ?>"
                            data-nama="<?= eSales($kategori['nama_kategori']) ?>">

                            <span class="kategori-jumlah">
                                <?= number_format((int)$kategori['jumlah_gps']) ?> GPS
                            </span>

                            <span class="kategori-ikon">
                                <i class="fas <?= eSales($kategori['ikon']) ?>"></i>
                            </span>

                            <h3><?= eSales($kategori['nama_kategori']) ?></h3>

                            <p><?= eSales($kategori['deskripsi']) ?></p>

                            <small>
                                <i class="fas fa-clock"></i>
                                <?= eSales($kategori['estimasi']) ?>
                                &bull;
                                mulai <?= rupiahSales($kategori['tarif_mulai']) ?>
                            </small>

                        </button>

                    <?php endforeach; ?>


                </div>

            </section>


            <!-- =====================================================
             DAFTAR JASA PENGIRIMAN (GPS)
        ===================================================== -->

            <section class="panel">

                <div class="panel-header">

                    <h2>
                        Jasa Pengiriman Terdaftar GPS
                    </h2>

                    <span
                        id="jasaCount"
                        style="font-size:1.2rem; color:#888;">
                        0 data ditemukan
                    </span>

                </div>


                <div class="filter-box filter-sales">

                    <div class="input-group">

                        <i class="fas fa-search"></i>

                        <input
                            type="text"
                            id="cariJasa"
                            class="input-control"
                            placeholder="Cari nama jasa, plat nomor, atau ID GPS...">

                    </div>

                    <select
                        id="filterGps"
                        class="select-control">

                        <option value="terdaftar">GPS Terdaftar</option>
                        <option value="online">GPS Online</option>
                        <option value="offline">GPS Offline</option>
                        <option value="belum">Belum Terdaftar GPS</option>
                        <option value="semua">Semua Jasa</option>

                    </select>

                    <button
                        type="button"
                        class="filter-btn"
                        id="resetFilter">

                        <i class="fas fa-rotate-left"></i>
                        Reset

                    </button>

                </div>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>Jasa Pengiriman</th>
                                <th>Kategori</th>
                                <th>Kendaraan</th>
                                <th>Tarif Dasar</th>
                                <th>Status GPS</th>
                                <th>Lokasi Terakhir</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>

                        <tbody id="jasaBody">

                            <?php foreach ($jasaList as $jasa): ?>

                                <?php
                                $adaGps = (int)$jasa['gps_terdaftar'] === 1;
                                $online = $adaGps && $jasa['gps_status'] === 'online';
                                ?>

                                <tr
                                    class="jasa-row <?= $adaGps ? '' : 'belum-gps' ?>"
                                    data-id="<?= (int)$jasa['id'] ?>"
                                    data-kategori="<?= (int)$jasa['kategori_id'] ?>"
                                    data-gps="<?= $adaGps ? 'terdaftar' : 'belum' ?>"
                                    data-online="<?= $online ? '1' : '0' ?>"
                                    data-nama="<?= eSales($jasa['nama_jasa']) ?>"
                                    data-kategori-nama="<?= eSales($jasa['nama_kategori']) ?>"
                                    data-device="<?= eSales($jasa['gps_device_id']) ?>"
                                    data-cari="<?= eSales(strtolower($jasa['nama_jasa'] . ' ' . $jasa['plat_nomor'] . ' ' . $jasa['gps_device_id'] . ' ' . $jasa['nama_kategori'])) ?>">

                                    <td>

                                        <div class="customer-name">
                                            <?= eSales($jasa['nama_jasa']) ?>
                                        </div>

                                        <span class="sub-info">
                                            <?= $adaGps ? eSales($jasa['gps_device_id']) : 'Tanpa perangkat GPS' ?>
                                            &bull;
                                            <?= eSales($jasa['telepon']) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <?= eSales($jasa['nama_kategori']) ?>

                                        <span class="sub-info">
                                            <?= eSales($jasa['estimasi']) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <?= eSales($jasa['kendaraan']) ?>

                                        <span class="sub-info">
                                            <?= eSales($jasa['plat_nomor']) ?>
                                        </span>

                                    </td>

                                    <td class="invoice">
                                        <?= rupiahSales($jasa['tarif_dasar']) ?>
                                    </td>

                                    <td>

                                        <?php if (!$adaGps): ?>

                                            <span class="status belum">
                                                Belum Terdaftar
                                            </span>

                                        <?php elseif ($online): ?>

                                            <span class="status online">
                                                <span class="gps-dot"></span>
                                                Online
                                            </span>

                                        <?php else: ?>

                                            <span class="status offline">
                                                <span class="gps-dot"></span>
                                                Offline
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if ($adaGps && $jasa['gps_lat'] !== null && $jasa['gps_lng'] !== null): ?>

                                            <a
                                                class="map-link"
                                                target="_blank"
                                                rel="noopener"
                                                href="https://www.google.com/maps?q=<?= eSales($jasa['gps_lat']) ?>,<?= eSales($jasa['gps_lng']) ?>">

                                                <i class="fas fa-location-dot"></i>
                                                Lihat di peta

                                            </a>

                                            <span class="sub-info">
                                                <?= eSales(waktuGps($jasa['gps_update'])) ?>
                                            </span>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <button
                                            type="button"
                                            class="btn btn-pilih"
                                            <?= $adaGps ? '' : 'disabled' ?>>

                                            <i class="fas fa-circle-check"></i>
                                            Pilih

                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <div
                    class="hasil-kosong"
                    id="jasaKosong">

                    <i class="fas fa-truck-ramp-box"></i>

                    Tidak ada jasa pengiriman yang sesuai dengan filter.

                </div>

            </section>


            <!-- =====================================================
             TUGASKAN KE PESANAN
        ===================================================== -->

            <section
                class="panel"
                id="panelTugas">

                <div class="panel-header">

                    <h2>
                        Tugaskan ke Pesanan
                    </h2>

                </div>


                <form
                    method="POST"
                    action="sales.php"
                    id="formTugas">

                    <input
                        type="hidden"
                        name="action"
                        value="tugaskan">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= eSales($_SESSION['csrf_sales']) ?>">

                    <input
                        type="hidden"
                        name="jasa_id"
                        id="jasaId"
                        value="">


                    <div class="tugas-grid">


                        <div class="full">

                            <div class="jasa-terpilih-box">

                                <i class="fas fa-truck-fast"></i>

                                <div>

                                    <strong id="jasaTerpilihNama">
                                        Belum ada jasa dipilih
                                    </strong>

                                    <small id="jasaTerpilihInfo">
                                        Pilih kategori, lalu klik tombol Pilih pada jasa yang GPS-nya terdaftar.
                                    </small>

                                </div>

                            </div>

                        </div>


                        <div>

                            <label
                                class="form-label"
                                for="pesananId">
                                Pesanan
                            </label>

                            <select
                                name="pesanan_id"
                                id="pesananId"
                                class="select-control no-icon"
                                required>

                                <option value="">
                                    <?= $pesananList ? 'Pilih pesanan' : 'Tidak ada pesanan yang menunggu pengiriman' ?>
                                </option>

                                <?php foreach ($pesananList as $pesanan): ?>

                                    <option value="<?= (int)$pesanan['id'] ?>">

                                        <?= eSales($pesanan['invoice']) ?>
                                        -
                                        <?= eSales($pesanan['full_name']) ?>
                                        -
                                        <?= rupiahSales($pesanan['total']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div>

                            <label
                                class="form-label"
                                for="catatan">
                                Catatan (opsional)
                            </label>

                            <textarea
                                name="catatan"
                                id="catatan"
                                class="input-control"
                                maxlength="500"
                                placeholder="Contoh: kirim sebelum jam 15.00"></textarea>

                        </div>


                        <div class="full tugas-aksi">

                            <button
                                type="submit"
                                class="btn"
                                id="btnTugas"
                                disabled>

                                <i class="fas fa-paper-plane"></i>
                                Tugaskan Pengiriman

                            </button>

                        </div>


                    </div>

                </form>

            </section>


            <!-- =====================================================
             RIWAYAT PENUGASAN
        ===================================================== -->

            <section class="panel">

                <div class="panel-header">

                    <h2>
                        Penugasan Terbaru
                    </h2>

                </div>


                <?php if ($riwayatList): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>
                                    <th>Invoice</th>
                                    <th>Jasa Pengiriman</th>
                                    <th>Kategori</th>
                                    <th>ID GPS</th>
                                    <th>Status</th>
                                    <th>Dibuat</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($riwayatList as $riwayat): ?>

                                    <tr>

                                        <td class="invoice">
                                            <?= eSales($riwayat['invoice']) ?>
                                        </td>

                                        <td>
                                            <?= eSales($riwayat['nama_jasa']) ?>
                                        </td>

                                        <td>
                                            <?= eSales($riwayat['nama_kategori']) ?>
                                        </td>

                                        <td>
                                            <?= eSales($riwayat['gps_device_id']) ?>
                                        </td>

                                        <td>
                                            <span class="status <?= eSales(statusClassSales($riwayat['status'])) ?>">
                                                <?= eSales($riwayat['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= eSales(waktuGps($riwayat['created_at'])) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div
                        class="hasil-kosong"
                        style="display:block;">

                        <i class="fas fa-route"></i>

                        Belum ada penugasan pengiriman.

                    </div>

                <?php endif; ?>

            </section>

            <!-- =====================================================
             KELOLA STATUS PENGIRIMAN - FITUR TAMBAHAN
        ===================================================== -->

            <section class="panel">

                <div class="panel-header">

                    <h2>
                        Kelola Pengiriman
                    </h2>

                    <span style="font-size:1.2rem; color:#888;">
                        Resi, ETA, status dan pelacakan
                    </span>

                </div>

                <?php if ($pengirimanList): ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>
                                    <th>Invoice</th>
                                    <th>Resi</th>
                                    <th>Jasa / Kurir</th>
                                    <th>ETA</th>
                                    <th>Status</th>
                                    <th>Pelacakan</th>
                                    <th>Ubah Status</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($pengirimanList as $pengiriman): ?>

                                    <tr>

                                        <td class="invoice">
                                            <?= eSales($pengiriman['invoice']) ?>
                                            <span class="sub-info">
                                                <?= eSales($pengiriman['full_name']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <strong><?= eSales($pengiriman['resi'] ?: '-') ?></strong>
                                            <span class="sub-info">
                                                <?= eSales($pengiriman['kurir_telepon'] ?: '-') ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= eSales($pengiriman['nama_jasa']) ?>
                                            <span class="sub-info">
                                                <?= eSales($pengiriman['kurir_nama'] ?: $pengiriman['nama_jasa']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= $pengiriman['estimasi_tiba'] ? eSales(date('d/m/Y', strtotime($pengiriman['estimasi_tiba']))) : '-' ?>
                                        </td>

                                        <td>
                                            <span class="status <?= eSales(statusClassSales($pengiriman['status'])) ?>">
                                                <?= eSales($pengiriman['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <a
                                                href="pelanggan/tracking.php?pesanan=<?= (int)$pengiriman['pesanan_id'] ?>"
                                                class="btn"
                                                target="_blank"
                                                rel="noopener">
                                                <i class="fas fa-location-dot"></i>
                                                Lacak
                                            </a>
                                        </td>

                                        <td>
                                            <form method="POST" action="sales.php" style="display:flex; gap:.5rem; align-items:center;">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="update_status">

                                                <input
                                                    type="hidden"
                                                    name="csrf"
                                                    value="<?= eSales($_SESSION['csrf_sales']) ?>">

                                                <input
                                                    type="hidden"
                                                    name="pengiriman_id"
                                                    value="<?= (int)$pengiriman['id'] ?>">

                                                <select
                                                    name="status_pengiriman"
                                                    class="select-control no-icon"
                                                    style="min-width:17rem; padding-left:1rem;"
                                                    required>

                                                    <option value="Dijadwalkan" <?= $pengiriman['status'] === 'Dijadwalkan' ? 'selected' : '' ?>>Dijadwalkan</option>
                                                    <option value="Dalam Perjalanan" <?= $pengiriman['status'] === 'Dalam Perjalanan' ? 'selected' : '' ?>>Dalam Perjalanan</option>
                                                    <option value="Terkirim" <?= $pengiriman['status'] === 'Terkirim' ? 'selected' : '' ?>>Terkirim</option>
                                                    <option value="Dibatalkan" <?= $pengiriman['status'] === 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>

                                                </select>

                                                <button
                                                    type="submit"
                                                    class="btn"
                                                    title="Simpan status">
                                                    <i class="fas fa-save"></i>
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="hasil-kosong" style="display:block;">
                        <i class="fas fa-truck"></i>
                        Belum ada data pengiriman yang bisa dikelola.
                    </div>

                <?php endif; ?>

            </section>


            <!-- =====================================================
             UPDATE GPS KURIR - FITUR TAMBAHAN
        ===================================================== -->

            <section class="panel">

                <div class="panel-header">

                    <h2>
                        Update GPS Kurir
                    </h2>

                    <span style="font-size:1.2rem; color:#888;">
                        Masukkan koordinat GPS terbaru
                    </span>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Jasa Pengiriman</th>
                                <th>ID GPS</th>
                                <th>Status</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($jasaList as $gpsJasa): ?>

                                <?php if ((int)$gpsJasa['gps_terdaftar'] !== 1) continue; ?>

                                <tr>

                                    <td>
                                        <strong><?= eSales($gpsJasa['nama_jasa']) ?></strong>
                                        <span class="sub-info">
                                            <?= eSales($gpsJasa['kendaraan']) ?>
                                            &bull;
                                            <?= eSales($gpsJasa['plat_nomor']) ?>
                                        </span>
                                    </td>

                                    <td><?= eSales($gpsJasa['gps_device_id']) ?></td>

                                    <td>
                                        <span class="status <?= eSales($gpsJasa['gps_status'] === 'online' ? 'online' : 'offline') ?>">
                                            <span class="gps-dot"></span>
                                            <?= eSales(ucfirst($gpsJasa['gps_status'])) ?>
                                        </span>
                                        <span class="sub-info"><?= eSales(waktuGps($gpsJasa['gps_update'])) ?></span>
                                    </td>

                                    <td colspan="3">

                                        <form method="POST" action="sales.php" style="display:grid; grid-template-columns:1fr 1fr auto; gap:.6rem;">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="update_gps">

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= eSales($_SESSION['csrf_sales']) ?>">

                                            <input
                                                type="hidden"
                                                name="jasa_id"
                                                value="<?= (int)$gpsJasa['id'] ?>">

                                            <input
                                                type="number"
                                                name="gps_lat"
                                                step="0.0000001"
                                                min="-90"
                                                max="90"
                                                class="input-control"
                                                style="padding-left:1rem;"
                                                value="<?= $gpsJasa['gps_lat'] !== null ? eSales($gpsJasa['gps_lat']) : '' ?>"
                                                placeholder="Latitude"
                                                required>

                                            <input
                                                type="number"
                                                name="gps_lng"
                                                step="0.0000001"
                                                min="-180"
                                                max="180"
                                                class="input-control"
                                                style="padding-left:1rem;"
                                                value="<?= $gpsJasa['gps_lng'] !== null ? eSales($gpsJasa['gps_lng']) : '' ?>"
                                                placeholder="Longitude"
                                                required>

                                            <button
                                                type="submit"
                                                class="btn">
                                                <i class="fas fa-location-crosshairs"></i>
                                                Update GPS
                                            </button>

                                        </form>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </section>


        </section>


    </main>


    <!-- =====================================================
     JAVASCRIPT
===================================================== -->

    <script>
        /* =====================================================
           MOBILE SIDEBAR
        ===================================================== */

        const menuBtn =
            document.getElementById('menu-btn');

        const sidebar =
            document.getElementById('sidebar');

        menuBtn.addEventListener(
            'click',
            function() {

                sidebar.classList.toggle('active');

                this.classList.toggle('fa-bars');

                this.classList.toggle('fa-xmark');

            }
        );


        document
            .querySelectorAll('.sidebar a')
            .forEach(function(link) {

                link.addEventListener(
                    'click',
                    function() {

                        if (window.innerWidth <= 900) {

                            sidebar.classList.remove('active');

                            menuBtn.classList.remove('fa-xmark');

                            menuBtn.classList.add('fa-bars');

                        }

                    }
                );

            });


        document.addEventListener(
            'click',
            function(event) {

                if (
                    window.innerWidth <= 900 &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !menuBtn.contains(event.target)
                ) {

                    sidebar.classList.remove('active');

                    menuBtn.classList.remove('fa-xmark');

                    menuBtn.classList.add('fa-bars');

                }

            }
        );


        /* =====================================================
           SALES - KATEGORI, FILTER, DAN PILIH JASA
        ===================================================== */

        (function() {

            const kategoriCards =
                document.querySelectorAll('.kategori-card');

            const rows =
                document.querySelectorAll('.jasa-row');

            const cari =
                document.getElementById('cariJasa');

            const filterGps =
                document.getElementById('filterGps');

            const resetBtn =
                document.getElementById('resetFilter');

            const jasaCount =
                document.getElementById('jasaCount');

            const jasaKosong =
                document.getElementById('jasaKosong');

            const kategoriInfo =
                document.getElementById('kategoriInfo');

            const jasaId =
                document.getElementById('jasaId');

            const pesananId =
                document.getElementById('pesananId');

            const btnTugas =
                document.getElementById('btnTugas');

            const namaBox =
                document.getElementById('jasaTerpilihNama');

            const infoBox =
                document.getElementById('jasaTerpilihInfo');

            let kategoriAktif = 'all';


            function terapkanFilter() {

                const kata = cari.value.trim().toLowerCase();

                const modeGps = filterGps.value;

                let tampil = 0;

                rows.forEach(function(row) {

                    let cocok = true;

                    if (
                        kategoriAktif !== 'all' &&
                        row.dataset.kategori !== kategoriAktif
                    ) {
                        cocok = false;
                    }

                    if (kata && row.dataset.cari.indexOf(kata) === -1) {
                        cocok = false;
                    }

                    if (modeGps === 'terdaftar' && row.dataset.gps !== 'terdaftar') {
                        cocok = false;
                    }

                    if (
                        modeGps === 'online' &&
                        !(row.dataset.gps === 'terdaftar' && row.dataset.online === '1')
                    ) {
                        cocok = false;
                    }

                    if (
                        modeGps === 'offline' &&
                        !(row.dataset.gps === 'terdaftar' && row.dataset.online === '0')
                    ) {
                        cocok = false;
                    }

                    if (modeGps === 'belum' && row.dataset.gps !== 'belum') {
                        cocok = false;
                    }

                    row.style.display = cocok ? '' : 'none';

                    if (cocok) {
                        tampil++;
                    }

                });

                jasaCount.textContent =
                    tampil + ' data ditemukan';

                jasaKosong.style.display =
                    tampil === 0 ? 'block' : 'none';

            }


            function perbaruiTombol() {

                btnTugas.disabled = !(jasaId.value && pesananId.value);

            }


            kategoriCards.forEach(function(card) {

                card.addEventListener('click', function() {

                    kategoriCards.forEach(function(c) {
                        c.classList.remove('active');
                    });

                    card.classList.add('active');

                    kategoriAktif = card.dataset.kategori;

                    kategoriInfo.textContent =
                        'Kategori: ' + card.dataset.nama;

                    terapkanFilter();

                });

            });


            rows.forEach(function(row) {

                const tombol = row.querySelector('.btn-pilih');

                if (!tombol || tombol.disabled) {
                    return;
                }

                tombol.addEventListener('click', function() {

                    rows.forEach(function(r) {
                        r.classList.remove('terpilih');
                    });

                    row.classList.add('terpilih');

                    jasaId.value = row.dataset.id;

                    namaBox.textContent = row.dataset.nama;

                    infoBox.textContent =
                        row.dataset.kategoriNama +
                        ' \u2022 ID GPS ' +
                        row.dataset.device;

                    perbaruiTombol();

                    document
                        .getElementById('panelTugas')
                        .scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });

                });

            });


            cari.addEventListener('input', terapkanFilter);

            filterGps.addEventListener('change', terapkanFilter);

            pesananId.addEventListener('change', perbaruiTombol);

            resetBtn.addEventListener('click', function() {

                cari.value = '';

                filterGps.value = 'terdaftar';

                kategoriCards[0].click();

            });

            terapkanFilter();

        })();
    </script>

</body>

</html>
