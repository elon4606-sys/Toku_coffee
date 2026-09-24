<?php

require_once "config/koneksi.php";
require_once "config/session.php";

requireRole(['admin', 'staff']);

/* =========================================================
   FUNGSI BANTUAN
========================================================= */

if (!function_exists('rupiah')) {
    function rupiah($angka)
    {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('statusClass')) {
    function statusClass($status)
    {
        return strtolower(str_replace([' ', '_'], '-', $status));
    }
}


/* =========================================================
   USER LOGIN
========================================================= */

$namaUser = $_SESSION['full_name']
    ?? $_SESSION['name']
    ?? $_SESSION['username']
    ?? 'Admin ERP';

$roleUser = $_SESSION['role']
    ?? 'Administrator';

$avatarName = urlencode($namaUser);

$userId = $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? null;


/* =========================================================
   PROSES MUTASI STOK
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mutasi') {

    $produkId = (int)($_POST['produk_id'] ?? 0);
    $gudangId = (int)($_POST['gudang_id'] ?? 0);
    $tipe = trim($_POST['tipe'] ?? '');
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    $allowedTypes = ['Masuk', 'Keluar', 'Penyesuaian'];

    if ($produkId <= 0 || $gudangId <= 0) {

        header(
            "Location: inventory.php?error=" .
                urlencode("Produk dan gudang wajib dipilih.")
        );

        exit;
    }

    if (!in_array($tipe, $allowedTypes, true)) {

        header(
            "Location: inventory.php?error=" .
                urlencode("Tipe mutasi tidak valid.")
        );

        exit;
    }

    if ($jumlah < 0) {

        header(
            "Location: inventory.php?error=" .
                urlencode("Jumlah stok tidak valid.")
        );

        exit;
    }


    try {

        $conn->begin_transaction();


        /* =====================================================
           CEK PRODUK
        ===================================================== */

        $stmt = $conn->prepare("
            SELECT
                id,
                nama_produk,
                stok_minimum
            FROM produk
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Gagal menyiapkan query produk.");
        }

        $stmt->bind_param(
            "i",
            $produkId
        );

        $stmt->execute();

        $produkResult = $stmt->get_result();

        if (
            !$produkResult ||
            $produkResult->num_rows === 0
        ) {

            throw new Exception(
                "Produk tidak ditemukan."
            );
        }

        $produkData =
            $produkResult->fetch_assoc();

        $stmt->close();


        /* =====================================================
           CEK GUDANG
        ===================================================== */

        $stmt = $conn->prepare("
            SELECT
                id,
                nama_gudang
            FROM gudang
            WHERE id = ?
            AND status = 'aktif'
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Gagal menyiapkan query gudang.");
        }

        $stmt->bind_param(
            "i",
            $gudangId
        );

        $stmt->execute();

        $gudangResult =
            $stmt->get_result();

        if (
            !$gudangResult ||
            $gudangResult->num_rows === 0
        ) {

            throw new Exception(
                "Gudang tidak ditemukan atau tidak aktif."
            );
        }

        $stmt->close();


        /* =====================================================
           CEK INVENTORY
        ===================================================== */

        $stmt = $conn->prepare("
            SELECT
                id,
                stok,
                stok_minimum
            FROM inventory
            WHERE produk_id = ?
            AND gudang_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception("Gagal menyiapkan query inventory.");
        }

        $stmt->bind_param(
            "ii",
            $produkId,
            $gudangId
        );

        $stmt->execute();

        $inventoryResult =
            $stmt->get_result();


        $inventoryId = 0;

        $stokSebelum = 0;

        $stokMinimum =
            (int)$produkData['stok_minimum'];


        if (
            $inventoryResult &&
            $inventoryResult->num_rows > 0
        ) {

            $inventoryData =
                $inventoryResult->fetch_assoc();

            $inventoryId =
                (int)$inventoryData['id'];

            $stokSebelum =
                (int)$inventoryData['stok'];

            $stokMinimum =
                (int)$inventoryData['stok_minimum'];
        }

        $stmt->close();


        /* =====================================================
           HITUNG STOK BARU
        ===================================================== */

        if ($tipe === 'Masuk') {

            $stokSesudah =
                $stokSebelum + $jumlah;

            $jumlahMutasi =
                $jumlah;
        } elseif ($tipe === 'Keluar') {

            if ($jumlah > $stokSebelum) {

                throw new Exception(
                    "Stok tidak mencukupi. Stok tersedia: " .
                        $stokSebelum
                );
            }

            $stokSesudah =
                $stokSebelum - $jumlah;

            $jumlahMutasi =
                $jumlah;
        } else {

            /*
             * Penyesuaian =
             * jumlah dianggap sebagai stok akhir
             */

            $stokSesudah =
                $jumlah;

            $jumlahMutasi =
                abs(
                    $stokSesudah -
                        $stokSebelum
                );
        }


        /* =====================================================
           INSERT / UPDATE INVENTORY
        ===================================================== */

        if ($inventoryId > 0) {

            $stmt = $conn->prepare("
                UPDATE inventory
                SET
                    stok = ?,
                    stok_minimum = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    "Gagal menyiapkan update inventory."
                );
            }

            $stmt->bind_param(
                "iii",
                $stokSesudah,
                $stokMinimum,
                $inventoryId
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Gagal memperbarui inventory."
                );
            }

            $stmt->close();
        } else {

            $stmt = $conn->prepare("
                INSERT INTO inventory
                (
                    produk_id,
                    gudang_id,
                    stok,
                    stok_minimum
                )
                VALUES (?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception(
                    "Gagal menyiapkan insert inventory."
                );
            }

            $stmt->bind_param(
                "iiii",
                $produkId,
                $gudangId,
                $stokSesudah,
                $stokMinimum
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Gagal menambahkan inventory."
                );
            }

            $inventoryId =
                $stmt->insert_id;

            $stmt->close();
        }


        /* =====================================================
           HITUNG TOTAL STOK PRODUK DARI SEMUA GUDANG
        ===================================================== */

        $stmt = $conn->prepare("
            SELECT
                COALESCE(SUM(stok), 0) AS total_stok
            FROM inventory
            WHERE produk_id = ?
        ");

        if (!$stmt) {
            throw new Exception(
                "Gagal menghitung total stok."
            );
        }

        $stmt->bind_param(
            "i",
            $produkId
        );

        $stmt->execute();

        $totalStockResult =
            $stmt->get_result();

        $totalStockData =
            $totalStockResult->fetch_assoc();

        $totalStockProduk =
            (int)$totalStockData['total_stok'];

        $stmt->close();


        /* =====================================================
           SINKRONISASI STOK DI TABEL PRODUK
        ===================================================== */

        $stmt = $conn->prepare("
            UPDATE produk
            SET
                stok = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new Exception(
                "Gagal menyiapkan sinkronisasi stok."
            );
        }

        $stmt->bind_param(
            "ii",
            $totalStockProduk,
            $produkId
        );

        if (!$stmt->execute()) {

            throw new Exception(
                "Gagal sinkronisasi stok produk."
            );
        }

        $stmt->close();


        /* =====================================================
           CATAT MUTASI
        ===================================================== */

        if ($keterangan === '') {

            if ($tipe === 'Masuk') {

                $keterangan =
                    'Penambahan stok';
            } elseif ($tipe === 'Keluar') {

                $keterangan =
                    'Pengeluaran stok';
            } else {

                $keterangan =
                    'Penyesuaian stok';
            }
        }


        if ($tipe === 'Penyesuaian') {

            $keterangan .=
                " dari " .
                $stokSebelum .
                " menjadi " .
                $stokSesudah;
        }


        if ($userId !== null) {

            $stmt = $conn->prepare("
                INSERT INTO mutasi_stok
                (
                    produk_id,
                    gudang_id,
                    user_id,
                    tipe,
                    jumlah,
                    stok_sebelum,
                    stok_sesudah,
                    keterangan
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception(
                    "Gagal menyiapkan riwayat mutasi."
                );
            }

            $stmt->bind_param(
                "iiisiiss",
                $produkId,
                $gudangId,
                $userId,
                $tipe,
                $jumlahMutasi,
                $stokSebelum,
                $stokSesudah,
                $keterangan
            );
        } else {

            $stmt = $conn->prepare("
                INSERT INTO mutasi_stok
                (
                    produk_id,
                    gudang_id,
                    user_id,
                    tipe,
                    jumlah,
                    stok_sebelum,
                    stok_sesudah,
                    keterangan
                )
                VALUES (?, ?, NULL, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception(
                    "Gagal menyiapkan riwayat mutasi."
                );
            }

            $stmt->bind_param(
                "iisiiis",
                $produkId,
                $gudangId,
                $tipe,
                $jumlahMutasi,
                $stokSebelum,
                $stokSesudah,
                $keterangan
            );
        }


        if (!$stmt->execute()) {

            throw new Exception(
                "Gagal menyimpan riwayat mutasi."
            );
        }

        $stmt->close();


        /* =====================================================
           COMMIT
        ===================================================== */

        $conn->commit();


        header(
            "Location: inventory.php?success=" .
                urlencode("Mutasi stok berhasil disimpan.")
        );

        exit;
    } catch (Throwable $e) {

        if ($conn->errno) {
            $conn->rollback();
        }

        header(
            "Location: inventory.php?error=" .
                urlencode($e->getMessage())
        );

        exit;
    }
}


/* =========================================================
   PESAN SUKSES / ERROR
========================================================= */

$successMessage =
    $_GET['success'] ?? '';

$errorMessage =
    $_GET['error'] ?? '';


/* =========================================================
   STATISTIK INVENTORY
========================================================= */

$totalStok = 0;

$jenisProduk = 0;

$stokMenipis = 0;

$produkHabis = 0;


/* =========================================================
   TOTAL STOK
========================================================= */

$result = $conn->query("
    SELECT
        COALESCE(SUM(stok), 0) AS total
    FROM inventory
");

if ($result) {

    $row =
        $result->fetch_assoc();

    $totalStok =
        (int)$row['total'];
}


/* =========================================================
   JENIS PRODUK
========================================================= */

$result = $conn->query("
    SELECT
        COUNT(DISTINCT produk_id) AS total
    FROM inventory
");

if ($result) {

    $row =
        $result->fetch_assoc();

    $jenisProduk =
        (int)$row['total'];
}


/* =========================================================
   STOK MENIPIS
========================================================= */

$result = $conn->query("
    SELECT
        COUNT(DISTINCT produk_id) AS total
    FROM inventory
    WHERE stok > 0
    AND stok <= stok_minimum
");

if ($result) {

    $row =
        $result->fetch_assoc();

    $stokMenipis =
        (int)$row['total'];
}


/* =========================================================
   PRODUK HABIS
========================================================= */

$result = $conn->query("
    SELECT
        COUNT(DISTINCT produk_id) AS total
    FROM inventory
    WHERE stok <= 0
");

if ($result) {

    $row =
        $result->fetch_assoc();

    $produkHabis =
        (int)$row['total'];
}


/* =========================================================
   NOTIFIKASI
========================================================= */

$totalNotifikasi =
    $stokMenipis +
    $produkHabis;


/* =========================================================
   DATA INVENTORY
========================================================= */

$inventoryData = [];

$result = $conn->query("
    SELECT
        i.id,
        i.produk_id,
        i.gudang_id,
        i.stok,
        i.stok_minimum,
        i.updated_at,

        p.sku,
        p.nama_produk,
        p.harga,
        p.satuan,
        p.gambar,
        p.status AS status_produk,

        k.nama_kategori,

        g.nama_gudang

    FROM inventory i

    INNER JOIN produk p
        ON i.produk_id = p.id

    LEFT JOIN kategori k
        ON p.kategori_id = k.id

    INNER JOIN gudang g
        ON i.gudang_id = g.id

    ORDER BY
        CASE
            WHEN i.stok <= 0 THEN 1
            WHEN i.stok <= i.stok_minimum THEN 2
            ELSE 3
        END,
        i.id DESC
");

if ($result) {

    while ($row =
        $result->fetch_assoc()
    ) {

        $stok =
            (int)$row['stok'];

        $minimum =
            (int)$row['stok_minimum'];


        /* =====================================================
           STATUS
        ===================================================== */

        if ($stok <= 0) {

            $status =
                'Habis';
        } elseif ($stok <= $minimum) {

            $status =
                'Menipis';
        } else {

            $status =
                'Aman';
        }


        /* =====================================================
           PROGRESS
        ===================================================== */

        $batasProgress =
            max(
                $minimum * 3,
                1
            );

        $progress =
            ($stok / $batasProgress) * 100;

        $progress =
            max(
                0,
                min(
                    100,
                    $progress
                )
            );


        /* =====================================================
           SIMPAN DATA
        ===================================================== */

        $inventoryData[] = [

            'id' =>
            (int)$row['id'],

            'produk_id' =>
            (int)$row['produk_id'],

            'gudang_id' =>
            (int)$row['gudang_id'],

            'sku' =>
            $row['sku'],

            'nama_produk' =>
            $row['nama_produk'],

            'kategori' =>
            $row['nama_kategori']
                ?: 'Tanpa Kategori',

            'gudang' =>
            $row['nama_gudang'],

            'stok' =>
            $stok,

            'minimum' =>
            $minimum,

            'satuan' =>
            $row['satuan']
                ?: 'Pcs',

            'harga' =>
            (float)$row['harga'],

            'nilai_stok' =>
            $stok *
                (float)$row['harga'],

            'status' =>
            $status,

            'status_produk' =>
            $row['status_produk'],

            'gambar' =>
            $row['gambar'],

            'updated_at' =>
            $row['updated_at'],

            'progress' =>
            round($progress)

        ];
    }
}


/* =========================================================
   DATA PRODUK UNTUK FORM
========================================================= */

$produkList = [];

$result = $conn->query("
    SELECT
        p.id,
        p.sku,
        p.nama_produk,
        p.stok_minimum,
        p.satuan,
        p.status
    FROM produk p
    WHERE p.status = 'aktif'
    ORDER BY p.nama_produk ASC
");

if ($result) {

    while ($row =
        $result->fetch_assoc()
    ) {

        $produkList[] =
            $row;
    }
}


/* =========================================================
   DATA GUDANG
========================================================= */

$gudangList = [];

$result = $conn->query("
    SELECT
        id,
        nama_gudang,
        status
    FROM gudang
    WHERE status = 'aktif'
    ORDER BY nama_gudang ASC
");

if ($result) {

    while ($row =
        $result->fetch_assoc()
    ) {

        $gudangList[] =
            $row;
    }
}


/* =========================================================
   JUMLAH DATA
========================================================= */

$totalInventoryRows =
    count($inventoryData);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Inventory - Toku Coffee ERP
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

            --bg:
                #faf9f5;

            --white:
                #fff;

            --green:
                #527853;

            --orange:
                #c68b3c;

            --red:
                #a94442;

            --blue:
                #557a95;

            --gray:
                #888;

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


        button,
        input,
        select,
        textarea {

            font: inherit;

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

            background:
                #fff;

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

            margin-right:
                .5rem;

        }


        .menu-title {

            font-size:
                1.1rem;

            color:
                #aaa;

            padding:
                0 1.5rem;

            margin-bottom:
                1rem;

            text-transform:
                uppercase;

        }


        .sidebar a {

            display:
                flex;

            align-items:
                center;

            gap:
                1.3rem;

            padding:
                1.3rem 1.5rem;

            margin-bottom:
                .7rem;

            color:
                var(--main-color);

            font-size:
                1.5rem;

            border-radius:
                var(--border-radius);

            position:
                relative;

        }


        .sidebar a i {

            width:
                2rem;

            font-size:
                1.7rem;

            text-align:
                center;

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

            margin-left:
                26rem;

            min-height:
                100vh;

        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {

            height:
                8rem;

            background:
                #fff;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                0 4rem;

            box-shadow:
                0 .3rem 1rem rgba(0, 0, 0, .05);

            position:
                sticky;

            top:
                0;

            z-index:
                900;

        }


        .topbar-left {

            display:
                flex;

            align-items:
                center;

            gap:
                1.5rem;

        }


        .page-title h2 {

            font-size:
                2.4rem;

            animation:
                fadeDown .5s ease;

        }


        .page-title p {

            font-size:
                1.3rem;

            color:
                #999;

        }


        #menu-btn {

            display:
                none;

            font-size:
                2.5rem;

            cursor:
                pointer;

        }


        #menu-btn:hover {

            transform:
                rotate(5deg) scale(1.1);

        }


        .topbar-right {

            display:
                flex;

            align-items:
                center;

            gap:
                2rem;

        }


        /* =====================================================
           NOTIFICATION
        ===================================================== */

        .notification {

            position:
                relative;

            font-size:
                2rem;

            cursor:
                pointer;

        }


        .notification:hover i {

            transform:
                rotate(-12deg) scale(1.08);

        }


        .notification span {

            position:
                absolute;

            top:
                -1rem;

            right:
                -1rem;

            min-width:
                1.8rem;

            height:
                1.8rem;

            padding:
                0 .4rem;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                50%;

            background:
                var(--red);

            color:
                #fff;

            font-size:
                1rem;

            animation:
                pulse 2s infinite;

        }


        /* =====================================================
           PROFILE
        ===================================================== */

        .profile {

            display:
                flex;

            align-items:
                center;

            gap:
                1rem;

        }


        .profile img {

            width:
                4rem;

            height:
                4rem;

            border-radius:
                50%;

            border:
                .2rem solid var(--main-color);

        }


        .profile:hover img {

            transform:
                scale(1.08) rotate(3deg);

        }


        .profile h4 {

            font-size:
                1.4rem;

        }


        .profile p {

            font-size:
                1.1rem;

            color:
                #999;

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

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                1rem;

            margin-bottom:
                2rem;

        }


        .page-description {

            font-size:
                1.3rem;

            color:
                #888;

        }


        .action-group {

            display:
                flex;

            gap:
                1rem;

        }


        .btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                .7rem;

            padding:
                1rem 1.7rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            background:
                none;

            cursor:
                pointer;

            font-size:
                1.3rem;

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


        .btn-primary {

            background:
                var(--main-color);

            color:
                #fff;

        }


        .btn-primary:hover {

            background:
                #5b5b43;

            color:
                #fff;

        }


        /* =====================================================
           ALERT
        ===================================================== */

        .alert {

            display:
                flex;

            align-items:
                center;

            gap:
                1rem;

            padding:
                1.4rem 1.7rem;

            margin-bottom:
                2rem;

            background:
                #fff;

            border:
                .15rem solid;

            border-radius:
                var(--border-radius);

            font-size:
                1.2rem;

            animation:
                fadeUp .5s ease;

        }


        .alert.success {

            color:
                var(--green);

            border-color:
                var(--green);

            background:
                #f4faf4;

        }


        .alert.error {

            color:
                var(--red);

            border-color:
                var(--red);

            background:
                #fff4f3;

        }


        .alert i {

            font-size:
                1.7rem;

        }


        /* =====================================================
           STATISTICS
        ===================================================== */

        .stats {

            display:
                grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap:
                1.8rem;

            margin-bottom:
                3rem;

        }


        .stat {

            background:
                #fff;

            padding:
                2rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            display:
                flex;

            align-items:
                center;

            gap:
                1.5rem;

            animation:
                cardAppear .6s ease both;

        }


        .stat:nth-child(1) {

            animation-delay:
                .05s;

        }


        .stat:nth-child(2) {

            animation-delay:
                .10s;

        }


        .stat:nth-child(3) {

            animation-delay:
                .15s;

        }


        .stat:nth-child(4) {

            animation-delay:
                .20s;

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

            width:
                5rem;

            height:
                5rem;

            flex:
                0 0 5rem;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                .15rem solid var(--main-color);

            border-radius:
                45% 55% 60% 40%;

            font-size:
                2rem;

        }


        .stat:hover .stat-icon {

            transform:
                rotate(-5deg) scale(1.08);

        }


        .stat h3 {

            font-size:
                2.1rem;

            line-height:
                1.3;

        }


        .stat p {

            font-size:
                1.2rem;

            color:
                #888;

        }


        .stat small {

            display:
                block;

            color:
                var(--green);

            font-size:
                1rem;

            margin-top:
                .4rem;

        }


        .stat.warning small {

            color:
                var(--orange);

        }


        .stat.danger small {

            color:
                var(--red);

        }


        /* =====================================================
           PANEL
        ===================================================== */

        .panel {

            background:
                #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

            transition:
                border-radius .25s ease,
                border .25s ease,
                transform .25s ease,
                box-shadow .25s ease;

        }


        .panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .panel-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                1rem;

            margin-bottom:
                2rem;

        }


        .panel-header h2 {

            font-size:
                1.9rem;

        }


        .panel-header p {

            font-size:
                1.1rem;

            color:
                #999;

            margin-top:
                .3rem;

        }


        /* =====================================================
           TOOLBAR
        ===================================================== */

        .toolbar {

            display:
                grid;

            grid-template-columns:
                1.5fr 1fr 1fr auto;

            gap:
                1rem;

            margin-bottom:
                2rem;

        }


        .input-group {

            position:
                relative;

        }


        .input-group i {

            position:
                absolute;

            left:
                1.3rem;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #999;

            font-size:
                1.3rem;

        }


        .input-control {

            width:
                100%;

            padding:
                1.2rem 1.3rem;

            border:
                .1rem solid #ddd;

            border-radius:
                1rem;

            color:
                var(--main-color);

            background:
                #fff;

            font-size:
                1.2rem;

        }


        .input-group .input-control {

            padding-left:
                3.8rem;

        }


        .input-control:focus {

            border-color:
                var(--main-color);

            box-shadow:
                0 0 0 .3rem rgba(68, 68, 51, .06);

        }


        .toolbar .btn {

            white-space:
                nowrap;

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-wrapper {

            width:
                100%;

            overflow-x:
                auto;

        }


        table {

            width:
                100%;

            min-width:
                100rem;

            border-collapse:
                collapse;

        }


        thead tr {

            border-bottom:
                .2rem solid var(--main-color);

        }


        th {

            text-align:
                left;

            padding:
                1.5rem 1rem;

            font-size:
                1.2rem;

            color:
                var(--main-color);

            white-space:
                nowrap;

        }


        td {

            padding:
                1.5rem 1rem;

            border-bottom:
                .1rem solid #eee;

            font-size:
                1.2rem;

            vertical-align:
                middle;

        }


        tbody tr {

            transition:
                transform .2s ease,
                background .2s ease;

            animation:
                rowAppear .4s ease both;

        }


        tbody tr:hover {

            background:
                #faf8f1;

            transform:
                translateX(.3rem);

        }


        .product-cell {

            display:
                flex;

            align-items:
                center;

            gap:
                1rem;

            min-width:
                22rem;

        }


        .product-icon {

            width:
                4.5rem;

            height:
                4.5rem;

            flex:
                0 0 4.5rem;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #f4f1e8;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            font-size:
                1.7rem;

            overflow:
                hidden;

        }


        .product-icon img {

            width:
                100%;

            height:
                100%;

            object-fit:
                cover;

            border-radius:
                inherit;

        }


        .product-cell:hover .product-icon {

            border:
                .15rem solid var(--main-color);

            transform:
                rotate(-4deg) scale(1.05);

        }


        .product-info {

            min-width:
                0;

        }


        .product-info strong {

            display:
                block;

            font-size:
                1.3rem;

            white-space:
                nowrap;

            overflow:
                hidden;

            text-overflow:
                ellipsis;

            max-width:
                25rem;

        }


        .product-info span {

            display:
                block;

            font-size:
                1rem;

            color:
                #999;

            margin-top:
                .2rem;

        }


        .sku {

            font-size:
                1.1rem;

            font-weight:
                600;

            color:
                var(--main-color);

        }


        .warehouse {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                .5rem;

            padding:
                .5rem .8rem;

            background:
                #f5f3ed;

            border:
                .1rem solid #ddd;

            border-radius:
                1rem;

            font-size:
                1rem;

        }


        /* =====================================================
           STOCK
        ===================================================== */

        .stock-info {

            min-width:
                12rem;

        }


        .stock-number {

            display:
                flex;

            align-items:
                baseline;

            gap:
                .4rem;

            margin-bottom:
                .7rem;

        }


        .stock-number strong {

            font-size:
                1.5rem;

        }


        .stock-number span {

            font-size:
                1rem;

            color:
                #999;

        }


        .stock-bar {

            width:
                100%;

            height:
                .5rem;

            background:
                #eee;

            border-radius:
                1rem;

            overflow:
                hidden;

        }


        .stock-progress {

            height:
                100%;

            border-radius:
                1rem;

            background:
                var(--green);

        }


        .stock-progress.low {

            background:
                var(--orange);

        }


        .stock-progress.empty {

            background:
                var(--red);

        }


        .minimum {

            display:
                block;

            font-size:
                .9rem;

            color:
                #999;

            margin-top:
                .4rem;

        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                .5rem;

            padding:
                .5rem 1rem;

            border:
                .1rem solid currentColor;

            border-radius:
                var(--border-radius);

            font-size:
                1rem;

            white-space:
                nowrap;

        }


        .status.aman {

            color:
                var(--green);

            background:
                #f0f7f0;

        }


        .status.menipis {

            color:
                var(--orange);

            background:
                #fff7e9;

        }


        .status.habis {

            color:
                var(--red);

            background:
                #fff0ef;

        }


        /* =====================================================
           VALUE
        ===================================================== */

        .stock-value strong {

            font-size:
                1.3rem;

        }


        .stock-value span {

            display:
                block;

            font-size:
                1rem;

            color:
                #999;

            margin-top:
                .2rem;

        }


        /* =====================================================
           LOGOUT
        ===================================================== */

        .sidebar .logout-menu {

            color:
                var(--red);

        }


        .sidebar .logout-menu i {

            color:
                var(--red);

        }


        .sidebar .logout-menu:hover {

            background:
                #fff0ef;

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
           ACTION
        ===================================================== */

        .action-btn {

            width:
                3.5rem;

            height:
                3.5rem;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                .1rem solid #ddd;

            border-radius:
                40% 60% 55% 45%;

            background:
                #fff;

            color:
                var(--main-color);

            cursor:
                pointer;

            margin-right:
                .3rem;

        }


        .action-btn:hover {

            border:
                var(--border);

            transform:
                translateY(-.2rem) rotate(-3deg);

            background:
                #f7f4ec;

        }


        /* =====================================================
           PAGINATION
        ===================================================== */

        .table-footer {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                1rem;

            margin-top:
                2rem;

        }


        .result-info {

            font-size:
                1.1rem;

            color:
                #888;

        }


        .pagination {

            display:
                flex;

            gap:
                .5rem;

        }


        .page-btn {

            min-width:
                3.5rem;

            height:
                3.5rem;

            padding:
                0 .8rem;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                .1rem solid #ddd;

            background:
                #fff;

            color:
                var(--main-color);

            border-radius:
                35% 65% 55% 45%;

            cursor:
                pointer;

            font-size:
                1.1rem;

        }


        .page-btn:hover,
        .page-btn.active {

            background:
                var(--main-color);

            color:
                #fff;

            border-color:
                var(--main-color);

        }


        .page-btn:disabled {

            opacity:
                .4;

            cursor:
                not-allowed;

        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            text-align:
                center;

            padding:
                4rem 1rem;

            color:
                #999;

            font-size:
                1.2rem;

        }


        .empty i {

            display:
                block;

            font-size:
                3.5rem;

            margin-bottom:
                1rem;

        }


        /* =====================================================
           MODAL
        ===================================================== */

        .modal {

            position:
                fixed;

            inset:
                0;

            background:
                rgba(68, 68, 51, .45);

            backdrop-filter:
                blur(.4rem);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                2rem;

            z-index:
                2000;

            opacity:
                0;

            visibility:
                hidden;

            transition:
                opacity .25s ease,
                visibility .25s ease;

        }


        .modal.active {

            opacity:
                1;

            visibility:
                visible;

        }


        .modal-box {

            width:
                min(55rem, 100%);

            max-height:
                90vh;

            overflow-y:
                auto;

            background:
                #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

            transform:
                translateY(2rem) scale(.96);

            transition:
                transform .3s ease;

        }


        .modal.active .modal-box {

            transform:
                translateY(0) scale(1);

        }


        .modal-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            margin-bottom:
                2rem;

        }


        .modal-header h2 {

            font-size:
                2rem;

        }


        .close-modal {

            width:
                3.5rem;

            height:
                3.5rem;

            border:
                .1rem solid #ddd;

            border-radius:
                50%;

            background:
                #fff;

            color:
                var(--main-color);

            cursor:
                pointer;

        }


        .close-modal:hover {

            transform:
                rotate(90deg);

            background:
                #f5f2e9;

        }


        .form-grid {

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                1.5rem;

        }


        .form-group {

            display:
                flex;

            flex-direction:
                column;

            gap:
                .7rem;

        }


        .form-group.full {

            grid-column:
                1 / -1;

        }


        .form-group label {

            font-size:
                1.2rem;

            font-weight:
                500;

        }


        .form-group label span {

            color:
                var(--red);

        }


        .form-control {

            width:
                100%;

            padding:
                1.2rem 1.3rem;

            border:
                .1rem solid #ddd;

            border-radius:
                1rem;

            background:
                #fff;

            color:
                var(--main-color);

            font-size:
                1.2rem;

        }


        .form-control:focus {

            border-color:
                var(--main-color);

            box-shadow:
                0 0 0 .3rem rgba(68, 68, 51, .06);

        }


        textarea.form-control {

            min-height:
                9rem;

            resize:
                vertical;

        }


        .form-note {

            padding:
                1rem 1.2rem;

            background:
                #f8f6ef;

            border:
                .1rem dashed #cfc8b6;

            border-radius:
                1rem;

            font-size:
                1rem;

            color:
                #777;

            margin-top:
                1.5rem;

        }


        .modal-footer {

            display:
                flex;

            justify-content:
                flex-end;

            gap:
                1rem;

            margin-top:
                2rem;

        }


        /* =====================================================
           ANIMATION
        ===================================================== */

        @keyframes fadeUp {

            from {

                opacity:
                    0;

                transform:
                    translateY(1.5rem);

            }

            to {

                opacity:
                    1;

                transform:
                    translateY(0);

            }

        }


        @keyframes fadeDown {

            from {

                opacity:
                    0;

                transform:
                    translateY(-1rem);

            }

            to {

                opacity:
                    1;

                transform:
                    translateY(0);

            }

        }


        @keyframes cardAppear {

            from {

                opacity:
                    0;

                transform:
                    translateY(1.5rem);

            }

            to {

                opacity:
                    1;

                transform:
                    translateY(0);

            }

        }


        @keyframes rowAppear {

            from {

                opacity:
                    0;

                transform:
                    translateY(.7rem);

            }

            to {

                opacity:
                    1;

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


            .toolbar {

                grid-template-columns:
                    1fr 1fr;

            }

        }


        /* =====================================================
           RESPONSIVE 900
        ===================================================== */

        @media (max-width: 900px) {

            .sidebar {

                left:
                    -27rem;

                box-shadow:
                    .5rem 0 2rem rgba(0, 0, 0, .08);

            }


            .sidebar.active {

                left:
                    0;

            }


            .main {

                margin-left:
                    0;

            }


            #menu-btn {

                display:
                    block;

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

                display:
                    none;

            }


            .page-actions {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .action-group {

                width:
                    100%;

            }


            .action-group .btn {

                flex:
                    1;

            }

        }


        /* =====================================================
           RESPONSIVE 600
        ===================================================== */

        @media (max-width: 600px) {

            html {

                font-size:
                    50%;

            }


            .stats {

                grid-template-columns:
                    1fr;

            }


            .toolbar {

                grid-template-columns:
                    1fr;

            }


            .panel {

                padding:
                    1.5rem;

            }


            .content {

                padding:
                    1.5rem;

            }


            .table-footer {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .pagination {

                width:
                    100%;

                justify-content:
                    center;

            }


            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .form-group.full {

                grid-column:
                    auto;

            }


            .modal {

                padding:
                    1rem;

            }


            .modal-box {

                padding:
                    1.7rem;

            }


            .modal-footer {

                flex-direction:
                    column;

            }


            .modal-footer .btn {

                width:
                    100%;

            }


            .page-title h2 {

                font-size:
                    2rem;

            }


            .page-title p {

                display:
                    none;

            }

        }


        /* =====================================================
           REDUCED MOTION
        ===================================================== */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                animation-duration:
                    .01ms !important;

                animation-iteration-count:
                    1 !important;

                transition-duration:
                    .01ms !important;

                scroll-behavior:
                    auto !important;

            }

        }


        /* =====================================================
           PRINT
        ===================================================== */

        @media print {

            .sidebar,
            .topbar,
            .page-actions,
            .toolbar,
            .action-column,
            .pagination,
            .table-footer {

                display:
                    none !important;

            }


            .main {

                margin-left:
                    0;

            }


            .content {

                padding:
                    0;

            }


            .panel {

                border:
                    .1rem solid #333;

                border-radius:
                    0;

            }

        }
    </style>

</head>


<body>


    <!-- =========================================================
         SIDEBAR
    ========================================================= -->

    <aside
        class="sidebar"
        id="sidebar">


        <!-- LOGO -->

        <a
            href="dashboard.php"
            class="logo">

            <i class="fas fa-mug-hot"></i>

            TOKU COFFEE

        </a>


        <!-- MENU UTAMA -->

        <div class="menu-title">

            Menu Utama

        </div>


        <a href="dashboard.php">

            <i class="fas fa-chart-pie"></i>

            Dashboard

        </a>


        <a href="orders.php">

            <i class="fas fa-shopping-bag"></i>

            Pesanan

        </a>


        <a href="produk/products.php">

            <i class="fas fa-box"></i>

            Produk

        </a>


        <a href="customers.php">

            <i class="fas fa-users"></i>

            Pelanggan

        </a>


        <!-- MANAJEMEN -->

        <div
            class="menu-title"
            style="margin-top:2rem;">

            Manajemen

        </div>


        <a
            href="inventory.php"
            class="active">

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


        <!-- SISTEM -->

        <div
            class="menu-title"
            style="margin-top:2rem;">

            Sistem

        </div>


        <a href="settings.php">

            <i class="fas fa-gear"></i>

            Pengaturan

        </a>


        <!-- AKUN -->

        <div
            class="menu-title"
            style="margin-top:2rem;">

            Akun

        </div>


        <a
            href="login/logout.php"
            class="logout-menu"
            onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">

            <i class="fas fa-right-from-bracket"></i>

            Logout

        </a>


    </aside>


    <!-- =========================================================
         MAIN
    ========================================================= -->

    <main class="main">


        <!-- =====================================================
             TOPBAR
        ===================================================== -->

        <header class="topbar">


            <div class="topbar-left">


                <i
                    class="fas fa-bars"
                    id="menu-btn">
                </i>


                <div class="page-title">

                    <h2>
                        Inventory
                    </h2>

                    <p>
                        Kelola stok produk dan gudang Toku Coffee ERP
                    </p>

                </div>


            </div>


            <div class="topbar-right">


                <div class="profile">


                    <img
                        src="https://ui-avatars.com/api/?name=<?= e($avatarName) ?>&background=443&color=fff"
                        alt="Profile">


                    <div class="profile-info">

                        <h4>
                            <?= e($namaUser) ?>
                        </h4>

                        <p>
                            <?= e(ucfirst($roleUser)) ?>
                        </p>

                    </div>


                </div>


            </div>


        </header>


        <!-- =====================================================
             CONTENT
        ===================================================== -->

        <section class="content">


            <!-- ALERT SUCCESS -->

            <?php if ($successMessage): ?>

                <div class="alert success">

                    <i class="fas fa-circle-check"></i>

                    <span>

                        <?= e($successMessage) ?>

                    </span>

                </div>

            <?php endif; ?>


            <!-- ALERT ERROR -->

            <?php if ($errorMessage): ?>

                <div class="alert error">

                    <i class="fas fa-circle-exclamation"></i>

                    <span>

                        <?= e($errorMessage) ?>

                    </span>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 PAGE ACTION
            ================================================= -->

            <div class="page-actions">


                <div class="page-description">

                    <strong>
                        <?= number_format($totalInventoryRows) ?>
                    </strong>

                    data inventory terdaftar

                </div>


                <div class="action-group">


                    <button
                        type="button"
                        class="btn"
                        onclick="openMutationModal()">

                        <i class="fas fa-arrows-rotate"></i>

                        Mutasi Stok

                    </button>


                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="openAddStockModal()">

                        <i class="fas fa-plus"></i>

                        Tambah Stok

                    </button>


                    <button
                        type="button"
                        class="btn"
                        onclick="window.print()">

                        <i class="fas fa-print"></i>

                        Cetak

                    </button>


                </div>


            </div>


            <!-- =================================================
                 STATISTICS
            ================================================= -->

            <div class="stats">


                <!-- TOTAL STOK -->

                <div class="stat">


                    <div class="stat-icon">

                        <i class="fas fa-cubes"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($totalStok) ?>
                        </h3>

                        <p>
                            Total Stok
                        </p>

                        <small>

                            <i class="fas fa-database"></i>

                            Seluruh gudang

                        </small>

                    </div>


                </div>


                <!-- JENIS PRODUK -->

                <div class="stat">


                    <div class="stat-icon">

                        <i class="fas fa-boxes-stacked"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($jenisProduk) ?>
                        </h3>

                        <p>
                            Jenis Produk
                        </p>

                        <small>

                            <i class="fas fa-box"></i>

                            Terdaftar inventory

                        </small>

                    </div>


                </div>


                <!-- STOK MENIPIS -->

                <div class="stat warning">


                    <div class="stat-icon">

                        <i class="fas fa-triangle-exclamation"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($stokMenipis) ?>
                        </h3>

                        <p>
                            Stok Menipis
                        </p>

                        <small>

                            <i class="fas fa-arrow-down"></i>

                            Perlu restock

                        </small>

                    </div>


                </div>


                <!-- PRODUK HABIS -->

                <div class="stat danger">


                    <div class="stat-icon">

                        <i class="fas fa-circle-xmark"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($produkHabis) ?>
                        </h3>

                        <p>
                            Produk Habis
                        </p>

                        <small>

                            <i class="fas fa-ban"></i>

                            Segera pengadaan

                        </small>

                    </div>


                </div>


            </div>


            <!-- =================================================
                 INVENTORY PANEL
            ================================================= -->

            <section class="panel">


                <div class="panel-header">


                    <div>

                        <h2>
                            Daftar Inventory
                        </h2>

                        <p>
                            Data stok produk berdasarkan gudang
                        </p>

                    </div>


                    <div>

                        <span
                            class="status aman">

                            <i class="fas fa-circle"></i>

                            Data Terhubung

                        </span>

                    </div>


                </div>


                <!-- TOOLBAR -->

                <div class="toolbar">


                    <!-- SEARCH -->

                    <div class="input-group">

                        <i class="fas fa-search"></i>

                        <input
                            type="text"
                            id="searchInput"
                            class="input-control"
                            placeholder="Cari produk atau SKU...">

                    </div>


                    <!-- GUDANG -->

                    <select
                        id="warehouseFilter"
                        class="input-control">

                        <option value="">

                            Semua Gudang

                        </option>


                        <?php foreach ($gudangList as $gudang): ?>

                            <option
                                value="<?= e($gudang['nama_gudang']) ?>">

                                <?= e($gudang['nama_gudang']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <!-- STATUS -->

                    <select
                        id="statusFilter"
                        class="input-control">

                        <option value="">

                            Semua Status

                        </option>

                        <option value="Aman">

                            Aman

                        </option>

                        <option value="Menipis">

                            Menipis

                        </option>

                        <option value="Habis">

                            Habis

                        </option>

                    </select>


                    <!-- RESET -->

                    <button
                        type="button"
                        class="btn"
                        onclick="resetFilter()">

                        <i class="fas fa-rotate-left"></i>

                        Reset

                    </button>


                </div>


                <!-- =================================================
                     TABLE
                ================================================= -->

                <div class="table-wrapper">


                    <table id="inventoryTable">


                        <thead>

                            <tr>

                                <th>
                                    Produk
                                </th>

                                <th>
                                    SKU
                                </th>

                                <th>
                                    Gudang
                                </th>

                                <th>
                                    Stok
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Nilai Stok
                                </th>

                                <th>
                                    Update
                                </th>

                                <th class="action-column">
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (!empty($inventoryData)): ?>


                                <?php foreach ($inventoryData as $item): ?>


                                    <tr
                                        data-product="<?= e(strtolower($item['nama_produk'])) ?>"
                                        data-sku="<?= e(strtolower($item['sku'])) ?>"
                                        data-warehouse="<?= e($item['gudang']) ?>"
                                        data-status="<?= e($item['status']) ?>">


                                        <!-- PRODUK -->

                                        <td>


                                            <div class="product-cell">


                                                <div class="product-icon">


                                                    <?php if (!empty($item['gambar'])): ?>


                                                        <!--
                                                        DATABASE:
                                                        upload/nama_file.jpg

                                                        FILE SEBENARNYA:
                                                        products/upload/nama_file.jpg

                                                        KARENA inventory.php
                                                        BERADA DI ROOT.
                                                        -->

                                                        <img
                                                            src="products/<?= e($item['gambar']) ?>"
                                                            alt="<?= e($item['nama_produk']) ?>"
                                                            style="
                                                                width:100%;
                                                                height:100%;
                                                                object-fit:cover;
                                                                border-radius:inherit;
                                                            "
                                                            onerror="this.style.display='none'; this.parentElement.querySelector('.fallback-icon').style.display='flex';">


                                                        <i
                                                            class="fas fa-mug-hot fallback-icon"
                                                            style="
                                                                display:none;
                                                                width:100%;
                                                                height:100%;
                                                                align-items:center;
                                                                justify-content:center;
                                                            ">
                                                        </i>


                                                    <?php else: ?>


                                                        <i class="fas fa-mug-hot"></i>


                                                    <?php endif; ?>


                                                </div>


                                                <div class="product-info">


                                                    <strong>

                                                        <?= e($item['nama_produk']) ?>

                                                    </strong>


                                                    <span>

                                                        <?= e($item['kategori']) ?>

                                                    </span>


                                                </div>


                                            </div>


                                        </td>


                                        <!-- SKU -->

                                        <td>

                                            <span class="sku">

                                                <?= e($item['sku']) ?>

                                            </span>

                                        </td>


                                        <!-- GUDANG -->

                                        <td>

                                            <span class="warehouse">

                                                <i class="fas fa-warehouse"></i>

                                                <?= e($item['gudang']) ?>

                                            </span>

                                        </td>


                                        <!-- STOK -->

                                        <td>


                                            <div class="stock-info">


                                                <div class="stock-number">

                                                    <strong>

                                                        <?= number_format($item['stok']) ?>

                                                    </strong>

                                                    <span>

                                                        <?= e($item['satuan']) ?>

                                                    </span>

                                                </div>


                                                <div class="stock-bar">

                                                    <div
                                                        class="stock-progress
                                                        <?= $item['status'] === 'Menipis'
                                                            ? 'low'
                                                            : ($item['status'] === 'Habis'
                                                                ? 'empty'
                                                                : '') ?>"
                                                        style="
                                                            width:<?= $item['progress'] ?>%;">
                                                    </div>

                                                </div>


                                                <span class="minimum">

                                                    Minimum:

                                                    <?= number_format($item['minimum']) ?>

                                                </span>


                                            </div>


                                        </td>


                                        <!-- STATUS -->

                                        <td>


                                            <span
                                                class="status <?= statusClass($item['status']) ?>">


                                                <?php if ($item['status'] === 'Aman'): ?>

                                                    <i class="fas fa-circle-check"></i>

                                                <?php elseif ($item['status'] === 'Menipis'): ?>

                                                    <i class="fas fa-triangle-exclamation"></i>

                                                <?php else: ?>

                                                    <i class="fas fa-circle-xmark"></i>

                                                <?php endif; ?>


                                                <?= e($item['status']) ?>


                                            </span>


                                        </td>


                                        <!-- NILAI STOK -->

                                        <td>


                                            <div class="stock-value">


                                                <strong>

                                                    <?= rupiah($item['nilai_stok']) ?>

                                                </strong>


                                                <span>

                                                    <?= rupiah($item['harga']) ?>

                                                    /

                                                    <?= e($item['satuan']) ?>

                                                </span>


                                            </div>


                                        </td>


                                        <!-- UPDATE -->

                                        <td>


                                            <?php

                                            $updated =
                                                !empty($item['updated_at'])
                                                ? date(
                                                    'd M Y H:i',
                                                    strtotime($item['updated_at'])
                                                )
                                                : '-';

                                            ?>


                                            <span>

                                                <?= e($updated) ?>

                                            </span>


                                        </td>


                                        <!-- AKSI -->

                                        <td class="action-column">


                                            <button
                                                type="button"
                                                class="action-btn"
                                                title="Mutasi Stok"
                                                onclick='openRowMutation(
                                                    <?= (int)$item["produk_id"] ?>,
                                                    <?= (int)$item["gudang_id"] ?>,
                                                    <?= json_encode($item["nama_produk"]) ?>
                                                )'>


                                                <i class="fas fa-arrows-rotate"></i>


                                            </button>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td
                                        colspan="8"
                                        class="empty">

                                        <i class="fas fa-box-open"></i>

                                        Belum ada data inventory.

                                    </td>

                                </tr>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


                <!-- =================================================
                     FOOTER
                ================================================= -->

                <div class="table-footer">


                    <div
                        class="result-info"
                        id="resultInfo">

                        Menampilkan data inventory

                    </div>


                    <div
                        class="pagination"
                        id="pagination">
                    </div>


                </div>


            </section>


        </section>


    </main>


    <!-- =========================================================
         MODAL MUTASI STOK
    ========================================================= -->

    <div
        class="modal"
        id="mutationModal">


        <div class="modal-box">


            <div class="modal-header">


                <h2>

                    <i class="fas fa-arrows-rotate"></i>

                    Mutasi Stok

                </h2>


                <button
                    type="button"
                    class="close-modal"
                    onclick="closeMutationModal()">

                    <i class="fas fa-xmark"></i>

                </button>


            </div>


            <form
                method="POST"
                action="inventory.php"
                id="mutationForm">


                <input
                    type="hidden"
                    name="action"
                    value="mutasi">


                <div class="form-grid">


                    <!-- PRODUK -->

                    <div class="form-group full">

                        <label>

                            Produk

                            <span>*</span>

                        </label>


                        <select
                            name="produk_id"
                            id="produk_id"
                            class="form-control"
                            required>

                            <option value="">

                                Pilih produk

                            </option>


                            <?php foreach ($produkList as $produk): ?>

                                <option
                                    value="<?= (int)$produk['id'] ?>">

                                    <?= e($produk['sku']) ?>

                                    -

                                    <?= e($produk['nama_produk']) ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>


                    <!-- GUDANG -->

                    <div class="form-group">

                        <label>

                            Gudang

                            <span>*</span>

                        </label>


                        <select
                            name="gudang_id"
                            id="gudang_id"
                            class="form-control"
                            required>

                            <option value="">

                                Pilih gudang

                            </option>


                            <?php foreach ($gudangList as $gudang): ?>

                                <option
                                    value="<?= (int)$gudang['id'] ?>">

                                    <?= e($gudang['nama_gudang']) ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>


                    <!-- TIPE -->

                    <div class="form-group">

                        <label>

                            Tipe Mutasi

                            <span>*</span>

                        </label>


                        <select
                            name="tipe"
                            id="tipe"
                            class="form-control"
                            required>

                            <option value="Masuk">

                                Stok Masuk

                            </option>

                            <option value="Keluar">

                                Stok Keluar

                            </option>

                            <option value="Penyesuaian">

                                Penyesuaian

                            </option>


                        </select>

                    </div>


                    <!-- JUMLAH -->

                    <div class="form-group full">

                        <label id="jumlahLabel">

                            Jumlah Stok

                            <span>*</span>

                        </label>


                        <input
                            type="number"
                            name="jumlah"
                            id="jumlah"
                            class="form-control"
                            min="0"
                            value="1"
                            required>

                    </div>


                    <!-- KETERANGAN -->

                    <div class="form-group full">

                        <label>

                            Keterangan

                        </label>


                        <textarea
                            name="keterangan"
                            class="form-control"
                            placeholder="Contoh: Restock dari supplier..."></textarea>

                    </div>


                </div>


                <div class="form-note">

                    <i class="fas fa-circle-info"></i>

                    Untuk <strong>Penyesuaian</strong>,
                    angka yang dimasukkan dianggap sebagai
                    jumlah stok akhir yang diinginkan.

                </div>


                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn"
                        onclick="closeMutationModal()">

                        Batal

                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="fas fa-save"></i>

                        Simpan Mutasi

                    </button>


                </div>


            </form>


        </div>


    </div>


    <!-- =========================================================
         JAVASCRIPT
    ========================================================= -->

    <script>
        /* =========================================================
           MOBILE SIDEBAR
        ========================================================= */

        const menuBtn =
            document.getElementById('menu-btn');

        const sidebar =
            document.getElementById('sidebar');


        if (menuBtn && sidebar) {

            menuBtn.addEventListener(
                'click',
                function() {

                    sidebar.classList.toggle(
                        'active'
                    );

                    this.classList.toggle(
                        'fa-bars'
                    );

                    this.classList.toggle(
                        'fa-xmark'
                    );

                }
            );

        }


        /* =========================================================
           CLOSE SIDEBAR MENU
        ========================================================= */

        document
            .querySelectorAll('.sidebar a')
            .forEach(function(link) {

                link.addEventListener(
                    'click',
                    function() {

                        if (
                            window.innerWidth <= 900
                        ) {

                            sidebar.classList.remove(
                                'active'
                            );

                            menuBtn.classList.remove(
                                'fa-xmark'
                            );

                            menuBtn.classList.add(
                                'fa-bars'
                            );

                        }

                    }
                );

            });


        /* =========================================================
           CLOSE SIDEBAR OUTSIDE
        ========================================================= */

        document.addEventListener(
            'click',
            function(event) {

                if (
                    window.innerWidth <= 900 &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !menuBtn.contains(event.target)
                ) {

                    sidebar.classList.remove(
                        'active'
                    );

                    menuBtn.classList.remove(
                        'fa-xmark'
                    );

                    menuBtn.classList.add(
                        'fa-bars'
                    );

                }

            }
        );


        /* =========================================================
           FILTER + PAGINATION
        ========================================================= */

        const searchInput =
            document.getElementById(
                'searchInput'
            );

        const warehouseFilter =
            document.getElementById(
                'warehouseFilter'
            );

        const statusFilter =
            document.getElementById(
                'statusFilter'
            );

        const table =
            document.getElementById(
                'inventoryTable'
            );

        const pagination =
            document.getElementById(
                'pagination'
            );

        const resultInfo =
            document.getElementById(
                'resultInfo'
            );


        const allRows =
            Array.from(
                table.querySelectorAll(
                    'tbody tr[data-product]'
                )
            );


        let currentPage = 1;

        const perPage = 10;


        function getFilteredRows() {

            const keyword =
                searchInput.value
                .trim()
                .toLowerCase();

            const warehouse =
                warehouseFilter.value;

            const status =
                statusFilter.value;


            return allRows.filter(
                function(row) {

                    const product =
                        row.dataset.product || '';

                    const sku =
                        row.dataset.sku || '';

                    const rowWarehouse =
                        row.dataset.warehouse || '';

                    const rowStatus =
                        row.dataset.status || '';


                    const matchSearch =
                        keyword === '' ||
                        product.includes(keyword) ||
                        sku.includes(keyword);


                    const matchWarehouse =
                        warehouse === '' ||
                        rowWarehouse === warehouse;


                    const matchStatus =
                        status === '' ||
                        rowStatus === status;


                    return (
                        matchSearch &&
                        matchWarehouse &&
                        matchStatus
                    );

                }
            );

        }


        function renderTable() {

            const filteredRows =
                getFilteredRows();


            const total =
                filteredRows.length;


            const totalPages =
                Math.max(
                    1,
                    Math.ceil(
                        total / perPage
                    )
                );


            if (
                currentPage > totalPages
            ) {

                currentPage =
                    totalPages;

            }


            allRows.forEach(
                function(row) {

                    row.style.display =
                        'none';

                }
            );


            const start =
                (currentPage - 1) *
                perPage;


            const end =
                start + perPage;


            filteredRows
                .slice(start, end)
                .forEach(
                    function(row, index) {

                        row.style.display =
                            '';

                        row.style.animationDelay =
                            (index * 0.03) + 's';

                    }
                );


            if (total === 0) {

                resultInfo.textContent =
                    'Tidak ada data yang sesuai filter.';

            } else {

                resultInfo.textContent =
                    'Menampilkan ' +
                    (start + 1) +
                    '–' +
                    Math.min(
                        end,
                        total
                    ) +
                    ' dari ' +
                    total +
                    ' data';

            }


            renderPagination(
                totalPages
            );

        }


        function renderPagination(
            totalPages
        ) {

            pagination.innerHTML = '';


            if (
                allRows.length === 0
            ) {

                return;

            }


            const prev =
                document.createElement(
                    'button'
                );


            prev.className =
                'page-btn';


            prev.innerHTML =
                '<i class="fas fa-chevron-left"></i>';


            prev.disabled =
                currentPage === 1;


            prev.addEventListener(
                'click',
                function() {

                    if (
                        currentPage > 1
                    ) {

                        currentPage--;

                        renderTable();

                    }

                }
            );


            pagination.appendChild(
                prev
            );


            let startPage =
                Math.max(
                    1,
                    currentPage - 2
                );


            let endPage =
                Math.min(
                    totalPages,
                    startPage + 4
                );


            if (
                endPage - startPage < 4
            ) {

                startPage =
                    Math.max(
                        1,
                        endPage - 4
                    );

            }


            for (
                let page = startPage; page <= endPage; page++
            ) {

                const button =
                    document.createElement(
                        'button'
                    );


                button.className =
                    'page-btn' +
                    (
                        page === currentPage ?
                        ' active' :
                        ''
                    );


                button.textContent =
                    page;


                button.addEventListener(
                    'click',
                    function() {

                        currentPage =
                            page;

                        renderTable();

                    }
                );


                pagination.appendChild(
                    button
                );

            }


            const next =
                document.createElement(
                    'button'
                );


            next.className =
                'page-btn';


            next.innerHTML =
                '<i class="fas fa-chevron-right"></i>';


            next.disabled =
                currentPage === totalPages;


            next.addEventListener(
                'click',
                function() {

                    if (
                        currentPage <
                        totalPages
                    ) {

                        currentPage++;

                        renderTable();

                    }

                }
            );


            pagination.appendChild(
                next
            );

        }


        /* =========================================================
           SEARCH
        ========================================================= */

        searchInput.addEventListener(
            'input',
            function() {

                currentPage = 1;

                renderTable();

            }
        );


        /* =========================================================
           WAREHOUSE FILTER
        ========================================================= */

        warehouseFilter.addEventListener(
            'change',
            function() {

                currentPage = 1;

                renderTable();

            }
        );


        /* =========================================================
           STATUS FILTER
        ========================================================= */

        statusFilter.addEventListener(
            'change',
            function() {

                currentPage = 1;

                renderTable();

            }
        );


        /* =========================================================
           RESET FILTER
        ========================================================= */

        function resetFilter() {

            searchInput.value =
                '';

            warehouseFilter.value =
                '';

            statusFilter.value =
                '';

            currentPage =
                1;

            renderTable();

        }


        /* =========================================================
           MODAL
        ========================================================= */

        const mutationModal =
            document.getElementById(
                'mutationModal'
            );

        const mutationForm =
            document.getElementById(
                'mutationForm'
            );


        function openMutationModal() {

            mutationForm.reset();


            document.getElementById(
                    'tipe'
                ).value =
                'Masuk';


            document.getElementById(
                    'jumlah'
                ).value =
                1;


            mutationModal.classList.add(
                'active'
            );


            document.body.style.overflow =
                'hidden';


            updateJumlahLabel();

        }


        function openAddStockModal() {

            openMutationModal();


            document.getElementById(
                    'tipe'
                ).value =
                'Masuk';


            updateJumlahLabel();

        }


        function openRowMutation(
            produkId,
            gudangId,
            produkName
        ) {

            openMutationModal();


            document.getElementById(
                    'produk_id'
                ).value =
                produkId;


            document.getElementById(
                    'gudang_id'
                ).value =
                gudangId;


            document.getElementById(
                    'tipe'
                ).value =
                'Masuk';


            updateJumlahLabel();


            document.getElementById(
                'jumlah'
            ).focus();

        }


        function closeMutationModal() {

            mutationModal.classList.remove(
                'active'
            );


            document.body.style.overflow =
                '';

        }


        /* =========================================================
           CLOSE MODAL CLICK OUTSIDE
        ========================================================= */

        mutationModal.addEventListener(
            'click',
            function(event) {

                if (
                    event.target ===
                    mutationModal
                ) {

                    closeMutationModal();

                }

            }
        );


        /* =========================================================
           ESC CLOSE MODAL
        ========================================================= */

        document.addEventListener(
            'keydown',
            function(event) {

                if (
                    event.key ===
                    'Escape'
                ) {

                    closeMutationModal();

                }

            }
        );


        /* =========================================================
           LABEL JUMLAH
        ========================================================= */

        const tipeSelect =
            document.getElementById(
                'tipe'
            );

        const jumlahLabel =
            document.getElementById(
                'jumlahLabel'
            );


        function updateJumlahLabel() {

            if (
                tipeSelect.value ===
                'Penyesuaian'
            ) {

                jumlahLabel.innerHTML =
                    'Stok Akhir <span>*</span>';

            } else {

                jumlahLabel.innerHTML =
                    'Jumlah Stok <span>*</span>';

            }

        }


        tipeSelect.addEventListener(
            'change',
            updateJumlahLabel
        );


        updateJumlahLabel();


        /* =========================================================
           AUTO HIDE ALERT
        ========================================================= */

        setTimeout(
            function() {

                document
                    .querySelectorAll(
                        '.alert'
                    )
                    .forEach(
                        function(alert) {

                            alert.style.opacity =
                                '0';

                            alert.style.transform =
                                'translateY(-.5rem)';


                            setTimeout(
                                function() {

                                    alert.remove();

                                },
                                250
                            );

                        }
                    );

            },
            5000
        );


        /* =========================================================
           INITIAL RENDER
        ========================================================= */

        renderTable();
    </script>


</body>

</html>
