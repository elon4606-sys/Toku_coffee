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
            return strtolower(
                str_replace(
                    [' ', '_'],
                    '-',
                    $status
                )
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
    NOTIFIKASI INVENTORY
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
    TAMBAH PRODUK
    ========================================================= */

    $success = '';
    $error = '';

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
            UPLOAD GAMBAR
            ===================================================== */

            $gambar = '';

            if (
                isset($_FILES['gambar']) &&
                $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if (
                    $_FILES['gambar']['error']
                    !== UPLOAD_ERR_OK
                ) {

                    $error =
                        "Upload gambar gagal.";
                } else {

                    $maxSize =
                        2 * 1024 * 1024;

                    if (
                        $_FILES['gambar']['size']
                        > $maxSize
                    ) {

                        $error =
                            "Ukuran gambar maksimal 2 MB.";
                    } else {

                        $allowedMime = [
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ];

                        $finfo =
                            finfo_open(FILEINFO_MIME_TYPE);

                        $mime =
                            finfo_file(
                                $finfo,
                                $_FILES['gambar']['tmp_name']
                            );

                        finfo_close($finfo);

                        if (
                            !in_array(
                                $mime,
                                $allowedMime,
                                true
                            )
                        ) {

                            $error =
                                "Format gambar harus JPG, PNG, atau WEBP.";
                        } else {

                            $uploadDir =
                                "../upload/";

                            if (
                                !is_dir($uploadDir)
                            ) {

                                mkdir(
                                    $uploadDir,
                                    0755,
                                    true
                                );
                            }

                            $extension =
                                strtolower(
                                    pathinfo(
                                        $_FILES['gambar']['name'],
                                        PATHINFO_EXTENSION
                                    )
                                );

                            $namaFile =
                                'produk_' .
                                time() .
                                '_' .
                                bin2hex(
                                    random_bytes(4)
                                ) .
                                '.' .
                                $extension;

                            $targetFile =
                                $uploadDir .
                                $namaFile;

                            if (
                                move_uploaded_file(
                                    $_FILES['gambar']['tmp_name'],
                                    $targetFile
                                )
                            ) {

                                $gambar =
                                    $namaFile;
                            } else {

                                $error =
                                    "Gambar gagal disimpan.";
                            }
                        }
                    }
                }
            }


            /* =====================================================
            VALIDASI
            ===================================================== */

            if (
                $error === '' &&
                $kategori_id <= 0
            ) {

                $error =
                    "Kategori produk wajib dipilih.";
            } elseif (
                $error === '' &&
                $sku === ''
            ) {

                $error =
                    "SKU produk wajib diisi.";
            } elseif (
                $error === '' &&
                $nama_produk === ''
            ) {

                $error =
                    "Nama produk wajib diisi.";
            } elseif (
                $error === '' &&
                $harga <= 0
            ) {

                $error =
                    "Harga jual harus lebih dari 0.";
            } elseif (
                $error === '' &&
                $harga_beli < 0
            ) {

                $error =
                    "Harga beli tidak boleh kurang dari 0.";
            } elseif (
                $error === '' &&
                $stok < 0
            ) {

                $error =
                    "Stok tidak boleh kurang dari 0.";
            } elseif (
                $error === '' &&
                $stok_minimum < 0
            ) {

                $error =
                    "Stok minimum tidak boleh kurang dari 0.";
            } elseif (
                $error === '' &&
                !in_array(
                    $status,
                    ['aktif', 'nonaktif'],
                    true
                )
            ) {

                $error =
                    "Status produk tidak valid.";
            }


            /* =====================================================
            CEK KATEGORI
            ===================================================== */

            if ($error === '') {

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

                    if (
                        $hasilKategori->num_rows === 0
                    ) {

                        $error =
                            "Kategori yang dipilih tidak ditemukan atau tidak aktif.";
                    }

                    $cekKategori->close();
                }
            }


            /* =====================================================
            CEK SKU
            ===================================================== */

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

                    if (
                        $hasilSKU->num_rows > 0
                    ) {

                        $error =
                            "SKU tersebut sudah digunakan. Silakan gunakan SKU lain.";
                    }

                    $cekSKU->close();
                }
            }


            /* =====================================================
            SIMPAN PRODUK
            ===================================================== */

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
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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

                        $produkId =
                            $stmt->insert_id;

                        $stmt->close();


                        /*
                        * Sinkronisasi stok awal
                        * ke inventory jika tabel
                        * inventory memiliki gudang.
                        *
                        * Produk tetap menyimpan
                        * nilai stok sebagai total.
                        */

                        if ($stok > 0) {

                            $cekGudang =
                                $conn->query("
                                    SELECT id
                                    FROM gudang
                                    WHERE status = 'aktif'
                                    ORDER BY id ASC
                                    LIMIT 1
                                ");

                            if (
                                $cekGudang &&
                                $cekGudang->num_rows > 0
                            ) {

                                $gudang =
                                    $cekGudang->fetch_assoc();

                                $gudangId =
                                    (int)$gudang['id'];

                                $cekInventory =
                                    $conn->prepare("
                                        SELECT id
                                        FROM inventory
                                        WHERE produk_id = ?
                                        AND gudang_id = ?
                                        LIMIT 1
                                    ");

                                if ($cekInventory) {

                                    $cekInventory->bind_param(
                                        "ii",
                                        $produkId,
                                        $gudangId
                                    );

                                    $cekInventory->execute();

                                    $hasilInventory =
                                        $cekInventory->get_result();

                                    if (
                                        $hasilInventory->num_rows > 0
                                    ) {

                                        $inventoryRow =
                                            $hasilInventory->fetch_assoc();

                                        $inventoryId =
                                            (int)$inventoryRow['id'];

                                        $updateInventory =
                                            $conn->prepare("
                                                UPDATE inventory
                                                SET
                                                    stok = ?,
                                                    stok_minimum = ?,
                                                    updated_at = NOW()
                                                WHERE id = ?
                                            ");

                                        if ($updateInventory) {

                                            $updateInventory->bind_param(
                                                "iii",
                                                $stok,
                                                $stok_minimum,
                                                $inventoryId
                                            );

                                            $updateInventory->execute();

                                            $updateInventory->close();
                                        }
                                    } else {

                                        $insertInventory =
                                            $conn->prepare("
                                                INSERT INTO inventory
                                                (
                                                    produk_id,
                                                    gudang_id,
                                                    stok,
                                                    stok_minimum,
                                                    updated_at
                                                )
                                                VALUES
                                                (?, ?, ?, ?, NOW())
                                            ");

                                        if ($insertInventory) {

                                            $insertInventory->bind_param(
                                                "iiii",
                                                $produkId,
                                                $gudangId,
                                                $stok,
                                                $stok_minimum
                                            );

                                            $insertInventory->execute();

                                            $insertInventory->close();
                                        }
                                    }

                                    $cekInventory->close();
                                }
                            }
                        }


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
                    }
                }
            }
        }
    }


    /* =========================================================
    FLASH MESSAGE
    ========================================================= */

    if (isset($_GET['success'])) {

        $success =
            trim($_GET['success']);
    }

    if (isset($_GET['error'])) {

        $error =
            trim($_GET['error']);
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

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $categories[] =
                $row;
        }
    }


    /* =========================================================
    DATA PRODUK
    ========================================================= */

    $products = [];

    /*
    * STOK MENGIKUTI TOTAL INVENTORY
    *
    * Jika produk mempunyai:
    * Gudang A = 20
    * Gudang B = 15
    *
    * Maka stok yang ditampilkan = 35.
    *
    * COALESCE(..., p.stok, 0)
    * digunakan sebagai fallback.
    */

    $result =
        $conn->query("
            SELECT
                p.id,
                p.kategori_id,
                p.sku,
                p.nama_produk,
                p.deskripsi,
                p.harga,
                p.harga_beli,

                COALESCE(
                    (
                        SELECT SUM(i.stok)
                        FROM inventory i
                        WHERE i.produk_id = p.id
                    ),
                    p.stok,
                    0
                ) AS stok,

                p.stok_minimum,
                p.satuan,
                p.gambar,
                p.status,
                p.created_at,

                k.nama_kategori

            FROM produk p

            LEFT JOIN kategori k
                ON p.kategori_id = k.id

            ORDER BY p.id DESC
        ");

    if ($result) {

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $products[] =
                $row;
        }
    }


    /* =========================================================
    STATISTIK PRODUK
    ========================================================= */

    $totalProduk = 0;

    $produkAktif = 0;

    $produkHabis = 0;

    $stokMenipis = 0;


    $result =
        $conn->query("
            SELECT
                COUNT(*) AS total,

                COALESCE(
                    SUM(status = 'aktif'),
                    0
                ) AS aktif,

                COALESCE(
                    SUM(
                        COALESCE(
                            (
                                SELECT SUM(i.stok)
                                FROM inventory i
                                WHERE i.produk_id = produk.id
                            ),
                            produk.stok,
                            0
                        ) <= 0
                    ),
                    0
                ) AS habis,

                COALESCE(
                    SUM(
                        COALESCE(
                            (
                                SELECT SUM(i.stok)
                                FROM inventory i
                                WHERE i.produk_id = produk.id
                            ),
                            produk.stok,
                            0
                        ) > 0

                        AND

                        COALESCE(
                            (
                                SELECT SUM(i.stok)
                                FROM inventory i
                                WHERE i.produk_id = produk.id
                            ),
                            produk.stok,
                            0
                        ) <= produk.stok_minimum
                    ),
                    0
                ) AS menipis

            FROM produk
        ");

    if ($result) {

        $row =
            $result->fetch_assoc();

        $totalProduk =
            (int)($row['total'] ?? 0);

        $produkAktif =
            (int)($row['aktif'] ?? 0);

        $produkHabis =
            (int)($row['habis'] ?? 0);

        $stokMenipis =
            (int)($row['menipis'] ?? 0);
    }


    /* =========================================================
    TOTAL NILAI STOK
    ========================================================= */

    $totalNilaiStok = 0;

    $result =
        $conn->query("
            SELECT
                COALESCE(
                    SUM(
                        COALESCE(
                            (
                                SELECT SUM(i.stok)
                                FROM inventory i
                                WHERE i.produk_id = p.id
                            ),
                            p.stok,
                            0
                        )
                        * p.harga_beli
                    ),
                    0
                ) AS total

            FROM produk p

            WHERE p.status = 'aktif'
        ");

    if ($result) {

        $totalNilaiStok =
            (float)$result
                ->fetch_assoc()['total'];
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
            Produk - Toku Coffee ERP
        </title>

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

        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


        <style>
            /* =========================================================
    TEMA TETAP
    ========================================================= */

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

                --bg: #faf9f5;

                --white: #fff;

                --green: #527853;

                --orange: #c68b3c;

                --red: #a94442;

                --blue: #557a95;

                --gray: #888;
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


            /* =========================================================
    SIDEBAR
    ========================================================= */

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

            .logo i {
                margin-right: .5rem;
            }

            .menu-title {

                font-size: 1.1rem;

                color: #aaa;

                padding: 0 1.5rem;

                margin-bottom: 1rem;

                text-transform: uppercase;
            }

            .sidebar a {

                display: flex;

                align-items: center;

                gap: 1.3rem;

                padding: 1.3rem 1.5rem;

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


            /* =========================================================
    MAIN
    ========================================================= */

            .main {

                margin-left: 26rem;

                min-height: 100vh;
            }

            .topbar {

                height: 8rem;

                background: #fff;

                display: flex;

                align-items: center;

                justify-content: space-between;

                padding: 0 4rem;

                box-shadow:
                    0 .3rem 1rem rgba(0, 0, 0, .05);

                position: sticky;

                top: 0;

                z-index: 900;
            }

            .topbar-left {

                display: flex;

                align-items: center;

                gap: 1.5rem;
            }

            .page-title h2 {

                font-size: 2.4rem;
            }

            .page-title p {

                font-size: 1.3rem;

                color: #999;
            }

            #menu-btn {

                display: none;

                font-size: 2.5rem;

                cursor: pointer;
            }

            .profile {

                display: flex;

                align-items: center;

                gap: 1rem;
            }

            .profile img {

                width: 4rem;

                height: 4rem;

                border-radius: 50%;

                border:
                    .2rem solid var(--main-color);
            }

            .profile h4 {

                font-size: 1.4rem;
            }

            .profile p {

                font-size: 1.1rem;

                color: #999;
            }


            /* =========================================================
    CONTENT
    ========================================================= */

            .content {

                padding: 3rem 4rem;

                animation:
                    fadeUp .5s ease;
            }

            .page-actions {

                display: flex;

                justify-content: flex-end;

                margin-bottom: 2rem;

                gap: 1rem;
            }

            .btn {

                display: inline-flex;

                align-items: center;

                justify-content: center;

                gap: .7rem;

                padding: 1rem 1.7rem;

                border:
                    var(--border);

                border-radius:
                    var(--border-radius);

                color:
                    var(--main-color);

                background: none;

                cursor: pointer;

                font-size: 1.3rem;
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


            /* =========================================================
    STATS
    ========================================================= */

            .stats {

                display: grid;

                grid-template-columns:
                    repeat(4, 1fr);

                gap: 1.8rem;

                margin-bottom: 3rem;
            }

            .stat {

                background: #fff;

                padding: 2rem;

                border:
                    var(--border);

                border-radius:
                    var(--border-radius);

                display: flex;

                align-items: center;

                gap: 1.5rem;
            }

            .stat:hover {

                border:
                    var(--border-hover);

                border-radius:
                    var(--border-radius-hover);

                transform:
                    translateY(-.5rem);

                box-shadow:
                    0 1rem 2rem rgba(68, 68, 51, .08);
            }

            .stat-icon {

                width: 5rem;
                height: 5rem;

                flex: 0 0 5rem;

                display: flex;

                align-items: center;
                justify-content: center;

                border:
                    .15rem solid var(--main-color);

                border-radius:
                    45% 55% 60% 40%;

                font-size: 2rem;
            }

            .stat h3 {
                font-size: 2rem;
            }

            .stat p {

                font-size: 1.2rem;

                color: #888;
            }

            .stat small {

                display: block;

                color: var(--green);

                font-size: 1rem;

                margin-top: .4rem;
            }


            /* =========================================================
    PANEL
    ========================================================= */

            .panel {

                background: #fff;

                border:
                    var(--border);

                border-radius:
                    var(--border-radius);

                padding: 2.5rem;

                margin-bottom: 2rem;
            }

            .panel-header {

                display: flex;

                align-items: center;

                justify-content: space-between;

                gap: 1rem;

                margin-bottom: 2rem;
            }

            .panel-header h2 {

                font-size: 1.9rem;
            }

            .panel-header p {

                font-size: 1.1rem;

                color: #999;

                margin-top: .3rem;
            }


            /* =========================================================
    FILTER
    ========================================================= */

            .filter-box {

                display: grid;

                grid-template-columns:
                    2fr 1fr 1fr 1fr;

                gap: 1rem;

                margin-bottom: 2rem;
            }

            .input-box {

                position: relative;
            }

            .input-box i {

                position: absolute;

                left: 1.3rem;

                top: 50%;

                transform:
                    translateY(-50%);

                color: #999;

                font-size: 1.3rem;
            }

            .input-box input,
            .input-box select {

                width: 100%;

                padding:
                    1.2rem 1.2rem 1.2rem 3.8rem;

                border:
                    .1rem solid #ddd;

                border-radius:
                    var(--border-radius);

                background: #fff;

                color:
                    var(--main-color);

                font-size: 1.2rem;
            }

            .input-box select {

                padding-left: 1.2rem;

                cursor: pointer;
            }


            /* =========================================================
    PRODUCT GRID
    ========================================================= */

            .product-grid {

                display: grid;

                grid-template-columns:
                    repeat(4, 1fr);

                gap: 1.8rem;
            }

            .product-card {

                background: #fff;

                border:
                    .15rem solid #eee;

                border-radius:
                    var(--border-radius);

                padding: 1.5rem;

                position: relative;

                overflow: hidden;
            }

            .product-card:hover {

                border:
                    var(--border-hover);

                border-radius:
                    var(--border-radius-hover);

                transform:
                    translateY(-.5rem);

                box-shadow:
                    0 1rem 2rem rgba(68, 68, 51, .08);
            }

            .product-image {

                width: 100%;

                height: 18rem;

                background: #f4f1e8;

                border-radius:
                    var(--border-radius);

                display: flex;

                align-items: center;

                justify-content: center;

                overflow: hidden;

                margin-bottom: 1.5rem;

                border:
                    .1rem solid #eee;
            }

            .product-image img {

                width: 100%;
                height: 100%;

                object-fit: cover;
            }

            .no-image {

                font-size: 4rem;

                color: #aaa;
            }

            .product-category {

                display: inline-flex;

                padding:
                    .4rem .9rem;

                background: #f4f1e8;

                border:
                    .1rem solid #ddd;

                border-radius:
                    var(--border-radius);

                font-size: 1rem;

                margin-bottom: .8rem;
            }

            .product-card h3 {

                font-size: 1.5rem;

                margin-bottom: .5rem;

                white-space: nowrap;

                overflow: hidden;

                text-overflow: ellipsis;
            }

            .product-sku {

                font-size: 1rem;

                color: #999;

                margin-bottom: 1rem;
            }

            .product-price {

                font-size: 1.7rem;

                font-weight: 600;

                margin-bottom: 1rem;
            }

            .product-stock {

                display: flex;

                justify-content: space-between;

                align-items: center;

                gap: 1rem;

                margin-bottom: 1.3rem;
            }

            .stock-text {

                font-size: 1.1rem;

                color: #777;
            }

            .stock-status {

                display: inline-flex;

                align-items: center;

                padding: .4rem .8rem;

                border:
                    .1rem solid currentColor;

                border-radius:
                    var(--border-radius);

                font-size: .9rem;
            }

            .stock-status.aman {

                color: var(--green);

                background: #f0f7f0;
            }

            .stock-status.menipis {

                color: var(--orange);

                background: #fff7e9;
            }

            .stock-status.habis {

                color: var(--red);

                background: #fff0ef;
            }

            .product-actions {

                display: grid;

                grid-template-columns:
                    repeat(3, 1fr);

                gap: .6rem;
            }

            .product-actions a {

                display: flex;

                align-items: center;

                justify-content: center;

                gap: .4rem;

                padding: .8rem .5rem;

                border:
                    .1rem solid #ddd;

                border-radius:
                    var(--border-radius);

                color:
                    var(--main-color);

                font-size: 1rem;
            }

            .product-actions a:hover {

                border:
                    .1rem dashed var(--main-color);

                background: #f7f4ec;
            }

            .product-actions .delete {

                color: var(--red);
            }


            /* =========================================================
    STATUS
    ========================================================= */

            .product-status {

                position: absolute;

                top: 2rem;
                right: 2rem;

                z-index: 5;

                padding: .5rem .9rem;

                border:
                    .1rem solid currentColor;

                border-radius:
                    var(--border-radius);

                font-size: .9rem;

                background: #fff;
            }

            .product-status.aktif {

                color: var(--green);
            }

            .product-status.nonaktif {

                color: var(--gray);
            }


            /* =========================================================
    PAGINATION
    ========================================================= */

            .pagination {

                display: flex;

                align-items: center;

                justify-content: center;

                gap: .6rem;

                margin-top: 2.5rem;
            }

            .pagination button {

                width: 3.8rem;
                height: 3.8rem;

                border:
                    .1rem solid #ddd;

                background: #fff;

                color:
                    var(--main-color);

                border-radius:
                    var(--border-radius);

                cursor: pointer;
            }

            .pagination button:hover,
            .pagination button.active {

                background: #f3f0e8;

                border:
                    var(--border);
            }


            /* =========================================================
    EMPTY & ALERT
    ========================================================= */

            .empty {

                text-align: center;

                padding: 5rem 2rem;

                color: #999;

                font-size: 1.2rem;

                grid-column: 1 / -1;
            }

            .empty i {

                display: block;

                font-size: 4rem;

                margin-bottom: 1rem;
            }

            .alert {

                padding: 1.3rem 1.5rem;

                border:
                    .1rem solid currentColor;

                border-radius:
                    var(--border-radius);

                margin-bottom: 2rem;

                font-size: 1.2rem;

                display: flex;

                align-items: center;

                gap: 1rem;
            }

            .alert.success {

                color: var(--green);

                background: #f0f7f0;
            }

            .alert.error {

                color: var(--red);

                background: #fff0ef;
            }


            /* =========================================================
    MODAL
    ========================================================= */

            .modal {

                position: fixed;

                inset: 0;

                background:
                    rgba(68, 68, 51, .45);

                display: none;

                align-items: center;

                justify-content: center;

                padding: 2rem;

                z-index: 2000;
            }

            .modal.show {

                display: flex;
            }

            .modal-content {

                width: 100%;

                max-width: 70rem;

                max-height: 90vh;

                overflow-y: auto;

                background: #fff;

                border:
                    var(--border);

                border-radius:
                    var(--border-radius);

                padding: 2.5rem;
            }

            .modal-header {

                display: flex;

                justify-content: space-between;

                align-items: center;

                margin-bottom: 2rem;
            }

            .modal-header h2 {

                font-size: 2rem;
            }

            .close-modal {

                width: 3.8rem;

                height: 3.8rem;

                display: flex;

                align-items: center;

                justify-content: center;

                border:
                    .1rem solid #ddd;

                border-radius:
                    var(--border-radius);

                background: #fff;

                cursor: pointer;
            }

            .form-grid {

                display: grid;

                grid-template-columns:
                    1fr 1fr;

                gap: 1.5rem;
            }

            .form-group {

                display: flex;

                flex-direction: column;

                gap: .6rem;
            }

            .form-group.full {

                grid-column: 1 / -1;
            }

            .form-group label {

                font-size: 1.2rem;

                font-weight: 500;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {

                width: 100%;

                padding: 1.1rem 1.2rem;

                border:
                    .1rem solid #ddd;

                border-radius:
                    var(--border-radius);

                font-size: 1.2rem;

                color:
                    var(--main-color);

                background: #fff;
            }

            .form-group textarea {

                min-height: 10rem;

                resize: vertical;
            }

            .form-group small {

                font-size: 1rem;

                color: #999;
            }

            .form-actions {

                display: flex;

                justify-content: flex-end;

                gap: 1rem;

                margin-top: 2rem;
            }


            /* =========================================================
    TOAST
    ========================================================= */

            .erp-toast {

                position: fixed;

                top: 2.5rem;
                right: 3rem;

                width: 390px;

                min-height: 90px;

                background: #fff;

                border:
                    .2rem solid #443;

                border-radius:
                    95% 4% 97% 5% / 4% 94% 3% 95%;

                display: flex;

                align-items: center;

                gap: 1.4rem;

                padding: 1.5rem 1.7rem;

                box-shadow:
                    0 1rem 3rem rgba(68, 51, 51, .15);

                z-index: 99999;

                animation:
                    toastIn .45s ease forwards;
            }

            .toast-icon {

                width: 4.5rem;
                height: 4.5rem;

                flex-shrink: 0;

                display: flex;

                align-items: center;
                justify-content: center;

                background: #527853;

                color: #fff;

                border-radius: 50%;

                font-size: 1.8rem;
            }

            .toast-content {

                flex: 1;

                display: flex;

                flex-direction: column;

                gap: .35rem;
            }

            .toast-content strong {

                font-size: 1.4rem;

                color: #443;
            }

            .toast-content span {

                font-size: 1.1rem;

                color: #888;

                line-height: 1.5;
            }

            .toast-close {

                background: transparent;

                color: #888;

                font-size: 1.5rem;

                cursor: pointer;
            }

            .toast-progress {

                position: absolute;

                left: 0;
                bottom: 0;

                height: .35rem;

                width: 100%;

                background: #527853;

                animation:
                    toastProgress 4s linear forwards;
            }


            /* =========================================================
    ANIMATION
    ========================================================= */

            @keyframes fadeUp {

                from {

                    opacity: 0;

                    transform:
                        translateY(1.5rem);
                }

                to {

                    opacity: 1;

                    transform:
                        translateY(0);
                }
            }

            @keyframes toastIn {

                from {

                    opacity: 0;

                    transform:
                        translateX(120%);
                }

                to {

                    opacity: 1;

                    transform:
                        translateX(0);
                }
            }

            @keyframes toastOut {

                from {

                    opacity: 1;

                    transform:
                        translateX(0);
                }

                to {

                    opacity: 0;

                    transform:
                        translateX(120%);
                }
            }

            @keyframes toastProgress {

                from {
                    width: 100%;
                }

                to {
                    width: 0;
                }
            }


            /* =========================================================
    RESPONSIVE
    ========================================================= */

            @media (max-width:1200px) {

                .stats {

                    grid-template-columns:
                        repeat(2, 1fr);
                }

                .product-grid {

                    grid-template-columns:
                        repeat(3, 1fr);
                }

                .filter-box {

                    grid-template-columns:
                        1fr 1fr;
                }
            }

            @media (max-width:900px) {

                .sidebar {

                    left: -27rem;
                }

                .sidebar.active {

                    left: 0;
                }

                .main {

                    margin-left: 0;
                }

                #menu-btn {

                    display: block;
                }

                .content {

                    padding: 2rem;
                }

                .topbar {

                    padding: 0 2rem;
                }

                .profile-info {

                    display: none;
                }

                .product-grid {

                    grid-template-columns:
                        repeat(2, 1fr);
                }

                .form-grid {

                    grid-template-columns: 1fr;
                }

                .form-group.full {

                    grid-column: auto;
                }
            }

            @media (max-width:550px) {

                html {

                    font-size: 50%;
                }

                .stats {

                    grid-template-columns: 1fr;
                }

                .product-grid {

                    grid-template-columns: 1fr;
                }

                .filter-box {

                    grid-template-columns: 1fr;
                }

                .panel {

                    padding: 1.5rem;
                }

                .content {

                    padding: 1.5rem;
                }

                .page-actions {

                    flex-direction: column;
                }

                .page-actions .btn {

                    width: 100%;
                }

                .form-actions {

                    flex-direction: column;
                }

                .form-actions .btn {

                    width: 100%;
                }

                .erp-toast {

                    top: 2rem;

                    left: 2rem;
                    right: 2rem;

                    width: auto;
                }
            }

            @media print {

                .sidebar,
                .topbar,
                .page-actions,
                .filter-box,
                .product-actions,
                .pagination {

                    display: none !important;
                }

                .main {

                    margin-left: 0;
                }

                .content {

                    padding: 0;
                }
            }
        </style>

    </head>


    <body>


        <?php if (isset($_GET['success'])): ?>

            <div
                class="erp-toast success-toast"
                id="successToast">

                <div class="toast-icon">
                    <i class="fas fa-check"></i>
                </div>

                <div class="toast-content">

                    <strong>
                        Produk Berhasil Ditambahkan
                    </strong>

                    <span>
                        <?= e($_GET['success']) ?>
                    </span>

                </div>

                <button
                    type="button"
                    onclick="closeToast()"
                    class="toast-close">

                    <i class="fas fa-times"></i>

                </button>

                <div class="toast-progress"></div>

            </div>

        <?php endif; ?>


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


            <header class="topbar">

                <div class="topbar-left">

                    <i
                        class="fas fa-bars"
                        id="menu-btn">
                    </i>

                    <div class="page-title">

                        <h2>
                            Produk
                        </h2>

                        <p>
                            Manajemen produk Toku Coffee ERP
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



            <section class="content">


                <div class="page-actions">

                    <a
                        href="products_tambah.php"
                        class="btn">

                        <i class="fas fa-plus"></i>

                        Tambah Produk

                    </a>


                    <button
                        type="button"
                        class="btn"
                        onclick="window.print()">

                        <i class="fas fa-print"></i>

                        Cetak

                    </button>

                </div>



                <?php if ($success): ?>

                    <div class="alert success">

                        <i class="fas fa-circle-check"></i>

                        <?= e($success) ?>

                    </div>

                <?php endif; ?>


                <?php if ($error): ?>

                    <div class="alert error">

                        <i class="fas fa-circle-exclamation"></i>

                        <?= e($error) ?>

                    </div>

                <?php endif; ?>



                <!-- =====================================================
        STATISTICS
    ===================================================== -->

                <div class="stats">


                    <div class="stat">

                        <div class="stat-icon">

                            <i class="fas fa-box-open"></i>

                        </div>

                        <div>

                            <h3>
                                <?= number_format($totalProduk) ?>
                            </h3>

                            <p>
                                Total Produk
                            </p>

                            <small>

                                <i class="fas fa-database"></i>

                                Semua produk

                            </small>

                        </div>

                    </div>



                    <div class="stat">

                        <div class="stat-icon">

                            <i class="fas fa-circle-check"></i>

                        </div>

                        <div>

                            <h3>
                                <?= number_format($produkAktif) ?>
                            </h3>

                            <p>
                                Produk Aktif
                            </p>

                            <small>

                                <i class="fas fa-check"></i>

                                Siap dijual

                            </small>

                        </div>

                    </div>



                    <div class="stat">

                        <div class="stat-icon">

                            <i class="fas fa-triangle-exclamation"></i>

                        </div>

                        <div>

                            <h3>
                                <?= number_format($stokMenipis) ?>
                            </h3>

                            <p>
                                Stok Menipis
                            </p>

                            <small>

                                <i class="fas fa-arrow-down"></i>

                                Perlu restock

                            </small>

                        </div>

                    </div>



                    <div class="stat">

                        <div class="stat-icon">

                            <i class="fas fa-box"></i>

                        </div>

                        <div>

                            <h3>
                                <?= number_format($produkHabis) ?>
                            </h3>

                            <p>
                                Produk Habis
                            </p>

                            <small>

                                <i class="fas fa-circle-xmark"></i>

                                Perlu pengadaan

                            </small>

                        </div>

                    </div>


                </div>



                <!-- =====================================================
        PRODUCT PANEL
    ===================================================== -->

                <section class="panel">


                    <div class="panel-header">

                        <div>

                            <h2>
                                Daftar Produk
                            </h2>

                            <p>
                                Kelola katalog dan informasi produk Toku Coffee
                            </p>

                        </div>


                        <div>

                            <strong style="font-size:1.3rem;">

                                Nilai Stok:

                                <?= rupiah($totalNilaiStok) ?>

                            </strong>

                        </div>

                    </div>



                    <!-- FILTER -->

                    <div class="filter-box">


                        <div class="input-box">

                            <i class="fas fa-search"></i>

                            <input
                                type="text"
                                id="searchProduct"
                                placeholder="Cari nama produk atau SKU...">

                        </div>


                        <div class="input-box">

                            <select id="categoryFilter">

                                <option value="">
                                    Semua Kategori
                                </option>

                                <?php foreach ($categories as $category): ?>

                                    <option
                                        value="<?= e($category['nama_kategori']) ?>">

                                        <?= e($category['nama_kategori']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="input-box">

                            <select id="stockFilter">

                                <option value="">
                                    Semua Stok
                                </option>

                                <option value="aman">
                                    Stok Aman
                                </option>

                                <option value="menipis">
                                    Stok Menipis
                                </option>

                                <option value="habis">
                                    Stok Habis
                                </option>

                            </select>

                        </div>


                        <div class="input-box">

                            <select id="sortProduct">

                                <option value="newest">
                                    Terbaru
                                </option>

                                <option value="oldest">
                                    Terlama
                                </option>

                                <option value="name">
                                    Nama A-Z
                                </option>

                                <option value="price-low">
                                    Harga Terendah
                                </option>

                                <option value="price-high">
                                    Harga Tertinggi
                                </option>

                                <option value="stock-low">
                                    Stok Terendah
                                </option>

                                <option value="stock-high">
                                    Stok Tertinggi
                                </option>

                            </select>

                        </div>


                    </div>



                    <!-- PRODUCT GRID -->

                    <div
                        class="product-grid"
                        id="productGrid">


                        <?php if (!empty($products)): ?>


                            <?php foreach ($products as $index => $product): ?>


                                <?php

                                $stok =
                                    (int)$product['stok'];

                                $stokMinimum =
                                    (int)$product['stok_minimum'];


                                if ($stok <= 0) {

                                    $stockClass =
                                        'habis';

                                    $stockLabel =
                                        'Habis';
                                } elseif (
                                    $stok <= $stokMinimum
                                ) {

                                    $stockClass =
                                        'menipis';

                                    $stockLabel =
                                        'Menipis';
                                } else {

                                    $stockClass =
                                        'aman';

                                    $stockLabel =
                                        'Aman';
                                }


                                $productStatus =
                                    strtolower(
                                        $product['status']
                                    );

                                ?>


                                <article
                                    class="product-card"

                                    data-name="<?= e(strtolower($product['nama_produk'])) ?>"

                                    data-sku="<?= e(strtolower($product['sku'])) ?>"

                                    data-category="<?= e($product['nama_kategori'] ?? '') ?>"

                                    data-stock="<?= $stockClass ?>"

                                    data-price="<?= (float)$product['harga'] ?>"

                                    data-stock-number="<?= $stok ?>"

                                    data-id="<?= (int)$product['id'] ?>"

                                    style="animation-delay:<?= min($index * .04, .5) ?>s;">


                                    <span
                                        class="product-status <?= e(statusClass($productStatus)) ?>">

                                        <?= e(ucfirst($product['status'])) ?>

                                    </span>



                                    <div class="product-image">

                                        <?php if (!empty($product['gambar'])): ?>

                                            <?php
                                            $namaGambar = basename((string)$product['gambar']);
                                            $gambarPath = "../upload/" . rawurlencode($namaGambar);
                                            ?>

                                            <img
                                                src="<?= e($gambarPath) ?>"
                                                alt="<?= e($product['nama_produk']) ?>"
                                                onerror="
                                                this.style.display='none';
                                                this.nextElementSibling.style.display='flex';
                                            ">

                                            <div
                                                class="no-image"
                                                style="display:none;">
                                                <i class="fas fa-mug-hot"></i>
                                            </div>

                                        <?php else: ?>

                                            <div class="no-image">
                                                <i class="fas fa-mug-hot"></i>
                                            </div>

                                        <?php endif; ?>

                                    </div>

                                    <?php if (!empty($product['nama_kategori'])): ?>

                                        <span class="product-category">

                                            <?= e($product['nama_kategori']) ?>

                                        </span>

                                    <?php endif; ?>



                                    <h3
                                        title="<?= e($product['nama_produk']) ?>">

                                        <?= e($product['nama_produk']) ?>

                                    </h3>



                                    <div class="product-sku">

                                        SKU:

                                        <?= e($product['sku']) ?>

                                    </div>



                                    <div class="product-price">

                                        <?= rupiah($product['harga']) ?>

                                    </div>



                                    <div class="product-stock">

                                        <span class="stock-text">

                                            Stok:

                                            <strong>
                                                <?= number_format($stok) ?>
                                            </strong>

                                            <?= e($product['satuan']) ?>

                                        </span>


                                        <span
                                            class="stock-status <?= $stockClass ?>">

                                            <?= $stockLabel ?>

                                        </span>

                                    </div>



                                    <div class="product-actions">

                                        <a
                                            href="products_detail.php?id=<?= (int)$product['id'] ?>">

                                            <i class="fas fa-eye"></i>

                                            Detail

                                        </a>


                                        <a
                                            href="products_edit.php?id=<?= (int)$product['id'] ?>">

                                            <i class="fas fa-pen"></i>

                                            Edit

                                        </a>


                                        <a
                                            href="products_hapus.php?id=<?= (int)$product['id'] ?>"
                                            class="delete"

                                            onclick="
            return confirm(
                'Apakah kamu yakin ingin menghapus produk ini?'
            );
        ">

                                            <i class="fas fa-trash"></i>

                                            Hapus

                                        </a>


                                    </div>


                                </article>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <div class="empty">

                                <i class="fas fa-box-open"></i>

                                Belum ada produk.

                            </div>


                        <?php endif; ?>


                    </div>



                    <div
                        class="pagination"
                        id="pagination">
                    </div>


                </section>


            </section>


        </main>



        <!-- =====================================================
        MODAL TAMBAH PRODUK
    ===================================================== -->

        <div
            class="modal"
            id="productModal">


            <div class="modal-content">


                <div class="modal-header">

                    <h2>
                        Tambah Produk
                    </h2>

                    <button
                        type="button"
                        class="close-modal"
                        onclick="closeProductModal()">

                        <i class="fas fa-xmark"></i>

                    </button>

                </div>



                <form
                    method="POST"
                    action="products.php"
                    enctype="multipart/form-data">


                    <input
                        type="hidden"
                        name="action"
                        value="tambah_produk">


                    <div class="form-grid">


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
                                        value="<?= (int)$category['id'] ?>">

                                        <?= e($category['nama_kategori']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>



                        <div class="form-group">

                            <label>
                                SKU *
                            </label>

                            <input
                                type="text"
                                name="sku"
                                placeholder="Contoh: SKU-011"
                                required>

                        </div>



                        <div class="form-group full">

                            <label>
                                Nama Produk *
                            </label>

                            <input
                                type="text"
                                name="nama_produk"
                                placeholder="Masukkan nama produk"
                                required>

                        </div>



                        <div class="form-group full">

                            <label>
                                Deskripsi
                            </label>

                            <textarea
                                name="deskripsi"
                                placeholder="Deskripsi produk..."></textarea>

                        </div>



                        <div class="form-group">

                            <label>
                                Harga Jual *
                            </label>

                            <input
                                type="number"
                                name="harga"
                                min="1"
                                step="1"
                                placeholder="85000"
                                required>

                        </div>



                        <div class="form-group">

                            <label>
                                Harga Beli
                            </label>

                            <input
                                type="number"
                                name="harga_beli"
                                min="0"
                                step="1"
                                value="0">

                        </div>



                        <div class="form-group">

                            <label>
                                Stok Awal
                            </label>

                            <input
                                type="number"
                                name="stok"
                                min="0"
                                value="0">

                        </div>



                        <div class="form-group">

                            <label>
                                Stok Minimum
                            </label>

                            <input
                                type="number"
                                name="stok_minimum"
                                min="0"
                                value="10">

                        </div>



                        <div class="form-group">

                            <label>
                                Satuan
                            </label>

                            <select name="satuan">

                                <option value="Pcs">
                                    Pcs
                                </option>

                                <option value="Pack">
                                    Pack
                                </option>

                                <option value="Unit">
                                    Unit
                                </option>

                                <option value="Kg">
                                    Kg
                                </option>

                                <option value="Gram">
                                    Gram
                                </option>

                                <option value="Liter">
                                    Liter
                                </option>

                            </select>

                        </div>



                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select name="status">

                                <option value="aktif">
                                    Aktif
                                </option>

                                <option value="nonaktif">
                                    Nonaktif
                                </option>

                            </select>

                        </div>



                        <div class="form-group">

                            <label>
                                Foto Produk
                            </label>

                            <input
                                type="file"
                                name="gambar"
                                accept="image/jpeg,image/png,image/webp">

                            <small>
                                Format: JPG, PNG, WEBP. Maksimal 2 MB.
                            </small>

                        </div>


                    </div>



                    <div class="form-actions">


                        <button
                            type="button"
                            class="btn"
                            onclick="closeProductModal()">

                            <i class="fas fa-xmark"></i>

                            Batal

                        </button>


                        <button
                            type="submit"
                            class="btn">

                            <i class="fas fa-save"></i>

                            Simpan Produk

                        </button>


                    </div>


                </form>


            </div>

        </div>



        <script>
            /* =========================================================
    TOAST
    ========================================================= */

            function closeToast() {

                const toast =
                    document.getElementById(
                        'successToast'
                    );

                if (!toast) return;

                toast.style.animation =
                    'toastOut .35s ease forwards';

                setTimeout(
                    function() {
                        toast.remove();
                    },
                    350
                );
            }

            setTimeout(
                closeToast,
                4000
            );


            /* =========================================================
            MOBILE SIDEBAR
            ========================================================= */

            const menuBtn =
                document.getElementById(
                    'menu-btn'
                );

            const sidebar =
                document.getElementById(
                    'sidebar'
                );


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


            document
                .querySelectorAll('.sidebar a')
                .forEach(
                    function(link) {

                        link.addEventListener(
                            'click',
                            function() {

                                if (
                                    window.innerWidth <= 900
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


            document.addEventListener(
                'click',
                function(event) {

                    if (
                        window.innerWidth <= 900 &&
                        sidebar.classList.contains('active') &&
                        !sidebar.contains(event.target) &&
                        !menuBtn.contains(event.target)
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


            /* =========================================================
            FILTER
            ========================================================= */

            const searchInput =
                document.getElementById(
                    'searchProduct'
                );

            const categoryFilter =
                document.getElementById(
                    'categoryFilter'
                );

            const stockFilter =
                document.getElementById(
                    'stockFilter'
                );

            const sortProduct =
                document.getElementById(
                    'sortProduct'
                );

            const productGrid =
                document.getElementById(
                    'productGrid'
                );

            const pagination =
                document.getElementById(
                    'pagination'
                );


            let currentPage = 1;

            const itemsPerPage = 8;


            function getFilteredProducts() {

                const search =
                    searchInput.value
                    .toLowerCase()
                    .trim();

                const category =
                    categoryFilter.value
                    .toLowerCase();

                const stock =
                    stockFilter.value
                    .toLowerCase();


                const cards =
                    Array.from(
                        productGrid.querySelectorAll(
                            '.product-card'
                        )
                    );


                let filtered =
                    cards.filter(
                        function(card) {

                            const name =
                                card.dataset.name || '';

                            const sku =
                                card.dataset.sku || '';

                            const cardCategory =
                                (
                                    card.dataset.category || ''
                                ).toLowerCase();

                            const cardStock =
                                card.dataset.stock || '';


                            const matchSearch =
                                name.includes(search) ||
                                sku.includes(search);

                            const matchCategory = !category ||
                                cardCategory === category;

                            const matchStock = !stock ||
                                cardStock === stock;


                            return (
                                matchSearch &&
                                matchCategory &&
                                matchStock
                            );

                        }
                    );


                const sort =
                    sortProduct.value;


                filtered.sort(
                    function(a, b) {

                        const nameA =
                            a.dataset.name || '';

                        const nameB =
                            b.dataset.name || '';

                        const priceA =
                            parseFloat(
                                a.dataset.price || 0
                            );

                        const priceB =
                            parseFloat(
                                b.dataset.price || 0
                            );

                        const stockA =
                            parseInt(
                                a.dataset.stockNumber || 0
                            );

                        const stockB =
                            parseInt(
                                b.dataset.stockNumber || 0
                            );

                        const idA =
                            parseInt(
                                a.dataset.id || 0
                            );

                        const idB =
                            parseInt(
                                b.dataset.id || 0
                            );


                        if (sort === 'name') {

                            return nameA.localeCompare(
                                nameB
                            );

                        }

                        if (sort === 'price-low') {

                            return priceA - priceB;

                        }

                        if (sort === 'price-high') {

                            return priceB - priceA;

                        }

                        if (sort === 'stock-low') {

                            return stockA - stockB;

                        }

                        if (sort === 'stock-high') {

                            return stockB - stockA;

                        }

                        if (sort === 'oldest') {

                            return idA - idB;

                        }

                        return idB - idA;

                    }
                );


                return filtered;
            }


            function renderProducts() {

                const cards =
                    Array.from(
                        productGrid.querySelectorAll(
                            '.product-card'
                        )
                    );


                const filtered =
                    getFilteredProducts();


                const totalPages =
                    Math.ceil(
                        filtered.length /
                        itemsPerPage
                    );


                if (
                    currentPage > totalPages &&
                    totalPages > 0
                ) {

                    currentPage =
                        totalPages;
                }


                cards.forEach(
                    function(card) {

                        card.style.display =
                            'none';

                    }
                );


                const start =
                    (currentPage - 1) *
                    itemsPerPage;

                const end =
                    start +
                    itemsPerPage;


                filtered
                    .slice(start, end)
                    .forEach(
                        function(card) {

                            card.style.display =
                                'block';

                        }
                    );


                renderPagination(
                    totalPages
                );


                let emptyMessage =
                    document.getElementById(
                        'filterEmpty'
                    );


                if (filtered.length === 0) {

                    if (!emptyMessage) {

                        emptyMessage =
                            document.createElement(
                                'div'
                            );

                        emptyMessage.id =
                            'filterEmpty';

                        emptyMessage.className =
                            'empty';

                        emptyMessage.innerHTML = `
                    <i class="fas fa-search"></i>
                    Produk tidak ditemukan.
                `;

                        productGrid.appendChild(
                            emptyMessage
                        );
                    }

                } else {

                    if (emptyMessage) {

                        emptyMessage.remove();

                    }

                }

            }


            function renderPagination(
                totalPages
            ) {

                pagination.innerHTML = '';

                if (totalPages <= 1) {
                    return;
                }


                const previous =
                    document.createElement(
                        'button'
                    );

                previous.innerHTML =
                    '<i class="fas fa-chevron-left"></i>';

                previous.disabled =
                    currentPage === 1;

                previous.onclick =
                    function() {

                        if (currentPage > 1) {

                            currentPage--;

                            renderProducts();

                        }

                    };

                pagination.appendChild(
                    previous
                );


                for (
                    let i = 1; i <= totalPages; i++
                ) {

                    const button =
                        document.createElement(
                            'button'
                        );

                    button.textContent =
                        i;

                    if (
                        i === currentPage
                    ) {

                        button.classList.add(
                            'active'
                        );

                    }

                    button.onclick =
                        function() {

                            currentPage = i;

                            renderProducts();

                        };

                    pagination.appendChild(
                        button
                    );

                }


                const next =
                    document.createElement(
                        'button'
                    );

                next.innerHTML =
                    '<i class="fas fa-chevron-right"></i>';

                next.disabled =
                    currentPage === totalPages;

                next.onclick =
                    function() {

                        if (
                            currentPage <
                            totalPages
                        ) {

                            currentPage++;

                            renderProducts();

                        }

                    };

                pagination.appendChild(
                    next
                );

            }


            [
                searchInput,
                categoryFilter,
                stockFilter,
                sortProduct
            ].forEach(
                function(element) {

                    element.addEventListener(
                        'input',
                        function() {

                            currentPage = 1;

                            renderProducts();

                        }
                    );

                    element.addEventListener(
                        'change',
                        function() {

                            currentPage = 1;

                            renderProducts();

                        }
                    );

                }
            );


            /* =========================================================
            MODAL
            ========================================================= */

            const productModal =
                document.getElementById(
                    'productModal'
                );


            function openProductModal() {

                productModal.classList.add(
                    'show'
                );

                document.body.style.overflow =
                    'hidden';
            }


            function closeProductModal() {

                productModal.classList.remove(
                    'show'
                );

                document.body.style.overflow =
                    '';
            }


            productModal.addEventListener(
                'click',
                function(event) {

                    if (
                        event.target ===
                        productModal
                    ) {

                        closeProductModal();

                    }

                }
            );


            document.addEventListener(
                'keydown',
                function(event) {

                    if (
                        event.key === 'Escape'
                    ) {

                        closeProductModal();

                    }

                }
            );


            /* =========================================================
            INIT
            ========================================================= */

            document.addEventListener(
                'DOMContentLoaded',
                function() {

                    renderProducts();

                }
            );
        </script>


    </body>

    </html>
