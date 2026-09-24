<?php

/* =========================================================
   PESANAN SELESAI - TOKU COFFEE
========================================================= */

require_once "../config/koneksi.php";
require_once "../config/session.php";

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

if (($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../login/login.php?error=akses_ditolak");
    exit;
}

/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($angka)
{
    return 'Rp ' .
        number_format(
            (float)$angka,
            0,
            ',',
            '.'
        );
}

/* =========================================================
   USER
========================================================= */

$userId =
    (int)($_SESSION['user_id'] ?? 0);

$namaUser =
    $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Pelanggan';

/* =========================================================
   AMBIL PESANAN TERAKHIR
========================================================= */

$pesananTerakhir =
    $_SESSION['pesanan_terakhir']
    ?? null;

/* =========================================================
   JIKA SESSION PESANAN TIDAK ADA
========================================================= */

if (
    !$pesananTerakhir &&
    $userId > 0
) {

    $sqlPesanan = "
        SELECT
            id,
            invoice,
            total,
            metode_pembayaran,
            status,
            created_at
        FROM pesanan
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ";

    $stmtPesanan =
        $conn->prepare($sqlPesanan);

    if ($stmtPesanan) {

        $stmtPesanan->bind_param(
            "i",
            $userId
        );

        $stmtPesanan->execute();

        $resultPesanan =
            $stmtPesanan->get_result();

        if ($resultPesanan) {

            $dataPesanan =
                $resultPesanan->fetch_assoc();

            if ($dataPesanan) {

                $pesananTerakhir = [

                    'pesanan_id' =>
                    $dataPesanan['id'],

                    'invoice' =>
                    $dataPesanan['invoice'],

                    'total' =>
                    $dataPesanan['total'],

                    'metode_pembayaran' =>
                    $dataPesanan['metode_pembayaran'],

                    'status' =>
                    $dataPesanan['status'],

                    'tanggal' =>
                    $dataPesanan['created_at']
                        ?? date('Y-m-d H:i:s')
                ];
            }
        }

        $stmtPesanan->close();
    }
}

/* =========================================================
   JIKA TIDAK ADA PESANAN
========================================================= */

if (!$pesananTerakhir) {

    header("Location: index.php");

    exit;
}

/* =========================================================
   DATA PESANAN
========================================================= */

$pesananId =
    (int)(
        $pesananTerakhir['pesanan_id']
        ?? 0
    );

$invoice =
    $pesananTerakhir['invoice']
    ?? '-';

$total =
    (float)(
        $pesananTerakhir['total']
        ?? 0
    );

$metodePembayaran =
    $pesananTerakhir['metode_pembayaran']
    ?? 'COD';

$status =
    $pesananTerakhir['status']
    ?? 'Menunggu';

$tanggal =
    $pesananTerakhir['tanggal']
    ?? date('Y-m-d H:i:s');

/* =========================================================
   FORMAT TANGGAL
========================================================= */

$timestamp =
    strtotime($tanggal);

if ($timestamp !== false) {

    $tanggalFormat =
        date(
            'd F Y, H:i',
            $timestamp
        );
} else {

    $tanggalFormat =
        $tanggal;
}

/* =========================================================
   TERJEMAHAN BULAN
========================================================= */

$bulan = [

    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];

foreach ($bulan as $en => $id) {

    $tanggalFormat =
        str_replace(
            $en,
            $id,
            $tanggalFormat
        );
}

/* =========================================================
   AMBIL DETAIL PRODUK
========================================================= */

$detailPesanan = [];

if ($pesananId > 0) {

    $sqlDetail = "
        SELECT
            dp.id,
            dp.produk_id,
            dp.jumlah,
            dp.harga,
            p.nama_produk,
            p.gambar
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

                $gambar = trim($row['gambar'] ?? '');

                if (!empty($gambar)) {


                    if (
                        strpos($gambar, 'http://') === 0 ||
                        strpos($gambar, 'https://') === 0
                    ) {
                    } elseif (
                        strpos($gambar, '../upload/') === 0
                    ) {
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

                $detailPesanan[] = [

                    'nama_produk' =>
                    $row['nama_produk']
                        ?? 'Produk',

                    'jumlah' =>
                    (int)$row['jumlah'],

                    'harga' =>
                    (float)$row['harga'],

                    'gambar' =>
                    $gambar
                ];
            }
        }

        $stmtDetail->close();
    }
}

/* =========================================================
   JUMLAH PRODUK
========================================================= */

$totalItem = 0;

foreach ($detailPesanan as $item) {

    $totalItem +=
        (int)$item['jumlah'];
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
        Pesanan Berhasil - Toku Coffee
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

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #faf9f5;
            color: #443;
        }

        /* =====================================================
   PAGE
===================================================== */

        .page {
            min-height: 100vh;
            padding: 4rem 1.5rem 3rem;
        }

        .success-container {
            max-width: 65rem;
            margin: 0 auto;
        }

        /* =====================================================
   SUCCESS HEADER
===================================================== */

        .success-header {
            text-align: center;
            margin-bottom: 1.3rem;
        }

        .success-icon {
            width: 4.2rem;
            height: 4.2rem;

            margin: 0 auto .8rem;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #527853;
            color: #fff;

            font-size: 1.8rem;

            box-shadow:
                0 .5rem 1rem rgba(82, 120, 83, .15);

            animation: muncul .5s ease;
        }

        @keyframes muncul {

            from {
                opacity: 0;
                transform: scale(.7);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }

        }

        .success-header h1 {
            color: #2d211b;
            font-size: 1.8rem;
            margin-bottom: .3rem;
        }

        .success-header h1 span {
            color: #527853;
        }

        .success-header p {
            color: #777;
            font-size: .85rem;
        }

        /* =====================================================
   INVOICE
===================================================== */

        .invoice-box {
            background: #fff;

            border: .1rem solid #e8e3dc;

            border-radius: 1rem;

            padding: 1.2rem;

            margin-bottom: 1rem;

            box-shadow:
                0 .7rem 1.5rem rgba(68, 51, 51, .04);
        }

        .invoice-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 1rem;

            padding-bottom: .9rem;

            border-bottom: .1rem solid #eee;
        }

        .invoice-label {
            color: #999;

            font-size: .7rem;

            margin-bottom: .15rem;
        }

        .invoice-number {

            color: #2d211b;

            font-size: 1rem;

            font-weight: 700;
        }

        .status {

            padding: .35rem .7rem;

            border-radius: 5rem;

            background: #fff4df;

            color: #b47720;

            font-size: .7rem;

            font-weight: 600;
        }

        /* =====================================================
   ORDER INFO
===================================================== */

        .order-info {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: .7rem;

            margin-top: .9rem;
        }

        .info-box {

            background: #faf9f5;

            border-radius: .7rem;

            padding: .7rem;
        }

        .info-box i {

            color: #6f4e37;

            margin-right: .2rem;
        }

        .info-box span {

            display: block;

            color: #999;

            font-size: .65rem;

            margin-bottom: .2rem;
        }

        .info-box strong {

            color: #443;

            font-size: .75rem;
        }

        /* =====================================================
   CONTENT
===================================================== */

        .content-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1.5fr) minmax(20rem, .8fr);

            gap: 1rem;

            align-items: start;
        }

        .card {

            background: #fff;

            border: .1rem solid #e8e3dc;

            border-radius: 1rem;

            padding: 1.2rem;

            box-shadow:
                0 .7rem 1.5rem rgba(68, 51, 51, .04);
        }

        .card h2 {

            color: #2d211b;

            font-size: 1.05rem;

            margin-bottom: .9rem;

            display: flex;

            align-items: center;

            gap: .4rem;
        }

        .card h2 i {
            color: #6f4e37;
        }

        /* =====================================================
   PRODUK
===================================================== */

        .product-item {

            display: flex;

            align-items: center;

            gap: .7rem;

            padding: .6rem 0;

            border-bottom: .1rem solid #eee;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-image {

            width: 3.5rem;
            height: 3.5rem;

            flex-shrink: 0;

            border-radius: .6rem;

            overflow: hidden;

            background: #f5f1ea;
        }

        .product-image img {

            width: 100%;
            height: 100%;

            object-fit: cover;
        }

        .product-info {

            flex: 1;

            min-width: 0;
        }

        .product-info h3 {

            color: #2d211b;

            font-size: .8rem;

            margin-bottom: .1rem;
        }

        .product-info p {

            color: #999;

            font-size: .7rem;
        }

        .product-price {

            color: #6f4e37;

            font-size: .75rem;

            font-weight: 700;

            white-space: nowrap;
        }

        /* =====================================================
   TOTAL
===================================================== */

        .total-box {

            margin-top: .9rem;

            padding-top: .7rem;

            border-top: .1rem solid #ddd;
        }

        .total-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: .7rem;

            padding: .3rem 0;

            color: #777;

            font-size: .75rem;
        }

        .total-row strong {
            color: #443;
        }

        .grand-total {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding-top: .7rem;

            margin-top: .5rem;

            border-top: .1rem solid #ddd;
        }

        .grand-total span {

            font-size: .85rem;

            font-weight: 600;
        }

        .grand-total strong {

            color: #6f4e37;

            font-size: 1.15rem;
        }

        /* =====================================================
   NEXT STEP
===================================================== */

        .next-box {

            background: #f4f8f2;

            border: .1rem solid #dbe8d5;

            border-radius: .7rem;

            padding: .8rem;

            margin-bottom: .7rem;
        }

        .next-box h3 {

            color: #527853;

            font-size: .8rem;

            margin-bottom: .3rem;
        }

        .next-box p {

            color: #777;

            font-size: .7rem;

            line-height: 1.5;
        }

        /* =====================================================
   BUTTON
===================================================== */

        .button-group {

            display: flex;

            flex-direction: column;

            gap: .5rem;
        }

        .btn {

            width: 100%;

            padding: .75rem 1rem;

            border-radius: .6rem;

            text-decoration: none;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: .4rem;

            font-size: .75rem;

            font-weight: 600;

            transition: .2s ease;
        }

        .btn-primary {

            background: #443;

            color: #fff;
        }

        .btn-primary:hover {

            background: #2d211b;

            transform: translateY(-1px);
        }

        .btn-secondary {

            background: #fff;

            color: #443;

            border: .1rem solid #ddd;
        }

        .btn-secondary:hover {

            border-color: #6f4e37;

            color: #6f4e37;
        }

        /* =====================================================
   RESPONSIVE
===================================================== */

        @media (max-width: 850px) {

            .content-grid {
                grid-template-columns: 1fr;
            }

            .order-info {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 600px) {

            .page {
                padding: 3rem 1rem 2rem;
            }

            .success-header h1 {
                font-size: 1.5rem;
            }

            .success-header p {
                font-size: .75rem;
            }

            .invoice-box,
            .card {
                padding: 1rem;
            }

            .invoice-header {

                flex-direction: column;

                align-items: flex-start;
            }

            .invoice-number {
                font-size: .9rem;
            }

            .product-image {

                width: 3rem;
                height: 3rem;
            }

            .product-price {
                font-size: .7rem;
            }

        }
    </style>
</head>

<body>

    <div class="page">

        <div class="success-container">


            <!-- =================================================
             HEADER SUKSES
        ================================================== -->

            <div class="success-header">

                <div class="success-icon">

                    <i class="fas fa-check"></i>

                </div>

                <h1>

                    Pesanan

                    <span>
                        Berhasil!
                    </span>

                </h1>

                <p>

                    Terima kasih sudah melakukan pemesanan
                    di Toku Coffee.

                </p>

            </div>


            <!-- =================================================
             INVOICE
        ================================================== -->

            <div class="invoice-box">

                <div class="invoice-header">

                    <div>

                        <div class="invoice-label">
                            Nomor Invoice
                        </div>

                        <div class="invoice-number">

                            <?= e($invoice) ?>

                        </div>

                    </div>


                    <div class="status">

                        <i class="fas fa-clock"></i>

                        <?= e($status) ?>

                    </div>

                </div>


                <div class="order-info">


                    <!-- TANGGAL -->

                    <div class="info-box">

                        <span>

                            <i class="fas fa-calendar"></i>

                            Tanggal Pesanan

                        </span>

                        <strong>

                            <?= e($tanggalFormat) ?>

                        </strong>

                    </div>


                    <!-- PEMBAYARAN -->

                    <div class="info-box">

                        <span>

                            <i class="fas fa-credit-card"></i>

                            Metode Pembayaran

                        </span>

                        <strong>

                            <?= e($metodePembayaran) ?>

                        </strong>

                    </div>


                    <!-- TOTAL -->

                    <div class="info-box">

                        <span>

                            <i class="fas fa-money-bill"></i>

                            Total Pembayaran

                        </span>

                        <strong>

                            <?= rupiah($total) ?>

                        </strong>

                    </div>

                </div>

            </div>


            <!-- =================================================
             CONTENT
        ================================================== -->

            <div class="content-grid">


                <!-- DETAIL PRODUK -->

                <div class="card">

                    <h2>

                        <i class="fas fa-receipt"></i>

                        Detail Pesanan

                    </h2>


                    <?php if (!empty($detailPesanan)): ?>

                        <?php foreach (
                            $detailPesanan
                            as $item
                        ): ?>

                            <div class="product-item">

                                <div class="product-image">

                                    <img
                                        src="<?= e($item['gambar']) ?>"
                                        alt="<?= e($item['nama_produk']) ?>"
                                        onerror="this.src='../upload/toku-americano.png';">

                                </div>


                                <div class="product-info">

                                    <h3>

                                        <?= e(
                                            $item['nama_produk']
                                        ) ?>

                                    </h3>

                                    <p>

                                        <?= (int)$item['jumlah'] ?>

                                        ×

                                        <?= rupiah(
                                            $item['harga']
                                        ) ?>

                                    </p>

                                </div>


                                <div class="product-price">

                                    <?= rupiah(
                                        $item['jumlah'] *
                                            $item['harga']
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p style="
                        color:#777;
                        font-size:.9rem;
                    ">

                            Detail produk tidak ditemukan.

                        </p>

                    <?php endif; ?>


                    <!-- TOTAL -->

                    <div class="total-box">

                        <div class="total-row">

                            <span>
                                Total Item
                            </span>

                            <strong>

                                <?= $totalItem ?>

                                item

                            </strong>

                        </div>


                        <div class="total-row">

                            <span>
                                Ongkir
                            </span>

                            <strong style="color:#527853;">

                                Gratis

                            </strong>

                        </div>


                        <div class="grand-total">

                            <span>
                                Total
                            </span>

                            <strong>

                                <?= rupiah($total) ?>

                            </strong>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                 PESANAN
            ================================================== -->

                <div class="card">

                    <h2>

                        <i class="fas fa-circle-check"></i>

                        Pesanan Anda

                    </h2>


                    <div class="next-box">

                        <h3>

                            <i class="fas fa-check"></i>

                            Pesanan berhasil dibuat

                        </h3>

                        <p>

                            Pesanan dengan nomor invoice

                            <strong>
                                <?= e($invoice) ?>
                            </strong>

                            telah berhasil dicatat oleh
                            Toku Coffee.

                        </p>

                    </div>


                    <?php if (
                        $metodePembayaran === 'COD'
                    ): ?>

                        <div class="next-box">

                            <h3>

                                <i class="fas fa-money-bill-wave"></i>

                                Pembayaran COD

                            </h3>

                            <p>

                                Silakan siapkan pembayaran
                                saat pesanan diterima.

                            </p>

                        </div>

                    <?php else: ?>

                        <div class="next-box">

                            <h3>

                                <i class="fas fa-credit-card"></i>

                                Pembayaran

                            </h3>

                            <p>

                                Metode pembayaran:

                                <strong>
                                    <?= e(
                                        $metodePembayaran
                                    ) ?>
                                </strong>

                            </p>

                        </div>

                    <?php endif; ?>


                    <div class="button-group">

                        <a
                            href="akun-pelanggan.php"
                            class="btn btn-primary">

                            <i class="fas fa-user"></i>

                            Lihat Akun & Pesanan

                        </a>


                        <a
                            href="index.php"
                            class="btn btn-secondary">

                            <i class="fas fa-mug-hot"></i>

                            Kembali ke Menu

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</body>

</html>
