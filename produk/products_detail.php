<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

requireRole(['admin', 'staff']);


/* =========================================================
   HELPER
========================================================= */

if (!function_exists('rupiah')) {
    function rupiah($angka)
    {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

if (!function_exists('e')) {
    function e($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('statusClass')) {
    function statusClass($status)
    {
        return strtolower(
            str_replace(
                [' ', '_'],
                '-',
                trim((string)$status)
            )
        );
    }
}


/* =========================================================
   CEK ID
========================================================= */

$id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($id <= 0) {

    header(
        "Location: products.php?error="
            . urlencode("ID produk tidak valid.")
    );

    exit;
}


/* =========================================================
   USER LOGIN
========================================================= */

$namaUser =
    $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Admin ERP';

$roleUser =
    $_SESSION['role']
    ?? 'Administrator';

$avatarName = urlencode($namaUser);


/* =========================================================
   AMBIL DATA PRODUK
========================================================= */

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.kategori_id,
        p.sku,
        p.nama_produk,
        p.deskripsi,
        p.harga,
        p.harga_beli,
        p.stok,
        p.stok_minimum,
        p.satuan,
        p.gambar,
        p.status,
        p.created_at,
        k.nama_kategori
    FROM produk p
    LEFT JOIN kategori k
        ON k.id = p.kategori_id
    WHERE p.id = ?
    LIMIT 1
");

if (!$stmt) {

    die("Query produk gagal: "
        . $conn->error);
}

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   PRODUK TIDAK DITEMUKAN
========================================================= */

if (!$product) {

    header(
        "Location: products.php?error="
            . urlencode("Produk tidak ditemukan.")
    );

    exit;
}


/* =========================================================
   PERHITUNGAN
========================================================= */

$hargaJual =
    (float)$product['harga'];

$hargaBeli =
    (float)$product['harga_beli'];

$stok =
    (int)$product['stok'];

$stokMinimum =
    (int)$product['stok_minimum'];

$margin =
    $hargaJual - $hargaBeli;

$marginPersen = 0;

if ($hargaBeli > 0) {

    $marginPersen =
        ($margin / $hargaBeli) * 100;
}


/* =========================================================
   STATUS STOK
========================================================= */

if ($stok <= 0) {

    $stokStatus = 'Habis';

    $stokClass = 'danger';
} elseif ($stok <= $stokMinimum) {

    $stokStatus = 'Stok Rendah';

    $stokClass = 'warning';
} else {

    $stokStatus = 'Aman';

    $stokClass = 'success';
}


/* =========================================================
   STATUS PRODUK
========================================================= */

$statusProduk =
    strtolower(
        trim(
            $product['status'] ?? ''
        )
    );

if ($statusProduk === 'aktif') {

    $statusLabel = 'Aktif';

    $statusClass = 'success';
} else {

    $statusLabel = 'Nonaktif';

    $statusClass = 'danger';
}


/* =========================================================
   FOTO PRODUK
========================================================= */

$fotoProduk = trim($product['gambar'] ?? '');

if (!empty($fotoProduk)) {
    if (
        strpos($fotoProduk, 'http://') === 0 ||
        strpos($fotoProduk, 'https://') === 0
    ) {
        // URL tetap digunakan
    } else {
        $fotoProduk = '../upload/' . basename($fotoProduk);
    }
} else {
    $fotoProduk = '../upload/toku-americano.png';
}

/* =========================================================
   TANGGAL
========================================================= */

$tanggalProduk = '';

if (!empty($product['created_at'])) {

    $timestamp =
        strtotime(
            $product['created_at']
        );

    if ($timestamp !== false) {

        $tanggalProduk =
            date(
                'd M Y, H:i',
                $timestamp
            );
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
        Detail Produk - Toku Coffee ERP
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
                left .35s ease;

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


        .topbar-right {

            display:
                flex;

            align-items:
                center;

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
           BACK BUTTON
        ===================================================== */

        .back-button {

            margin-bottom:
                2rem;

        }


        .back-button a {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                .7rem;

            padding:
                1rem 1.5rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            font-size:
                1.2rem;

        }


        .back-button a:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

            background:
                #f7f4ec;

            transform:
                translateX(-.3rem);

        }


        /* =====================================================
           PRODUCT CONTAINER
        ===================================================== */

        .product-container {

            display:
                grid;

            grid-template-columns:
                1fr 2fr;

            gap:
                2rem;

            align-items:
                start;

        }


        /* =====================================================
           IMAGE PANEL
        ===================================================== */

        .image-panel {

            background:
                #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

            position:
                sticky;

            top:
                10rem;

        }


        .image-panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .image-panel h2 {

            font-size:
                1.8rem;

            margin-bottom:
                .5rem;

        }


        .image-panel p {

            font-size:
                1.1rem;

            color:
                #999;

            margin-bottom:
                2rem;

        }


        .image-preview {

            width:
                100%;

            height:
                34rem;

            background:
                #f4f1e8;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            overflow:
                hidden;

            margin-bottom:
                1.5rem;

        }


        .image-preview img {

            width:
                100%;

            height:
                100%;

            object-fit:
                cover;

        }


        .no-image {

            text-align:
                center;

            color:
                #aaa;

            padding:
                2rem;

        }


        .no-image i {

            display:
                block;

            font-size:
                6rem;

            margin-bottom:
                1rem;

        }


        .image-info {

            padding:
                1.3rem;

            background:
                #faf9f5;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

        }


        .image-info span {

            display:
                block;

            font-size:
                1rem;

            color:
                #999;

            margin-bottom:
                .3rem;

        }


        .image-info strong {

            display:
                block;

            font-size:
                1.3rem;

        }


        /* =====================================================
           DETAIL PANEL
        ===================================================== */

        .detail-panel {

            background:
                #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

        }


        .detail-panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        /* =====================================================
           HEADER
        ===================================================== */

        .detail-header {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                flex-start;

            gap:
                2rem;

            margin-bottom:
                2.5rem;

            padding-bottom:
                2rem;

            border-bottom:
                .1rem solid #eee;

        }


        .detail-header h1 {

            font-size:
                2.6rem;

            margin-bottom:
                .5rem;

        }


        .detail-header p {

            font-size:
                1.2rem;

            color:
                #999;

        }


        /* =====================================================
           STATUS
        ===================================================== */

        .status-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                .6rem;

            padding:
                .7rem 1.2rem;

            border:
                .1rem solid currentColor;

            border-radius:
                var(--border-radius);

            font-size:
                1.1rem;

            white-space:
                nowrap;

        }


        .status-badge.success {

            color:
                var(--green);

            background:
                #eef7ee;

        }


        .status-badge.warning {

            color:
                var(--orange);

            background:
                #fff8e9;

        }


        .status-badge.danger {

            color:
                var(--red);

            background:
                #fff0ef;

        }


        /* =====================================================
           PRICE
        ===================================================== */

        .price-card {

            background:
                #faf9f5;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

            padding:
                1.8rem;

            margin-bottom:
                2rem;

        }


        .price-card span {

            display:
                block;

            font-size:
                1.1rem;

            color:
                #999;

            margin-bottom:
                .5rem;

        }


        .price-card strong {

            display:
                block;

            font-size:
                2.4rem;

        }


        .price-card small {

            font-size:
                1.1rem;

            color:
                var(--green);

        }


        /* =====================================================
           INFO GRID
        ===================================================== */

        .info-grid {

            display:
                grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap:
                1rem;

            margin-bottom:
                2rem;

        }


        .info-box {

            padding:
                1.4rem;

            background:
                #faf9f5;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

        }


        .info-box span {

            display:
                block;

            font-size:
                1rem;

            color:
                #999;

            margin-bottom:
                .4rem;

        }


        .info-box strong {

            font-size:
                1.3rem;

            word-break:
                break-word;

        }


        /* =====================================================
           STOCK SECTION
        ===================================================== */

        .section-title {

            font-size:
                1.6rem;

            margin-bottom:
                1rem;

        }


        .stock-card {

            display:
                grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap:
                1rem;

            margin-bottom:
                2rem;

        }


        .stock-box {

            padding:
                1.4rem;

            background:
                #faf9f5;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

        }


        .stock-box span {

            display:
                block;

            font-size:
                1rem;

            color:
                #999;

            margin-bottom:
                .4rem;

        }


        .stock-box strong {

            font-size:
                1.8rem;

        }


        .stock-box.success {

            color:
                var(--green);

        }


        .stock-box.warning {

            color:
                var(--orange);

        }


        .stock-box.danger {

            color:
                var(--red);

        }


        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .description {

            padding:
                1.5rem;

            background:
                #faf9f5;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

            margin-bottom:
                2rem;

        }


        .description p {

            font-size:
                1.2rem;

            line-height:
                1.8;

            color:
                #666;

            white-space:
                pre-line;

        }


        .description .empty {

            color:
                #aaa;

            font-style:
                italic;

        }


        /* =====================================================
           META
        ===================================================== */

        .meta {

            display:
                flex;

            flex-wrap:
                wrap;

            gap:
                1rem;

            padding-top:
                1.5rem;

            border-top:
                .1rem solid #eee;

        }


        .meta-item {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                .6rem;

            font-size:
                1rem;

            color:
                #888;

        }


        .meta-item i {

            color:
                var(--main-color);

        }


        /* =====================================================
           ACTION
        ===================================================== */

        .actions {

            display:
                flex;

            justify-content:
                flex-end;

            gap:
                1rem;

            margin-top:
                2rem;

            padding-top:
                2rem;

            border-top:
                .1rem solid #eee;

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


        .btn-edit {

            background:
                #f3f0e8;

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


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .product-container {

                grid-template-columns:
                    1fr;

            }


            .image-panel {

                position:
                    static;

            }


            .image-preview {

                height:
                    30rem;

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


            .profile-info {

                display:
                    none;

            }

        }


        @media (max-width: 650px) {

            html {

                font-size:
                    50%;

            }


            .content {

                padding:
                    1.5rem;

            }


            .detail-panel,
            .image-panel {

                padding:
                    1.5rem;

            }


            .detail-header {

                flex-direction:
                    column;

            }


            .info-grid {

                grid-template-columns:
                    1fr;

            }


            .stock-card {

                grid-template-columns:
                    1fr;

            }


            .actions {

                flex-direction:
                    column;

            }


            .actions .btn {

                width:
                    100%;

            }


            .image-preview {

                height:
                    24rem;

            }

        }


        /* =====================================================
           PRINT
        ===================================================== */

        @media print {

            .sidebar,
            .topbar,
            .back-button,
            .actions {

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


            .product-container {

                display:
                    block;

            }


            .image-panel,
            .detail-panel {

                border:
                    .1rem solid #333;

                margin-bottom:
                    2rem;

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
            href="../dashboard.php"
            class="logo">

            <i class="fas fa-mug-hot"></i>

            TOKU COFFEE

        </a>


        <div class="menu-title">
            Menu Utama
        </div>


        <a href="../dashboard.php">

            <i class="fas fa-chart-pie"></i>

            Dashboard

        </a>


        <a href="../orders.php">

            <i class="fas fa-shopping-bag"></i>

            Pesanan

        </a>


        <a
            href="products.php"
            class="active">

            <i class="fas fa-box"></i>

            Produk

        </a>


        <a href="../customers.php">

            <i class="fas fa-users"></i>

            Pelanggan

        </a>


        <div
            class="menu-title"
            style="margin-top:2rem;">

            Manajemen

        </div>


        <a href="../inventory.php">

            <i class="fas fa-warehouse"></i>

            Inventory

        </a>


        <a href="../suppliers.php">

            <i class="fas fa-truck"></i>

            Supplier

        </a>


        <a href="../finance.php">

            <i class="fas fa-wallet"></i>

            Keuangan

        </a>


        <a href="../reports.php">

            <i class="fas fa-file-lines"></i>

            Laporan

        </a>


        <div
            class="menu-title"
            style="margin-top:2rem;">

            Sistem

        </div>


        <a href="../settings.php">

            <i class="fas fa-gear"></i>

            Pengaturan

        </a>


        <div
            class="menu-title"
            style="margin-top:2rem;">

            Akun

        </div>


        <a
            href="../login/logout.php"
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
                        Detail Produk
                    </h2>

                    <p>
                        Informasi lengkap produk Toku Coffee ERP
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


            <!-- BACK -->

            <div class="back-button">

                <a href="products.php">

                    <i class="fas fa-arrow-left"></i>

                    Kembali ke Produk

                </a>

            </div>


            <!-- =================================================
                 PRODUCT CONTAINER
            ================================================= -->

            <div class="product-container">


                <!-- =================================================
                     IMAGE PANEL
                ================================================= -->

                <section class="image-panel">


                    <h2>
                        Foto Produk
                    </h2>


                    <p>
                        Tampilan foto produk yang tersimpan.
                    </p>


                    <div class="image-preview">


                        <?php if (!empty($fotoProduk)): ?>


                            <img
                                src="<?= e($fotoProduk) ?>"
                                alt="<?= e($product['nama_produk']) ?>"
                                onerror="
                                this.style.display='none';
                                document.getElementById('noImage').style.display='block';
                                ">


                            <div
                                class="no-image"
                                id="noImage"
                                style="display:none;">

                                <i class="fas fa-mug-hot"></i>

                                <span>
                                    Foto tidak ditemukan
                                </span>

                            </div>


                        <?php else: ?>


                            <div class="no-image">

                                <i class="fas fa-mug-hot"></i>

                                <span>
                                    Belum ada foto produk
                                </span>

                            </div>


                        <?php endif; ?>


                    </div>


                    <div class="image-info">


                        <span>
                            Nama Produk
                        </span>


                        <strong>
                            <?= e($product['nama_produk']) ?>
                        </strong>


                    </div>


                </section>


                <!-- =================================================
                     DETAIL PANEL
                ================================================= -->

                <section class="detail-panel">


                    <!-- HEADER -->

                    <div class="detail-header">


                        <div>

                            <h1>
                                <?= e($product['nama_produk']) ?>
                            </h1>


                            <p>

                                SKU:
                                <strong>
                                    <?= e($product['sku']) ?>
                                </strong>

                            </p>

                        </div>


                        <div
                            class="status-badge <?= e($statusClass) ?>">


                            <?php if ($statusProduk === 'aktif'): ?>

                                <i class="fas fa-circle-check"></i>

                            <?php else: ?>

                                <i class="fas fa-circle-xmark"></i>

                            <?php endif; ?>


                            <?= e($statusLabel) ?>


                        </div>


                    </div>


                    <!-- PRICE -->

                    <div class="price-card">


                        <span>
                            Harga Jual
                        </span>


                        <strong>
                            <?= rupiah($hargaJual) ?>
                        </strong>


                        <?php if ($hargaBeli > 0): ?>

                            <small>

                                <i class="fas fa-arrow-trend-up"></i>

                                Margin
                                <?= rupiah($margin) ?>

                                (
                                <?= number_format(
                                    $marginPersen,
                                    1,
                                    ',',
                                    '.'
                                ) ?>%
                                )

                            </small>

                        <?php else: ?>

                            <small>

                                Harga beli belum tersedia

                            </small>

                        <?php endif; ?>


                    </div>


                    <!-- BASIC INFO -->

                    <h3 class="section-title">
                        Informasi Produk
                    </h3>


                    <div class="info-grid">


                        <div class="info-box">

                            <span>
                                ID Produk
                            </span>

                            <strong>
                                #<?= (int)$product['id'] ?>
                            </strong>

                        </div>


                        <div class="info-box">

                            <span>
                                SKU
                            </span>

                            <strong>
                                <?= e($product['sku']) ?>
                            </strong>

                        </div>


                        <div class="info-box">

                            <span>
                                Kategori
                            </span>

                            <strong>

                                <?= !empty($product['nama_kategori'])
                                    ? e($product['nama_kategori'])
                                    : 'Tidak ada kategori'
                                ?>

                            </strong>

                        </div>


                        <div class="info-box">

                            <span>
                                Harga Beli
                            </span>

                            <strong>
                                <?= rupiah($hargaBeli) ?>
                            </strong>

                        </div>


                        <div class="info-box">

                            <span>
                                Satuan
                            </span>

                            <strong>
                                <?= e($product['satuan']) ?>
                            </strong>

                        </div>


                        <div class="info-box">

                            <span>
                                Status
                            </span>

                            <strong>
                                <?= e($statusLabel) ?>
                            </strong>

                        </div>


                    </div>


                    <!-- STOCK -->

                    <h3 class="section-title">
                        Informasi Stok
                    </h3>


                    <div class="stock-card">


                        <div class="stock-box <?= e($stokClass) ?>">

                            <span>
                                Stok Saat Ini
                            </span>

                            <strong>

                                <?= number_format(
                                    $stok,
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                                <?= e($product['satuan']) ?>

                            </strong>

                        </div>


                        <div class="stock-box">

                            <span>
                                Stok Minimum
                            </span>

                            <strong>

                                <?= number_format(
                                    $stokMinimum,
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                                <?= e($product['satuan']) ?>

                            </strong>

                        </div>


                        <div class="stock-box <?= e($stokClass) ?>">

                            <span>
                                Status Stok
                            </span>

                            <strong>
                                <?= e($stokStatus) ?>
                            </strong>

                        </div>


                    </div>


                    <!-- DESCRIPTION -->

                    <h3 class="section-title">
                        Deskripsi Produk
                    </h3>


                    <div class="description">


                        <?php if (!empty(trim($product['deskripsi'] ?? ''))): ?>

                            <p>
                                <?= e($product['deskripsi']) ?>
                            </p>

                        <?php else: ?>

                            <p class="empty">
                                Belum ada deskripsi untuk produk ini.
                            </p>

                        <?php endif; ?>


                    </div>


                    <!-- META -->

                    <div class="meta">


                        <div class="meta-item">

                            <i class="fas fa-calendar-plus"></i>

                            Dibuat:

                            <?= e(
                                $tanggalProduk ?: '-'
                            ) ?>

                        </div>


                        <div class="meta-item">

                            <i class="fas fa-image"></i>

                            Foto:

                            <?= !empty($fotoProduk)
                                ? 'Tersedia'
                                : 'Tidak tersedia'
                            ?>

                        </div>


                        <div class="meta-item">

                            <i class="fas fa-box"></i>

                            Produk ID:

                            #<?= (int)$product['id'] ?>

                        </div>


                    </div>


                    <!-- ACTION -->

                    <div class="actions">


                        <a
                            href="products.php"
                            class="btn">

                            <i class="fas fa-arrow-left"></i>

                            Kembali

                        </a>


                        <a
                            href="products_edit.php?id=<?= (int)$product['id'] ?>"
                            class="btn btn-edit">

                            <i class="fas fa-pen"></i>

                            Edit Produk

                        </a>


                    </div>


                </section>


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
            document.getElementById('menu-btn');

        const sidebar =
            document.getElementById('sidebar');


        if (menuBtn) {

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
    </script>


</body>

</html>
