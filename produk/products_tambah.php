<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

requireRole(['admin', 'staff']);


/* =========================================================
   FUNGSI BANTUAN
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
   INFORMASI USER LOGIN
========================================================= */

$namaUser =
    $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Admin ERP';


$roleUser =
    $_SESSION['role']
    ?? 'Administrator';


$avatarName =
    urlencode($namaUser);


/* =========================================================
   NOTIFIKASI
========================================================= */

$notificationCount = 0;


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM inventory
    WHERE stok <= stok_minimum
");


if ($result) {

    $notificationCount =
        (int)$result->fetch_assoc()['total'];
}


/* =========================================================
   VARIABLE FORM
========================================================= */

$success = '';

$error = '';


$kategori_id = '';

$sku = '';

$nama_produk = '';

$deskripsi = '';

$harga = '';

$harga_beli = '0';

$stok = '0';

$stok_minimum = '10';

$satuan = 'Pcs';

$status = 'aktif';


/* =========================================================
   PROSES TAMBAH PRODUK
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? '';


    if ($action === 'tambah_produk') {


        /* =====================================================
           AMBIL DATA FORM
        ===================================================== */

        $kategori_id =
            (int)($_POST['kategori_id'] ?? 0);


        $sku =
            trim($_POST['sku'] ?? '');


        $nama_produk =
            trim($_POST['nama_produk'] ?? '');


        $deskripsi =
            trim($_POST['deskripsi'] ?? '');


        $harga =
            (float)($_POST['harga'] ?? 0);


        $harga_beli =
            (float)($_POST['harga_beli'] ?? 0);


        $stok =
            (int)($_POST['stok'] ?? 0);


        $stok_minimum =
            (int)($_POST['stok_minimum'] ?? 0);


        $satuan =
            trim($_POST['satuan'] ?? 'Pcs');


        $status =
            trim($_POST['status'] ?? 'aktif');


        /* =====================================================
           VALIDASI
        ===================================================== */

        if ($kategori_id <= 0) {

            $error =
                "Kategori produk wajib dipilih.";
        } elseif ($sku === '') {

            $error =
                "SKU produk wajib diisi.";
        } elseif ($nama_produk === '') {

            $error =
                "Nama produk wajib diisi.";
        } elseif ($harga <= 0) {

            $error =
                "Harga jual harus lebih dari 0.";
        } elseif ($harga_beli < 0) {

            $error =
                "Harga beli tidak boleh kurang dari 0.";
        } elseif ($stok < 0) {

            $error =
                "Stok tidak boleh kurang dari 0.";
        } elseif ($stok_minimum < 0) {

            $error =
                "Stok minimum tidak boleh kurang dari 0.";
        } elseif (!in_array(
            $status,
            ['aktif', 'nonaktif'],
            true
        )) {

            $error =
                "Status produk tidak valid.";
        } else {


            /* =================================================
               CEK KATEGORI
            ================================================= */

            $cekKategori =
                $conn->prepare("
                    SELECT id
                    FROM kategori
                    WHERE id = ?
                      AND status = 'aktif'
                    LIMIT 1
                ");


            if (!$cekKategori) {

                $error =
                    "Query kategori gagal: "
                    . $conn->error;
            } else {

                $cekKategori->bind_param(
                    "i",
                    $kategori_id
                );


                $cekKategori->execute();


                $hasilKategori =
                    $cekKategori->get_result();


                if ($hasilKategori->num_rows === 0) {

                    $error =
                        "Kategori yang dipilih tidak ditemukan atau tidak aktif.";
                }


                $cekKategori->close();
            }


            /* =================================================
               CEK SKU
            ================================================= */

            if ($error === '') {

                $cekSKU =
                    $conn->prepare("
                        SELECT id
                        FROM produk
                        WHERE sku = ?
                        LIMIT 1
                    ");


                if (!$cekSKU) {

                    $error =
                        "Query pengecekan SKU gagal: "
                        . $conn->error;
                } else {

                    $cekSKU->bind_param(
                        "s",
                        $sku
                    );


                    $cekSKU->execute();


                    $hasilSKU =
                        $cekSKU->get_result();


                    if ($hasilSKU->num_rows > 0) {

                        $error =
                            "SKU tersebut sudah digunakan. Silakan gunakan SKU lain.";
                    }


                    $cekSKU->close();
                }
            }


            /* =================================================
               UPLOAD GAMBAR
            ================================================= */

            $gambar =
                '';


            if (
                $error === ''
                &&
                isset($_FILES['gambar'])
                &&
                $_FILES['gambar']['error']
                !== UPLOAD_ERR_NO_FILE
            ) {


                if (
                    $_FILES['gambar']['error']
                    !== UPLOAD_ERR_OK
                ) {

                    $error =
                        "Gambar gagal diupload.";
                } else {


                    $namaFile =
                        $_FILES['gambar']['name'];


                    $tmpFile =
                        $_FILES['gambar']['tmp_name'];


                    $ukuranFile =
                        $_FILES['gambar']['size'];


                    $extension =
                        strtolower(
                            pathinfo(
                                $namaFile,
                                PATHINFO_EXTENSION
                            )
                        );


                    $allowedExtension = [
                        'jpg',
                        'jpeg',
                        'png',
                        'webp'
                    ];


                    if (
                        !in_array(
                            $extension,
                            $allowedExtension,
                            true
                        )
                    ) {

                        $error =
                            "Format gambar harus JPG, JPEG, PNG, atau WEBP.";
                    } elseif (
                        $ukuranFile > 5 * 1024 * 1024
                    ) {

                        $error =
                            "Ukuran gambar maksimal 5 MB.";
                    } else {


                        /* =====================================
                           FOLDER UPLOAD
                        ===================================== */

                        $uploadDir =
                            __DIR__
                            . DIRECTORY_SEPARATOR
                            . 'upload'
                            . DIRECTORY_SEPARATOR;


                        if (!is_dir($uploadDir)) {

                            mkdir(
                                $uploadDir,
                                0755,
                                true
                            );
                        }


                        /* =====================================
                           NAMA FILE
                        ===================================== */

                        $namaFileBaru =
                            'produk_'
                            . time()
                            . '_'
                            . bin2hex(
                                random_bytes(5)
                            )
                            . '.'
                            . $extension;


                        $lokasiFile =
                            $uploadDir
                            . $namaFileBaru;


                        if (
                            move_uploaded_file(
                                $tmpFile,
                                $lokasiFile
                            )
                        ) {

                            $gambar =
                                'upload/'
                                . $namaFileBaru;
                        } else {

                            $error =
                                "Gambar gagal disimpan.";
                        }
                    }
                }
            }


            /* =================================================
               SIMPAN PRODUK
            ================================================= */

            if ($error === '') {


                $stmt =
                    $conn->prepare("
                        INSERT INTO produk
                        (
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
                            ?,
                            ?,
                            ?
                        )
                    ");


                if (!$stmt) {

                    $error =
                        "Query tambah produk gagal dibuat: "
                        . $conn->error;
                } else {


                    $stmt->bind_param(
                        "isssddiisss",
                        $kategori_id,
                        $sku,
                        $nama_produk,
                        $deskripsi,
                        $harga,
                        $harga_beli,
                        $stok,
                        $stok_minimum,
                        $satuan,
                        $gambar,
                        $status
                    );


                    if ($stmt->execute()) {


                        $stmt->close();


                        header(
                            "Location: products.php?success="
                                . urlencode(
                                    "Produk {$nama_produk} berhasil ditambahkan."
                                )
                        );


                        exit;
                    } else {


                        $error =
                            "Produk gagal ditambahkan: "
                            . $stmt->error;


                        $stmt->close();


                        /* =====================================
                           HAPUS GAMBAR JIKA INSERT GAGAL
                        ===================================== */

                        if ($gambar !== '') {

                            $fileHapus =
                                __DIR__
                                . DIRECTORY_SEPARATOR
                                . str_replace(
                                    '/',
                                    DIRECTORY_SEPARATOR,
                                    $gambar
                                );


                            if (
                                file_exists($fileHapus)
                            ) {

                                unlink($fileHapus);
                            }
                        }
                    }
                }
            }
        }
    }
}


/* =========================================================
   DATA KATEGORI
========================================================= */

$categories = [];


$result =
    $conn->query("
        SELECT
            id,
            nama_kategori
        FROM kategori
        WHERE status = 'aktif'
        ORDER BY nama_kategori ASC
    ");


if ($result) {

    while ($row =
        $result->fetch_assoc()
    ) {

        $categories[] =
            $row;
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
        Tambah Produk - Toku Coffee ERP
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
                left .35s ease,
                transform .35s ease;

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

            position:
                relative;

            overflow:
                hidden;

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

            transform:
                translateX(.3rem);

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

            gap:
                2rem;

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
           PAGE ACTION
        ===================================================== */

        .page-actions {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                1rem;

            margin-bottom:
                2rem;

        }


        .page-heading h1 {

            font-size:
                2.3rem;

        }


        .page-heading p {

            font-size:
                1.2rem;

            color:
                #999;

            margin-top:
                .3rem;

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


        .btn-primary {

            background:
                var(--main-color);

            color:
                #fff;

        }


        .btn-primary:hover {

            background:
                #5a4a4a;

            color:
                #fff;

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

            margin-bottom:
                2rem;

        }


        .form-panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .form-panel-header {

            display:
                flex;

            align-items:
                center;

            gap:
                1.5rem;

            margin-bottom:
                2.5rem;

            padding-bottom:
                2rem;

            border-bottom:
                .1rem solid #eee;

        }


        .form-icon {

            width:
                5.5rem;

            height:
                5.5rem;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            border:
                .15rem solid var(--main-color);

            border-radius:
                45% 55% 60% 40%;

            font-size:
                2.2rem;

        }


        .form-panel-header h2 {

            font-size:
                2rem;

        }


        .form-panel-header p {

            font-size:
                1.1rem;

            color:
                #999;

            margin-top:
                .3rem;

        }


        /* =====================================================
           FORM GRID
        ===================================================== */

        .form-grid {

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                1.8rem;

        }


        .form-group {

            display:
                flex;

            flex-direction:
                column;

            gap:
                .7rem;

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


        .form-group label span {

            color:
                var(--red);

        }


        .form-group input,
        .form-group select,
        .form-group textarea {

            width:
                100%;

            padding:
                1.2rem 1.3rem;

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
                0 .3rem 1rem rgba(68,
                    68,
                    51,
                    .05);

        }


        .form-group textarea {

            min-height:
                13rem;

            resize:
                vertical;

        }


        .form-group small {

            font-size:
                1rem;

            color:
                #999;

            line-height:
                1.5;

        }


        /* =====================================================
           INPUT WITH ICON
        ===================================================== */

        .input-icon {

            position:
                relative;

        }


        .input-icon i {

            position:
                absolute;

            left:
                1.3rem;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #999;

            font-size:
                1.3rem;

            pointer-events:
                none;

        }


        .input-icon input {

            padding-left:
                3.8rem;

        }


        /* =====================================================
           UPLOAD AREA
        ===================================================== */

        .upload-wrapper {

            width:
                100%;

        }


        .upload-area {

            position:
                relative;

            display:
                flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            min-height:
                22rem;

            padding:
                2.5rem;

            border:
                .15rem dashed #ccc;

            border-radius:
                var(--border-radius);

            background:
                #faf9f5;

            cursor:
                pointer;

            text-align:
                center;

            overflow:
                hidden;

        }


        .upload-area:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

            background:
                #f7f4ec;

        }


        .upload-area i {

            font-size:
                4rem;

            color:
                var(--main-color);

            margin-bottom:
                1rem;

        }


        .upload-area h3 {

            font-size:
                1.5rem;

            margin-bottom:
                .5rem;

        }


        .upload-area p {

            font-size:
                1.1rem;

            color:
                #999;

        }


        .upload-area input {

            display:
                none;

        }


        .image-preview {

            display:
                none;

            margin-top:
                1.5rem;

            text-align:
                center;

        }


        .image-preview img {

            width:
                20rem;

            height:
                20rem;

            object-fit:
                cover;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

        }


        /* =====================================================
           FORM ACTIONS
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
           INFO BOX
        ===================================================== */

        .info-box {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                1rem;

            padding:
                1.5rem;

            margin-top:
                2rem;

            background:
                #f7f4ec;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

        }


        .info-box i {

            font-size:
                1.6rem;

            margin-top:
                .2rem;

        }


        .info-box p {

            font-size:
                1.1rem;

            color:
                #777;

            line-height:
                1.7;

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

        @media (max-width: 900px) {

            .sidebar {

                left:
                    -27rem;

                box-shadow:
                    .5rem 0 2rem rgba(0,
                        0,
                        0,
                        .08);

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


            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .form-group.full {

                grid-column:
                    auto;

            }

        }


        @media (max-width: 550px) {

            html {

                font-size:
                    50%;

            }


            .content {

                padding:
                    1.5rem;

            }


            .page-actions {

                flex-direction:
                    column;

                align-items:
                    stretch;

            }


            .page-actions .btn {

                width:
                    100%;

            }


            .form-panel {

                padding:
                    1.5rem;

            }


            .form-panel-header {

                align-items:
                    flex-start;

            }


            .form-actions {

                flex-direction:
                    column;

            }


            .form-actions .btn {

                width:
                    100%;

            }


            .upload-area {

                min-height:
                    18rem;

            }

        }


        /* =========================================================
           ERP SUCCESS TOAST
        ========================================================= */

        .erp-toast {

            position:
                fixed;

            top:
                2.5rem;

            right:
                3rem;

            width:
                390px;

            min-height:
                90px;

            background:
                #ffffff;

            border:
                .2rem solid #443;

            border-radius:
                95% 4% 97% 5% / 4% 94% 3% 95%;

            display:
                flex;

            align-items:
                center;

            gap:
                1.4rem;

            padding:
                1.5rem 1.7rem;

            box-shadow:
                0 1rem 3rem rgba(68,
                    51,
                    51,
                    .15);

            z-index:
                99999;

            animation:
                toastIn .45s ease forwards;

        }


        .toast-icon {

            width:
                4.5rem;

            height:
                4.5rem;

            flex-shrink:
                0;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #527853;

            color:
                #fff;

            border-radius:
                50%;

            font-size:
                1.8rem;

        }


        .toast-content {

            flex:
                1;

            display:
                flex;

            flex-direction:
                column;

            gap:
                .35rem;

        }


        .toast-content strong {

            font-size:
                1.4rem;

            color:
                #443;

        }


        .toast-content span {

            font-size:
                1.1rem;

            color:
                #888;

            line-height:
                1.5;

        }


        .toast-close {

            border:
                0;

            background:
                transparent;

            color:
                #888;

            font-size:
                1.5rem;

            cursor:
                pointer;

        }


        .toast-close:hover {

            color:
                #a94442;

            transform:
                rotate(90deg);

        }


        .toast-progress {

            position:
                absolute;

            left:
                0;

            bottom:
                0;

            height:
                .35rem;

            width:
                100%;

            background:
                #527853;

            animation:
                toastProgress 4s linear forwards;

        }


        @keyframes toastIn {

            from {

                opacity:
                    0;

                transform:
                    translateX(120%);

            }

            to {

                opacity:
                    1;

                transform:
                    translateX(0);

            }

        }


        @keyframes toastOut {

            from {

                opacity:
                    1;

                transform:
                    translateX(0);

            }

            to {

                opacity:
                    0;

                transform:
                    translateX(120%);

            }

        }


        @keyframes toastProgress {

            from {

                width:
                    100%;

            }

            to {

                width:
                    0;

            }

        }


        @media (max-width: 768px) {

            .erp-toast {

                top:
                    2rem;

                left:
                    2rem;

                right:
                    2rem;

                width:
                    auto;

                padding:
                    1.3rem;

            }

        }
    </style>

</head>


<body>


    <!-- =====================================================
         ERROR
    ===================================================== -->

    <?php if ($error): ?>

        <div class="alert error">

            <i class="fas fa-circle-exclamation"></i>

            <?= e($error) ?>

        </div>

    <?php endif; ?>



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
                        Tambah Produk
                    </h2>

                    <p>
                        Tambahkan produk baru ke Toku Coffee ERP
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


            <!-- PAGE ACTION -->

            <div class="page-actions">


                <div class="page-heading">

                    <h1>
                        Tambah Produk
                    </h1>

                    <p>
                        Masukkan informasi produk yang akan ditambahkan ke katalog.
                    </p>

                </div>


                <a
                    href="products.php"
                    class="btn">

                    <i class="fas fa-arrow-left"></i>

                    Kembali ke Produk

                </a>


            </div>



            <!-- =================================================
                 FORM PANEL
            ================================================= -->

            <section class="form-panel">


                <div class="form-panel-header">


                    <div class="form-icon">

                        <i class="fas fa-box-open"></i>

                    </div>


                    <div>

                        <h2>
                            Informasi Produk
                        </h2>

                        <p>
                            Lengkapi data produk dengan benar sebelum disimpan.
                        </p>

                    </div>


                </div>



                <form
                    method="POST"
                    action=""
                    enctype="multipart/form-data">


                    <input
                        type="hidden"
                        name="action"
                        value="tambah_produk">



                    <div class="form-grid">


                        <!-- =================================================
                             KATEGORI
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Kategori

                                <span>*</span>

                            </label>


                            <select
                                name="kategori_id"
                                required>


                                <option value="">

                                    Pilih kategori produk

                                </option>


                                <?php foreach (
                                    $categories
                                    as $category
                                ): ?>


                                    <option
                                        value="<?= (int)$category['id'] ?>"
                                        <?= (
                                            (int)$kategori_id
                                            ===
                                            (int)$category['id']
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>>

                                        <?= e(
                                            $category['nama_kategori']
                                        ) ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>



                        <!-- =================================================
                             SKU
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                SKU Produk

                                <span>*</span>

                            </label>


                            <div class="input-icon">

                                <i class="fas fa-barcode"></i>


                                <input
                                    type="text"
                                    name="sku"
                                    value="<?= e($sku) ?>"
                                    placeholder="Contoh: SKU-011"
                                    required>


                            </div>


                            <small>

                                SKU harus unik untuk setiap produk.

                            </small>


                        </div>



                        <!-- =================================================
                             NAMA PRODUK
                        ================================================= -->

                        <div class="form-group full">

                            <label>

                                Nama Produk

                                <span>*</span>

                            </label>


                            <div class="input-icon">

                                <i class="fas fa-mug-hot"></i>


                                <input
                                    type="text"
                                    name="nama_produk"
                                    value="<?= e($nama_produk) ?>"
                                    placeholder="Contoh: Kopi Arabica Premium"
                                    required>


                            </div>


                        </div>



                        <!-- =================================================
                             HARGA JUAL
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Harga Jual

                                <span>*</span>

                            </label>


                            <div class="input-icon">

                                <i class="fas fa-tag"></i>


                                <input
                                    type="number"
                                    name="harga"
                                    value="<?= e($harga) ?>"
                                    min="1"
                                    step="1"
                                    placeholder="85000"
                                    required>


                            </div>


                            <small>

                                Harga yang ditampilkan kepada pelanggan.

                            </small>


                        </div>



                        <!-- =================================================
                             HARGA BELI
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Harga Beli

                            </label>


                            <div class="input-icon">

                                <i class="fas fa-money-bill-wave"></i>


                                <input
                                    type="number"
                                    name="harga_beli"
                                    value="<?= e($harga_beli) ?>"
                                    min="0"
                                    step="1"
                                    placeholder="60000">


                            </div>


                            <small>

                                Harga modal atau harga pembelian dari supplier.

                            </small>


                        </div>



                        <!-- =================================================
                             STOK
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Stok Awal

                            </label>


                            <div class="input-icon">

                                <i class="fas fa-boxes-stacked"></i>


                                <input
                                    type="number"
                                    name="stok"
                                    value="<?= e($stok) ?>"
                                    min="0"
                                    placeholder="0">


                            </div>


                        </div>



                        <!-- =================================================
                             STOK MINIMUM
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Stok Minimum

                            </label>


                            <div class="input-icon">

                                <i class="fas fa-triangle-exclamation"></i>


                                <input
                                    type="number"
                                    name="stok_minimum"
                                    value="<?= e($stok_minimum) ?>"
                                    min="0"
                                    placeholder="10">


                            </div>


                            <small>

                                Digunakan untuk indikator stok menipis.

                            </small>


                        </div>



                        <!-- =================================================
                             SATUAN
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Satuan

                            </label>


                            <select
                                name="satuan">


                                <?php

                                $daftarSatuan = [

                                    'Pcs',

                                    'Pack',

                                    'Unit',

                                    'Kg',

                                    'Gram',

                                    'Liter'

                                ];

                                ?>


                                <?php foreach (
                                    $daftarSatuan
                                    as $item
                                ): ?>


                                    <option
                                        value="<?= e($item) ?>"
                                        <?= $satuan === $item
                                            ? 'selected'
                                            : ''
                                        ?>>

                                        <?= e($item) ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                        </div>



                        <!-- =================================================
                             STATUS
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Status Produk

                            </label>


                            <select
                                name="status">


                                <option
                                    value="aktif"
                                    <?= $status === 'aktif'
                                        ? 'selected'
                                        : ''
                                    ?>>

                                    Aktif

                                </option>


                                <option
                                    value="nonaktif"
                                    <?= $status === 'nonaktif'
                                        ? 'selected'
                                        : ''
                                    ?>>

                                    Nonaktif

                                </option>


                            </select>


                        </div>



                        <!-- =================================================
                             GAMBAR
                        ================================================= -->

                        <div class="form-group full">


                            <label>

                                Foto Produk

                            </label>


                            <div class="upload-wrapper">


                                <label
                                    for="gambar"
                                    class="upload-area">


                                    <i class="fas fa-cloud-arrow-up"></i>


                                    <h3>

                                        Upload Foto Produk

                                    </h3>


                                    <p>

                                        Klik area ini untuk memilih gambar.

                                    </p>


                                    <p>

                                        JPG, JPEG, PNG atau WEBP —
                                        maksimal 5 MB.

                                    </p>


                                    <input
                                        type="file"
                                        id="gambar"
                                        name="gambar"
                                        accept="image/jpeg,image/png,image/webp">


                                </label>


                                <div
                                    class="image-preview"
                                    id="imagePreview">


                                    <img
                                        id="previewImage"
                                        src=""
                                        alt="Preview Produk">


                                </div>


                            </div>


                        </div>



                        <!-- =================================================
                             DESKRIPSI
                        ================================================= -->

                        <div class="form-group full">

                            <label>

                                Deskripsi Produk

                            </label>


                            <textarea
                                name="deskripsi"
                                placeholder="Masukkan deskripsi produk..."><?= e($deskripsi) ?></textarea>


                            <small>

                                Jelaskan informasi produk seperti jenis kopi,
                                ukuran, rasa, atau informasi lainnya.

                            </small>


                        </div>


                    </div>



                    <!-- =================================================
                         INFO
                    ================================================= -->

                    <div class="info-box">

                        <i class="fas fa-circle-info"></i>


                        <p>

                            Pastikan SKU, kategori, nama produk,
                            harga dan informasi stok sudah benar
                            sebelum menekan tombol Simpan Produk.
                            Produk dengan status aktif dapat digunakan
                            dalam katalog dan transaksi.

                        </p>

                    </div>



                    <!-- =================================================
                         FORM ACTION
                    ================================================= -->

                    <div class="form-actions">


                        <a
                            href="products.php"
                            class="btn">


                            <i class="fas fa-xmark"></i>


                            Batal


                        </a>



                        <button
                            type="submit"
                            class="btn btn-primary">


                            <i class="fas fa-save"></i>


                            Simpan Produk


                        </button>


                    </div>


                </form>


            </section>


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



        /* =====================================================
           TUTUP SIDEBAR SAAT LINK DIKLIK
        ===================================================== */

        document
            .querySelectorAll('.sidebar a')
            .forEach(
                function(link) {


                    link.addEventListener(
                        'click',
                        function() {


                            if (
                                window.innerWidth <=
                                900
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

                }
            );



        /* =====================================================
           CLOSE SIDEBAR DI LUAR
        ===================================================== */

        document.addEventListener(
            'click',
            function(event) {


                if (

                    window.innerWidth <= 900

                    &&

                    sidebar.classList.contains(
                        'active'
                    )

                    &&

                    !sidebar.contains(
                        event.target
                    )

                    &&

                    !menuBtn.contains(
                        event.target
                    )

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



        /* =====================================================
           PREVIEW GAMBAR
        ===================================================== */

        const gambarInput =
            document.getElementById(
                'gambar'
            );


        const imagePreview =
            document.getElementById(
                'imagePreview'
            );


        const previewImage =
            document.getElementById(
                'previewImage'
            );


        gambarInput.addEventListener(
            'change',
            function(event) {


                const file =
                    event.target.files[0];


                if (!file) {


                    imagePreview.style.display =
                        'none';


                    previewImage.src =
                        '';


                    return;

                }


                const reader =
                    new FileReader();


                reader.onload =
                    function(e) {


                        previewImage.src =
                            e.target.result;


                        imagePreview.style.display =
                            'block';


                    };


                reader.readAsDataURL(
                    file
                );

            }
        );
    </script>


</body>

</html>
