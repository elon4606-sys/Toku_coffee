<?php

/* =========================================================
   CHECKOUT - TOKU COFFEE
   COD -> LANGSUNG PESANAN SELESAI
   TRANSFER / QRIS / E-WALLET -> PEMBAYARAN.PHP

   PROMO:
   TOKUBARU   -> Diskon 20% max Rp15.000, min Rp50.000
   TOKUHEMAT  -> Potongan Rp10.000, min Rp75.000
   TOKUWEEKEND -> Diskon 15% max Rp20.000, min Rp100.000
========================================================= */

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
   AMBIL DATA USER
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

        $resultUser = $stmtUser->get_result();

        if ($resultUser) {
            $dataUser =
                $resultUser->fetch_assoc()
                ?? [];
        }

        $stmtUser->close();
    }
}

/* =========================================================
   DATA DEFAULT CHECKOUT
========================================================= */

$namaCheckout =
    $dataUser['full_name']
    ?? $namaUser;

$emailCheckout =
    $dataUser['email']
    ?? $emailUser;

$teleponCheckout =
    $dataUser['phone']
    ?? '';

$kotaCheckout =
    $dataUser['city']
    ?? '';

$alamatCheckout =
    $dataUser['address']
    ?? '';

$metodePembayaranCheckout =
    '';

/* =========================================================
   JUMLAH KERANJANG
========================================================= */

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
            (int)($item['qty'] ?? 0);
    }
}

/* =========================================================
   CEK KERANJANG
========================================================= */

if (
    !isset($_SESSION['keranjang']) ||
    !is_array($_SESSION['keranjang']) ||
    empty($_SESSION['keranjang'])
) {

    header(
        "Location: keranjang.php?error=keranjang_kosong"
    );

    exit;
}

/* =========================================================
   AMBIL ID PRODUK
========================================================= */

$idProdukList = [];

foreach (
    $_SESSION['keranjang']
    as $item
) {

    $id =
        (int)($item['id'] ?? 0);

    $qty =
        (int)($item['qty'] ?? 0);

    if (
        $id > 0 &&
        $qty > 0
    ) {

        $idProdukList[] = $id;
    }
}

/* =========================================================
   DATA PRODUK CHECKOUT
========================================================= */

$produkCheckout = [];

$totalItem = 0;

$subtotal = 0;

/* =========================================================
   QUERY PRODUK
========================================================= */

if (!empty($idProdukList)) {

    $idProdukList =
        array_values(
            array_unique(
                $idProdukList
            )
        );

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($idProdukList),
                '?'
            )
        );

    $types =
        str_repeat(
            'i',
            count($idProdukList)
        );

    $sqlProduk = "
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
        WHERE p.id IN ($placeholders)
        AND p.status = 'aktif'
    ";

    $stmtProduk =
        $conn->prepare(
            $sqlProduk
        );

    if ($stmtProduk) {

        $stmtProduk->bind_param(
            $types,
            ...$idProdukList
        );

        $stmtProduk->execute();

        $resultProduk =
            $stmtProduk->get_result();

        $dataProdukDB = [];

        if ($resultProduk) {

            while (
                $row =
                $resultProduk->fetch_assoc()
            ) {

                $dataProdukDB[(int)$row['id']] = $row;
            }
        }

        $stmtProduk->close();

        /* =================================================
           GABUNG DATA PRODUK DENGAN KERANJANG
        ================================================= */

        foreach (
            $_SESSION['keranjang']
            as $item
        ) {

            $id =
                (int)($item['id'] ?? 0);

            $qty =
                (int)($item['qty'] ?? 0);

            if (
                $id <= 0 ||
                $qty <= 0 ||
                !isset(
                    $dataProdukDB[$id]
                )
            ) {
                continue;
            }

            $produk =
                $dataProdukDB[$id];

            $harga =
                (float)$produk['harga'];

            $jumlah =
                $harga * $qty;

            /* =================================================
               GAMBAR PRODUK
            ================================================= */

            $gambar =
                trim(
                    $produk['gambar'] ?? ''
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

            if (
                $kategoriDB === 'kopi'
            ) {

                $kategori =
                    'Kopi';
            } elseif (
                $kategoriDB === 'minuman' ||
                $kategoriDB === 'non coffee' ||
                $kategoriDB === 'non-kopi' ||
                $kategoriDB === 'non kopi'
            ) {

                $kategori =
                    'Non-Kopi';
            } else {

                $kategori =
                    $produk['nama_kategori']
                    ?? 'Umum';
            }

            /* =================================================
               SIMPAN DATA PRODUK
            ================================================= */

            $produkCheckout[] = [

                'id' =>
                $id,

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

                'kategori' =>
                $kategori
            ];

            $totalItem += $qty;

            $subtotal += $jumlah;
        }
    }
}

/* =========================================================
   PRODUK TIDAK VALID
========================================================= */

if (empty($produkCheckout)) {

    $_SESSION['keranjang'] = [];

    header(
        "Location: keranjang.php?error=produk_tidak_valid"
    );

    exit;
}

/* =========================================================
   ONGKIR
========================================================= */

$ongkir = 0;

/* =========================================================
   PROMO / DISKON
========================================================= */

$kodePromo =
    strtoupper(
        trim(
            $_SESSION['promo_code'] ?? ''
        )
    );

$namaPromo =
    trim(
        $_SESSION['promo_name'] ?? ''
    );

$diskonPromo =
    (float)(
        $_SESSION['promo_discount'] ?? 0
    );

/* =========================================================
   VALIDASI ULANG PROMO
========================================================= */

if ($kodePromo !== '') {

    /* =====================================================
       TOKUBARU
    ===================================================== */

    if ($kodePromo === 'TOKUBARU') {

        $jumlahPesananUser = 0;

        $stmtPromo =
            $conn->prepare("
                SELECT COUNT(*) AS total
                FROM pesanan
                WHERE user_id = ?
                AND status != 'Dibatalkan'
            ");

        if ($stmtPromo) {

            $stmtPromo->bind_param(
                "i",
                $userId
            );

            $stmtPromo->execute();

            $resultPromo =
                $stmtPromo->get_result();

            if ($resultPromo) {

                $dataPromo =
                    $resultPromo->fetch_assoc();

                $jumlahPesananUser =
                    (int)(
                        $dataPromo['total']
                        ?? 0
                    );
            }

            $stmtPromo->close();
        }

        /* =================================================
           CEK SYARAT
        ================================================= */

        if (
            $subtotal < 50000 ||
            $jumlahPesananUser > 0
        ) {

            $kodePromo = '';

            $namaPromo = '';

            $diskonPromo = 0;

            unset(
                $_SESSION['promo_code'],
                $_SESSION['promo_name'],
                $_SESSION['promo_discount']
            );
        } else {

            $diskonPromo =
                min(
                    $subtotal * 0.20,
                    15000
                );

            $namaPromo =
                'Diskon 20% Pelanggan Baru';
        }
    }

    /* =====================================================
       TOKUHEMAT
    ===================================================== */ elseif (
        $kodePromo === 'TOKUHEMAT'
    ) {

        if (
            $subtotal >= 75000
        ) {

            $diskonPromo =
                10000;

            $namaPromo =
                'Potongan Rp10.000';
        } else {

            $kodePromo = '';

            $namaPromo = '';

            $diskonPromo = 0;

            unset(
                $_SESSION['promo_code'],
                $_SESSION['promo_name'],
                $_SESSION['promo_discount']
            );
        }
    }

    /* =====================================================
       TOKUWEEKEND
    ===================================================== */ elseif (
        $kodePromo === 'TOKUWEEKEND'
    ) {

        if (
            $subtotal >= 100000
        ) {

            $diskonPromo =
                min(
                    $subtotal * 0.15,
                    20000
                );

            $namaPromo =
                'Weekend Coffee Sale';
        } else {

            $kodePromo = '';

            $namaPromo = '';

            $diskonPromo = 0;

            unset(
                $_SESSION['promo_code'],
                $_SESSION['promo_name'],
                $_SESSION['promo_discount']
            );
        }
    }

    /* =====================================================
       PROMO TIDAK DIKENAL
    ===================================================== */ else {

        $kodePromo = '';

        $namaPromo = '';

        $diskonPromo = 0;

        unset(
            $_SESSION['promo_code'],
            $_SESSION['promo_name'],
            $_SESSION['promo_discount']
        );
    }
}

/* =========================================================
   BATASI DISKON
========================================================= */

$diskonPromo =
    min(
        max(
            0,
            $diskonPromo
        ),
        $subtotal
    );

/* =========================================================
   TOTAL BAYAR
========================================================= */

$totalSetelahPromo =
    max(
        0,
        $subtotal - $diskonPromo
    );

$totalBayar =
    $totalSetelahPromo + $ongkir;

/* =========================================================
   ERROR
========================================================= */

$errorCheckout = '';

/* =========================================================
   PROSES CHECKOUT
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'buat_pesanan'
) {

    /* =====================================================
       DATA FORM
    ===================================================== */

    $nama =
        trim(
            $_POST['nama'] ?? ''
        );

    $email =
        trim(
            $_POST['email'] ?? ''
        );

    $telepon =
        trim(
            $_POST['telepon'] ?? ''
        );

    $kota =
        trim(
            $_POST['kota'] ?? ''
        );

    $alamat =
        trim(
            $_POST['alamat'] ?? ''
        );

    $metodePembayaran =
        trim(
            $_POST['metode_pembayaran'] ?? ''
        );

    /* =====================================================
       TAMPILKAN KEMBALI DATA FORM
    ===================================================== */

    $namaCheckout =
        $nama;

    $emailCheckout =
        $email;

    $teleponCheckout =
        $telepon;

    $kotaCheckout =
        $kota;

    $alamatCheckout =
        $alamat;

    $metodePembayaranCheckout =
        $metodePembayaran;

    /* =====================================================
       VALIDASI
    ===================================================== */

    if ($nama === '') {

        $errorCheckout =
            'Nama penerima wajib diisi.';
    } elseif ($telepon === '') {

        $errorCheckout =
            'Nomor telepon wajib diisi.';
    } elseif ($kota === '') {

        $errorCheckout =
            'Kota wajib diisi.';
    } elseif ($alamat === '') {

        $errorCheckout =
            'Alamat lengkap wajib diisi.';
    } elseif (
        !in_array(
            $metodePembayaran,
            [
                'COD',
                'QRIS',
                'Transfer Bank',
                'E-Wallet'
            ],
            true
        )
    ) {

        $errorCheckout =
            'Silakan pilih metode pembayaran.';
    } elseif ($userId <= 0) {

        $errorCheckout =
            'Data pengguna tidak ditemukan.';
    } else {

        /* =================================================
           BUAT INVOICE
        ================================================= */

        $invoice =
            'TOKU-' .
            date('YmdHis') .
            '-' .
            strtoupper(
                substr(
                    bin2hex(
                        random_bytes(3)
                    ),
                    0,
                    6
                )
            );

        /* =================================================
           DATA CHECKOUT KE SESSION
        ================================================= */

        $_SESSION['checkout'] = [

            'user_id' =>
            $userId,

            'nama' =>
            $nama,

            'email' =>
            $email,

            'telepon' =>
            $telepon,

            'kota' =>
            $kota,

            'alamat' =>
            $alamat,

            'metode_pembayaran' =>
            $metodePembayaran,

            'subtotal' =>
            $subtotal,

            'kode_promo' =>
            $kodePromo,

            'nama_promo' =>
            $namaPromo,

            'diskon_promo' =>
            $diskonPromo,

            'ongkir' =>
            $ongkir,

            'total' =>
            $totalBayar,

            'invoice' =>
            $invoice,

            'tanggal' =>
            date('Y-m-d H:i:s')
        ];

        /* =================================================
           JIKA COD
        ================================================= */

        if (
            $metodePembayaran === 'COD'
        ) {

            try {

                $conn->begin_transaction();

                /* =========================================
                   INSERT PESANAN
                ========================================= */

                $sqlPesanan = "
                    INSERT INTO pesanan
                    (
                        user_id,
                        invoice,
                        total,
                        metode_pembayaran,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        'Menunggu'
                    )
                ";

                $stmtPesanan =
                    $conn->prepare(
                        $sqlPesanan
                    );

                if (!$stmtPesanan) {

                    throw new Exception(
                        'Gagal menyiapkan pesanan: ' .
                            $conn->error
                    );
                }

                $stmtPesanan->bind_param(
                    "isds",
                    $userId,
                    $invoice,
                    $totalBayar,
                    $metodePembayaran
                );

                if (
                    !$stmtPesanan->execute()
                ) {

                    throw new Exception(
                        'Gagal menyimpan pesanan: ' .
                            $stmtPesanan->error
                    );
                }

                $pesananId =
                    $conn->insert_id;

                $stmtPesanan->close();

                /* =========================================
                   INSERT DETAIL
                ========================================= */

                $sqlDetail = "
                    INSERT INTO detail_pesanan
                    (
                        pesanan_id,
                        produk_id,
                        jumlah,
                        harga
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ";

                $stmtDetail =
                    $conn->prepare(
                        $sqlDetail
                    );

                if (!$stmtDetail) {

                    throw new Exception(
                        'Gagal menyiapkan detail pesanan: ' .
                            $conn->error
                    );
                }

                foreach (
                    $produkCheckout
                    as $produk
                ) {

                    $produkId =
                        (int)$produk['id'];

                    $jumlahProduk =
                        (int)$produk['qty'];

                    $hargaProduk =
                        (float)$produk['harga'];

                    $stmtDetail->bind_param(
                        "iiid",
                        $pesananId,
                        $produkId,
                        $jumlahProduk,
                        $hargaProduk
                    );

                    if (
                        !$stmtDetail->execute()
                    ) {

                        throw new Exception(
                            'Gagal menyimpan detail pesanan: ' .
                                $stmtDetail->error
                        );
                    }
                }

                $stmtDetail->close();

                /* =========================================
                   COMMIT
                ========================================= */

                $conn->commit();

                /* =========================================
                   SIMPAN PESANAN TERAKHIR
                ========================================= */

                $_SESSION['pesanan_terakhir'] = [

                    'pesanan_id' =>
                    $pesananId,

                    'invoice' =>
                    $invoice,

                    'nama' =>
                    $nama,

                    'email' =>
                    $email,

                    'telepon' =>
                    $telepon,

                    'kota' =>
                    $kota,

                    'alamat' =>
                    $alamat,

                    'metode_pembayaran' =>
                    $metodePembayaran,

                    'subtotal' =>
                    $subtotal,

                    'kode_promo' =>
                    $kodePromo,

                    'nama_promo' =>
                    $namaPromo,

                    'diskon_promo' =>
                    $diskonPromo,

                    'total' =>
                    $totalBayar,

                    'tanggal' =>
                    date('Y-m-d H:i:s')
                ];

                /* =========================================
                   KOSONGKAN KERANJANG
                ========================================= */

                $_SESSION['keranjang'] = [];

                /* =========================================
                   HAPUS CHECKOUT SEMENTARA
                ========================================= */

                unset(
                    $_SESSION['checkout']
                );

                /* =========================================
                   HAPUS PROMO
                ========================================= */

                unset(
                    $_SESSION['promo_code'],
                    $_SESSION['promo_discount'],
                    $_SESSION['promo_name']
                );

                /* =========================================
                   LANGSUNG PESANAN SELESAI
                ========================================= */

                header(
                    "Location: pesanan-selesai.php"
                );

                exit;
            } catch (Throwable $e) {

                if (
                    method_exists(
                        $conn,
                        'rollback'
                    )
                ) {

                    $conn->rollback();
                }

                $errorCheckout =
                    $e->getMessage();
            }
        } else {

            /* =================================================
               TRANSFER / QRIS / E-WALLET

               Tidak insert database di sini.
               Data checkout dibawa ke pembayaran.php.
            ================================================= */

            header(
                "Location: pembayaran.php"
            );

            exit;
        }
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
        Checkout - Toku Coffee
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
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

        .checkout-hero {
            padding: 12rem 7% 4rem;
            text-align: center;
            background: #faf9f5;
        }

        .checkout-label {
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

        .checkout-hero h1 {
            font-size: 3.5rem;
            color: #2d211b;
            margin-bottom: .8rem;
        }

        .checkout-hero h1 span {
            color: var(--main-color);
        }

        .checkout-hero p {
            font-size: 1.5rem;
            color: #777;
        }

        .checkout-steps {
            max-width: 70rem;
            margin: 0 auto 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .step {
            display: flex;
            align-items: center;
            gap: .8rem;
            color: #999;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .step.active {
            color: var(--main-color);
        }

        .step-number {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: .1rem solid #ddd;
            background: #fff;
            font-weight: 600;
        }

        .step.active .step-number {
            background: var(--main-color);
            border-color: var(--main-color);
            color: #fff;
        }

        .step-line {
            width: 7rem;
            height: .1rem;
            background: #ddd;
            margin: 0 1rem;
        }

        .checkout-section {
            padding: 1rem 7% 7rem;
            background: #faf9f5;
        }

        .checkout-container {
            max-width: 120rem;
            margin: 0 auto;
            display: grid;
            grid-template-columns:
                minmax(0, 1.5fr) minmax(32rem, .8fr);
            gap: 2.5rem;
            align-items: start;
        }

        .checkout-card {
            background: #fff;
            border: .1rem solid #e8e3dc;
            border-radius: 1.8rem;
            padding: 2.5rem;
            box-shadow:
                0 1.5rem 3rem rgba(68, 51, 51, .06);
        }

        .checkout-card h2 {
            font-size: 2rem;
            color: #2d211b;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: .9rem;
        }

        .checkout-card h2 i {
            color: var(--main-color);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: .1rem solid #eee;
        }

        .card-header h2 {
            margin: 0;
        }

        .item-badge {
            background: #f4efe7;
            color: var(--main-color);
            padding: .6rem 1.2rem;
            border-radius: 5rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.7rem;
        }

        .form-group label {
            display: block;
            font-size: 1.25rem;
            color: #443;
            font-weight: 600;
            margin-bottom: .7rem;
        }

        .form-group label i {
            color: var(--main-color);
            margin-right: .4rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            border: .1rem solid #ddd;
            border-radius: 1rem;
            padding: 1.2rem 1.4rem;
            font-family: inherit;
            font-size: 1.25rem;
            color: #443;
            background: #fff;
            outline: none;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--main-color);
        }

        .form-group textarea {
            min-height: 11rem;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .checkout-error {
            display: flex;
            align-items: flex-start;
            gap: .8rem;
            background: #fff0f0;
            border: .1rem solid #e4b2b2;
            color: #a94442;
            border-radius: 1rem;
            padding: 1.3rem 1.5rem;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        .payment-title {
            margin-top: 3rem !important;
            margin-bottom: 1.5rem !important;
        }

        .payment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.4rem;
            border: .1rem solid #ddd;
            border-radius: 1.2rem;
            background: #fff;
            cursor: pointer;
            transition: .2s ease;
        }

        .payment-option:hover {
            border-color: var(--main-color);
            background: #fdfaf6;
        }

        .payment-option input {
            accent-color: var(--main-color);
            width: 1.7rem;
            height: 1.7rem;
        }

        .payment-option i {
            width: 2.5rem;
            text-align: center;
            color: var(--main-color);
            font-size: 1.7rem;
        }

        .payment-option span {
            font-size: 1.2rem;
            color: #443;
        }

        .order-list {
            margin-bottom: 1rem;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1.3rem;
            padding: 1.4rem 0;
            border-bottom: .1rem solid #eee;
        }

        .order-item-image {
            position: relative;
            width: 7rem;
            height: 7rem;
            border-radius: 1.2rem;
            overflow: hidden;
            background: #f5f1ea;
            flex-shrink: 0;
        }

        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-item-qty {
            position: absolute;
            right: .4rem;
            bottom: .4rem;
            min-width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--main-color);
            color: #fff;
            border-radius: .5rem;
            font-size: 1rem;
            font-weight: 600;
        }

        .order-item-info {
            flex: 1;
            min-width: 0;
        }

        .order-item-category {
            display: inline-block;
            font-size: 1rem;
            color: var(--main-color);
            background: #f4efe7;
            border-radius: 5rem;
            padding: .3rem .8rem;
            margin-bottom: .4rem;
        }

        .order-item-info h3 {
            font-size: 1.35rem;
            color: #2d211b;
            margin-bottom: .3rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .order-item-info p {
            font-size: 1.1rem;
            color: #999;
        }

        .order-item-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--main-color);
            white-space: nowrap;
        }

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

        .free-shipping {
            color: #527853 !important;
        }

        .promo-checkout {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #f4f8f2;
            border: .1rem solid #dbe8d5;
            border-radius: 1rem;
            padding: 1.2rem 1.4rem;
            margin-top: 1rem;
        }

        .promo-checkout-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .promo-checkout-icon {
            width: 3.6rem;
            height: 3.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #527853;
            color: #fff;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .promo-checkout-text strong {
            display: block;
            color: #527853;
            font-size: 1.15rem;
        }

        .promo-checkout-text span {
            display: block;
            color: #777;
            font-size: 1rem;
            margin-top: .2rem;
        }

        .promo-checkout-code {
            font-size: 1rem;
            font-weight: 700;
            color: var(--main-color);
            background: #fff;
            border: .1rem solid #ddd;
            padding: .5rem .8rem;
            border-radius: .6rem;
            white-space: nowrap;
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

        .btn-checkout {
            width: 100%;
            border: none;
            background: var(--main-color);
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-size: 1.3rem;
            font-weight: 600;
            padding: 1.4rem 2rem;
            border-radius: 1rem;
            transition: .2s ease;
            margin-top: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .8rem;
        }

        .btn-checkout:hover {
            background: #2f2424;
            transform: translateY(-2px);
        }

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
            margin-top: 1rem;
        }

        .free-box {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #f4f8f2;
            border: .1rem solid #dbe8d5;
            border-radius: 1rem;
            padding: 1.2rem;
            margin-top: 1.5rem;
        }

        .free-box i {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #527853;
            color: #fff;
        }

        .free-box strong {
            display: block;
            color: #527853;
            font-size: 1.2rem;
        }

        .free-box span {
            color: #777;
            font-size: 1.05rem;
        }

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

        #menu-btn {
            display: none;
            font-size: 2.5rem;
            color: var(--main-color);
            cursor: pointer;
        }

        @media (max-width: 991px) {

            .checkout-container {
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

            .form-row,
            .payment-options {
                grid-template-columns: 1fr;
            }

            .checkout-card {
                padding: 2rem;
            }

            .checkout-hero h1 {
                font-size: 2.8rem;
            }
        }

        @media (max-width: 550px) {

            .checkout-hero {
                padding: 10rem 5% 3rem;
            }

            .checkout-section {
                padding: 1rem 5% 5rem;
            }

            .checkout-card {
                padding: 1.5rem;
            }

            .checkout-steps {
                padding: 0 1rem;
            }

            .step {
                font-size: 0;
            }

            .step-line {
                flex: 1;
                width: auto;
                margin: 0 .7rem;
            }

            .promo-checkout {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

</head>

<body>

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

                <?php if ($jumlahKeranjang > 0): ?>

                    <span class="count">
                        <?= $jumlahKeranjang ?>
                    </span>

                <?php endif; ?>

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


    <!-- ======================================================
         HERO
    ======================================================= -->

    <section class="checkout-hero">

        <div class="checkout-label">

            <i class="fas fa-receipt"></i>

            TOKU COFFEE CHECKOUT

        </div>

        <h1>

            Selesaikan

            <span>
                Pesananmu
            </span>

        </h1>

        <p>

            Lengkapi data pengiriman dan pilih metode pembayaran.

        </p>

    </section>


    <!-- ======================================================
         STEP
    ======================================================= -->

    <div class="checkout-steps">

        <div class="step active">

            <div class="step-number">

                <i class="fas fa-shopping-bag"></i>

            </div>

            <span>
                Keranjang
            </span>

        </div>

        <div class="step-line"></div>

        <div class="step active">

            <div class="step-number">

                <i class="fas fa-location-dot"></i>

            </div>

            <span>
                Checkout
            </span>

        </div>

        <div class="step-line"></div>

        <div class="step">

            <div class="step-number">

                <i class="fas fa-check"></i>

            </div>

            <span>
                Selesai
            </span>

        </div>

    </div>


    <!-- ======================================================
         CHECKOUT
    ======================================================= -->

    <section class="checkout-section">

        <div class="checkout-container">


            <!-- ==================================================
                 FORM
            =================================================== -->

            <div class="checkout-card">

                <div class="card-header">

                    <h2>

                        <i class="fas fa-location-dot"></i>

                        Data Pengiriman

                    </h2>

                    <span class="item-badge">

                        <?= (int)$totalItem ?>

                        item

                    </span>

                </div>


                <?php if ($errorCheckout !== ''): ?>

                    <div class="checkout-error">

                        <i class="fas fa-circle-exclamation"></i>

                        <span>

                            <?= e($errorCheckout) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action="checkout.php"
                    onsubmit="return konfirmasiPesanan();">

                    <input
                        type="hidden"
                        name="action"
                        value="buat_pesanan">


                    <!-- NAMA -->

                    <div class="form-group">

                        <label>

                            <i class="fas fa-user"></i>

                            Nama Penerima

                        </label>

                        <input
                            type="text"
                            name="nama"
                            value="<?= e($namaCheckout) ?>"
                            placeholder="Masukkan nama penerima"
                            required>

                    </div>


                    <!-- EMAIL + TELEPON -->

                    <div class="form-row">

                        <div class="form-group">

                            <label>

                                <i class="fas fa-envelope"></i>

                                Email

                            </label>

                            <input
                                type="email"
                                name="email"
                                value="<?= e($emailCheckout) ?>"
                                placeholder="contoh@email.com">

                        </div>


                        <div class="form-group">

                            <label>

                                <i class="fas fa-phone"></i>

                                Nomor Telepon

                            </label>

                            <input
                                type="text"
                                name="telepon"
                                value="<?= e($teleponCheckout) ?>"
                                placeholder="08xxxxxxxxxx"
                                required>

                        </div>

                    </div>


                    <!-- KOTA -->

                    <div class="form-group">

                        <label>

                            <i class="fas fa-city"></i>

                            Kota

                        </label>

                        <input
                            type="text"
                            name="kota"
                            value="<?= e($kotaCheckout) ?>"
                            placeholder="Contoh: Jayapura"
                            required>

                    </div>


                    <!-- ALAMAT -->

                    <div class="form-group">

                        <label>

                            <i class="fas fa-map-location-dot"></i>

                            Alamat Lengkap

                        </label>

                        <textarea
                            name="alamat"
                            placeholder="Masukkan alamat lengkap pengiriman..."
                            required><?= e($alamatCheckout) ?></textarea>

                    </div>


                    <!-- METODE PEMBAYARAN -->

                    <h2 class="payment-title">

                        <i class="fas fa-credit-card"></i>

                        Metode Pembayaran

                    </h2>


                    <div class="payment-options">


                        <!-- COD -->

                        <label class="payment-option">

                            <input
                                type="radio"
                                name="metode_pembayaran"
                                value="COD"
                                <?= $metodePembayaranCheckout === 'COD'
                                    ? 'checked'
                                    : '' ?>
                                required>

                            <i class="fas fa-money-bill-wave"></i>

                            <span>
                                COD / Bayar di Tempat
                            </span>

                        </label>


                        <!-- QRIS -->

                        <label class="payment-option">

                            <input
                                type="radio"
                                name="metode_pembayaran"
                                value="QRIS"
                                <?= $metodePembayaranCheckout === 'QRIS'
                                    ? 'checked'
                                    : '' ?>>

                            <i class="fas fa-qrcode"></i>

                            <span>
                                QRIS
                            </span>

                        </label>


                        <!-- TRANSFER BANK -->

                        <label class="payment-option">

                            <input
                                type="radio"
                                name="metode_pembayaran"
                                value="Transfer Bank"
                                <?= $metodePembayaranCheckout === 'Transfer Bank'
                                    ? 'checked'
                                    : '' ?>>

                            <i class="fas fa-building-columns"></i>

                            <span>
                                Transfer Bank
                            </span>

                        </label>


                        <!-- E-WALLET -->

                        <label class="payment-option">

                            <input
                                type="radio"
                                name="metode_pembayaran"
                                value="E-Wallet"
                                <?= $metodePembayaranCheckout === 'E-Wallet'
                                    ? 'checked'
                                    : '' ?>>

                            <i class="fas fa-wallet"></i>

                            <span>
                                E-Wallet
                            </span>

                        </label>

                    </div>


                    <!-- BUTTON -->

                    <button
                        type="submit"
                        class="btn-checkout">

                        <i class="fas fa-check-circle"></i>

                        Lanjutkan Pesanan

                    </button>


                    <a
                        href="keranjang.php"
                        class="btn-kembali">

                        <i class="fas fa-arrow-left"></i>

                        Kembali ke Keranjang

                    </a>

                </form>

            </div>


            <!-- ==================================================
                 RINGKASAN PESANAN
            =================================================== -->

            <div>

                <div class="checkout-card">

                    <div class="card-header">

                        <h2>

                            <i class="fas fa-receipt"></i>

                            Pesanan Saya

                        </h2>

                        <span class="item-badge">

                            <?= count($produkCheckout) ?>

                            produk

                        </span>

                    </div>


                    <div class="order-list">

                        <?php foreach (
                            $produkCheckout
                            as $produk
                        ): ?>

                            <div class="order-item">

                                <div class="order-item-image">

                                    <img
                                        src="<?= e($produk['gambar']) ?>"
                                        alt="<?= e($produk['nama_produk']) ?>"
                                        onerror="this.src='../upload/toku-americano.png';">

                                    <span class="order-item-qty">

                                        <?= (int)$produk['qty'] ?>

                                    </span>

                                </div>


                                <div class="order-item-info">

                                    <span class="order-item-category">

                                        <?= e($produk['kategori']) ?>

                                    </span>

                                    <h3>

                                        <?= e(
                                            $produk['nama_produk']
                                        ) ?>

                                    </h3>

                                    <p>

                                        <?= rupiah(
                                            $produk['harga']
                                        ) ?>

                                        / item

                                    </p>

                                </div>


                                <div class="order-item-price">

                                    <?= rupiah(
                                        $produk['jumlah']
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- SUMMARY -->

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

                                <?= rupiah($subtotal) ?>

                            </strong>

                        </div>


                        <!-- PROMO -->

                        <?php if ($diskonPromo > 0): ?>

                            <div class="summary-row">

                                <span>

                                    <i class="fas fa-tag"></i>

                                    Promo
                                    (<?= e($kodePromo) ?>)

                                </span>

                                <strong
                                    style="color:#527853;">

                                    - <?= rupiah($diskonPromo) ?>

                                </strong>

                            </div>

                        <?php endif; ?>


                        <div class="summary-row">

                            <span>
                                Ongkir
                            </span>

                            <strong class="free-shipping">

                                Gratis

                            </strong>

                        </div>


                        <div class="summary-total">

                            <span>
                                Total Pembayaran
                            </span>

                            <strong>

                                <?= rupiah($totalBayar) ?>

                            </strong>

                        </div>

                    </div>


                    <!-- PROMO AKTIF -->

                    <?php if ($diskonPromo > 0): ?>

                        <div class="promo-checkout">

                            <div class="promo-checkout-left">

                                <div class="promo-checkout-icon">

                                    <i class="fas fa-tag"></i>

                                </div>

                                <div class="promo-checkout-text">

                                    <strong>

                                        Promo berhasil digunakan

                                    </strong>

                                    <span>

                                        <?= e($namaPromo) ?>

                                    </span>

                                </div>

                            </div>

                            <div class="promo-checkout-code">

                                <?= e($kodePromo) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- GRATIS ONGKIR -->

                    <div class="free-box">

                        <i class="fas fa-truck-fast"></i>

                        <div>

                            <strong>
                                Gratis Ongkir
                            </strong>

                            <span>
                                Biaya pengiriman saat ini gratis.
                            </span>

                        </div>

                    </div>

                </div>


                <!-- SECURITY -->

                <div class="security-info">

                    <h3>

                        <i class="fas fa-shield-halved"></i>

                        Checkout Aman

                    </h3>

                    <p>

                        Pastikan nama penerima, nomor telepon,
                        kota, alamat dan metode pembayaran sudah
                        benar sebelum melanjutkan pesanan.

                    </p>

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
                    (<?= $jumlahKeranjang ?>)

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


    <script>
        /* =====================================================
           MOBILE MENU
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
           KONFIRMASI PESANAN
        ===================================================== */

        function konfirmasiPesanan() {

            const nama =
                document.querySelector(
                    'input[name="nama"]'
                )?.value.trim();

            const metode =
                document.querySelector(
                    'input[name="metode_pembayaran"]:checked'
                )?.value;

            if (!nama) {

                alert(
                    'Nama penerima wajib diisi.'
                );

                return false;
            }

            if (!metode) {

                alert(
                    'Silakan pilih metode pembayaran.'
                );

                return false;
            }

            <?php if ($diskonPromo > 0): ?>

                const promoAktif =
                    <?= json_encode($kodePromo) ?>;

                const diskon =
                    <?= json_encode(rupiah($diskonPromo)) ?>;

                if (metode === 'COD') {

                    return confirm(
                        'Promo ' +
                        promoAktif +
                        ' aktif dengan diskon ' +
                        diskon +
                        '.\n\n' +
                        'Pesanan COD akan langsung dibuat. ' +
                        'Apakah data pesanan sudah benar?'
                    );

                }

                return confirm(
                    'Promo ' +
                    promoAktif +
                    ' aktif dengan diskon ' +
                    diskon +
                    '.\n\n' +
                    'Kamu akan diarahkan ke halaman pembayaran ' +
                    'untuk menyelesaikan pembayaran.'
                );

            <?php else: ?>

                if (metode === 'COD') {

                    return confirm(
                        'Pesanan COD akan langsung dibuat. ' +
                        'Apakah data pesanan sudah benar?'
                    );

                }

                return confirm(
                    'Kamu akan diarahkan ke halaman pembayaran ' +
                    'untuk menyelesaikan pembayaran.'
                );

            <?php endif; ?>
        }
    </script>

</body>

</html>
