<?php

require_once "config/koneksi.php";
require_once "config/session.php";
require_once "config/notifikasi.php";

requireRole(['admin', 'staff']);

/* =========================================================
   FUNGSI BANTUAN
========================================================= */

function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function statusClass($status)
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function queryValue($conn, $sql, $default = 0)
{
    $result = $conn->query($sql);

    if ($result) {
        $row = $result->fetch_assoc();

        if ($row) {
            $value = array_values($row)[0];

            return $value ?? $default;
        }
    }

    return $default;
}


/* =========================================================
   STATISTIK DASHBOARD
========================================================= */

/* Total Produk Aktif */

$totalProduk = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM produk
    WHERE status = 'aktif'
    "
);


/* Total Customer */

$totalPelanggan = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
    "
);


/* Total Pesanan */

$totalPesanan = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM pesanan
    "
);


/* Total Penjualan */

$totalPenjualan = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0) AS total
    FROM pesanan
    WHERE status != 'Dibatalkan'
    "
);


/* Total Stok */

$totalStok = (int) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(stok), 0) AS total
    FROM inventory
    "
);


/* =========================================================
   STATUS PESANAN
========================================================= */

$pesananBaru = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM pesanan
    WHERE status = 'Menunggu'
    "
);

$pesananDiproses = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM pesanan
    WHERE status = 'Diproses'
    "
);

$pesananDikirim = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM pesanan
    WHERE status = 'Dikirim'
    "
);

$pesananSelesai = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM pesanan
    WHERE status = 'Selesai'
    "
);


/* =========================================================
   STOK
========================================================= */

$stokMenipis = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM inventory
    WHERE stok > 0
    AND stok <= stok_minimum
    "
);


$produkHabis = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM inventory
    WHERE stok <= 0
    "
);


/* =========================================================
   TOTAL TRANSAKSI HARI INI
========================================================= */

$pesananHariIni = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM pesanan
    WHERE DATE(tanggal_pesanan) = CURDATE()
    "
);


/* =========================================================
   PENJUALAN HARI INI
========================================================= */

$penjualanHariIni = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0) AS total
    FROM pesanan
    WHERE DATE(tanggal_pesanan) = CURDATE()
    AND status != 'Dibatalkan'
    "
);


/* =========================================================
   PENJUALAN BULAN INI
========================================================= */

$penjualanBulanIni = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0) AS total
    FROM pesanan
    WHERE MONTH(tanggal_pesanan) = MONTH(CURDATE())
    AND YEAR(tanggal_pesanan) = YEAR(CURDATE())
    AND status != 'Dibatalkan'
    "
);


/* =========================================================
   DATA PENJUALAN 6 BULAN TERAKHIR
========================================================= */

$sales = [];

$result = $conn->query("
    SELECT
        DATE_FORMAT(tanggal_pesanan, '%b') AS bulan,
        COALESCE(SUM(total), 0) AS total
    FROM pesanan
    WHERE status != 'Dibatalkan'
    AND tanggal_pesanan >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY YEAR(tanggal_pesanan), MONTH(tanggal_pesanan)
    ORDER BY YEAR(tanggal_pesanan), MONTH(tanggal_pesanan)
");

if ($result) {

    $salesData = [];

    while ($row = $result->fetch_assoc()) {

        $salesData[] = [
            'month' => $row['bulan'],
            'total' => (float)$row['total']
        ];
    }

    $maxSales = 0;

    foreach ($salesData as $item) {

        if ($item['total'] > $maxSales) {
            $maxSales = $item['total'];
        }
    }

    foreach ($salesData as $item) {

        $percentage = 0;

        if ($maxSales > 0) {

            $percentage =
                ($item['total'] / $maxSales) * 100;
        }

        $sales[] = [

            'month' => $item['month'],

            'value' => round($percentage),

            'total' => $item['total']

        ];
    }
}


/* Jika belum ada transaksi */

if (empty($sales)) {

    $sales = [

        [
            'month' => 'Jan',
            'value' => 0,
            'total' => 0
        ],

        [
            'month' => 'Feb',
            'value' => 0,
            'total' => 0
        ],

        [
            'month' => 'Mar',
            'value' => 0,
            'total' => 0
        ],

        [
            'month' => 'Apr',
            'value' => 0,
            'total' => 0
        ],

        [
            'month' => 'Mei',
            'value' => 0,
            'total' => 0
        ],

        [
            'month' => 'Jun',
            'value' => 0,
            'total' => 0
        ]

    ];
}


/* =========================================================
   PESANAN TERBARU
========================================================= */

$recentOrders = [];

$result = $conn->query("
    SELECT
        p.invoice,
        u.full_name AS customer,
        p.tanggal_pesanan,
        p.total,
        p.status
    FROM pesanan p
    LEFT JOIN users u
        ON p.user_id = u.id
    ORDER BY p.tanggal_pesanan DESC
    LIMIT 5
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $recentOrders[] = [

            'invoice' =>
            $row['invoice'],

            'customer' =>
            $row['customer']
                ?: 'Customer',

            'date' =>
            date(
                'd M Y H:i',
                strtotime(
                    $row['tanggal_pesanan']
                )
            ),

            'total' =>
            $row['total'],

            'status' =>
            $row['status']

        ];
    }
}


/* =========================================================
   PRODUK TERLARIS
========================================================= */

$topProducts = [];

$result = $conn->query("
    SELECT
        pr.nama_produk,
        COALESCE(SUM(dp.jumlah), 0) AS sold,
        COALESCE(MAX(i.stok), 0) AS stock
    FROM detail_pesanan dp

    INNER JOIN produk pr
        ON dp.produk_id = pr.id

    LEFT JOIN inventory i
        ON pr.id = i.produk_id

    INNER JOIN pesanan p
        ON dp.pesanan_id = p.id

    WHERE p.status != 'Dibatalkan'

    GROUP BY
        pr.id,
        pr.nama_produk

    ORDER BY sold DESC

    LIMIT 5
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $topProducts[] = [

            'name' =>
            $row['nama_produk'],

            'sold' =>
            (int)$row['sold'],

            'stock' =>
            (int)$row['stock']

        ];
    }
}


/* =========================================================
   INFORMASI USER
========================================================= */

$namaUser =
    $_SESSION['full_name']
    ??
    $_SESSION['username']
    ??
    'Admin ERP';

$roleUser =
    $_SESSION['role']
    ??
    'Administrator';

/* =========================================================
   NOTIFIKASI
========================================================= */

$currentUserId = getCurrentUserId($conn);

$jumlahNotifikasi =
    getUnreadNotificationCount(
        $conn,
        $currentUserId
    );

/* =========================================================
   AVATAR
========================================================= */

$avatarName = urlencode($namaUser);

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Dashboard - Toku Coffee ERP
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

            transition:
                transform .25s ease;

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

            transition:
                transform .25s ease;

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

            transition:
                margin-left .35s ease;

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


        .notification i {

            transition:
                transform .25s ease;

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

            cursor: pointer;

        }


        .profile img {

            width: 4rem;

            height: 4rem;

            border-radius: 50%;

            border:
                .2rem solid var(--main-color);

            transition:
                transform .3s ease;

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

            position: relative;

            overflow: hidden;

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


        .btn i {

            transition:
                transform .25s ease;

        }


        .btn:hover i {

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

            cursor: default;

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

            transition:
                transform .3s ease;

        }


        .stat:hover .stat-icon {

            transform:
                rotate(-5deg) scale(1.08);

        }


        .stat h3 {

            font-size: 2.1rem;

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
           DASHBOARD GRID
        ===================================================== */

        .dashboard-grid {

            display: grid;

            grid-template-columns:
                2fr 1fr;

            gap: 2rem;

            margin-bottom: 2rem;

        }


        /* =====================================================
           CHART
        ===================================================== */

        .chart {

            height: 28rem;

            display: flex;

            align-items: flex-end;

            gap: 1rem;

            padding:
                2rem .5rem 0;

            border-bottom:
                .1rem solid #ddd;

        }


        .chart-column {

            flex: 1;

            height: 100%;

            display: flex;

            flex-direction: column;

            justify-content: flex-end;

            align-items: center;

            gap: .7rem;

        }


        .chart-bar {

            width: 65%;

            max-width: 4rem;

            min-height: .4rem;

            background:
                var(--main-color);

            border-radius:
                1.5rem 1.5rem .4rem .4rem;

            transform-origin:
                bottom;

            animation:
                chartGrow .9s cubic-bezier(.22, 1, .36, 1) both;

        }


        .chart-column:nth-child(1) .chart-bar {
            animation-delay: .1s;
        }

        .chart-column:nth-child(2) .chart-bar {
            animation-delay: .18s;
        }

        .chart-column:nth-child(3) .chart-bar {
            animation-delay: .26s;
        }

        .chart-column:nth-child(4) .chart-bar {
            animation-delay: .34s;
        }

        .chart-column:nth-child(5) .chart-bar {
            animation-delay: .42s;
        }

        .chart-column:nth-child(6) .chart-bar {
            animation-delay: .50s;
        }


        .chart-bar:hover {

            background: #766;

            transform:
                scaleX(1.08) translateY(-.4rem);

        }


        .chart-value {

            font-size: 1rem;

            color: #777;

        }


        .chart-label {

            font-size: 1rem;

            color: #888;

        }


        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary-list {

            display: flex;

            flex-direction: column;

            gap: 1rem;

        }


        .summary-item {

            display: flex;

            align-items: center;

            gap: 1rem;

            padding: 1.2rem;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

            transition:
                all .25s ease;

        }


        .summary-item:hover {

            border:
                .15rem dashed var(--main-color);

            border-radius:
                var(--border-radius-hover);

            background:
                #faf8f1;

            transform:
                translateX(.4rem);

        }


        .summary-icon {

            width: 4rem;

            height: 4rem;

            flex:
                0 0 4rem;

            border-radius:
                45%;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                #f4f1e8;

            font-size: 1.5rem;

        }


        .summary-content {

            flex: 1;

        }


        .summary-content h4 {

            font-size: 1.3rem;

        }


        .summary-content span {

            display: block;

            color: #888;

            font-size: 1.1rem;

            margin-top: .2rem;

        }


        .summary-number {

            font-size: 1.5rem;

            font-weight: 600;

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-panel {

            margin-bottom: 2rem;

        }


        .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        table {

            width: 100%;

            min-width: 80rem;

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

            transition:
                transform .2s ease,
                background .2s ease;

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


        /* =====================================================
           LOWER GRID
        ===================================================== */

        .lower-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 2rem;

        }


        .product-item {

            display: flex;

            align-items: center;

            gap: 1.2rem;

            padding:
                1.2rem 0;

            border-bottom:
                .1rem solid #eee;

            transition:
                transform .25s ease;

        }


        .product-item:hover {

            transform:
                translateX(.4rem);

        }


        .product-item:last-child {

            border-bottom: none;

        }


        .product-image {

            width: 4.8rem;

            height: 4.8rem;

            flex:
                0 0 4.8rem;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                #f4f1e8;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            font-size: 1.8rem;

        }


        .product-item:hover .product-image {

            border:
                .15rem solid var(--main-color);

            transform:
                rotate(-4deg) scale(1.05);

        }


        .product-info {

            flex: 1;

            min-width: 0;

        }


        .product-info h4 {

            font-size: 1.3rem;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }


        .product-info span {

            font-size: 1.1rem;

            color: #888;

        }


        .product-sales {

            text-align: right;

        }


        .product-sales strong {

            display: block;

            font-size: 1.4rem;

        }


        .product-sales span {

            font-size: 1.1rem;

            color: #888;

        }


        /* =====================================================
           QUICK ACTION
        ===================================================== */

        .quick-actions {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 1rem;

        }


        .quick-action {

            padding: 1.5rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            text-align: center;

            font-size: 1.2rem;

            transition:
                all .25s ease;

        }


        .quick-action:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

            background:
                #f7f4ec;

            transform:
                translateY(-.4rem);

        }


        .quick-action i {

            display: block;

            font-size: 2.2rem;

            margin-bottom: .7rem;

            transition:
                transform .25s ease;

        }


        .quick-action:hover i {

            transform:
                scale(1.12) translateY(-.2rem);

        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            text-align: center;

            padding: 3rem 1rem;

            color: #999;

            font-size: 1.2rem;

        }


        .empty i {

            font-size: 3rem;

            margin-bottom: 1rem;

            display: block;

        }

        /* =====================================================
   NOTIFICATION BUTTON
===================================================== */

        .notification {
            width: 4.5rem;
            height: 4.5rem;

            display: flex;
            align-items: center;
            justify-content: center;

            position: relative;

            background: #fff;

            border: .15rem solid #e5e2da;

            border-radius: 50%;

            color: #443;

            text-decoration: none;

            transition: all .3s ease;
        }


        /* =====================================================
   ICON
===================================================== */

        .notification i {
            font-size: 1.8rem;

            color: #443;

            transition: all .3s ease;
        }


        /* =====================================================
   HOVER
===================================================== */

        .notification:hover {
            border-color: #443;

            background: #f3f0e8;

            transform: translateY(-.2rem);

            box-shadow:
                0 .5rem 1.5rem rgba(68, 68, 51, .10);
        }


        .notification:hover i {
            transform: rotate(-10deg) scale(1.05);
        }


        /* =====================================================
   NOTIFICATION BADGE
===================================================== */

        .notification span {
            position: absolute;

            top: -.6rem;

            right: -.5rem;

            min-width: 2rem;

            height: 2rem;

            padding: 0 .5rem;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #a94442;

            color: #fff;

            border: .15rem solid #fff;

            border-radius: 2rem;

            font-size: .85rem;

            font-weight: 600;

            line-height: 1;

            box-shadow:
                0 .2rem .7rem rgba(169, 68, 66, .20);

            z-index: 2;

            animation: notificationBadge .3s ease;
        }


        /* =====================================================
   BADGE ANIMATION
===================================================== */

        @keyframes notificationBadge {

            from {
                opacity: 0;
                transform: scale(.5);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }


        /* =====================================================
   MOBILE
===================================================== */

        @media (max-width: 600px) {

            .notification {
                width: 4rem;
                height: 4rem;
            }

            .notification i {
                font-size: 1.6rem;
            }

            .notification span {
                top: -.5rem;
                right: -.4rem;

                min-width: 1.8rem;
                height: 1.8rem;

                font-size: .75rem;
            }
        }


        /* =====================================================
   SMALL MOBILE
===================================================== */

        @media (max-width: 450px) {

            .notification {
                width: 3.8rem;
                height: 3.8rem;
            }

            .notification i {
                font-size: 1.5rem;
            }

            .notification span {
                min-width: 1.7rem;
                height: 1.7rem;

                padding: 0 .4rem;

                font-size: .7rem;
            }
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


        @keyframes chartGrow {

            from {

                transform:
                    scaleY(0);

                opacity: 0;

            }

            to {

                transform:
                    scaleY(1);

                opacity: 1;

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


            .dashboard-grid {

                grid-template-columns:
                    1fr;

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


            .lower-grid {

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


            .panel {

                padding:
                    1.5rem;

            }


            .content {

                padding:
                    1.5rem;

            }


            .quick-actions {

                grid-template-columns:
                    1fr;

            }


            .chart {

                height:
                    23rem;

                gap:
                    .5rem;

            }


            .chart-bar {

                width:
                    70%;

            }


            .page-actions {

                justify-content:
                    stretch;

            }


            .page-actions .btn {

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


        <a
            href="dashboar.php"
            class="active">

            <i class="fas fa-chart-pie"></i>

            Dashboard

        </a>


        <a href="orders.php">

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
                        Dashboard
                    </h2>

                    <p>
                        Ringkasan bisnis Toku Coffee ERP
                    </p>

                </div>


            </div>


            <div class="topbar-right">


                <a
                    href="notifications.php"
                    class="notification"
                    title="Notifikasi">

                    <i class="far fa-bell"></i>

                    <?php if ($jumlahNotifikasi > 0): ?>

                        <span>
                            <?= $jumlahNotifikasi > 99
                                ? '99+'
                                : number_format($jumlahNotifikasi) ?>
                        </span>

                    <?php endif; ?>

                </a>

                <div class="profile">


                    <img
                        src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff"
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


            <!-- =====================================================
     ACTION
===================================================== -->

            <div class="page-actions">

                <button
                    class="btn"
                    onclick="window.print()">

                    <i class="fas fa-print"></i>

                    Cetak Laporan

                </button>

            </div>


            <!-- =====================================================
     STATISTICS
===================================================== -->

            <div class="stats">


                <!-- PENJUALAN -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-money-bill-wave"></i>

                    </div>


                    <div>

                        <h3>
                            <?= rupiah($totalPenjualan) ?>
                        </h3>

                        <p>
                            Total Penjualan
                        </p>

                        <small>

                            <i class="fas fa-chart-line"></i>

                            Semua transaksi

                        </small>

                    </div>

                </div>


                <!-- PESANAN -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-shopping-bag"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($totalPesanan) ?>
                        </h3>

                        <p>
                            Total Pesanan
                        </p>

                        <small>

                            <i class="fas fa-cart-shopping"></i>

                            <?= number_format($pesananHariIni) ?>
                            pesanan hari ini

                        </small>

                    </div>

                </div>


                <!-- PRODUK -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-box-open"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($totalProduk) ?>
                        </h3>

                        <p>
                            Produk Aktif
                        </p>

                        <small>

                            <i class="fas fa-check"></i>

                            Katalog aktif

                        </small>

                    </div>

                </div>


                <!-- CUSTOMER -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-users"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($totalPelanggan) ?>
                        </h3>

                        <p>
                            Total Pelanggan
                        </p>

                        <small>

                            <i class="fas fa-user"></i>

                            Customer terdaftar

                        </small>

                    </div>

                </div>


            </div>


            <!-- =====================================================
     KPI E-COMMERCE / ERP
===================================================== -->

            <section class="dashboard-grid">


                <!-- PENJUALAN -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>
                            Penjualan Bulanan
                        </h2>

                        <a href="reports.php">

                            Lihat laporan

                        </a>

                    </div>


                    <div class="chart">


                        <?php foreach ($sales as $item): ?>


                            <div class="chart-column">


                                <span class="chart-value">

                                    <?= rupiah($item['total']) ?>

                                </span>


                                <div
                                    class="chart-bar"
                                    style="
                            height:
                            <?= max(2, $item['value']) ?>%;">
                                </div>


                                <span class="chart-label">

                                    <?= e($item['month']) ?>

                                </span>


                            </div>


                        <?php endforeach; ?>


                    </div>


                </div>


                <!-- OPERASIONAL ERP -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>
                            Operasional ERP
                        </h2>

                    </div>


                    <div class="summary-list">


                        <!-- PESANAN MENUNGGU -->

                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-clock"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Pesanan Menunggu
                                </h4>

                                <span>
                                    Perlu diproses
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= number_format($pesananBaru) ?>

                            </div>

                        </div>


                        <!-- DIPROSES -->

                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-gears"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Sedang Diproses
                                </h4>

                                <span>
                                    Order aktif
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= number_format($pesananDiproses) ?>

                            </div>

                        </div>


                        <!-- DIKIRIM -->

                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-truck-fast"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Sedang Dikirim
                                </h4>

                                <span>
                                    Dalam pengiriman
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= number_format($pesananDikirim) ?>

                            </div>

                        </div>


                        <!-- SELESAI -->

                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-circle-check"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Pesanan Selesai
                                </h4>

                                <span>
                                    Transaksi selesai
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= number_format($pesananSelesai) ?>

                            </div>

                        </div>


                        <!-- STOK MENIPIS -->

                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-triangle-exclamation"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Stok Menipis
                                </h4>

                                <span>
                                    Perlu restock
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= number_format($stokMenipis) ?>

                            </div>

                        </div>


                        <!-- PRODUK HABIS -->

                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-circle-xmark"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Produk Habis
                                </h4>

                                <span>
                                    Perlu pengadaan
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= number_format($produkHabis) ?>

                            </div>

                        </div>


                        <!-- TOTAL STOK -->

                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-warehouse"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Total Stok
                                </h4>

                                <span>
                                    Seluruh inventory
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= number_format($totalStok) ?>

                            </div>

                        </div>


                    </div>


                </div>


            </section>


            <!-- =====================================================
     INFORMASI E-COMMERCE
===================================================== -->

            <section class="dashboard-grid">


                <!-- PENJUALAN HARI INI -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>
                            Performa Hari Ini
                        </h2>

                    </div>


                    <div class="summary-list">


                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-cash-register"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Penjualan Hari Ini
                                </h4>

                                <span>
                                    Total transaksi
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= rupiah($penjualanHariIni) ?>

                            </div>

                        </div>


                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-cart-shopping"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Pesanan Hari Ini
                                </h4>

                                <span>
                                    Order masuk
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= number_format($pesananHariIni) ?>

                            </div>

                        </div>


                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-calendar-days"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Penjualan Bulan Ini
                                </h4>

                                <span>
                                    Performa berjalan
                                </span>

                            </div>


                            <div class="summary-number">

                                <?= rupiah($penjualanBulanIni) ?>

                            </div>

                        </div>


                    </div>


                </div>


                <!-- ALUR E-COMMERCE -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>
                            Alur E-Commerce
                        </h2>

                    </div>


                    <div class="summary-list">


                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-store"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Produk
                                </h4>

                                <span>
                                    Kelola katalog produk
                                </span>

                            </div>


                            <a
                                href="products.php"
                                class="panel-header a">

                                <i class="fas fa-arrow-right"></i>

                            </a>

                        </div>


                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-cart-shopping"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Pesanan
                                </h4>

                                <span>
                                    Kelola transaksi customer
                                </span>

                            </div>


                            <a
                                href="orders.php"
                                class="panel-header a">

                                <i class="fas fa-arrow-right"></i>

                            </a>

                        </div>


                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-boxes-stacked"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Inventory
                                </h4>

                                <span>
                                    Kontrol stok barang
                                </span>

                            </div>


                            <a
                                href="inventory.php"
                                class="panel-header a">

                                <i class="fas fa-arrow-right"></i>

                            </a>

                        </div>


                        <div class="summary-item">

                            <div class="summary-icon">

                                <i class="fas fa-chart-line"></i>

                            </div>


                            <div class="summary-content">

                                <h4>
                                    Laporan
                                </h4>

                                <span>
                                    Analisis bisnis ERP
                                </span>

                            </div>


                            <a
                                href="reports.php"
                                class="panel-header a">

                                <i class="fas fa-arrow-right"></i>

                            </a>

                        </div>


                    </div>


                </div>


            </section>


            <!-- =====================================================
     PESANAN TERBARU
===================================================== -->

            <section class="panel table-panel">


                <div class="panel-header">

                    <h2>
                        Pesanan Terbaru
                    </h2>

                    <a href="orders.php">

                        Lihat semua

                    </a>

                </div>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Invoice
                                </th>

                                <th>
                                    Pelanggan
                                </th>

                                <th>
                                    Tanggal
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (!empty($recentOrders)): ?>


                                <?php foreach ($recentOrders as $order): ?>


                                    <tr>


                                        <td>

                                            <span class="invoice">

                                                <?= e(
                                                    $order['invoice']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= e(
                                                $order['customer']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= e(
                                                $order['date']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= rupiah(
                                                $order['total']
                                            ) ?>

                                        </td>


                                        <td>

                                            <span
                                                class="status <?= e(
                                                                    statusClass(
                                                                        $order['status']
                                                                    )
                                                                ) ?>">

                                                <?= e(
                                                    $order['status']
                                                ) ?>

                                            </span>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td
                                        colspan="5"
                                        class="empty">

                                        <i
                                            class="fas fa-receipt">
                                        </i>

                                        Belum ada pesanan.

                                    </td>

                                </tr>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </section>


            <!-- =====================================================
     LOWER GRID
===================================================== -->

            <section class="lower-grid">


                <!-- PRODUK TERLARIS -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>
                            Produk Terlaris
                        </h2>

                        <a href="products.php">

                            Lihat semua

                        </a>

                    </div>


                    <?php if (!empty($topProducts)): ?>


                        <?php foreach ($topProducts as $product): ?>


                            <div class="product-item">


                                <div class="product-image">

                                    <i class="fas fa-mug-hot"></i>

                                </div>


                                <div class="product-info">

                                    <h4>

                                        <?= e(
                                            $product['name']
                                        ) ?>

                                    </h4>


                                    <span>

                                        Stok tersedia:

                                        <?= number_format(
                                            $product['stock']
                                        ) ?>

                                    </span>

                                </div>


                                <div class="product-sales">

                                    <strong>

                                        <?= number_format(
                                            $product['sold']
                                        ) ?>

                                    </strong>

                                    <span>
                                        terjual
                                    </span>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div class="empty">

                            <i
                                class="fas fa-box-open">
                            </i>

                            Belum ada data penjualan produk.

                        </div>


                    <?php endif; ?>


                </div>


                <!-- AKSI CEPAT ERP -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>
                            Aksi Cepat ERP
                        </h2>

                    </div>


                    <div class="quick-actions">


                        <a
                            href="orders.php"
                            class="quick-action">

                            <i class="fas fa-plus-circle"></i>

                            Kelola Pesanan

                        </a>


                        <a
                            href="products.php"
                            class="quick-action">

                            <i class="fas fa-box-open"></i>

                            Tambah Produk

                        </a>


                        <a
                            href="inventory.php"
                            class="quick-action">

                            <i class="fas fa-warehouse"></i>

                            Kelola Inventory

                        </a>


                        <a
                            href="customers.php"
                            class="quick-action">

                            <i class="fas fa-users"></i>

                            Pelanggan

                        </a>


                        <a
                            href="suppliers.php"
                            class="quick-action">

                            <i class="fas fa-truck"></i>

                            Supplier

                        </a>


                        <a
                            href="finance.php"
                            class="quick-action">

                            <i class="fas fa-wallet"></i>

                            Keuangan

                        </a>


                        <a
                            href="reports.php"
                            class="quick-action">

                            <i class="fas fa-chart-line"></i>

                            Laporan

                        </a>


                        <a
                            href="settings.php"
                            class="quick-action">

                            <i class="fas fa-gear"></i>

                            Pengaturan

                        </a>


                    </div>


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
            document.getElementById(
                'menu-btn'
            );

        const sidebar =
            document.getElementById(
                'sidebar'
            );


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


        /* =====================================================
           TUTUP SIDEBAR SAAT MENU DIKLIK
        ===================================================== */

        document
            .querySelectorAll('.sidebar a')
            .forEach(
                function(link) {

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

                }
            );


        /* =====================================================
           CLOSE SIDEBAR KLIK DI LUAR
        ===================================================== */

        document.addEventListener(
            'click',
            function(event) {

                if (
                    window.innerWidth <= 900 &&
                    sidebar.classList.contains(
                        'active'
                    ) &&
                    !sidebar.contains(
                        event.target
                    ) &&
                    !menuBtn.contains(
                        event.target
                    )
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


        /* =====================================================
           AUTO REFRESH DASHBOARD
        ===================================================== */

        let dashboardRefresh = null;


        /*
         * Refresh ringan setiap 60 detik.
         * Tidak melakukan refresh jika user sedang
         * berada pada tab browser yang tidak aktif.
         */

        function startDashboardRefresh() {

            if (dashboardRefresh) {
                clearInterval(
                    dashboardRefresh
                );
            }


            dashboardRefresh =
                setInterval(
                    function() {

                        if (
                            !document.hidden
                        ) {

                            window.location.reload();

                        }

                    },
                    60000
                );

        }


        startDashboardRefresh();
    </script>


</body>

</html>
