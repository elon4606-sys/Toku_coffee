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

            $value =
                array_values($row)[0];

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
   INFORMASI USER LOGIN
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

$avatarName =
    urlencode(
        $namaUser
    );


/* =========================================================
   CUSTOMER ID
========================================================= */

$customerId =
    isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;


if ($customerId <= 0) {

    header(
        "Location: customers.php?error=customer_tidak_ditemukan"
    );

    exit;
}


/* =========================================================
   DATA CUSTOMER
========================================================= */

$stmt =
    $conn->prepare("
        SELECT
            id,
            full_name,
            username,
            email,
            status,
            failed_attempts,
            created_at,
            updated_at
        FROM users
        WHERE id = ?
        AND role = 'customer'
        LIMIT 1
    ");


if (!$stmt) {

    die("Query customer gagal: "
        .
        e($conn->error));
}


$stmt->bind_param(
    "i",
    $customerId
);

$stmt->execute();

$result =
    $stmt->get_result();

$customer =
    $result->fetch_assoc();

$stmt->close();


if (!$customer) {

    header(
        "Location: customers.php?error=customer_tidak_ditemukan"
    );

    exit;
}


/* =========================================================
   STATISTIK CUSTOMER
========================================================= */

/* Total pesanan */

$totalPesanan =
    (int)queryValue(
        $conn,
        "
        SELECT COUNT(*)
        FROM pesanan
        WHERE user_id = $customerId
        "
    );


/* Pesanan selesai */

$pesananSelesai =
    (int)queryValue(
        $conn,
        "
        SELECT COUNT(*)
        FROM pesanan
        WHERE user_id = $customerId
        AND status = 'Selesai'
        "
    );


/* Pesanan diproses */

$pesananDiproses =
    (int)queryValue(
        $conn,
        "
        SELECT COUNT(*)
        FROM pesanan
        WHERE user_id = $customerId
        AND status NOT IN (
            'Selesai',
            'Dibatalkan'
        )
        "
    );


/* Pesanan dibatalkan */

$pesananDibatalkan =
    (int)queryValue(
        $conn,
        "
        SELECT COUNT(*)
        FROM pesanan
        WHERE user_id = $customerId
        AND status = 'Dibatalkan'
        "
    );


/* Total transaksi */

$totalBelanja =
    (float)queryValue(
        $conn,
        "
        SELECT COALESCE(
            SUM(total),
            0
        )
        FROM pesanan
        WHERE user_id = $customerId
        AND status != 'Dibatalkan'
        "
    );


/* =========================================================
   PESANAN TERBARU
========================================================= */

$pesananTerbaru = [];

$stmtPesanan =
    $conn->prepare("
        SELECT
            id,
            total,
            status,
            created_at
        FROM pesanan
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 5
    ");


if ($stmtPesanan) {

    $stmtPesanan->bind_param(
        "i",
        $customerId
    );

    $stmtPesanan->execute();

    $resultPesanan =
        $stmtPesanan->get_result();

    while (
        $row =
        $resultPesanan->fetch_assoc()
    ) {

        $pesananTerbaru[] =
            $row;
    }

    $stmtPesanan->close();
}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

$success =
    $_GET['success']
    ??
    '';

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Detail Pelanggan - Toku Coffee ERP
    </title>


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


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <style>
        :root {

            --main-color:
                #443;

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

        }


        .sidebar a i {

            width:
                2rem;

            font-size:
                1.7rem;

            text-align:
                center;

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


        .page-actions {

            display:
                flex;

            gap:
                .8rem;

            flex-wrap:
                wrap;

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


        /* =====================================================
           ALERT
        ===================================================== */

        .alert {

            padding:
                1.4rem 1.7rem;

            margin-bottom:
                2rem;

            border:
                .1rem solid;

            border-radius:
                var(--border-radius);

            font-size:
                1.2rem;

        }


        .alert-success {

            color:
                var(--green);

            background:
                #f0f7f0;

            border-color:
                #c8ddc8;

        }


        /* =====================================================
           PROFILE CARD
        ===================================================== */

        .customer-profile {

            background:
                #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

            margin-bottom:
                2rem;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                2rem;

        }


        .customer-profile:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .profile-main {

            display:
                flex;

            align-items:
                center;

            gap:
                1.8rem;

        }


        .big-avatar {

            width:
                8rem;

            height:
                8rem;

            border-radius:
                50%;

            border:
                .2rem solid var(--main-color);

        }


        .customer-profile h1 {

            font-size:
                2.2rem;

        }


        .username {

            font-size:
                1.2rem;

            color:
                #999;

            margin-top:
                .3rem;

        }


        .profile-email {

            font-size:
                1.2rem;

            color:
                #777;

            margin-top:
                .5rem;

        }


        .profile-status {

            text-align:
                right;

        }


        .status {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                .5rem;

            padding:
                .6rem 1.2rem;

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


        .customer-id {

            display:
                block;

            font-size:
                1rem;

            color:
                #999;

            margin-top:
                .8rem;

        }


        /* =====================================================
           STAT
        ===================================================== */

        .stats {

            display:
                grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap:
                1.8rem;

            margin-bottom:
                2rem;

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

        }


        .stat:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

            transform:
                translateY(-.4rem);

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


        .stat h3 {

            font-size:
                1.9rem;

        }


        .stat p {

            font-size:
                1.1rem;

            color:
                #888;

        }


        /* =====================================================
           GRID
        ===================================================== */

        .detail-grid {

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                2rem;

        }


        .panel {

            background:
                #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

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

            margin-bottom:
                2rem;

        }


        .panel-header h2 {

            font-size:
                1.8rem;

        }


        .panel-header i {

            font-size:
                1.8rem;

        }


        /* =====================================================
           INFO ROW
        ===================================================== */

        .info-list {

            display:
                flex;

            flex-direction:
                column;

            gap:
                1.3rem;

        }


        .info-row {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                2rem;

            padding:
                1.2rem;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

        }


        .info-label {

            display:
                flex;

            align-items:
                center;

            gap:
                .8rem;

            color:
                #888;

            font-size:
                1.1rem;

        }


        .info-label i {

            width:
                2rem;

            text-align:
                center;

        }


        .info-value {

            text-align:
                right;

            font-size:
                1.2rem;

            font-weight:
                500;

            color:
                var(--main-color);

        }


        /* =====================================================
           ORDER TABLE
        ===================================================== */

        .order-table-wrapper {

            width:
                100%;

            overflow-x:
                auto;

        }


        .order-table {

            width:
                100%;

            border-collapse:
                collapse;

            min-width:
                45rem;

        }


        .order-table th {

            text-align:
                left;

            padding:
                1.2rem;

            font-size:
                1.1rem;

            border-bottom:
                .2rem solid var(--main-color);

        }


        .order-table td {

            padding:
                1.2rem;

            border-bottom:
                .1rem solid #eee;

            font-size:
                1.1rem;

        }


        .order-table tr:hover {

            background:
                #faf8f1;

        }


        .order-status {

            display:
                inline-flex;

            padding:
                .5rem .9rem;

            border:
                .1rem solid currentColor;

            border-radius:
                var(--border-radius);

            font-size:
                .9rem;

        }


        .order-status.selesai {

            color:
                var(--green);

        }


        .order-status.dibatalkan {

            color:
                var(--red);

        }


        .order-status.diproses,
        .order-status.pending {

            color:
                var(--orange);

        }


        .order-status.dikirim {

            color:
                var(--blue);

        }


        .empty {

            text-align:
                center;

            padding:
                3rem;

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
           SECURITY
        ===================================================== */

        .security-box {

            margin-top:
                2rem;

            padding:
                1.5rem;

            background:
                #faf8f1;

            border:
                .1rem dashed var(--main-color);

            border-radius:
                var(--border-radius);

        }


        .security-box h3 {

            font-size:
                1.3rem;

            margin-bottom:
                1rem;

        }


        .security-item {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                .8rem 0;

            font-size:
                1.1rem;

        }


        .security-item span:last-child {

            font-weight:
                600;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1200px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 1000px) {

            .detail-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 900px) {

            .sidebar {

                left:
                    -27rem;

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

        }


        @media (max-width: 650px) {

            .customer-profile {

                flex-direction:
                    column;

                align-items:
                    flex-start;

            }


            .profile-status {

                text-align:
                    left;

            }


            .page-header {

                flex-direction:
                    column;

                align-items:
                    flex-start;

            }

        }


        @media (max-width: 550px) {

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


            .stats {

                grid-template-columns:
                    1fr;

            }


            .profile-info {

                display:
                    none;

            }


            .customer-profile {

                padding:
                    1.5rem;

            }


            .panel {

                padding:
                    1.5rem;

            }

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
    </style>

</head>


<body>


    <!-- =========================================================
     SIDEBAR
========================================================= -->

    <aside
        class="sidebar"
        id="sidebar">


        <a
            href="dashboard.php"
            class="logo">

            <i class="fas fa-mug-hot"></i>

            TOKU COFFEE

        </a>


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


        <a href="../REKAYASA_E_BISNIS/produk/products.php">

            <i class="fas fa-box"></i>

            Produk

        </a>


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


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="topbar-left">


                <i
                    class="fas fa-bars"
                    id="menu-btn">
                </i>


                <div class="page-title">

                    <h2>
                        Detail Pelanggan
                    </h2>

                    <p>
                        Informasi lengkap pelanggan Toku Coffee
                    </p>

                </div>


            </div>


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


        </header>


        <!-- =====================================================
         CONTENT
    ===================================================== -->

        <section class="content">


            <!-- =================================================
             SUCCESS
        ================================================= -->

            <?php if ($success === 'updated'): ?>

                <div class="alert alert-success">

                    <i class="fas fa-circle-check"></i>

                    Data pelanggan berhasil diperbarui.

                </div>

            <?php endif; ?>


            <!-- =================================================
             PAGE HEADER
        ================================================= -->

            <div class="page-header">


                <div>

                    <h1>
                        Detail Pelanggan
                    </h1>

                    <p>
                        Informasi akun dan aktivitas pelanggan.
                    </p>

                </div>


                <div class="page-actions">


                    <a
                        href="customers.php"
                        class="btn">

                        <i class="fas fa-arrow-left"></i>

                        Kembali

                    </a>


                    <a
                        href="customers_edit.php?id=<?= (int)$customer['id'] ?>"
                        class="btn">

                        <i class="fas fa-pen"></i>

                        Edit

                    </a>


                </div>


            </div>


            <!-- =================================================
             PROFILE
        ================================================= -->

            <div class="customer-profile">


                <div class="profile-main">


                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($customer['full_name']) ?>&background=443&color=fff&size=150"
                        class="big-avatar"
                        alt="<?= e($customer['full_name']) ?>">


                    <div>


                        <h1>
                            <?= e($customer['full_name']) ?>
                        </h1>


                        <div class="username">

                            @<?= e($customer['username']) ?>

                        </div>


                        <div class="profile-email">

                            <i class="fas fa-envelope"></i>

                            <?= e($customer['email']) ?>

                        </div>


                    </div>


                </div>


                <div class="profile-status">


                    <?php

                    $customerStatus =
                        strtolower(
                            trim(
                                $customer['status']
                                    ??
                                    'aktif'
                            )
                        );

                    ?>


                    <span
                        class="status <?= e(
                                            statusClass(
                                                $customerStatus
                                            )
                                        ) ?>">


                        <?php if (
                            $customerStatus === 'aktif'
                        ): ?>

                            <i class="fas fa-circle-check"></i>

                            Aktif

                        <?php elseif (
                            $customerStatus === 'nonaktif'
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


                    <span class="customer-id">

                        Customer ID #<?= (int)$customer['id'] ?>

                    </span>


                </div>


            </div>


            <!-- =================================================
             STATISTICS
        ================================================= -->

            <div class="stats">


                <div class="stat">


                    <div class="stat-icon">

                        <i class="fas fa-cart-shopping"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($totalPesanan) ?>
                        </h3>

                        <p>
                            Total Pesanan
                        </p>

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
                            Pesanan Selesai
                        </p>

                    </div>


                </div>


                <div class="stat">


                    <div class="stat-icon">

                        <i class="fas fa-clock"></i>

                    </div>


                    <div>

                        <h3>
                            <?= number_format($pesananDiproses) ?>
                        </h3>

                        <p>
                            Dalam Proses
                        </p>

                    </div>


                </div>


                <div class="stat">


                    <div class="stat-icon">

                        <i class="fas fa-wallet"></i>

                    </div>


                    <div>

                        <h3>

                            Rp
                            <?= number_format(
                                $totalBelanja,
                                0,
                                ',',
                                '.'
                            ) ?>

                        </h3>

                        <p>
                            Total Belanja
                        </p>

                    </div>


                </div>


            </div>


            <!-- =================================================
             DETAIL GRID
        ================================================= -->

            <div class="detail-grid">


                <!-- =================================================
                 INFORMASI AKUN
            ================================================= -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>
                            Informasi Akun
                        </h2>

                        <i class="fas fa-user"></i>

                    </div>


                    <div class="info-list">


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-user"></i>

                                Nama Lengkap

                            </div>

                            <div class="info-value">

                                <?= e($customer['full_name']) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-at"></i>

                                Username

                            </div>

                            <div class="info-value">

                                @<?= e($customer['username']) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-envelope"></i>

                                Email

                            </div>

                            <div class="info-value">

                                <?= e($customer['email']) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-calendar-plus"></i>

                                Bergabung

                            </div>

                            <div class="info-value">

                                <?=
                                !empty($customer['created_at'])
                                    ?
                                    date(
                                        'd M Y H:i',
                                        strtotime(
                                            $customer['created_at']
                                        )
                                    )
                                    :
                                    '-'
                                ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-clock"></i>

                                Update Terakhir

                            </div>

                            <div class="info-value">

                                <?=
                                !empty($customer['updated_at'])
                                    ?
                                    date(
                                        'd M Y H:i',
                                        strtotime(
                                            $customer['updated_at']
                                        )
                                    )
                                    :
                                    '-'
                                ?>

                            </div>

                        </div>


                    </div>


                    <!-- SECURITY -->

                    <div class="security-box">


                        <h3>

                            <i class="fas fa-shield-halved"></i>

                            Keamanan Akun

                        </h3>


                        <div class="security-item">

                            <span>
                                Percobaan Login Gagal
                            </span>

                            <span>

                                <?= number_format(
                                    (int)(
                                        $customer['failed_attempts']
                                        ??
                                        0
                                    )
                                ) ?>

                            </span>

                        </div>


                        <div class="security-item">

                            <span>
                                Status Akun
                            </span>

                            <span>

                                <?= e(
                                    ucfirst(
                                        $customerStatus
                                    )
                                ) ?>

                            </span>

                        </div>


                    </div>


                </div>


                <!-- =================================================
                 RINGKASAN TRANSAKSI
            ================================================= -->

                <div class="panel">


                    <div class="panel-header">

                        <h2>
                            Ringkasan Transaksi
                        </h2>

                        <i class="fas fa-chart-line"></i>

                    </div>


                    <div class="info-list">


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-cart-shopping"></i>

                                Total Pesanan

                            </div>

                            <div class="info-value">

                                <?= number_format(
                                    $totalPesanan
                                ) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-circle-check"></i>

                                Pesanan Selesai

                            </div>

                            <div class="info-value">

                                <?= number_format(
                                    $pesananSelesai
                                ) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-spinner"></i>

                                Dalam Proses

                            </div>

                            <div class="info-value">

                                <?= number_format(
                                    $pesananDiproses
                                ) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-ban"></i>

                                Dibatalkan

                            </div>

                            <div class="info-value">

                                <?= number_format(
                                    $pesananDibatalkan
                                ) ?>

                            </div>

                        </div>


                        <div class="info-row">

                            <div class="info-label">

                                <i class="fas fa-wallet"></i>

                                Total Belanja

                            </div>

                            <div class="info-value">

                                Rp
                                <?= number_format(
                                    $totalBelanja,
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                            </div>

                        </div>


                    </div>


                </div>


            </div>


            <!-- =================================================
             PESANAN TERBARU
        ================================================= -->

            <div
                class="panel"
                style="margin-top:2rem;">


                <div class="panel-header">


                    <h2>
                        Pesanan Terbaru
                    </h2>


                    <i class="fas fa-receipt"></i>


                </div>


                <div class="order-table-wrapper">


                    <?php if (
                        !empty($pesananTerbaru)
                    ): ?>


                        <table class="order-table">


                            <thead>

                                <tr>

                                    <th>
                                        ID PESANAN
                                    </th>

                                    <th>
                                        TOTAL
                                    </th>

                                    <th>
                                        STATUS
                                    </th>

                                    <th>
                                        TANGGAL
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $pesananTerbaru
                                    as $pesanan
                                ): ?>


                                    <?php

                                    $orderStatus =
                                        strtolower(
                                            trim(
                                                $pesanan['status']
                                                    ??
                                                    ''
                                            )
                                        );

                                    $orderStatusClass =
                                        str_replace(
                                            ' ',
                                            '-',
                                            $orderStatus
                                        );

                                    ?>


                                    <tr>


                                        <td>

                                            <strong>

                                                #<?= (int)$pesanan['id'] ?>

                                            </strong>

                                        </td>


                                        <td>

                                            Rp
                                            <?= number_format(
                                                (float)(
                                                    $pesanan['total']
                                                    ??
                                                    0
                                                ),
                                                0,
                                                ',',
                                                '.'
                                            ) ?>

                                        </td>


                                        <td>

                                            <span
                                                class="order-status <?= e(
                                                                        $orderStatusClass
                                                                    ) ?>">

                                                <?= e(
                                                    $pesanan['status']
                                                        ??
                                                        '-'
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?=
                                            !empty($pesanan['created_at'])
                                                ?
                                                date(
                                                    'd M Y H:i',
                                                    strtotime(
                                                        $pesanan['created_at']
                                                    )
                                                )
                                                :
                                                '-'
                                            ?>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    <?php else: ?>


                        <div class="empty">

                            <i class="fas fa-receipt"></i>

                            Belum ada pesanan dari pelanggan ini.

                        </div>


                    <?php endif; ?>


                </div>


            </div>


        </section>


    </main>


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
           CLOSE SIDEBAR
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
    </script>


</body>

</html>
