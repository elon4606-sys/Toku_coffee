<?php

/* =====================================================
   PEMBAYARAN - TOKU COFFEE

   TRANSFER BANK / QRIS / E-WALLET
   Setelah konfirmasi:
   pembayaran.php
          ↓
   pesanan-selesai.php
===================================================== */

require_once "../config/koneksi.php";
require_once "../config/session.php";

if (file_exists("../config/notifikasi.php")) {
    require_once "../config/notifikasi.php";
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =====================================================
   CEK LOGIN
===================================================== */

if (!isset($_SESSION['user_id'])) {

    header(
        "Location: ../login/login.php"
    );

    exit;
}


if (($_SESSION['role'] ?? '') !== 'customer') {

    header(
        "Location: ../login/login.php?error=akses_ditolak"
    );

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
   USER
===================================================== */

$userId =
    (int)($_SESSION['user_id'] ?? 0);


/* =====================================================
   CEK SESSION CHECKOUT
===================================================== */

if (
    !isset($_SESSION['checkout']) ||
    !is_array($_SESSION['checkout'])
) {

    header(
        "Location: checkout.php?error=data_checkout_tidak_ditemukan"
    );

    exit;
}


$checkout =
    $_SESSION['checkout'];


/* =====================================================
   DATA CHECKOUT
===================================================== */

$nama =
    trim(
        $checkout['nama'] ?? ''
    );

$email =
    trim(
        $checkout['email'] ?? ''
    );

$telepon =
    trim(
        $checkout['telepon'] ?? ''
    );

$kota =
    trim(
        $checkout['kota'] ?? ''
    );

$alamat =
    trim(
        $checkout['alamat'] ?? ''
    );

$metodePembayaran =
    trim(
        $checkout['metode_pembayaran'] ?? ''
    );


/* =====================================================
   VALIDASI METODE PEMBAYARAN
===================================================== */

$metodeValid = [

    'Transfer Bank',

    'QRIS',

    'E-Wallet'

];


if (
    !in_array(
        $metodePembayaran,
        $metodeValid,
        true
    )
) {

    header(
        "Location: checkout.php?error=metode_pembayaran_tidak_valid"
    );

    exit;
}


/* =====================================================
   CEK KERANJANG
===================================================== */

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


/* =====================================================
   AMBIL PRODUK DARI KERANJANG
===================================================== */

$produkCheckout = [];

$subtotal = 0;

$totalItem = 0;

$idProdukList = [];


foreach (
    $_SESSION['keranjang'] as $item
) {

    $id =
        (int)($item['id'] ?? 0);

    $qty =
        (int)($item['qty'] ?? 0);

    if (
        $id > 0 &&
        $qty > 0
    ) {

        $idProdukList[] =
            $id;
    }
}


/* =====================================================
   HAPUS DUPLIKAT ID
===================================================== */

$idProdukList =
    array_values(
        array_unique(
            $idProdukList
        )
    );


if (empty($idProdukList)) {

    header(
        "Location: keranjang.php?error=keranjang_kosong"
    );

    exit;
}


/* =====================================================
   QUERY PRODUK
===================================================== */

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


if (!$stmtProduk) {

    die('Gagal mengambil produk: '
        . e($conn->error));
}


$stmtProduk->bind_param(
    $types,
    ...$idProdukList
);


$stmtProduk->execute();


$resultProduk =
    $stmtProduk->get_result();


$dataProdukDB = [];


while (
    $row =
    $resultProduk->fetch_assoc()
) {

    $dataProdukDB[(int)$row['id']] = $row;
}


$stmtProduk->close();


/* =====================================================
   GABUNGKAN PRODUK
===================================================== */

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
        !isset($dataProdukDB[$id])
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
   GAMBAR
================================================= */

    $gambar = trim(
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
            // Sudah menggunakan path upload
        } else {

            // Database hanya menyimpan nama file
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

    $kategori =
        $produk['nama_kategori']
        ?? 'Umum';


    /* =================================================
       DATA PRODUK
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


    $subtotal +=
        $jumlah;


    $totalItem +=
        $qty;
}


/* =====================================================
   CEK PRODUK
===================================================== */

if (empty($produkCheckout)) {

    header(
        "Location: keranjang.php?error=produk_tidak_ditemukan"
    );

    exit;
}


/* =====================================================
   TOTAL
===================================================== */

$ongkir = 0;

$totalBayar =
    $subtotal + $ongkir;


/* =====================================================
   ERROR
===================================================== */

$errorPembayaran = '';


/* =====================================================
   PROSES KONFIRMASI PEMBAYARAN
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') ===
    'konfirmasi_pembayaran'
) {

    try {

        /* =============================================
           PASTIKAN METODE MASIH VALID
        ============================================= */

        if (
            !in_array(
                $metodePembayaran,
                $metodeValid,
                true
            )
        ) {

            throw new Exception(
                'Metode pembayaran tidak valid.'
            );
        }


        /* =============================================
           GENERATE INVOICE
        ============================================= */

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


        /* =============================================
           MULAI TRANSAKSI
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
                'Gagal menyiapkan pesanan: '
                    . $conn->error
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
                'Gagal menyimpan pesanan: '
                    . $stmtPesanan->error
            );
        }


        /* =============================================
           AMBIL ID PESANAN
        ============================================= */

        $pesananId =
            $conn->insert_id;


        $stmtPesanan->close();


        /* =============================================
           INSERT DETAIL PESANAN
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
                'Gagal menyiapkan detail pesanan: '
                    . $conn->error
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
                    'Gagal menyimpan detail pesanan: '
                        . $stmtDetail->error
                );
            }
        }


        $stmtDetail->close();


        /* =============================================
           COMMIT TRANSAKSI
        ============================================= */

        $conn->commit();


        /* =============================================
           SIMPAN DATA PESANAN TERAKHIR
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

            'metode_pembayaran' =>
            $metodePembayaran,

            'total' =>
            $totalBayar,

            'status' =>
            'Menunggu',

            'tanggal' =>
            date('Y-m-d H:i:s')

        ];


        /* =============================================
           KOSONGKAN KERANJANG
        ============================================= */

        $_SESSION['keranjang'] = [];


        /* =============================================
           HAPUS SESSION CHECKOUT
        ============================================= */

        unset(
            $_SESSION['checkout']
        );


        /* =============================================
           LANGSUNG KE PESANAN SELESAI
        ============================================= */

        header(
            "Location: pesanan-selesai.php"
        );

        exit;
    } catch (Throwable $e) {

        /* =============================================
           ROLLBACK JIKA GAGAL
        ============================================= */

        try {

            $conn->rollback();
        } catch (Throwable $rollbackError) {

            // Abaikan error rollback
        }


        /* =============================================
           LOG ERROR
        ============================================= */

        error_log(
            'TOKU PEMBAYARAN ERROR: '
                . $e->getMessage()
        );


        $errorPembayaran =
            'Pembayaran gagal diproses. Silakan coba kembali.';
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
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }


        body {
            background: #faf9f5;
            color: #443;
        }


        .payment-page {
            padding: 12rem 7% 7rem;
        }


        .payment-container {
            max-width: 100rem;
            margin: auto;

            display: grid;

            grid-template-columns:
                1.2fr .8fr;

            gap: 2rem;
        }


        .payment-card {
            background: #fff;

            border-radius: 1.8rem;

            padding: 2.5rem;

            border: 1px solid #e8e3dc;

            box-shadow:
                0 1.5rem 3rem rgba(68, 51, 51, .06);
        }


        .payment-card h1,
        .payment-card h2 {
            color: #2d211b;
            margin-bottom: 1.5rem;
        }


        .payment-method {
            background: #f8f4ed;

            border-radius: 1.2rem;

            padding: 2rem;

            margin-bottom: 2rem;
        }


        .payment-method i {
            color: #443;

            font-size: 2.5rem;

            margin-bottom: 1rem;
        }


        .payment-method h3 {
            font-size: 1.8rem;

            margin-bottom: .7rem;
        }


        .payment-method p {
            color: #777;

            font-size: 1.2rem;

            line-height: 1.7;
        }


        .account-number {
            background: #fff;

            border: 1px dashed #cdbda8;

            border-radius: 1rem;

            padding: 1.5rem;

            margin-top: 1.5rem;
        }


        .account-number strong {
            display: block;

            font-size: 1.8rem;

            color: #443;

            margin-top: .4rem;
        }


        .account-number span {
            color: #777;
        }


        .payment-note {
            padding: 1.4rem;

            background: #fff7e8;

            border: 1px solid #ead7ae;

            border-radius: 1rem;

            color: #765d2b;

            font-size: 1.15rem;

            line-height: 1.7;

            margin-bottom: 2rem;
        }


        .btn-payment {
            width: 100%;

            border: none;

            background: #443;

            color: #fff;

            padding: 1.4rem;

            border-radius: 1rem;

            cursor: pointer;

            font-size: 1.3rem;

            font-weight: 600;

            transition: .2s ease;
        }


        .btn-payment:hover {
            background: #2f2424;

            transform: translateY(-2px);
        }


        .summary-row {
            display: flex;

            justify-content: space-between;

            gap: 1rem;

            padding: 1rem 0;

            border-bottom: 1px solid #eee;

            font-size: 1.2rem;
        }


        .summary-row strong {
            white-space: nowrap;
        }


        .summary-total {
            display: flex;

            justify-content: space-between;

            padding-top: 1.5rem;

            margin-top: 1rem;

            border-top: 1px solid #ddd;
        }


        .summary-total strong {
            color: #443;

            font-size: 1.8rem;
        }


        .error {
            background: #fff0f0;

            border: 1px solid #e4b2b2;

            color: #a94442;

            padding: 1.3rem;

            border-radius: 1rem;

            margin-bottom: 2rem;
        }


        .back {
            display: inline-block;

            margin-top: 1.5rem;

            color: #443;

            text-decoration: none;

            font-size: 1.2rem;
        }


        .back:hover {
            color: #6f4e37;
        }


        @media (max-width: 768px) {

            .payment-container {
                grid-template-columns: 1fr;
            }


            .payment-page {
                padding:
                    10rem 5% 5rem;
            }

        }
    </style>

</head>


<body>


    <section class="payment-page">


        <div class="payment-container">


            <!-- =================================================
             PEMBAYARAN
        ================================================== -->

            <div class="payment-card">


                <h1>

                    <i class="fas fa-credit-card"></i>

                    Pembayaran

                </h1>


                <!-- =================================================
                 TRANSFER BANK
            ================================================== -->

                <?php if (
                    $metodePembayaran ===
                    'Transfer Bank'
                ): ?>


                    <div class="payment-method">

                        <i
                            class="fas fa-building-columns">
                        </i>


                        <h3>
                            Transfer Bank
                        </h3>


                        <p>

                            Silakan transfer sesuai
                            total pembayaran ke rekening
                            Toku Coffee.

                        </p>


                        <div class="account-number">

                            <small>
                                Bank BCA
                            </small>


                            <strong>
                                1234567890
                            </strong>


                            <span>
                                a.n. Toku Coffee
                            </span>

                        </div>

                    </div>


                    <!-- =================================================
                 QRIS
            ================================================== -->

                <?php elseif (
                    $metodePembayaran ===
                    'QRIS'
                ): ?>


                    <div class="payment-method">

                        <i
                            class="fas fa-qrcode">
                        </i>


                        <h3>
                            QRIS
                        </h3>


                        <p>

                            Silakan lakukan pembayaran
                            menggunakan QRIS Toku Coffee.

                        </p>


                        <div class="account-number">

                            <strong>
                                QRIS TOKU COFFEE
                            </strong>


                            <span>

                                Scan QRIS Toku Coffee
                                untuk melakukan pembayaran.

                            </span>

                        </div>

                    </div>


                    <!-- =================================================
                 E-WALLET
            ================================================== -->

                <?php elseif (
                    $metodePembayaran ===
                    'E-Wallet'
                ): ?>


                    <div class="payment-method">

                        <i
                            class="fas fa-wallet">
                        </i>


                        <h3>
                            E-Wallet
                        </h3>


                        <p>

                            Silakan lakukan pembayaran
                            menggunakan e-wallet yang tersedia.

                        </p>


                        <div class="account-number">

                            <strong>
                                0812-0000-0000
                            </strong>


                            <span>
                                Toku Coffee
                            </span>

                        </div>

                    </div>


                <?php endif; ?>


                <!-- =================================================
                 ERROR
            ================================================== -->

                <?php if (
                    $errorPembayaran !== ''
                ): ?>

                    <div class="error">

                        <i
                            class="fas fa-circle-exclamation">
                        </i>

                        <?= e(
                            $errorPembayaran
                        ) ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                 CATATAN
            ================================================== -->

                <div class="payment-note">

                    <i
                        class="fas fa-circle-info">
                    </i>


                    Setelah melakukan pembayaran,
                    klik tombol

                    <strong>
                        Konfirmasi Pembayaran
                    </strong>

                    untuk menyelesaikan pesanan.

                </div>


                <!-- =================================================
                 FORM KONFIRMASI
            ================================================== -->

                <form
                    method="POST"
                    onsubmit="return konfirmasiPembayaran();">


                    <input
                        type="hidden"
                        name="action"
                        value="konfirmasi_pembayaran">


                    <button
                        type="submit"
                        class="btn-payment">

                        <i
                            class="fas fa-check-circle">
                        </i>

                        Konfirmasi Pembayaran

                    </button>

                </form>


                <!-- =================================================
                 KEMBALI
            ================================================== -->

                <a
                    href="checkout.php"
                    class="back">

                    <i
                        class="fas fa-arrow-left">
                    </i>

                    Kembali ke Checkout

                </a>


            </div>


            <!-- =================================================
             RINGKASAN PESANAN
        ================================================== -->

            <div class="payment-card">


                <h2>
                    Ringkasan Pesanan
                </h2>


                <?php foreach (
                    $produkCheckout
                    as $produk
                ): ?>


                    <div class="summary-row">


                        <span>

                            <?= e(
                                $produk['nama_produk']
                            ) ?>

                            ×

                            <?= (int)$produk['qty'] ?>

                        </span>


                        <strong>

                            <?= rupiah(
                                $produk['jumlah']
                            ) ?>

                        </strong>


                    </div>


                <?php endforeach; ?>


                <!-- SUBTOTAL -->

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


                <!-- ONGKIR -->

                <div class="summary-row">

                    <span>
                        Ongkir
                    </span>


                    <strong
                        style="color:#527853;">

                        Gratis

                    </strong>

                </div>


                <!-- TOTAL -->

                <div class="summary-total">

                    <span>
                        Total
                    </span>


                    <strong>

                        <?= rupiah(
                            $totalBayar
                        ) ?>

                    </strong>

                </div>


            </div>


        </div>


    </section>


    <script>
        function konfirmasiPembayaran() {
            const metode =
                <?= json_encode(
                    $metodePembayaran
                ) ?>;


            return confirm(
                'Apakah pembayaran melalui ' +
                metode +
                ' sudah dilakukan?\\n\\n' +
                'Setelah dikonfirmasi, pesanan akan langsung dibuat dan Anda akan diarahkan ke halaman Pesanan Selesai.'
            );
        }
    </script>


</body>

</html>
