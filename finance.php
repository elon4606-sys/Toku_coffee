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

function statusClass($status)
{
    $status = strtolower(trim((string)$status));

    return str_replace(' ', '-', $status);
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
   TOTAL PENDAPATAN
========================================================= */

$totalPendapatan = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM pesanan
    WHERE status != 'Dibatalkan'
    "
);


/* =========================================================
   PENDAPATAN HARI INI
========================================================= */

$pendapatanHariIni = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(SUM(total), 0)
    FROM pesanan
    WHERE DATE(tanggal_pesanan) = CURDATE()
    AND status != 'Dibatalkan'
    "
);


/* =========================================================
   PENDAPATAN BULAN INI
========================================================= */

$pendapatanBulanIni = (float) queryValue(
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
   TOTAL TRANSAKSI
========================================================= */

$totalTransaksi = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM pesanan
    WHERE status != 'Dibatalkan'
    "
);


/* =========================================================
   TRANSAKSI HARI INI
========================================================= */

$transaksiHariIni = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM pesanan
    WHERE DATE(tanggal_pesanan) = CURDATE()
    "
);


/* =========================================================
   TRANSAKSI SELESAI
========================================================= */

$transaksiSelesai = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM pesanan
    WHERE status = 'Selesai'
    "
);


/* =========================================================
   TRANSAKSI MENUNGGU
========================================================= */

$transaksiMenunggu = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM pesanan
    WHERE status = 'Menunggu'
    "
);


/* =========================================================
   TRANSAKSI DIBATALKAN
========================================================= */

$transaksiDibatalkan = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*)
    FROM pesanan
    WHERE status = 'Dibatalkan'
    "
);


/* =========================================================
   RATA-RATA TRANSAKSI
========================================================= */

$rataTransaksi = (float) queryValue(
    $conn,
    "
    SELECT COALESCE(AVG(total), 0)
    FROM pesanan
    WHERE status != 'Dibatalkan'
    "
);


/* =========================================================
   DATA PENJUALAN 6 BULAN
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
   JIKA BELUM ADA DATA
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
   TRANSAKSI TERBARU
========================================================= */

$recentTransactions = [];

$result = $conn->query("
    SELECT
        id,
        invoice,
        tanggal_pesanan,
        total,
        status
    FROM pesanan
    ORDER BY tanggal_pesanan DESC
    LIMIT 8
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $recentTransactions[] = [

            'id' =>
            $row['id'],

            'invoice' =>
            $row['invoice'],

            'date' =>
            date(
                'd M Y H:i',
                strtotime(
                    $row['tanggal_pesanan']
                )
            ),

            'total' =>
            (float)$row['total'],

            'status' =>
            $row['status']

        ];
    }
}


/* =========================================================
   RINGKASAN STATUS
========================================================= */

$statusKeuangan = [

    [
        'icon' => 'fa-circle-check',
        'title' => 'Transaksi Selesai',
        'description' => 'Transaksi berhasil',
        'value' => $transaksiSelesai,
        'class' => 'success'
    ],

    [
        'icon' => 'fa-clock',
        'title' => 'Menunggu',
        'description' => 'Perlu diproses',
        'value' => $transaksiMenunggu,
        'class' => 'warning'
    ],

    [
        'icon' => 'fa-circle-xmark',
        'title' => 'Dibatalkan',
        'description' => 'Tidak dihitung sebagai pendapatan',
        'value' => $transaksiDibatalkan,
        'class' => 'danger'
    ],

    [
        'icon' => 'fa-chart-line',
        'title' => 'Rata-rata Transaksi',
        'description' => 'Nilai rata-rata order',
        'value' => rupiah($rataTransaksi),
        'class' => 'normal'
    ]

];

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Keuangan - Toku Coffee ERP
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

            cursor: pointer;

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
           ACTION
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
           GRID
        ===================================================== */

        .finance-grid {

            display: grid;

            grid-template-columns:
                2fr 1fr;

            gap: 2rem;

            margin-bottom: 2rem;

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
                border .25s ease;

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

            color:
                var(--main-color);

            font-size: 1.2rem;

            border-bottom:
                .1rem dashed var(--main-color);

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


        .chart-bar:hover {

            background:
                #766;

            transform:
                scaleX(1.08) translateY(-.4rem);

        }


        .chart-value {

            font-size: 1rem;

            color: #777;

            text-align: center;

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

            font-size: 1.4rem;

            font-weight: 600;

        }


        /* =====================================================
           FINANCE SUMMARY
        ===================================================== */

        .finance-overview {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 1rem;

            margin-bottom: 2rem;

        }


        .finance-box {

            padding: 1.5rem;

            background:
                #faf8f1;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

        }


        .finance-box:hover {

            border:
                .15rem dashed var(--main-color);

            border-radius:
                var(--border-radius-hover);

            transform:
                translateY(-.3rem);

        }


        .finance-box i {

            font-size: 1.8rem;

            margin-bottom: .8rem;

        }


        .finance-box h4 {

            font-size: 1.2rem;

            color: #888;

        }


        .finance-box strong {

            display: block;

            font-size: 1.6rem;

            margin-top: .4rem;

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

            min-width: 75rem;

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


        .invoice {

            font-weight: 600;

            color:
                var(--main-color);

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


        .status.dibatalkan {

            color:
                var(--red);

            background:
                #fff0ef;

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


            .finance-grid {

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


            .finance-overview {

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


        <!-- AKTIF -->

        <a
            href="finance.php"
            class="active">

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

                        Keuangan

                    </h2>

                    <p>

                        Manajemen keuangan Toku Coffee ERP

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


                <!-- TOTAL PENDAPATAN -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-money-bill-wave"></i>

                    </div>


                    <div>

                        <h3>

                            <?= rupiah($totalPendapatan) ?>

                        </h3>

                        <p>

                            Total Pendapatan

                        </p>

                        <small>

                            <i class="fas fa-chart-line"></i>

                            Semua transaksi

                        </small>

                    </div>

                </div>


                <!-- HARI INI -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-calendar-day"></i>

                    </div>


                    <div>

                        <h3>

                            <?= rupiah($pendapatanHariIni) ?>

                        </h3>

                        <p>

                            Pendapatan Hari Ini

                        </p>

                        <small>

                            <i class="fas fa-arrow-up"></i>

                            <?= number_format($transaksiHariIni) ?>

                            transaksi

                        </small>

                    </div>

                </div>


                <!-- BULAN -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-calendar-days"></i>

                    </div>


                    <div>

                        <h3>

                            <?= rupiah($pendapatanBulanIni) ?>

                        </h3>

                        <p>

                            Pendapatan Bulan Ini

                        </p>

                        <small>

                            <i class="fas fa-chart-column"></i>

                            Performa berjalan

                        </small>

                    </div>

                </div>


                <!-- TRANSAKSI -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-receipt"></i>

                    </div>


                    <div>

                        <h3>

                            <?= number_format($totalTransaksi) ?>

                        </h3>

                        <p>

                            Total Transaksi

                        </p>

                        <small>

                            <i class="fas fa-cart-shopping"></i>

                            Transaksi aktif

                        </small>

                    </div>

                </div>


            </div>


            <!-- =====================================================
             GRAFIK + RINGKASAN
        ===================================================== -->

            <section class="finance-grid">


                <!-- GRAFIK -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>

                            Grafik Pendapatan

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
                                    <?= max(2, $item['value']) ?>%; ">

                                </div>


                                <span class="chart-label">

                                    <?= e($item['month']) ?>

                                </span>


                            </div>


                        <?php endforeach; ?>


                    </div>


                </div>


                <!-- RINGKASAN -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>

                            Ringkasan Keuangan

                        </h2>

                    </div>


                    <div class="summary-list">


                        <?php foreach ($statusKeuangan as $item): ?>


                            <div class="summary-item">


                                <div class="summary-icon">

                                    <i
                                        class="fas <?= e($item['icon']) ?>">
                                    </i>

                                </div>


                                <div class="summary-content">

                                    <h4>

                                        <?= e($item['title']) ?>

                                    </h4>

                                    <span>

                                        <?= e($item['description']) ?>

                                    </span>

                                </div>


                                <div class="summary-number">

                                    <?= is_numeric($item['value'])
                                        ? number_format($item['value'])
                                        : e($item['value'])
                                    ?>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                </div>


            </section>


            <!-- =====================================================
             PERFORMA KEUANGAN
        ===================================================== -->

            <section class="panel"
                style="margin-bottom:2rem;">


                <div class="panel-header">

                    <h2>

                        Performa Keuangan

                    </h2>

                </div>


                <div class="finance-overview">


                    <div class="finance-box">

                        <i class="fas fa-cash-register"></i>

                        <h4>

                            Pendapatan Hari Ini

                        </h4>

                        <strong>

                            <?= rupiah($pendapatanHariIni) ?>

                        </strong>

                    </div>


                    <div class="finance-box">

                        <i class="fas fa-chart-line"></i>

                        <h4>

                            Pendapatan Bulan Ini

                        </h4>

                        <strong>

                            <?= rupiah($pendapatanBulanIni) ?>

                        </strong>

                    </div>


                    <div class="finance-box">

                        <i class="fas fa-calculator"></i>

                        <h4>

                            Rata-rata Transaksi

                        </h4>

                        <strong>

                            <?= rupiah($rataTransaksi) ?>

                        </strong>

                    </div>


                </div>


                <div class="summary-list">


                    <div class="summary-item">

                        <div class="summary-icon">

                            <i class="fas fa-receipt"></i>

                        </div>


                        <div class="summary-content">

                            <h4>

                                Total Transaksi

                            </h4>

                            <span>

                                Seluruh transaksi valid

                            </span>

                        </div>


                        <div class="summary-number">

                            <?= number_format($totalTransaksi) ?>

                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-icon">

                            <i class="fas fa-circle-check"></i>

                        </div>


                        <div class="summary-content">

                            <h4>

                                Transaksi Selesai

                            </h4>

                            <span>

                                Pesanan yang sudah selesai

                            </span>

                        </div>


                        <div class="summary-number">

                            <?= number_format($transaksiSelesai) ?>

                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-icon">

                            <i class="fas fa-clock"></i>

                        </div>


                        <div class="summary-content">

                            <h4>

                                Transaksi Menunggu

                            </h4>

                            <span>

                                Perlu tindak lanjut

                            </span>

                        </div>


                        <div class="summary-number">

                            <?= number_format($transaksiMenunggu) ?>

                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-icon">

                            <i class="fas fa-circle-xmark"></i>

                        </div>


                        <div class="summary-content">

                            <h4>

                                Transaksi Dibatalkan

                            </h4>

                            <span>

                                Tidak dihitung sebagai pendapatan

                            </span>

                        </div>


                        <div class="summary-number">

                            <?= number_format($transaksiDibatalkan) ?>

                        </div>

                    </div>


                </div>


            </section>


            <!-- =====================================================
             TRANSAKSI TERBARU
        ===================================================== -->

            <section class="panel table-panel">


                <div class="panel-header">

                    <h2>

                        Transaksi Keuangan Terbaru

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

                                    No

                                </th>

                                <th>

                                    Invoice

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


                            <?php if (!empty($recentTransactions)): ?>


                                <?php
                                $no = 1;
                                ?>


                                <?php foreach (
                                    $recentTransactions
                                    as $transaction
                                ): ?>


                                    <tr>


                                        <td>

                                            <?= $no++ ?>

                                        </td>


                                        <td>

                                            <span class="invoice">

                                                <?= e(
                                                    $transaction['invoice']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= e(
                                                $transaction['date']
                                            ) ?>

                                        </td>


                                        <td>

                                            <strong>

                                                <?= rupiah(
                                                    $transaction['total']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <span
                                                class="status <?= e(
                                                                    statusClass(
                                                                        $transaction['status']
                                                                    )
                                                                ) ?>">

                                                <?= e(
                                                    $transaction['status']
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
                                            class="fas fa-wallet">
                                        </i>

                                        Belum ada transaksi keuangan.

                                    </td>

                                </tr>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </section>


            <!-- =====================================================
             AKSI CEPAT
        ===================================================== -->

            <section class="panel">


                <div class="panel-header">

                    <h2>

                        Aksi Cepat Keuangan

                    </h2>

                </div>


                <div class="quick-actions">


                    <a
                        href="orders.php"
                        class="quick-action">

                        <i class="fas fa-receipt"></i>

                        Lihat Pesanan

                    </a>


                    <a
                        href="reports.php"
                        class="quick-action">

                        <i class="fas fa-chart-line"></i>

                        Laporan Keuangan

                    </a>


                    <a
                        href="inventory.php"
                        class="quick-action">

                        <i class="fas fa-warehouse"></i>

                        Inventory

                    </a>


                    <a
                        href="suppliers.php"
                        class="quick-action">

                        <i class="fas fa-truck"></i>

                        Supplier

                    </a>


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
           AUTO REFRESH
        ===================================================== */

        let financeRefresh = null;


        function startFinanceRefresh() {

            if (financeRefresh) {

                clearInterval(
                    financeRefresh
                );

            }


            financeRefresh =
                setInterval(
                    function() {

                        if (!document.hidden) {

                            window.location.reload();

                        }

                    },
                    60000
                );

        }


        startFinanceRefresh();
    </script>


</body>

</html>
