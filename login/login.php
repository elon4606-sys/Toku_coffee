<?php
session_start();

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

/*
|--------------------------------------------------------------------------
| Pesan Error
|--------------------------------------------------------------------------
*/

$errorMessages = [
    'data_kosong'       => 'Username atau password wajib diisi.',
    'login_gagal'       => 'Username/email atau password salah.',
    'akun_diblokir'     => 'Akun kamu telah diblokir. Silakan hubungi administrator.',
    'akun_tidak_aktif' => 'Akun kamu tidak aktif. Silakan hubungi administrator.',
    'role_tidak_valid' => 'Role akun tidak valid.',
];

/*
|--------------------------------------------------------------------------
| Pesan Success
|--------------------------------------------------------------------------
*/

$successMessages = [
    'logout=success' => 'Kamu berhasil logout.',
];

if (isset($errorMessages[$error])) {
    $errorText = $errorMessages[$error];
} else {
    $errorText = $error;
}

if (isset($successMessages[$success])) {
    $successText = $successMessages[$success];
} else {
    $successText = $success;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Login - Toku Coffee</title>

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
           LOGIN PAGE
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


        /* LOGO */

        .login-info .logo {

            display: inline-block;

            font-size: 2.8rem;

            color: var(--main-color);

            margin-bottom: 2rem;

        }


        .login-info .logo i {

            padding-right: .7rem;

        }


        /* TAGLINE */

        .login-info .tagline {

            font-size: 1.5rem;

            color: var(--main-color);

            margin-bottom: 1.5rem;

            text-transform: uppercase;

            letter-spacing: .2rem;

        }


        /* HEADING */

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


        /* DESCRIPTION */

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
           LOGIN BOX
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


        /* INPUT */

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
           REMEMBER + FORGOT
        ===================================================== */

        .form-options {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin: .5rem 0 2rem;

        }


        .remember {

            display: flex;

            align-items: center;

            gap: .7rem;

            color: var(--main-color);

            font-size: 1.2rem;

            cursor: pointer;

        }


        .remember input {

            width: 1.5rem;

            height: 1.5rem;

            accent-color: var(--main-color);

            cursor: pointer;

        }


        .forgot-password {

            color: var(--main-color);

            font-size: 1.2rem;

            border-bottom: var(--border-hover);

        }


        .forgot-password:hover {

            letter-spacing: .05rem;

        }


        /* =====================================================
           LOGIN BUTTON
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
           DIVIDER
        ===================================================== */

        .divider {

            display: flex;

            align-items: center;

            gap: 1.2rem;

            margin: 2.5rem 0;

            color: #777;

            font-size: 1.2rem;

        }


        .divider::before,
        .divider::after {

            content: '';

            flex: 1;

            height: .1rem;

            background: #ccc;

        }


        /* =====================================================
           GOOGLE BUTTON
        ===================================================== */

        .btn-google {

            display: flex;

            align-items: center;

            justify-content: center;

            width: 100%;

            padding: 1.2rem 1.5rem;

            border: var(--border);

            border-radius: var(--border-radius);

            background: #fff;

            color: var(--main-color);

            font-size: 1.5rem;

            cursor: pointer;

        }


        .btn-google:hover {

            border-radius: var(--border-radius-hover);

            border: var(--border-hover);

            transform: translateY(-.2rem);

        }


        .btn-google i {

            margin-right: 1rem;

            font-size: 1.7rem;

        }


        /* =====================================================
           REGISTER LINK
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


            .form-options {

                gap: 1rem;

            }


            .forgot-password {

                font-size: 1.1rem;

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

                    Selamat Datang

                    <span>
                        Kembali ke Toku Coffee
                    </span>

                </h1>


                <!-- DESCRIPTION -->

                <p>

                    Login ke akun Toku Coffee untuk melanjutkan
                    belanja kopi, minuman, makanan, dan berbagai
                    produk pilihan favoritmu dengan mudah.

                </p>


                <!-- BENEFITS -->

                <div class="benefits">


                    <div class="benefit">

                        <i class="fas fa-cart-shopping"></i>

                        <h3>
                            Belanja Mudah
                        </h3>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-box"></i>

                        <h3>
                            Pesanan Terpantau
                        </h3>

                    </div>


                    <div class="benefit">

                        <i class="fas fa-shield-halved"></i>

                        <h3>
                            Akun Aman
                        </h3>

                    </div>


                </div>


            </div>


            <!-- =================================================
             LOGIN BOX
        ================================================== -->

            <div class="login-box">


                <!-- HEADING -->

                <div class="login-heading">

                    <h2>
                        Login
                    </h2>

                    <p>
                        Masuk Untuk Melanjutkan Belanja
                    </p>

                </div>


                <!-- =================================================
                 ERROR
            ================================================== -->

                <?php if ($errorText): ?>

                    <div class="alert alert-error">

                        <i class="fas fa-circle-exclamation"></i>

                        <span>
                            <?= htmlspecialchars($errorText) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                 SUCCESS
            ================================================== -->

                <?php if ($successText): ?>

                    <div class="alert alert-success">

                        <i class="fas fa-circle-check"></i>

                        <span>
                            <?= htmlspecialchars($successText) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                 FORM LOGIN
            ================================================== -->

                <form
                    action="login_process.php"
                    method="POST">


                    <!-- USERNAME / EMAIL -->

                    <div class="form-group">

                        <label for="login">

                            Username Atau Email

                        </label>


                        <div class="input-box">

                            <i class="fas fa-user"></i>


                            <input
                                type="text"
                                id="login"
                                name="login"
                                placeholder="Masukkan username atau email"
                                autocomplete="username"
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
                                placeholder="Masukkan password"
                                autocomplete="current-password"
                                required>


                            <span
                                class="password-toggle"
                                onclick="togglePassword()">


                                <i
                                    class="fas fa-eye"
                                    id="eyeIcon">
                                </i>


                            </span>

                        </div>

                    </div>


                    <!-- OPTIONS -->

                    <div class="form-options">


                        <label class="remember">

                            <input
                                type="checkbox"
                                name="remember"
                                value="1">

                            Ingat Saya

                        </label>


                        <a
                            href="#"
                            class="forgot-password">

                            Lupa Password?

                        </a>


                    </div>


                    <!-- LOGIN BUTTON -->

                    <button
                        type="submit"
                        class="btn-login">

                        <i class="fas fa-right-to-bracket"></i>

                        Login

                    </button>


                </form>


                <!-- =================================================
                 DIVIDER
            ================================================== -->

                <div class="divider">

                    <span>
                        Atau
                    </span>

                </div>


                <!-- =================================================
                 GOOGLE
            ================================================== -->

                <button
                    type="button"
                    class="btn-google"
                    onclick="googleLogin()">

                    <i class="fab fa-google"></i>

                    Login Dengan Google

                </button>


                <!-- =================================================
                 REGISTER
            ================================================== -->

                <div class="register-link">

                    Belum Punya Akun?

                    <a href="register.php">

                        Daftar Sekarang

                    </a>

                </div>


                <!-- =================================================
                 SECURITY
            ================================================== -->

                <div class="secure-info">

                    <i class="fas fa-shield-halved"></i>

                    Login Aman Dan Terlindungi

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

        function togglePassword() {

            const input =
                document.getElementById('password');

            const icon =
                document.getElementById('eyeIcon');


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


        /*
        |--------------------------------------------------------------------------
        | Google Login
        |--------------------------------------------------------------------------
        |
        | Untuk sekarang Google Login belum diaktifkan.
        | Nantinya bisa dihubungkan dengan Google OAuth.
        |
        */

        function googleLogin() {

            alert(
                'Login dengan Google belum diaktifkan.'
            );

        }
    </script>


</body>

</html>
