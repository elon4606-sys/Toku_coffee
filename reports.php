<?php

require_once "config/koneksi.php";
require_once "config/session.php";

requireRole(['admin', 'staff']);


/* =========================================================
   FUNGSI BANTUAN
========================================================= */

function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
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

$avatarName = urlencode($namaUser);


/* =========================================================
   TANGGAL
========================================================= */

$bulanSekarang = date('F Y');

$bulanIndonesia = [
    'January'   => 'Januari',
    'February'  => 'Februari',
    'March'     => 'Maret',
    'April'     => 'April',
    'May'       => 'Mei',
    'June'      => 'Juni',
    'July'      => 'Juli',
    'August'    => 'Agustus',
    'September' => 'September',
    'October'   => 'Oktober',
    'November'  => 'November',
    'December'  => 'Desember'
];

$bulanSekarang =
    $bulanIndonesia[date('F')]
    . ' '
    . date('Y');


/* =========================================================
   DATA LAPORAN
========================================================= */

/* Total Penjualan */

$totalPenjualan = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM pesanan
    WHERE status != 'Dibatalkan'
    "
);


/* Total Pesanan */

$totalPesanan = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM pesanan
    "
);


/* Produk Aktif */

$totalProduk = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM produk
    WHERE status = 'aktif'
    "
);


/* Total Pelanggan */

$totalPelanggan = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM users
    WHERE role = 'customer'
    "
);


/* Total Stok */

$totalStok = (int) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(stok), 0)
    FROM inventory
    "
);


/* Stok Menipis */

$stokMenipis = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM inventory
    WHERE stok > 0
    AND stok <= stok_minimum
    "
);


/* Produk Habis */

$produkHabis = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM inventory
    WHERE stok <= 0
    "
);


/* =========================================================
   PENJUALAN BULAN INI
========================================================= */

$penjualanBulanIni = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM pesanan
    WHERE MONTH(tanggal_pesanan) = MONTH(CURDATE())
    AND YEAR(tanggal_pesanan) = YEAR(CURDATE())
    AND status != 'Dibatalkan'
    "
);


/* =========================================================
   PESANAN BULAN INI
========================================================= */

$pesananBulanIni = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM pesanan
    WHERE MONTH(tanggal_pesanan) = MONTH(CURDATE())
    AND YEAR(tanggal_pesanan) = YEAR(CURDATE())
    "
);


/* =========================================================
   PENJUALAN HARI INI
========================================================= */

$penjualanHariIni = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM pesanan
    WHERE DATE(tanggal_pesanan) = CURDATE()
    AND status != 'Dibatalkan'
    "
);


/* =========================================================
   PESANAN HARI INI
========================================================= */

$pesananHariIni = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM pesanan
    WHERE DATE(tanggal_pesanan) = CURDATE()
    "
);


/* =========================================================
   RATA-RATA ORDER
========================================================= */

$rataOrder = 0;

if ($totalPesanan > 0) {

    $rataOrder =
        $totalPenjualan /
        $totalPesanan;
}


/* =========================================================
   DATA GRAFIK 6 BULAN
========================================================= */

$sales = [];

$result = $conn->query("
    SELECT
        DATE_FORMAT(tanggal_pesanan, '%b') AS bulan,
        COALESCE(SUM(total), 0) AS total
    FROM pesanan
    WHERE status != 'Dibatalkan'
    AND tanggal_pesanan >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY
        YEAR(tanggal_pesanan),
        MONTH(tanggal_pesanan)
    ORDER BY
        YEAR(tanggal_pesanan),
        MONTH(tanggal_pesanan)
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

            $maxSales =
                $item['total'];
        }
    }


    foreach ($salesData as $item) {

        $percentage = 0;

        if ($maxSales > 0) {

            $percentage =
                ($item['total'] / $maxSales) * 100;
        }


        $sales[] = [

            'month' =>
            $item['month'],

            'value' =>
            round($percentage),

            'total' =>
            $item['total']

        ];
    }
}


/* =========================================================
   JIKA DATA KOSONG
========================================================= */

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
   NOTIFIKASI
========================================================= */

$notifikasi =
    (int)$stokMenipis +
    (int)$produkHabis;


/* =========================================================
   TAHUN
========================================================= */

$tahunSekarang = date('Y');

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Laporan - Toku Coffee ERP
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
                left .35s ease;

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


        .logo i {

            margin-right: .5rem;

        }


        .logo:hover {

            transform:
                scale(1.03);

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


        #menu-btn {

            display: none;

            font-size: 2.5rem;

            cursor: pointer;

        }


        #menu-btn:hover {

            transform:
                rotate(5deg) scale(1.1);

        }


        .page-title h2 {

            font-size: 2.4rem;

            animation:
                fadeDown .5s ease;

        }


        .page-title p {

            color: #999;

            font-size: 1.3rem;

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

            background:
                var(--red);

            color: #fff;

            border-radius: 50%;

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

            color: #999;

            font-size: 1.1rem;

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
                1.1rem 1.7rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            background: none;

            color:
                var(--main-color);

            cursor: pointer;

            font-size: 1.2rem;

            white-space: nowrap;

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

            color: #fff;

        }


        .btn-primary:hover {

            background:
                #5b5b45;

            color: #fff;

        }


        /* =====================================================
           FILTER PANEL
        ===================================================== */

        .filter-panel {

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

            margin-bottom:
                2.5rem;

            animation:
                cardAppear .6s ease;

        }


        .filter-panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .filter-title {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom:
                2rem;

        }


        .filter-title h3 {

            font-size:
                1.9rem;

        }


        .filter-title span {

            color: #999;

            font-size:
                1.1rem;

        }


        .filter-title>i {

            font-size:
                2rem;

        }


        .filter-form {

            display: grid;

            grid-template-columns:
                1.2fr 1fr 1fr 1fr auto;

            gap: 1rem;

            align-items: end;

        }


        .field label {

            display: block;

            font-size:
                1.1rem;

            color: #888;

            margin-bottom:
                .6rem;

        }


        .field select,
        .field input {

            width: 100%;

            padding:
                1.15rem 1.3rem;

            border:
                .15rem solid #ccc;

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            background: #fff;

            font-size:
                1.2rem;

        }


        .field select:focus,
        .field input:focus {

            border:
                var(--border);

        }


        /* =====================================================
           REPORT CARDS
        ===================================================== */

        .report-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 2rem;

            margin-bottom:
                2.5rem;

        }


        .report-card {

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.3rem;

            position: relative;

            overflow: hidden;

            animation:
                cardAppear .6s ease both;

        }


        .report-card:nth-child(1) {
            animation-delay: .05s;
        }

        .report-card:nth-child(2) {
            animation-delay: .10s;
        }

        .report-card:nth-child(3) {
            animation-delay: .15s;
        }

        .report-card:nth-child(4) {
            animation-delay: .20s;
        }

        .report-card:nth-child(5) {
            animation-delay: .25s;
        }

        .report-card:nth-child(6) {
            animation-delay: .30s;
        }


        .report-card:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

            transform:
                translateY(-.5rem);

            box-shadow:
                0 1rem 2rem rgba(68, 68, 51, .08);

        }


        .report-icon {

            width: 5rem;

            height: 5rem;

            display: flex;

            align-items: center;

            justify-content: center;

            border:
                .15rem solid var(--main-color);

            border-radius:
                45% 55% 60% 40%;

            font-size:
                2rem;

            margin-bottom:
                1.5rem;

        }


        .report-card:hover .report-icon {

            transform:
                rotate(-5deg) scale(1.08);

        }


        .report-card h3 {

            font-size:
                1.7rem;

            margin-bottom:
                .7rem;

        }


        .report-card p {

            color: #999;

            font-size:
                1.2rem;

            line-height:
                1.7;

            padding-right:
                3rem;

        }


        .report-card .arrow {

            position: absolute;

            right: 2rem;

            bottom: 2rem;

            width: 3.5rem;

            height: 3.5rem;

            display: flex;

            align-items: center;

            justify-content: center;

            border:
                .1rem solid #ccc;

            border-radius:
                var(--border-radius);

            cursor: pointer;

        }


        .report-card .arrow:hover {

            background:
                var(--main-color);

            color: #fff;

            transform:
                rotate(-5deg) scale(1.08);

        }


        /* =====================================================
           SUMMARY GRID
        ===================================================== */

        .summary-grid {

            display: grid;

            grid-template-columns:
                2fr 1fr;

            gap: 2rem;

            margin-bottom:
                2.5rem;

        }


        .summary-box {

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

        }


        .summary-box:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .summary-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 1rem;

            margin-bottom:
                2.5rem;

        }


        .summary-header h3 {

            font-size:
                1.8rem;

        }


        .summary-header span {

            display: block;

            color: #999;

            font-size:
                1.1rem;

            margin-top:
                .3rem;

        }


        .summary-values {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 1.5rem;

            margin-bottom:
                2.5rem;

        }


        .summary-item {

            padding:
                1.5rem;

            border:
                .1rem solid #ddd;

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
                translateY(-.3rem);

        }


        .summary-item small {

            color: #999;

            font-size:
                1rem;

        }


        .summary-item strong {

            display: block;

            font-size:
                1.9rem;

            margin-top:
                .5rem;

        }


        .green {

            color:
                var(--green);

        }


        .red {

            color:
                var(--red);

        }


        .blue {

            color:
                var(--blue);

        }


        .orange {

            color:
                var(--orange);

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
           CHART
        ===================================================== */

        .chart {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 1rem;

            height:
                23rem;

            padding:
                2rem .5rem 0;

            border-bottom:
                .1rem solid #ddd;

        }


        .bar-column {

            flex: 1;

            height: 100%;

            display: flex;

            flex-direction: column;

            justify-content: flex-end;

            align-items: center;

            gap: .7rem;

        }


        .bar {

            width:
                65%;

            max-width:
                4rem;

            min-height:
                .4rem;

            background:
                var(--main-color);

            border-radius:
                1.5rem 1.5rem .4rem .4rem;

            transform-origin:
                bottom;

            animation:
                chartGrow .9s cubic-bezier(.22, 1, .36, 1) both;

        }


        .bar:hover {

            background:
                #766;

            transform:
                scaleX(1.08) translateY(-.4rem);

        }


        .bar-label {

            color: #999;

            font-size:
                1rem;

        }


        .bar-value {

            font-size:
                .9rem;

            color: #777;

        }


        /* =====================================================
           STATISTIC INFO
        ===================================================== */

        .info {

            display: grid;

            grid-template-columns:
                1fr;

            gap: 1rem;

        }


        .info-box {

            background: #fff;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            padding:
                1.5rem;

            transition:
                all .25s ease;

        }


        .info-box:hover {

            border:
                .15rem dashed var(--main-color);

            border-radius:
                var(--border-radius-hover);

            background:
                #faf8f1;

            transform:
                translateX(.3rem);

        }


        .info-box p {

            color: #999;

            font-size:
                1rem;

        }


        .info-box h4 {

            font-size:
                1.7rem;

            margin-top:
                .5rem;

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-panel {

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

            margin-bottom:
                2.5rem;

        }


        .table-panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .table-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 1rem;

            margin-bottom:
                2rem;

        }


        .table-header h3 {

            font-size:
                1.8rem;

        }


        .table-header span {

            display: block;

            color: #999;

            font-size:
                1.1rem;

            margin-top:
                .3rem;

        }


        .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        table {

            width: 100%;

            min-width:
                75rem;

            border-collapse:
                collapse;

        }


        th {

            padding:
                1.4rem 1rem;

            text-align:
                left;

            border-bottom:
                .2rem solid var(--main-color);

            font-size:
                1.1rem;

            white-space:
                nowrap;

        }


        td {

            padding:
                1.4rem 1rem;

            border-bottom:
                .1rem solid #eee;

            font-size:
                1.2rem;

        }


        tbody tr {

            transition:
                all .2s ease;

        }


        tbody tr:hover {

            background:
                #faf8f1;

            transform:
                translateX(.3rem);

        }


        .badge {

            display:
                inline-flex;

            align-items:
                center;

            padding:
                .5rem 1rem;

            border:
                .1rem solid #ccc;

            border-radius:
                var(--border-radius);

            font-size:
                1rem;

        }


        .download {

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
                .1rem solid #ccc;

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            background: none;

            cursor:
                pointer;

        }


        .download:hover {

            background:
                var(--main-color);

            color:
                #fff;

            transform:
                scale(1.08);

        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            text-align:
                center;

            padding:
                3rem 1rem;

            color:
                #999;

            font-size:
                1.2rem;

        }


        .empty i {

            display:
                block;

            font-size:
                3rem;

            margin-bottom:
                1rem;

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

        @media (max-width:1200px) {

            .report-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .summary-grid {

                grid-template-columns:
                    1fr;

            }


            .filter-form {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        /* =====================================================
           RESPONSIVE 900
        ===================================================== */

        @media (max-width:900px) {

            .sidebar {

                left:
                    -27rem;

                box-shadow:
                    .5rem 0 2rem rgba(0, 0, 0, .08);

            }


            .sidebar.active {

                left: 0;

            }


            .main {

                margin-left:
                    0;

            }


            #menu-btn {

                display:
                    block;

            }


            .topbar {

                padding:
                    0 2rem;

            }


            .content {

                padding:
                    2rem;

            }


            .profile-info {

                display:
                    none;

            }

        }


        /* =====================================================
           RESPONSIVE 600
        ===================================================== */

        @media (max-width:600px) {

            html {

                font-size:
                    50%;

            }


            .content {

                padding:
                    1.5rem;

            }


            .topbar {

                padding:
                    0 1.5rem;

            }


            .report-grid {

                grid-template-columns:
                    1fr;

            }


            .summary-values {

                grid-template-columns:
                    1fr;

            }


            .filter-form {

                grid-template-columns:
                    1fr;

            }


            .filter-panel,
            .summary-box,
            .table-panel {

                padding:
                    1.5rem;

            }


            .table-header {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }


            .table-header .btn {

                width:
                    100%;

            }


            .chart {

                height:
                    20rem;

                gap:
                    .5rem;

            }


            .bar {

                width:
                    70%;

            }

        }


        /* =====================================================
           PRINT
        ===================================================== */

        @media print {

            .sidebar,
            .topbar,
            .page-actions,
            .filter-panel {

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


            .report-card,
            .summary-box,
            .table-panel {

                border:
                    .1rem solid #333;

                box-shadow:
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


        <a href="dashboar.php">

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


        <a
            href="reports.php"
            class="active">

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
                        Laporan
                    </h2>

                    <p>
                        Analisis dan laporan bisnis Toku Coffee ERP
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


            <!-- ACTION -->

            <div class="page-actions">

                <button
                    class="btn"
                    onclick="window.print()">

                    <i class="fas fa-print"></i>

                    Cetak Laporan

                </button>

            </div>


            <!-- =====================================================
                 FILTER
            ===================================================== -->

            <div class="filter-panel">


                <div class="filter-title">


                    <div>

                        <h3>
                            Filter Laporan
                        </h3>

                        <span>
                            Tentukan periode dan jenis laporan
                        </span>

                    </div>


                    <i class="fas fa-filter"></i>


                </div>


                <div class="filter-form">


                    <div class="field">

                        <label>
                            Jenis Laporan
                        </label>

                        <select id="reportType">

                            <option value="all">
                                Semua Laporan
                            </option>

                            <option value="sales">
                                Penjualan
                            </option>

                            <option value="purchase">
                                Pembelian
                            </option>

                            <option value="inventory">
                                Inventory
                            </option>

                            <option value="customer">
                                Pelanggan
                            </option>

                            <option value="supplier">
                                Supplier
                            </option>

                            <option value="finance">
                                Keuangan
                            </option>

                        </select>

                    </div>


                    <div class="field">

                        <label>
                            Dari Tanggal
                        </label>

                        <input
                            type="date"
                            id="dateFrom"
                            value="<?= date('Y-m-01') ?>">

                    </div>


                    <div class="field">

                        <label>
                            Sampai Tanggal
                        </label>

                        <input
                            type="date"
                            id="dateTo"
                            value="<?= date('Y-m-d') ?>">

                    </div>


                    <div class="field">

                        <label>
                            Format
                        </label>

                        <select id="reportFormat">

                            <option value="ringkasan">
                                Ringkasan
                            </option>

                            <option value="detail">
                                Detail
                            </option>

                        </select>

                    </div>


                    <button
                        class="btn btn-primary"
                        onclick="generateReport()">

                        <i class="fas fa-chart-column"></i>

                        Tampilkan

                    </button>


                </div>


            </div>


            <!-- =====================================================
                 REPORT CARDS
            ===================================================== -->

            <div class="report-grid">


                <!-- PENJUALAN -->

                <div class="report-card">


                    <div class="report-icon">

                        <i class="fas fa-cart-shopping"></i>

                    </div>


                    <h3>
                        Laporan Penjualan
                    </h3>


                    <p>
                        Analisis omzet, transaksi,
                        dan performa penjualan Toku Coffee.
                    </p>


                    <div
                        class="arrow"
                        onclick="openReport('sales')">

                        <i class="fas fa-arrow-right"></i>

                    </div>


                </div>


                <!-- PEMBELIAN -->

                <div class="report-card">


                    <div class="report-icon">

                        <i class="fas fa-basket-shopping"></i>

                    </div>


                    <h3>
                        Laporan Pembelian
                    </h3>


                    <p>
                        Pantau transaksi pembelian,
                        supplier dan pengadaan barang.
                    </p>


                    <div
                        class="arrow"
                        onclick="openReport('purchase')">

                        <i class="fas fa-arrow-right"></i>

                    </div>


                </div>


                <!-- INVENTORY -->

                <div class="report-card">


                    <div class="report-icon">

                        <i class="fas fa-boxes-stacked"></i>

                    </div>


                    <h3>
                        Laporan Inventory
                    </h3>


                    <p>
                        Informasi stok, stok minimum,
                        barang masuk dan barang keluar.
                    </p>


                    <div
                        class="arrow"
                        onclick="openReport('inventory')">

                        <i class="fas fa-arrow-right"></i>

                    </div>


                </div>


                <!-- PELANGGAN -->

                <div class="report-card">


                    <div class="report-icon">

                        <i class="fas fa-users"></i>

                    </div>


                    <h3>
                        Laporan Pelanggan
                    </h3>


                    <p>
                        Analisis jumlah pelanggan,
                        status akun dan aktivitas customer.
                    </p>


                    <div
                        class="arrow"
                        onclick="openReport('customer')">

                        <i class="fas fa-arrow-right"></i>

                    </div>


                </div>


                <!-- SUPPLIER -->

                <div class="report-card">


                    <div class="report-icon">

                        <i class="fas fa-truck"></i>

                    </div>


                    <h3>
                        Laporan Supplier
                    </h3>


                    <p>
                        Informasi supplier,
                        pembelian dan kebutuhan pengadaan.
                    </p>


                    <div
                        class="arrow"
                        onclick="openReport('supplier')">

                        <i class="fas fa-arrow-right"></i>

                    </div>


                </div>


                <!-- KEUANGAN -->

                <div class="report-card">


                    <div class="report-icon">

                        <i class="fas fa-money-bill-trend-up"></i>

                    </div>


                    <h3>
                        Laporan Keuangan
                    </h3>


                    <p>
                        Ringkasan pemasukan,
                        pengeluaran dan performa keuangan.
                    </p>


                    <div
                        class="arrow"
                        onclick="openReport('finance')">

                        <i class="fas fa-arrow-right"></i>

                    </div>


                </div>


            </div>


            <!-- =====================================================
                 SUMMARY
            ===================================================== -->

            <div class="summary-grid">


                <!-- PERFORMA -->

                <div class="summary-box">


                    <div class="summary-header">


                        <div>

                            <h3>
                                Ringkasan Performa
                            </h3>

                            <span>
                                <?= e($bulanSekarang) ?>
                            </span>

                        </div>


                        <button
                            class="btn"
                            onclick="window.print()">

                            <i class="fas fa-download"></i>

                            Export

                        </button>


                    </div>


                    <div class="summary-values">


                        <div class="summary-item">

                            <small>
                                Total Penjualan
                            </small>

                            <strong class="green">

                                <?= rupiah($totalPenjualan) ?>

                            </strong>

                        </div>


                        <div class="summary-item">

                            <small>
                                Total Pesanan
                            </small>

                            <strong class="orange">

                                <?= number_format($totalPesanan) ?>

                            </strong>

                        </div>


                        <div class="summary-item">

                            <small>
                                Rata-rata Order
                            </small>

                            <strong class="blue">

                                <?= rupiah($rataOrder) ?>

                            </strong>

                        </div>


                    </div>


                    <!-- CHART -->

                    <div class="chart">


                        <?php foreach ($sales as $item): ?>


                            <div class="bar-column">


                                <span class="bar-value">

                                    <?= rupiah($item['total']) ?>

                                </span>


                                <div
                                    class="bar"
                                    style="
                                        height:
                                        <?= max(2, $item['value']) ?>%;
                                    ">

                                </div>


                                <span class="bar-label">

                                    <?= e($item['month']) ?>

                                </span>


                            </div>


                        <?php endforeach; ?>


                    </div>


                </div>


                <!-- STATISTIK -->

                <div class="summary-box">


                    <div class="summary-header">


                        <div>

                            <h3>
                                Statistik
                            </h3>

                            <span>
                                Data berjalan
                            </span>

                        </div>


                    </div>


                    <div class="info">


                        <div class="info-box">

                            <p>
                                Pesanan Bulan Ini
                            </p>

                            <h4>
                                <?= number_format($pesananBulanIni) ?>
                            </h4>

                        </div>


                        <div class="info-box">

                            <p>
                                Penjualan Bulan Ini
                            </p>

                            <h4 class="green">
                                <?= rupiah($penjualanBulanIni) ?>
                            </h4>

                        </div>


                        <div class="info-box">

                            <p>
                                Pelanggan
                            </p>

                            <h4>
                                <?= number_format($totalPelanggan) ?>
                            </h4>

                        </div>


                        <div class="info-box">

                            <p>
                                Produk Aktif
                            </p>

                            <h4>
                                <?= number_format($totalProduk) ?>
                            </h4>

                        </div>


                    </div>


                </div>


            </div>


            <!-- =====================================================
                 TABLE DATA
            ===================================================== -->

            <div class="table-panel">


                <div class="table-header">


                    <div>

                        <h3>
                            Ringkasan Data ERP
                        </h3>

                        <span>
                            Data aktual dari database Toku Coffee
                        </span>

                    </div>


                    <button
                        class="btn"
                        onclick="window.print()">

                        <i class="fas fa-print"></i>

                        Cetak

                    </button>


                </div>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    DATA
                                </th>

                                <th>
                                    JUMLAH
                                </th>

                                <th>
                                    NILAI
                                </th>

                                <th>
                                    STATUS
                                </th>

                                <th>
                                    AKSI
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <!-- PENJUALAN -->

                            <tr>

                                <td>

                                    <strong>
                                        Penjualan
                                    </strong>

                                </td>

                                <td>

                                    <?= number_format($totalPesanan) ?>

                                    transaksi

                                </td>

                                <td>

                                    <?= rupiah($totalPenjualan) ?>

                                </td>

                                <td>

                                    <span class="badge green">

                                        Aktif

                                    </span>

                                </td>

                                <td>

                                    <button
                                        class="download"
                                        onclick="openReport('sales')"
                                        title="Lihat laporan">

                                        <i class="fas fa-arrow-right"></i>

                                    </button>

                                </td>

                            </tr>


                            <!-- PRODUK -->

                            <tr>

                                <td>

                                    <strong>
                                        Produk
                                    </strong>

                                </td>

                                <td>

                                    <?= number_format($totalProduk) ?>

                                    produk

                                </td>

                                <td>

                                    Katalog aktif

                                </td>

                                <td>

                                    <span class="badge green">

                                        Aktif

                                    </span>

                                </td>

                                <td>

                                    <button
                                        class="download"
                                        onclick="openReport('inventory')"
                                        title="Lihat inventory">

                                        <i class="fas fa-arrow-right"></i>

                                    </button>

                                </td>

                            </tr>


                            <!-- PELANGGAN -->

                            <tr>

                                <td>

                                    <strong>
                                        Pelanggan
                                    </strong>

                                </td>

                                <td>

                                    <?= number_format($totalPelanggan) ?>

                                    customer

                                </td>

                                <td>

                                    User terdaftar

                                </td>

                                <td>

                                    <span class="badge blue">

                                        Terdaftar

                                    </span>

                                </td>

                                <td>

                                    <button
                                        class="download"
                                        onclick="openReport('customer')"
                                        title="Lihat pelanggan">

                                        <i class="fas fa-arrow-right"></i>

                                    </button>

                                </td>

                            </tr>


                            <!-- INVENTORY -->

                            <tr>

                                <td>

                                    <strong>
                                        Inventory
                                    </strong>

                                </td>

                                <td>

                                    <?= number_format($totalStok) ?>

                                    unit

                                </td>

                                <td>

                                    <?= number_format($stokMenipis) ?>

                                    stok menipis

                                </td>

                                <td>

                                    <?php if ($produkHabis > 0): ?>

                                        <span class="badge red">

                                            Perlu Perhatian

                                        </span>

                                    <?php elseif ($stokMenipis > 0): ?>

                                        <span class="badge orange">

                                            Perlu Restock

                                        </span>

                                    <?php else: ?>

                                        <span class="badge green">

                                            Normal

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <button
                                        class="download"
                                        onclick="openReport('inventory')"
                                        title="Lihat inventory">

                                        <i class="fas fa-arrow-right"></i>

                                    </button>

                                </td>

                            </tr>


                        </tbody>


                    </table>


                </div>


            </div>


            <!-- =====================================================
                 INFO BAWAH
            ===================================================== -->

            <div
                class="summary-values"
                style="margin-bottom:0;">


                <div class="summary-item">

                    <small>
                        Penjualan Hari Ini
                    </small>

                    <strong class="green">

                        <?= rupiah($penjualanHariIni) ?>

                    </strong>

                </div>


                <div class="summary-item">

                    <small>
                        Pesanan Hari Ini
                    </small>

                    <strong>

                        <?= number_format($pesananHariIni) ?>

                    </strong>

                </div>


                <div class="summary-item">

                    <small>
                        Stok Menipis
                    </small>

                    <strong class="orange">

                        <?= number_format($stokMenipis) ?>

                    </strong>

                </div>


                <div class="summary-item">

                    <small>
                        Produk Habis
                    </small>

                    <strong class="red">

                        <?= number_format($produkHabis) ?>

                    </strong>

                </div>


            </div>


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
                        'fa-xmark'
                    );

                    this.classList.toggle(
                        'fa-bars'
                    );

                }
            );

        }


        /* =====================================================
           TUTUP SIDEBAR SAAT LINK DIKLIK
        ===================================================== */

        document
            .querySelectorAll(
                '.sidebar a'
            )
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
           KLIK DI LUAR SIDEBAR
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
           GENERATE REPORT
        ===================================================== */

        function generateReport() {

            const type =
                document.getElementById(
                    'reportType'
                ).value;

            const dateFrom =
                document.getElementById(
                    'dateFrom'
                ).value;

            const dateTo =
                document.getElementById(
                    'dateTo'
                ).value;

            const format =
                document.getElementById(
                    'reportFormat'
                ).value;


            if (!dateFrom || !dateTo) {

                alert(
                    'Silakan tentukan periode laporan.'
                );

                return;

            }


            if (dateFrom > dateTo) {

                alert(
                    'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.'
                );

                return;

            }


            if (type === 'all') {

                alert(
                    'Menampilkan semua laporan\n' +
                    dateFrom +
                    ' sampai ' +
                    dateTo +
                    '\nFormat: ' +
                    format
                );

            } else {

                alert(
                    'Laporan ' +
                    type +
                    '\n' +
                    dateFrom +
                    ' sampai ' +
                    dateTo +
                    '\nFormat: ' +
                    format
                );

            }

        }


        /* =====================================================
           OPEN REPORT
        ===================================================== */

        function openReport(type) {

            const pages = {

                sales: 'report-sales.php',

                purchase: 'report-purchase.php',

                inventory: 'report-inventory.php',

                customer: 'report-customers.php',

                supplier: 'report-suppliers.php',

                finance: 'report-finance.php'

            };


            if (pages[type]) {

                window.location.href =
                    pages[type];

            }

        }
    </script>


</body>

</html>
