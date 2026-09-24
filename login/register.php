    <?php
    session_start();

    $error = $_GET['error'] ?? '';
    $success = $_GET['success'] ?? '';
    ?>

    <!DOCTYPE html>
    <html lang="id">

    <head>

        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Daftar - Toku Coffee</title>

        <!-- GOOGLE FONT -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600&display=swap"
            rel="stylesheet">

        <!-- FONT AWESOME -->
        <link rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

        <style>
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600&display=swap');

            :root {
                --main-color: #443;
                --border-radius: 95% 4% 97% 5% / 4% 94% 3% 95%;
                --border-radius-hover: 4% 95% 6% 95% / 95% 4% 92% 5%;
                --border: .2rem solid var(--main-color);
                --border-hover: .2rem dashed var(--main-color);
            }

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

            /* =========================
            REGISTER
            ========================= */

            .register-page {
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

            .register-container {
                width: 100%;

                max-width: 110rem;

                display: flex;

                align-items: center;

                gap: 6rem;
            }

            /* =========================
            LEFT CONTENT
            ========================= */

            .register-info {
                flex: 1 1 45rem;
            }

            .register-info .logo {
                display: inline-block;

                font-size: 2.8rem;

                color: var(--main-color);

                margin-bottom: 2rem;
            }

            .register-info .logo i {
                padding-right: .7rem;
            }

            .register-info .tagline {
                font-size: 1.5rem;

                color: var(--main-color);

                margin-bottom: 1.5rem;

                text-transform: uppercase;

                letter-spacing: .2rem;
            }

            .register-info h1 {
                font-size: 5.5rem;

                line-height: 1.2;

                color: var(--main-color);

                text-transform: uppercase;

                margin-bottom: 2rem;
            }

            .register-info h1 span {
                display: block;

                font-size: 3rem;

                text-transform: none;
            }

            .register-info p {
                color: var(--main-color);

                font-size: 1.5rem;

                line-height: 1.8;

                max-width: 50rem;
            }

            /* =========================
            BENEFITS
            ========================= */

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

            /* =========================
            REGISTER BOX
            ========================= */

            .register-box {
                flex: 1 1 43rem;

                max-width: 48rem;

                padding: 3.5rem;

                background: #fff;

                border: var(--border);

                border-radius: var(--border-radius);

                box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .08);
            }

            .register-box:hover {
                border-radius: var(--border-radius-hover);
            }

            /* =========================
            HEADING
            ========================= */

            .register-heading {
                text-align: center;

                margin-bottom: 2.5rem;
            }

            .register-heading h2 {
                font-size: 3rem;

                color: var(--main-color);

                margin-bottom: .5rem;
            }

            .register-heading p {
                font-size: 1.3rem;

                color: var(--main-color);
            }

            /* =========================
            ALERT
            ========================= */

            .alert {
                display: flex;

                align-items: center;

                gap: 1rem;

                padding: 1.2rem 1.5rem;

                margin-bottom: 1.5rem;

                border: var(--border);

                border-radius: .5rem;

                font-size: 1.3rem;

                color: var(--main-color);
            }

            .alert-error {
                border-color: #8b3e3e;

                color: #8b3e3e;
            }

            .alert-success {
                border-color: #526b52;

                color: #526b52;
            }

            /* =========================
            FORM
            ========================= */

            .form-group {
                margin-bottom: 1.5rem;
            }

            .form-group label {
                display: block;

                font-size: 1.3rem;

                color: var(--main-color);

                margin-bottom: .6rem;
            }

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

            .password-toggle {
                position: absolute;

                right: 1.4rem;

                top: 50%;

                transform: translateY(-50%);

                cursor: pointer;

                color: var(--main-color);

                font-size: 1.5rem;
            }

            /* =========================
            FORM ROW
            ========================= */

            .form-row {
                display: grid;

                grid-template-columns: 1fr 1fr;

                gap: 1.2rem;
            }

            /* =========================
            TERMS
            ========================= */

            .terms {
                display: flex;

                align-items: flex-start;

                gap: .8rem;

                margin: 1rem 0 2rem;

                color: var(--main-color);

                font-size: 1.1rem;

                line-height: 1.7;

                cursor: pointer;
            }

            .terms input {
                margin-top: .4rem;

                accent-color: var(--main-color);
            }

            .terms a {
                color: var(--main-color);

                font-weight: 600;

                text-decoration: underline;
            }

            /* =========================
            BUTTON
            ========================= */

            .btn-register {
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

            .btn-register:hover {
                border-radius: var(--border-radius-hover);

                border: var(--border-hover);

                background: #fff;

                transform: translateY(-.2rem);
            }

            /* =========================
            LOGIN
            ========================= */

            .login-link {
                text-align: center;

                margin-top: 2rem;

                font-size: 1.3rem;

                color: var(--main-color);
            }

            .login-link a {
                color: var(--main-color);

                font-weight: 600;

                border-bottom: var(--border-hover);
            }

            .login-link a:hover {
                letter-spacing: .1rem;
            }

            /* =========================
            SECURITY
            ========================= */

            .secure-info {
                display: flex;

                justify-content: center;

                align-items: center;

                gap: .6rem;

                margin-top: 1.5rem;

                color: var(--main-color);

                font-size: 1.1rem;
            }

            /* =========================
            MOBILE LOGO
            ========================= */

            .mobile-logo {
                display: none;

                text-align: center;

                font-size: 2.5rem;

                color: var(--main-color);

                margin-bottom: 2rem;
            }

            /* =========================
            RESPONSIVE
            ========================= */

            @media (max-width: 991px) {

                html {
                    font-size: 55%;
                }

                .register-page {
                    padding: 3rem;
                }

                .register-container {
                    gap: 3rem;
                }

                .register-info h1 {
                    font-size: 4.5rem;
                }

            }

            @media (max-width: 768px) {

                .register-page {
                    padding: 2rem;
                }

                .register-container {
                    display: block;
                }

                .register-info {
                    text-align: center;

                    margin-bottom: 3rem;
                }

                .register-info .logo {
                    font-size: 2.5rem;
                }

                .register-info h1 {
                    font-size: 3.8rem;
                }

                .register-info h1 span {
                    font-size: 2.3rem;
                }

                .register-info p {
                    margin: auto;
                }

                .benefits {
                    justify-content: center;
                }

                .benefit {
                    flex: 1 1 15rem;
                }

                .register-box {
                    max-width: 55rem;

                    margin: auto;
                }

            }

            @media (max-width: 450px) {

                html {
                    font-size: 50%;
                }

                .register-page {
                    padding: 1.5rem;
                }

                .register-info h1 {
                    font-size: 3.2rem;
                }

                .register-info h1 span {
                    font-size: 2rem;
                }

                .register-info p {
                    font-size: 1.3rem;
                }

                .benefits {
                    display: none;
                }

                .register-box {
                    padding: 2.5rem 2rem;
                }

                .form-row {
                    grid-template-columns: 1fr;
                    gap: 0;
                }

                .register-heading h2 {
                    font-size: 2.5rem;
                }

            }
        </style>

    </head>


    <body>


        <section class="register-page">


            <div class="register-container">


                <!-- =========================
                INFORMASI TOKO
            ========================== -->

                <div class="register-info">

                    <a href="index.php" class="logo">

                        <i class="fas fa-mug-hot"></i>

                        Toku Coffee

                    </a>


                    <div class="tagline">

                        <i class="fas fa-coffee"></i>

                        Premium Coffee & Beverage

                    </div>


                    <h1>

                        Bergabung

                        <span>
                            dan Nikmati Kopi Favoritmu
                        </span>

                    </h1>


                    <p>

                        Buat akun Toku Coffee dan nikmati pengalaman
                        berbelanja kopi, minuman, makanan, serta
                        berbagai produk pilihan dengan mudah.

                    </p>


                    <div class="benefits">


                        <div class="benefit">

                            <i class="fas fa-cart-shopping"></i>

                            <h3>
                                Belanja Mudah
                            </h3>

                        </div>


                        <div class="benefit">

                            <i class="fas fa-truck"></i>

                            <h3>
                                Pengiriman Cepat
                            </h3>

                        </div>


                        <div class="benefit">

                            <i class="fas fa-heart"></i>

                            <h3>
                                Produk Favorit
                            </h3>

                        </div>


                    </div>

                </div>


                <!-- =========================
                FORM DAFTAR
            ========================== -->

                <div class="register-box">


                    <div class="register-heading">

                        <h2>
                            Buat Akun
                        </h2>

                        <p>
                            Daftar untuk mulai berbelanja di Toku Coffee
                        </p>

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


                    <form
                        action="register_process.php"
                        method="POST">


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
                                    autocomplete="name"
                                    required>

                            </div>

                        </div>


                        <!-- USERNAME + EMAIL -->

                        <div class="form-row">


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
                                        placeholder="Username"
                                        autocomplete="username"
                                        required>

                                </div>

                            </div>


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
                                        placeholder="Email"
                                        autocomplete="email"
                                        required>

                                </div>

                            </div>


                        </div>


                        <!-- PASSWORD -->

                        <div class="form-row">


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
                                        placeholder="Password"
                                        minlength="6"
                                        autocomplete="new-password"
                                        required>

                                    <span
                                        class="password-toggle"
                                        onclick="togglePassword('password','eye1')">

                                        <i
                                            class="fas fa-eye"
                                            id="eye1"></i>

                                    </span>

                                </div>

                            </div>


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
                                            id="eye2"></i>

                                    </span>

                                </div>

                            </div>


                        </div>


                        <!-- TERMS -->

                        <label class="terms">

                            <input
                                type="checkbox"
                                required>

                            <span>

                                Saya menyetujui

                                <a href="#">
                                    Syarat & Ketentuan
                                </a>

                                dan

                                <a href="#">
                                    Kebijakan Privasi
                                </a>

                                Toku Coffee.

                            </span>

                        </label>


                        <!-- BUTTON -->

                        <button
                            type="submit"
                            class="btn-register">

                            <i class="fas fa-user-plus"></i>

                            &nbsp; Daftar Sekarang

                        </button>


                    </form>


                    <!-- LOGIN -->

                    <div class="login-link">

                        Sudah punya akun?

                        <a href="login.php">
                            Login Sekarang
                        </a>

                    </div>


                    <!-- SECURITY -->

                    <div class="secure-info">

                        <i class="fas fa-shield-halved"></i>

                        Data akun kamu aman dan terlindungi

                    </div>


                </div>


            </div>


        </section>


        <script>
            function togglePassword(inputId, iconId) {

                const input = document.getElementById(inputId);

                const icon = document.getElementById(iconId);


                if (input.type === "password") {

                    input.type = "text";

                    icon.classList.remove("fa-eye");

                    icon.classList.add("fa-eye-slash");

                } else {

                    input.type = "password";

                    icon.classList.remove("fa-eye-slash");

                    icon.classList.add("fa-eye");

                }

            }
        </script>


    </body>

    </html>
