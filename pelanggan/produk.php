<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

if (file_exists("../config/notifikasi.php")) {
    require_once "../config/notifikasi.php";
}

/* =====================================================
   CEK LOGIN CUSTOMER
===================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';

if ($role !== 'customer') {
    header("Location: ../login/login.php?error=akses_ditolak");
    exit;
}


/* =====================================================
   HELPER
===================================================== */

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


/* =====================================================
   DATA USER
===================================================== */

$userId = $_SESSION['user_id'] ?? '';

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


/* =====================================================
   DATA USER DARI DATABASE
===================================================== */

if (!empty($userId)) {

    $stmtUser = @$conn->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmtUser) {

        $stmtUser->bind_param(
            "i",
            $userId
        );

        $stmtUser->execute();

        $resultUser =
            $stmtUser->get_result();

        if (
            $resultUser &&
            $resultUser->num_rows > 0
        ) {

            $userData =
                $resultUser->fetch_assoc();

            if (!empty($userData['full_name'])) {
                $namaUser =
                    $userData['full_name'];
            }

            if (!empty($userData['username'])) {
                $username =
                    $userData['username'];
            }

            if (!empty($userData['email'])) {
                $emailUser =
                    $userData['email'];
            }
        }

        $stmtUser->close();
    }
}


/* =====================================================
   AVATAR
===================================================== */

$avatarName =
    urlencode($namaUser);


/* =====================================================
   SESSION KERANJANG
===================================================== */

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}


/* =====================================================
   TAMBAH KE KERANJANG
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'tambah_keranjang'
) {

    $idProduk =
        (int)($_POST['id_produk'] ?? 0);

    $qty =
        max(
            1,
            (int)($_POST['qty'] ?? 1)
        );


    if ($idProduk <= 0) {

        header(
            "Location: produk.php?error=produk_tidak_valid"
        );

        exit;
    }


    /* =================================================
       CEK PRODUK
    ================================================== */

    $stmtProduk =
        @$conn->prepare("
            SELECT
                id,
                nama_produk,
                harga,
                gambar,
                status
            FROM produk
            WHERE id = ?
            LIMIT 1
        ");


    if (!$stmtProduk) {

        header(
            "Location: produk.php?error=database"
        );

        exit;
    }


    $stmtProduk->bind_param(
        "i",
        $idProduk
    );

    $stmtProduk->execute();

    $resultProduk =
        $stmtProduk->get_result();


    if (
        !$resultProduk ||
        $resultProduk->num_rows === 0
    ) {

        $stmtProduk->close();

        header(
            "Location: produk.php?error=produk_tidak_ditemukan"
        );

        exit;
    }


    $produkDB =
        $resultProduk->fetch_assoc();


    if (
        strtolower(
            trim(
                $produkDB['status'] ?? ''
            )
        ) !== 'aktif'
    ) {

        $stmtProduk->close();

        header(
            "Location: produk.php?error=produk_tidak_aktif"
        );

        exit;
    }


    $stmtProduk->close();


    /* =================================================
       CEK PRODUK SUDAH ADA DI KERANJANG
    ================================================== */

    $ditemukan = false;


    foreach (
        $_SESSION['keranjang']
        as $index => $item
    ) {

        $itemId =
            (int)(
                $item['id']
                ?? $item['id_produk']
                ?? 0
            );


        if ($itemId === $idProduk) {

            $jumlahLama =
                (int)(
                    $item['qty']
                    ?? $item['jumlah']
                    ?? 0
                );


            $_SESSION['keranjang'][$index]['id'] =
                $idProduk;

            $_SESSION['keranjang'][$index]['qty'] =
                $jumlahLama + $qty;


            unset(
                $_SESSION['keranjang'][$index]['id_produk']
            );

            unset(
                $_SESSION['keranjang'][$index]['jumlah']
            );


            $ditemukan = true;

            break;
        }
    }


    /* =================================================
       PRODUK BARU
    ================================================== */

    if (!$ditemukan) {

        $_SESSION['keranjang'][] = [

            'id' =>
            $idProduk,

            'qty' =>
            $qty

        ];
    }


    /* =================================================
       REDIRECT
    ================================================== */

    $redirectKategori =
        $_GET['kategori']
        ?? $_POST['kategori']
        ?? '';

    $url =
        "produk.php?success=ditambahkan";


    if (!empty($redirectKategori)) {

        $url .=
            "&kategori="
            . urlencode($redirectKategori);
    }


    header(
        "Location: " . $url
    );

    exit;
}


/* =====================================================
   HITUNG JUMLAH KERANJANG
===================================================== */

$jumlahKeranjang = 0;


foreach (
    $_SESSION['keranjang']
    as $item
) {

    $jumlahKeranjang +=
        (int)(
            $item['qty']
            ?? $item['jumlah']
            ?? 0
        );
}


/* =====================================================
   FILTER KATEGORI
===================================================== */

$filterKategori =
    trim(
        $_GET['kategori'] ?? ''
    );


if (
    strtolower($filterKategori)
    === 'minuman'
) {

    $filterKategori =
        'Non-Kopi';
}


if (
    strtolower($filterKategori)
    === 'non coffee'
) {

    $filterKategori =
        'Non-Kopi';
}


if (
    strtolower($filterKategori)
    === 'non kopi'
) {

    $filterKategori =
        'Non-Kopi';
}


if (
    !in_array(
        $filterKategori,
        [
            '',
            'Kopi',
            'Non-Kopi'
        ],
        true
    )
) {

    $filterKategori = '';
}


/* =====================================================
   SEARCH
===================================================== */

$keyword =
    trim(
        $_GET['search'] ?? ''
    );


/* =====================================================
   AMBIL DATA PRODUK
===================================================== */

$produk = [];

$sql = "

    SELECT

        p.id,

        p.nama_produk,

        p.harga,

        p.gambar,

        p.deskripsi,

        k.nama_kategori

    FROM produk p

    LEFT JOIN kategori k
        ON k.id = p.kategori_id

    WHERE
        p.status = 'aktif'

        AND (
            LOWER(k.nama_kategori) = 'kopi'

            OR LOWER(k.nama_kategori) = 'non kopi'

            OR LOWER(k.nama_kategori) = 'non coffee'

            OR LOWER(k.nama_kategori) = 'minuman'
        )

";


/* =====================================================
   FILTER KATEGORI
===================================================== */

if ($filterKategori === 'Kopi') {

    $sql .= "
        AND LOWER(k.nama_kategori) = 'kopi'
    ";
} elseif ($filterKategori === 'Non-Kopi') {

    $sql .= "
        AND (
            LOWER(k.nama_kategori) = 'non kopi'
            OR LOWER(k.nama_kategori) = 'non coffee'
            OR LOWER(k.nama_kategori) = 'minuman'
        )
    ";
}


/* =====================================================
   SEARCH
===================================================== */

if ($keyword !== '') {

    $keywordSQL =
        $conn->real_escape_string(
            $keyword
        );

    $sql .= "

        AND (
            p.nama_produk LIKE '%$keywordSQL%'
            OR p.deskripsi LIKE '%$keywordSQL%'
            OR k.nama_kategori LIKE '%$keywordSQL%'
        )

    ";
}


$sql .= "

    ORDER BY p.id DESC

";


$result =
    @$conn->query($sql);


/* =====================================================
   BACA PRODUK
===================================================== */

if (
    $result &&
    $result->num_rows > 0
) {

    while (
        $row =
        $result->fetch_assoc()
    ) {

        /* =============================================
           GAMBAR
        ============================================== */

        $gambar =
            $row['gambar'] ?? '';


        if (
            !empty($gambar) &&
            strpos($gambar, '/') === false &&
            strpos($gambar, '\\') === false
        ) {

            $gambar =
                "../image/menu-toku/"
                . $gambar;
        }


        if (empty($gambar)) {

            $gambar =
                "../image/menu-toku/"
                . "toku-americano.png";
        }


        /* =============================================
           KATEGORI
        ============================================== */

        $kategoriDB =
            strtolower(
                trim(
                    $row['nama_kategori'] ?? ''
                )
            );


        if (
            $kategoriDB === 'kopi'
        ) {

            $kategori =
                'Kopi';
        } elseif (
            $kategoriDB === 'minuman' ||
            $kategoriDB === 'non coffee' ||
            $kategoriDB === 'non kopi'
        ) {

            $kategori =
                'Non-Kopi';
        } else {

            continue;
        }


        /* =============================================
           DATA PRODUK
        ============================================== */

        $produk[] = [

            'id' =>
            $row['id'],

            'nama_produk' =>
            $row['nama_produk'],

            'harga' =>
            $row['harga'],

            'gambar' =>
            $gambar,

            'deskripsi' =>
            $row['deskripsi'] ?? '',

            'kategori' =>
            $kategori

        ];
    }
}


/* =====================================================
   JUMLAH PRODUK
===================================================== */

$totalProduk =
    count($produk);

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Menu Produk - Toku Coffee
    </title>


    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;500;600;700&display=swap"
        rel="stylesheet">


    <!-- =====================================================
         FONT AWESOME
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- =====================================================
         CSS UTAMA
    ====================================================== -->

    <link
        rel="stylesheet"
        href="../css/style.css">


    <style>
        /* =====================================================
           HEADER
        ====================================================== */

        .header .logo {

            display: flex;

            align-items: center;

            font-weight: 700;

            letter-spacing: .1rem;

        }


        .toku-logo-text {

            font-weight: 700;

        }


        .toku-logo-text span {

            color: var(--main-color);

        }


        .header .user-box {

            display: flex;

            align-items: center;

            gap: 1.5rem;

        }


        /* =====================================================
           CART
        ====================================================== */

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

            transition: .2s linear;

        }


        .cart-link:hover {

            background: #f5f2ea;

            border-color: var(--main-color);

            transform: translateY(-2px);

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


        /* =====================================================
           USER INFO
        ====================================================== */

        .user-info {

            display: flex;

            align-items: center;

            gap: 1rem;

        }


        .user-profile-link {

            display: block;

            width: 4.5rem;

            height: 4.5rem;

        }


        .user-profile-link img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            border-radius: 50%;

            border: .2rem solid #eee;

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


        .user-data a:hover {

            text-decoration: underline;

        }


        /* =====================================================
           PAGE HERO
        ====================================================== */

        .product-page {

            padding-top: 8rem;

        }


        .menu-hero {

            background:

                linear-gradient(135deg,
                    #443 0%,
                    #5b4030 55%,
                    #75563f 100%);

            border-radius: 2rem;

            padding: 4rem;

            margin-bottom: 3rem;

            position: relative;

            overflow: hidden;

            color: #fff;

        }


        .menu-hero::before {

            content: '';

            position: absolute;

            width: 22rem;

            height: 22rem;

            border-radius: 50%;

            background: rgba(255, 255, 255, .06);

            right: -5rem;

            top: -8rem;

        }


        .menu-hero::after {

            content: '';

            position: absolute;

            width: 15rem;

            height: 15rem;

            border-radius: 50%;

            background: rgba(255, 255, 255, .04);

            right: 12rem;

            bottom: -8rem;

        }


        .menu-hero-content {

            position: relative;

            z-index: 2;

            max-width: 70rem;

        }


        .menu-hero .small-title {

            display: inline-flex;

            align-items: center;

            gap: .7rem;

            font-size: 1.3rem;

            padding: .6rem 1.4rem;

            border: .1rem solid rgba(255, 255, 255, .3);

            border-radius: 5rem;

            margin-bottom: 1.3rem;

        }


        .menu-hero h1 {

            font-size: 4rem;

            line-height: 1.2;

            margin-bottom: 1rem;

        }


        .menu-hero h1 span {

            color: #e7c79e;

        }


        .menu-hero p {

            font-size: 1.5rem;

            line-height: 1.8;

            color: rgba(255, 255, 255, .85);

            max-width: 65rem;

        }


        /* =====================================================
           SEARCH
        ====================================================== */

        .menu-tools {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 2rem;

            margin-bottom: 2.5rem;

            flex-wrap: wrap;

        }


        .search-box {

            flex: 1;

            min-width: 25rem;

            position: relative;

        }


        .search-box i {

            position: absolute;

            left: 1.7rem;

            top: 50%;

            transform: translateY(-50%);

            color: #888;

            font-size: 1.5rem;

        }


        .search-box input {

            width: 100%;

            height: 5rem;

            border: .1rem solid #ddd;

            border-radius: 1rem;

            padding:

                0 5rem 0 4.8rem;

            font-family: Poppins, sans-serif;

            font-size: 1.4rem;

            outline: none;

            background: #fff;

            transition: .2s linear;

        }


        .search-box input:focus {

            border-color: var(--main-color);

            box-shadow:
                0 0 0 .3rem rgba(68, 51, 51, .08);

        }


        .search-box button {

            position: absolute;

            right: .7rem;

            top: .7rem;

            height: 3.6rem;

            padding: 0 1.5rem;

            border: none;

            border-radius: .7rem;

            background: var(--main-color);

            color: #fff;

            cursor: pointer;

            font-family: Poppins, sans-serif;

        }


        /* =====================================================
           CATEGORY
        ====================================================== */

        .category-filter {

            display: flex;

            align-items: center;

            gap: .8rem;

            flex-wrap: wrap;

        }


        .category-filter a {

            display: inline-flex;

            align-items: center;

            gap: .6rem;

            padding: 1rem 1.6rem;

            border-radius: 5rem;

            border: .1rem solid #ddd;

            background: #fff;

            color: #555;

            font-size: 1.3rem;

            text-decoration: none;

            transition: .2s linear;

        }


        .category-filter a:hover {

            border-color: var(--main-color);

            color: var(--main-color);

            transform: translateY(-2px);

        }


        .category-filter a.active {

            background: var(--main-color);

            border-color: var(--main-color);

            color: #fff;

        }


        /* =====================================================
           PRODUCT HEADER
        ====================================================== */

        .product-heading {

            display: flex;

            align-items: end;

            justify-content: space-between;

            gap: 2rem;

            margin-bottom: 2rem;

        }


        .product-heading h2 {

            font-size: 2.4rem;

            color: var(--main-color);

            margin: 0;

        }


        .product-heading p {

            margin-top: .5rem;

            color: #777;

            font-size: 1.3rem;

        }


        .product-count {

            font-size: 1.3rem;

            color: #777;

            white-space: nowrap;

        }


        .product-count strong {

            color: var(--main-color);

        }


        /* =====================================================
           PRODUCT GRID
        ====================================================== */

        .product-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 2rem;

        }


        /* =====================================================
           PRODUCT CARD
        ====================================================== */

        .product-card {

            background: #fff;

            border: .1rem solid #e7e3de;

            border-radius: 1.5rem;

            overflow: hidden;

            position: relative;

            transition:
                transform .25s ease,
                box-shadow .25s ease,
                border-color .25s ease;

        }


        .product-card:hover {

            transform: translateY(-.6rem);

            border-color: #cbbcaf;

            box-shadow:
                0 1.5rem 3rem rgba(68, 51, 51, .10);

        }


        /* =====================================================
           PRODUCT IMAGE
        ====================================================== */

        .product-image {

            height: 24rem;

            position: relative;

            overflow: hidden;

            background: #f7f4ef;

        }


        .product-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            transition: transform .4s ease;

        }


        .product-card:hover .product-image img {

            transform: scale(1.07);

        }


        /* =====================================================
           CATEGORY BADGE
        ====================================================== */

        .product-badge {

            position: absolute;

            top: 1.2rem;

            left: 1.2rem;

            z-index: 3;

            padding: .5rem 1rem;

            border-radius: 5rem;

            background: rgba(255, 255, 255, .94);

            color: var(--main-color);

            font-size: 1.1rem;

            font-weight: 600;

            box-shadow:
                0 .4rem 1rem rgba(0, 0, 0, .08);

        }


        /* =====================================================
           PRODUCT BODY
        ====================================================== */

        .product-body {

            padding: 1.7rem;

        }


        .product-category {

            font-size: 1.1rem;

            color: #98765d;

            text-transform: uppercase;

            letter-spacing: .08rem;

            font-weight: 600;

            margin-bottom: .6rem;

        }


        .product-body h3 {

            font-size: 1.8rem;

            color: #3b2418;

            line-height: 1.4;

            margin-bottom: .7rem;

        }


        .product-description {

            font-size: 1.25rem;

            line-height: 1.6;

            color: #777;

            min-height: 4rem;

            margin-bottom: 1.2rem;

        }


        .product-bottom {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 1rem;

            padding-top: 1.2rem;

            border-top: .1rem solid #eee;

        }


        .product-price {

            display: flex;

            flex-direction: column;

        }


        .price-label {

            font-size: 1rem;

            color: #999;

        }


        .price-value {

            font-size: 1.65rem;

            font-weight: 700;

            color: var(--main-color);

        }


        /* =====================================================
           ADD BUTTON
        ====================================================== */

        .add-cart-btn {

            width: 4.2rem;

            height: 4.2rem;

            flex-shrink: 0;

            border: none;

            border-radius: 1rem;

            display: flex;

            align-items: center;

            justify-content: center;

            background: var(--main-color);

            color: #fff;

            font-size: 1.5rem;

            cursor: pointer;

            transition: .2s linear;

        }


        .add-cart-btn:hover {

            transform: scale(1.06);

            background: #5a4141;

        }


        /* =====================================================
           EMPTY STATE
        ====================================================== */

        .empty-product {

            grid-column: 1 / -1;

            text-align: center;

            padding: 6rem 2rem;

            border: .1rem dashed #d8d0c8;

            border-radius: 1.5rem;

            background: #fff;

        }


        .empty-product i {

            font-size: 5rem;

            color: #b6a79b;

            margin-bottom: 1.5rem;

        }


        .empty-product h3 {

            font-size: 2rem;

            color: var(--main-color);

            margin-bottom: .8rem;

        }


        .empty-product p {

            font-size: 1.4rem;

            color: #777;

            margin-bottom: 2rem;

        }


        /* =====================================================
           NOTIFICATION
        ====================================================== */

        .product-notification {

            position: fixed;

            top: 9rem;

            right: 2rem;

            z-index: 9999;

            max-width: 35rem;

            padding: 1.3rem 1.6rem;

            border-radius: 1rem;

            display: flex;

            align-items: center;

            gap: 1rem;

            background: #fff;

            box-shadow:
                0 1rem 3rem rgba(0, 0, 0, .12);

            border-left: .4rem solid #527853;

            font-size: 1.3rem;

        }


        .product-notification i {

            color: #527853;

            font-size: 1.7rem;

        }


        /* =====================================================
           FOOTER
        ====================================================== */

        .footer .box-container {

            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(25rem, 1fr));

            gap: 2rem;

        }


        /* =====================================================
           RESPONSIVE TABLET
        ====================================================== */

        @media (max-width: 1100px) {

            .product-grid {

                grid-template-columns:
                    repeat(3, 1fr);

            }

        }


        @media (max-width: 900px) {

            .product-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .user-data {

                display: none;

            }


            .menu-hero {

                padding: 3rem;

            }


            .menu-hero h1 {

                font-size: 3.3rem;

            }

        }


        /* =====================================================
           RESPONSIVE MOBILE
        ====================================================== */

        @media (max-width: 600px) {

            .product-page {

                padding-top: 6rem;

            }


            .menu-hero {

                padding: 2.5rem 2rem;

                border-radius: 1.5rem;

            }


            .menu-hero h1 {

                font-size: 2.8rem;

            }


            .menu-hero p {

                font-size: 1.3rem;

            }


            .menu-tools {

                display: block;

            }


            .search-box {

                min-width: 100%;

                margin-bottom: 1.5rem;

            }


            .category-filter {

                overflow-x: auto;

                flex-wrap: nowrap;

                padding-bottom: .5rem;

            }


            .category-filter a {

                white-space: nowrap;

            }


            .product-heading {

                align-items: flex-start;

                flex-direction: column;

                gap: .5rem;

            }


            .product-grid {

                grid-template-columns:
                    repeat(2, 1fr);

                gap: 1.2rem;

            }


            .product-image {

                height: 18rem;

            }


            .product-body {

                padding: 1.3rem;

            }


            .product-body h3 {

                font-size: 1.5rem;

            }


            .product-description {

                font-size: 1.1rem;

            }


            .price-value {

                font-size: 1.4rem;

            }


            .add-cart-btn {

                width: 3.8rem;

                height: 3.8rem;

            }

        }


        @media (max-width: 400px) {

            .product-grid {

                grid-template-columns: 1fr;

            }


            .product-image {

                height: 22rem;

            }

        }
    </style>

</head>


<body>


    <!-- =====================================================
     HEADER
===================================================== -->

    <header class="header">


        <!-- LOGO -->

        <a
            href="index.php"
            class="logo">

            <span class="toku-logo-text">

                TOKU <span>COFFEE</span>

            </span>

        </a>


        <!-- NAVBAR -->

        <nav class="navbar">

            <a href="index.php">
                beranda
            </a>

            <a href="produk.php">
                menu
            </a>

            <a href="index.php#about">
                tentang
            </a>

            <a href="index.php#customer-account">
                akun
            </a>

            <a href="index.php#footer">
                kontak
            </a>

        </nav>


        <!-- KANAN HEADER -->

        <div class="user-box">


            <!-- KERANJANG -->

            <a
                href="keranjang.php"
                class="cart-link"
                title="Keranjang Saya">

                <i class="fas fa-shopping-bag"></i>

                <?php if ($jumlahKeranjang > 0): ?>

                    <span class="count">

                        <?= $jumlahKeranjang ?>

                    </span>

                <?php endif; ?>

            </a>


            <!-- AKUN SAYA -->

            <div class="user-info">


                <a
                    href="akun-pelanggan.php"
                    class="user-profile-link"
                    title="Akun Saya">

                    <img
                        src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff"
                        alt="Profil">

                </a>


                <div class="user-data">

                    <h4>

                        <?= e($namaUser) ?>

                    </h4>


                    <a
                        href="akun-pelanggan.php">

                        <i class="fas fa-user"></i>

                        Akun Saya

                    </a>

                </div>


            </div>


        </div>


        <!-- MOBILE MENU -->

        <div
            id="menu-btn"
            class="fas fa-bars">

        </div>


    </header>


    <!-- =====================================================
     MAIN PRODUCT PAGE
===================================================== -->

    <main class="product-page">

        <div class="container">


            <!-- =================================================
             HERO
        ================================================== -->

            <section class="menu-hero">


                <div class="menu-hero-content">


                    <div class="small-title">

                        <i class="fas fa-mug-hot"></i>

                        TOKU COFFEE MENU

                    </div>


                    <h1>

                        Temukan Menu

                        <span>Favoritmu</span>

                    </h1>


                    <p>

                        Pilih kopi dan minuman non-kopi
                        favoritmu. Semua menu Toku Coffee
                        diracik dengan bahan pilihan untuk
                        memberikan rasa terbaik di setiap
                        tegukan.

                    </p>


                </div>


            </section>


            <!-- =================================================
             SEARCH + CATEGORY
        ================================================== -->

            <section class="menu-tools">


                <!-- SEARCH -->

                <form
                    method="GET"
                    action="produk.php"
                    class="search-box">

                    <?php if ($filterKategori !== ''): ?>

                        <input
                            type="hidden"
                            name="kategori"
                            value="<?= e($filterKategori) ?>">

                    <?php endif; ?>


                    <i class="fas fa-search"></i>


                    <input
                        type="text"
                        name="search"
                        value="<?= e($keyword) ?>"
                        placeholder="Cari kopi atau minuman favorit...">


                    <button
                        type="submit">

                        Cari

                    </button>

                </form>


                <!-- CATEGORY -->

                <div class="category-filter">


                    <a
                        href="produk.php"
                        class="<?= $filterKategori === '' ? 'active' : '' ?>">

                        <i class="fas fa-border-all"></i>

                        Semua

                    </a>


                    <a
                        href="produk.php?kategori=Kopi"
                        class="<?= $filterKategori === 'Kopi' ? 'active' : '' ?>">

                        <i class="fas fa-coffee"></i>

                        Kopi

                    </a>


                    <a
                        href="produk.php?kategori=Non-Kopi"
                        class="<?= $filterKategori === 'Non-Kopi' ? 'active' : '' ?>">

                        <i class="fas fa-glass-water"></i>

                        Non-Kopi

                    </a>


                </div>


            </section>


            <!-- =================================================
             PRODUCT HEADING
        ================================================== -->

            <section class="product-heading">


                <div>

                    <h2>

                        <?= $filterKategori !== ''
                            ? e($filterKategori)
                            : 'Semua Menu'
                        ?>

                    </h2>


                    <p>

                        <?php if ($keyword !== ''): ?>

                            Hasil pencarian untuk
                            <strong>
                                "<?= e($keyword) ?>"
                            </strong>

                        <?php else: ?>

                            Pilihan menu Toku Coffee untukmu

                        <?php endif; ?>

                    </p>

                </div>


                <div class="product-count">

                    Menampilkan

                    <strong>
                        <?= $totalProduk ?>
                    </strong>

                    produk

                </div>


            </section>


            <!-- =================================================
             PRODUCT GRID
        ================================================== -->

            <section class="product-grid">


                <?php if (empty($produk)): ?>


                    <div class="empty-product">


                        <i class="fas fa-mug-hot"></i>


                        <h3>
                            Menu Tidak Ditemukan
                        </h3>


                        <p>

                            Produk yang kamu cari belum tersedia
                            atau tidak sesuai dengan filter.

                        </p>


                        <a
                            href="produk.php"
                            class="btn">

                            <i class="fas fa-rotate-left"></i>

                            Lihat Semua Menu

                        </a>


                    </div>


                <?php else: ?>


                    <?php foreach (
                        $produk
                        as $item
                    ): ?>


                        <article
                            class="product-card">


                            <!-- GAMBAR -->

                            <div
                                class="product-image">


                                <span
                                    class="product-badge">

                                    <?= e($item['kategori']) ?>

                                </span>


                                <img
                                    src="<?= e($item['gambar']) ?>"
                                    alt="<?= e($item['nama_produk']) ?>"
                                    loading="lazy"
                                    onerror="this.src='../image/menu-toku/toku-americano.png';">


                            </div>


                            <!-- BODY -->

                            <div class="product-body">


                                <div
                                    class="product-category">

                                    <?= e($item['kategori']) ?>

                                </div>


                                <h3>

                                    <?= e($item['nama_produk']) ?>

                                </h3>


                                <p
                                    class="product-description">

                                    <?= e(
                                        $item['deskripsi']
                                            ?: 'Nikmati racikan Toku Coffee dengan bahan pilihan.'
                                    ) ?>

                                </p>


                                <div
                                    class="product-bottom">


                                    <!-- HARGA -->

                                    <div
                                        class="product-price">

                                        <span
                                            class="price-label">

                                            Harga

                                        </span>


                                        <span
                                            class="price-value">

                                            <?= rupiah(
                                                $item['harga']
                                            ) ?>

                                        </span>

                                    </div>


                                    <!-- TAMBAH -->

                                    <form
                                        method="POST"
                                        action="produk.php<?= $filterKategori !== '' ? '?kategori=' . urlencode($filterKategori) : '' ?>">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="tambah_keranjang">


                                        <input
                                            type="hidden"
                                            name="id_produk"
                                            value="<?= (int)$item['id'] ?>">


                                        <input
                                            type="hidden"
                                            name="qty"
                                            value="1">


                                        <button
                                            type="submit"
                                            class="add-cart-btn"
                                            title="Tambah ke Keranjang">

                                            <i
                                                class="fas fa-plus">
                                            </i>

                                        </button>

                                    </form>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                <?php endif; ?>


            </section>


        </div>

    </main>


    <!-- =====================================================
     FOOTER
===================================================== -->

    <section
        class="footer"
        id="footer">


        <div class="box-container">


            <!-- TOKU -->

            <div class="box">

                <h3>
                    Toku Coffee
                </h3>


                <a href="index.php">

                    <i class="fas fa-home"></i>

                    Beranda

                </a>


                <a href="produk.php">

                    <i class="fas fa-mug-hot"></i>

                    Menu

                </a>


                <a href="index.php#about">

                    <i class="fas fa-circle-info"></i>

                    Tentang Kami

                </a>

            </div>


            <!-- AKUN -->

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


                <a href="akun-pelanggan.php#wishlist">

                    <i class="fas fa-heart"></i>

                    Wishlist

                </a>


                <a href="keranjang.php">

                    <i class="fas fa-shopping-bag"></i>

                    Keranjang

                </a>

            </div>


            <!-- MENU -->

            <div class="box">

                <h3>
                    Menu
                </h3>


                <a href="produk.php">

                    <i class="fas fa-border-all"></i>

                    Semua Menu

                </a>


                <a href="produk.php?kategori=Kopi">

                    <i class="fas fa-coffee"></i>

                    Kopi

                </a>


                <a href="produk.php?kategori=Non-Kopi">

                    <i class="fas fa-glass-water"></i>

                    Non-Kopi

                </a>

            </div>


            <!-- KONTAK -->

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

            | seluruh hak cipta dilindungi

        </div>


    </section>


    <!-- =====================================================
     JAVASCRIPT
===================================================== -->

    <script>
        const menuBtn =
            document.querySelector('#menu-btn');

        const navbar =
            document.querySelector('.navbar');


        if (
            menuBtn &&
            navbar
        ) {

            menuBtn.onclick = () => {

                navbar.classList.toggle('active');

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


        /* =================================================
           NOTIFIKASI TAMBAH KERANJANG
        ================================================== */

        const params =
            new URLSearchParams(
                window.location.search
            );


        if (
            params.get('success') ===
            'ditambahkan'
        ) {

            const notification =
                document.createElement('div');


            notification.className =
                'product-notification';


            notification.innerHTML = `

            <i class="fas fa-circle-check"></i>

            <span>
                Produk berhasil ditambahkan ke keranjang.
            </span>

        `;


            document.body.appendChild(
                notification
            );


            setTimeout(() => {

                notification.style.opacity =
                    '0';

                notification.style.transform =
                    'translateX(2rem)';

                notification.style.transition =
                    '.3s ease';


                setTimeout(() => {

                    notification.remove();

                }, 300);

            }, 2500);

        }
    </script>


</body>

</html>
