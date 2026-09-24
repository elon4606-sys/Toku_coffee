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
   AMBIL DATA USER
===================================================== */

if (!empty($userId)) {

    $stmtUser = @$conn->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmtUser) {

        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();

        $resultUser = $stmtUser->get_result();

        if ($resultUser && $resultUser->num_rows > 0) {

            $userData = $resultUser->fetch_assoc();

            if (!empty($userData['full_name'])) {
                $namaUser = $userData['full_name'];
            }

            if (!empty($userData['username'])) {
                $username = $userData['username'];
            }

            if (!empty($userData['email'])) {
                $emailUser = $userData['email'];
            }
        }

        $stmtUser->close();
    }
}


/* =====================================================
   AVATAR
===================================================== */

$avatarName = urlencode($namaUser);

/* =====================================================
   PROMO TOKU COFFEE
===================================================== */

$promoList = [
    [
        'kode' => 'TOKUBARU',
        'judul' => 'Diskon 20% Pelanggan Baru',
        'deskripsi' => 'Potongan 20% maksimal Rp15.000',
        'syarat' => 'Minimal belanja Rp50.000 • Transaksi pertama',
        'icon' => 'fa-gift'
    ],
    [
        'kode' => 'TOKUHEMAT',
        'judul' => 'Hemat Rp10.000',
        'deskripsi' => 'Potongan langsung Rp10.000',
        'syarat' => 'Minimal belanja Rp75.000',
        'icon' => 'fa-tags'
    ],
    [
        'kode' => 'TOKUWEEKEND',
        'judul' => 'Weekend Coffee Sale',
        'deskripsi' => 'Diskon 15% maksimal Rp20.000',
        'syarat' => 'Minimal belanja Rp100.000',
        'icon' => 'fa-mug-hot'
    ]
];
/* =====================================================
   PRODUK FALLBACK
===================================================== */

$produkFallback = [

    [
        'id' => 'toku-americano',
        'nama_produk' => 'Toku Americano',
        'kategori' => 'Kopi',
        'harga' => 18000,
        'stok' => 10,
        'gambar' => '../upload/toku-americano.png',
        'deskripsi' => 'Kopi hitam klasik dengan cita rasa kuat dan aroma khas.'
    ],

    [
        'id' => 'toku-salted-caramel',
        'nama_produk' => 'Toku Salted Caramel',
        'kategori' => 'Kopi',
        'harga' => 23000,
        'stok' => 10,
        'gambar' => '../upload/toku-salted-caramel.png',
        'deskripsi' => 'Perpaduan espresso dan salted caramel yang creamy.'
    ],

    [
        'id' => 'toku-matcha-latte',
        'nama_produk' => 'Matcha Latte',
        'kategori' => 'Non-Kopi',
        'harga' => 25000,
        'stok' => 10,
        'gambar' => '../upload/toku-matcha-latte.png',
        'deskripsi' => 'Matcha premium dengan susu segar yang lembut.'
    ],

    [
        'id' => 'toku-pure-chocolate',
        'nama_produk' => 'Pure Chocolate',
        'kategori' => 'Non-Kopi',
        'harga' => 22000,
        'stok' => 10,
        'gambar' => '../upload/toku-pure-chocolate.png',
        'deskripsi' => 'Minuman cokelat dengan rasa pekat dan creamy.'
    ],

    [
        'id' => 'toku-caramel',
        'nama_produk' => 'Toku Caramel',
        'kategori' => 'Kopi',
        'harga' => 23000,
        'stok' => 10,
        'gambar' => '../upload/toku-caramel.png',
        'deskripsi' => 'Kopi dengan sentuhan caramel manis dan aroma lembut.'
    ],

    [
        'id' => 'toku-vanilla-latte',
        'nama_produk' => 'Vanilla Latte',
        'kategori' => 'Kopi',
        'harga' => 24000,
        'stok' => 10,
        'gambar' => '../upload/toku-vanilla-latte.png',
        'deskripsi' => 'Espresso dengan susu dan aroma vanilla yang lembut.'
    ]

];


/* =====================================================
   TAMBAH KE KERANJANG - AJAX
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'tambah_keranjang'
) {

    header('Content-Type: application/json; charset=UTF-8');

    $idProduk = $_POST['id_produk'] ?? '';

    $qty = isset($_POST['qty'])
        ? (int)$_POST['qty']
        : 1;

    if ($qty < 1) {
        $qty = 1;
    }

    if (
        !isset($_SESSION['keranjang']) ||
        !is_array($_SESSION['keranjang'])
    ) {
        $_SESSION['keranjang'] = [];
    }


    /* =================================================
       PRODUK DATABASE
    ================================================= */

    if (is_numeric($idProduk)) {

        $idProdukDB = (int)$idProduk;

        $stmtProduk = @$conn->prepare("
            SELECT
                p.id,
                p.nama_produk,
                p.harga,
                p.gambar,
                p.deskripsi,
                p.stok
            FROM produk p
            WHERE p.id = ?
            AND p.status = 'aktif'
            LIMIT 1
        ");

        if ($stmtProduk) {

            $stmtProduk->bind_param(
                "i",
                $idProdukDB
            );

            $stmtProduk->execute();

            $resultProduk =
                $stmtProduk->get_result();

            $produkData =
                $resultProduk->fetch_assoc();

            $stmtProduk->close();


            if ($produkData) {

                $stok =
                    (int)$produkData['stok'];

                $key =
                    (string)$produkData['id'];

                $stokDalamKeranjang =
                    isset($_SESSION['keranjang'][$key])
                    ? (int)(
                        $_SESSION['keranjang'][$key]['qty']
                        ?? 0
                    )
                    : 0;


                /* STOK HABIS */

                if ($stok <= 0) {

                    echo json_encode([
                        'success' => false,
                        'message' => 'Produk sedang habis.'
                    ]);

                    exit;
                }


                /* MELEBIHI STOK */

                if (
                    ($stokDalamKeranjang + $qty)
                    > $stok
                ) {

                    echo json_encode([
                        'success' => false,
                        'message' =>
                        'Jumlah pesanan melebihi stok yang tersedia.'
                    ]);

                    exit;
                }


                /* PRODUK SUDAH ADA */

                if (
                    isset($_SESSION['keranjang'][$key])
                ) {

                    $_SESSION['keranjang'][$key]['qty'] =
                        $stokDalamKeranjang + $qty;
                } else {

                    $_SESSION['keranjang'][$key] = [

                        'id' =>
                        (int)$produkData['id'],

                        'nama_produk' =>
                        $produkData['nama_produk'],

                        'harga' =>
                        (float)$produkData['harga'],

                        'gambar' =>
                        $produkData['gambar'],

                        'deskripsi' =>
                        $produkData['deskripsi'] ?? '',

                        'qty' =>
                        $qty
                    ];
                }


                /* HITUNG CART */

                $jumlahKeranjangAjax = 0;

                foreach (
                    $_SESSION['keranjang']
                    as $item
                ) {

                    $jumlahKeranjangAjax +=
                        (int)(
                            $item['qty']
                            ?? $item['jumlah']
                            ?? 0
                        );
                }


                echo json_encode([
                    'success' => true,
                    'message' =>
                    $produkData['nama_produk']
                        . ' berhasil ditambahkan ke keranjang.',
                    'cart_count' =>
                    $jumlahKeranjangAjax
                ]);

                exit;
            }
        }


        echo json_encode([
            'success' => false,
            'message' => 'Produk tidak ditemukan.'
        ]);

        exit;
    }


    /* =================================================
       PRODUK FALLBACK
    ================================================= */

    foreach (
        $produkFallback
        as $produkFallbackItem
    ) {

        if (
            (string)$produkFallbackItem['id']
            ===
            (string)$idProduk
        ) {

            $key =
                (string)$produkFallbackItem['id'];

            $stok =
                (int)$produkFallbackItem['stok'];

            $stokDalamKeranjang =
                isset($_SESSION['keranjang'][$key])
                ? (int)(
                    $_SESSION['keranjang'][$key]['qty']
                    ?? 0
                )
                : 0;


            if ($stok <= 0) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Produk sedang habis.'
                ]);

                exit;
            }


            if (
                ($stokDalamKeranjang + $qty)
                > $stok
            ) {

                echo json_encode([
                    'success' => false,
                    'message' =>
                    'Jumlah pesanan melebihi stok yang tersedia.'
                ]);

                exit;
            }


            if (
                isset(
                    $_SESSION['keranjang'][$key]
                )
            ) {

                $_SESSION['keranjang'][$key]['qty'] =
                    $stokDalamKeranjang + $qty;
            } else {

                $_SESSION['keranjang'][$key] = [

                    'id' =>
                    $produkFallbackItem['id'],

                    'nama_produk' =>
                    $produkFallbackItem['nama_produk'],

                    'harga' =>
                    (float)$produkFallbackItem['harga'],

                    'gambar' =>
                    $produkFallbackItem['gambar'],

                    'deskripsi' =>
                    $produkFallbackItem['deskripsi'],

                    'qty' =>
                    $qty
                ];
            }


            $jumlahKeranjangAjax = 0;

            foreach (
                $_SESSION['keranjang']
                as $item
            ) {

                $jumlahKeranjangAjax +=
                    (int)(
                        $item['qty']
                        ?? $item['jumlah']
                        ?? 0
                    );
            }


            echo json_encode([
                'success' => true,
                'message' =>
                $produkFallbackItem['nama_produk']
                    . ' berhasil ditambahkan ke keranjang.',
                'cart_count' =>
                $jumlahKeranjangAjax
            ]);

            exit;
        }
    }


    echo json_encode([
        'success' => false,
        'message' => 'Produk tidak ditemukan.'
    ]);

    exit;
}


/* =====================================================
   JUMLAH KERANJANG
===================================================== */

$jumlahKeranjang = 0;

if (
    isset($_SESSION['keranjang']) &&
    is_array($_SESSION['keranjang'])
) {

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
}


/* =====================================================
   AMBIL PRODUK DARI DATABASE
===================================================== */

$produkMenu = [];

$sql = "

    SELECT
        p.id,
        p.kategori_id,
        p.nama_produk,
        p.harga,
        p.gambar,
        p.deskripsi,
        p.stok,
        p.stok_minimum,
        p.satuan,
        p.status,
        k.nama_kategori

    FROM produk p

    LEFT JOIN kategori k
        ON k.id = p.kategori_id

    WHERE p.status = 'aktif'

    ORDER BY p.id DESC

";

$result = @$conn->query($sql);


/* =================================================
   NORMALISASI PRODUK
===================================================== */

if (
    $result &&
    $result->num_rows > 0
) {

    while (
        $row =
        $result->fetch_assoc()
    ) {

        /* =================================================
           GAMBAR PRODUK
        ================================================= */

        $gambar = trim(
            $row['gambar'] ?? ''
        );


        if (!empty($gambar)) {

            /* URL */

            if (
                strpos($gambar, 'http://') === 0 ||
                strpos($gambar, 'https://') === 0
            ) {
                // URL tetap digunakan
            }

            /* SUDAH BENAR */ elseif (
                strpos($gambar, '../upload/') === 0
            ) {
                // Tidak perlu diubah
            }

            /* DATABASE HANYA MENYIMPAN NAMA FILE */ else {

                $gambar =
                    '../upload/' .
                    basename($gambar);
            }
        }


        /* FALLBACK */

        if (empty($gambar)) {

            $gambar =
                '../upload/toku-americano.png';
        }


        /* =================================================
           KATEGORI
        ================================================= */

        $kategoriDB =
            strtolower(
                trim(
                    $row['nama_kategori'] ?? ''
                )
            );


        if ($kategoriDB === 'kopi') {

            $kategori = 'Kopi';
        } elseif (
            $kategoriDB === 'minuman' ||
            $kategoriDB === 'non coffee' ||
            $kategoriDB === 'non-kopi' ||
            $kategoriDB === 'non kopi'
        ) {

            $kategori = 'Non-Kopi';
        } else {

            $kategori =
                $row['nama_kategori']
                ?? 'Umum';
        }


        $produkMenu[] = [

            'id' =>
            $row['id'],

            'nama_produk' =>
            $row['nama_produk'],

            'kategori' =>
            $kategori,

            'harga' =>
            $row['harga'],

            'stok' =>
            (int)($row['stok'] ?? 0),

            'gambar' =>
            $gambar,

            'deskripsi' =>
            $row['deskripsi'] ?? ''

        ];
    }
} else {

    $produkMenu =
        $produkFallback;
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Toku Coffee</title>

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
        /* =====================================================
           HEADER
        ===================================================== */

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


        /* =====================================================
           USER
        ===================================================== */

        .header .user-box {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

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
        }

        .user-data a {
            font-size: 1.2rem;
            color: var(--main-color);
            text-decoration: none;
        }


        /* =====================================================
           CART
        ===================================================== */

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
           WELCOME
        ===================================================== */

        .welcome-tag {
            display: inline-block;
            font-size: 1.5rem;
            color: var(--main-color);
            border: var(--border);
            border-radius: var(--border-radius);
            padding: .6rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .home .row .content p {
            font-size: 1.6rem;
            color: #555;
            line-height: 1.8;
            padding: 1.5rem 0;
            max-width: 55rem;
        }

        .btn-group {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .btn-solid {
            background: var(--main-color);
            color: #fff !important;
        }


        /* =====================================================
           MENU
        ===================================================== */

        .menu {
            padding-top: 7rem;
        }

        .menu-header {
            text-align: center;
            max-width: 75rem;
            margin: 0 auto 3rem;
        }

        .menu-header .menu-label {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            background: #f4efe7;
            color: var(--main-color);
            border-radius: 5rem;
            padding: .7rem 1.6rem;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.2rem;
        }

        .menu-header h2 {
            font-size: 3.5rem;
            color: #2d211b;
            margin-bottom: 1rem;
        }

        .menu-header h2 span {
            color: var(--main-color);
        }

        .menu-header p {
            font-size: 1.5rem;
            color: #777;
            line-height: 1.7;
        }


        /* =====================================================
           TOOLBAR
        ===================================================== */

        .menu-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
            margin: 0 auto 3rem;
            max-width: 120rem;
            flex-wrap: wrap;
        }

        .category-list {
            display: flex;
            gap: .8rem;
            flex-wrap: wrap;
        }

        .category-btn {
            border: .1rem solid #ddd;
            background: #fff;
            color: #555;
            padding: .9rem 1.8rem;
            border-radius: 5rem;
            font-family: inherit;
            font-size: 1.3rem;
            cursor: pointer;
            transition: .2s ease;
        }

        .category-btn:hover,
        .category-btn.active {
            background: var(--main-color);
            color: #fff;
            border-color: var(--main-color);
        }

        .menu-search {
            position: relative;
            width: 30rem;
            max-width: 100%;
        }

        .menu-search i {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .menu-search input {
            width: 100%;
            border: .1rem solid #ddd;
            border-radius: 5rem;
            padding: 1.1rem 1.5rem 1.1rem 4.2rem;
            font-family: inherit;
            font-size: 1.3rem;
            outline: none;
        }

        .menu-search input:focus {
            border-color: var(--main-color);
        }


        /* =====================================================
           PRODUCT GRID
        ===================================================== */

        .box-container-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(25rem, 1fr));
            gap: 2.5rem;
        }

        .product-card {
            position: relative;
            background: #fff;
            border: .1rem solid #e8e3dc;
            border-radius: 1.8rem;
            overflow: hidden;
            padding-bottom: 1.8rem;
            transition: .25s ease;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-.7rem);
            box-shadow:
                0 1.5rem 3rem rgba(68, 51, 51, .10);
            border-color: #d9cbbd;
        }


        /* =====================================================
           PRODUCT IMAGE
        ===================================================== */

        .product-image {
            position: relative;
            height: 24rem;
            overflow: hidden;
            background: #f7f4ef;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: .4s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.06);
        }

        .product-category {
            position: absolute;
            left: 1.4rem;
            top: 1.4rem;
            background: rgba(255, 255, 255, .94);
            color: var(--main-color);
            padding: .5rem 1.2rem;
            border-radius: 5rem;
            font-size: 1.1rem;
            font-weight: 600;
        }


        /* =====================================================
           STOCK
        ===================================================== */

        .stock-badge {
            position: absolute;
            right: 1.4rem;
            top: 1.4rem;
            padding: .5rem 1.1rem;
            border-radius: 5rem;
            font-size: 1.1rem;
            font-weight: 600;
            background: #fff;
            box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .08);
        }

        .stock-available {
            color: #2e7d32;
        }

        .stock-low {
            color: #c77700;
        }

        .stock-empty {
            color: #b3261e;
        }

        /* =========================================================
   PROMO TOKU COFFEE
========================================================= */

        .promo-section {
            padding: 3rem 4rem;
            background: #fff;
            margin-bottom: 2rem;
        }

        .promo-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .promo-label {
            display: inline-flex;
            align-items: center;
            gap: .7rem;

            background: #f3f0e8;
            color: #8b6847;

            padding: .6rem 1.5rem;

            border-radius: 5rem;

            font-size: 1.2rem;
            font-weight: 600;
        }

        .promo-header h2 {
            margin-top: 1rem;

            font-size: 2.8rem;

            color: var(--main-color);
        }

        .promo-header h2 span {
            color: #c68b3c;
        }

        .promo-header p {
            margin-top: .7rem;

            font-size: 1.3rem;

            color: #888;
        }

        .promo-grid {
            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(28rem, 1fr));

            gap: 1.5rem;

            max-width: 120rem;

            margin: auto;
        }

        .promo-card {
            position: relative;

            display: flex;

            align-items: center;

            gap: 1.5rem;

            padding: 2rem;

            background: #faf9f5;

            border: .1rem solid #eee;

            border-radius: 1.5rem;

            transition: .3s ease;
        }

        .promo-card:hover {
            transform: translateY(-.5rem);

            box-shadow:
                0 1rem 2rem rgba(0, 0, 0, .08);

            border-color: #c68b3c;
        }

        .promo-icon {
            flex-shrink: 0;

            width: 5rem;
            height: 5rem;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #443;

            color: #fff;

            border-radius: 50%;

            font-size: 2rem;
        }

        .promo-content {
            flex: 1;
        }

        .promo-code {
            display: inline-block;

            background: #f0e2cf;

            color: #8b6847;

            padding: .3rem .9rem;

            border-radius: .5rem;

            font-size: 1.1rem;

            font-weight: 700;

            letter-spacing: .05rem;
        }

        .promo-content h3 {
            margin-top: .6rem;

            font-size: 1.6rem;

            color: var(--main-color);
        }

        .promo-content p {
            margin-top: .3rem;

            font-size: 1.2rem;

            color: #666;
        }

        .promo-content small {
            display: block;

            margin-top: .5rem;

            font-size: 1rem;

            color: #999;
        }

        .btn-pakai-promo {
            border: none;

            background: var(--main-color);

            color: #fff;

            padding: .8rem 1.2rem;

            border-radius: .7rem;

            cursor: pointer;

            font-size: 1.1rem;

            white-space: nowrap;

            transition: .25s ease;
        }

        .btn-pakai-promo:hover {
            background: #6f4e37;

            transform: scale(1.03);
        }

        @media (max-width: 768px) {

            .promo-section {
                padding: 2rem 1.5rem;
            }

            .promo-header h2 {
                font-size: 2.2rem;
            }

            .promo-card {
                align-items: flex-start;

                flex-wrap: wrap;
            }

            .btn-pakai-promo {
                width: 100%;
            }
        }

        /* =====================================================
           PRODUCT CONTENT
        ===================================================== */

        .product-content {
            padding: 1.6rem 1.7rem 0;
        }

        .product-content h3 {
            font-size: 1.9rem;
            color: #2d211b;
            margin-bottom: .7rem;
            line-height: 1.4;
        }

        .product-content p {
            font-size: 1.3rem;
            color: #777;
            line-height: 1.7;
            min-height: 4.5rem;
            margin-bottom: 1rem;
        }

        .product-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
        }

        .product-price {
            font-size: 1.7rem;
            color: var(--main-color);
            font-weight: 700;
            white-space: nowrap;
        }

        .add-cart-btn {
            border: none;
            background: var(--main-color);
            color: #fff;
            padding: 1rem 1.3rem;
            border-radius: 1rem;
            font-family: inherit;
            font-size: 1.2rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            transition: .2s ease;
        }

        .add-cart-btn:hover {
            background: #2f2424;
            transform: translateY(-2px);
        }

        .add-cart-btn:disabled {
            background: #bbb;
            cursor: not-allowed;
            transform: none;
        }


        /* =====================================================
           DETAIL MODAL
        ===================================================== */

        .product-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .product-modal.active {
            display: flex;
        }

        .modal-content {
            width: 100%;
            max-width: 80rem;
            background: #fff;
            border-radius: 2rem;
            overflow: hidden;
            position: relative;
            animation: modalShow .25s ease;
        }

        @keyframes modalShow {

            from {
                opacity: 0;
                transform: translateY(2rem) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

        }

        .modal-close {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            width: 4rem;
            height: 4rem;
            border: none;
            border-radius: 50%;
            background: #fff;
            color: #333;
            font-size: 1.8rem;
            cursor: pointer;
            z-index: 5;
            box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .1);
        }

        .modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .modal-image {
            height: 45rem;
            background: #f7f4ef;
        }

        .modal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-info {
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .modal-category {
            color: var(--main-color);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .modal-info h2 {
            font-size: 3rem;
            color: #2d211b;
            margin-bottom: 1rem;
        }

        .modal-info p {
            font-size: 1.4rem;
            color: #777;
            line-height: 1.8;
            margin-bottom: 2rem;
        }

        .modal-price {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--main-color);
            margin-bottom: 1rem;
        }

        .modal-stock {
            font-size: 1.4rem;
            margin-bottom: 2rem;
        }


        /* =====================================================
           TOAST
        ===================================================== */

        .cart-toast {
            position: fixed;
            right: 2rem;
            bottom: 2rem;
            background: #2d211b;
            color: #fff;
            padding: 1.3rem 1.8rem;
            border-radius: 1rem;
            font-size: 1.3rem;
            z-index: 10000;
            opacity: 0;
            transform: translateY(2rem);
            pointer-events: none;
            transition: .3s ease;
        }

        .cart-toast.show {
            opacity: 1;
            transform: translateY(0);
        }


        /* =====================================================
           NO PRODUCT
        ===================================================== */

        .no-product {
            display: none;
            text-align: center;
            padding: 5rem 2rem;
            background: #fff;
            border: .1rem solid #eee;
            border-radius: 1.5rem;
        }

        .no-product i {
            font-size: 4rem;
            color: var(--main-color);
            margin-bottom: 1rem;
        }

        .no-product h3 {
            font-size: 2rem;
            color: #333;
        }

        .no-product p {
            font-size: 1.3rem;
            color: #777;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width:900px) {

            .user-data {
                display: none;
            }

            .menu-toolbar {
                align-items: stretch;
            }

            .menu-search {
                width: 100%;
            }

            .modal-body {
                grid-template-columns: 1fr;
            }

            .modal-image {
                height: 28rem;
            }

            .modal-info {
                padding: 2.5rem;
            }

        }


        @media (max-width:768px) {

            .header .user-box {
                gap: .8rem;
            }

            .menu-header h2 {
                font-size: 2.8rem;
            }

            .product-image {
                height: 22rem;
            }

        }


        @media (max-width:450px) {

            .product-bottom {
                flex-direction: column;
                align-items: stretch;
            }

            .product-price {
                text-align: center;
            }

            .add-cart-btn {
                width: 100%;
            }

            .modal-info h2 {
                font-size: 2.4rem;
            }

            .cart-toast {
                left: 1.5rem;
                right: 1.5rem;
                text-align: center;
            }

        }
    </style>

</head>


<body>


    <!-- =====================================================
         HEADER
    ===================================================== -->

    <header class="header">

        <a href="index.php" class="logo">

            <span class="toku-logo-text">
                TOKU <span>COFFEE</span>
            </span>

        </a>


        <nav class="navbar">

            <a href="index.php">
                beranda
            </a>

            <a href="#menu">
                menu
            </a>

            <a href="#about">
                tentang
            </a>

            <a href="#footer">
                kontak
            </a>

        </nav>


        <div class="user-box">

            <a
                href="keranjang.php"
                class="cart-link"
                title="Keranjang Saya">

                <i class="fas fa-shopping-bag"></i>

                <span
                    class="count"
                    id="cartCount"
                    style="<?= $jumlahKeranjang > 0 ? '' : 'display:none;' ?>">

                    <?= $jumlahKeranjang ?>

                </span>

            </a>


            <div class="user-info">

                <a
                    href="akun-pelanggan.php"
                    class="user-profile-link">

                    <img
                        src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff"
                        alt="Profil">

                </a>


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


    <!-- =====================================================
         HOME
    ===================================================== -->

    <section class="home" id="home">

        <div class="row">

            <div class="content">

                <span class="welcome-tag">

                    <i class="fas fa-hand-sparkles"></i>

                    Halo, <?= e($namaUser) ?>!

                </span>


                <h3>

                    Nikmati Racikan

                    <br>

                    Toku Coffee

                </h3>


                <p>

                    Selamat datang kembali di Toku Coffee.
                    Jelajahi menu kopi dan minuman non-kopi
                    favoritmu, pesan dengan mudah, dan nikmati
                    kesegarannya kapan saja.

                </p>


                <div class="btn-group">

                    <a
                        href="#menu"
                        class="btn btn-solid">

                        lihat menu

                    </a>


                    <a
                        href="keranjang.php"
                        class="btn">

                        keranjang saya

                    </a>

                </div>

            </div>


            <div class="image">

                <img
                    src="../image/home-img-1.png"
                    alt="Toku Coffee">

            </div>

        </div>


        <div class="image-slider">

            <img
                src="../upload/menu-1.png"
                alt="Toku Americano">

            <img
                src="../upload/menu-2.png"
                alt="Toku Caramel">

            <img
                src="../upload/menu-3.png"
                alt="Matcha Latte">

            <img
                src="../upload/menu-4.png"
                alt="Vanilla Latte">

            <img
                src="../upload/menu-5.png"
                alt="Pure Chocolate">

        </div>

    </section>
    <!-- =====================================================
     PROMO TOKU COFFEE
    ===================================================== -->

    <section class="promo-section">

        <div class="promo-header">
            <span class="promo-label">
                <i class="fas fa-bolt"></i>
                PROMO SPESIAL
            </span>

            <h2>
                Nikmati Lebih Banyak,
                <span>Bayar Lebih Hemat</span>
            </h2>

            <p>
                Gunakan kode promo Toku Coffee saat checkout.
            </p>
        </div>


        <div class="promo-grid">

            <?php foreach ($promoList as $promo): ?>

                <div class="promo-card">

                    <div class="promo-icon">
                        <i class="fas <?= e($promo['icon']) ?>"></i>
                    </div>

                    <div class="promo-content">

                        <span class="promo-code">
                            <?= e($promo['kode']) ?>
                        </span>

                        <h3>
                            <?= e($promo['judul']) ?>
                        </h3>

                        <p>
                            <?= e($promo['deskripsi']) ?>
                        </p>

                        <small>
                            <?= e($promo['syarat']) ?>
                        </small>

                    </div>

                    <button
                        type="button"
                        class="btn-pakai-promo"
                        onclick="copyPromo('<?= e($promo['kode']) ?>')">

                        <i class="fas fa-copy"></i>
                        Salin Kode

                    </button>

                </div>

            <?php endforeach; ?>

        </div>

    </section>


    <!-- =====================================================
         ABOUT
    ===================================================== -->

    <section class="about" id="about">

        <div class="row">

            <div class="image">

                <img
                    src="../image/about-img.jpg"
                    alt="Tentang Toku Coffee">

            </div>


            <div class="content">

                <div class="title">
                    Kenapa Memilih Toku Coffee?
                </div>


                <p>

                    Setiap botol Toku diracik dari biji kopi
                    pilihan dan bahan premium tanpa pengawet,
                    dikemas segar agar cita rasanya tetap
                    terjaga sampai ke tanganmu.

                </p>


                <div class="icons-container">

                    <div class="icons">

                        <img
                            src="../image/about-icon-1.png"
                            alt="Bahan Segar">

                        <h3>
                            bahan segar
                        </h3>

                    </div>


                    <div class="icons">

                        <img
                            src="../image/about-icon-2.png"
                            alt="Racikan Premium">

                        <h3>
                            racikan premium
                        </h3>

                    </div>


                    <div class="icons">

                        <img
                            src="../image/about-icon-3.png"
                            alt="Pengiriman Cepat">

                        <h3>
                            pengiriman cepat
                        </h3>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         MENU
    ===================================================== -->

    <section class="menu" id="menu">

        <div class="menu-header">

            <div class="menu-label">

                <i class="fas fa-mug-hot"></i>

                TOKU COFFEE MENU

            </div>


            <h2>

                Pilihan Menu

                <span>Toku Coffee</span>

            </h2>


            <p>

                Temukan berbagai pilihan kopi dan minuman
                favorit yang dibuat dengan bahan pilihan
                untuk menemani aktivitasmu.

            </p>

        </div>


        <!-- TOOLBAR -->

        <div class="menu-toolbar">

            <div class="category-list">

                <button
                    type="button"
                    class="category-btn active"
                    data-category="Semua">

                    Semua Menu

                </button>


                <button
                    type="button"
                    class="category-btn"
                    data-category="Kopi">

                    <i class="fas fa-coffee"></i>

                    Kopi

                </button>


                <button
                    type="button"
                    class="category-btn"
                    data-category="Non-Kopi">

                    <i class="fas fa-glass-water"></i>

                    Non-Kopi

                </button>

            </div>


            <div class="menu-search">

                <i class="fas fa-search"></i>

                <input
                    type="text"
                    id="productSearch"
                    placeholder="Cari menu favoritmu...">

            </div>

        </div>


        <!-- PRODUCT GRID -->

        <div
            class="box-container-grid"
            id="productGrid">

            <?php foreach ($produkMenu as $produk): ?>

                <?php

                $stok =
                    (int)($produk['stok'] ?? 0);


                if ($stok <= 0) {

                    $stockClass =
                        'stock-empty';

                    $stockText =
                        'Habis';
                } elseif ($stok <= 5) {

                    $stockClass =
                        'stock-low';

                    $stockText =
                        'Stok: ' . $stok;
                } else {

                    $stockClass =
                        'stock-available';

                    $stockText =
                        'Stok: ' . $stok;
                }

                ?>


                <div
                    class="product-card"

                    data-category="<?= e($produk['kategori']) ?>"

                    data-name="<?= e(
                                    strtolower(
                                        $produk['nama_produk']
                                    )
                                ) ?>"

                    data-id="<?= e($produk['id']) ?>"

                    data-product-name="<?= e(
                                            $produk['nama_produk']
                                        ) ?>"

                    data-price="<?= e(
                                    $produk['harga']
                                ) ?>"

                    data-stock="<?= $stok ?>"

                    data-image="<?= e(
                                    $produk['gambar']
                                ) ?>"

                    data-description="<?= e(
                                            $produk['deskripsi']
                                        ) ?>">


                    <div class="product-image">

                        <img
                            src="<?= e($produk['gambar']) ?>"
                            alt="<?= e($produk['nama_produk']) ?>"
                            loading="lazy"
                            onerror="this.src='../upload/toku-americano.png';">


                        <span class="product-category">

                            <?= e(
                                $produk['kategori']
                            ) ?>

                        </span>


                        <span
                            class="stock-badge <?= $stockClass ?>">

                            <?php if ($stok > 0): ?>

                                <i class="fas fa-box"></i>

                            <?php else: ?>

                                <i class="fas fa-circle-xmark"></i>

                            <?php endif; ?>

                            <?= e($stockText) ?>

                        </span>

                    </div>


                    <div class="product-content">

                        <h3>

                            <?= e(
                                $produk['nama_produk']
                            ) ?>

                        </h3>


                        <p>

                            <?= e(
                                $produk['deskripsi']
                                    ?: 'Nikmati minuman pilihan Toku Coffee dengan cita rasa yang khas.'
                            ) ?>

                        </p>


                        <div class="product-bottom">

                            <span class="product-price">

                                <?= rupiah(
                                    $produk['harga']
                                ) ?>

                            </span>


                            <form
                                class="add-cart-form"
                                method="POST"
                                action=""
                                style="margin:0;">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="tambah_keranjang">


                                <input
                                    type="hidden"
                                    name="id_produk"
                                    value="<?= e(
                                                $produk['id']
                                            ) ?>">


                                <input
                                    type="hidden"
                                    name="qty"
                                    value="1">


                                <button
                                    type="submit"
                                    class="add-cart-btn"

                                    <?= $stok <= 0
                                        ? 'disabled'
                                        : '' ?>>

                                    <?php if ($stok <= 0): ?>

                                        <i class="fas fa-ban"></i>

                                        Habis

                                    <?php else: ?>

                                        <i class="fas fa-cart-plus"></i>

                                        Tambah

                                    <?php endif; ?>

                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- NO PRODUCT -->

        <div
            class="no-product"
            id="noProduct">

            <i class="fas fa-mug-hot"></i>

            <h3>
                Menu tidak ditemukan
            </h3>

            <p>
                Coba gunakan nama menu atau kategori lain.
            </p>

        </div>

    </section>


    <!-- =====================================================
         DETAIL PRODUK
    ===================================================== -->

    <div
        class="product-modal"
        id="productModal">

        <div class="modal-content">

            <button
                type="button"
                class="modal-close"
                id="modalClose">

                <i class="fas fa-times"></i>

            </button>


            <div class="modal-body">

                <div class="modal-image">

                    <img
                        id="modalImage"
                        src="../upload/toku-americano.png"
                        alt="Produk"
                        onerror="this.src='../upload/toku-americano.png';">

                </div>


                <div class="modal-info">

                    <div
                        class="modal-category"
                        id="modalCategory">
                    </div>


                    <h2
                        id="modalName">
                    </h2>


                    <p
                        id="modalDescription">
                    </p>


                    <div
                        class="modal-price"
                        id="modalPrice">
                    </div>


                    <div
                        class="modal-stock"
                        id="modalStock">
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         TOAST
    ===================================================== -->

    <div
        class="cart-toast"
        id="cartToast">
    </div>


    <!-- =====================================================
         FOOTER
    ===================================================== -->

    <section
        class="footer"
        id="footer">

        <div class="box-container">


            <div class="box">

                <h3>
                    Toku Coffee
                </h3>

                <a href="#home">
                    beranda
                </a>

                <a href="#menu">
                    menu
                </a>

                <a href="#about">
                    tentang kami
                </a>

            </div>


            <div class="box">

                <h3>
                    akun
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

                <a href="akun-pelanggan.php#reward">

                    <i class="fas fa-gift"></i>

                    Reward

                </a>

            </div>


            <div class="box">

                <h3>
                    menu
                </h3>

                <a href="#menu">

                    <i class="fas fa-mug-hot"></i>

                    Semua Menu

                </a>

                <a href="#menu">

                    <i class="fas fa-coffee"></i>

                    Kopi

                </a>

                <a href="#menu">

                    <i class="fas fa-glass-water"></i>

                    Non-Kopi

                </a>

                <a href="keranjang.php">

                    <i class="fas fa-shopping-bag"></i>

                    Keranjang
                    (<span id="footerCartCount">
                        <?= $jumlahKeranjang ?>
                    </span>)

                </a>

            </div>


            <div class="box">

                <h3>
                    hubungi kami
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
        function copyPromo(kode) {

            if (navigator.clipboard) {

                navigator.clipboard.writeText(kode);

                alert(
                    'Kode promo ' +
                    kode +
                    ' berhasil disalin. Silakan gunakan di keranjang.'
                );

            } else {

                alert(
                    'Kode promo: ' + kode
                );

            }
        }
    </script>
    <script>
        /* =====================================================
           MOBILE MENU
        ===================================================== */

        const menuBtn =
            document.querySelector('#menu-btn');

        const navbar =
            document.querySelector('.navbar');

        if (menuBtn && navbar) {

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


        /* =====================================================
           FILTER PRODUK
        ===================================================== */

        const categoryButtons =
            document.querySelectorAll(
                '.category-btn'
            );

        const productCards =
            document.querySelectorAll(
                '.product-card'
            );

        const productSearch =
            document.querySelector(
                '#productSearch'
            );

        const noProduct =
            document.querySelector(
                '#noProduct'
            );

        let selectedCategory = 'Semua';


        function filterProducts() {

            const keyword =
                productSearch.value
                .toLowerCase()
                .trim();

            let jumlahTampil = 0;


            productCards.forEach(card => {

                const category =
                    card.dataset.category || '';

                const name =
                    card.dataset.name || '';


                const cocokKategori =
                    selectedCategory === 'Semua' ||
                    category === selectedCategory;


                const cocokSearch =
                    name.includes(keyword);


                if (
                    cocokKategori &&
                    cocokSearch
                ) {

                    card.style.display =
                        'block';

                    jumlahTampil++;

                } else {

                    card.style.display =
                        'none';

                }

            });


            if (noProduct) {

                noProduct.style.display =
                    jumlahTampil === 0 ?
                    'block' :
                    'none';

            }

        }


        categoryButtons.forEach(button => {

            button.addEventListener(
                'click',
                function() {

                    categoryButtons.forEach(item => {

                        item.classList.remove(
                            'active'
                        );

                    });


                    this.classList.add(
                        'active'
                    );


                    selectedCategory =
                        this.dataset.category;


                    filterProducts();

                }
            );

        });


        if (productSearch) {

            productSearch.addEventListener(
                'input',
                filterProducts
            );

        }


        /* =====================================================
           DETAIL PRODUK
        ===================================================== */

        const productModal =
            document.querySelector(
                '#productModal'
            );

        const modalClose =
            document.querySelector(
                '#modalClose'
            );

        const modalImage =
            document.querySelector(
                '#modalImage'
            );

        const modalName =
            document.querySelector(
                '#modalName'
            );

        const modalCategory =
            document.querySelector(
                '#modalCategory'
            );

        const modalDescription =
            document.querySelector(
                '#modalDescription'
            );

        const modalPrice =
            document.querySelector(
                '#modalPrice'
            );

        const modalStock =
            document.querySelector(
                '#modalStock'
            );


        productCards.forEach(card => {

            card.addEventListener(
                'click',
                function(e) {

                    /*
                     * Jika tombol Tambah ditekan,
                     * jangan buka modal.
                     */

                    if (
                        e.target.closest(
                            '.add-cart-form'
                        )
                    ) {

                        return;

                    }


                    const nama =
                        this.dataset.productName;

                    const kategori =
                        this.dataset.category;

                    const harga =
                        Number(
                            this.dataset.price
                        );

                    const stok =
                        Number(
                            this.dataset.stock
                        );

                    const gambar =
                        this.dataset.image;

                    const deskripsi =
                        this.dataset.description;


                    modalName.textContent =
                        nama;


                    modalCategory.textContent =
                        kategori;


                    modalDescription.textContent =
                        deskripsi ||
                        'Nikmati minuman pilihan Toku Coffee dengan cita rasa yang khas.';


                    modalPrice.textContent =
                        'Rp ' +
                        harga.toLocaleString(
                            'id-ID'
                        );


                    modalImage.src =
                        gambar ||
                        '../upload/toku-americano.png';


                    if (stok <= 0) {

                        modalStock.innerHTML =
                            '<span style="color:#b3261e;">' +
                            '<i class="fas fa-circle-xmark"></i> ' +
                            'Stok habis' +
                            '</span>';

                    } else if (stok <= 5) {

                        modalStock.innerHTML =
                            '<span style="color:#c77700;">' +
                            '<i class="fas fa-box"></i> ' +
                            'Stok tersisa: ' +
                            stok +
                            '</span>';

                    } else {

                        modalStock.innerHTML =
                            '<span style="color:#2e7d32;">' +
                            '<i class="fas fa-box"></i> ' +
                            'Stok tersedia: ' +
                            stok +
                            '</span>';

                    }


                    productModal.classList.add(
                        'active'
                    );

                }
            );

        });


        /* =====================================================
           CLOSE MODAL
        ===================================================== */

        if (modalClose) {

            modalClose.addEventListener(
                'click',
                () => {

                    productModal.classList.remove(
                        'active'
                    );

                }
            );

        }


        if (productModal) {

            productModal.addEventListener(
                'click',
                function(e) {

                    if (
                        e.target === productModal
                    ) {

                        productModal.classList.remove(
                            'active'
                        );

                    }

                }
            );

        }


        document.addEventListener(
            'keydown',
            function(e) {

                if (
                    e.key === 'Escape'
                ) {

                    if (productModal) {

                        productModal.classList.remove(
                            'active'
                        );

                    }

                }

            }
        );


        /* =====================================================
           TAMBAH KE KERANJANG
           AJAX - TANPA RELOAD
        ===================================================== */

        const cartForms =
            document.querySelectorAll(
                '.add-cart-form'
            );

        const cartCount =
            document.querySelector(
                '#cartCount'
            );

        const footerCartCount =
            document.querySelector(
                '#footerCartCount'
            );

        const cartToast =
            document.querySelector(
                '#cartToast'
            );


        function showToast(message) {

            if (!cartToast) {
                return;
            }


            cartToast.textContent =
                message;


            cartToast.classList.add(
                'show'
            );


            setTimeout(() => {

                cartToast.classList.remove(
                    'show'
                );

            }, 2500);

        }


        cartForms.forEach(form => {

            form.addEventListener(
                'submit',
                async function(e) {

                    e.preventDefault();


                    const button =
                        form.querySelector(
                            'button'
                        );


                    if (
                        !button ||
                        button.disabled
                    ) {

                        return;

                    }


                    const formData =
                        new FormData(form);


                    button.disabled = true;


                    try {

                        const response =
                            await fetch(
                                window.location.href, {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                }
                            );


                        const data =
                            await response.json();


                        if (data.success) {

                            /* UPDATE CART HEADER */

                            if (cartCount) {

                                cartCount.textContent =
                                    data.cart_count;


                                cartCount.style.display =
                                    data.cart_count > 0 ?
                                    'flex' :
                                    'none';

                            }


                            /* UPDATE CART FOOTER */

                            if (footerCartCount) {

                                footerCartCount.textContent =
                                    data.cart_count;

                            }


                            showToast(
                                data.message
                            );


                        } else {

                            showToast(
                                data.message
                            );

                        }


                    } catch (error) {

                        console.error(
                            error
                        );


                        showToast(
                            'Terjadi kesalahan saat menambahkan produk.'
                        );

                    }


                    button.disabled = false;

                }
            );

        });
    </script>

</body>

</html>
