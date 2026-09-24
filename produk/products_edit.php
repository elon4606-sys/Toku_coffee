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
        return strtolower(str_replace([' ', '_'], '-', $status));
    }
}


/* =========================================================
   CEK ID PRODUK
========================================================= */

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: products.php?error=" . urlencode("ID produk tidak valid."));
    exit;
}


/* =========================================================
   INFORMASI USER LOGIN
========================================================= */

$namaUser = $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Admin ERP';

$roleUser = $_SESSION['role']
    ?? 'Administrator';

$avatarName = urlencode($namaUser);


/* =========================================================
   DATA KATEGORI
========================================================= */

$categories = [];

$result = $conn->query("
    SELECT
        id,
        nama_kategori
    FROM kategori
    WHERE status = 'aktif'
    ORDER BY nama_kategori ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $categories[] = $row;
    }
}


/* =========================================================
   AMBIL DATA PRODUK
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        kategori_id,
        sku,
        nama_produk,
        deskripsi,
        harga,
        harga_beli,
        stok,
        stok_minimum,
        satuan,
        gambar,
        status,
        created_at
    FROM produk
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Query produk gagal: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();

$stmt->close();


if (!$product) {
    header(
        "Location: products.php?error="
            . urlencode("Produk tidak ditemukan.")
    );
    exit;
}


/* =========================================================
   PROSES UPDATE PRODUK
========================================================= */

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $kategori_id = (int)($_POST['kategori_id'] ?? 0);

    $sku = trim($_POST['sku'] ?? '');

    $nama_produk = trim($_POST['nama_produk'] ?? '');

    $deskripsi = trim($_POST['deskripsi'] ?? '');

    $harga = (float)($_POST['harga'] ?? 0);

    $harga_beli = (float)($_POST['harga_beli'] ?? 0);

    $stok = (int)($_POST['stok'] ?? 0);

    $stok_minimum = (int)($_POST['stok_minimum'] ?? 0);

    $satuan = trim($_POST['satuan'] ?? 'Pcs');

    $status = trim($_POST['status'] ?? 'aktif');

    $gambarLama = $product['gambar'] ?? '';

    $gambarBaru = $gambarLama;


    /* =====================================================
       VALIDASI
    ===================================================== */

    if ($kategori_id <= 0) {

        $error = "Kategori produk wajib dipilih.";
    } elseif ($sku === '') {

        $error = "SKU produk wajib diisi.";
    } elseif ($nama_produk === '') {

        $error = "Nama produk wajib diisi.";
    } elseif ($harga <= 0) {

        $error = "Harga jual harus lebih dari 0.";
    } elseif ($harga_beli < 0) {

        $error = "Harga beli tidak boleh kurang dari 0.";
    } elseif ($stok < 0) {

        $error = "Stok tidak boleh kurang dari 0.";
    } elseif ($stok_minimum < 0) {

        $error = "Stok minimum tidak boleh kurang dari 0.";
    } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {

        $error = "Status produk tidak valid.";
    }


    /* =====================================================
       CEK KATEGORI
    ===================================================== */

    if ($error === '') {

        $stmt = $conn->prepare("
            SELECT id
            FROM kategori
            WHERE id = ?
              AND status = 'aktif'
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Query kategori gagal: " . $conn->error;
        } else {

            $stmt->bind_param("i", $kategori_id);

            $stmt->execute();

            $resultKategori = $stmt->get_result();

            if ($resultKategori->num_rows === 0) {

                $error =
                    "Kategori yang dipilih tidak ditemukan atau tidak aktif.";
            }

            $stmt->close();
        }
    }


    /* =====================================================
       CEK SKU
    ===================================================== */

    if ($error === '') {

        $stmt = $conn->prepare("
            SELECT id
            FROM produk
            WHERE sku = ?
              AND id != ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error =
                "Query pengecekan SKU gagal: "
                . $conn->error;
        } else {

            $stmt->bind_param(
                "si",
                $sku,
                $id
            );

            $stmt->execute();

            $resultSKU = $stmt->get_result();

            if ($resultSKU->num_rows > 0) {

                $error =
                    "SKU tersebut sudah digunakan oleh produk lain.";
            }

            $stmt->close();
        }
    }


    /* =====================================================
       UPLOAD FOTO
    ===================================================== */

    if (
        $error === ''
        && isset($_FILES['gambar'])
        && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        $file = $_FILES['gambar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {

            $error = "Upload foto produk gagal.";
        } elseif ($file['size'] > 2 * 1024 * 1024) {

            $error =
                "Ukuran foto maksimal 2 MB.";
        } else {

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            $mimeType = finfo_file(
                $finfo,
                $file['tmp_name']
            );

            finfo_close($finfo);

            if (!isset($allowedTypes[$mimeType])) {

                $error =
                    "Format foto harus JPG, PNG, atau WEBP.";
            } else {

                $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR;
                if (!is_dir($uploadDir)) {

                    if (!mkdir($uploadDir, 0755, true)) {

                        $error =
                            "Folder upload tidak dapat dibuat.";
                    }
                }


                if ($error === '') {

                    $extension =
                        $allowedTypes[$mimeType];

                    $fileName =
                        'produk_'
                        . time()
                        . '_'
                        . bin2hex(random_bytes(5))
                        . '.'
                        . $extension;

                    $targetPath =
                        $uploadDir . $fileName;


                    if (
                        move_uploaded_file(
                            $file['tmp_name'],
                            $targetPath
                        )
                    ) {

                        $gambarBaru = $fileName;


                        /* =====================================
                           HAPUS FOTO LAMA
                        ===================================== */

                        if (!empty($gambarLama)) {

                            $oldPath =
                                dirname(__DIR__)
                                . DIRECTORY_SEPARATOR
                                . 'upload'
                                . DIRECTORY_SEPARATOR
                                . basename($gambarLama);

                            if (
                                file_exists($oldPath)
                                && is_file($oldPath)
                            ) {
                                @unlink($oldPath);
                            }
                        }
                    } else {

                        $error =
                            "Foto produk gagal disimpan.";
                    }
                }
            }
        }
    }


    /* =====================================================
       UPDATE DATABASE
    ===================================================== */

    if ($error === '') {

        $stmt = $conn->prepare("
            UPDATE produk
            SET
                kategori_id = ?,
                sku = ?,
                nama_produk = ?,
                deskripsi = ?,
                harga = ?,
                harga_beli = ?,
                stok = ?,
                stok_minimum = ?,
                satuan = ?,
                gambar = ?,
                status = ?
            WHERE id = ?
        ");

        if (!$stmt) {

            $error =
                "Query update produk gagal: "
                . $conn->error;
        } else {

            $stmt->bind_param(
                "isssddiisssi",
                $kategori_id,
                $sku,
                $nama_produk,
                $deskripsi,
                $harga,
                $harga_beli,
                $stok,
                $stok_minimum,
                $satuan,
                $gambarBaru,
                $status,
                $id
            );


            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: products.php?success="
                        . urlencode(
                            "Produk {$nama_produk} berhasil diperbarui."
                        )
                );

                exit;
            } else {

                $error =
                    "Produk gagal diperbarui: "
                    . $stmt->error;

                $stmt->close();
            }
        }
    }


    /* =====================================================
       UPDATE DATA FORM JIKA ERROR
    ===================================================== */

    $product['kategori_id'] = $kategori_id;
    $product['sku'] = $sku;
    $product['nama_produk'] = $nama_produk;
    $product['deskripsi'] = $deskripsi;
    $product['harga'] = $harga;
    $product['harga_beli'] = $harga_beli;
    $product['stok'] = $stok;
    $product['stok_minimum'] = $stok_minimum;
    $product['satuan'] = $satuan;
    $product['gambar'] = $gambarBaru;
    $product['status'] = $status;
}


/* =========================================================
   PATH FOTO
========================================================= */
$fotoProduk = trim($product['gambar'] ?? '');

if (!empty($fotoProduk)) {
    if (
        strpos($fotoProduk, 'http://') === 0 ||
        strpos($fotoProduk, 'https://') === 0
    ) {
        // URL tetap
    } else {
        $fotoProduk = '../upload/' . basename($fotoProduk);
    }
} else {
    $fotoProduk = '../upload/toku-americano.png';
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
        Edit Produk - Toku Coffee ERP
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

            margin: 0;

            padding: 0;

            box-sizing: border-box;

            outline: none;

            border: none;

            text-decoration: none;

            transition:
                all .2s linear;

        }


        html {

            font-size: 62.5%;

            overflow-x: hidden;

            scroll-behavior: smooth;

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

            position: fixed;

            top: 0;

            left: 0;

            width: 26rem;

            height: 100vh;

            background: #fff;

            border-right:
                .1rem solid #eee;

            padding:
                2.5rem 1.5rem;

            z-index: 1000;

            overflow-y: auto;

            transition:
                left .35s ease;

        }


        .logo {

            display: block;

            text-align: center;

            color:
                var(--main-color);

            font-size: 2.6rem;

            font-weight: 600;

            margin-bottom: 3rem;

        }


        .logo:hover {

            transform:
                scale(1.03);

        }


        .logo i {

            margin-right: .5rem;

        }


        .menu-title {

            font-size: 1.1rem;

            color: #aaa;

            padding:
                0 1.5rem;

            margin-bottom: 1rem;

            text-transform: uppercase;

        }


        .sidebar a {

            display: flex;

            align-items: center;

            gap: 1.3rem;

            padding:
                1.3rem 1.5rem;

            margin-bottom: .7rem;

            color:
                var(--main-color);

            font-size: 1.5rem;

            border-radius:
                var(--border-radius);

        }


        .sidebar a i {

            width: 2rem;

            font-size: 1.7rem;

            text-align: center;

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
           EDIT CONTAINER
        ===================================================== */

        .edit-container {

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
                30rem;

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


        .image-preview .no-image {

            text-align:
                center;

            color:
                #aaa;

        }


        .image-preview .no-image i {

            display:
                block;

            font-size:
                6rem;

            margin-bottom:
                1rem;

        }


        .image-info {

            padding:
                1.2rem;

            background:
                #faf9f5;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

        }


        .image-info strong {

            display:
                block;

            font-size:
                1.1rem;

            margin-bottom:
                .3rem;

        }


        .image-info span {

            font-size:
                1rem;

            color:
                #888;

            word-break:
                break-all;

        }


        /* =====================================================
           FORM PANEL
        ===================================================== */

        .form-panel {

            background:
                #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding:
                2.5rem;

        }


        .form-panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .form-header {

            margin-bottom:
                2.5rem;

        }


        .form-header h2 {

            font-size:
                2rem;

            margin-bottom:
                .4rem;

        }


        .form-header p {

            font-size:
                1.2rem;

            color:
                #999;

        }


        /* =====================================================
           ALERT
        ===================================================== */

        .alert {

            padding:
                1.3rem 1.5rem;

            border:
                .1rem solid currentColor;

            border-radius:
                var(--border-radius);

            margin-bottom:
                2rem;

            font-size:
                1.2rem;

            display:
                flex;

            align-items:
                center;

            gap:
                1rem;

        }


        .alert.error {

            color:
                var(--red);

            background:
                #fff0ef;

        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-grid {

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                1.5rem;

        }


        .form-group {

            display:
                flex;

            flex-direction:
                column;

            gap:
                .6rem;

        }


        .form-group.full {

            grid-column:
                1 / -1;

        }


        .form-group label {

            font-size:
                1.2rem;

            font-weight:
                500;

        }


        .form-group input,
        .form-group select,
        .form-group textarea {

            width:
                100%;

            padding:
                1.1rem 1.2rem;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            font-size:
                1.2rem;

            color:
                var(--main-color);

            background:
                #fff;

        }


        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {

            border:
                .15rem solid var(--main-color);

            box-shadow:
                0 .3rem 1rem rgba(68, 68, 51, .05);

        }


        .form-group textarea {

            min-height:
                12rem;

            resize:
                vertical;

        }


        .form-group small {

            font-size:
                1rem;

            color:
                #999;

        }


        /* =====================================================
           FILE INPUT
        ===================================================== */

        .file-input {

            padding:
                1rem !important;

            background:
                #faf9f5 !important;

        }


        /* =====================================================
           CURRENT IMAGE
        ===================================================== */

        .current-image {

            margin-top:
                1rem;

            padding:
                1rem;

            background:
                #f7f4ec;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

            font-size:
                1rem;

            color:
                #777;

        }


        /* =====================================================
           BUTTON
        ===================================================== */

        .form-actions {

            display:
                flex;

            justify-content:
                flex-end;

            gap:
                1rem;

            margin-top:
                2.5rem;

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


        .btn-save {

            background:
                #f3f0e8;

        }


        /* =====================================================
           PRODUCT INFO
        ===================================================== */

        .product-info {

            display:
                grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap:
                1rem;

            margin-bottom:
                2rem;

        }


        .info-box {

            padding:
                1.3rem;

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
                .3rem;

        }


        .info-box strong {

            font-size:
                1.3rem;

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

            .edit-container {

                grid-template-columns:
                    1fr;

            }


            .image-panel {

                position:
                    static;

            }


            .image-preview {

                height:
                    25rem;

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


            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .form-group.full {

                grid-column:
                    auto;

            }


            .product-info {

                grid-template-columns:
                    1fr;

            }


            .content {

                padding:
                    1.5rem;

            }


            .form-panel,
            .image-panel {

                padding:
                    1.5rem;

            }


            .form-actions {

                flex-direction:
                    column;

            }


            .form-actions .btn {

                width:
                    100%;

            }


            .image-preview {

                height:
                    22rem;

            }

        }


        /* =====================================================
           PRINT
        ===================================================== */

        @media print {

            .sidebar,
            .topbar,
            .back-button,
            .form-actions {

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


            .edit-container {

                display:
                    block;

            }


            .image-panel,
            .form-panel {

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
            href="../dashboar.php"
            class="logo">

            <i class="fas fa-mug-hot"></i>

            TOKU COFFEE

        </a>


        <div class="menu-title">
            Menu Utama
        </div>


        <a href="../dashboar.php">

            <i class="fas fa-chart-pie"></i>

            Dashboard

        </a>


        <a href="../orders.php">

            <i class="fas fa-shopping-bag"></i>

            Pesanan

        </a>


        <a
            href="../products.php"
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


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="topbar-left">


                <i
                    class="fas fa-bars"
                    id="menu-btn">
                </i>


                <div class="page-title">

                    <h2>
                        Edit Produk
                    </h2>

                    <p>
                        Perbarui informasi produk Toku Coffee ERP
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


        <!-- =====================================================
         CONTENT
    ===================================================== -->

        <section class="content">


            <!-- BACK -->

            <div class="back-button">

                <a href="products.php">

                    <i class="fas fa-arrow-left"></i>

                    Kembali ke Produk

                </a>

            </div>


            <?php if ($error): ?>

                <div class="alert error">

                    <i class="fas fa-circle-exclamation"></i>

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
             EDIT CONTAINER
        ================================================= -->

            <div class="edit-container">


                <!-- =================================================
                 IMAGE PANEL
            ================================================= -->

                <section class="image-panel">


                    <h2>
                        Foto Produk
                    </h2>


                    <p>
                        Preview gambar produk yang sedang diedit.
                    </p>


                    <div class="image-preview"
                        id="imagePreview">

                        <?php if (!empty($fotoProduk)): ?>

                            <img
                                id="previewImage"
                                src="<?= e($fotoProduk) ?>"
                                alt="<?= e($product['nama_produk']) ?>"
                                onerror="
                                this.src='../upload/toku-americano.png';
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

                            <div
                                class="no-image"
                                id="noImage">

                                <i class="fas fa-mug-hot"></i>

                                <span>
                                    Belum ada foto produk
                                </span>

                            </div>

                            <img
                                id="previewImage"
                                src=""
                                alt="Preview"
                                style="display:none;">

                        <?php endif; ?>


                    </div>


                    <div class="image-info">

                        <strong>
                            Nama Produk
                        </strong>

                        <span>
                            <?= e($product['nama_produk']) ?>
                        </span>

                    </div>


                </section>


                <!-- =================================================
                 FORM PANEL
            ================================================= -->

                <section class="form-panel">


                    <div class="form-header">

                        <h2>
                            Informasi Produk
                        </h2>

                        <p>
                            Ubah data produk kemudian klik
                            <strong>Simpan Perubahan</strong>.
                        </p>

                    </div>


                    <!-- PRODUCT INFO -->

                    <div class="product-info">


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
                                SKU Saat Ini
                            </span>

                            <strong>
                                <?= e($product['sku']) ?>
                            </strong>

                        </div>


                        <div class="info-box">

                            <span>
                                Harga Jual
                            </span>

                            <strong>
                                <?= rupiah($product['harga']) ?>
                            </strong>

                        </div>


                    </div>


                    <!-- FORM -->

                    <form
                        method="POST"
                        enctype="multipart/form-data">


                        <div class="form-grid">


                            <!-- KATEGORI -->

                            <div class="form-group">

                                <label>
                                    Kategori *
                                </label>

                                <select
                                    name="kategori_id"
                                    required>

                                    <option value="">
                                        Pilih kategori
                                    </option>


                                    <?php foreach ($categories as $category): ?>

                                        <option
                                            value="<?= (int)$category['id'] ?>"
                                            <?= ((int)$product['kategori_id'] === (int)$category['id'])
                                                ? 'selected'
                                                : '' ?>>

                                            <?= e($category['nama_kategori']) ?>

                                        </option>

                                    <?php endforeach; ?>


                                </select>

                            </div>


                            <!-- SKU -->

                            <div class="form-group">

                                <label>
                                    SKU *
                                </label>

                                <input
                                    type="text"
                                    name="sku"
                                    value="<?= e($product['sku']) ?>"
                                    placeholder="Contoh: SKU-011"
                                    required>

                            </div>


                            <!-- NAMA -->

                            <div class="form-group full">

                                <label>
                                    Nama Produk *
                                </label>

                                <input
                                    type="text"
                                    name="nama_produk"
                                    value="<?= e($product['nama_produk']) ?>"
                                    placeholder="Masukkan nama produk"
                                    required>

                            </div>


                            <!-- DESKRIPSI -->

                            <div class="form-group full">

                                <label>
                                    Deskripsi
                                </label>

                                <textarea
                                    name="deskripsi"
                                    placeholder="Deskripsi produk..."><?= e($product['deskripsi']) ?></textarea>

                            </div>


                            <!-- HARGA JUAL -->

                            <div class="form-group">

                                <label>
                                    Harga Jual *
                                </label>

                                <input
                                    type="number"
                                    name="harga"
                                    min="1"
                                    step="1"
                                    value="<?= e($product['harga']) ?>"
                                    required>

                            </div>


                            <!-- HARGA BELI -->

                            <div class="form-group">

                                <label>
                                    Harga Beli
                                </label>

                                <input
                                    type="number"
                                    name="harga_beli"
                                    min="0"
                                    step="1"
                                    value="<?= e($product['harga_beli']) ?>">

                            </div>


                            <!-- STOK -->

                            <div class="form-group">

                                <label>
                                    Stok
                                </label>

                                <input
                                    type="number"
                                    name="stok"
                                    min="0"
                                    value="<?= e($product['stok']) ?>">

                            </div>


                            <!-- STOK MINIMUM -->

                            <div class="form-group">

                                <label>
                                    Stok Minimum
                                </label>

                                <input
                                    type="number"
                                    name="stok_minimum"
                                    min="0"
                                    value="<?= e($product['stok_minimum']) ?>">

                            </div>


                            <!-- SATUAN -->

                            <div class="form-group">

                                <label>
                                    Satuan
                                </label>

                                <select name="satuan">


                                    <?php
                                    $satuanList = [
                                        'Pcs',
                                        'Pack',
                                        'Unit',
                                        'Kg',
                                        'Gram',
                                        'Liter'
                                    ];
                                    ?>


                                    <?php foreach ($satuanList as $satuan): ?>

                                        <option
                                            value="<?= e($satuan) ?>"
                                            <?= $product['satuan'] === $satuan
                                                ? 'selected'
                                                : '' ?>>

                                            <?= e($satuan) ?>

                                        </option>

                                    <?php endforeach; ?>


                                </select>

                            </div>


                            <!-- STATUS -->

                            <div class="form-group">

                                <label>
                                    Status
                                </label>

                                <select name="status">

                                    <option
                                        value="aktif"
                                        <?= $product['status'] === 'aktif'
                                            ? 'selected'
                                            : '' ?>>

                                        Aktif

                                    </option>


                                    <option
                                        value="nonaktif"
                                        <?= $product['status'] === 'nonaktif'
                                            ? 'selected'
                                            : '' ?>>

                                        Nonaktif

                                    </option>

                                </select>

                            </div>


                            <!-- FOTO -->

                            <div class="form-group full">

                                <label>
                                    Ganti Foto Produk
                                </label>

                                <input
                                    type="file"
                                    class="file-input"
                                    name="gambar"
                                    id="gambar"
                                    accept="image/jpeg,image/png,image/webp">

                                <small>
                                    JPG, PNG, WEBP. Maksimal 2 MB.
                                    Kosongkan jika tidak ingin mengganti foto.
                                </small>


                                <?php if (!empty($fotoProduk)): ?>

                                    <div class="current-image">

                                        <i class="fas fa-image"></i>

                                        Foto saat ini:
                                        <?= e($fotoProduk) ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                        </div>


                        <!-- FORM ACTION -->

                        <div class="form-actions">


                            <a
                                href="products.php"
                                class="btn">

                                <i class="fas fa-xmark"></i>

                                Batal

                            </a>


                            <button
                                type="submit"
                                class="btn btn-save">

                                <i class="fas fa-save"></i>

                                Simpan Perubahan

                            </button>


                        </div>


                    </form>


                </section>


            </div>


        </section>


    </main>


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

                    sidebar.classList.toggle('active');

                    this.classList.toggle('fa-bars');

                    this.classList.toggle('fa-xmark');

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

                        if (window.innerWidth <= 900) {

                            sidebar.classList.remove('active');

                            menuBtn.classList.remove('fa-xmark');

                            menuBtn.classList.add('fa-bars');

                        }

                    }
                );

            });


        /* =====================================================
           PREVIEW FOTO
        ===================================================== */

        const gambarInput =
            document.getElementById('gambar');

        const previewImage =
            document.getElementById('previewImage');

        const noImage =
            document.getElementById('noImage');


        if (gambarInput) {

            gambarInput.addEventListener(
                'change',
                function() {

                    const file =
                        this.files[0];

                    if (!file) {
                        return;
                    }


                    if (file.size > 2 * 1024 * 1024) {

                        alert(
                            'Ukuran foto maksimal 2 MB.'
                        );

                        this.value = '';

                        return;
                    }


                    const allowed = [
                        'image/jpeg',
                        'image/png',
                        'image/webp'
                    ];


                    if (!allowed.includes(file.type)) {

                        alert(
                            'Format foto harus JPG, PNG, atau WEBP.'
                        );

                        this.value = '';

                        return;
                    }


                    const reader =
                        new FileReader();


                    reader.onload =
                        function(event) {

                            if (previewImage) {

                                previewImage.src =
                                    event.target.result;

                                previewImage.style.display =
                                    'block';

                            }


                            if (noImage) {

                                noImage.style.display =
                                    'none';

                            }

                        };


                    reader.readAsDataURL(file);

                }
            );

        }
    </script>


</body>

</html>
