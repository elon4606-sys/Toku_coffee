<?php

require_once "config/koneksi.php";
require_once "config/session.php";

requireRole(['admin', 'staff']);

/* =========================================================
   FUNGSI BANTUAN
========================================================= */

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

function statusClass($status)
{
    return strtolower(
        str_replace(
            ' ',
            '-',
            trim((string)$status)
        )
    );
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
   STATISTIK CUSTOMER
========================================================= */

$totalPelanggan = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
    "
);


$pelangganAktif = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
    AND status = 'aktif'
    "
);


$pelangganNonaktif = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
    AND status != 'aktif'
    "
);


/* =========================================================
   CUSTOMER BARU BULAN INI
========================================================= */

$customerBaru = (int) queryValue(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
    AND MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
    "
);


/* =========================================================
   CUSTOMER YANG BERTRANSAKSI
========================================================= */

$customerTransaksi = (int) queryValue(
    $conn,
    "
    SELECT COUNT(DISTINCT user_id) AS total
    FROM pesanan
    WHERE user_id IS NOT NULL
    AND status != 'Dibatalkan'
    "
);


/* =========================================================
   PENCARIAN
========================================================= */

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';


$statusFilter = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';


/* =========================================================
   DATA CUSTOMER
========================================================= */

$customers = [];

$sql = "
    SELECT
        id,
        full_name,
        username,
        email,
        status,
        created_at
    FROM users
    WHERE role = 'customer'
";


/* =========================================================
   SEARCH
========================================================= */

if ($search !== '') {

    $searchSafe =
        $conn->real_escape_string($search);

    $sql .= "
        AND (
            full_name LIKE '%$searchSafe%'
            OR username LIKE '%$searchSafe%'
            OR email LIKE '%$searchSafe%'
        )
    ";
}


/* =========================================================
   FILTER STATUS
========================================================= */

if ($statusFilter !== '') {

    $statusSafe =
        $conn->real_escape_string($statusFilter);

    $sql .= "
        AND status = '$statusSafe'
    ";
}


$sql .= "
    ORDER BY id DESC
";


$result = $conn->query($sql);


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $customers[] = $row;
    }
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Pelanggan - Toku Coffee ERP
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

            margin:
                0;

            padding:
                0;

            box-sizing:
                border-box;

            outline:
                none;

            border:
                none;

            text-decoration:
                none;

            transition:
                all .2s linear;

        }


        html {

            font-size:
                62.5%;

            overflow-x:
                hidden;

            scroll-behavior:
                smooth;

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

            position:
                fixed;

            top:
                0;

            left:
                0;

            width:
                26rem;

            height:
                100vh;

            background:
                #fff;

            border-right:
                .1rem solid #eee;

            padding:
                2.5rem 1.5rem;

            z-index:
                1000;

            overflow-y:
                auto;

            transition:
                left .35s ease,
                transform .35s ease;

        }


        .logo {

            display:
                block;

            text-align:
                center;

            color:
                var(--main-color);

            font-size:
                2.6rem;

            font-weight:
                600;

            margin-bottom:
                3rem;

            transition:
                transform .25s ease;

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

            overflow:
                hidden;

        }


        .sidebar a i {

            width:
                2rem;

            font-size:
                1.7rem;

            text-align:
                center;

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

            margin-left:
                26rem;

            min-height:
                100vh;

            transition:
                margin-left .35s ease;

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


        .notification i {

            transition:
                transform .25s ease;

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

            width:
                1.8rem;

            height:
                1.8rem;

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

            cursor:
                pointer;

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

            transition:
                transform .3s ease;

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
           PAGE HEADER
        ===================================================== */

        .page-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                2rem;

            margin-bottom:
                2rem;

        }


        .page-header h1 {

            font-size:
                2.3rem;

        }


        .page-header p {

            font-size:
                1.2rem;

            color:
                #888;

            margin-top:
                .3rem;

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
           PAGE ACTION
        ===================================================== */

        .page-actions {

            display:
                flex;

            justify-content:
                flex-end;

            margin-bottom:
                2rem;

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

            position:
                relative;

            overflow:
                hidden;

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

            cursor:
                default;

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

            transition:
                transform .3s ease;

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


        .panel-header a {

            font-size:
                1.2rem;

            color:
                var(--main-color);

            border-bottom:
                .1rem dashed var(--main-color);

        }


        .panel-header a:hover {

            padding-right:
                .5rem;

        }


        /* =====================================================
           CUSTOMER SUMMARY
        ===================================================== */

        .customer-summary {

            display:
                grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap:
                1.2rem;

            margin-bottom:
                2.5rem;

        }


        .summary-item {

            display:
                flex;

            align-items:
                center;

            gap:
                1rem;

            padding:
                1.2rem;

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

            width:
                4rem;

            height:
                4rem;

            flex:
                0 0 4rem;

            border-radius:
                45%;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #f4f1e8;

            font-size:
                1.5rem;

        }


        .summary-content {

            flex:
                1;

        }


        .summary-content h4 {

            font-size:
                1.3rem;

        }


        .summary-content span {

            display:
                block;

            color:
                #888;

            font-size:
                1.1rem;

            margin-top:
                .2rem;

        }


        .summary-number {

            font-size:
                1.5rem;

            font-weight:
                600;

        }


        /* =====================================================
           FILTER
        ===================================================== */

        .filter-box {

            display:
                flex;

            align-items:
                center;

            gap:
                1rem;

            margin-bottom:
                2rem;

        }


        .search-box {

            position:
                relative;

            flex:
                1;

        }


        .search-box i {

            position:
                absolute;

            left:
                1.4rem;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #999;

            font-size:
                1.4rem;

        }


        .search-box input {

            width:
                100%;

            height:
                4.5rem;

            padding:
                0 1.5rem 0 4rem;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            font-size:
                1.2rem;

            color:
                var(--main-color);

            background:
                #fff;

        }


        .search-box input:focus {

            border:
                .2rem solid var(--main-color);

            border-radius:
                var(--border-radius-hover);

        }


        .filter-select {

            height:
                4.5rem;

            min-width:
                15rem;

            padding:
                0 1.2rem;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            font-family:
                'Poppins',
                sans-serif;

            font-size:
                1.2rem;

            color:
                var(--main-color);

            background:
                #fff;

            cursor:
                pointer;

        }


        .filter-select:focus {

            border:
                .2rem solid var(--main-color);

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table-panel {

            margin-bottom:
                2rem;

        }


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
                85rem;

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

        }


        tbody tr:hover {

            background:
                #faf8f1;

            transform:
                translateX(.3rem);

        }


        /* =====================================================
           CUSTOMER
        ===================================================== */

        .customer-info {

            display:
                flex;

            align-items:
                center;

            gap:
                1rem;

        }


        .customer-avatar {

            width:
                4.5rem;

            height:
                4.5rem;

            border-radius:
                50%;

            border:
                .15rem solid var(--main-color);

            object-fit:
                cover;

        }


        .customer-name {

            font-size:
                1.3rem;

            font-weight:
                600;

            color:
                var(--main-color);

        }


        .customer-username {

            font-size:
                1rem;

            color:
                #999;

            margin-top:
                .2rem;

        }


        .customer-email {

            color:
                #777;

            font-size:
                1.2rem;

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

        }


        .status.aktif {

            color:
                var(--green);

            background:
                #f0f7f0;

        }


        .status.nonaktif {

            color:
                var(--red);

            background:
                #fff0ef;

        }


        .status.pending {

            color:
                var(--orange);

            background:
                #fff7e9;

        }


        /* =====================================================
           ACTION
        ===================================================== */

        .action-buttons {

            display:
                flex;

            align-items:
                center;

            gap:
                .7rem;

        }


        .action-btn {

            width:
                3.5rem;

            height:
                3.5rem;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            background:
                #fff;

            font-size:
                1.2rem;

        }


        .action-btn:hover {

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
                3rem;

            margin-bottom:
                1rem;

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


            .customer-summary {

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

        }


        /* =====================================================
           RESPONSIVE 650
        ===================================================== */

        @media (max-width: 650px) {

            .customer-summary {

                grid-template-columns:
                    1fr;

            }


            .filter-box {

                flex-direction:
                    column;

                align-items:
                    stretch;

            }


            .filter-select {

                width:
                    100%;

            }


            .filter-box .btn {

                width:
                    100%;

            }


            .page-header {

                flex-direction:
                    column;

                align-items:
                    flex-start;

            }

        }


        /* =====================================================
           RESPONSIVE 550
        ===================================================== */

        @media (max-width: 550px) {

            html {

                font-size:
                    50%;

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


            .page-title h2 {

                font-size:
                    2rem;

            }


            .page-title p {

                display:
                    none;

            }


            .topbar {

                padding:
                    0 1.5rem;

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


        <!-- ACTIVE PELANGGAN -->

        <a
            href="customers.php"
            class="active">

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
                        Pelanggan
                    </h2>

                    <p>
                        Manajemen pelanggan Toku Coffee ERP
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


            <!-- =================================================
             PAGE HEADER
        ================================================= -->

            <div class="page-header">

                <div>

                    <h1>
                        Data Pelanggan
                    </h1>

                    <p>
                        Kelola dan pantau seluruh pelanggan Toku Coffee.
                    </p>

                </div>

            </div>


            <!-- =================================================
             PAGE ACTION
        ================================================= -->

            <div class="page-actions">

                <button
                    class="btn"
                    onclick="window.location.reload();">

                    <i class="fas fa-sync-alt"></i>

                    Refresh Data

                </button>

            </div>


            <!-- =================================================
             STATISTICS
        ================================================= -->

            <div class="stats">


                <!-- TOTAL -->

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

                            <i class="fas fa-users"></i>

                            Customer terdaftar

                        </small>

                    </div>

                </div>


                <!-- AKTIF -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-user-check"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($pelangganAktif) ?>
                        </h3>

                        <p>
                            Pelanggan Aktif
                        </p>

                        <small>

                            <i class="fas fa-circle-check"></i>

                            Akun aktif

                        </small>

                    </div>

                </div>


                <!-- BARU -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-user-plus"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($customerBaru) ?>
                        </h3>

                        <p>
                            Customer Baru
                        </p>

                        <small>

                            <i class="fas fa-calendar"></i>

                            Bulan ini

                        </small>

                    </div>

                </div>


                <!-- TRANSAKSI -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-cart-shopping"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($customerTransaksi) ?>
                        </h3>

                        <p>
                            Customer Bertransaksi
                        </p>

                        <small>

                            <i class="fas fa-chart-line"></i>

                            Customer aktif

                        </small>

                    </div>

                </div>


            </div>


            <!-- =================================================
             CUSTOMER SUMMARY
        ================================================= -->

            <div class="panel"
                style="margin-bottom:2rem;">


                <div class="panel-header">

                    <h2>
                        Ringkasan Pelanggan
                    </h2>

                </div>


                <div class="customer-summary">


                    <div class="summary-item">

                        <div class="summary-icon">

                            <i class="fas fa-user-check"></i>

                        </div>


                        <div class="summary-content">

                            <h4>
                                Pelanggan Aktif
                            </h4>

                            <span>
                                Akun dapat digunakan
                            </span>

                        </div>


                        <div class="summary-number">

                            <?= number_format($pelangganAktif) ?>

                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-icon">

                            <i class="fas fa-user-slash"></i>

                        </div>


                        <div class="summary-content">

                            <h4>
                                Pelanggan Nonaktif
                            </h4>

                            <span>
                                Akun tidak aktif
                            </span>

                        </div>


                        <div class="summary-number">

                            <?= number_format($pelangganNonaktif) ?>

                        </div>

                    </div>


                    <div class="summary-item">

                        <div class="summary-icon">

                            <i class="fas fa-user-plus"></i>

                        </div>


                        <div class="summary-content">

                            <h4>
                                Customer Baru
                            </h4>

                            <span>
                                Terdaftar bulan ini
                            </span>

                        </div>


                        <div class="summary-number">

                            <?= number_format($customerBaru) ?>

                        </div>

                    </div>


                </div>


            </div>


            <!-- =================================================
             CUSTOMER TABLE
        ================================================= -->

            <section class="panel table-panel">


                <div class="panel-header">

                    <div>

                        <h2>
                            Daftar Pelanggan
                        </h2>

                    </div>


                    <a href="customers.php">

                        Semua pelanggan

                    </a>

                </div>


                <!-- =================================================
                 FILTER
            ================================================= -->

                <form
                    method="GET"
                    class="filter-box">


                    <div class="search-box">

                        <i class="fas fa-search"></i>

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Cari nama, username, atau email...">

                    </div>


                    <select
                        name="status"
                        class="filter-select"
                        onchange="this.form.submit();">


                        <option value="">

                            Semua Status

                        </option>


                        <option
                            value="aktif"
                            <?= $statusFilter === 'aktif'
                                ? 'selected'
                                : '' ?>>

                            Aktif

                        </option>


                        <option
                            value="nonaktif"
                            <?= $statusFilter === 'nonaktif'
                                ? 'selected'
                                : '' ?>>

                            Nonaktif

                        </option>


                        <option
                            value="pending"
                            <?= $statusFilter === 'pending'
                                ? 'selected'
                                : '' ?>>

                            Pending

                        </option>


                    </select>


                    <button
                        type="submit"
                        class="btn">

                        <i class="fas fa-search"></i>

                        Cari

                    </button>


                    <?php if (
                        $search !== ''
                        ||
                        $statusFilter !== ''
                    ): ?>


                        <a
                            href="customers.php"
                            class="btn">

                            <i class="fas fa-xmark"></i>

                            Reset

                        </a>


                    <?php endif; ?>


                </form>


                <!-- =================================================
                 TABLE
            ================================================= -->

                <div class="table-wrapper">


                    <?php if (!empty($customers)): ?>


                        <table>


                            <thead>

                                <tr>

                                    <th>
                                        PELANGGAN
                                    </th>

                                    <th>
                                        EMAIL
                                    </th>

                                    <th>
                                        STATUS
                                    </th>

                                    <th>
                                        BERGABUNG
                                    </th>

                                    <th>
                                        AKSI
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach ($customers as $customer): ?>


                                    <?php

                                    $customerName =
                                        $customer['full_name']
                                        ?:
                                        'Customer';

                                    $avatar =
                                        urlencode(
                                            $customerName
                                        );

                                    $customerStatus =
                                        strtolower(
                                            trim(
                                                $customer['status']
                                                    ??
                                                    'aktif'
                                            )
                                        );

                                    ?>


                                    <tr>


                                        <!-- CUSTOMER -->

                                        <td>

                                            <div class="customer-info">


                                                <img
                                                    src="https://ui-avatars.com/api/?name=<?= $avatar ?>&background=443&color=fff"
                                                    class="customer-avatar"
                                                    alt="<?= e($customerName) ?>">


                                                <div>

                                                    <div class="customer-name">

                                                        <?= e(
                                                            $customerName
                                                        ) ?>

                                                    </div>


                                                    <div class="customer-username">

                                                        @<?= e(
                                                                $customer['username']
                                                                    ??
                                                                    '-'
                                                            ) ?>

                                                    </div>

                                                </div>


                                            </div>

                                        </td>


                                        <!-- EMAIL -->

                                        <td>

                                            <span class="customer-email">

                                                <?= e(
                                                    $customer['email']
                                                        ??
                                                        '-'
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- STATUS -->

                                        <td>


                                            <span
                                                class="status <?= e(
                                                                    statusClass(
                                                                        $customerStatus
                                                                    )
                                                                ) ?>">


                                                <?php if (
                                                    $customerStatus
                                                    ===
                                                    'aktif'
                                                ): ?>


                                                    <i class="fas fa-circle-check"></i>

                                                    Aktif


                                                <?php elseif (
                                                    $customerStatus
                                                    ===
                                                    'nonaktif'
                                                ): ?>


                                                    <i class="fas fa-circle-xmark"></i>

                                                    Nonaktif


                                                <?php else: ?>


                                                    <i class="fas fa-clock"></i>

                                                    <?= e(
                                                        ucfirst(
                                                            $customerStatus
                                                        )
                                                    ) ?>


                                                <?php endif; ?>


                                            </span>


                                        </td>


                                        <!-- TANGGAL -->

                                        <td>

                                            <?=
                                            !empty($customer['created_at'])
                                                ? date(
                                                    'd M Y',
                                                    strtotime(
                                                        $customer['created_at']
                                                    )
                                                )
                                                : '-'
                                            ?>

                                        </td>


                                        <!-- AKSI -->

                                        <td>


                                            <div class="action-buttons">


                                                <a
                                                    href="customers_detail.php?id=<?= (int)$customer['id'] ?>"
                                                    class="action-btn"
                                                    title="Lihat Detail">

                                                    <i class="fas fa-eye"></i>

                                                </a>


                                                <a
                                                    href="customers_edit.php?id=<?= (int)$customer['id'] ?>"
                                                    class="action-btn"
                                                    title="Edit Pelanggan">

                                                    <i class="fas fa-pen"></i>

                                                </a>


                                            </div>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    <?php else: ?>


                        <div class="empty">


                            <i class="fas fa-users-slash"></i>


                            Tidak ada data pelanggan.


                            <?php if (
                                $search !== ''
                                ||
                                $statusFilter !== ''
                            ): ?>

                                <br><br>

                                <a
                                    href="customers.php"
                                    class="btn">

                                    <i class="fas fa-refresh"></i>

                                    Tampilkan Semua

                                </a>

                            <?php endif; ?>


                        </div>


                    <?php endif; ?>


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


        if (
            menuBtn &&
            sidebar
        ) {

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
           CLOSE SIDEBAR SAAT MENU DIKLIK
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
    </script>


</body>

</html>
