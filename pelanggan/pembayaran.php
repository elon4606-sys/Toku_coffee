<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

if (file_exists("../config/notifikasi.php")) {
    require_once "../config/notifikasi.php";
}

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

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

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
            (float) $angka,
            0,
            ',',
            '.'
        );
    }
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/* =========================================================
   CEK SESSION CHECKOUT
========================================================= */

if (
    !isset($_SESSION['checkout']) ||
    !is_array($_SESSION['checkout'])
) {
    header("Location: checkout.php");
    exit;
}

$checkout = $_SESSION['checkout'];

/* =========================================================
   DATA CHECKOUT
========================================================= */

$nama = trim(
    $checkout['nama'] ?? ''
);

$email = trim(
    $checkout['email'] ?? ''
);

$telepon = trim(
    $checkout['telepon'] ?? ''
);

$kota = trim(
    $checkout['kota'] ?? ''
);

$alamat = trim(
    $checkout['alamat'] ?? ''
);

/* =========================================================
   TIPE PESANAN
========================================================= */

$tipePesanan = trim(
    $checkout['tipe_pesanan'] ?? 'Delivery'
);

if (
    $tipePesanan !== 'Delivery' &&
    $tipePesanan !== 'Take Away'
) {
    $tipePesanan = 'Delivery';
}

/* =========================================================
   METODE PEMBAYARAN
========================================================= */

$metodePembayaran = trim(
    $checkout['metode_pembayaran'] ?? 'QRIS'
);

/* =========================================================
   PROMO
========================================================= */

$kodePromo = strtoupper(
    trim(
        $checkout['kode_promo'] ?? ''
    )
);

$namaPromo = trim(
    $checkout['nama_promo'] ?? ''
);

$diskonPromo = (float) (
    $checkout['diskon_promo'] ?? 0
);

/* =========================================================
   ONGKIR
========================================================= */

if ($tipePesanan === 'Delivery') {

    $ongkir = (float) (
        $checkout['ongkir'] ?? 10000
    );

    if ($ongkir <= 0) {
        $ongkir = 10000;
    }
} else {

    $ongkir = 0;
}

/* =========================================================
   KERANJANG
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
   AMBIL PRODUK
========================================================= */

$idProdukList = [];

foreach (
    $_SESSION['keranjang'] as $item
) {

    $id = (int) (
        $item['id'] ?? 0
    );

    $qty = (int) (
        $item['qty'] ?? 0
    );

    if (
        $id > 0 &&
        $qty > 0
    ) {
        $idProdukList[] = $id;
    }
}

$idProdukList = array_values(
    array_unique($idProdukList)
);

if (empty($idProdukList)) {

    header(
        "Location: keranjang.php?error=produk_tidak_valid"
    );

    exit;
}

/* =========================================================
   QUERY PRODUK
========================================================= */

$produkCheckout = [];

$subtotal = 0;

$totalItem = 0;

$placeholders = implode(
    ',',
    array_fill(
        0,
        count($idProdukList),
        '?'
    )
);

$types = str_repeat(
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

$stmtProduk = $conn->prepare(
    $sqlProduk
);

if (!$stmtProduk) {
    die("Gagal mengambil produk: " .
        e($conn->error));
}

$stmtProduk->bind_param(
    $types,
    ...$idProdukList
);

$stmtProduk->execute();

$resultProduk = $stmtProduk->get_result();

$dataProdukDB = [];

if ($resultProduk) {

    while (
        $row = $resultProduk->fetch_assoc()
    ) {

        $dataProdukDB[(int) $row['id']] = $row;
    }
}

$stmtProduk->close();

/* =========================================================
   GABUNG PRODUK DENGAN KERANJANG
========================================================= */

foreach (
    $_SESSION['keranjang'] as $item
) {

    $id = (int) (
        $item['id'] ?? 0
    );

    $qty = (int) (
        $item['qty'] ?? 0
    );

    if (
        $id <= 0 ||
        $qty <= 0 ||
        !isset($dataProdukDB[$id])
    ) {
        continue;
    }

    $produk = $dataProdukDB[$id];

    $harga = (float) (
        $produk['harga'] ?? 0
    );

    $jumlah = $harga * $qty;

    /* =====================================================
       GAMBAR
    ===================================================== */

    $gambar = trim(
        $produk['gambar'] ?? ''
    );

    if ($gambar !== '') {

        if (
            preg_match(
                '/^https?:\/\//i',
                $gambar
            )
        ) {

            // URL

        } elseif (
            strpos(
                $gambar,
                '../upload/'
            ) === 0
        ) {

            // Sudah benar

        } elseif (
            strpos(
                $gambar,
                'upload/'
            ) === 0
        ) {

            $gambar =
                '../' . $gambar;
        } else {

            $gambar =
                '../upload/' .
                basename($gambar);
        }
    } else {

        $gambar =
            '../upload/toku-americano.png';
    }

    /* =====================================================
       KATEGORI
    ===================================================== */

    $kategoriDB = strtolower(
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

    $subtotal += $jumlah;

    $totalItem += $qty;
}

/* =========================================================
   VALIDASI PRODUK
========================================================= */

if (empty($produkCheckout)) {

    $_SESSION['keranjang'] = [];

    header(
        "Location: keranjang.php?error=produk_tidak_valid"
    );

    exit;
}

/* =========================================================
   BATASI DISKON
========================================================= */

$diskonPromo = min(
    max(
        0,
        $diskonPromo
    ),
    $subtotal
);

/* =========================================================
   TOTAL
========================================================= */

$totalSetelahDiskon =
    max(
        0,
        $subtotal - $diskonPromo
    );

$totalBayar =
    $totalSetelahDiskon + $ongkir;

/* =========================================================
   INVOICE
========================================================= */

$invoice = trim(
    $checkout['invoice'] ?? ''
);

if ($invoice === '') {

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
}

/* =========================================================
   ERROR
========================================================= */

$errorPembayaran = '';

/* =========================================================
   PROSES KONFIRMASI
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (
        $_POST['action'] ?? ''
    ) === 'konfirmasi_pembayaran'
) {

    $metodePembayaran =
        trim(
            $_POST['metode_pembayaran']
                ?? $metodePembayaran
        );

    $metodeValid = [
        'QRIS',
        'Transfer Bank',
        'E-Wallet'
    ];

    if (
        !in_array(
            $metodePembayaran,
            $metodeValid,
            true
        )
    ) {

        $errorPembayaran =
            'Silakan pilih metode pembayaran.';
    } else {

        try {

            /* =============================================
               TRANSAKSI
            ============================================= */

            $conn->begin_transaction();

            /* =============================================
               INSERT PESANAN
            ============================================= */

            $sqlPesanan = "
                INSERT INTO pesanan
                (
                    user_id,
                    invoice,
                    total,
                    metode_pembayaran,
                    tipe_pesanan,
                    kota,
                    alamat_pengiriman,
                    ongkir,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
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
                "isdssssd",
                $userId,
                $invoice,
                $totalBayar,
                $metodePembayaran,
                $tipePesanan,
                $kota,
                $alamat,
                $ongkir
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
                (int) $conn->insert_id;

            $stmtPesanan->close();

            /* =============================================
               DETAIL PESANAN
            ============================================= */

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
                $produkCheckout as $produk
            ) {

                $produkId =
                    (int) $produk['id'];

                $jumlahProduk =
                    (int) $produk['qty'];

                $hargaProduk =
                    (float) $produk['harga'];

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

            /* =============================================
               COMMIT
            ============================================= */

            $conn->commit();

            /* =============================================
               SIMPAN PESANAN TERAKHIR
            ============================================= */

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

                'tipe_pesanan' =>
                $tipePesanan,

                'ongkir' =>
                $ongkir,

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

            /* =============================================
               KOSONGKAN CART
            ============================================= */

            $_SESSION['keranjang'] = [];

            /* =============================================
               HAPUS CHECKOUT
            ============================================= */

            unset(
                $_SESSION['checkout']
            );

            unset(
                $_SESSION['promo_code'],
                $_SESSION['promo_name'],
                $_SESSION['promo_discount']
            );

            /* =============================================
               KE DETAIL PESANAN
            ============================================= */

            header(
                "Location: detail-pesanan.php?id=" .
                    $pesananId
            );

            exit;
        } catch (Throwable $e) {

            $conn->rollback();

            $errorPembayaran =
                $e->getMessage();
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
        Pembayaran - Toku Coffee
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            margin: 0;
            background: #f5f5f5;
            color: #333;
        }

        /* =========================================================
   PAGE
========================================================= */

        .payment-page {
            min-height: 100vh;
            padding: 7rem 2rem 4rem;
        }

        .payment-container {
            width: 100%;
            max-width: 1050px;
            margin: 0 auto;
        }

        /* =========================================================
   GRID
========================================================= */

        .payment-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, .8fr);
            gap: 16px;
            align-items: start;
        }

        /* =========================================================
   CARD
========================================================= */

        .payment-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        }

        .payment-card h2 {
            margin: 0 0 18px;
            color: #222;
            font-size: 18px;
            font-weight: 600;
        }

        .payment-card h2 i {
            margin-right: 7px;
            font-size: 16px;
        }

        /* =========================================================
   QRIS
========================================================= */

        .qris-box {
            background: #faf8f4;
            border: 1px solid #eee7dd;
            border-radius: 8px;
            padding: 18px;
        }

        .qris-icon {
            font-size: 23px;
            color: #444638;
            margin-bottom: 8px;
        }

        .qris-box h3 {
            margin: 0 0 5px;
            font-size: 17px;
            font-weight: 600;
            color: #292929;
        }

        .qris-box p {
            margin: 0;
            color: #666;
            font-size: 12px;
            line-height: 1.6;
        }

        /* =========================================================
   QRIS CODE
========================================================= */

        .qris-code {
            margin-top: 15px;
            background: #fff;
            border: 1px dashed #d8c7ad;
            border-radius: 8px;
            padding: 17px;
            min-height: 90px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .qris-code h4 {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 600;
            color: #333;
        }

        .qris-code span {
            color: #777;
            font-size: 11px;
        }

        .qris-image {
            max-width: 150px;
            width: 100%;
            margin: 12px auto 2px;
            display: block;
        }

        /* =========================================================
   INFO
========================================================= */

        .payment-info {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 14px;
            padding: 12px 14px;
            background: #fffaf0;
            border: 1px solid #f0dfb7;
            border-radius: 7px;
            color: #755d2b;
            font-size: 11px;
            line-height: 1.6;
        }

        .payment-info i {
            margin-top: 2px;
            font-size: 12px;
        }

        /* =========================================================
   BUTTON
========================================================= */

        .btn-confirm {
            width: 100%;
            margin-top: 15px;
            padding: 12px 15px;
            border: none;
            border-radius: 7px;
            background: #444638;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn-confirm:hover {
            background: #303128;
        }

        .btn-back {
            display: block;
            margin-top: 13px;
            color: #555;
            text-decoration: none;
            font-size: 12px;
        }

        .btn-back:hover {
            color: #222;
        }

        /* =========================================================
   ORDER ITEMS
========================================================= */

        .order-items {
            margin-bottom: 15px;
        }

        .order-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .order-item:first-child {
            padding-top: 0;
        }

        .order-item-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .order-item-image {
            width: 46px;
            height: 46px;
            border-radius: 6px;
            overflow: hidden;
            background: #f3f3f3;
            flex-shrink: 0;
        }

        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-item-info {
            min-width: 0;
        }

        .order-item-info strong {
            display: block;
            color: #333;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .order-item-info span {
            display: block;
            margin-top: 2px;
            color: #999;
            font-size: 10px;
        }

        .order-item-price {
            color: #333;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* =========================================================
   DETAIL
========================================================= */

        .detail-section {
            margin-top: 15px;
            padding-top: 14px;
            border-top: 1px solid #eeeeee;
        }

        .detail-section-title {
            margin-bottom: 9px;
            color: #333;
            font-size: 12px;
            font-weight: 600;
        }

        .detail-section-title i {
            margin-right: 5px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            padding: 5px 0;
            font-size: 11px;
        }

        .detail-row span:first-child {
            color: #999;
            flex-shrink: 0;
        }

        .detail-row span:last-child {
            color: #444;
            font-weight: 500;
            text-align: right;
            max-width: 65%;
        }

        /* =========================================================
   SUMMARY
========================================================= */

        .summary {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 12px;
        }

        .summary-row span:first-child {
            color: #777;
        }

        .summary-row strong {
            color: #333;
            font-weight: 600;
        }

        .discount {
            color: #527853 !important;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 7px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
        }

        .total-row span {
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }

        .total-row strong {
            color: #333;
            font-size: 19px;
            font-weight: 700;
        }

        /* =========================================================
   ERROR
========================================================= */

        .error-box {
            margin-bottom: 14px;
            padding: 10px 12px;
            background: #fff1f1;
            border: 1px solid #edc1c1;
            border-radius: 7px;
            color: #a94442;
            font-size: 11px;
        }

        /* =========================================================
   DELIVERY BADGE
========================================================= */

        .delivery-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 5px;
            background: #f3f1e8;
            color: #555;
            font-size: 10px;
        }

        /* =========================================================
   RESPONSIVE
========================================================= */

        @media (max-width: 850px) {

            .payment-page {
                padding: 6rem 15px 3rem;
            }

            .payment-grid {
                grid-template-columns: 1fr;
            }

            .payment-card {
                padding: 18px;
            }
        }

        @media (max-width: 500px) {

            .payment-page {
                padding: 5rem 10px 2rem;
            }

            .payment-card {
                padding: 15px;
                border-radius: 7px;
            }

            .payment-card h2 {
                font-size: 16px;
            }

            .detail-row {
                gap: 8px;
            }

            .detail-row span:last-child {
                max-width: 60%;
            }

            .total-row strong {
                font-size: 17px;
            }
        }
    </style>

</head>

<body>

    <section class="payment-page">

        <div class="payment-container">

            <div class="payment-grid">

                <!-- =================================================
                 BAGIAN PEMBAYARAN
            ================================================== -->

                <div class="payment-card">

                    <h2>
                        <i class="fas fa-credit-card"></i>
                        Pembayaran
                    </h2>

                    <?php if ($errorPembayaran !== ''): ?>

                        <div class="error-box">

                            <i class="fas fa-circle-exclamation"></i>

                            <?= e($errorPembayaran) ?>

                        </div>

                    <?php endif; ?>


                    <!-- QRIS -->

                    <?php if ($metodePembayaran === 'QRIS'): ?>

                        <div class="qris-box">

                            <div class="qris-icon">

                                <i class="fas fa-qrcode"></i>

                            </div>

                            <h3>
                                QRIS
                            </h3>

                            <p>
                                Silakan lakukan pembayaran
                                menggunakan QRIS Toku Coffee.
                            </p>


                            <div class="qris-code">

                                <h4>
                                    QRIS TOKU COFFEE
                                </h4>

                                <span>
                                    Scan QRIS Toku Coffee
                                    untuk melakukan pembayaran.
                                </span>

                                <?php
                                $qrisPath =
                                    "../upload/qris-toku.png";

                                if (file_exists($qrisPath)):
                                ?>

                                    <img
                                        src="<?= e($qrisPath) ?>"
                                        alt="QRIS Toku Coffee"
                                        class="qris-image">

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- TRANSFER -->

                    <?php if ($metodePembayaran === 'Transfer Bank'): ?>

                        <div class="qris-box">

                            <div class="qris-icon">

                                <i class="fas fa-building-columns"></i>

                            </div>

                            <h3>
                                Transfer Bank
                            </h3>

                            <p>
                                Silakan transfer sesuai total
                                pembayaran pesanan.
                            </p>

                            <div class="qris-code">

                                <h4>
                                    Rekening Toku Coffee
                                </h4>

                                <span>
                                    Bank BCA
                                </span>

                                <strong
                                    style="
                                    display:block;
                                    font-size:1.5rem;
                                    margin-top:.6rem;
                                    color:#443;
                                ">

                                    1234567890

                                </strong>

                                <span>
                                    a.n. Toku Coffee
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- E-WALLET -->

                    <?php if ($metodePembayaran === 'E-Wallet'): ?>

                        <div class="qris-box">

                            <div class="qris-icon">

                                <i class="fas fa-wallet"></i>

                            </div>

                            <h3>
                                E-Wallet
                            </h3>

                            <p>
                                Silakan lakukan pembayaran
                                menggunakan E-Wallet.
                            </p>

                            <div class="qris-code">

                                <h4>
                                    E-Wallet Toku Coffee
                                </h4>

                                <span>
                                    GoPay / OVO / DANA
                                </span>

                                <strong
                                    style="
                                    display:block;
                                    font-size:1.5rem;
                                    margin-top:.6rem;
                                    color:#443;
                                ">

                                    0812-3456-7890

                                </strong>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- INFO -->

                    <div class="payment-info">

                        <i class="fas fa-circle-info"></i>

                        <span>

                            Setelah melakukan pembayaran,
                            klik tombol
                            <strong>
                                Konfirmasi Pembayaran
                            </strong>
                            untuk menyelesaikan pesanan.

                        </span>

                    </div>


                    <!-- FORM -->

                    <form
                        method="POST"
                        action="">

                        <input
                            type="hidden"
                            name="action"
                            value="konfirmasi_pembayaran">

                        <input
                            type="hidden"
                            name="metode_pembayaran"
                            value="<?= e($metodePembayaran) ?>">

                        <button
                            type="submit"
                            class="btn-confirm">

                            <i class="fas fa-circle-check"></i>

                            Konfirmasi Pembayaran

                        </button>

                    </form>


                    <a
                        href="checkout.php"
                        class="btn-back">

                        <i class="fas fa-arrow-left"></i>

                        Kembali Ke Checkout

                    </a>

                </div>


                <!-- =================================================
                 RINCIAN PESANAN
            ================================================== -->

                <div class="payment-card">

                    <h2>
                        Ringkasan Pesanan
                    </h2>


                    <!-- PRODUK -->

                    <div class="order-items">

                        <?php foreach (
                            $produkCheckout
                            as $produk
                        ): ?>

                            <div class="order-item">

                                <div class="order-item-left">

                                    <div class="order-item-image">

                                        <img
                                            src="<?= e($produk['gambar']) ?>"
                                            alt="<?= e($produk['nama_produk']) ?>">

                                    </div>


                                    <div class="order-item-info">

                                        <strong>
                                            <?= e(
                                                $produk['nama_produk']
                                            ) ?>
                                            ×
                                            <?= (int)$produk['qty'] ?>
                                        </strong>

                                        <span>
                                            <?= rupiah(
                                                $produk['harga']
                                            ) ?>
                                            / item
                                        </span>

                                    </div>

                                </div>


                                <div class="order-item-price">

                                    <?= rupiah(
                                        $produk['jumlah']
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- =================================================
                     DETAIL PESANAN
                ================================================== -->

                    <div class="detail-section">

                        <div class="detail-section-title">

                            <i class="fas fa-clipboard-list"></i>

                            Rincian Pengiriman

                        </div>


                        <div class="detail-row">

                            <span>
                                Tipe Pesanan
                            </span>

                            <span>

                                <?php if (
                                    $tipePesanan === 'Delivery'
                                ): ?>

                                    <i class="fas fa-motorcycle"></i>
                                    Delivery

                                <?php else: ?>

                                    <i class="fas fa-store"></i>
                                    Take Away

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="detail-row">

                            <span>
                                Nama
                            </span>

                            <span>
                                <?= e($nama) ?>
                            </span>

                        </div>


                        <div class="detail-row">

                            <span>
                                Telepon
                            </span>

                            <span>
                                <?= e($telepon) ?>
                            </span>

                        </div>


                        <?php if ($tipePesanan === 'Delivery'): ?>

                            <div class="detail-row">

                                <span>
                                    Kota
                                </span>

                                <span>
                                    <?= e($kota) ?>
                                </span>

                            </div>


                            <div class="detail-row">

                                <span>
                                    Alamat
                                </span>

                                <span>
                                    <?= e($alamat) ?>
                                </span>

                            </div>

                        <?php else: ?>

                            <div class="detail-row">

                                <span>
                                    Lokasi Pickup
                                </span>

                                <span>
                                    Toku Coffee
                                </span>

                            </div>

                        <?php endif; ?>


                        <div class="detail-row">

                            <span>
                                Pembayaran
                            </span>

                            <span>
                                <?= e($metodePembayaran) ?>
                            </span>

                        </div>

                    </div>


                    <!-- =================================================
                     SUMMARY
                ================================================== -->

                    <div class="summary">

                        <div class="summary-row">

                            <span>
                                Subtotal
                            </span>

                            <strong>
                                <?= rupiah($subtotal) ?>
                            </strong>

                        </div>


                        <?php if ($diskonPromo > 0): ?>

                            <div class="summary-row">

                                <span>
                                    Diskon
                                </span>

                                <strong class="discount">

                                    - <?= rupiah(
                                            $diskonPromo
                                        ) ?>

                                </strong>

                            </div>

                        <?php endif; ?>


                        <div class="summary-row">

                            <span>
                                Ongkir
                            </span>

                            <strong
                                class="<?= $ongkir > 0
                                            ? ''
                                            : 'discount' ?>">

                                <?php if ($ongkir > 0): ?>

                                    <?= rupiah($ongkir) ?>

                                <?php else: ?>

                                    Gratis

                                <?php endif; ?>

                            </strong>

                        </div>


                        <div class="total-row">

                            <span>
                                Total
                            </span>

                            <strong>
                                <?= rupiah($totalBayar) ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>

</body>

</html>
