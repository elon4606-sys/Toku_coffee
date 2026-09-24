<?php

require_once "config/koneksi.php";
require_once "config/session.php";

requireRole(['admin', 'staff']);

/* =========================================================
   FUNGSI BANTUAN
========================================================= */

function rupiahOrder($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function eOrder($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function statusClassOrder($status)
{
    return strtolower(str_replace(' ', '-', trim($status)));
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
   STATISTIK PESANAN
========================================================= */

$totalPesanan = 0;
$pesananMenunggu = 0;
$pesananDiproses = 0;
$pesananSelesai = 0;
$totalPenjualan = 0;


/* Total semua pesanan */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM pesanan
");

if ($result) {
    $totalPesanan = (int)$result->fetch_assoc()['total'];
}


/* Pesanan menunggu */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM pesanan
    WHERE status = 'Menunggu'
");

if ($result) {
    $pesananMenunggu = (int)$result->fetch_assoc()['total'];
}


/* Pesanan diproses */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM pesanan
    WHERE status = 'Diproses'
");

if ($result) {
    $pesananDiproses = (int)$result->fetch_assoc()['total'];
}


/* Pesanan selesai */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM pesanan
    WHERE status = 'Selesai'
");

if ($result) {
    $pesananSelesai = (int)$result->fetch_assoc()['total'];
}


/* Total penjualan */

$result = $conn->query("
    SELECT COALESCE(SUM(total), 0) AS total
    FROM pesanan
    WHERE status != 'Dibatalkan'
");

if ($result) {
    $totalPenjualan = (float)$result->fetch_assoc()['total'];
}


/* =========================================================
   UPDATE STATUS PESANAN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {

        $pesananId = (int)($_POST['pesanan_id'] ?? 0);
        $statusBaru = trim($_POST['status'] ?? '');

        $statusValid = [
            'Menunggu',
            'Diproses',
            'Dikirim',
            'Selesai',
            'Dibatalkan'
        ];

        if ($pesananId <= 0) {

            header("Location: orders.php?error=ID pesanan tidak valid");
            exit;
        }

        if (!in_array($statusBaru, $statusValid, true)) {

            header("Location: orders.php?error=Status pesanan tidak valid");
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE pesanan
            SET status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "si",
                $statusBaru,
                $pesananId
            );

            if ($stmt->execute()) {

                header("Location: orders.php?success=Status pesanan berhasil diperbarui");
                exit;
            } else {

                header("Location: orders.php?error=Gagal memperbarui status pesanan");
                exit;
            }
        } else {

            header("Location: orders.php?error=Query update tidak dapat diproses");
            exit;
        }
    }
}


/* =========================================================
   FILTER DATA
========================================================= */

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');


/* =========================================================
   QUERY PESANAN
========================================================= */

$orders = [];

$sql = "
    SELECT
        p.id,
        p.invoice,
        p.user_id,
        p.tanggal_pesanan,
        p.total,
        p.status,
        p.alamat_pengiriman,
        p.catatan,
        u.full_name AS customer,
        u.email
    FROM pesanan p
    LEFT JOIN users u
        ON p.user_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";


/* Search */

if ($search !== '') {

    $sql .= "
        AND (
            p.invoice LIKE ?
            OR u.full_name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $searchLike = '%' . $search . '%';

    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;

    $types .= "sss";
}


/* Status */

if ($statusFilter !== '') {

    $sql .= " AND p.status = ? ";

    $params[] = $statusFilter;

    $types .= "s";
}


$sql .= "
    ORDER BY p.tanggal_pesanan DESC, p.id DESC
";


$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $orders[] = $row;
    }
}


/* =========================================================
   NOTIFIKASI
========================================================= */

$notificationCount = $pesananMenunggu;


/* =========================================================
   PESAN FLASH
========================================================= */

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Pesanan - Toku Coffee ERP
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
            href="orders.php"
            class="active">

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


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="topbar-left">


                <i
                    class="fas fa-bars"
                    id="menu-btn">
                </i>


                <div class="page-title">

                    <h2>
                        Pesanan
                    </h2>

                    <p>
                        Kelola pesanan Toku Coffee ERP
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

                            <?= eOrder($namaUser) ?>

                        </h4>

                        <p>

                            <?= eOrder(ucfirst($roleUser)) ?>

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

                    <?= eOrder($successMessage) ?>

                </div>

            <?php endif; ?>


            <?php if ($errorMessage): ?>

                <div class="flash error">

                    <i class="fas fa-circle-exclamation"></i>

                    <?= eOrder($errorMessage) ?>

                </div>

            <?php endif; ?>


            <!-- PAGE ACTION -->

            <div class="page-actions">

                <button
                    class="btn"
                    onclick="window.print()">

                    <i class="fas fa-print"></i>

                    Cetak Laporan

                </button>

            </div>


            <!-- =================================================
             STATISTICS
        ================================================= -->

            <div class="stats">


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

                            <i class="fas fa-database"></i>

                            Semua pesanan

                        </small>

                    </div>

                </div>


                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-clock"></i>

                    </div>


                    <div>

                        <h3>

                            <?= number_format($pesananMenunggu) ?>

                        </h3>

                        <p>
                            Menunggu
                        </p>

                        <small>

                            <i class="fas fa-hourglass-half"></i>

                            Perlu diproses

                        </small>

                    </div>

                </div>


                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-gears"></i>

                    </div>


                    <div>

                        <h3>

                            <?= number_format($pesananDiproses) ?>

                        </h3>

                        <p>
                            Diproses
                        </p>

                        <small>

                            <i class="fas fa-arrows-rotate"></i>

                            Sedang dikerjakan

                        </small>

                    </div>

                </div>


                <div class="stat">

                    <div class="stat-icon">

                        <i class="fas fa-circle-check"></i>

                    </div>


                    <div>

                        <h3>

                            <?= number_format($pesananSelesai) ?>

                        </h3>

                        <p>
                            Selesai
                        </p>

                        <small>

                            <i class="fas fa-check"></i>

                            Pesanan selesai

                        </small>

                    </div>

                </div>


            </div>


            <!-- =================================================
             PESANAN
        ================================================= -->

            <section class="panel">


                <div class="panel-header">

                    <h2>
                        Daftar Pesanan
                    </h2>

                    <span style="
                    font-size:1.2rem;
                    color:#888;
                ">

                        <?= number_format(count($orders)) ?>
                        data ditemukan

                    </span>

                </div>


                <!-- FILTER -->

                <form
                    method="GET"
                    class="filter-box">


                    <div class="input-group">

                        <i class="fas fa-search"></i>

                        <input
                            type="text"
                            name="search"
                            class="input-control"
                            placeholder="Cari invoice, nama pelanggan, atau email..."
                            value="<?= eOrder($search) ?>">

                    </div>


                    <select
                        name="status"
                        class="select-control">

                        <option value="">
                            Semua Status
                        </option>

                        <option
                            value="Menunggu"
                            <?= $statusFilter === 'Menunggu' ? 'selected' : '' ?>>

                            Menunggu

                        </option>

                        <option
                            value="Diproses"
                            <?= $statusFilter === 'Diproses' ? 'selected' : '' ?>>

                            Diproses

                        </option>

                        <option
                            value="Dikirim"
                            <?= $statusFilter === 'Dikirim' ? 'selected' : '' ?>>

                            Dikirim

                        </option>

                        <option
                            value="Selesai"
                            <?= $statusFilter === 'Selesai' ? 'selected' : '' ?>>

                            Selesai

                        </option>

                        <option
                            value="Dibatalkan"
                            <?= $statusFilter === 'Dibatalkan' ? 'selected' : '' ?>>

                            Dibatalkan

                        </option>

                    </select>


                    <button
                        type="submit"
                        class="filter-btn">

                        <i class="fas fa-filter"></i>

                        Filter

                    </button>


                </form>


                <!-- TABLE -->

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

                                <th>
                                    Aksi
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (!empty($orders)): ?>


                                <?php foreach ($orders as $order): ?>


                                    <tr>


                                        <!-- INVOICE -->

                                        <td>

                                            <span class="invoice">

                                                <?= eOrder(
                                                    $order['invoice']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- CUSTOMER -->

                                        <td>

                                            <span class="customer-name">

                                                <?= eOrder(
                                                    $order['customer']
                                                        ?: 'Customer'
                                                ) ?>

                                            </span>


                                            <?php if (!empty($order['email'])): ?>

                                                <span class="customer-email">

                                                    <?= eOrder(
                                                        $order['email']
                                                    ) ?>

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- DATE -->

                                        <td>

                                            <?= eOrder(
                                                date(
                                                    'd M Y H:i',
                                                    strtotime(
                                                        $order['tanggal_pesanan']
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <!-- TOTAL -->

                                        <td>

                                            <span class="order-total">

                                                <?= rupiahOrder(
                                                    $order['total']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="status <?= eOrder(
                                                                    statusClassOrder(
                                                                        $order['status']
                                                                    )
                                                                ) ?>">

                                                <?= eOrder(
                                                    $order['status']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- ACTION -->

                                        <td>

                                            <div class="action-group">


                                                <button
                                                    type="button"
                                                    class="action-btn"
                                                    title="Detail"
                                                    onclick='showOrder(
                                                    <?= json_encode(
                                                        $order,
                                                        JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_QUOT |
                                                            JSON_HEX_AMP
                                                    ) ?>
                                                )'>

                                                    <i class="fas fa-eye"></i>

                                                </button>


                                                <button
                                                    type="button"
                                                    class="action-btn"
                                                    title="Ubah Status"
                                                    onclick='showStatus(
                                                    <?= json_encode(
                                                        $order,
                                                        JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_QUOT |
                                                            JSON_HEX_AMP
                                                    ) ?>
                                                )'>

                                                    <i class="fas fa-pen"></i>

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

                                        <i class="fas fa-receipt"></i>

                                        Belum ada pesanan yang ditemukan.

                                    </td>

                                </tr>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </section>


        </section>


    </main>


    <!-- =====================================================
     MODAL DETAIL
===================================================== -->

    <div
        class="modal"
        id="detailModal">


        <div class="modal-box">


            <div class="modal-header">

                <h2>
                    Detail Pesanan
                </h2>

                <button
                    type="button"
                    class="close-modal"
                    onclick="closeModal('detailModal')">

                    <i class="fas fa-xmark"></i>

                </button>

            </div>


            <div class="detail-list">


                <div class="detail-item">

                    <strong>
                        Invoice
                    </strong>

                    <span id="detailInvoice">
                        -
                    </span>

                </div>


                <div class="detail-item">

                    <strong>
                        Pelanggan
                    </strong>

                    <span id="detailCustomer">
                        -
                    </span>

                </div>


                <div class="detail-item">

                    <strong>
                        Email
                    </strong>

                    <span id="detailEmail">
                        -
                    </span>

                </div>


                <div class="detail-item">

                    <strong>
                        Tanggal Pesanan
                    </strong>

                    <span id="detailDate">
                        -
                    </span>

                </div>


                <div class="detail-item">

                    <strong>
                        Total
                    </strong>

                    <span id="detailTotal">
                        -
                    </span>

                </div>


                <div class="detail-item">

                    <strong>
                        Alamat Pengiriman
                    </strong>

                    <span id="detailAddress">
                        -
                    </span>

                </div>


                <div class="detail-item">

                    <strong>
                        Catatan
                    </strong>

                    <span id="detailNote">
                        -
                    </span>

                </div>


                <div class="detail-item">

                    <strong>
                        Status
                    </strong>

                    <span id="detailStatus">
                        -
                    </span>

                </div>


            </div>


        </div>


    </div>


    <!-- =====================================================
     MODAL STATUS
===================================================== -->

    <div
        class="modal"
        id="statusModal">


        <div class="modal-box">


            <div class="modal-header">

                <h2>
                    Ubah Status Pesanan
                </h2>

                <button
                    type="button"
                    class="close-modal"
                    onclick="closeModal('statusModal')">

                    <i class="fas fa-xmark"></i>

                </button>

            </div>


            <form
                method="POST"
                class="status-form">


                <input
                    type="hidden"
                    name="action"
                    value="update_status">


                <input
                    type="hidden"
                    name="pesanan_id"
                    id="statusOrderId">


                <div class="detail-item">

                    <strong>
                        Invoice
                    </strong>

                    <span id="statusInvoice">
                        -
                    </span>

                </div>


                <br>


                <label>
                    Status Pesanan
                </label>


                <select
                    name="status"
                    id="statusSelect"
                    required>


                    <option value="Menunggu">
                        Menunggu
                    </option>

                    <option value="Diproses">
                        Diproses
                    </option>

                    <option value="Dikirim">
                        Dikirim
                    </option>

                    <option value="Selesai">
                        Selesai
                    </option>

                    <option value="Dibatalkan">
                        Dibatalkan
                    </option>


                </select>


                <div class="modal-actions">


                    <button
                        type="button"
                        class="btn"
                        onclick="closeModal('statusModal')">

                        Batal

                    </button>


                    <button
                        type="submit"
                        class="btn">

                        <i class="fas fa-save"></i>

                        Simpan Status

                    </button>


                </div>


            </form>


        </div>


    </div>


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


        /* =====================================================
           TUTUP SIDEBAR SAAT MENU DIKLIK
        ===================================================== */

        document
            .querySelectorAll('.sidebar a')
            .forEach(function(link) {

                link.addEventListener(
                    'click',
                    function() {

                        if (window.innerWidth <= 900) {

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


        /* =====================================================
           CLOSE SIDEBAR KETIKA KLIK DI LUAR
        ===================================================== */

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


        /* =====================================================
           DETAIL PESANAN
        ===================================================== */

        function showOrder(order) {

            document.getElementById(
                    'detailInvoice'
                ).textContent =
                order.invoice || '-';


            document.getElementById(
                    'detailCustomer'
                ).textContent =
                order.customer || 'Customer';


            document.getElementById(
                    'detailEmail'
                ).textContent =
                order.email || '-';


            document.getElementById(
                    'detailDate'
                ).textContent =
                order.tanggal_pesanan ?
                new Date(
                    order.tanggal_pesanan
                ).toLocaleString(
                    'id-ID'
                ) :
                '-';


            document.getElementById(
                    'detailTotal'
                ).textContent =
                formatRupiah(
                    order.total
                );


            document.getElementById(
                    'detailAddress'
                ).textContent =
                order.alamat_pengiriman || '-';


            document.getElementById(
                    'detailNote'
                ).textContent =
                order.catatan || '-';


            document.getElementById(
                    'detailStatus'
                ).textContent =
                order.status || '-';


            document
                .getElementById('detailModal')
                .classList.add('active');

        }


        /* =====================================================
           UBAH STATUS
        ===================================================== */

        function showStatus(order) {

            document.getElementById(
                    'statusOrderId'
                ).value =
                order.id;


            document.getElementById(
                    'statusInvoice'
                ).textContent =
                order.invoice || '-';


            document.getElementById(
                    'statusSelect'
                ).value =
                order.status || 'Menunggu';


            document
                .getElementById('statusModal')
                .classList.add('active');

        }


        /* =====================================================
           CLOSE MODAL
        ===================================================== */

        function closeModal(id) {

            document
                .getElementById(id)
                .classList.remove('active');

        }


        /* =====================================================
           KLIK AREA LUAR MODAL
        ===================================================== */

        document
            .querySelectorAll('.modal')
            .forEach(function(modal) {

                modal.addEventListener(
                    'click',
                    function(event) {

                        if (event.target === modal) {

                            modal.classList.remove(
                                'active'
                            );

                        }

                    }
                );

            });


        /* =====================================================
           ESC UNTUK CLOSE MODAL
        ===================================================== */

        document.addEventListener(
            'keydown',
            function(event) {

                if (event.key === 'Escape') {

                    document
                        .querySelectorAll('.modal')
                        .forEach(function(modal) {

                            modal.classList.remove(
                                'active'
                            );

                        });

                }

            }
        );


        /* =====================================================
           FORMAT RUPIAH
        ===================================================== */

        function formatRupiah(value) {

            return 'Rp ' +
                Number(value || 0)
                .toLocaleString(
                    'id-ID'
                );

        }
    </script>


</body>

</html>
