<?php

require_once "config/koneksi.php";
require_once "config/session.php";

requireRole(['admin', 'staff']);

/* =========================================================
   FUNGSI BANTUAN
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function statusClass($status)
{
    return strtolower(
        str_replace(
            ' ',
            '-',
            trim((string)$status)
        )
    );
}


/* =========================================================
   INFORMASI USER LOGIN
========================================================= */

$namaUser =
    $_SESSION['full_name']
    ??
    $_SESSION['username']
    ??
    'Admin ERP';

$roleUser =
    $_SESSION['role']
    ??
    'Administrator';

$avatarName = urlencode($namaUser);


/* =========================================================
   AMBIL ID CUSTOMER
========================================================= */

$customerId = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;


if ($customerId <= 0) {

    header("Location: customers.php?error=customer_tidak_ditemukan");
    exit;
}


/* =========================================================
   AMBIL DATA CUSTOMER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        username,
        email,
        status,
        failed_attempts,
        created_at,
        updated_at
    FROM users
    WHERE id = ?
    AND role = 'customer'
    LIMIT 1
");

if (!$stmt) {

    die("Query customer gagal: "
        .
        e($conn->error));
}

$stmt->bind_param(
    "i",
    $customerId
);

$stmt->execute();

$result = $stmt->get_result();

$customer = $result->fetch_assoc();

$stmt->close();


if (!$customer) {

    header("Location: customers.php?error=customer_tidak_ditemukan");
    exit;
}


/* =========================================================
   PROSES UPDATE CUSTOMER
========================================================= */

$errors = [];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $fullName =
        trim(
            $_POST['full_name']
                ??
                ''
        );

    $username =
        trim(
            $_POST['username']
                ??
                ''
        );

    $email =
        trim(
            $_POST['email']
                ??
                ''
        );

    $status =
        trim(
            $_POST['status']
                ??
                ''
        );

    $password =
        $_POST['password']
        ??
        '';

    $confirmPassword =
        $_POST['confirm_password']
        ??
        '';


    /* =====================================================
       VALIDASI
    ===================================================== */

    if ($fullName === '') {

        $errors[] =
            'Nama lengkap wajib diisi.';
    }


    if ($username === '') {

        $errors[] =
            'Username wajib diisi.';
    }


    if ($email === '') {

        $errors[] =
            'Email wajib diisi.';
    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $errors[] =
            'Format email tidak valid.';
    }


    $statusValid =
        [
            'aktif',
            'nonaktif',
            'pending'
        ];

    if (
        !in_array(
            $status,
            $statusValid,
            true
        )
    ) {

        $errors[] =
            'Status pelanggan tidak valid.';
    }


    if (
        $password !== ''
        &&
        strlen($password) < 6
    ) {

        $errors[] =
            'Password baru minimal 6 karakter.';
    }


    if (
        $password !== ''
        &&
        $password !== $confirmPassword
    ) {

        $errors[] =
            'Konfirmasi password tidak cocok.';
    }


    /* =====================================================
       CEK USERNAME DUPLIKAT
    ===================================================== */

    if (empty($errors)) {

        $stmtCheckUsername =
            $conn->prepare("
                SELECT id
                FROM users
                WHERE username = ?
                AND id != ?
                LIMIT 1
            ");

        if ($stmtCheckUsername) {

            $stmtCheckUsername->bind_param(
                "si",
                $username,
                $customerId
            );

            $stmtCheckUsername->execute();

            $resultUsername =
                $stmtCheckUsername->get_result();

            if (
                $resultUsername->num_rows > 0
            ) {

                $errors[] =
                    'Username sudah digunakan oleh akun lain.';
            }

            $stmtCheckUsername->close();
        } else {

            $errors[] =
                'Gagal memeriksa username.';
        }
    }


    /* =====================================================
       CEK EMAIL DUPLIKAT
    ===================================================== */

    if (empty($errors)) {

        $stmtCheckEmail =
            $conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                AND id != ?
                LIMIT 1
            ");

        if ($stmtCheckEmail) {

            $stmtCheckEmail->bind_param(
                "si",
                $email,
                $customerId
            );

            $stmtCheckEmail->execute();

            $resultEmail =
                $stmtCheckEmail->get_result();

            if (
                $resultEmail->num_rows > 0
            ) {

                $errors[] =
                    'Email sudah digunakan oleh akun lain.';
            }

            $stmtCheckEmail->close();
        } else {

            $errors[] =
                'Gagal memeriksa email.';
        }
    }


    /* =====================================================
       UPDATE DATA
    ===================================================== */

    if (empty($errors)) {

        if ($password !== '') {

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $stmtUpdate =
                $conn->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        username = ?,
                        email = ?,
                        password = ?,
                        status = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                    AND role = 'customer'
                ");

            if ($stmtUpdate) {

                $stmtUpdate->bind_param(
                    "sssssi",
                    $fullName,
                    $username,
                    $email,
                    $passwordHash,
                    $status,
                    $customerId
                );
            }
        } else {

            $stmtUpdate =
                $conn->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        username = ?,
                        email = ?,
                        status = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                    AND role = 'customer'
                ");

            if ($stmtUpdate) {

                $stmtUpdate->bind_param(
                    "ssssi",
                    $fullName,
                    $username,
                    $email,
                    $status,
                    $customerId
                );
            }
        }


        if (
            !isset($stmtUpdate)
            ||
            !$stmtUpdate
        ) {

            $errors[] =
                'Gagal menyiapkan proses update.';
        } elseif (
            !$stmtUpdate->execute()
        ) {

            $errors[] =
                'Data pelanggan gagal diperbarui: '
                .
                $stmtUpdate->error;

            $stmtUpdate->close();
        } else {

            $stmtUpdate->close();

            header(
                "Location: customers_detail.php?id="
                    .
                    $customerId
                    .
                    "&success=updated"
            );

            exit;
        }
    }


    /* =====================================================
       TAMPILKAN DATA INPUT KEMBALI
    ===================================================== */

    if (!empty($errors)) {

        $customer['full_name'] =
            $fullName;

        $customer['username'] =
            $username;

        $customer['email'] =
            $email;

        $customer['status'] =
            $status;
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
        Edit Pelanggan - Toku Coffee ERP
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

        }


        .sidebar a i {

            width:
                2rem;

            font-size:
                1.7rem;

            text-align:
                center;

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


        .page-header {

            margin-bottom:
                2rem;

        }


        .page-header h1 {

            font-size:
                2.3rem;

        }


        .page-header p {

            font-size:
                1.2rem;

            color:
                #888;

            margin-top:
                .3rem;

        }


        /* =====================================================
           ALERT
        ===================================================== */

        .alert {

            padding:
                1.5rem 1.7rem;

            border:
                .1rem solid;

            border-radius:
                var(--border-radius);

            margin-bottom:
                2rem;

            font-size:
                1.2rem;

        }


        .alert-error {

            color:
                var(--red);

            background:
                #fff0ef;

            border-color:
                #e5c1be;

        }


        .alert-error div {

            margin-bottom:
                .4rem;

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

            max-width:
                90rem;

        }


        .form-panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

        }


        .form-header {

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


        .customer-avatar {

            width:
                6rem;

            height:
                6rem;

            border-radius:
                50%;

            border:
                .2rem solid var(--main-color);

        }


        .form-header h2 {

            font-size:
                1.9rem;

        }


        .form-header p {

            color:
                #888;

            font-size:
                1.1rem;

            margin-top:
                .3rem;

        }


        .form-grid {

            display:
                grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap:
                2rem;

        }


        .form-group {

            display:
                flex;

            flex-direction:
                column;

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

            margin-bottom:
                .7rem;

        }


        .form-group label span {

            color:
                var(--red);

        }


        .form-control {

            width:
                100%;

            height:
                4.7rem;

            padding:
                0 1.4rem;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            background:
                #fff;

            color:
                var(--main-color);

            font-size:
                1.2rem;

        }


        .form-control:focus {

            border:
                .2rem solid var(--main-color);

            border-radius:
                var(--border-radius-hover);

        }


        select.form-control {

            cursor:
                pointer;

        }


        .form-help {

            font-size:
                1rem;

            color:
                #999;

            margin-top:
                .5rem;

        }


        .password-box {

            position:
                relative;

        }


        .password-box .form-control {

            padding-right:
                4.5rem;

        }


        .password-toggle {

            position:
                absolute;

            right:
                1.3rem;

            top:
                50%;

            transform:
                translateY(-50%);

            background:
                none;

            color:
                #888;

            cursor:
                pointer;

            font-size:
                1.4rem;

        }


        .password-toggle:hover {

            color:
                var(--main-color);

        }


        /* =====================================================
           ACTION
        ===================================================== */

        .form-actions {

            display:
                flex;

            justify-content:
                flex-end;

            gap:
                1rem;

            margin-top:
                3rem;

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


        .btn-primary {

            background:
                var(--main-color);

            color:
                #fff;

        }


        .btn-primary:hover {

            background:
                #f7f4ec;

            color:
                var(--main-color);

        }


        .btn-danger {

            color:
                var(--red);

            border-color:
                var(--red);

        }


        /* =====================================================
           INFO
        ===================================================== */

        .info-box {

            margin-top:
                2rem;

            padding:
                1.5rem;

            background:
                #faf8f1;

            border:
                .1rem dashed var(--main-color);

            border-radius:
                var(--border-radius);

        }


        .info-box h3 {

            font-size:
                1.3rem;

            margin-bottom:
                .7rem;

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
           RESPONSIVE
        ===================================================== */

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

        }


        @media (max-width: 650px) {

            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .form-group.full {

                grid-column:
                    auto;

            }


            .form-actions {

                flex-direction:
                    column;

            }


            .form-actions .btn {

                width:
                    100%;

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


            .topbar {

                padding:
                    0 1.5rem;

            }


            .profile-info {

                display:
                    none;

            }


            .form-panel {

                padding:
                    1.5rem;

            }

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
    </style>

</head>


<body>


    <!-- =========================================================
     SIDEBAR
========================================================= -->

    <aside
        class="sidebar"
        id="sidebar">

        <a
            href="dashboard.php"
            class="logo">

            <i class="fas fa-mug-hot"></i>

            TOKU COFFEE

        </a>


        <div class="menu-title">
            Menu Utama
        </div>


        <a href="dashboard.php">

            <i class="fas fa-chart-pie"></i>

            Dashboard

        </a>


        <a href="orders.php">

            <i class="fas fa-shopping-bag"></i>

            Pesanan

        </a>


        <a href="../REKAYASA_E_BISNIS/produk/products.php">>

            <i class="fas fa-box"></i>

            Produk

        </a>


        <a
            href="customers.php"
            class="active">

            <i class="fas fa-users"></i>

            Pelanggan

        </a>


        <div
            class="menu-title"
            style="margin-top:2rem;">

            Manajemen

        </div>


        <a href="inventory.php">

            <i class="fas fa-warehouse"></i>

            Inventory

        </a>


        <a href="suppliers.php">

            <i class="fas fa-truck"></i>

            Supplier

        </a>


        <a href="finance.php">

            <i class="fas fa-wallet"></i>

            Keuangan

        </a>


        <a href="reports.php">

            <i class="fas fa-file-lines"></i>

            Laporan

        </a>


        <div
            class="menu-title"
            style="margin-top:2rem;">

            Sistem

        </div>


        <a href="settings.php">

            <i class="fas fa-gear"></i>

            Pengaturan

        </a>


        <div
            class="menu-title"
            style="margin-top:2rem;">

            Akun

        </div>


        <a
            href="login/logout.php"
            class="logout-menu"
            onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">

            <i class="fas fa-right-from-bracket"></i>

            Logout

        </a>

    </aside>


    <!-- =========================================================
     MAIN
========================================================= -->

    <main class="main">


        <header class="topbar">

            <div class="topbar-left">

                <i
                    class="fas fa-bars"
                    id="menu-btn">
                </i>


                <div class="page-title">

                    <h2>
                        Edit Pelanggan
                    </h2>

                    <p>
                        Perbarui informasi pelanggan Toku Coffee
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


            <div class="page-header">

                <h1>
                    Edit Data Pelanggan
                </h1>

                <p>
                    Ubah informasi akun pelanggan sesuai kebutuhan.
                </p>

            </div>


            <!-- =================================================
             ERROR
        ================================================= -->

            <?php if (!empty($errors)): ?>

                <div class="alert alert-error">

                    <?php foreach ($errors as $error): ?>

                        <div>

                            <i class="fas fa-circle-exclamation"></i>

                            <?= e($error) ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
             FORM
        ================================================= -->

            <div class="form-panel">


                <div class="form-header">

                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($customer['full_name']) ?>&background=443&color=fff"
                        class="customer-avatar"
                        alt="Customer">

                    <div>

                        <h2>
                            <?= e($customer['full_name']) ?>
                        </h2>

                        <p>
                            ID Customer #<?= (int)$customer['id'] ?>
                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    autocomplete="off">


                    <div class="form-grid">


                        <!-- NAMA -->

                        <div class="form-group">

                            <label for="full_name">

                                Nama Lengkap
                                <span>*</span>

                            </label>

                            <input
                                type="text"
                                name="full_name"
                                id="full_name"
                                class="form-control"
                                value="<?= e($customer['full_name']) ?>"
                                required>

                        </div>


                        <!-- USERNAME -->

                        <div class="form-group">

                            <label for="username">

                                Username
                                <span>*</span>

                            </label>

                            <input
                                type="text"
                                name="username"
                                id="username"
                                class="form-control"
                                value="<?= e($customer['username']) ?>"
                                required>

                        </div>


                        <!-- EMAIL -->

                        <div class="form-group full">

                            <label for="email">

                                Email
                                <span>*</span>

                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control"
                                value="<?= e($customer['email']) ?>"
                                required>

                        </div>


                        <!-- STATUS -->

                        <div class="form-group">

                            <label for="status">

                                Status
                                <span>*</span>

                            </label>

                            <select
                                name="status"
                                id="status"
                                class="form-control"
                                required>

                                <option
                                    value="aktif"
                                    <?= $customer['status'] === 'aktif'
                                        ? 'selected'
                                        : '' ?>>

                                    Aktif

                                </option>

                                <option
                                    value="nonaktif"
                                    <?= $customer['status'] === 'nonaktif'
                                        ? 'selected'
                                        : '' ?>>

                                    Nonaktif

                                </option>

                                <option
                                    value="pending"
                                    <?= $customer['status'] === 'pending'
                                        ? 'selected'
                                        : '' ?>>

                                    Pending

                                </option>

                            </select>

                        </div>


                        <!-- PASSWORD -->

                        <div class="form-group">

                            <label for="password">

                                Password Baru

                            </label>

                            <div class="password-box">

                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    placeholder="Kosongkan jika tidak diubah">

                                <button
                                    type="button"
                                    class="password-toggle"
                                    onclick="togglePassword('password', this)">

                                    <i class="fas fa-eye"></i>

                                </button>

                            </div>

                            <div class="form-help">

                                Minimal 6 karakter. Kosongkan jika password tidak ingin diubah.

                            </div>

                        </div>


                        <!-- KONFIRMASI PASSWORD -->

                        <div class="form-group">

                            <label for="confirm_password">

                                Konfirmasi Password

                            </label>

                            <div class="password-box">

                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    class="form-control"
                                    placeholder="Ulangi password baru">

                                <button
                                    type="button"
                                    class="password-toggle"
                                    onclick="togglePassword('confirm_password', this)">

                                    <i class="fas fa-eye"></i>

                                </button>

                            </div>

                        </div>


                    </div>


                    <!-- INFO -->

                    <div class="info-box">

                        <h3>

                            <i class="fas fa-circle-info"></i>

                            Informasi

                        </h3>

                        <p>

                            Perubahan data pelanggan akan langsung disimpan ke sistem.
                            Password hanya akan berubah apabila kolom password baru diisi.

                        </p>

                    </div>


                    <!-- ACTION -->

                    <div class="form-actions">

                        <a
                            href="customers_detail.php?id=<?= (int)$customer['id'] ?>"
                            class="btn">

                            <i class="fas fa-arrow-left"></i>

                            Kembali

                        </a>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="fas fa-save"></i>

                            Simpan Perubahan

                        </button>

                    </div>


                </form>

            </div>


        </section>


    </main>


    <script>
        /* =====================================================
       MOBILE SIDEBAR
    ===================================================== */

        const menuBtn =
            document.getElementById(
                'menu-btn'
            );

        const sidebar =
            document.getElementById(
                'sidebar'
            );


        if (
            menuBtn &&
            sidebar
        ) {

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

        }


        /* =====================================================
           PASSWORD
        ===================================================== */

        function togglePassword(
            inputId,
            button
        ) {

            const input =
                document.getElementById(
                    inputId
                );

            const icon =
                button.querySelector(
                    'i'
                );


            if (
                input.type === 'password'
            ) {

                input.type =
                    'text';

                icon.classList.remove(
                    'fa-eye'
                );

                icon.classList.add(
                    'fa-eye-slash'
                );

            } else {

                input.type =
                    'password';

                icon.classList.remove(
                    'fa-eye-slash'
                );

                icon.classList.add(
                    'fa-eye'
                );

            }

        }
    </script>


</body>

</html>
