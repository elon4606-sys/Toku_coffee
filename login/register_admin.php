<?php

require_once "../config/koneksi.php";

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | VALIDASI
    |--------------------------------------------------------------------------
    */

    if (
        $full_name === '' ||
        $username === '' ||
        $email === '' ||
        $password === '' ||
        $confirm_password === ''
    ) {

        $error = "Semua data wajib diisi.";
    } elseif (strlen($full_name) < 3) {

        $error = "Nama lengkap minimal 3 karakter.";
    } elseif (strlen($username) < 4) {

        $error = "Username minimal 4 karakter.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {

        $error = "Username hanya boleh menggunakan huruf, angka, titik, dan underscore.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Format email tidak valid.";
    } elseif (strlen($password) < 6) {

        $error = "Password minimal 6 karakter.";
    } elseif ($password !== $confirm_password) {

        $error = "Konfirmasi password tidak cocok.";
    } else {

        /*
        |--------------------------------------------------------------------------
        | CEK USERNAME DAN EMAIL
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
               OR email = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Query pengecekan akun gagal: " . $conn->error;
        } else {

            $stmt->bind_param(
                "ss",
                $username,
                $email
            );

            if (!$stmt->execute()) {

                $error = "Pengecekan akun gagal: " . $stmt->error;

                $stmt->close();
            } else {

                $result = $stmt->get_result();

                $stmt->close();

                if ($result->num_rows > 0) {

                    $error = "Username atau email sudah digunakan.";
                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | HASH PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | DATA ADMIN
                    |--------------------------------------------------------------------------
                    */

                    $role = "admin";
                    $status = "active";
                    $failed_attempts = 0;

                    /*
                    |--------------------------------------------------------------------------
                    | INSERT ADMIN
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $conn->prepare("
                        INSERT INTO users
                        (
                            full_name,
                            username,
                            email,
                            password,
                            role,
                            status,
                            failed_attempts
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    if (!$stmt) {

                        $error = "Query pendaftaran gagal: " . $conn->error;
                    } else {

                        $stmt->bind_param(
                            "ssssssi",
                            $full_name,
                            $username,
                            $email,
                            $passwordHash,
                            $role,
                            $status,
                            $failed_attempts
                        );

                        if ($stmt->execute()) {

                            $success = "Akun admin berhasil dibuat. Silakan login.";

                            /*
                            | Bersihkan form setelah berhasil
                            */

                            $full_name = "";
                            $username = "";
                            $email = "";
                        } else {

                            $error = "Gagal membuat akun admin: " . $stmt->error;
                        }

                        $stmt->close();
                    }
                }
            }
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

    <title>Register Admin - Toku Coffee</title>


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
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600&display=swap');


        /* =====================================================
           ROOT
        ===================================================== */

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

        }


        /* =====================================================
           RESET
        ===================================================== */

        * {

            font-family: 'Poppins', sans-serif;

            margin: 0;
            padding: 0;

            box-sizing: border-box;

            outline: none;
            border: none;

            text-decoration: none;

            text-transform: capitalize;

            transition: all .2s linear;

        }


        html {

            font-size: 62.5%;

            overflow-x: hidden;

        }


        body {

            min-height: 100vh;

            background: #fff;

        }


        /* =====================================================
           REGISTER PAGE
        ===================================================== */

        .login-page {

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 5rem 9%;

            background:

                linear-gradient(rgba(255, 255, 255, .92),
                    rgba(255, 255, 255, .92)),

                url('../image/home-bg.jpg');

            background-position: center;

            background-size: cover;

        }


        /* =====================================================
           CONTAINER
        ===================================================== */

        .login-container {

            width: 100%;

            max-width: 110rem;

            display: flex;

            align-items: center;

            gap: 6rem;

        }


        /* =====================================================
           LEFT INFORMATION
        ===================================================== */

        .login-info {

            flex: 1 1 45rem;

        }


        .login-info .logo {

            display: inline-block;

            font-size: 2.8rem;

            color: var(--main-color);

            margin-bottom: 2rem;

        }


        .login-info .logo i {

            padding-right: .7rem;

        }


        .login-info .tagline {

            font-size: 1.5rem;

            color: var(--main-color);

            margin-bottom: 1.5rem;

            text-transform: uppercase;

            letter-spacing: .2rem;

        }


        .login-info h1 {

            font-size: 5.5rem;

            line-height: 1.2;

            color: var(--main-color);

            text-transform: uppercase;

            margin-bottom: 2rem;

        }


        .login-info h1 span {

            display: block;

            font-size: 3rem;

            text-transform: none;

        }


        .login-info p {

            color: var(--main-color);

            font-size: 1.5rem;

            line-height: 1.8;

            max-width: 50rem;

        }


        /* =====================================================
           BENEFITS
        ===================================================== */

        .benefits {

            display: flex;

            flex-wrap: wrap;

            gap: 1rem;

            margin-top: 3rem;

        }


        .benefit {

            flex: 1 1 18rem;

            padding: 1.5rem;

            border: var(--border);

            border-radius: var(--border-radius);

            text-align: center;

            color: var(--main-color);

            background: rgba(255, 255, 255, .35);

        }


        .benefit:hover {

            border: var(--border-hover);

            border-radius: var(--border-radius-hover);

            transform: translateY(-.3rem);

        }


        .benefit i {

            font-size: 2.8rem;

            margin-bottom: .8rem;

        }


        .benefit h3 {

            font-size: 1.4rem;

            font-weight: 500;

        }


        /* =====================================================
           REGISTER BOX
        ===================================================== */

        .login-box {

            flex: 1 1 43rem;

            max-width: 48rem;

            padding: 3.5rem;

            background: #fff;

            border: var(--border);

            border-radius: var(--border-radius);

            box-shadow:
                0 .5rem 1.5rem rgba(0, 0, 0, .08);

        }


        .login-box:hover {

            border-radius: var(--border-radius-hover);

        }


        /* =====================================================
           HEADING
        ===================================================== */

        .login-heading {

            text-align: center;

            margin-bottom: 2.5rem;

        }


        .login-heading h2 {

            font-size: 3rem;

            color: var(--main-color);

            margin-bottom: .5rem;

        }


        .login-heading p {

            font-size: 1.3rem;

            color: var(--main-color);

        }


        /* =====================================================
           ADMIN BADGE
        ===================================================== */

        .admin-badge {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: .7rem;

            padding: 1rem;

            margin-bottom: 2rem;

            border: var(--border);

            border-radius: var(--border-radius);

            color: var(--main-color);

            background: #fff;

            font-size: 1.2rem;

            font-weight: 500;

        }


        .admin-badge:hover {

            border: var(--border-hover);

            border-radius: var(--border-radius-hover);

        }


        /* =====================================================
           ALERT
        ===================================================== */

        .alert {

            display: flex;

            align-items: center;

            gap: 1rem;

            padding: 1.2rem 1.5rem;

            margin-bottom: 1.5rem;

            border: var(--border);

            border-radius: .5rem;

            font-size: 1.3rem;

        }


        .alert-error {

            border-color: #8b3e3e;

            color: #8b3e3e;

            background: #fffafa;

        }


        .alert-success {

            border-color: #526b52;

            color: #526b52;

            background: #f9fff9;

        }


        .alert i {

            font-size: 1.5rem;

            flex-shrink: 0;

        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-group {

            margin-bottom: 1.8rem;

        }


        .form-group label {

            display: block;

            font-size: 1.3rem;

            color: var(--main-color);

            margin-bottom: .6rem;

        }


        /* =====================================================
           INPUT
        ===================================================== */

        .input-box {

            position: relative;

        }


        .input-box>i {

            position: absolute;

            left: 1.4rem;

            top: 50%;

            transform: translateY(-50%);

            font-size: 1.5rem;

            color: var(--main-color);

            pointer-events: none;

        }


        .input-box input {

            width: 100%;

            padding: 1.2rem 4.2rem;

            border: var(--border);

            border-radius: .5rem;

            background: #fff;

            color: var(--main-color);

            font-size: 1.4rem;

            text-transform: none;

        }


        .input-box input:focus {

            border: var(--border-hover);

        }


        .input-box input::placeholder {

            color: #999;

        }


        /* =====================================================
           PASSWORD TOGGLE
        ===================================================== */

        .password-toggle {

            position: absolute;

            right: 1.4rem;

            top: 50%;

            transform: translateY(-50%);

            cursor: pointer;

            color: var(--main-color);

            font-size: 1.5rem;

        }


        .password-toggle:hover {

            transform:
                translateY(-50%) scale(1.1);

        }


        /* =====================================================
           REGISTER BUTTON
        ===================================================== */

        .btn-login {

            display: block;

            width: 100%;

            padding: 1.2rem 1.5rem;

            border: var(--border);

            border-radius: var(--border-radius);

            color: var(--main-color);

            background: none;

            cursor: pointer;

            font-size: 1.6rem;

            font-weight: 500;

        }


        .btn-login:hover {

            border-radius: var(--border-radius-hover);

            border: var(--border-hover);

            background: #fff;

            transform: translateY(-.2rem);

        }


        .btn-login i {

            margin-right: .5rem;

        }


        /* =====================================================
           LOGIN LINK
        ===================================================== */

        .register-link {

            text-align: center;

            margin-top: 2rem;

            font-size: 1.3rem;

            color: var(--main-color);

        }


        .register-link a {

            color: var(--main-color);

            font-weight: 600;

            border-bottom: var(--border-hover);

        }


        .register-link a:hover {

            letter-spacing: .1rem;

        }


        /* =====================================================
           SECURITY
        ===================================================== */

        .secure-info {

            display: flex;

            justify-content: center;

            align-items: center;

            gap: .6rem;

            margin-top: 1.5rem;

            color: var(--main-color);

            font-size: 1.1rem;

        }


        .secure-info i {

            font-size: 1.2rem;

        }


        /* =====================================================
           RESPONSIVE TABLET
        ===================================================== */

        @media (max-width: 991px) {

            html {

                font-size: 55%;

            }


            .login-page {

                padding: 3rem;

            }


            .login-container {

                gap: 3rem;

            }


            .login-info h1 {

                font-size: 4.5rem;

            }

        }


        /* =====================================================
           RESPONSIVE MOBILE
        ===================================================== */

        @media (max-width: 768px) {

            .login-page {

                padding: 2rem;

            }


            .login-container {

                display: block;

            }


            .login-info {

                text-align: center;

                margin-bottom: 3rem;

            }


            .login-info .logo {

                font-size: 2.5rem;

            }


            .login-info h1 {

                font-size: 3.8rem;

            }


            .login-info h1 span {

                font-size: 2.3rem;

            }


            .login-info p {

                margin: auto;

            }


            .benefits {

                justify-content: center;

            }


            .benefit {

                flex: 1 1 15rem;

            }


            .login-box {

                max-width: 55rem;

                margin: auto;

            }

        }


        /* =====================================================
           RESPONSIVE SMALL MOBILE
        ===================================================== */

        @media (max-width: 450px) {

            html {

                font-size: 50%;

            }


            .login-page {

                padding: 1.5rem;

            }


            .login-info h1 {

                font-size: 3.2rem;

            }


            .login-info h1 span {

                font-size: 2rem;

            }


            .login-info p {

                font-size: 1.3rem;

            }


            .benefits {

                display: none;

            }


            .login-box {

                padding: 2.5rem 2rem;

            }


            .login-heading h2 {

                font-size: 2.5rem;

            }

        }
    </style>

</head>


<body>


    <section class="login-page">


        <div class="login-container">


            <!-- =================================================
             INFORMASI TOKO
        ================================================== -->

            <div class="login-info">


                <!-- LOGO -->

                <a
                    href="../index.php"
                    class="logo">

                    <i class="fas fa-mug-hot"></i>

                    Toku Coffee

                </a>


                <!-- TAGLINE -->

                <div class="tagline">

                    <i class="fas fa-coffee"></i>

                    Premium Coffee & Beverage

                </div>


                <!-- HEADING -->

                <h1>

                    Buat Akun

                    <span>
                        Administrator Toku Coffee
                    </span>

                </h1>


                <!-- DESCRIPTION -->

                <p>

                    Buat akun administrator untuk mengelola
                    produk, pesanan, pelanggan, inventory,
                    supplier, keuangan, dan seluruh sistem
                    ERP Toku Coffee.

                </p>


                <!-- BENEFITS -->

                <div class="benefits">


                    <div class="benefit">

                        <i class="fas fa-boxes-stacked"></i>

                        <h3>
                            Kelola Produk
                        </h3>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-chart-line"></i>

                        <h3>
                            Kelola Bisnis
                        </h3>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-shield-halved"></i>

                        <h3>
                            Admin Aman
                        </h3>

                    </div>


                </div>


            </div>


            <!-- =================================================
             REGISTER BOX
        ================================================== -->

            <div class="login-box">


                <!-- HEADING -->

                <div class="login-heading">

                    <h2>
                        Register Admin
                    </h2>

                    <p>
                        Buat akun administrator baru
                    </p>

                </div>


                <!-- ADMIN BADGE -->

                <div class="admin-badge">

                    <i class="fas fa-user-shield"></i>

                    Role akun otomatis: ADMIN

                </div>


                <!-- ERROR -->

                <?php if ($error): ?>

                    <div class="alert alert-error">

                        <i class="fas fa-circle-exclamation"></i>

                        <span>

                            <?= htmlspecialchars($error) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- SUCCESS -->

                <?php if ($success): ?>

                    <div class="alert alert-success">

                        <i class="fas fa-circle-check"></i>

                        <span>

                            <?= htmlspecialchars($success) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                 FORM
            ================================================== -->

                <form
                    method="POST"
                    autocomplete="off">


                    <!-- NAMA -->

                    <div class="form-group">

                        <label for="full_name">

                            Nama Lengkap

                        </label>


                        <div class="input-box">

                            <i class="fas fa-user"></i>


                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                placeholder="Masukkan nama lengkap"
                                value="<?= htmlspecialchars($full_name ?? '') ?>"
                                autocomplete="name"
                                required>

                        </div>

                    </div>


                    <!-- USERNAME -->

                    <div class="form-group">

                        <label for="username">

                            Username

                        </label>


                        <div class="input-box">

                            <i class="fas fa-at"></i>


                            <input
                                type="text"
                                id="username"
                                name="username"
                                placeholder="Masukkan username admin"
                                value="<?= htmlspecialchars($username ?? '') ?>"
                                autocomplete="username"
                                required>

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">

                            Email

                        </label>


                        <div class="input-box">

                            <i class="fas fa-envelope"></i>


                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Masukkan email admin"
                                value="<?= htmlspecialchars($email ?? '') ?>"
                                autocomplete="email"
                                required>

                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">

                            Password

                        </label>


                        <div class="input-box">

                            <i class="fas fa-lock"></i>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Minimal 6 karakter"
                                minlength="6"
                                autocomplete="new-password"
                                required>


                            <span
                                class="password-toggle"
                                onclick="togglePassword('password','eye1')">

                                <i
                                    class="fas fa-eye"
                                    id="eye1">
                                </i>

                            </span>

                        </div>

                    </div>


                    <!-- KONFIRMASI PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">

                            Konfirmasi Password

                        </label>


                        <div class="input-box">

                            <i class="fas fa-lock"></i>


                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Ulangi password"
                                minlength="6"
                                autocomplete="new-password"
                                required>


                            <span
                                class="password-toggle"
                                onclick="togglePassword('confirm_password','eye2')">

                                <i
                                    class="fas fa-eye"
                                    id="eye2">
                                </i>

                            </span>

                        </div>

                    </div>


                    <!-- BUTTON -->

                    <button
                        type="submit"
                        class="btn-login">

                        <i class="fas fa-user-shield"></i>

                        Buat Akun Admin

                    </button>


                </form>


                <!-- LOGIN -->

                <div class="register-link">

                    Sudah Punya Akun?

                    <a href="login.php">

                        Login Sekarang

                    </a>

                </div>


                <!-- SECURITY -->

                <div class="secure-info">

                    <i class="fas fa-shield-halved"></i>

                    Admin Terlindungi Dengan Password Hash

                </div>


            </div>


        </div>


    </section>


    <script>
        /*
    |--------------------------------------------------------------------------
    | Toggle Password
    |--------------------------------------------------------------------------
    */

        function togglePassword(inputId, iconId) {

            const input =
                document.getElementById(inputId);

            const icon =
                document.getElementById(iconId);


            if (input.type === 'password') {

                input.type = 'text';

                icon.classList.remove('fa-eye');

                icon.classList.add('fa-eye-slash');

            } else {

                input.type = 'password';

                icon.classList.remove('fa-eye-slash');

                icon.classList.add('fa-eye');

            }

        }
    </script>


</body>

</html>
