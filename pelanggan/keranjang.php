<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

if (file_exists("../config/notifikasi.php")) {
    require_once "../config/notifikasi.php";
}

/* =====================================================
   CEK SESSION
===================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =====================================================
   CEK LOGIN CUSTOMER
===================================================== */

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
   AMBIL DATA USER DATABASE
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

$avatarName = urlencode($namaUser);

/* =====================================================
   INISIALISASI KERANJANG
===================================================== */

if (
    !isset($_SESSION['keranjang']) ||
    !is_array($_SESSION['keranjang'])
) {
    $_SESSION['keranjang'] = [];
}

/* =====================================================
   PROSES KERANJANG
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action']
        ?? '';

    /* =================================================
       UPDATE QTY
    ================================================= */

    if ($action === 'update') {

        $index =
            isset($_POST['index'])
            ? (int)$_POST['index']
            : -1;

        $qty =
            isset($_POST['qty'])
            ? (int)$_POST['qty']
            : 1;

        if (
            isset($_SESSION['keranjang'][$index])
        ) {

            if ($qty <= 0) {

                unset(
                    $_SESSION['keranjang'][$index]
                );
            } else {

                $_SESSION['keranjang'][$index]['qty']
                    = min($qty, 99);
            }
        }

        $_SESSION['keranjang'] =
            array_values(
                $_SESSION['keranjang']
            );

        header("Location: keranjang.php");
        exit;
    }

    /* =================================================
       HAPUS PRODUK
    ================================================= */

    if ($action === 'hapus') {

        $index =
            isset($_POST['index'])
            ? (int)$_POST['index']
            : -1;

        if (
            isset($_SESSION['keranjang'][$index])
        ) {

            unset(
                $_SESSION['keranjang'][$index]
            );
        }

        $_SESSION['keranjang'] =
            array_values(
                $_SESSION['keranjang']
            );

        header("Location: keranjang.php");
        exit;
    }

    /* =================================================
       KOSONGKAN KERANJANG
    ================================================= */

    if ($action === 'kosongkan') {

        $_SESSION['keranjang'] = [];

        unset(
            $_SESSION['promo_code'],
            $_SESSION['promo_discount'],
            $_SESSION['promo_name']
        );

        header("Location: keranjang.php");
        exit;
    }
}

/* =====================================================
   HITUNG JUMLAH KERANJANG
===================================================== */

$jumlahKeranjang = 0;

foreach (
    $_SESSION['keranjang']
    as $cartItem
) {

    $qty =
        (int)(
            $cartItem['qty']
            ?? $cartItem['jumlah']
            ?? 0
        );

    if ($qty > 0) {
        $jumlahKeranjang += $qty;
    }
}

/* =====================================================
   AMBIL DATA PRODUK DALAM KERANJANG
===================================================== */

$items = [];

$subtotal = 0;

$totalItem = 0;

foreach (
    $_SESSION['keranjang']
    as $index => $cartItem
) {

    $produkId =
        $cartItem['id']
        ?? $cartItem['id_produk']
        ?? null;

    $qty =
        (int)(
            $cartItem['qty']
            ?? $cartItem['jumlah']
            ?? 1
        );

    if (
        !is_numeric($produkId) ||
        $qty <= 0
    ) {
        continue;
    }

    $produkId =
        (int)$produkId;

    /* =================================================
       QUERY PRODUK
    ================================================= */

    $stmtProduk = @$conn->prepare("

        SELECT
            p.id,
            p.nama_produk,
            p.harga,
            p.gambar,
            p.deskripsi,
            p.status,
            k.nama_kategori

        FROM produk p

        LEFT JOIN kategori k
            ON k.id = p.kategori_id

        WHERE p.id = ?

        LIMIT 1

    ");

    if (!$stmtProduk) {
        continue;
    }

    $stmtProduk->bind_param(
        "i",
        $produkId
    );

    $stmtProduk->execute();

    $resultProduk =
        $stmtProduk->get_result();

    if (
        !$resultProduk ||
        $resultProduk->num_rows === 0
    ) {

        $stmtProduk->close();

        continue;
    }

    $produk =
        $resultProduk->fetch_assoc();

    $stmtProduk->close();

    /* =================================================
       CEK PRODUK AKTIF
    ================================================= */

    if (
        strtolower(
            trim(
                $produk['status'] ?? ''
            )
        ) !== 'aktif'
    ) {
        continue;
    }

    /* =================================================
       GAMBAR
    ================================================= */

    $gambar =
        trim(
            $produk['gambar']
                ?? ''
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
       KATEGORI
    ================================================= */

    $kategoriDB =
        strtolower(
            trim(
                $produk['nama_kategori']
                    ?? ''
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
            $produk['nama_kategori']
            ?? 'Umum';
    }

    /* =================================================
       HARGA
    ================================================= */

    $harga =
        (float)(
            $produk['harga']
            ?? 0
        );

    $jumlah =
        $harga * $qty;

    $subtotal += $jumlah;

    $totalItem += $qty;

    /* =================================================
       SIMPAN ITEM
    ================================================= */

    $items[] = [

        'index' =>
        $index,

        'id' =>
        $produk['id'],

        'nama_produk' =>
        $produk['nama_produk'],

        'harga' =>
        $harga,

        'qty' =>
        $qty,

        'jumlah' =>
        $jumlah,

        'gambar' =>
        $gambar,

        'deskripsi' =>
        $produk['deskripsi']
            ?? '',

        'kategori' =>
        $kategori
    ];
}

/* =====================================================
   JUMLAH PRODUK
===================================================== */

$jumlahKeranjang =
    $totalItem;

$jumlahJenisProduk =
    count($items);

/* =====================================================
   ONGKIR
===================================================== */

$ongkir = 0;

/* =====================================================
   FUNGSI HITUNG PROMO
===================================================== */

function hitungPromo($kode, $subtotal, $conn)
{
    $kode =
        strtoupper(
            trim($kode)
        );

    $promo = [

        'kode' =>
        '',

        'nama' =>
        '',

        'diskon' =>
        0,

        'pesan' =>
        '',

        'valid' =>
        false
    ];

    /* =================================================
       KERANJANG KOSONG
    ================================================= */

    if ($subtotal <= 0) {

        $promo['pesan'] =
            'Keranjang masih kosong.';

        return $promo;
    }

    /* =================================================
       TOKUBARU
       20% maksimal Rp15.000
       Minimal Rp50.000
       Transaksi pertama
    ================================================= */

    if ($kode === 'TOKUBARU') {

        if ($subtotal < 50000) {

            $promo['pesan'] =
                'Minimal belanja Rp50.000 untuk promo TOKUBARU.';

            return $promo;
        }

        $userId =
            (int)(
                $_SESSION['user_id']
                ?? 0
            );

        $jumlahPesanan = 0;

        if ($userId > 0) {

            $stmt =
                @$conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM pesanan
                    WHERE user_id = ?
                    AND status != 'Dibatalkan'
                ");

            if ($stmt) {

                $stmt->bind_param(
                    "i",
                    $userId
                );

                $stmt->execute();

                $result =
                    $stmt->get_result();

                $data =
                    $result->fetch_assoc();

                $jumlahPesanan =
                    (int)(
                        $data['total']
                        ?? 0
                    );

                $stmt->close();
            }
        }

        if ($jumlahPesanan > 0) {

            $promo['pesan'] =
                'Promo TOKUBARU hanya untuk transaksi pertama.';

            return $promo;
        }

        $diskon =
            $subtotal * 0.20;

        $diskon =
            min(
                $diskon,
                15000
            );

        $promo['kode'] =
            'TOKUBARU';

        $promo['nama'] =
            'Diskon 20% Pelanggan Baru';

        $promo['diskon'] =
            $diskon;

        $promo['valid'] =
            true;

        return $promo;
    }

    /* =================================================
       TOKUHEMAT
       Potongan Rp10.000
       Minimal Rp75.000
    ================================================= */

    if ($kode === 'TOKUHEMAT') {

        if ($subtotal < 75000) {

            $promo['pesan'] =
                'Minimal belanja Rp75.000 untuk promo TOKUHEMAT.';

            return $promo;
        }

        $promo['kode'] =
            'TOKUHEMAT';

        $promo['nama'] =
            'Potongan Rp10.000';

        $promo['diskon'] =
            10000;

        $promo['valid'] =
            true;

        return $promo;
    }

    /* =================================================
       TOKUWEEKEND
       Diskon 15% maksimal Rp20.000
       Minimal Rp100.000
    ================================================= */

    if ($kode === 'TOKUWEEKEND') {

        if ($subtotal < 100000) {

            $promo['pesan'] =
                'Minimal belanja Rp100.000 untuk promo TOKUWEEKEND.';

            return $promo;
        }

        $diskon =
            $subtotal * 0.15;

        $diskon =
            min(
                $diskon,
                20000
            );

        $promo['kode'] =
            'TOKUWEEKEND';

        $promo['nama'] =
            'Weekend Coffee Sale';

        $promo['diskon'] =
            $diskon;

        $promo['valid'] =
            true;

        return $promo;
    }

    /* =================================================
       KODE TIDAK DITEMUKAN
    ================================================= */

    $promo['pesan'] =
        'Kode promo tidak ditemukan.';

    return $promo;
}

/* =====================================================
   PESAN PROMO
===================================================== */

$pesanPromo = '';

$promoBerhasil = false;

/* =====================================================
   PROSES GUNAKAN PROMO
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'gunakan_promo'
) {

    $kodePromo =
        strtoupper(
            trim(
                $_POST['kode_promo']
                    ?? ''
            )
        );

    $hasilPromo =
        hitungPromo(
            $kodePromo,
            $subtotal,
            $conn
        );

    if ($hasilPromo['valid']) {

        $_SESSION['promo_code'] =
            $hasilPromo['kode'];

        $_SESSION['promo_discount'] =
            (float)$hasilPromo['diskon'];

        $_SESSION['promo_name'] =
            $hasilPromo['nama'];

        $pesanPromo =
            'Promo berhasil digunakan!';

        $promoBerhasil =
            true;
    } else {

        unset(
            $_SESSION['promo_code'],
            $_SESSION['promo_discount'],
            $_SESSION['promo_name']
        );

        $pesanPromo =
            $hasilPromo['pesan'];
    }
}

/* =====================================================
   HAPUS PROMO
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'hapus_promo'
) {

    unset(
        $_SESSION['promo_code'],
        $_SESSION['promo_discount'],
        $_SESSION['promo_name']
    );

    $pesanPromo =
        'Promo berhasil dihapus.';
}

/* =====================================================
   AMBIL PROMO AKTIF
===================================================== */

$kodePromoAktif =
    $_SESSION['promo_code']
    ?? '';

$diskonPromo =
    (float)(
        $_SESSION['promo_discount']
        ?? 0
    );

$namaPromoAktif =
    $_SESSION['promo_name']
    ?? '';

/* =====================================================
   VALIDASI ULANG PROMO AKTIF
   Agar promo tidak tetap aktif ketika subtotal
   sudah tidak memenuhi minimal pembelian.
===================================================== */

if ($kodePromoAktif !== '') {

    $validasiPromo =
        hitungPromo(
            $kodePromoAktif,
            $subtotal,
            $conn
        );

    if ($validasiPromo['valid']) {

        $diskonPromo =
            (float)$validasiPromo['diskon'];

        $_SESSION['promo_discount'] =
            $diskonPromo;

        $_SESSION['promo_name'] =
            $validasiPromo['nama'];

        $namaPromoAktif =
            $validasiPromo['nama'];
    } else {

        unset(
            $_SESSION['promo_code'],
            $_SESSION['promo_discount'],
            $_SESSION['promo_name']
        );

        $kodePromoAktif = '';

        $diskonPromo = 0;

        $namaPromoAktif = '';
    }
}

/* =====================================================
   PASTIKAN DISKON TIDAK MELEBIHI SUBTOTAL
===================================================== */

if ($diskonPromo > $subtotal) {
    $diskonPromo = $subtotal;
}

/* =====================================================
   TOTAL BAYAR
===================================================== */

$totalSebelumDiskon =
    $subtotal + $ongkir;

$totalBayar =
    max(
        0,
        $subtotal +
            $ongkir -
            $diskonPromo
    );

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Keranjang - Toku Coffee
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- CSS UTAMA -->

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

        .header .user-box {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        /* =====================================================
           CART HEADER
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
           USER
        ===================================================== */

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
           CART PAGE
        ===================================================== */

        .cart-page {
            padding: 13rem 7% 7rem;
            background: #faf9f5;
            min-height: 100vh;
        }

        .cart-header {
            text-align: center;
            max-width: 75rem;
            margin: 0 auto 4rem;
        }

        .cart-label {
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

        .cart-header h1 {
            font-size: 3.8rem;
            color: #2d211b;
            margin-bottom: 1rem;
        }

        .cart-header h1 span {
            color: var(--main-color);
        }

        .cart-header p {
            font-size: 1.5rem;
            color: #777;
            line-height: 1.7;
        }

        /* =====================================================
           PROMO BOX
        ===================================================== */

        .promo-box {
            max-width: 120rem;
            margin: 0 auto 3rem;
            background: linear-gradient(135deg,
                    #fff8ed,
                    #fff);
            border: .1rem solid #ead9c3;
            border-radius: 1.8rem;
            padding: 2rem 2.5rem;
            box-shadow:
                0 .8rem 2rem rgba(68, 51, 51, .04);
        }

        .promo-box-title {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .promo-box-title>i {
            width: 4.5rem;
            height: 4.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4e2c7;
            color: #a76b2f;
            border-radius: 1.2rem;
            font-size: 1.8rem;
        }

        .promo-box-title h3 {
            margin: 0 0 .3rem;
            color: #2d211b;
            font-size: 1.7rem;
        }

        .promo-box-title p {
            margin: 0;
            color: #888;
            font-size: 1.2rem;
        }

        .promo-input {
            display: flex;
            gap: 1rem;
            max-width: 60rem;
        }

        .promo-input input {
            flex: 1;
            height: 4.8rem;
            border: .1rem solid #ddd;
            border-radius: 1rem;
            padding: 0 1.5rem;
            font-family: inherit;
            font-size: 1.3rem;
            outline: none;
            background: #fff;
            text-transform: uppercase;
        }

        .promo-input input:focus {
            border-color: var(--main-color);
            box-shadow: 0 0 0 .2rem rgba(68, 51, 51, .08);
        }

        .promo-input button {
            height: 4.8rem;
            border: none;
            border-radius: 1rem;
            padding: 0 2rem;
            background: var(--main-color);
            color: #fff;
            font-family: inherit;
            font-size: 1.3rem;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .promo-input button:hover {
            background: #2f2424;
            transform: translateY(-2px);
        }

        .promo-message {
            margin-top: 1rem;
            padding: 1rem 1.2rem;
            border-radius: .9rem;
            background: #f7f4ef;
            color: #6f6259;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: .7rem;
        }

        .promo-active {
            margin-top: 1.2rem;
            padding: 1.2rem 1.5rem;
            border-radius: 1rem;
            background: #eef8ef;
            border: .1rem solid #cfe5d0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .promo-active strong {
            display: block;
            color: #3e6f40;
            font-size: 1.3rem;
            margin-bottom: .3rem;
        }

        .promo-active strong i {
            margin-right: .4rem;
        }

        .promo-active span {
            display: block;
            color: #6f8270;
            font-size: 1.1rem;
        }

        .promo-active form button {
            width: 3.5rem;
            height: 3.5rem;
            border: none;
            border-radius: .8rem;
            background: #fff;
            color: #a94442;
            cursor: pointer;
            transition: .2s ease;
        }

        .promo-active form button:hover {
            background: #fbeaea;
        }

        /* =====================================================
           STEP
        ===================================================== */

        .cart-step {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3.5rem;
            flex-wrap: wrap;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: .7rem;
            color: #aaa;
            font-size: 1.3rem;
            font-weight: 500;
        }

        .step-item.active {
            color: var(--main-color);
            font-weight: 600;
        }

        .step-number {
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #eee;
            color: #777;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .step-item.active .step-number {
            background: var(--main-color);
            color: #fff;
        }

        .step-line {
            width: 4rem;
            height: .1rem;
            background: #ddd;
        }

        /* =====================================================
           CART LAYOUT
        ===================================================== */

        .cart-layout {
            max-width: 120rem;
            margin: auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 36rem;
            gap: 2.5rem;
            align-items: start;
        }

        .cart-main {
            background: #fff;
            border: .1rem solid #e8e3dc;
            border-radius: 1.8rem;
            overflow: hidden;
        }

        .cart-main-header {
            padding: 2rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: .1rem solid #eee;
        }

        .cart-main-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-main-title i {
            width: 4rem;
            height: 4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            background: #f4efe7;
            color: var(--main-color);
            font-size: 1.6rem;
        }

        .cart-main-title h2 {
            margin: 0;
            font-size: 2rem;
            color: #2d211b;
        }

        .cart-main-title span {
            display: block;
            font-size: 1.2rem;
            color: #888;
            margin-top: .2rem;
        }

        .clear-cart {
            border: none;
            background: transparent;
            color: #a94442;
            font-family: inherit;
            font-size: 1.2rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .8rem;
        }

        .clear-cart:hover {
            text-decoration: underline;
        }

        /* =====================================================
           CART ITEM
        ===================================================== */

        .cart-items {
            padding: .5rem 2.5rem 1rem;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 10rem minmax(0, 1fr) auto;
            gap: 1.8rem;
            align-items: center;
            padding: 2rem 0;
            border-bottom: .1rem solid #eee;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-image {
            width: 10rem;
            height: 10rem;
            background: #f7f4ef;
            border-radius: 1.5rem;
            overflow: hidden;
            border: .1rem solid #eee;
        }

        .cart-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: .3s ease;
        }

        .cart-image:hover img {
            transform: scale(1.05);
        }

        .cart-info {
            min-width: 0;
        }

        .cart-category {
            display: inline-block;
            background: #f4efe7;
            color: var(--main-color);
            padding: .4rem 1rem;
            border-radius: 5rem;
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: .7rem;
        }

        .cart-info h3 {
            font-size: 1.7rem;
            color: #2d211b;
            margin-bottom: .5rem;
        }

        .cart-info p {
            color: #888;
            font-size: 1.2rem;
            line-height: 1.6;
            max-width: 48rem;
            margin-bottom: .7rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .cart-price {
            font-size: 1.4rem;
            color: var(--main-color);
            font-weight: 600;
        }

        .cart-actions {
            min-width: 14rem;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
        }

        .quantity-box {
            display: flex;
            align-items: center;
            border: .1rem solid #ddd;
            border-radius: 1rem;
            overflow: hidden;
            background: #fff;
        }

        .qty-btn {
            width: 3.5rem;
            height: 3.5rem;
            border: none;
            background: #f8f6f2;
            color: var(--main-color);
            font-size: 1.3rem;
            cursor: pointer;
            transition: .2s ease;
        }

        .qty-btn:hover {
            background: #eee8df;
        }

        .qty-input {
            width: 4rem;
            height: 3.5rem;
            border: none;
            border-left: .1rem solid #eee;
            border-right: .1rem solid #eee;
            text-align: center;
            font-family: inherit;
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            outline: none;
        }

        .item-total {
            font-size: 1.6rem;
            color: #2d211b;
            font-weight: 700;
        }

        .delete-item {
            border: none;
            background: transparent;
            color: #a94442;
            cursor: pointer;
            font-family: inherit;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .delete-item:hover {
            text-decoration: underline;
        }

        /* =====================================================
           CONTINUE SHOPPING
        ===================================================== */

        .cart-footer-action {
            padding: 1.5rem 2.5rem 2.5rem;
            display: flex;
            justify-content: flex-start;
        }

        .continue-shopping {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            color: var(--main-color);
            text-decoration: none;
            font-size: 1.3rem;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border: .1rem solid #ddd;
            border-radius: 1rem;
            transition: .2s ease;
        }

        .continue-shopping:hover {
            background: #f5f1eb;
            border-color: var(--main-color);
            transform: translateY(-2px);
        }

        /* =====================================================
           SUMMARY
        ===================================================== */

        .cart-summary {
            background: #fff;
            border: .1rem solid #e8e3dc;
            border-radius: 1.8rem;
            overflow: hidden;
            position: sticky;
            top: 10rem;
        }

        .summary-header {
            background: var(--main-color);
            color: #fff;
            padding: 2rem 2.2rem;
        }

        .summary-header h2 {
            font-size: 2rem;
            margin-bottom: .4rem;
        }

        .summary-header p {
            font-size: 1.2rem;
            opacity: .8;
        }

        .summary-body {
            padding: 2.2rem;
        }

        .summary-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 0;
            font-size: 1.3rem;
            color: #666;
        }

        .summary-row strong {
            color: #333;
            font-weight: 600;
        }

        .summary-row.shipping strong {
            color: #527853;
        }

        .summary-row.discount strong {
            color: #a94442;
        }

        .summary-divider {
            border: none;
            border-top: .1rem solid #eee;
            margin: 1rem 0;
        }

        .summary-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.5rem 0;
        }

        .summary-total span:first-child {
            font-size: 1.4rem;
            color: #333;
            font-weight: 600;
        }

        .summary-total span:last-child {
            font-size: 2rem;
            color: var(--main-color);
            font-weight: 700;
        }

        .checkout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .8rem;
            background: var(--main-color);
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 1rem;
            padding: 1.4rem 1.5rem;
            font-family: inherit;
            font-size: 1.4rem;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .checkout-btn:hover {
            background: #2f2424;
            transform: translateY(-2px);
        }

        .free-shipping {
            margin-top: 1.5rem;
            padding: 1rem 1.2rem;
            border-radius: 1rem;
            background: #f3f8f3;
            color: #527853;
            font-size: 1.15rem;
            line-height: 1.5;
            display: flex;
            gap: .7rem;
            align-items: flex-start;
        }

        .payment-info {
            margin-top: 1.8rem;
            padding-top: 1.5rem;
            border-top: .1rem solid #eee;
        }

        .payment-info h4 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .payment-methods {
            display: flex;
            gap: .7rem;
            flex-wrap: wrap;
        }

        .payment-method {
            border: .1rem solid #eee;
            border-radius: .8rem;
            padding: .7rem 1rem;
            color: #777;
            font-size: 1.1rem;
            background: #fafafa;
        }

        /* =====================================================
           PROMO INFO
        ===================================================== */

        .promo-info {
            margin-top: 1.5rem;
            padding: 1.2rem;
            background: #fff9ed;
            border: .1rem dashed #e2c28f;
            border-radius: 1rem;
        }

        .promo-info h4 {
            color: #8a5a20;
            font-size: 1.2rem;
            margin-bottom: .7rem;
        }

        .promo-info p {
            margin: .4rem 0;
            font-size: 1.05rem;
            color: #806b51;
            line-height: 1.5;
        }

        /* =====================================================
           EMPTY CART
        ===================================================== */

        .empty-cart {
            max-width: 70rem;
            margin: 0 auto;
            background: #fff;
            border: .1rem solid #e8e3dc;
            border-radius: 2rem;
            padding: 6rem 3rem;
            text-align: center;
            box-shadow: 0 1.5rem 3rem rgba(68, 51, 51, .05);
        }

        .empty-icon {
            width: 9rem;
            height: 9rem;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f4efe7;
            color: var(--main-color);
            font-size: 3.5rem;
        }

        .empty-cart h2 {
            font-size: 2.5rem;
            color: #2d211b;
            margin-bottom: 1rem;
        }

        .empty-cart p {
            max-width: 50rem;
            margin: 0 auto 2.5rem;
            color: #777;
            font-size: 1.4rem;
            line-height: 1.8;
        }

        .empty-cart .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .7rem;
            text-decoration: none;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .footer .box-container {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(25rem, 1fr));
            gap: 2rem;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1000px) {

            .cart-layout {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
            }
        }

        @media (max-width: 800px) {

            .cart-page {
                padding-left: 4%;
                padding-right: 4%;
            }

            .user-data {
                display: none;
            }

            .cart-item {
                grid-template-columns:
                    8rem minmax(0, 1fr);
                gap: 1.3rem;
            }

            .cart-image {
                width: 8rem;
                height: 8rem;
            }

            .cart-actions {
                grid-column: 1 / -1;
                width: 100%;
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                padding-left: 9.3rem;
            }

            .promo-input {
                max-width: none;
            }
        }

        @media (max-width: 600px) {

            .header .user-box {
                gap: .8rem;
            }

            .cart-header h1 {
                font-size: 3rem;
            }

            .cart-header p {
                font-size: 1.3rem;
            }

            .promo-box {
                padding: 1.7rem;
            }

            .promo-input {
                flex-direction: column;
            }

            .promo-input button {
                width: 100%;
            }

            .cart-main-header {
                padding: 1.7rem;
            }

            .cart-items {
                padding-left: 1.7rem;
                padding-right: 1.7rem;
            }

            .cart-footer-action {
                padding-left: 1.7rem;
                padding-right: 1.7rem;
            }

            .cart-item {
                grid-template-columns:
                    7.5rem minmax(0, 1fr);
            }

            .cart-image {
                width: 7.5rem;
                height: 7.5rem;
            }

            .cart-info h3 {
                font-size: 1.5rem;
            }

            .cart-info p {
                font-size: 1.1rem;
            }

            .cart-actions {
                padding-left: 8.8rem;
                flex-wrap: wrap;
            }

            .cart-summary {
                border-radius: 1.5rem;
            }

            .summary-body {
                padding: 1.8rem;
            }
        }

        @media (max-width: 450px) {

            .cart-page {
                padding-top: 11rem;
            }

            .cart-header h1 {
                font-size: 2.6rem;
            }

            .cart-step {
                gap: .5rem;
            }

            .step-line {
                width: 2rem;
            }

            .step-item {
                font-size: 1rem;
            }

            .step-number {
                width: 2.6rem;
                height: 2.6rem;
            }

            .cart-main-title h2 {
                font-size: 1.6rem;
            }

            .clear-cart {
                font-size: 1rem;
            }

            .cart-item {
                grid-template-columns:
                    6.5rem minmax(0, 1fr);
            }

            .cart-image {
                width: 6.5rem;
                height: 6.5rem;
            }

            .cart-actions {
                padding-left: 7.8rem;
            }

            .quantity-box {
                transform: scale(.9);
                transform-origin: left center;
            }

            .item-total {
                font-size: 1.4rem;
            }

            .empty-cart {
                padding: 4rem 1.5rem;
            }

            .promo-active {
                align-items: flex-start;
            }
        }
    </style>

</head>

<body>

    <!-- =====================================================
     HEADER
===================================================== -->

    <header class="header">

        <a
            href="index.php"
            class="logo">

            <span class="toku-logo-text">
                TOKU <span>COFFEE</span>
            </span>

        </a>

        <nav class="navbar">

            <a href="index.php">
                beranda
            </a>

            <a href="index.php#menu">
                menu
            </a>

            <a href="index.php#about">
                tentang
            </a>

            <a href="index.php#footer">
                kontak
            </a>

        </nav>

        <div class="user-box">

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
     CART PAGE
===================================================== -->

    <section class="cart-page">

        <div class="cart-header">

            <div class="cart-label">

                <i class="fas fa-shopping-bag"></i>

                TOKU COFFEE

            </div>

            <h1>
                Keranjang <span>Saya</span>
            </h1>

            <p>
                Periksa kembali pilihan minumanmu sebelum
                melanjutkan ke proses checkout.
            </p>

        </div>

        <!-- =================================================
         PROMO
    ================================================== -->

        <div class="promo-box">

            <div class="promo-box-title">

                <i class="fas fa-ticket-alt"></i>

                <div>

                    <h3>
                        Punya Kode Promo?
                    </h3>

                    <p>
                        Gunakan promo dan hemat belanjamu.
                    </p>

                </div>

            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="gunakan_promo">

                <div class="promo-input">

                    <input
                        type="text"
                        name="kode_promo"
                        placeholder="Contoh: TOKUHEMAT"
                        value="<?= e($kodePromoAktif) ?>"
                        autocomplete="off"
                        maxlength="30">

                    <button type="submit">

                        <i class="fas fa-ticket"></i>

                        Gunakan

                    </button>

                </div>

            </form>

            <?php if ($pesanPromo !== ''): ?>

                <div class="promo-message">

                    <i class="fas fa-info-circle"></i>

                    <?= e($pesanPromo) ?>

                </div>

            <?php endif; ?>

            <?php if ($kodePromoAktif !== ''): ?>

                <div class="promo-active">

                    <div>

                        <strong>

                            <i class="fas fa-check-circle"></i>

                            <?= e($namaPromoAktif) ?>

                        </strong>

                        <span>

                            Kode:
                            <?= e($kodePromoAktif) ?>

                            · Hemat
                            <?= rupiah($diskonPromo) ?>

                        </span>

                    </div>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="hapus_promo">

                        <button
                            type="submit"
                            title="Hapus promo">

                            <i class="fas fa-times"></i>

                        </button>

                    </form>

                </div>

            <?php endif; ?>

        </div>

        <!-- =================================================
         STEP CHECKOUT
    ================================================== -->

        <div class="cart-step">

            <div class="step-item active">

                <span class="step-number">
                    1
                </span>

                <span>
                    Keranjang
                </span>

            </div>

            <span class="step-line"></span>

            <div class="step-item">

                <span class="step-number">
                    2
                </span>

                <span>
                    Checkout
                </span>

            </div>

            <span class="step-line"></span>

            <div class="step-item">

                <span class="step-number">
                    3
                </span>

                <span>
                    Selesai
                </span>

            </div>

        </div>

        <?php if (empty($items)): ?>

            <!-- =================================================
             EMPTY CART
        ================================================== -->

            <div class="empty-cart">

                <div class="empty-icon">

                    <i class="fas fa-shopping-bag"></i>

                </div>

                <h2>
                    Keranjangmu masih kosong
                </h2>

                <p>

                    Belum ada menu yang kamu pilih.
                    Yuk, pilih kopi atau minuman non-kopi
                    favoritmu dari menu Toku Coffee.

                </p>

                <a
                    href="index.php#menu"
                    class="btn btn-solid">

                    <i class="fas fa-mug-hot"></i>

                    Lihat Menu

                </a>

            </div>

        <?php else: ?>

            <!-- =================================================
             CART LAYOUT
        ================================================== -->

            <div class="cart-layout">

                <!-- =================================================
                 CART MAIN
            ================================================== -->

                <div class="cart-main">

                    <div class="cart-main-header">

                        <div class="cart-main-title">

                            <i class="fas fa-basket-shopping"></i>

                            <div>

                                <h2>
                                    Produk Pilihan
                                </h2>

                                <span>

                                    <?= $jumlahJenisProduk ?>
                                    jenis menu ·
                                    <?= $totalItem ?>
                                    item

                                </span>

                            </div>

                        </div>

                        <form
                            method="POST"
                            onsubmit="return confirm('Kosongkan semua isi keranjang?');">

                            <input
                                type="hidden"
                                name="action"
                                value="kosongkan">

                            <button
                                type="submit"
                                class="clear-cart">

                                <i class="fas fa-trash"></i>

                                Kosongkan

                            </button>

                        </form>

                    </div>

                    <div class="cart-items">

                        <?php foreach (
                            $items
                            as $item
                        ): ?>

                            <div class="cart-item">

                                <div class="cart-image">

                                    <img
                                        src="<?= e($item['gambar']) ?>"
                                        alt="<?= e($item['nama_produk']) ?>"
                                        loading="lazy"
                                        onerror="this.src='../upload/toku-americano.png';">

                                </div>

                                <div class="cart-info">

                                    <span class="cart-category">

                                        <?= e($item['kategori']) ?>

                                    </span>

                                    <h3>

                                        <?= e($item['nama_produk']) ?>

                                    </h3>

                                    <p>

                                        <?= e(
                                            $item['deskripsi']
                                                ?: 'Nikmati minuman pilihan Toku Coffee dengan cita rasa yang khas.'
                                        ) ?>

                                    </p>

                                    <div class="cart-price">

                                        <?= rupiah($item['harga']) ?>

                                        <span
                                            style="font-weight:400;color:#999;font-size:1.1rem;">

                                            / item

                                        </span>

                                    </div>

                                </div>

                                <div class="cart-actions">

                                    <form
                                        method="POST"
                                        class="quantity-box">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update">

                                        <input
                                            type="hidden"
                                            name="index"
                                            value="<?= (int)$item['index'] ?>">

                                        <button
                                            type="button"
                                            class="qty-btn"
                                            onclick="ubahQty(this, -1)">

                                            <i class="fas fa-minus"></i>

                                        </button>

                                        <input
                                            type="number"
                                            name="qty"
                                            value="<?= (int)$item['qty'] ?>"
                                            min="1"
                                            max="99"
                                            class="qty-input"
                                            aria-label="Jumlah produk">

                                        <button
                                            type="button"
                                            class="qty-btn"
                                            onclick="ubahQty(this, 1)">

                                            <i class="fas fa-plus"></i>

                                        </button>

                                    </form>

                                    <div class="item-total">

                                        <?= rupiah($item['jumlah']) ?>

                                    </div>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Hapus produk ini dari keranjang?');">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="hapus">

                                        <input
                                            type="hidden"
                                            name="index"
                                            value="<?= (int)$item['index'] ?>">

                                        <button
                                            type="submit"
                                            class="delete-item">

                                            <i class="fas fa-trash-can"></i>

                                            Hapus

                                        </button>

                                    </form>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <div class="cart-footer-action">

                        <a
                            href="index.php#menu"
                            class="continue-shopping">

                            <i class="fas fa-arrow-left"></i>

                            Lanjut Belanja

                        </a>

                    </div>

                </div>

                <!-- =================================================
                 SUMMARY
            ================================================== -->

                <aside class="cart-summary">

                    <div class="summary-header">

                        <h2>
                            Ringkasan Pesanan
                        </h2>

                        <p>
                            Detail pembayaran pesananmu
                        </p>

                    </div>

                    <div class="summary-body">

                        <div class="summary-row">

                            <span>
                                Total item
                            </span>

                            <strong>
                                <?= $totalItem ?> item
                            </strong>

                        </div>

                        <div class="summary-row">

                            <span>
                                Jenis menu
                            </span>

                            <strong>
                                <?= $jumlahJenisProduk ?>
                            </strong>

                        </div>

                        <div class="summary-row">

                            <span>
                                Subtotal
                            </span>

                            <strong>
                                <?= rupiah($subtotal) ?>
                            </strong>

                        </div>

                        <div class="summary-row shipping">

                            <span>
                                Ongkir
                            </span>

                            <strong>

                                <?php if ($ongkir <= 0): ?>

                                    Gratis

                                <?php else: ?>

                                    <?= rupiah($ongkir) ?>

                                <?php endif; ?>

                            </strong>

                        </div>

                        <?php if ($diskonPromo > 0): ?>

                            <div class="summary-row discount">

                                <span>

                                    <i class="fas fa-ticket"></i>

                                    Diskon Promo

                                </span>

                                <strong>

                                    - <?= rupiah($diskonPromo) ?>

                                </strong>

                            </div>

                        <?php endif; ?>

                        <hr class="summary-divider">

                        <?php if ($diskonPromo > 0): ?>

                            <div class="summary-row">

                                <span>
                                    Sebelum Diskon
                                </span>

                                <strong>
                                    <?= rupiah($totalSebelumDiskon) ?>
                                </strong>

                            </div>

                        <?php endif; ?>

                        <div class="summary-total">

                            <span>
                                Total Pembayaran
                            </span>

                            <span>
                                <?= rupiah($totalBayar) ?>
                            </span>

                        </div>

                        <a
                            href="checkout.php"
                            class="checkout-btn">

                            Lanjut ke Checkout

                            <i class="fas fa-arrow-right"></i>

                        </a>

                        <div class="free-shipping">

                            <i class="fas fa-truck-fast"></i>

                            <span>

                                Pesananmu siap diproses.
                                Ongkir saat ini gratis.

                            </span>

                        </div>

                        <!-- =================================================
                         INFO PROMO
                    ================================================== -->

                        <div class="promo-info">

                            <h4>

                                <i class="fas fa-gift"></i>

                                Promo Toku Coffee

                            </h4>

                            <p>
                                <strong>TOKUBARU</strong>
                                — Diskon 20% maksimal Rp15.000.
                            </p>

                            <p>
                                <strong>TOKUHEMAT</strong>
                                — Potongan Rp10.000.
                            </p>

                            <p>
                                <strong>TOKUWEEKEND</strong>
                                — Diskon 15% maksimal Rp20.000.
                            </p>

                        </div>

                        <div class="payment-info">

                            <h4>
                                Metode Pembayaran
                            </h4>

                            <div class="payment-methods">

                                <span class="payment-method">

                                    <i class="fas fa-qrcode"></i>

                                    QRIS

                                </span>

                                <span class="payment-method">

                                    <i class="fas fa-wallet"></i>

                                    E-Wallet

                                </span>

                                <span class="payment-method">

                                    <i class="fas fa-money-bill"></i>

                                    Tunai

                                </span>

                            </div>

                        </div>

                    </div>

                </aside>

            </div>

        <?php endif; ?>

    </section>

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

                <a href="index.php">
                    beranda
                </a>

                <a href="index.php#menu">
                    menu
                </a>

                <a href="index.php#about">
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

                <a href="index.php#menu">

                    <i class="fas fa-mug-hot"></i>

                    Semua Menu

                </a>

                <a href="index.php#menu">

                    <i class="fas fa-coffee"></i>

                    Kopi

                </a>

                <a href="index.php#menu">

                    <i class="fas fa-glass-water"></i>

                    Non-Kopi

                </a>

                <a href="keranjang.php">

                    <i class="fas fa-shopping-bag"></i>

                    Keranjang
                    (<?= $jumlahKeranjang ?>)

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
        /* =====================================================
       MOBILE NAVBAR
    ===================================================== */

        const menuBtn =
            document.querySelector('#menu-btn');

        const navbar =
            document.querySelector('.navbar');

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
           UBAH QUANTITY
        ===================================================== */

        function ubahQty(button, nilai) {

            const form =
                button.closest('form');

            if (!form) {
                return;
            }

            const input =
                form.querySelector('.qty-input');

            if (!input) {
                return;
            }

            let qty =
                parseInt(input.value) || 1;

            qty += nilai;

            if (qty < 1) {
                qty = 1;
            }

            if (qty > 99) {
                qty = 99;
            }

            input.value = qty;

            clearTimeout(
                form._updateTimer
            );

            form._updateTimer =
                setTimeout(() => {

                    form.submit();

                }, 250);
        }

        /* =====================================================
           VALIDASI INPUT QUANTITY
        ===================================================== */

        document.querySelectorAll(
            '.qty-input'
        ).forEach(input => {

            input.addEventListener(
                'change',
                function() {

                    let value =
                        parseInt(this.value) || 1;

                    if (value < 1) {
                        value = 1;
                    }

                    if (value > 99) {
                        value = 99;
                    }

                    this.value = value;

                    this.form.submit();

                }
            );

        });
    </script>

</body>

</html>
