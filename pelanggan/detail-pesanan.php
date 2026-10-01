<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

if (file_exists("../config/notifikasi.php")) {
    require_once "../config/notifikasi.php";
}

/* =========================================================
   SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   CEK LOGIN
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';

if ($role !== 'customer') {
    header("Location: ../login/login.php?error=akses_ditolak");
    exit;
}

/* =========================================================
   HELPER
========================================================= */

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

if (!function_exists('e')) {
    function e($text)
    {
        return htmlspecialchars(
            (string)$text,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/* =========================================================
   USER
========================================================= */

$userId = (int)($_SESSION['user_id'] ?? 0);

$namaUser =
    $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Pelanggan';

$username =
    $_SESSION['username']
    ?? '';

$emailUser =
    $_SESSION['email']
    ?? '';

$avatarName = urlencode($namaUser);

/* =========================================================
   DATA USER
========================================================= */

$dataUser = [];

if ($userId > 0) {

    $sqlUser = "
        SELECT
            id,
            full_name,
            username,
            email,
            phone,
            city,
            address
        FROM users
        WHERE id = ?
        LIMIT 1
    ";

    $stmtUser = $conn->prepare($sqlUser);

    if ($stmtUser) {

        $stmtUser->bind_param(
            "i",
            $userId
        );

        $stmtUser->execute();

        $resultUser =
            $stmtUser->get_result();

        if ($resultUser) {

            $dataUser =
                $resultUser->fetch_assoc()
                ?? [];
        }

        $stmtUser->close();
    }
}

/* =========================================================
   ID PESANAN
========================================================= */

$pesananId =
    isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($pesananId <= 0) {

    header(
        "Location: akun-pelanggan.php?error=pesanan_tidak_valid"
    );

    exit;
}

/* =========================================================
   AMBIL DATA PESANAN
========================================================= */

$pesanan = [];

$sqlPesanan = "
    SELECT
        p.id,
        p.user_id,
        p.invoice,
        p.tanggal_pesanan,
        p.total,
        p.metode_pembayaran,
        p.status,
        p.tipe_pesanan,
        p.kota,
        p.alamat_pengiriman,
        p.ongkir,

        u.full_name,
        u.username,
        u.email,
        u.phone

    FROM pesanan p

    LEFT JOIN users u
        ON u.id = p.user_id

    WHERE p.id = ?
    AND p.user_id = ?

    LIMIT 1
";

$stmtPesanan =
    $conn->prepare($sqlPesanan);

if (!$stmtPesanan) {

    die("Gagal menyiapkan query pesanan: " .
        e($conn->error));
}

$stmtPesanan->bind_param(
    "ii",
    $pesananId,
    $userId
);

$stmtPesanan->execute();

$resultPesanan =
    $stmtPesanan->get_result();

if ($resultPesanan) {

    $pesanan =
        $resultPesanan->fetch_assoc()
        ?? [];
}

$stmtPesanan->close();

/* =========================================================
   PESANAN TIDAK DITEMUKAN
========================================================= */

if (empty($pesanan)) {

    header(
        "Location: akun-pelanggan.php?error=pesanan_tidak_ditemukan"
    );

    exit;
}
/* =========================================================
   NOMOR TELEPON
========================================================= */

$nomorTelepon = trim(
    $pesanan['phone'] ?? ''
);

if ($nomorTelepon === '') {
    $nomorTelepon = trim(
        $dataUser['phone'] ?? ''
    );
}

if ($nomorTelepon === '') {
    $nomorTelepon = trim(
        $_SESSION['phone']
            ?? $_SESSION['telepon']
            ?? ''
    );
}

if ($nomorTelepon === '') {
    $nomorTelepon = '-';
}
/* =========================================================
   DATA DETAIL PESANAN
========================================================= */

$detailPesanan = [];

$sqlDetail = "
    SELECT
        dp.id,
        dp.pesanan_id,
        dp.produk_id,
        dp.jumlah,
        dp.harga,

        p.nama_produk,
        p.gambar,
        p.deskripsi,
        p.status

    FROM detail_pesanan dp

    LEFT JOIN produk p
        ON p.id = dp.produk_id

    WHERE dp.pesanan_id = ?

    ORDER BY dp.id ASC
";

$stmtDetail =
    $conn->prepare($sqlDetail);

if ($stmtDetail) {

    $stmtDetail->bind_param(
        "i",
        $pesananId
    );

    $stmtDetail->execute();

    $resultDetail =
        $stmtDetail->get_result();

    if ($resultDetail) {

        while (
            $row =
            $resultDetail->fetch_assoc()
        ) {

            /* =================================================
               GAMBAR PRODUK
            ================================================= */

            $gambar =
                trim(
                    $row['gambar'] ?? ''
                );

            if (!empty($gambar)) {

                if (
                    strpos($gambar, 'http://') === 0 ||
                    strpos($gambar, 'https://') === 0
                ) {

                    // URL tetap digunakan

                } elseif (
                    strpos($gambar, '../upload/') === 0
                ) {

                    // Sudah benar

                } else {

                    $gambar =
                        '../upload/' .
                        basename($gambar);
                }
            }

            if (empty($gambar)) {

                $gambar =
                    '../upload/toku-americano.png';
            }

            /* =================================================
               JUMLAH
            ================================================= */

            $jumlah =
                (int)($row['jumlah'] ?? 0);

            $harga =
                (float)($row['harga'] ?? 0);

            $totalProduk =
                $harga * $jumlah;

            /* =================================================
               SIMPAN
            ================================================= */

            $detailPesanan[] = [

                'id' =>
                (int)$row['id'],

                'produk_id' =>
                (int)$row['produk_id'],

                'nama_produk' =>
                $row['nama_produk']
                    ?? 'Produk',

                'gambar' =>
                $gambar,

                'deskripsi' =>
                $row['deskripsi']
                    ?? '',

                'jumlah' =>
                $jumlah,

                'harga' =>
                $harga,

                'total' =>
                $totalProduk
            ];
        }
    }

    $stmtDetail->close();
}

/* =========================================================
   DATA PESANAN
========================================================= */

$invoice =
    $pesanan['invoice']
    ?? ('TOKU-' . $pesananId);

$tanggalPesanan =
    $pesanan['tanggal_pesanan']
    ?? '';

$totalBayar =
    (float)(
        $pesanan['total']
        ?? 0
    );

$metodePembayaran =
    $pesanan['metode_pembayaran']
    ?? '-';

$statusPesanan =
    trim(
        $pesanan['status']
            ?? 'Menunggu'
    );

$tipePesanan =
    trim(
        $pesanan['tipe_pesanan']
            ?? 'Delivery'
    );

if ($tipePesanan === '') {
    $tipePesanan = 'Delivery';
}

$kota =
    $pesanan['kota']
    ?? '';

$alamat =
    $pesanan['alamat_pengiriman']
    ?? '';

$ongkir =
    (float)(
        $pesanan['ongkir']
        ?? 0
    );

/* =========================================================
   STATUS CLASS
========================================================= */

$statusClass =
    strtolower(
        trim(
            $statusPesanan
        )
    );

if ($statusClass === 'batal') {
    $statusClass = 'dibatalkan';
}

/* =========================================================
   ICON STATUS
========================================================= */

$statusIcon =
    'fa-clock';

switch ($statusClass) {

    case 'menunggu':

        $statusIcon =
            'fa-clock';

        break;

    case 'diproses':

        $statusIcon =
            'fa-spinner';

        break;

    case 'dikirim':

        $statusIcon =
            'fa-truck-fast';

        break;

    case 'selesai':

        $statusIcon =
            'fa-circle-check';

        break;

    case 'dibatalkan':

        $statusIcon =
            'fa-ban';

        break;

    default:

        $statusIcon =
            'fa-receipt';

        break;
}

/* =========================================================
   SUBTOTAL
========================================================= */

$subtotal =
    0;

$totalItem =
    0;

foreach (
    $detailPesanan
    as $item
) {

    $subtotal +=
        (float)$item['total'];

    $totalItem +=
        (int)$item['jumlah'];
}

/* =========================================================
   CEK BISA BATAL
========================================================= */

$bisaDibatalkan =
    strtolower(
        trim(
            $statusPesanan
        )
    ) === 'menunggu';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Detail Pesanan -
        <?= e($invoice) ?>
        | Toku Coffee
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        rel="stylesheet"
        href="../css/style.css">

    <style>
        * {
            font-family: 'Poppins', sans-serif;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: #faf9f5;
            color: #443;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .header .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #443;
            font-weight: 700;
            letter-spacing: .1rem;
        }

        .toku-logo-text {
            font-weight: 700;
        }

        .toku-logo-text span {
            color: var(--main-color);
        }

        .header .navbar a {
            color: #443;
            text-decoration: none;
            font-size: 1.7rem;
            transition: .2s ease;
        }

        .header .navbar a:hover {
            color: var(--main-color);
        }

        .header .user-box {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .cart-link {
            position: relative;
            width: 4.5rem;
            height: 4.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: .1rem solid #ddd;
            border-radius: 50%;
            background: #fff;
            color: var(--main-color);
            font-size: 1.8rem;
            text-decoration: none;
        }

        .cart-link .count {
            position: absolute;
            top: -.5rem;
            right: -.5rem;
            min-width: 2rem;
            height: 2rem;
            padding: 0 .4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #a94442;
            color: #fff;
            border-radius: 50%;
            font-size: 1rem;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar-wrapper {
            position: relative;
        }

        .user-profile-btn {
            border: none;
            background: transparent;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .user-profile-btn img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }

        .user-dropdown {
            position: absolute;
            top: 52px;
            right: 0;
            width: 180px;
            background: #fff;
            border-radius: 10px;
            padding: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, .15);

            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);

            transition: .25s ease;
            z-index: 9999;
        }

        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;

            padding: 11px 12px;
            border-radius: 7px;

            text-decoration: none;
            color: #443;
            font-size: 14px;
        }

        .user-dropdown a:hover {
            background: #f7f3ef;
        }

        .user-dropdown .logout-link {
            color: #a94442;
        }

        .user-dropdown .logout-link:hover {
            background: #fff0ef;
        }

        .user-data h4 {
            margin: 0;
            font-size: 1.4rem;
            color: #333;
            font-weight: 600;
            white-space: nowrap;
        }

        .user-data a {
            font-size: 1.2rem;
            color: var(--main-color);
            text-decoration: none;
        }

        #menu-btn {
            display: none;
            font-size: 2.5rem;
            color: var(--main-color);
            cursor: pointer;
        }

        /* =====================================================
           HERO
        ===================================================== */

        .detail-hero {
            padding: 12rem 7% 4rem;
            text-align: center;
            background: #faf9f5;
        }

        .detail-label {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            background: #f4efe7;
            color: var(--main-color);
            border-radius: 5rem;
            padding: .7rem 1.6rem;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.3rem;
        }

        .detail-hero h1 {
            font-size: 3.5rem;
            color: #2d211b;
            margin-bottom: .8rem;
        }

        .detail-hero h1 span {
            color: var(--main-color);
        }

        .detail-hero p {
            font-size: 1.5rem;
            color: #777;
        }

        /* =====================================================
           DETAIL SECTION
        ===================================================== */

        .detail-section {
            padding: 1rem 7% 7rem;
            background: #faf9f5;
        }

        .detail-container {
            max-width: 120rem;
            margin: 0 auto;
        }

        /* =====================================================
           TOP ORDER
        ===================================================== */

        .order-header-card {
            background: #fff;
            border: .1rem solid #e8e3dc;
            border-radius: 1.8rem;
            padding: 2.5rem;
            box-shadow:
                0 1.5rem 3rem rgba(68, 51, 51, .06);

            margin-bottom: 2rem;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }

        .invoice-label {
            font-size: 1.15rem;
            color: #999;
            margin-bottom: .4rem;
        }

        .invoice-number {
            font-size: 2rem;
            color: #2d211b;
            font-weight: 700;
            margin-bottom: .5rem;
        }

        .order-date {
            color: #777;
            font-size: 1.2rem;
        }

        .order-date i {
            color: var(--main-color);
            margin-right: .4rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            padding: 1rem 1.5rem;
            border-radius: 5rem;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .status-badge.menunggu {
            background: #fff4d6;
            color: #856404;
        }

        .status-badge.diproses {
            background: #e8f1ff;
            color: #2563eb;
        }

        .status-badge.dikirim {
            background: #e6f5ff;
            color: #0369a1;
        }

        .status-badge.selesai {
            background: #e8f7ed;
            color: #257942;
        }

        .status-badge.dibatalkan {
            background: #fff0ef;
            color: #a94442;
        }

        /* =====================================================
           GRID
        ===================================================== */

        .detail-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1.5fr) minmax(32rem, .8fr);

            gap: 2.5rem;

            align-items: start;
        }

        .detail-card {
            background: #fff;
            border: .1rem solid #e8e3dc;
            border-radius: 1.8rem;
            padding: 2.5rem;
            box-shadow:
                0 1.5rem 3rem rgba(68, 51, 51, .06);

            margin-bottom: 2rem;
        }

        .detail-card h2 {
            font-size: 2rem;
            color: #2d211b;
            margin-bottom: 2rem;

            display: flex;
            align-items: center;
            gap: .9rem;
        }

        .detail-card h2 i {
            color: var(--main-color);
        }

        /* =====================================================
           ORDER ITEM
        ===================================================== */

        .detail-item {
            display: flex;
            align-items: center;
            gap: 1.3rem;
            padding: 1.5rem 0;
            border-bottom: .1rem solid #eee;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-item-image {
            position: relative;
            width: 8rem;
            height: 8rem;
            border-radius: 1.2rem;
            overflow: hidden;
            background: #f5f1ea;
            flex-shrink: 0;
        }

        .detail-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail-item-qty {
            position: absolute;
            right: .4rem;
            bottom: .4rem;

            min-width: 2.3rem;
            height: 2.3rem;

            display: flex;
            align-items: center;
            justify-content: center;

            background: var(--main-color);
            color: #fff;

            border-radius: .5rem;

            font-size: 1rem;
            font-weight: 600;
        }

        .detail-item-info {
            flex: 1;
            min-width: 0;
        }

        .detail-item-info h3 {
            font-size: 1.45rem;
            color: #2d211b;
            margin-bottom: .5rem;
        }

        .detail-item-info p {
            font-size: 1.1rem;
            color: #999;
            line-height: 1.5;
        }

        .detail-item-price {
            text-align: right;
            white-space: nowrap;
        }

        .detail-item-price small {
            display: block;
            color: #999;
            font-size: 1rem;
            margin-bottom: .3rem;
        }

        .detail-item-price strong {
            font-size: 1.3rem;
            color: var(--main-color);
        }

        /* =====================================================
           SUMMARY
        ===================================================== */

        .summary {
            margin-top: 1rem;
            padding-top: 1.5rem;
            border-top: .1rem solid #eee;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;

            font-size: 1.25rem;
            color: #777;

            padding: .8rem 0;
        }

        .summary-row strong {
            color: #443;
        }

        .summary-row i {
            margin-right: .4rem;
            color: var(--main-color);
        }

        .summary-total {
            margin-top: 1rem;
            padding: 1.5rem 0 0;

            border-top: .1rem solid #ddd;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-total span {
            font-size: 1.35rem;
            font-weight: 600;
            color: #443;
        }

        .summary-total strong {
            font-size: 2rem;
            color: var(--main-color);
        }

        /* =====================================================
           INFO
        ===================================================== */

        .info-box {
            display: flex;
            align-items: flex-start;
            gap: 1.2rem;

            padding: 1.4rem 0;

            border-bottom: .1rem solid #eee;
        }

        .info-box:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 4rem;
            height: 4rem;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #f4efe7;
            color: var(--main-color);

            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
        }

        .info-content small {
            display: block;
            color: #999;
            font-size: 1rem;
            margin-bottom: .3rem;
        }

        .info-content strong {
            display: block;
            color: #443;
            font-size: 1.2rem;
            line-height: 1.6;
        }

        /* =====================================================
           ORDER TYPE
        ===================================================== */

        .order-type-box {
            display: flex;
            align-items: center;
            gap: 1.2rem;

            padding: 1.5rem;

            border-radius: 1.2rem;

            background: #f4f8f2;
            border: .1rem solid #dbe8d5;

            margin-bottom: 1.5rem;
        }

        .order-type-box i {
            width: 4rem;
            height: 4rem;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #527853;
            color: #fff;

            border-radius: 50%;

            font-size: 1.6rem;
        }

        .order-type-box strong {
            display: block;
            font-size: 1.25rem;
            color: #527853;
        }

        .order-type-box span {
            display: block;
            font-size: 1.05rem;
            color: #777;
            margin-top: .2rem;
        }

        /* =====================================================
           CANCEL
        ===================================================== */

        .cancel-box {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: .1rem solid #eee;
        }

        .cancel-btn {
            width: 100%;

            border: .1rem solid #a94442;

            background: #fff;

            color: #a94442;

            cursor: pointer;

            font-family: inherit;

            font-size: 1.25rem;
            font-weight: 600;

            padding: 1.3rem 2rem;

            border-radius: 1rem;

            transition: .2s ease;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: .8rem;
        }

        .cancel-btn:hover {
            background: #a94442;
            color: #fff;

            transform: translateY(-2px);
        }

        .cancel-info {
            font-size: 1rem;
            color: #999;
            text-align: center;
            margin-top: .8rem;
        }

        /* =====================================================
           BACK BUTTON
        ===================================================== */

        .btn-kembali {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .7rem;

            width: 100%;

            border: .1rem solid #ddd;
            background: #fff;
            color: #443;

            text-decoration: none;

            font-size: 1.2rem;
            font-weight: 500;

            padding: 1.2rem 2rem;

            border-radius: 1rem;

            transition: .2s ease;
        }

        .btn-kembali:hover {
            border-color: var(--main-color);
            color: var(--main-color);
        }

        /* =====================================================
           SECURITY
        ===================================================== */

        .security-info {
            margin-top: 2rem;

            padding: 1.5rem;

            border-radius: 1.2rem;

            background: #f8f4ed;
            border: .1rem solid #eadfce;
        }

        .security-info h3 {
            display: flex;
            align-items: center;
            gap: .7rem;

            font-size: 1.35rem;

            color: #443;

            margin-bottom: .8rem;
        }

        .security-info h3 i {
            color: #527853;
        }

        .security-info p {
            font-size: 1.15rem;
            color: #777;
            line-height: 1.7;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 991px) {

            .detail-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 768px) {

            #menu-btn {
                display: block;
            }

            .header .navbar {
                position: absolute;

                top: 100%;
                left: 0;
                right: 0;

                background: #fff;

                padding: 1rem;

                display: none;

                z-index: 1000;
            }

            .header .navbar.active {
                display: block;
            }

            .header .navbar a {
                display: block;
                padding: 1.2rem;
            }

            .user-data {
                display: none;
            }

            .order-header-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .detail-card {
                padding: 2rem;
            }

            .detail-hero h1 {
                font-size: 2.8rem;
            }

        }

        @media (max-width: 550px) {

            .detail-hero {
                padding: 10rem 5% 3rem;
            }

            .detail-section {
                padding: 1rem 5% 5rem;
            }

            .order-header-card,
            .detail-card {
                padding: 1.5rem;
            }

            .detail-item {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .detail-item-image {
                width: 6.5rem;
                height: 6.5rem;
            }

            .detail-item-info {
                min-width: calc(100% - 8rem);
            }

            .detail-item-price {
                width: 100%;
                text-align: right;
                padding-left: 8rem;
            }

        }
    </style>

</head>

<body>

    <!-- ======================================================
         HEADER
    ======================================================= -->

    <header class="header">

        <a
            href="index.php"
            class="logo">

            <span class="toku-logo-text">

                TOKU
                <span>COFFEE</span>

            </span>

        </a>


        <nav class="navbar">

            <a href="index.php">
                Beranda
            </a>

            <a href="index.php#menu">
                Menu
            </a>

            <a href="index.php#about">
                Tentang
            </a>

            <a href="index.php#footer">
                Kontak
            </a>

        </nav>


        <div class="user-box">

            <a
                href="keranjang.php"
                class="cart-link"
                title="Keranjang Saya">

                <i class="fas fa-shopping-bag"></i>

            </a>


            <div class="user-info">

                <div class="user-avatar-wrapper">

                    <button
                        type="button"
                        class="user-profile-btn"
                        id="userProfileBtn">

                        <img
                            src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff"
                            alt="Profil">

                    </button>


                    <div
                        class="user-dropdown"
                        id="userDropdown">

                        <a href="akun-pelanggan.php">

                            <i class="fas fa-user"></i>

                            Akun Saya

                        </a>


                        <a
                            href="../login/logout.php"
                            class="logout-link">

                            <i class="fas fa-sign-out-alt"></i>

                            Logout

                        </a>

                    </div>

                </div>


                <div class="user-data">

                    <h4>
                        <?= e($namaUser) ?>
                    </h4>

                    <a href="akun-pelanggan.php">

                        <i class="fas fa-user"></i>

                        Akun Saya

                    </a>

                </div>

            </div>

        </div>


        <div
            id="menu-btn"
            class="fas fa-bars">
        </div>

    </header>


    <!-- ======================================================
         HERO
    ======================================================= -->

    <section class="detail-hero">

        <div class="detail-label">

            <i class="fas fa-receipt"></i>

            TOKU COFFEE

        </div>


        <h1>

            Detail
            <span>Pesanan</span>

        </h1>


        <p>

            Lihat informasi lengkap pesanan dan pembayaranmu.

        </p>

    </section>


    <!-- ======================================================
         DETAIL
    ======================================================= -->

    <section class="detail-section">

        <div class="detail-container">


            <!-- =================================================
                 HEADER PESANAN
            ================================================== -->

            <div class="order-header-card">

                <div>

                    <div class="invoice-label">

                        Nomor Pesanan

                    </div>


                    <div class="invoice-number">

                        <?= e($invoice) ?>

                    </div>


                    <div class="order-date">

                        <i class="far fa-calendar"></i>

                        <?= !empty($tanggalPesanan)
                            ? e(
                                date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $tanggalPesanan
                                    )
                                )
                            )
                            : '-'
                        ?>

                    </div>

                </div>


                <div>

                    <span
                        class="status-badge <?= e($statusClass) ?>">

                        <i
                            class="fas <?= e($statusIcon) ?>">
                        </i>

                        <?= e($statusPesanan) ?>

                    </span>

                </div>

            </div>


            <!-- =================================================
                 GRID
            ================================================== -->

            <div class="detail-grid">


                <!-- =================================================
                     BAGIAN KIRI
                ================================================== -->

                <div>


                    <!-- =================================================
                         PRODUK
                    ================================================== -->

                    <div class="detail-card">

                        <h2>

                            <i class="fas fa-bag-shopping"></i>

                            Produk Pesanan

                        </h2>


                        <?php if (
                            empty($detailPesanan)
                        ): ?>

                            <div
                                style="
                                    text-align:center;
                                    padding:3rem 1rem;
                                    color:#999;
                                ">

                                <i
                                    class="fas fa-box-open"
                                    style="
                                        font-size:3rem;
                                        margin-bottom:1rem;
                                    ">
                                </i>

                                <p>
                                    Tidak ada detail produk.
                                </p>

                            </div>

                        <?php else: ?>


                            <?php foreach (
                                $detailPesanan
                                as $item
                            ): ?>

                                <div class="detail-item">


                                    <div class="detail-item-image">

                                        <img
                                            src="<?= e(
                                                        $item['gambar']
                                                    ) ?>"
                                            alt="<?= e(
                                                        $item['nama_produk']
                                                    ) ?>"
                                            onerror="
                                                this.src='../upload/toku-americano.png';
                                            ">


                                        <span
                                            class="detail-item-qty">

                                            <?= (int)$item['jumlah'] ?>

                                        </span>

                                    </div>


                                    <div class="detail-item-info">

                                        <h3>

                                            <?= e(
                                                $item['nama_produk']
                                            ) ?>

                                        </h3>


                                        <p>

                                            <?= rupiah(
                                                $item['harga']
                                            ) ?>

                                            / item

                                        </p>

                                    </div>


                                    <div class="detail-item-price">

                                        <small>

                                            <?= (int)$item['jumlah'] ?>

                                            ×

                                            <?= rupiah(
                                                $item['harga']
                                            ) ?>

                                        </small>


                                        <strong>

                                            <?= rupiah(
                                                $item['total']
                                            ) ?>

                                        </strong>

                                    </div>

                                </div>

                            <?php endforeach; ?>


                        <?php endif; ?>


                        <!-- =================================================
                             SUMMARY
                        ================================================== -->

                        <div class="summary">


                            <div class="summary-row">

                                <span>

                                    Total Item

                                </span>

                                <strong>

                                    <?= (int)$totalItem ?>

                                    item

                                </strong>

                            </div>


                            <div class="summary-row">

                                <span>

                                    Subtotal

                                </span>

                                <strong>

                                    <?= rupiah(
                                        $subtotal
                                    ) ?>

                                </strong>

                            </div>


                            <div class="summary-row">

                                <span>

                                    <i class="fas fa-truck-fast"></i>

                                    Ongkir

                                </span>


                                <strong
                                    style="
                                        color:#527853;
                                    ">

                                    <?php if (
                                        $ongkir > 0
                                    ): ?>

                                        <?= rupiah(
                                            $ongkir
                                        ) ?>

                                    <?php else: ?>

                                        Gratis

                                    <?php endif; ?>

                                </strong>

                            </div>


                            <div class="summary-total">

                                <span>

                                    Total Pembayaran

                                </span>


                                <strong>

                                    <?= rupiah(
                                        $totalBayar
                                    ) ?>

                                </strong>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         INFORMASI CUSTOMER
                    ================================================== -->

                    <div class="detail-card">

                        <h2>

                            <i class="fas fa-user"></i>

                            Informasi Pelanggan

                        </h2>


                        <div class="info-box">

                            <div class="info-icon">

                                <i class="fas fa-user"></i>

                            </div>


                            <div class="info-content">

                                <small>
                                    Nama Pelanggan
                                </small>

                                <strong>

                                    <?= e(
                                        $pesanan['full_name']
                                            ?? $namaUser
                                    ) ?>

                                </strong>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-icon">

                                <i class="fas fa-envelope"></i>

                            </div>


                            <div class="info-content">

                                <small>
                                    Email
                                </small>

                                <strong>

                                    <?= e(
                                        $pesanan['email']
                                            ?? $emailUser
                                    ) ?>

                                </strong>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-icon">

                                <i class="fas fa-phone"></i>

                            </div>


                            <div class="info-content">

                                <small>
                                    Nomor Telepon
                                </small>

                                <strong>
                                    <?= e($nomorTelepon) ?>
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     BAGIAN KANAN
                ================================================== -->

                <div>


                    <!-- =================================================
                         INFORMASI PESANAN
                    ================================================== -->

                    <div class="detail-card">

                        <h2>

                            <i class="fas fa-truck-fast"></i>

                            Pengiriman

                        </h2>


                        <?php if (
                            strtolower(
                                $tipePesanan
                            ) === 'take away'
                        ): ?>


                            <div class="order-type-box">

                                <i class="fas fa-store"></i>

                                <div>

                                    <strong>
                                        Take Away
                                    </strong>

                                    <span>
                                        Ambil langsung di outlet Toku Coffee.
                                    </span>

                                </div>

                            </div>


                            <div class="info-box">

                                <div class="info-icon">

                                    <i class="fas fa-store"></i>

                                </div>


                                <div class="info-content">

                                    <small>
                                        Lokasi Pengambilan
                                    </small>

                                    <strong>
                                        Toku Coffee
                                    </strong>

                                </div>

                            </div>


                        <?php else: ?>


                            <div class="order-type-box">

                                <i class="fas fa-truck-fast"></i>

                                <div>

                                    <strong>
                                        Delivery
                                    </strong>

                                    <span>
                                        Pesanan akan diantar ke alamat pelanggan.
                                    </span>

                                </div>

                            </div>


                            <div class="info-box">

                                <div class="info-icon">

                                    <i class="fas fa-city"></i>

                                </div>


                                <div class="info-content">

                                    <small>
                                        Kota
                                    </small>

                                    <strong>

                                        <?= e(
                                            $kota
                                                ?: '-'
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                            <div class="info-box">

                                <div class="info-icon">

                                    <i class="fas fa-map-location-dot"></i>

                                </div>


                                <div class="info-content">

                                    <small>
                                        Alamat Pengiriman
                                    </small>

                                    <strong>

                                        <?= nl2br(
                                            e(
                                                $alamat
                                                    ?: '-'
                                            )
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                        <?php endif; ?>

                    </div>


                    <!-- =================================================
                         PEMBAYARAN
                    ================================================== -->

                    <div class="detail-card">

                        <h2>

                            <i class="fas fa-credit-card"></i>

                            Pembayaran

                        </h2>


                        <div class="info-box">

                            <div class="info-icon">

                                <i class="fas fa-wallet"></i>

                            </div>


                            <div class="info-content">

                                <small>
                                    Metode Pembayaran
                                </small>

                                <strong>

                                    <?= e(
                                        $metodePembayaran
                                    ) ?>

                                </strong>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-icon">

                                <i class="fas fa-money-bill-wave"></i>

                            </div>


                            <div class="info-content">

                                <small>
                                    Total Pembayaran
                                </small>

                                <strong
                                    style="
                                        color:var(--main-color);
                                        font-size:1.5rem;
                                    ">

                                    <?= rupiah(
                                        $totalBayar
                                    ) ?>

                                </strong>

                            </div>

                        </div>


                        <!-- =================================================
                             BATALKAN
                        ================================================== -->

                        <?php if (
                            $bisaDibatalkan
                        ): ?>

                            <div class="cancel-box">

                                <form
                                    action="batalkan-pesanan.php"
                                    method="POST"
                                    onsubmit="
                                        return konfirmasiBatalPesanan();
                                    ">

                                    <input
                                        type="hidden"
                                        name="pesanan_id"
                                        value="<?= (int)$pesananId ?>">


                                    <button
                                        type="submit"
                                        class="cancel-btn">

                                        <i class="fas fa-ban"></i>

                                        Batalkan Pesanan

                                    </button>

                                </form>


                                <div class="cancel-info">

                                    Pesanan hanya dapat dibatalkan
                                    selama status masih Menunggu.

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- =================================================
                         KEMBALI
                    ================================================== -->

                    <a
                        href="akun-pelanggan.php"
                        class="btn-kembali">

                        <i class="fas fa-arrow-left"></i>

                        Kembali ke Akun Saya

                    </a>


                    <!-- =================================================
                         SECURITY
                    ================================================== -->

                    <div class="security-info">

                        <h3>

                            <i class="fas fa-shield-halved"></i>

                            Informasi Pesanan

                        </h3>

                        <p>

                            Detail pesanan ini hanya dapat dilihat
                            oleh akun customer yang membuat pesanan.

                            Pastikan informasi pesanan dan pembayaran
                            sudah sesuai.

                        </p>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ======================================================
         FOOTER
    ======================================================= -->

    <section
        class="footer"
        id="footer">

        <div class="box-container">

            <div class="box">

                <h3>
                    Toku Coffee
                </h3>

                <a href="index.php">
                    Beranda
                </a>

                <a href="index.php#menu">
                    Menu
                </a>

                <a href="index.php#about">
                    Tentang Kami
                </a>

            </div>


            <div class="box">

                <h3>
                    Akun
                </h3>

                <a href="akun-pelanggan.php">

                    <i class="fas fa-user"></i>

                    Akun Saya

                </a>

                <a href="akun-pelanggan.php#riwayat">

                    <i class="fas fa-receipt"></i>

                    Pesanan Saya

                </a>

            </div>


            <div class="box">

                <h3>
                    Menu
                </h3>

                <a href="index.php#menu">

                    <i class="fas fa-mug-hot"></i>

                    Semua Menu

                </a>

                <a href="keranjang.php">

                    <i class="fas fa-shopping-bag"></i>

                    Keranjang

                </a>

            </div>


            <div class="box">

                <h3>
                    Hubungi Kami
                </h3>

                <a href="tel:+6281200000000">

                    <i class="fas fa-phone"></i>

                    +62 812-0000-0000

                </a>

                <a href="mailto:hallo@tokucoffee.id">

                    <i class="fas fa-envelope"></i>

                    hallo@tokucoffee.id

                </a>

            </div>

        </div>


        <div class="credit">

            &copy;

            <?= date('Y') ?>

            <span>
                Toku Coffee
            </span>

            | Seluruh hak cipta dilindungi

        </div>

    </section>


    <!-- ======================================================
         USER DROPDOWN
    ======================================================= -->

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function() {

                const profileBtn =
                    document.getElementById(
                        'userProfileBtn'
                    );

                const dropdown =
                    document.getElementById(
                        'userDropdown'
                    );

                if (
                    profileBtn &&
                    dropdown
                ) {

                    profileBtn.addEventListener(
                        'click',
                        function(e) {

                            e.stopPropagation();

                            dropdown.classList.toggle(
                                'active'
                            );

                        }
                    );


                    document.addEventListener(
                        'click',
                        function() {

                            dropdown.classList.remove(
                                'active'
                            );

                        }
                    );


                    dropdown.addEventListener(
                        'click',
                        function(e) {

                            e.stopPropagation();

                        }
                    );

                }

            }
        );
    </script>


    <!-- ======================================================
         MOBILE MENU + BATALKAN
    ======================================================= -->

    <script>
        const menuBtn =
            document.querySelector(
                '#menu-btn'
            );

        const navbar =
            document.querySelector(
                '.navbar'
            );


        if (
            menuBtn &&
            navbar
        ) {

            menuBtn.onclick = () => {

                navbar.classList.toggle(
                    'active'
                );

                menuBtn.classList.toggle(
                    'fa-times'
                );

            };


            window.onscroll = () => {

                navbar.classList.remove(
                    'active'
                );

                menuBtn.classList.remove(
                    'fa-times'
                );

            };

        }


        /* =====================================================
           KONFIRMASI BATAL PESANAN
        ===================================================== */

        function konfirmasiBatalPesanan() {

            return confirm(
                "Apakah kamu yakin ingin membatalkan pesanan ini?\n\n" +
                "Pesanan yang sudah dibatalkan tidak dapat diproses kembali."
            );

        }
    </script>

</body>

</html>
