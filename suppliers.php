<?php

require_once "config/koneksi.php";
require_once "config/session.php";

requireRole(['admin', 'staff']);

/* =========================================================
   FUNGSI BANTUAN
========================================================= */

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('rupiah')) {
    function rupiah($angka)
    {
        return 'Rp ' . number_format(
            (float)$angka,
            0,
            ',',
            '.'
        );
    }
}

if (!function_exists('statusLabel')) {
    function statusLabel($status)
    {
        $status = strtolower(trim($status ?? ''));

        switch ($status) {
            case 'active':
            case 'aktif':
                return 'Aktif';

            case 'pending':
            case 'menunggu':
                return 'Menunggu';

            case 'inactive':
            case 'nonaktif':
                return 'Nonaktif';

            default:
                return ucfirst($status ?: 'Tidak diketahui');
        }
    }
}

if (!function_exists('statusClass')) {
    function statusClass($status)
    {
        $status = strtolower(trim($status ?? ''));

        switch ($status) {
            case 'active':
            case 'aktif':
                return 'active';

            case 'pending':
            case 'menunggu':
                return 'pending';

            default:
                return 'inactive';
        }
    }
}


/* =========================================================
   INFORMASI USER LOGIN
========================================================= */

$namaUser = $_SESSION['full_name']
    ?? $_SESSION['name']
    ?? $_SESSION['username']
    ?? 'Admin ERP';

$roleUser = $_SESSION['role']
    ?? 'admin';

$avatarName = urlencode($namaUser);


/* =========================================================
   DATA SUPPLIER
========================================================= */

$supplierData = [];

$sqlSupplier = "
    SELECT
        s.id,
        s.nama_supplier,
        s.kontak,
        s.email,
        s.alamat,
        s.status,
        s.created_at,

        COALESCE(p.total_pembelian, 0) AS total_pembelian,
        COALESCE(p.jumlah_transaksi, 0) AS jumlah_transaksi

    FROM supplier s

    LEFT JOIN (
        SELECT
            supplier_id,
            SUM(total) AS total_pembelian,
            COUNT(*) AS jumlah_transaksi
        FROM pembelian
        WHERE status != 'Dibatalkan'
        GROUP BY supplier_id
    ) p
        ON p.supplier_id = s.id

    ORDER BY s.id DESC
";

$resultSupplier = $conn->query($sqlSupplier);

if ($resultSupplier) {

    while ($row = $resultSupplier->fetch_assoc()) {

        $supplierData[] = $row;
    }
}


/* =========================================================
   STATISTIK SUPPLIER
========================================================= */

$totalSupplier = count($supplierData);

$supplierAktif = 0;

$supplierNonaktif = 0;

$totalPembelian = 0;


foreach ($supplierData as $supplier) {

    $status = strtolower(
        trim($supplier['status'] ?? '')
    );

    if (
        $status === 'active' ||
        $status === 'aktif'
    ) {

        $supplierAktif++;
    } else {

        $supplierNonaktif++;
    }

    $totalPembelian +=
        (float)($supplier['total_pembelian'] ?? 0);
}


/* =========================================================
   NOTIFIKASI
========================================================= */

$notificationCount = 0;

$resultNotif = $conn->query("
    SELECT COUNT(*) AS total
    FROM supplier
    WHERE status = 'inactive'
");

if ($resultNotif) {

    $notifRow = $resultNotif->fetch_assoc();

    $notificationCount =
        (int)($notifRow['total'] ?? 0);
}


/* =========================================================
   FLASH MESSAGE
========================================================= */

$successMessage =
    $_GET['success'] ?? '';

$errorMessage =
    $_GET['error'] ?? '';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Supplier - Toku Coffee ERP
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


        .sidebar::-webkit-scrollbar {

            width: .5rem;

        }


        .sidebar::-webkit-scrollbar-thumb {

            background: #ddd;

            border-radius: 1rem;

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

            text-transform:
                uppercase;

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

            min-width: 2rem;

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

            justify-content:
                space-between;

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
           ALERT
        ===================================================== */

        .alert {

            padding:
                1.4rem 1.6rem;

            margin-bottom:
                2rem;

            border-radius:
                var(--border-radius);

            font-size: 1.3rem;

            display: flex;

            align-items: center;

            gap: 1rem;

            animation:
                fadeDown .4s ease;

        }


        .alert-success {

            background:
                #edf7ed;

            color:
                var(--green);

            border:
                .1rem solid #c8e0c8;

        }


        .alert-error {

            background:
                #fff0ef;

            color:
                var(--red);

            border:
                .1rem solid #e7c0bd;

        }


        /* =====================================================
           PAGE ACTION
        ===================================================== */

        .page-actions {

            display: flex;

            justify-content:
                flex-end;

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


        .btn-primary {

            background:
                var(--main-color);

            color: #fff;

        }


        .btn-primary:hover {

            background:
                #6f4e37;

            color: #fff;

        }


        /* =====================================================
           STATS
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


        .stat:nth-child(1) .stat-icon {

            color:
                var(--main-color);

            background:
                #f6f3ed;

        }


        .stat:nth-child(2) .stat-icon {

            color:
                var(--green);

            background:
                #edf7ed;

        }


        .stat:nth-child(3) .stat-icon {

            color:
                var(--red);

            background:
                #fff0ef;

        }


        .stat:nth-child(4) .stat-icon {

            color:
                var(--orange);

            background:
                #fff7e9;

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

            justify-content:
                space-between;

            gap: 1rem;

            margin-bottom: 2rem;

        }


        .panel-header h2 {

            font-size: 1.9rem;

        }


        .panel-header p {

            color: #888;

            font-size: 1.1rem;

            margin-top: .3rem;

        }


        /* =====================================================
           TOOLBAR
        ===================================================== */

        .toolbar {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            gap: 1.5rem;

            margin-bottom: 2rem;

            flex-wrap: wrap;

        }


        .search {

            position: relative;

            width: 32rem;

            max-width: 100%;

        }


        .search i {

            position: absolute;

            left: 1.5rem;

            top: 50%;

            transform:
                translateY(-50%);

            color: #999;

            font-size: 1.5rem;

        }


        .search input {

            width: 100%;

            padding:
                1.2rem 1.5rem 1.2rem 4rem;

            border:
                .15rem solid #ccc;

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            font-size: 1.3rem;

            background: #fff;

        }


        .search input:focus {

            border:
                var(--border);

        }


        .filters {

            display: flex;

            align-items: center;

            gap: 1rem;

            flex-wrap: wrap;

        }


        .filter {

            padding:
                1.1rem 1.4rem;

            border:
                .15rem solid #ccc;

            border-radius:
                var(--border-radius);

            background: #fff;

            color:
                var(--main-color);

            cursor: pointer;

            font-size: 1.2rem;

            min-width: 14rem;

        }


        .filter:focus {

            border:
                var(--border);

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

            min-width: 100rem;

            border-collapse:
                collapse;

        }


        thead tr {

            border-bottom:
                .2rem solid var(--main-color);

        }


        thead {

            background:
                #faf8f1;

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


        /* =====================================================
           SUPPLIER INFO
        ===================================================== */

        .supplier-info {

            display: flex;

            align-items: center;

            gap: 1.2rem;

            min-width: 24rem;

        }


        .supplier-avatar {

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
                .15rem solid var(--main-color);

            border-radius:
                45% 55% 60% 40%;

            font-size: 1.8rem;

            color:
                #6f4e37;

        }


        .supplier-name {

            font-size: 1.3rem;

            font-weight: 600;

        }


        .supplier-id {

            color: #999;

            font-size: 1rem;

            margin-top: .2rem;

        }


        /* =====================================================
           CONTACT
        ===================================================== */

        .contact {

            min-width: 20rem;

        }


        .contact-name {

            font-size: 1.2rem;

            font-weight: 500;

        }


        .contact-detail {

            color: #888;

            font-size: 1rem;

            margin-top: .4rem;

            white-space: nowrap;

        }


        .contact-detail i {

            width: 1.5rem;

            color:
                #6f4e37;

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
           LOCATION
        ===================================================== */

        .location {

            min-width: 20rem;

        }


        .location-name {

            font-size: 1.2rem;

            font-weight: 500;

        }


        .location-name i {

            color:
                #6f4e37;

            margin-right: .3rem;

        }


        .location-detail {

            color: #888;

            font-size: 1rem;

            margin-top: .4rem;

            line-height: 1.6;

            max-width: 25rem;

        }


        /* =====================================================
           PURCHASE
        ===================================================== */

        .purchase {

            min-width: 16rem;

        }


        .purchase strong {

            font-size: 1.3rem;

            font-weight: 600;

        }


        .order-count {

            color: #999;

            font-size: 1rem;

            margin-top: .3rem;

        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status {

            display: inline-flex;

            align-items: center;

            gap: .5rem;

            padding:
                .5rem 1rem;

            border:
                .1rem solid currentColor;

            border-radius:
                var(--border-radius);

            font-size: 1rem;

            white-space: nowrap;

        }


        .status.active {

            color:
                var(--green);

            background:
                #f0f7f0;

        }


        .status.pending {

            color:
                var(--orange);

            background:
                #fff7e9;

        }


        .status.inactive {

            color:
                var(--red);

            background:
                #fff0ef;

        }


        /* =====================================================
           ACTION
        ===================================================== */

        .actions {

            display: flex;

            gap: .5rem;

        }


        .action-btn {

            width: 3.5rem;

            height: 3.5rem;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff;

            color:
                var(--main-color);

            border:
                .1rem solid #ccc;

            border-radius:
                var(--border-radius);

            cursor: pointer;

        }


        .action-btn:hover {

            border:
                .15rem solid var(--main-color);

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

            color:
                #6f4e37;

        }


        .empty h3 {

            font-size: 1.7rem;

            color:
                var(--main-color);

            margin-bottom: .5rem;

        }


        /* =====================================================
           BOTTOM
        ===================================================== */

        .bottom {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            margin-top: 2rem;

            gap: 1.5rem;

            flex-wrap: wrap;

        }


        .showing {

            color: #999;

            font-size: 1.2rem;

        }


        .showing strong {

            color:
                var(--main-color);

        }


        .pagination {

            display: flex;

            gap: .5rem;

        }


        .pagination button {

            width: 3.5rem;

            height: 3.5rem;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff;

            color:
                var(--main-color);

            border:
                .1rem solid #ccc;

            border-radius:
                var(--border-radius);

            cursor: pointer;

        }


        .pagination button:hover,
        .pagination button.active {

            background:
                var(--main-color);

            color: #fff;

            border-color:
                var(--main-color);

        }


        .pagination button:disabled {

            opacity: .4;

            cursor: not-allowed;

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
                transform: scale(1);
            }

            50% {
                transform: scale(1.15);
            }

            100% {
                transform: scale(1);
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

                padding: 2rem;

            }


            .topbar {

                padding:
                    0 2rem;

            }


            .profile-info {

                display: none;

            }


            .toolbar {

                align-items:
                    stretch;

            }


            .search {

                width: 100%;

            }


            .filters {

                width: 100%;

            }


            .filter {

                flex: 1;

            }


            .btn {

                flex: 1;

            }

        }


        /* =====================================================
           RESPONSIVE 700
        ===================================================== */

        @media (max-width: 700px) {

            .stats {

                grid-template-columns:
                    1fr;

            }


            .toolbar {

                flex-direction:
                    column;

            }


            .filters {

                display: grid;

                grid-template-columns:
                    1fr 1fr;

            }


            .filter,
            .btn {

                width: 100%;

            }


            .panel {

                padding: 2rem;

            }

        }


        /* =====================================================
           RESPONSIVE 550
        ===================================================== */

        @media (max-width: 550px) {

            html {

                font-size: 50%;

            }


            .topbar {

                height: 7rem;

                padding:
                    0 1.5rem;

            }


            .content {

                padding:
                    1.5rem;

            }


            .panel {

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


            .filters {

                grid-template-columns:
                    1fr;

            }


            .bottom {

                flex-direction:
                    column;

                align-items:
                    flex-start;

            }


            .pagination {

                width: 100%;

                justify-content:
                    center;

                flex-wrap: wrap;

            }

        }


        /* =====================================================
           OVERLAY MOBILE
        ===================================================== */

        @media (max-width: 900px) {

            body::before {

                content: '';

                position: fixed;

                inset: 0;

                background:
                    rgba(0, 0, 0, .25);

                opacity: 0;

                visibility: hidden;

                z-index: 900;

                transition:
                    all .2s linear;

            }


            body:has(.sidebar.active)::before {

                opacity: 1;

                visibility: visible;

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


        <a
            href="suppliers.php"
            class="active">

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


        <!-- =================================================
         TOPBAR
    ================================================= -->

        <header class="topbar">


            <div class="topbar-left">


                <i
                    class="fas fa-bars"
                    id="menu-btn">
                </i>


                <div class="page-title">

                    <h2>
                        Supplier
                    </h2>

                    <p>
                        Kelola supplier dan pembelian Toku Coffee
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


        <!-- =================================================
         CONTENT
    ================================================= -->

        <section class="content">


            <!-- ALERT -->

            <?php if ($successMessage): ?>

                <div class="alert alert-success">

                    <i class="fas fa-circle-check"></i>

                    <?= e($successMessage) ?>

                </div>

            <?php endif; ?>


            <?php if ($errorMessage): ?>

                <div class="alert alert-error">

                    <i class="fas fa-circle-exclamation"></i>

                    <?= e($errorMessage) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
             ACTION
        ================================================= -->

            <div class="page-actions">

                <button
                    class="btn"
                    type="button"
                    onclick="exportSupplier()">

                    <i class="fas fa-file-export"></i>

                    Export Data

                </button>


                <button
                    class="btn btn-primary"
                    type="button"
                    onclick="addSupplier()">

                    <i class="fas fa-plus"></i>

                    Tambah Supplier

                </button>

            </div>


            <!-- =================================================
             STATISTICS
        ================================================= -->

            <div class="stats">


                <!-- TOTAL -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-truck-field"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($totalSupplier) ?>
                        </h3>

                        <p>
                            Total Supplier
                        </p>

                        <small>

                            <i class="fas fa-database"></i>

                            Data supplier

                        </small>

                    </div>

                </div>


                <!-- AKTIF -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-circle-check"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($supplierAktif) ?>
                        </h3>

                        <p>
                            Supplier Aktif
                        </p>

                        <small>

                            <i class="fas fa-check"></i>

                            Supplier aktif

                        </small>

                    </div>

                </div>


                <!-- NONAKTIF -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-circle-xmark"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($supplierNonaktif) ?>
                        </h3>

                        <p>
                            Supplier Nonaktif
                        </p>

                        <small>

                            <i class="fas fa-triangle-exclamation"></i>

                            Perlu diperiksa

                        </small>

                    </div>

                </div>


                <!-- PEMBELIAN -->

                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-money-bill-trend-up"></i>

                    </div>


                    <div>

                        <h3>
                            <?= rupiah($totalPembelian) ?>
                        </h3>

                        <p>
                            Total Pembelian
                        </p>

                        <small>

                            <i class="fas fa-cart-shopping"></i>

                            Transaksi supplier

                        </small>

                    </div>

                </div>


            </div>


            <!-- =================================================
             SUPPLIER PANEL
        ================================================= -->

            <section class="panel">


                <div class="panel-header">


                    <div>

                        <h2>
                            Daftar Supplier
                        </h2>

                        <p>
                            Kelola informasi supplier Toku Coffee
                        </p>

                    </div>


                </div>


                <!-- =================================================
                 TOOLBAR
            ================================================= -->

                <div class="toolbar">


                    <div class="search">

                        <i class="fas fa-search"></i>

                        <input
                            type="text"
                            id="searchSupplier"
                            placeholder="Cari nama, kontak, email, atau alamat..."
                            autocomplete="off">

                    </div>


                    <div class="filters">


                        <select
                            class="filter"
                            id="statusFilter">

                            <option value="">
                                Semua Status
                            </option>

                            <option value="active">
                                Aktif
                            </option>

                            <option value="inactive">
                                Nonaktif
                            </option>

                            <option value="pending">
                                Menunggu
                            </option>

                        </select>


                    </div>


                </div>


                <!-- =================================================
                 TABLE
            ================================================= -->

                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    SUPPLIER
                                </th>

                                <th>
                                    KONTAK
                                </th>

                                <th>
                                    LOKASI
                                </th>

                                <th>
                                    PEMBELIAN
                                </th>

                                <th>
                                    STATUS
                                </th>

                                <th>
                                    AKSI
                                </th>

                            </tr>

                        </thead>


                        <tbody id="supplierTable">


                            <?php if (!empty($supplierData)): ?>


                                <?php foreach ($supplierData as $supplier): ?>


                                    <?php

                                    $status =
                                        strtolower(
                                            trim(
                                                $supplier['status'] ?? ''
                                            )
                                        );

                                    $searchData =
                                        strtolower(
                                            ($supplier['nama_supplier'] ?? '')
                                                . ' '
                                                . ($supplier['kontak'] ?? '')
                                                . ' '
                                                . ($supplier['email'] ?? '')
                                                . ' '
                                                . ($supplier['alamat'] ?? '')
                                        );

                                    $statusCls =
                                        statusClass(
                                            $supplier['status']
                                        );

                                    ?>


                                    <tr
                                        data-search="<?= e($searchData) ?>"
                                        data-status="<?= e($status) ?>">


                                        <!-- SUPPLIER -->

                                        <td>

                                            <div class="supplier-info">


                                                <div class="supplier-avatar">

                                                    <i class="fas fa-building"></i>

                                                </div>


                                                <div>

                                                    <div class="supplier-name">

                                                        <?= e(
                                                            $supplier['nama_supplier']
                                                        ) ?>

                                                    </div>


                                                    <div class="supplier-id">

                                                        ID Supplier:
                                                        #<?= e(
                                                                $supplier['id']
                                                            ) ?>

                                                    </div>

                                                </div>


                                            </div>

                                        </td>


                                        <!-- KONTAK -->

                                        <td>

                                            <div class="contact">


                                                <div class="contact-name">

                                                    <?= !empty($supplier['kontak'])
                                                        ? e(
                                                            $supplier['kontak']
                                                        )
                                                        : '-'
                                                    ?>

                                                </div>


                                                <?php if (
                                                    !empty($supplier['email'])
                                                ): ?>

                                                    <div class="contact-detail">

                                                        <i class="fas fa-envelope"></i>

                                                        <?= e(
                                                            $supplier['email']
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                            </div>

                                        </td>


                                        <!-- LOKASI -->

                                        <td>

                                            <div class="location">


                                                <div class="location-name">

                                                    <i class="fas fa-location-dot"></i>

                                                    Alamat Supplier

                                                </div>


                                                <div class="location-detail">

                                                    <?= !empty($supplier['alamat'])
                                                        ? e(
                                                            $supplier['alamat']
                                                        )
                                                        : '-'
                                                    ?>

                                                </div>


                                            </div>

                                        </td>


                                        <!-- PEMBELIAN -->

                                        <td>

                                            <div class="purchase">


                                                <strong>

                                                    <?= rupiah(
                                                        $supplier['total_pembelian']
                                                    ) ?>

                                                </strong>


                                                <div class="order-count">

                                                    <?= number_format(
                                                        (int)$supplier['jumlah_transaksi']
                                                    ) ?>

                                                    transaksi pembelian

                                                </div>


                                            </div>

                                        </td>


                                        <!-- STATUS -->

                                        <td>


                                            <span
                                                class="status <?= e($statusCls) ?>">


                                                <?php if (
                                                    $statusCls === 'active'
                                                ): ?>

                                                    <i class="fas fa-circle-check"></i>

                                                <?php elseif (
                                                    $statusCls === 'pending'
                                                ): ?>

                                                    <i class="fas fa-clock"></i>

                                                <?php else: ?>

                                                    <i class="fas fa-circle-xmark"></i>

                                                <?php endif; ?>


                                                <?= e(
                                                    statusLabel(
                                                        $supplier['status']
                                                    )
                                                ) ?>


                                            </span>


                                        </td>


                                        <!-- AKSI -->

                                        <td>


                                            <div class="actions">


                                                <button
                                                    class="action-btn"
                                                    type="button"
                                                    title="Detail Supplier"
                                                    onclick="viewSupplier(<?= (int)$supplier['id'] ?>)">

                                                    <i class="fas fa-eye"></i>

                                                </button>


                                                <button
                                                    class="action-btn"
                                                    type="button"
                                                    title="Edit Supplier"
                                                    onclick="editSupplier(<?= (int)$supplier['id'] ?>)">

                                                    <i class="fas fa-pen"></i>

                                                </button>


                                                <button
                                                    class="action-btn"
                                                    type="button"
                                                    title="Pembelian Supplier"
                                                    onclick="viewPurchases(<?= (int)$supplier['id'] ?>)">

                                                    <i class="fas fa-cart-shopping"></i>

                                                </button>


                                            </div>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td
                                        colspan="6"
                                        class="empty">


                                        <i class="fas fa-truck"></i>


                                        <h3>
                                            Belum Ada Supplier
                                        </h3>


                                        Data supplier belum tersedia di database.


                                    </td>

                                </tr>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


                <!-- =================================================
                 BOTTOM
            ================================================= -->

                <div class="bottom">


                    <div class="showing">

                        Menampilkan

                        <strong id="visibleCount">
                            <?= count($supplierData) ?>
                        </strong>

                        dari

                        <strong>
                            <?= count($supplierData) ?>
                        </strong>

                        supplier

                    </div>


                    <div
                        class="pagination"
                        id="pagination">
                    </div>


                </div>


            </section>


        </section>


    </main>


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
                    window.innerWidth > 900
                ) {
                    return;
                }


                if (
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


        /* =========================================================
           FILTER SUPPLIER
        ========================================================= */

        const searchInput =
            document.getElementById(
                'searchSupplier'
            );

        const statusFilter =
            document.getElementById(
                'statusFilter'
            );

        const supplierTable =
            document.getElementById(
                'supplierTable'
            );

        const visibleCount =
            document.getElementById(
                'visibleCount'
            );

        const pagination =
            document.getElementById(
                'pagination'
            );


        const allRows =
            Array.from(
                supplierTable.querySelectorAll(
                    'tr[data-search]'
                )
            );


        let currentPage = 1;

        const perPage = 8;


        /* =========================================================
           FILTER DATA
        ========================================================= */

        function getFilteredRows() {

            const search =
                (
                    searchInput.value || ''
                )
                .toLowerCase()
                .trim();


            const status =
                (
                    statusFilter.value || ''
                )
                .toLowerCase();


            return allRows.filter(
                function(row) {

                    const rowSearch =
                        (
                            row.dataset.search || ''
                        )
                        .toLowerCase();


                    const rowStatus =
                        (
                            row.dataset.status || ''
                        )
                        .toLowerCase();


                    const searchMatch =
                        rowSearch.includes(
                            search
                        );


                    const statusMatch =
                        status === '' ||
                        rowStatus === status;


                    return (
                        searchMatch &&
                        statusMatch
                    );

                }
            );

        }


        /* =========================================================
           RENDER TABLE
        ========================================================= */

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
                currentPage >
                totalPages
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
                (
                    currentPage - 1
                ) * perPage;


            const end =
                start + perPage;


            const pageRows =
                filteredRows.slice(
                    start,
                    end
                );


            pageRows.forEach(
                function(row) {

                    row.style.display =
                        '';

                }
            );


            visibleCount.textContent =
                total;


            renderPagination(
                totalPages
            );

        }


        /* =========================================================
           PAGINATION
        ========================================================= */

        function renderPagination(
            totalPages
        ) {

            pagination.innerHTML =
                '';


            if (
                totalPages <= 1
            ) {

                return;

            }


            const prev =
                document.createElement(
                    'button'
                );


            prev.type =
                'button';


            prev.innerHTML =
                '<i class="fas fa-chevron-left"></i>';


            prev.disabled =
                currentPage === 1;


            prev.onclick =
                function() {

                    if (
                        currentPage > 1
                    ) {

                        currentPage--;

                        renderTable();

                    }

                };


            pagination.appendChild(
                prev
            );


            for (
                let i = 1; i <= totalPages; i++
            ) {

                const button =
                    document.createElement(
                        'button'
                    );


                button.type =
                    'button';


                button.textContent =
                    i;


                if (
                    i === currentPage
                ) {

                    button.classList.add(
                        'active'
                    );

                }


                button.onclick =
                    function() {

                        currentPage =
                            i;

                        renderTable();

                    };


                pagination.appendChild(
                    button
                );

            }


            const next =
                document.createElement(
                    'button'
                );


            next.type =
                'button';


            next.innerHTML =
                '<i class="fas fa-chevron-right"></i>';


            next.disabled =
                currentPage === totalPages;


            next.onclick =
                function() {

                    if (
                        currentPage <
                        totalPages
                    ) {

                        currentPage++;

                        renderTable();

                    }

                };


            pagination.appendChild(
                next
            );

        }


        /* =========================================================
           SEARCH
        ========================================================= */

        if (searchInput) {

            searchInput.addEventListener(
                'input',
                function() {

                    currentPage = 1;

                    renderTable();

                }
            );

        }


        /* =========================================================
           STATUS FILTER
        ========================================================= */

        if (statusFilter) {

            statusFilter.addEventListener(
                'change',
                function() {

                    currentPage = 1;

                    renderTable();

                }
            );

        }


        /* =========================================================
           DETAIL
        ========================================================= */

        function viewSupplier(id) {

            if (!id) {
                return;
            }


            window.location.href =
                'supplier_detail.php?id=' +
                encodeURIComponent(id);

        }


        /* =========================================================
           EDIT
        ========================================================= */

        function editSupplier(id) {

            if (!id) {
                return;
            }


            window.location.href =
                'supplier_edit.php?id=' +
                encodeURIComponent(id);

        }


        /* =========================================================
           PEMBELIAN
        ========================================================= */

        function viewPurchases(id) {

            if (!id) {
                return;
            }


            window.location.href =
                'pembelian.php?supplier_id=' +
                encodeURIComponent(id);

        }


        /* =========================================================
           TAMBAH SUPPLIER
        ========================================================= */

        function addSupplier() {

            window.location.href =
                'supplier_tambah.php';

        }


        /* =========================================================
           EXPORT CSV
        ========================================================= */

        function exportSupplier() {

            const rows =
                getFilteredRows();


            if (
                rows.length === 0
            ) {

                alert(
                    'Tidak ada data supplier untuk diekspor.'
                );

                return;

            }


            let csv =
                'ID,Nama Supplier,Kontak,Email,Alamat,Status\n';


            rows.forEach(
                function(row) {

                    const cells =
                        row.querySelectorAll(
                            'td'
                        );


                    if (
                        cells.length < 6
                    ) {

                        return;

                    }


                    const id =
                        cells[0]
                        .querySelector(
                            '.supplier-id'
                        )
                        ?.textContent
                        .replace(
                            'ID Supplier:',
                            ''
                        )
                        .trim() || '';


                    const name =
                        cells[0]
                        .querySelector(
                            '.supplier-name'
                        )
                        ?.textContent
                        .trim() || '';


                    const contact =
                        cells[1]
                        .querySelector(
                            '.contact-name'
                        )
                        ?.textContent
                        .trim() || '';


                    const email =
                        cells[1]
                        .querySelector(
                            '.contact-detail'
                        )
                        ?.textContent
                        .trim() || '';


                    const address =
                        cells[2]
                        .querySelector(
                            '.location-detail'
                        )
                        ?.textContent
                        .trim() || '';


                    const status =
                        cells[4]
                        .querySelector(
                            '.status'
                        )
                        ?.textContent
                        .trim() || '';


                    csv += [

                            id,
                            name,
                            contact,
                            email,
                            address,
                            status

                        ]
                        .map(
                            function(value) {

                                return '"' +
                                    value
                                    .replace(
                                        /"/g,
                                        '""'
                                    ) +
                                    '"';

                            }
                        )
                        .join(',') +
                        '\n';

                }
            );


            const blob =
                new Blob(
                    [csv], {
                        type: 'text/csv;charset=utf-8;'
                    }
                );


            const url =
                URL.createObjectURL(
                    blob
                );


            const link =
                document.createElement(
                    'a'
                );


            link.href =
                url;


            link.download =
                'supplier_toku_coffee.csv';


            document.body.appendChild(
                link
            );


            link.click();


            document.body.removeChild(
                link
            );


            URL.revokeObjectURL(
                url
            );

        }


        /* =========================================================
           INITIAL LOAD
        ========================================================= */

        renderTable();


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
                                'translateY(-1rem)';


                            setTimeout(
                                function() {

                                    alert.remove();

                                },
                                300
                            );

                        }
                    );

            },
            5000
        );
    </script>


</body>

</html>
