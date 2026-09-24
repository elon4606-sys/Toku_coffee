<?php

require_once "config/koneksi.php";
require_once "config/session.php";

requireRole(['admin', 'staff']);


/* =========================================================
   FUNGSI BANTUAN
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   TIMEZONE
========================================================= */

date_default_timezone_set('Asia/Jakarta');


/* =========================================================
   BUAT TABEL SETTINGS OTOMATIS
========================================================= */

$conn->query("
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
    )
");


/* =========================================================
   FUNGSI SETTINGS
========================================================= */

function getSetting($conn, $key, $default = '')
{
    $stmt = $conn->prepare("
        SELECT setting_value
        FROM settings
        WHERE setting_key = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param("s", $key);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {

        $stmt->close();

        return $row['setting_value'] ?? $default;
    }

    $stmt->close();

    return $default;
}


/* =========================================================
   SIMPAN SETTINGS
========================================================= */

function saveSetting($conn, $key, $value)
{
    $stmt = $conn->prepare("
        INSERT INTO settings
        (
            setting_key,
            setting_value
        )
        VALUES
        (
            ?,
            ?
        )
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "ss",
        $key,
        $value
    );

    $success = $stmt->execute();

    $stmt->close();

    return $success;
}


/* =========================================================
   SEED DEFAULT SETTINGS
========================================================= */

$defaultSettings = [

    'nama_toko' =>
    'Toku Coffee',

    'email_toko' =>
    'admin@tokucoffee.com',

    'telepon_toko' =>
    '0812-3456-7890',

    'alamat_toko' =>
    'Jl. Coffee Nusantara No. 10, Indonesia',

    'website_toko' =>
    'www.tokucoffee.com',

    'security_login_protection' =>
    '1',

    'security_activity_log' =>
    '1',

    'security_extra_verification' =>
    '0',

    'notification_new_order' =>
    '1',

    'notification_low_stock' =>
    '1',

    'notification_payment' =>
    '1'

];


foreach ($defaultSettings as $key => $value) {

    if (
        getSetting(
            $conn,
            $key,
            null
        ) === null
    ) {

        saveSetting(
            $conn,
            $key,
            $value
        );
    }
}


/* =========================================================
   AMBIL USER LOGIN
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
    'admin';

$usernameSession =
    $_SESSION['username']
    ??
    'admin';


$currentUser = [

    'id' => 0,

    'full_name' =>
    $namaUser,

    'username' =>
    $usernameSession,

    'email' =>
    $_SESSION['email']
        ??
        'admin@tokucoffee.com',

    'role' =>
    $roleUser

];


/* =========================================================
   CARI USER DARI DATABASE
========================================================= */

$stmtUser = $conn->prepare("
    SELECT
        id,
        full_name,
        username,
        email,
        role
    FROM users
    WHERE username = ?
    LIMIT 1
");

if ($stmtUser) {

    $stmtUser->bind_param(
        "s",
        $usernameSession
    );

    $stmtUser->execute();

    $resultUser =
        $stmtUser->get_result();

    if (
        $resultUser &&
        $rowUser = $resultUser->fetch_assoc()
    ) {

        $currentUser = $rowUser;
    }

    $stmtUser->close();
}


/* =========================================================
   ID USER
========================================================= */

$currentUserId =
    (int)($currentUser['id'] ?? 0);


/* =========================================================
   AVATAR
========================================================= */

$avatarName =
    urlencode(
        $currentUser['full_name']
            ?: $currentUser['username']
            ?: 'Admin ERP'
    );


/* =========================================================
   PESAN NOTIFIKASI
========================================================= */

$message = '';

$messageType = '';


/* =========================================================
   PROSES FORM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {


    /* =====================================================
       INFORMASI TOKO
    ===================================================== */

    if (
        isset(
            $_POST['save_store']
        )
    ) {

        $namaTokoPost =
            trim(
                $_POST['nama_toko']
                    ?? ''
            );

        $emailTokoPost =
            trim(
                $_POST['email_toko']
                    ?? ''
            );

        $teleponTokoPost =
            trim(
                $_POST['telepon_toko']
                    ?? ''
            );

        $alamatTokoPost =
            trim(
                $_POST['alamat_toko']
                    ?? ''
            );

        $websiteTokoPost =
            trim(
                $_POST['website_toko']
                    ?? ''
            );


        if (
            $namaTokoPost === ''
        ) {

            $message =
                'Nama toko wajib diisi.';

            $messageType =
                'error';
        } elseif (
            $emailTokoPost !== ''
            &&
            !filter_var(
                $emailTokoPost,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $message =
                'Format email toko tidak valid.';

            $messageType =
                'error';
        } else {

            $dataStore = [

                'nama_toko' =>
                $namaTokoPost,

                'email_toko' =>
                $emailTokoPost,

                'telepon_toko' =>
                $teleponTokoPost,

                'alamat_toko' =>
                $alamatTokoPost,

                'website_toko' =>
                $websiteTokoPost

            ];


            $success = true;


            foreach (
                $dataStore
                as $key => $value
            ) {

                if (
                    !saveSetting(
                        $conn,
                        $key,
                        $value
                    )
                ) {

                    $success = false;

                    break;
                }
            }


            if ($success) {

                header(
                    "Location: settings.php?saved=store#toko"
                );

                exit;
            } else {

                $message =
                    'Gagal menyimpan informasi toko.';

                $messageType =
                    'error';
            }
        }
    }


    /* =====================================================
       AKUN SAYA
    ===================================================== */ elseif (
        isset(
            $_POST['save_account']
        )
    ) {

        $fullNamePost =
            trim(
                $_POST['full_name']
                    ?? ''
            );

        $usernamePost =
            trim(
                $_POST['username']
                    ?? ''
            );

        $emailPost =
            trim(
                $_POST['email']
                    ?? ''
            );


        if (
            $currentUserId <= 0
        ) {

            $message =
                'Data user tidak ditemukan.';

            $messageType =
                'error';
        } elseif (
            $fullNamePost === ''
        ) {

            $message =
                'Nama lengkap wajib diisi.';

            $messageType =
                'error';
        } elseif (
            $usernamePost === ''
        ) {

            $message =
                'Username wajib diisi.';

            $messageType =
                'error';
        } elseif (
            !preg_match(
                '/^[a-zA-Z0-9._-]{3,50}$/',
                $usernamePost
            )
        ) {

            $message =
                'Username hanya boleh menggunakan huruf, angka, titik, underscore atau tanda minus.';

            $messageType =
                'error';
        } elseif (
            $emailPost !== ''
            &&
            !filter_var(
                $emailPost,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $message =
                'Format email tidak valid.';

            $messageType =
                'error';
        } else {


            /* =============================================
               CEK USERNAME
            ============================================= */

            $checkUsername =
                $conn->prepare("
                    SELECT id
                    FROM users
                    WHERE username = ?
                    AND id != ?
                    LIMIT 1
                ");


            $usernameExists =
                false;


            if ($checkUsername) {

                $checkUsername->bind_param(
                    "si",
                    $usernamePost,
                    $currentUserId
                );

                $checkUsername->execute();

                $checkResult =
                    $checkUsername->get_result();

                if (
                    $checkResult
                    &&
                    $checkResult->num_rows > 0
                ) {

                    $usernameExists =
                        true;
                }

                $checkUsername->close();
            }


            if ($usernameExists) {

                $message =
                    'Username tersebut sudah digunakan user lain.';

                $messageType =
                    'error';
            } else {


                /* =========================================
                   UPDATE USER
                ========================================= */

                $updateUser =
                    $conn->prepare("
                        UPDATE users
                        SET
                            full_name = ?,
                            username = ?,
                            email = ?
                        WHERE id = ?
                    ");


                if ($updateUser) {

                    $updateUser->bind_param(
                        "sssi",
                        $fullNamePost,
                        $usernamePost,
                        $emailPost,
                        $currentUserId
                    );


                    if (
                        $updateUser->execute()
                    ) {

                        $_SESSION['full_name'] =
                            $fullNamePost;

                        $_SESSION['username'] =
                            $usernamePost;

                        $_SESSION['email'] =
                            $emailPost;


                        header(
                            "Location: settings.php?saved=account#akun"
                        );

                        exit;
                    } else {

                        $message =
                            'Gagal memperbarui akun.';

                        $messageType =
                            'error';
                    }


                    $updateUser->close();
                } else {

                    $message =
                        'Query update akun tidak dapat diproses.';

                    $messageType =
                        'error';
                }
            }
        }
    }


    /* =====================================================
       KEAMANAN
    ===================================================== */ elseif (
        isset(
            $_POST['save_security']
        )
    ) {

        $loginProtection =
            isset(
                $_POST['login_protection']
            )
            ? '1'
            : '0';


        $activityLog =
            isset(
                $_POST['activity_log']
            )
            ? '1'
            : '0';


        $extraVerification =
            isset(
                $_POST['extra_verification']
            )
            ? '1'
            : '0';


        $securitySettings = [

            'security_login_protection' =>
            $loginProtection,

            'security_activity_log' =>
            $activityLog,

            'security_extra_verification' =>
            $extraVerification

        ];


        $success = true;


        foreach (
            $securitySettings
            as $key => $value
        ) {

            if (
                !saveSetting(
                    $conn,
                    $key,
                    $value
                )
            ) {

                $success = false;

                break;
            }
        }


        if ($success) {

            header(
                "Location: settings.php?saved=security#keamanan"
            );

            exit;
        } else {

            $message =
                'Gagal menyimpan pengaturan keamanan.';

            $messageType =
                'error';
        }
    }


    /* =====================================================
       NOTIFIKASI
    ===================================================== */ elseif (
        isset(
            $_POST['save_notification']
        )
    ) {

        $newOrder =
            isset(
                $_POST['new_order']
            )
            ? '1'
            : '0';


        $lowStock =
            isset(
                $_POST['low_stock']
            )
            ? '1'
            : '0';


        $payment =
            isset(
                $_POST['payment']
            )
            ? '1'
            : '0';


        $notificationSettings = [

            'notification_new_order' =>
            $newOrder,

            'notification_low_stock' =>
            $lowStock,

            'notification_payment' =>
            $payment

        ];


        $success = true;


        foreach (
            $notificationSettings
            as $key => $value
        ) {

            if (
                !saveSetting(
                    $conn,
                    $key,
                    $value
                )
            ) {

                $success = false;

                break;
            }
        }


        if ($success) {

            header(
                "Location: settings.php?saved=notification#notifikasi"
            );

            exit;
        } else {

            $message =
                'Gagal menyimpan pengaturan notifikasi.';

            $messageType =
                'error';
        }
    }
}


/* =========================================================
   STATUS HASIL SIMPAN
========================================================= */

if (
    isset($_GET['saved'])
) {

    switch ($_GET['saved']) {

        case 'store':

            $message =
                'Informasi toko berhasil disimpan.';

            $messageType =
                'success';

            break;


        case 'account':

            $message =
                'Data akun berhasil diperbarui.';

            $messageType =
                'success';

            break;


        case 'security':

            $message =
                'Pengaturan keamanan berhasil disimpan.';

            $messageType =
                'success';

            break;


        case 'notification':

            $message =
                'Pengaturan notifikasi berhasil disimpan.';

            $messageType =
                'success';

            break;
    }
}


/* =========================================================
   LOAD INFORMASI TOKO
========================================================= */

$namaToko =
    getSetting(
        $conn,
        'nama_toko',
        'Toku Coffee'
    );


$emailToko =
    getSetting(
        $conn,
        'email_toko',
        'admin@tokucoffee.com'
    );


$teleponToko =
    getSetting(
        $conn,
        'telepon_toko',
        '0812-3456-7890'
    );


$alamatToko =
    getSetting(
        $conn,
        'alamat_toko',
        'Jl. Coffee Nusantara No. 10, Indonesia'
    );


$websiteToko =
    getSetting(
        $conn,
        'website_toko',
        'www.tokucoffee.com'
    );


/* =========================================================
   LOAD SECURITY
========================================================= */

$loginProtection =
    getSetting(
        $conn,
        'security_login_protection',
        '1'
    ) === '1';


$activityLog =
    getSetting(
        $conn,
        'security_activity_log',
        '1'
    ) === '1';


$extraVerification =
    getSetting(
        $conn,
        'security_extra_verification',
        '0'
    ) === '1';


/* =========================================================
   LOAD NOTIFICATION
========================================================= */

$newOrderNotification =
    getSetting(
        $conn,
        'notification_new_order',
        '1'
    ) === '1';


$lowStockNotification =
    getSetting(
        $conn,
        'notification_low_stock',
        '1'
    ) === '1';


$paymentNotification =
    getSetting(
        $conn,
        'notification_payment',
        '1'
    ) === '1';


/* =========================================================
   NOTIFIKASI PESANAN BARU
========================================================= */

$pesananBaru = 0;

$resultNotif =
    $conn->query("
        SELECT COUNT(*) AS total
        FROM pesanan
        WHERE status = 'Menunggu'
    ");

if (
    $resultNotif
) {

    $rowNotif =
        $resultNotif->fetch_assoc();

    $pesananBaru =
        (int)(
            $rowNotif['total']
            ?? 0
        );
}


/* =========================================================
   STATUS DATABASE
========================================================= */

$databaseStatus =
    'Terhubung';

if (
    !$conn->ping()
) {

    $databaseStatus =
        'Tidak Terhubung';
}


/* =========================================================
   INFORMASI SISTEM
========================================================= */

$phpVersion =
    PHP_VERSION;

$databaseName =
    'toku_coffee';

$timezone =
    date_default_timezone_get();

$environment =
    'Development';


?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Pengaturan - Toku Coffee ERP
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

            --bg: #faf9f5;

            --white: #fff;

            --green: #527853;

            --orange: #c68b3c;

            --red: #a94442;

            --blue: #557a95;

            --gray: #888;

        }


        /* =====================================================
           RESET
        ===================================================== */

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
                left .35s ease,
                transform .35s ease;

        }


        .logo {

            display: block;

            text-align: center;

            color:
                var(--main-color);

            font-size: 2.6rem;

            font-weight: 600;

            margin-bottom: 3rem;

            transition:
                transform .25s ease;

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

            position: relative;

            overflow: hidden;

        }


        .sidebar a i {

            width: 2rem;

            font-size: 1.7rem;

            text-align: center;

            transition:
                transform .25s ease;

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


        /* =====================================================
           MAIN
        ===================================================== */

        .main {

            margin-left: 26rem;

            min-height: 100vh;

            transition:
                margin-left .35s ease;

        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {

            height: 8rem;

            background: #fff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 4rem;

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

            animation:
                fadeDown .5s ease;

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


        #menu-btn:hover {

            transform:
                rotate(5deg) scale(1.1);

        }


        .topbar-right {

            display: flex;

            align-items: center;

            gap: 2rem;

        }


        /* =====================================================
           NOTIFICATION
        ===================================================== */

        .notification {

            position: relative;

            font-size: 2rem;

            cursor: pointer;

        }


        .notification:hover i {

            transform:
                rotate(-12deg) scale(1.08);

        }


        .notification span {

            position: absolute;

            top: -1rem;

            right: -1rem;

            width: 1.8rem;

            height: 1.8rem;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background:
                var(--red);

            color: #fff;

            font-size: 1rem;

            animation:
                pulse 2s infinite;

        }


        /* =====================================================
           PROFILE
        ===================================================== */

        .profile {

            display: flex;

            align-items: center;

            gap: 1rem;

            cursor: pointer;

        }


        .profile img {

            width: 4rem;

            height: 4rem;

            border-radius: 50%;

            border:
                .2rem solid var(--main-color);

            transition:
                transform .3s ease;

        }


        .profile:hover img {

            transform:
                scale(1.08) rotate(3deg);

        }


        .profile h4 {

            font-size: 1.4rem;

        }


        .profile p {

            font-size: 1.1rem;

            color: #999;

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
           ALERT
        ===================================================== */

        .alert {

            display: flex;

            align-items: center;

            gap: 1rem;

            padding:
                1.4rem 1.7rem;

            margin-bottom: 2rem;

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            font-size: 1.3rem;

            animation:
                fadeDown .4s ease;

        }


        .alert.success {

            color:
                var(--green);

            border-color:
                var(--green);

            background:
                #f3f8f3;

        }


        .alert.error {

            color:
                var(--red);

            border-color:
                var(--red);

            background:
                #fff3f2;

        }


        .alert i {

            font-size: 1.8rem;

        }


        /* =====================================================
           SETTINGS LAYOUT
        ===================================================== */

        .settings-layout {

            display: grid;

            grid-template-columns:
                24rem 1fr;

            gap: 2rem;

            align-items: start;

        }


        /* =====================================================
           SETTINGS MENU
        ===================================================== */

        .settings-menu {

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding: 1.5rem;

            position: sticky;

            top: 10rem;

        }


        .settings-menu h3 {

            font-size: 1.5rem;

            margin-bottom: 1.5rem;

            padding:
                .5rem 1rem;

        }


        .settings-menu a {

            display: flex;

            align-items: center;

            gap: 1rem;

            padding:
                1.2rem 1rem;

            margin-bottom: .5rem;

            color:
                var(--main-color);

            font-size: 1.2rem;

            border-radius:
                var(--border-radius);

        }


        .settings-menu a:hover,
        .settings-menu a.active {

            background:
                #f3f0e8;

            border:
                .1rem solid var(--main-color);

            transform:
                translateX(.3rem);

        }


        .settings-menu a i {

            width: 2rem;

            text-align: center;

            font-size: 1.5rem;

        }


        /* =====================================================
           SETTINGS CONTENT
        ===================================================== */

        .settings-content {

            display: flex;

            flex-direction: column;

            gap: 2rem;

        }


        /* =====================================================
           PANEL
        ===================================================== */

        .panel {

            background: #fff;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            padding: 2.5rem;

            transition:
                border-radius .25s ease,
                border .25s ease,
                transform .25s ease,
                box-shadow .25s ease;

        }


        .panel:hover {

            border:
                var(--border-hover);

            border-radius:
                var(--border-radius-hover);

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

            margin-top: .4rem;

        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 1.7rem;

        }


        .form-group {

            display: flex;

            flex-direction: column;

            gap: .7rem;

        }


        .form-group.full {

            grid-column:
                1 / -1;

        }


        .form-group label {

            font-size: 1.2rem;

            font-weight: 500;

        }


        .form-group input,
        .form-group textarea {

            width: 100%;

            padding:
                1.2rem 1.4rem;

            border:
                .1rem solid #ddd;

            border-radius:
                var(--border-radius);

            background:
                #faf9f5;

            color:
                var(--main-color);

            font-size: 1.2rem;

            transition:
                all .25s ease;

        }


        .form-group textarea {

            min-height: 10rem;

            resize: vertical;

        }


        .form-group input:focus,
        .form-group textarea:focus {

            border:
                .15rem solid var(--main-color);

            background:
                #fff;

            box-shadow:
                0 .5rem 1rem rgba(68, 68, 51, .05);

        }


        .form-group input[readonly] {

            background:
                #f0eee7;

            cursor:
                not-allowed;

        }


        /* =====================================================
           FORM ACTION
        ===================================================== */

        .form-actions {

            display: flex;

            justify-content: flex-end;

            gap: 1rem;

            margin-top: 2rem;

            padding-top: 1.5rem;

            border-top:
                .1rem solid #eee;

        }


        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: .7rem;

            padding:
                1rem 1.7rem;

            border:
                var(--border);

            border-radius:
                var(--border-radius);

            color:
                var(--main-color);

            background: none;

            cursor: pointer;

            font-size: 1.3rem;

            position: relative;

            overflow: hidden;

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


        .btn.primary {

            background:
                var(--main-color);

            color: #fff;

        }


        .btn.primary:hover {

            background:
                #5b5b46;

            color: #fff;

        }


        /* =====================================================
           PROFILE CARD
        ===================================================== */

        .account-header {

            display: flex;

            align-items: center;

            gap: 1.5rem;

            margin-bottom: 2.5rem;

            padding-bottom: 2rem;

            border-bottom:
                .1rem solid #eee;

        }


        .account-avatar {

            width: 7rem;

            height: 7rem;

            border-radius: 50%;

            border:
                .2rem solid var(--main-color);

        }


        .account-info h3 {

            font-size: 1.8rem;

        }


        .account-info p {

            color: #999;

            font-size: 1.2rem;

            margin-top: .3rem;

        }


        .role-badge {

            display: inline-flex;

            margin-top: .7rem;

            padding:
                .4rem 1rem;

            border:
                .1rem solid var(--main-color);

            border-radius:
                var(--border-radius);

            background:
                #f3f0e8;

            font-size: 1rem;

        }


        /* =====================================================
           TOGGLE
        ===================================================== */

        .toggle-list {

            display: flex;

            flex-direction: column;

            gap: 1rem;

        }


        .toggle-item {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 2rem;

            padding:
                1.5rem;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

            transition:
                all .25s ease;

        }


        .toggle-item:hover {

            background:
                #faf8f1;

            border:
                .1rem solid var(--main-color);

            transform:
                translateX(.3rem);

        }


        .toggle-info {

            flex: 1;

        }


        .toggle-info h4 {

            font-size: 1.3rem;

        }


        .toggle-info p {

            color: #999;

            font-size: 1.1rem;

            margin-top: .3rem;

        }


        .switch {

            position: relative;

            width: 4.8rem;

            height: 2.6rem;

            flex:
                0 0 4.8rem;

        }


        .switch input {

            opacity: 0;

            width: 0;

            height: 0;

        }


        .slider {

            position: absolute;

            cursor: pointer;

            inset: 0;

            background:
                #ddd;

            border-radius:
                3rem;

            transition:
                .25s;

        }


        .slider:before {

            content: "";

            position: absolute;

            width: 2rem;

            height: 2rem;

            left: .3rem;

            bottom: .3rem;

            background: #fff;

            border-radius: 50%;

            transition:
                .25s;

            box-shadow:
                0 .2rem .5rem rgba(0, 0, 0, .15);

        }


        .switch input:checked+.slider {

            background:
                var(--main-color);

        }


        .switch input:checked+.slider:before {

            transform:
                translateX(2.2rem);

        }


        /* =====================================================
           SYSTEM INFO
        ===================================================== */

        .system-list {

            display: flex;

            flex-direction: column;

            gap: 0;

        }


        .system-item {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding:
                1.4rem 0;

            border-bottom:
                .1rem solid #eee;

            gap: 2rem;

        }


        .system-item:last-child {

            border-bottom: none;

        }


        .system-item span:first-child {

            font-size: 1.2rem;

            color: #777;

        }


        .system-item strong {

            font-size: 1.2rem;

            text-align: right;

        }


        .connected {

            color:
                var(--green);

        }


        .development {

            color:
                var(--orange);

        }

        /* =====================================================
   LOGOUT
===================================================== */

        .sidebar .logout-menu {

            color: var(--red);

        }

        .sidebar .logout-menu i {

            color: var(--red);

        }

        .sidebar .logout-menu:hover {

            background: #fff0ef;

            border:
                var(--border);

            transform:
                translateX(.3rem);

        }

        .sidebar .logout-menu:hover i {

            transform:
                translateX(.3rem) scale(1.08);

        }

        /* =====================================================
           EMPTY / INFO
        ===================================================== */

        .info-box {

            display: flex;

            gap: 1rem;

            padding:
                1.3rem 1.5rem;

            margin-top: 1.5rem;

            background:
                #faf8f1;

            border:
                .1rem solid #eee;

            border-radius:
                var(--border-radius);

            font-size: 1.1rem;

            color: #777;

        }


        .info-box i {

            font-size: 1.5rem;

            color:
                var(--main-color);

        }


        /* =====================================================
           ANIMATION
        ===================================================== */

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


        @keyframes fadeDown {

            from {

                opacity: 0;

                transform:
                    translateY(-1rem);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0);

            }

        }


        @keyframes pulse {

            0% {

                transform:
                    scale(1);

            }

            50% {

                transform:
                    scale(1.15);

            }

            100% {

                transform:
                    scale(1);

            }

        }


        /* =====================================================
           RESPONSIVE 1200
        ===================================================== */

        @media (max-width: 1200px) {

            .settings-layout {

                grid-template-columns:
                    21rem 1fr;

            }

        }


        /* =====================================================
           RESPONSIVE 900
        ===================================================== */

        @media (max-width: 900px) {

            .sidebar {

                left: -27rem;

                box-shadow:
                    .5rem 0 2rem rgba(0, 0, 0, .08);

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

                padding:
                    2rem;

            }


            .topbar {

                padding:
                    0 2rem;

            }


            .profile-info {

                display: none;

            }


            .settings-layout {

                grid-template-columns:
                    1fr;

            }


            .settings-menu {

                position:
                    static;

                display: grid;

                grid-template-columns:
                    repeat(5, 1fr);

                gap: .5rem;

            }


            .settings-menu h3 {

                display: none;

            }


            .settings-menu a {

                justify-content:
                    center;

                flex-direction:
                    column;

                gap: .5rem;

                text-align: center;

                margin: 0;

                padding:
                    1rem .5rem;

            }


            .settings-menu a span {

                font-size: 1rem;

            }

        }


        /* =====================================================
           RESPONSIVE 700
        ===================================================== */

        @media (max-width: 700px) {

            .form-grid {

                grid-template-columns:
                    1fr;

            }


            .form-group.full {

                grid-column:
                    auto;

            }


            .settings-menu {

                grid-template-columns:
                    repeat(3, 1fr);

            }

        }


        /* =====================================================
           RESPONSIVE 550
        ===================================================== */

        @media (max-width: 550px) {

            html {

                font-size:
                    50%;

            }


            .content {

                padding:
                    1.5rem;

            }


            .panel {

                padding:
                    1.5rem;

            }


            .page-title h2 {

                font-size:
                    2rem;

            }


            .page-title p {

                display:
                    none;

            }


            .settings-menu {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .form-actions {

                flex-direction:
                    column;

            }


            .form-actions .btn {

                width:
                    100%;

            }


            .toggle-item {

                align-items:
                    flex-start;

            }


            .system-item {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap: .4rem;

            }


            .system-item strong {

                text-align:
                    left;

            }


            .account-header {

                align-items:
                    flex-start;

                flex-direction:
                    column;

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
            href="dashboar.php"
            class="logo">

            <i class="fas fa-mug-hot"></i>

            TOKU COFFEE

        </a>


        <div class="menu-title">

            Menu Utama

        </div>


        <a href="dashboar.php">

            <i class="fas fa-chart-pie"></i>

            Dashboard

        </a>


        <a href="orders.php">

            <i class="fas fa-shopping-bag"></i>

            Pesanan

        </a>


        <a href="../REKAYASA_E_BISNIS/produk/products.php">

            <i class="fas fa-box"></i>

            Produk

        </a>


        <a href="customers.php">

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


        <a
            href="settings.php"
            class="active">

            <i class="fas fa-gear"></i>

            Pengaturan

        </a>

        <!-- LOGOUT -->

        <div
            class="menu-title"
            style="margin-top:2rem;">

            Akun

        </div>


        <a
            href="../REKAYASA_E_BISNIS/login/logout.php"
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
                        Pengaturan
                    </h2>

                    <p>
                        Kelola konfigurasi Toku Coffee ERP
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

                            <?= e(
                                $currentUser['full_name']
                            ) ?>

                        </h4>

                        <p>

                            <?= e(
                                ucfirst(
                                    $currentUser['role']
                                )
                            ) ?>

                        </p>

                    </div>


                </div>


            </div>


        </header>



        <!-- =================================================
         CONTENT
    ================================================= -->

        <section class="content">


            <?php if ($message !== ''): ?>


                <div
                    class="alert <?= e($messageType) ?>">

                    <?php if ($messageType === 'success'): ?>

                        <i
                            class="fas fa-circle-check">
                        </i>

                    <?php else: ?>

                        <i
                            class="fas fa-circle-exclamation">
                        </i>

                    <?php endif; ?>


                    <span>

                        <?= e($message) ?>

                    </span>

                </div>


            <?php endif; ?>



            <!-- =================================================
             SETTINGS LAYOUT
        ================================================= -->

            <div class="settings-layout">


                <!-- =================================================
                 SETTINGS MENU
            ================================================= -->

                <nav class="settings-menu">


                    <h3>
                        Pengaturan
                    </h3>


                    <a
                        href="#toko"
                        class="active">

                        <i class="fas fa-store"></i>

                        <span>
                            Informasi Toko
                        </span>

                    </a>


                    <a href="#akun">

                        <i class="fas fa-user"></i>

                        <span>
                            Akun Saya
                        </span>

                    </a>


                    <a href="#keamanan">

                        <i class="fas fa-shield-halved"></i>

                        <span>
                            Keamanan
                        </span>

                    </a>


                    <a href="#notifikasi">

                        <i class="fas fa-bell"></i>

                        <span>
                            Notifikasi
                        </span>

                    </a>


                    <a href="#sistem">

                        <i class="fas fa-server"></i>

                        <span>
                            Sistem
                        </span>

                    </a>


                </nav>



                <!-- =================================================
                 SETTINGS CONTENT
            ================================================= -->

                <div class="settings-content">


                    <!-- =================================================
                     INFORMASI TOKO
                ================================================= -->

                    <section
                        class="panel"
                        id="toko">


                        <div class="panel-header">

                            <div>

                                <h2>
                                    Informasi Toko
                                </h2>

                                <p>
                                    Informasi utama yang digunakan pada sistem Toku Coffee.
                                </p>

                            </div>

                        </div>


                        <form
                            method="POST"
                            action="settings.php#toko">


                            <div class="form-grid">


                                <div class="form-group">

                                    <label>
                                        Nama Toko
                                    </label>

                                    <input
                                        type="text"
                                        name="nama_toko"
                                        value="<?= e($namaToko) ?>"
                                        placeholder="Nama toko"
                                        required>

                                </div>


                                <div class="form-group">

                                    <label>
                                        Email Toko
                                    </label>

                                    <input
                                        type="email"
                                        name="email_toko"
                                        value="<?= e($emailToko) ?>"
                                        placeholder="Email toko">

                                </div>


                                <div class="form-group">

                                    <label>
                                        Nomor Telepon
                                    </label>

                                    <input
                                        type="text"
                                        name="telepon_toko"
                                        value="<?= e($teleponToko) ?>"
                                        placeholder="Nomor telepon">

                                </div>


                                <div class="form-group">

                                    <label>
                                        Website
                                    </label>

                                    <input
                                        type="text"
                                        name="website_toko"
                                        value="<?= e($websiteToko) ?>"
                                        placeholder="Website toko">

                                </div>


                                <div class="form-group full">

                                    <label>
                                        Alamat Toko
                                    </label>

                                    <textarea
                                        name="alamat_toko"
                                        placeholder="Alamat lengkap toko"><?= e($alamatToko) ?></textarea>

                                </div>


                            </div>


                            <div class="form-actions">


                                <button
                                    type="reset"
                                    class="btn">

                                    <i class="fas fa-rotate-left"></i>

                                    Reset

                                </button>


                                <button
                                    type="submit"
                                    name="save_store"
                                    class="btn primary">

                                    <i class="fas fa-save"></i>

                                    Simpan Informasi

                                </button>


                            </div>


                        </form>


                    </section>



                    <!-- =================================================
                     AKUN SAYA
                ================================================= -->

                    <section
                        class="panel"
                        id="akun">


                        <div class="panel-header">

                            <div>

                                <h2>
                                    Akun Saya
                                </h2>

                                <p>
                                    Kelola informasi akun pengguna yang sedang login.
                                </p>

                            </div>

                        </div>


                        <div class="account-header">


                            <img
                                class="account-avatar"
                                src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff&size=150"
                                alt="Avatar">


                            <div class="account-info">

                                <h3>

                                    <?= e(
                                        $currentUser['full_name']
                                    ) ?>

                                </h3>


                                <p>

                                    @<?= e(
                                            $currentUser['username']
                                        ) ?>

                                </p>


                                <span class="role-badge">

                                    <?= e(
                                        ucfirst(
                                            $currentUser['role']
                                        )
                                    ) ?>

                                </span>

                            </div>


                        </div>


                        <form
                            method="POST"
                            action="settings.php#akun">


                            <div class="form-grid">


                                <div class="form-group">

                                    <label>
                                        Nama Lengkap
                                    </label>

                                    <input
                                        type="text"
                                        name="full_name"
                                        value="<?= e(
                                                    $currentUser['full_name']
                                                ) ?>"
                                        required>

                                </div>


                                <div class="form-group">

                                    <label>
                                        Username
                                    </label>

                                    <input
                                        type="text"
                                        name="username"
                                        value="<?= e(
                                                    $currentUser['username']
                                                ) ?>"
                                        required>

                                </div>


                                <div class="form-group full">

                                    <label>
                                        Email
                                    </label>

                                    <input
                                        type="email"
                                        name="email"
                                        value="<?= e(
                                                    $currentUser['email']
                                                ) ?>">

                                </div>


                            </div>


                            <div class="form-actions">


                                <button
                                    type="submit"
                                    name="save_account"
                                    class="btn primary">

                                    <i class="fas fa-user-pen"></i>

                                    Simpan Akun

                                </button>


                            </div>


                        </form>


                    </section>



                    <!-- =================================================
                     KEAMANAN
                ================================================= -->

                    <section
                        class="panel"
                        id="keamanan">


                        <div class="panel-header">

                            <div>

                                <h2>
                                    Keamanan
                                </h2>

                                <p>
                                    Pengaturan keamanan sistem ERP Toku Coffee.
                                </p>

                            </div>

                        </div>


                        <form
                            method="POST"
                            action="settings.php#keamanan">


                            <div class="toggle-list">


                                <!-- LOGIN PROTECTION -->

                                <div class="toggle-item">


                                    <div class="toggle-info">

                                        <h4>
                                            Proteksi Login
                                        </h4>

                                        <p>
                                            Membatasi percobaan login yang gagal pada sistem.
                                        </p>

                                    </div>


                                    <label class="switch">

                                        <input
                                            type="checkbox"
                                            name="login_protection"
                                            value="1"
                                            <?= $loginProtection ? 'checked' : '' ?>>

                                        <span class="slider"></span>

                                    </label>


                                </div>



                                <!-- ACTIVITY LOG -->

                                <div class="toggle-item">


                                    <div class="toggle-info">

                                        <h4>
                                            Catat Aktivitas Pengguna
                                        </h4>

                                        <p>
                                            Menyimpan preferensi pencatatan aktivitas pengguna.
                                        </p>

                                    </div>


                                    <label class="switch">

                                        <input
                                            type="checkbox"
                                            name="activity_log"
                                            value="1"
                                            <?= $activityLog ? 'checked' : '' ?>>

                                        <span class="slider"></span>

                                    </label>


                                </div>



                                <!-- EXTRA VERIFICATION -->

                                <div class="toggle-item">


                                    <div class="toggle-info">

                                        <h4>
                                            Verifikasi Tambahan
                                        </h4>

                                        <p>
                                            Mengaktifkan preferensi verifikasi tambahan akun.
                                        </p>

                                    </div>


                                    <label class="switch">

                                        <input
                                            type="checkbox"
                                            name="extra_verification"
                                            value="1"
                                            <?= $extraVerification ? 'checked' : '' ?>>

                                        <span class="slider"></span>

                                    </label>


                                </div>


                            </div>


                            <div class="form-actions">


                                <button
                                    type="submit"
                                    name="save_security"
                                    class="btn primary">

                                    <i class="fas fa-shield-halved"></i>

                                    Simpan Keamanan

                                </button>


                            </div>


                        </form>


                        <div class="info-box">

                            <i class="fas fa-circle-info"></i>

                            <span>
                                Pengaturan keamanan disimpan pada database. Implementasi detail seperti pembatasan login dan pencatatan aktivitas dapat dihubungkan dengan modul login dan sistem aktivitas.
                            </span>

                        </div>


                    </section>



                    <!-- =================================================
                     NOTIFIKASI
                ================================================= -->

                    <section
                        class="panel"
                        id="notifikasi">


                        <div class="panel-header">

                            <div>

                                <h2>
                                    Notifikasi
                                </h2>

                                <p>
                                    Atur jenis pemberitahuan yang ingin digunakan oleh sistem.
                                </p>

                            </div>

                        </div>


                        <form
                            method="POST"
                            action="settings.php#notifikasi">


                            <div class="toggle-list">


                                <!-- NEW ORDER -->

                                <div class="toggle-item">


                                    <div class="toggle-info">

                                        <h4>
                                            Pesanan Baru
                                        </h4>

                                        <p>
                                            Pemberitahuan ketika terdapat pesanan dengan status Menunggu.
                                        </p>

                                    </div>


                                    <label class="switch">

                                        <input
                                            type="checkbox"
                                            name="new_order"
                                            value="1"
                                            <?= $newOrderNotification ? 'checked' : '' ?>>

                                        <span class="slider"></span>

                                    </label>


                                </div>



                                <!-- LOW STOCK -->

                                <div class="toggle-item">


                                    <div class="toggle-info">

                                        <h4>
                                            Stok Menipis
                                        </h4>

                                        <p>
                                            Pemberitahuan ketika persediaan produk berada di bawah batas minimum.
                                        </p>

                                    </div>


                                    <label class="switch">

                                        <input
                                            type="checkbox"
                                            name="low_stock"
                                            value="1"
                                            <?= $lowStockNotification ? 'checked' : '' ?>>

                                        <span class="slider"></span>

                                    </label>


                                </div>



                                <!-- PAYMENT -->

                                <div class="toggle-item">


                                    <div class="toggle-info">

                                        <h4>
                                            Pembayaran
                                        </h4>

                                        <p>
                                            Pemberitahuan terkait status pembayaran transaksi.
                                        </p>

                                    </div>


                                    <label class="switch">

                                        <input
                                            type="checkbox"
                                            name="payment"
                                            value="1"
                                            <?= $paymentNotification ? 'checked' : '' ?>>

                                        <span class="slider"></span>

                                    </label>


                                </div>


                            </div>


                            <div class="form-actions">


                                <button
                                    type="submit"
                                    name="save_notification"
                                    class="btn primary">

                                    <i class="fas fa-bell"></i>

                                    Simpan Notifikasi

                                </button>


                            </div>


                        </form>


                    </section>



                    <!-- =================================================
                     SISTEM
                ================================================= -->

                    <section
                        class="panel"
                        id="sistem">


                        <div class="panel-header">

                            <div>

                                <h2>
                                    Informasi Sistem
                                </h2>

                                <p>
                                    Informasi lingkungan aplikasi Toku Coffee ERP.
                                </p>

                            </div>

                        </div>


                        <div class="system-list">


                            <div class="system-item">

                                <span>
                                    Nama Aplikasi
                                </span>

                                <strong>
                                    Toku Coffee ERP
                                </strong>

                            </div>


                            <div class="system-item">

                                <span>
                                    Versi Aplikasi
                                </span>

                                <strong>
                                    1.0.0
                                </strong>

                            </div>


                            <div class="system-item">

                                <span>
                                    Database
                                </span>

                                <strong>
                                    <?= e($databaseName) ?>
                                </strong>

                            </div>


                            <div class="system-item">

                                <span>
                                    Status Database
                                </span>

                                <strong
                                    class="connected">

                                    <i class="fas fa-circle-check"></i>

                                    <?= e($databaseStatus) ?>

                                </strong>

                            </div>


                            <div class="system-item">

                                <span>
                                    PHP Version
                                </span>

                                <strong>

                                    PHP <?= e($phpVersion) ?>

                                </strong>

                            </div>


                            <div class="system-item">

                                <span>
                                    Timezone
                                </span>

                                <strong>

                                    <?= e($timezone) ?>

                                </strong>

                            </div>


                            <div class="system-item">

                                <span>
                                    Environment
                                </span>

                                <strong
                                    class="development">

                                    <?= e($environment) ?>

                                </strong>

                            </div>


                        </div>


                    </section>


                </div>


            </div>


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
           TUTUP SIDEBAR SAAT LINK DIKLIK
        ===================================================== */

        document
            .querySelectorAll(
                '.sidebar a'
            )
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


        /* =====================================================
           CLOSE SIDEBAR KLIK DI LUAR
        ===================================================== */

        document.addEventListener(
            'click',
            function(event) {

                if (
                    window.innerWidth <= 900 &&
                    sidebar.classList.contains(
                        'active'
                    ) &&
                    !sidebar.contains(
                        event.target
                    ) &&
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
           SETTINGS MENU ACTIVE
        ===================================================== */

        const settingLinks =
            document.querySelectorAll(
                '.settings-menu a'
            );


        const settingSections =
            document.querySelectorAll(
                '.settings-content .panel'
            );


        function updateSettingMenu() {

            let current =
                'toko';


            settingSections.forEach(
                function(section) {

                    const top =
                        section.getBoundingClientRect()
                        .top;


                    if (
                        top <= 180
                    ) {

                        current =
                            section.id;

                    }

                }
            );


            settingLinks.forEach(
                function(link) {

                    link.classList.remove(
                        'active'
                    );


                    const href =
                        link.getAttribute(
                            'href'
                        );


                    if (
                        href === '#' + current
                    ) {

                        link.classList.add(
                            'active'
                        );

                    }

                }
            );

        }


        window.addEventListener(
            'scroll',
            updateSettingMenu
        );


        updateSettingMenu();


        /* =====================================================
           SMOOTH SCROLL SETTINGS
        ===================================================== */

        settingLinks.forEach(
            function(link) {

                link.addEventListener(
                    'click',
                    function(event) {

                        const targetId =
                            this.getAttribute(
                                'href'
                            );


                        if (
                            targetId &&
                            targetId.startsWith('#')
                        ) {

                            const target =
                                document.querySelector(
                                    targetId
                                );


                            if (target) {

                                event.preventDefault();


                                const top =
                                    target.getBoundingClientRect()
                                    .top +
                                    window.pageYOffset -
                                    100;


                                window.scrollTo({

                                    top: top,

                                    behavior: 'smooth'

                                });


                                settingLinks.forEach(
                                    function(item) {

                                        item.classList.remove(
                                            'active'
                                        );

                                    }
                                );


                                this.classList.add(
                                    'active'
                                );

                            }

                        }

                    }
                );

            });


        /* =====================================================
           AUTO HIDE ALERT
        ===================================================== */

        const alertBox =
            document.querySelector(
                '.alert'
            );


        if (alertBox) {

            setTimeout(
                function() {

                    alertBox.style.opacity =
                        '0';

                    alertBox.style.transform =
                        'translateY(-1rem)';


                    setTimeout(
                        function() {

                            alertBox.remove();

                        },
                        300
                    );

                },
                5000
            );

        }
    </script>


</body>

</html>
