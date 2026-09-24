<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";

if (file_exists("../config/notifikasi.php")) {
    require_once "../config/notifikasi.php";
}

/* =====================================================
   SESSION & LOGIN
===================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

if ($role !== 'customer') {
    header("Location: ../login/login.php?error=akses_ditolak");
    exit;
}

/* =====================================================
   HELPER
===================================================== */

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

/* =====================================================
   DATA USER
===================================================== */

$user = null;

$stmtUser = $conn->prepare("
    SELECT
        id,
        full_name,
        username,
        email,
        role,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($stmtUser) {

    $stmtUser->bind_param(
        "i",
        $userId
    );

    $stmtUser->execute();

    $resultUser = $stmtUser->get_result();

    $user = $resultUser->fetch_assoc();

    $stmtUser->close();
}

if (!$user) {

    session_destroy();

    header(
        "Location: ../login/login.php?error=user_tidak_ditemukan"
    );

    exit;
}

/* =====================================================
   INFORMASI USER
===================================================== */

$namaUser =
    $user['full_name']
    ?: $user['username']
    ?: 'Pelanggan';

$username =
    $user['username']
    ?? '-';

$emailUser =
    $user['email']
    ?? '-';

$statusUser =
    $user['status']
    ?? 'Aktif';

$avatarName =
    urlencode($namaUser);

/* =====================================================
   KERANJANG
===================================================== */

$jumlahKeranjang = 0;

if (
    isset($_SESSION['keranjang']) &&
    is_array($_SESSION['keranjang'])
) {

    foreach (
        $_SESSION['keranjang']
        as $item
    ) {

        $qty =
            $item['qty']
            ?? $item['jumlah']
            ?? 0;

        $jumlahKeranjang += (int)$qty;
    }
}

/* =====================================================
   DATA PESANAN
===================================================== */

$totalPesanan   = 0;
$totalBelanja   = 0;
$pesananSelesai = 0;
$pesananAktif   = 0;

$orders = [];

$stmtOrder = $conn->prepare("
    SELECT
        id,
        tanggal_pesanan,
        total,
        status
    FROM pesanan
    WHERE user_id = ?
    ORDER BY id DESC
");

if ($stmtOrder) {

    $stmtOrder->bind_param(
        "i",
        $userId
    );

    $stmtOrder->execute();

    $resultOrder = $stmtOrder->get_result();

    while (
        $row =
        $resultOrder->fetch_assoc()
    ) {

        $orders[] = $row;

        $totalPesanan++;

        $status =
            strtolower(
                trim(
                    $row['status'] ?? ''
                )
            );

        if ($status !== 'dibatalkan') {

            $totalBelanja +=
                (float)$row['total'];
        }

        if ($status === 'selesai') {

            $pesananSelesai++;
        }

        if (
            $status !== 'selesai' &&
            $status !== 'dibatalkan'
        ) {

            $pesananAktif++;
        }
    }

    $stmtOrder->close();
}

/* =====================================================
   LEVEL CUSTOMER
===================================================== */

if ($totalBelanja >= 10000000) {

    $level = 'Platinum';
} elseif ($totalBelanja >= 5000000) {

    $level = 'Gold';
} elseif ($totalBelanja >= 2000000) {

    $level = 'Silver';
} else {

    $level = 'Bronze';
}

/* =====================================================
   POIN REWARD
===================================================== */

$poin =
    floor(
        $totalBelanja / 10000
    );

/* =====================================================
   PESAN SUKSES / ERROR
===================================================== */

$pesanSukses = '';
$pesanError  = '';

/* =====================================================
   UPDATE PROFIL
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_profile'])
) {

    $namaBaru =
        trim(
            $_POST['full_name']
                ?? ''
        );

    $emailBaru =
        trim(
            $_POST['email']
                ?? ''
        );

    if ($namaBaru === '') {

        $pesanError =
            'Nama lengkap tidak boleh kosong.';
    } else {

        $stmtUpdate =
            $conn->prepare("
                UPDATE users
                SET
                    full_name = ?,
                    email = ?
                WHERE id = ?
            ");

        if ($stmtUpdate) {

            $stmtUpdate->bind_param(
                "ssi",
                $namaBaru,
                $emailBaru,
                $userId
            );

            if ($stmtUpdate->execute()) {

                $pesanSukses =
                    'Profil berhasil diperbarui.';

                $user['full_name'] =
                    $namaBaru;

                $user['email'] =
                    $emailBaru;

                $namaUser =
                    $namaBaru;

                $avatarName =
                    urlencode($namaUser);
            } else {

                $pesanError =
                    'Profil gagal diperbarui.';
            }

            $stmtUpdate->close();
        } else {

            $pesanError =
                'Query update profil gagal.';
        }
    }
}

/* =====================================================
   UPDATE PASSWORD
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_password'])
) {

    $passwordLama =
        $_POST['password_lama']
        ?? '';

    $passwordBaru =
        $_POST['password_baru']
        ?? '';

    $konfirmasi =
        $_POST['konfirmasi_password']
        ?? '';

    if (
        $passwordLama === '' ||
        $passwordBaru === '' ||
        $konfirmasi === ''
    ) {

        $pesanError =
            'Semua kolom password wajib diisi.';
    } elseif (
        $passwordBaru !== $konfirmasi
    ) {

        $pesanError =
            'Konfirmasi password tidak sama.';
    } elseif (
        strlen($passwordBaru) < 6
    ) {

        $pesanError =
            'Password minimal 6 karakter.';
    } else {

        $stmtPass =
            $conn->prepare("
                SELECT password
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

        if ($stmtPass) {

            $stmtPass->bind_param(
                "i",
                $userId
            );

            $stmtPass->execute();

            $resultPass =
                $stmtPass->get_result();

            $dataPass =
                $resultPass->fetch_assoc();

            $stmtPass->close();

            if (
                $dataPass &&
                password_verify(
                    $passwordLama,
                    $dataPass['password']
                )
            ) {

                $passwordHash =
                    password_hash(
                        $passwordBaru,
                        PASSWORD_DEFAULT
                    );

                $stmtChange =
                    $conn->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                    ");

                if ($stmtChange) {

                    $stmtChange->bind_param(
                        "si",
                        $passwordHash,
                        $userId
                    );

                    if ($stmtChange->execute()) {

                        $pesanSukses =
                            'Password berhasil diubah.';
                    } else {

                        $pesanError =
                            'Password gagal diubah.';
                    }

                    $stmtChange->close();
                } else {

                    $pesanError =
                        'Query password gagal.';
                }
            } else {

                $pesanError =
                    'Password lama tidak sesuai.';
            }
        } else {

            $pesanError =
                'Data password tidak dapat dibaca.';
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

    <title>Akun Saya - Toku Coffee</title>

    <!-- GOOGLE FONT -->

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

    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- CSS UTAMA -->

    <link
        rel="stylesheet"
        href="../css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            overflow-x: hidden;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .header .logo {

            display: flex;
            align-items: center;

            font-weight: 700;
            letter-spacing: .1rem;

        }

        .toku-logo-text {
            font-weight: 700;
        }

        .toku-logo-text span {
            color: var(--main-color);
        }

        .header .user-box {

            display: flex;
            align-items: center;

            gap: 1.5rem;

        }

        .cart-link {

            position: relative;

            width: 4.5rem;
            height: 4.5rem;

            display: flex;

            align-items: center;
            justify-content: center;

            border: .1rem solid #ddd;

            border-radius: 50%;

            background: #fff;

            color: var(--main-color);

            font-size: 1.8rem;

            text-decoration: none;

            transition: .2s ease;

        }

        .cart-link:hover {

            background: #f5f2ea;

            border-color: var(--main-color);

            transform: translateY(-2px);

        }

        .cart-link .count {

            position: absolute;

            top: -.5rem;
            right: -.5rem;

            min-width: 2rem;
            height: 2rem;

            padding: 0 .4rem;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #a94442;

            color: #fff;

            border-radius: 50%;

            font-size: 1rem;

            font-weight: 600;

        }

        .user-info {

            display: flex;

            align-items: center;

            gap: 1rem;

        }

        .user-profile-link {

            display: block;

            width: 4.5rem;
            height: 4.5rem;

        }

        .user-profile-link img {

            width: 100%;
            height: 100%;

            object-fit: cover;

            border-radius: 50%;

            border: .2rem solid #eee;

        }

        .user-data h4 {

            margin: 0;

            font-size: 1.4rem;

            color: #333;

            font-weight: 600;

            white-space: nowrap;

        }

        .user-data a {

            font-size: 1.2rem;

            color: var(--main-color);

            text-decoration: none;

        }

        /* =====================================================
           ACCOUNT PAGE
        ===================================================== */

        .account-page {

            padding: 8rem 9% 5rem;

            background: #faf9f5;

            min-height: 100vh;

        }

        .account-header {

            text-align: center;

            margin-bottom: 4rem;

        }

        .account-label {

            display: inline-flex;

            align-items: center;

            gap: .7rem;

            background: #f4efe7;

            color: var(--main-color);

            border-radius: 5rem;

            padding: .7rem 1.6rem;

            font-size: 1.3rem;

            font-weight: 600;

            margin-bottom: 1.2rem;

        }

        .account-header h1 {

            font-size: 3.5rem;

            color: #2d211b;

            margin-bottom: 1rem;

            line-height: 1.3;

        }

        .account-header h1 span {

            color: var(--main-color);

        }

        .account-header p {

            font-size: 1.5rem;

            color: #777;

            line-height: 1.7;

        }

        /* =====================================================
           ALERT
        ===================================================== */

        .account-alert {

            max-width: 120rem;

            margin: 0 auto 2rem;

            padding: 1.4rem 1.8rem;

            border-radius: 1rem;

            font-size: 1.3rem;

            display: flex;

            align-items: center;

            gap: 1rem;

        }

        .account-alert.success {

            background: #edf7ee;

            color: #527853;

            border: .1rem solid #cce4ce;

        }

        .account-alert.error {

            background: #fceeee;

            color: #a94442;

            border: .1rem solid #eccccc;

        }

        /* =====================================================
           STATISTIK
        ===================================================== */

        .account-stats {

            max-width: 120rem;

            margin: 0 auto 3rem;

            display: grid;

            grid-template-columns: repeat(4, 1fr);

            gap: 1.8rem;

        }

        .account-stat {

            background: #fff;

            border: .1rem solid #e8e3dc;

            border-radius: 1.5rem;

            padding: 2rem;

            display: flex;

            align-items: center;

            gap: 1.5rem;

            transition: .2s ease;

            min-width: 0;

        }

        .account-stat:hover {

            transform: translateY(-.5rem);

            box-shadow:
                0 1.5rem 3rem rgba(68, 51, 51, .08);

        }

        .stat-icon {

            width: 5rem;
            height: 5rem;

            flex-shrink: 0;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 1.2rem;

            background: #f4efe7;

            color: var(--main-color);

            font-size: 2rem;

        }

        .account-stat h3 {

            font-size: 1.9rem;

            color: #2d211b;

            margin-bottom: .3rem;

            word-break: break-word;

        }

        .account-stat p {

            font-size: 1.1rem;

            color: #888;

        }

        .user-avatar-wrapper {
            position: relative;
        }

        .user-profile-btn {
            border: none;
            background: transparent;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .user-profile-btn img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }

        .user-dropdown {
            position: absolute;
            top: 52px;
            right: 0;
            width: 180px;
            background: #fff;
            border-radius: 10px;
            padding: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, .15);

            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);

            transition: .25s ease;
            z-index: 9999;
        }

        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;

            padding: 11px 12px;
            border-radius: 7px;

            text-decoration: none;
            color: #443;
            font-size: 14px;
        }

        .user-dropdown a:hover {
            background: #f7f3ef;
        }

        .user-dropdown .logout-link {
            color: #a94442;
        }

        .user-dropdown .logout-link:hover {
            background: #fff0ef;
        }

        /* =====================================================
           ACCOUNT GRID
        ===================================================== */

        .account-grid {

            max-width: 120rem;

            margin: 0 auto;

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 2.5rem;

        }

        .account-card {

            background: #fff;

            border: .1rem solid #e8e3dc;

            border-radius: 1.8rem;

            padding: 2.5rem;

            min-width: 0;

        }

        .account-card.full {

            grid-column: 1 / -1;

        }

        .card-title {

            display: flex;

            align-items: center;

            gap: 1rem;

            margin-bottom: 2.2rem;

            padding-bottom: 1.5rem;

            border-bottom: .1rem solid #eee;

        }

        .card-title-icon {

            width: 4rem;
            height: 4rem;

            flex-shrink: 0;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #f4efe7;

            border-radius: 1rem;

            color: var(--main-color);

            font-size: 1.6rem;

        }

        .card-title h2 {

            font-size: 1.9rem;

            color: #2d211b;

        }

        .card-title p {

            font-size: 1.1rem;

            color: #999;

        }

        /* =====================================================
           PROFILE
        ===================================================== */

        .profile-box {

            display: flex;

            align-items: center;

            gap: 2rem;

        }

        .profile-avatar {

            width: 10rem;
            height: 10rem;

            flex-shrink: 0;

            position: relative;

        }

        .profile-avatar img {

            width: 100%;
            height: 100%;

            border-radius: 50%;

            border: .25rem solid var(--main-color);

            object-fit: cover;

        }

        .online-dot {

            position: absolute;

            width: 2rem;
            height: 2rem;

            right: .3rem;
            bottom: .5rem;

            background: #527853;

            border: .3rem solid #fff;

            border-radius: 50%;

        }

        .profile-info {

            min-width: 0;
        }

        .profile-info h2 {

            font-size: 2.2rem;

            color: #2d211b;

            margin-bottom: .5rem;

            word-break: break-word;

        }

        .profile-info .username {

            font-size: 1.3rem;

            color: #888;

            margin-bottom: 1rem;

            word-break: break-word;

        }

        .membership {

            display: inline-flex;

            align-items: center;

            gap: .5rem;

            padding: .5rem 1.2rem;

            background: #fff4ce;

            color: #9a771e;

            border: .1rem solid #d8b94f;

            border-radius: 5rem;

            font-size: 1.1rem;

            font-weight: 600;

        }

        .profile-details {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 1.5rem;

            margin-top: 2.5rem;

        }

        .detail-item {

            padding: 1.4rem;

            background: #faf9f5;

            border-radius: 1rem;

            min-width: 0;

        }

        .detail-item label {

            display: block;

            color: #999;

            font-size: 1.1rem;

            margin-bottom: .5rem;

        }

        .detail-item p {

            font-size: 1.3rem;

            color: #333;

            word-break: break-word;

            line-height: 1.6;

        }

        .detail-item i {

            margin-right: .5rem;

            color: var(--main-color);

        }

        /* =====================================================
           FORM
        ===================================================== */

        .form-grid {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 1.5rem;

        }

        .form-group {

            min-width: 0;
        }

        .form-group label {

            display: block;

            font-size: 1.2rem;

            color: #666;

            margin-bottom: .7rem;

        }

        .form-group input {

            width: 100%;

            padding: 1.2rem 1.4rem;

            border: .1rem solid #ddd;

            border-radius: 1rem;

            outline: none;

            font-family: inherit;

            font-size: 1.3rem;

            background: #fff;

            transition: .2s ease;

        }

        .form-group input:focus {

            border-color: var(--main-color);

            box-shadow:
                0 0 0 .3rem rgba(68, 51, 51, .07);

        }

        .form-group input[readonly] {

            background: #f7f5f0;

            color: #888;

        }

        .form-actions {

            margin-top: 1.8rem;

        }

        .account-btn {

            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: .7rem;

            padding: 1.1rem 1.8rem;

            border-radius: 1rem;

            border: .1rem solid var(--main-color);

            background: var(--main-color);

            color: #fff;

            font-family: inherit;

            font-size: 1.3rem;

            cursor: pointer;

            transition: .2s ease;

            text-decoration: none;

        }

        .account-btn:hover {

            background: #2f2424;

            transform: translateY(-2px);

        }

        .account-btn-light {

            background: #fff;

            color: var(--main-color);

        }

        .account-btn-light:hover {

            background: #f5f1eb;

        }

        /* =====================================================
           ORDER
        ===================================================== */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;

            -webkit-overflow-scrolling: touch;

        }

        .order-table {

            width: 100%;

            border-collapse: collapse;

            min-width: 700px;

        }

        .order-table th {

            background: #f7f4ef;

            color: #777;

            font-size: 1.1rem;

            padding: 1.3rem;

            text-align: left;

            white-space: nowrap;

        }

        .order-table td {

            padding: 1.4rem 1.3rem;

            border-bottom: .1rem solid #eee;

            font-size: 1.3rem;

            color: #444;

        }

        .order-id {

            font-weight: 600;

            color: var(--main-color);

        }

        .order-status {

            display: inline-flex;

            padding: .5rem 1rem;

            border-radius: 5rem;

            font-size: 1rem;

            border: .1rem solid currentColor;

            white-space: nowrap;

        }

        .order-status.selesai {

            color: #527853;

            background: #edf7ee;

        }

        .order-status.menunggu,
        .order-status.diproses {

            color: #c68b3c;

            background: #fff7e8;

        }

        .order-status.dikirim {

            color: #557a95;

            background: #edf5fc;

        }

        .order-status.dibatalkan {

            color: #a94442;

            background: #fceeee;

        }

        .detail-btn {

            display: inline-flex;

            align-items: center;

            gap: .5rem;

            padding: .8rem 1.2rem;

            border: .1rem solid #ddd;

            border-radius: .8rem;

            background: #fff;

            color: var(--main-color);

            font-size: 1.1rem;

            text-decoration: none;

            white-space: nowrap;

        }

        .detail-btn:hover {

            background: #f5f1eb;

            border-color: var(--main-color);

        }

        /* =====================================================
           EMPTY
        ===================================================== */

        .empty-box {

            text-align: center;

            padding: 4rem 2rem;

        }

        .empty-box i {

            font-size: 4rem;

            color: #c8b9aa;

            margin-bottom: 1.5rem;

        }

        .empty-box h3 {

            font-size: 1.9rem;

            color: #333;

            margin-bottom: .7rem;

        }

        .empty-box p {

            color: #888;

            font-size: 1.3rem;

            margin-bottom: 1.5rem;

            line-height: 1.7;

        }

        /* =====================================================
           REWARD
        ===================================================== */

        .reward-box {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 1.5rem;

        }

        .reward-item {

            padding: 2rem;

            background: #faf9f5;

            border-radius: 1.2rem;

            text-align: center;

        }

        .reward-item i {

            font-size: 2.5rem;

            color: #c68b3c;

            margin-bottom: 1rem;

        }

        .reward-item h3 {

            font-size: 2rem;

            color: #2d211b;

        }

        .reward-item p {

            font-size: 1.1rem;

            color: #888;

        }

        /* =====================================================
           MOBILE MENU
        ===================================================== */

        #menu-btn {
            display: none;
        }

        /* =====================================================
           RESPONSIVE 1100PX
        ===================================================== */

        @media (max-width: 1100px) {

            .account-page {
                padding: 7rem 5% 4rem;
            }

            .account-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .account-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .account-card.full {
                grid-column: auto;
            }

        }

        /* =====================================================
           RESPONSIVE 768PX
        ===================================================== */

        @media (max-width: 768px) {

            /* HEADER */

            .header {
                padding: 1.5rem 5%;
            }

            .header .logo {
                font-size: 1.8rem;
            }

            .header .user-box {
                gap: .8rem;
                margin-left: auto;
            }

            .user-data {
                display: none;
            }

            .cart-link {
                width: 4.2rem;
                height: 4.2rem;
                font-size: 1.6rem;
            }

            .user-profile-link {
                width: 4.2rem;
                height: 4.2rem;
            }

            /* NAVBAR */

            #menu-btn {
                display: block;
                font-size: 2.2rem;
                cursor: pointer;
                margin-left: 1rem;
                color: var(--main-color);
            }

            .navbar {

                position: absolute;

                top: 100%;
                left: 0;
                right: 0;

                background: #fff;

                border-top: .1rem solid #eee;
                border-bottom: .1rem solid #eee;

                padding: 1rem 5%;

                display: none;

                flex-direction: column;

                box-shadow:
                    0 1rem 2rem rgba(0, 0, 0, .08);

                z-index: 999;

            }

            .navbar.active {
                display: flex;
            }

            .navbar a {

                width: 100%;

                padding: 1.3rem 1rem;

                border-bottom: .1rem solid #f0eee9;

                font-size: 1.4rem;

            }

            .navbar a:last-child {
                border-bottom: none;
            }

            /* ACCOUNT */

            .account-page {
                padding: 6rem 4% 4rem;
            }

            .account-header {
                margin-bottom: 2.8rem;
            }

            .account-label {
                font-size: 1.1rem;
                padding: .6rem 1.3rem;
            }

            .account-header h1 {
                font-size: 2.7rem;
            }

            .account-header p {
                font-size: 1.3rem;
            }

            /* STATISTIK */

            .account-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.2rem;
                margin-bottom: 2rem;
            }

            .account-stat {
                padding: 1.5rem;
                gap: 1rem;
                border-radius: 1.3rem;
            }

            .stat-icon {
                width: 4.2rem;
                height: 4.2rem;
                border-radius: 1rem;
                font-size: 1.6rem;
            }

            .account-stat h3 {
                font-size: 1.6rem;
            }

            .account-stat p {
                font-size: 1rem;
            }

            /* CARD */

            .account-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .account-card {
                padding: 1.8rem;
                border-radius: 1.5rem;
            }

            .account-card.full {
                grid-column: auto;
            }

            .card-title {
                margin-bottom: 1.8rem;
                padding-bottom: 1.2rem;
            }

            .card-title-icon {
                width: 3.6rem;
                height: 3.6rem;
                border-radius: .9rem;
                font-size: 1.4rem;
            }

            .card-title h2 {
                font-size: 1.6rem;
            }

            .card-title p {
                font-size: 1rem;
            }

            /* PROFILE */

            .profile-box {
                flex-direction: column;
                text-align: center;
                gap: 1.3rem;
            }

            .profile-avatar {
                width: 8.5rem;
                height: 8.5rem;
            }

            .profile-info h2 {
                font-size: 1.9rem;
            }

            .profile-info .username {
                font-size: 1.2rem;
            }

            .membership {
                font-size: 1rem;
                padding: .5rem 1rem;
            }

            .profile-details {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin-top: 2rem;
            }

            .detail-item {
                padding: 1.2rem;
            }

            .detail-item label {
                font-size: 1rem;
            }

            .detail-item p {
                font-size: 1.2rem;
            }

            /* FORM */

            .form-grid {
                grid-template-columns: 1fr;
                gap: 1.2rem;
            }

            .form-group label {
                font-size: 1.1rem;
            }

            .form-group input {
                padding: 1.1rem 1.2rem;
                font-size: 1.2rem;
            }

            .form-actions {
                margin-top: 1.5rem;
            }

            .account-btn {
                width: 100%;
                padding: 1.1rem 1.5rem;
                font-size: 1.2rem;
            }

            /* REWARD */

            .reward-box {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }

            .reward-item {
                padding: 1.5rem 1rem;
            }

            .reward-item i {
                font-size: 2rem;
            }

            .reward-item h3 {
                font-size: 1.7rem;
            }

            .reward-item p {
                font-size: 1rem;
            }

            /* TABLE */

            .table-wrapper {
                margin: 0 -.5rem;
                width: calc(100% + 1rem);
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .order-table {
                min-width: 650px;
            }

            .order-table th,
            .order-table td {
                padding: 1.1rem 1rem;
                font-size: 1.1rem;
            }

            .detail-btn {
                padding: .7rem 1rem;
                font-size: 1rem;
            }

            /* ALERT */

            .account-alert {
                padding: 1.2rem 1.4rem;
                font-size: 1.1rem;
                align-items: flex-start;
                line-height: 1.6;
            }

            /* EMPTY */

            .empty-box {
                padding: 3rem 1rem;
            }

            .empty-box i {
                font-size: 3.5rem;
            }

            .empty-box h3 {
                font-size: 1.6rem;
            }

            .empty-box p {
                font-size: 1.2rem;
            }

            /* FOOTER */

            .footer .box-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 2rem;
            }

        }

        /* =====================================================
           RESPONSIVE 500PX
        ===================================================== */

        @media (max-width: 500px) {

            .header {
                padding: 1.2rem 4%;
            }

            .header .logo {
                font-size: 1.6rem;
            }

            .cart-link {
                width: 3.8rem;
                height: 3.8rem;
            }

            .user-profile-link {
                width: 3.8rem;
                height: 3.8rem;
            }

            #menu-btn {
                font-size: 2rem;
            }

            .cart-link .count {

                min-width: 1.8rem;
                height: 1.8rem;

                font-size: .9rem;

                top: -.3rem;
                right: -.3rem;

            }

            /* ACCOUNT */

            .account-page {
                padding: 5rem 4% 3rem;
            }

            .account-header h1 {
                font-size: 2.3rem;
            }

            .account-header p {
                font-size: 1.15rem;
            }

            .account-label {
                font-size: 1rem;
            }

            /* STATISTIK */

            .account-stats {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .account-stat {
                padding: 1.4rem;
            }

            .stat-icon {
                width: 4rem;
                height: 4rem;
            }

            .account-stat h3 {
                font-size: 1.5rem;
            }

            /* CARD */

            .account-card {
                padding: 1.5rem;
                border-radius: 1.3rem;
            }

            .card-title {
                gap: .8rem;
            }

            .card-title h2 {
                font-size: 1.5rem;
            }

            /* PROFILE */

            .profile-avatar {
                width: 8rem;
                height: 8rem;
            }

            .profile-info h2 {
                font-size: 1.7rem;
            }

            /* REWARD */

            .reward-box {
                grid-template-columns: 1fr;
            }

            .reward-item {
                padding: 1.5rem;
            }

            /* FORM */

            .form-group input {
                font-size: 1.1rem;
            }

            /* TABLE */

            .order-table {
                min-width: 620px;
            }

            /* FOOTER */

            .footer .box-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer .box a {
                justify-content: center;
            }

            .footer .credit {
                font-size: 1rem;
                line-height: 1.7;
                padding: 1.5rem 1rem;
            }

        }

        /* =====================================================
           RESPONSIVE 360PX
        ===================================================== */

        @media (max-width: 360px) {

            .header .logo {
                font-size: 1.4rem;
            }

            .cart-link {
                width: 3.5rem;
                height: 3.5rem;
            }

            .user-profile-link {
                width: 3.5rem;
                height: 3.5rem;
            }

            #menu-btn {
                font-size: 1.8rem;
            }

            .account-page {
                padding-left: 3%;
                padding-right: 3%;
            }

            .account-card {
                padding: 1.3rem;
            }

            .account-header h1 {
                font-size: 2rem;
            }

            .account-stat {
                padding: 1.2rem;
            }

        }
    </style>

</head>

<body>

    <!-- =====================================================
     HEADER
===================================================== -->

    <header class="header">

        <a
            href="index.php"
            class="logo">

            <span class="toku-logo-text">
                TOKU <span>COFFEE</span>
            </span>

        </a>

        <nav class="navbar">

            <a href="index.php">
                beranda
            </a>

            <a href="index.php#menu">
                menu
            </a>

            <a href="index.php#about">
                tentang
            </a>

            <a href="index.php#footer">
                kontak
            </a>

        </nav>

        <div class="user-box">

            <a
                href="keranjang.php"
                class="cart-link"
                title="Keranjang Saya">

                <i class="fas fa-shopping-bag"></i>

                <span
                    class="count"
                    id="cartCount"
                    style="<?= $jumlahKeranjang > 0 ? '' : 'display:none;' ?>">

                    <?= $jumlahKeranjang ?>

                </span>

            </a>


            <div class="user-info">

                <!-- AVATAR -->
                <div class="user-avatar-wrapper">

                    <button
                        type="button"
                        class="user-profile-btn"
                        id="userProfileBtn">

                        <img
                            src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff"
                            alt="Profil">

                    </button>


                    <!-- DROPDOWN -->
                    <div
                        class="user-dropdown"
                        id="userDropdown">

                        <a href="akun-pelanggan.php">
                            <i class="fas fa-user"></i>
                            Akun Saya
                        </a>

                        <a
                            href="../login/logout.php"
                            class="logout-link">

                            <i class="fas fa-sign-out-alt"></i>
                            Logout

                        </a>

                    </div>

                </div>


                <!-- DATA USER -->
                <div class="user-data">

                    <h4>
                        <?= e($namaUser) ?>
                    </h4>

                    <a href="akun-pelanggan.php">

                        <i class="fas fa-user"></i>

                        Akun Saya

                    </a>

                </div>

            </div>

        </div>

        <div
            id="menu-btn"
            class="fas fa-bars">
        </div>

    </header>


    <!-- =====================================================
     ACCOUNT
===================================================== -->

    <section class="account-page">

        <div class="account-header">

            <div class="account-label">

                <i class="fas fa-user-circle"></i>

                AKUN PELANGGAN

            </div>

            <h1>

                Akun Saya
                <span>Toku Coffee</span>

            </h1>

            <p>

                Kelola informasi akun dan lihat aktivitas
                belanja kamu di Toku Coffee.

            </p>

        </div>


        <!-- ALERT -->

        <?php if ($pesanSukses): ?>

            <div class="account-alert success">

                <i class="fas fa-circle-check"></i>

                <?= e($pesanSukses) ?>

            </div>

        <?php endif; ?>


        <?php if ($pesanError): ?>

            <div class="account-alert error">

                <i class="fas fa-circle-exclamation"></i>

                <?= e($pesanError) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
         STATISTIK
    ================================================== -->

        <div class="account-stats">

            <div class="account-stat">

                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>

                <div>

                    <h3>
                        <?= $totalPesanan ?>
                    </h3>

                    <p>
                        Total Pesanan
                    </p>

                </div>

            </div>


            <div class="account-stat">

                <div class="stat-icon">
                    <i class="fas fa-wallet"></i>
                </div>

                <div>

                    <h3 style="font-size:1.5rem;">
                        <?= rupiah($totalBelanja) ?>
                    </h3>

                    <p>
                        Total Belanja
                    </p>

                </div>

            </div>


            <div class="account-stat">

                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>

                <div>

                    <h3>
                        <?= number_format(
                            $poin,
                            0,
                            ',',
                            '.'
                        ) ?>
                    </h3>

                    <p>
                        Poin Reward
                    </p>

                </div>

            </div>


            <div class="account-stat">

                <div class="stat-icon">
                    <i class="fas fa-crown"></i>
                </div>

                <div>

                    <h3>
                        <?= e($level) ?>
                    </h3>

                    <p>
                        Membership
                    </p>

                </div>

            </div>

        </div>


        <!-- =================================================
         ACCOUNT GRID
    ================================================== -->

        <div class="account-grid">


            <!-- PROFIL -->

            <div class="account-card">

                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fas fa-user"></i>

                    </div>

                    <div>

                        <h2>
                            Profil Saya
                        </h2>

                        <p>
                            Informasi akun pelanggan
                        </p>

                    </div>

                </div>


                <div class="profile-box">

                    <div class="profile-avatar">

                        <img
                            src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff"
                            alt="Avatar">

                        <span class="online-dot"></span>

                    </div>


                    <div class="profile-info">

                        <h2>
                            <?= e($namaUser) ?>
                        </h2>

                        <div class="username">
                            @<?= e($username) ?>
                        </div>

                        <span class="membership">

                            <i class="fas fa-crown"></i>

                            <?= e($level) ?>

                        </span>

                    </div>

                </div>


                <div class="profile-details">

                    <div class="detail-item">

                        <label>
                            Email
                        </label>

                        <p>

                            <i class="fas fa-envelope"></i>

                            <?= e($emailUser) ?>

                        </p>

                    </div>


                    <div class="detail-item">

                        <label>
                            Status
                        </label>

                        <p>

                            <i class="fas fa-circle-check"></i>

                            <?= e($statusUser) ?>

                        </p>

                    </div>


                    <div class="detail-item">

                        <label>
                            Pesanan Aktif
                        </label>

                        <p>

                            <i class="fas fa-truck"></i>

                            <?= $pesananAktif ?>
                            pesanan

                        </p>

                    </div>


                    <div class="detail-item">

                        <label>
                            Pesanan Selesai
                        </label>

                        <p>

                            <i class="fas fa-check"></i>

                            <?= $pesananSelesai ?>
                            pesanan

                        </p>

                    </div>

                </div>

            </div>


            <!-- REWARD -->

            <div
                class="account-card"
                id="reward">

                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fas fa-gift"></i>

                    </div>

                    <div>

                        <h2>
                            Reward Saya
                        </h2>

                        <p>
                            Membership dan poin pelanggan
                        </p>

                    </div>

                </div>


                <div class="reward-box">

                    <div class="reward-item">

                        <i class="fas fa-crown"></i>

                        <h3>
                            <?= e($level) ?>
                        </h3>

                        <p>
                            Level Membership
                        </p>

                    </div>


                    <div class="reward-item">

                        <i class="fas fa-coins"></i>

                        <h3>

                            <?= number_format(
                                $poin,
                                0,
                                ',',
                                '.'
                            ) ?>

                        </h3>

                        <p>
                            Total Poin
                        </p>

                    </div>

                </div>


                <div style="
                margin-top:2rem;
                padding:1.5rem;
                background:#faf9f5;
                border-radius:1rem;
                font-size:1.2rem;
                color:#777;
                line-height:1.7;
            ">

                    <i
                        class="fas fa-circle-info"
                        style="color:var(--main-color);">
                    </i>

                    Setiap pembelian sebesar
                    <strong>Rp 10.000</strong>
                    mendapatkan
                    <strong>1 poin</strong>
                    reward.

                </div>

            </div>


            <!-- EDIT PROFIL -->

            <div
                class="account-card"
                id="edit-profil">

                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fas fa-user-pen"></i>

                    </div>

                    <div>

                        <h2>
                            Edit Profil
                        </h2>

                        <p>
                            Perbarui informasi akun
                        </p>

                    </div>

                </div>


                <form method="POST">

                    <div class="form-grid">

                        <div class="form-group">

                            <label>
                                Nama Lengkap
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                value="<?= e(
                                            $user['full_name']
                                        ) ?>"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                value="<?= e(
                                            $user['email']
                                        ) ?>">

                        </div>


                        <div class="form-group">

                            <label>
                                Username
                            </label>

                            <input
                                type="text"
                                value="<?= e(
                                            $user['username']
                                        ) ?>"
                                readonly>

                        </div>


                        <div class="form-group">

                            <label>
                                Role
                            </label>

                            <input
                                type="text"
                                value="Customer / Pelanggan"
                                readonly>

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            name="update_profile"
                            class="account-btn">

                            <i class="fas fa-save"></i>

                            Simpan Perubahan

                        </button>

                    </div>

                </form>

            </div>


            <!-- PASSWORD -->

            <div
                class="account-card"
                id="password">

                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fas fa-lock"></i>

                    </div>

                    <div>

                        <h2>
                            Keamanan
                        </h2>

                        <p>
                            Ubah password akun
                        </p>

                    </div>

                </div>


                <form method="POST">

                    <div class="form-grid">

                        <div class="form-group">

                            <label>
                                Password Lama
                            </label>

                            <input
                                type="password"
                                name="password_lama"
                                placeholder="Masukkan password lama"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Password Baru
                            </label>

                            <input
                                type="password"
                                name="password_baru"
                                minlength="6"
                                placeholder="Minimal 6 karakter"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Konfirmasi Password
                            </label>

                            <input
                                type="password"
                                name="konfirmasi_password"
                                minlength="6"
                                placeholder="Ulangi password baru"
                                required>

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            name="update_password"
                            class="account-btn">

                            <i class="fas fa-lock"></i>

                            Ubah Password

                        </button>

                    </div>

                </form>

            </div>


            <!-- RIWAYAT PESANAN -->

            <div
                class="account-card full"
                id="riwayat">

                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fas fa-receipt"></i>

                    </div>

                    <div>

                        <h2>
                            Pesanan Saya
                        </h2>

                        <p>
                            Riwayat transaksi pelanggan
                        </p>

                    </div>

                </div>


                <?php if (empty($orders)): ?>

                    <div class="empty-box">

                        <i class="fas fa-box-open"></i>

                        <h3>
                            Belum Ada Pesanan
                        </h3>

                        <p>
                            Kamu belum memiliki riwayat
                            pesanan di Toku Coffee.
                        </p>

                        <a
                            href="produk.php"
                            class="account-btn">

                            <i class="fas fa-mug-hot"></i>

                            Mulai Belanja

                        </a>

                    </div>

                <?php else: ?>


                    <div class="table-wrapper">

                        <table class="order-table">

                            <thead>

                                <tr>

                                    <th>
                                        Pesanan
                                    </th>

                                    <th>
                                        Tanggal
                                    </th>

                                    <th>
                                        Total
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Aksi
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $orders
                                    as $order
                                ): ?>

                                    <tr>

                                        <td>

                                            <span class="order-id">

                                                ORD-<?= str_pad(
                                                        $order['id'],
                                                        4,
                                                        '0',
                                                        STR_PAD_LEFT
                                                    ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= e(
                                                $order['tanggal_pesanan']
                                            ) ?>

                                        </td>


                                        <td>

                                            <strong>

                                                <?= rupiah(
                                                    $order['total']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <span
                                                class="order-status <?= e(
                                                                        statusClass(
                                                                            $order['status']
                                                                        )
                                                                    ) ?>">

                                                <?= e(
                                                    $order['status']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <a
                                                href="detail-pesanan.php?id=<?= (int)$order['id'] ?>"
                                                class="detail-btn">

                                                <i class="fas fa-eye"></i>

                                                Detail

                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>


            <!-- WISHLIST -->

            <div
                class="account-card full"
                id="wishlist">

                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="far fa-heart"></i>

                    </div>

                    <div>

                        <h2>
                            Wishlist
                        </h2>

                        <p>
                            Produk favorit kamu
                        </p>

                    </div>

                </div>


                <div class="empty-box">

                    <i class="far fa-heart"></i>

                    <h3>
                        Wishlist
                    </h3>

                    <p>
                        Produk favorit pelanggan
                        dapat ditampilkan di sini.
                    </p>

                    <a
                        href="produk.php"
                        class="account-btn account-btn-light">

                        <i class="fas fa-mug-hot"></i>

                        Lihat Menu

                    </a>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
     FOOTER
===================================================== -->

    <section
        class="footer"
        id="footer">

        <div class="box-container">


            <div class="box">

                <h3>
                    Toku Coffee
                </h3>

                <a href="index.php">
                    beranda
                </a>

                <a href="produk.php">
                    menu
                </a>

                <a href="index.php#about">
                    tentang kami
                </a>

            </div>


            <div class="box">

                <h3>
                    akun
                </h3>

                <a href="akun-pelanggan.php">

                    <i class="fas fa-user"></i>

                    Akun Saya

                </a>

                <a href="akun-pelanggan.php#riwayat">

                    <i class="fas fa-receipt"></i>

                    Pesanan Saya

                </a>

                <a href="akun-pelanggan.php#wishlist">

                    <i class="fas fa-heart"></i>

                    Wishlist

                </a>

                <a href="akun-pelanggan.php#reward">

                    <i class="fas fa-gift"></i>

                    Reward

                </a>

            </div>


            <div class="box">

                <h3>
                    menu
                </h3>

                <a href="produk.php">

                    <i class="fas fa-mug-hot"></i>

                    Semua Menu

                </a>

                <a href="produk.php">

                    <i class="fas fa-coffee"></i>

                    Kopi

                </a>

                <a href="produk.php">

                    <i class="fas fa-glass-water"></i>

                    Non-Kopi

                </a>

                <a href="keranjang.php">

                    <i class="fas fa-shopping-bag"></i>

                    Keranjang
                    (<?= $jumlahKeranjang ?>)

                </a>

            </div>


            <div class="box">

                <h3>
                    hubungi kami
                </h3>

                <a href="tel:+6281200000000">

                    <i class="fas fa-phone"></i>

                    +62 812-0000-0000

                </a>

                <a href="mailto:hallo@tokucoffee.id">

                    <i class="fas fa-envelope"></i>

                    hallo@tokucoffee.id

                </a>

            </div>

        </div>


        <div class="credit">

            &copy;

            <?= date('Y') ?>

            <span>
                Toku Coffee
            </span>

            | seluruh hak cipta dilindungi

        </div>

    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const profileBtn = document.getElementById('userProfileBtn');
            const dropdown = document.getElementById('userDropdown');

            if (profileBtn && dropdown) {

                profileBtn.addEventListener('click', function(e) {
                    e.stopPropagation();

                    dropdown.classList.toggle('active');
                });

                document.addEventListener('click', function() {
                    dropdown.classList.remove('active');
                });

                dropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

        });
    </script>
    <!-- =====================================================
     JAVASCRIPT
===================================================== -->

    <script>
        const menuBtn =
            document.querySelector('#menu-btn');

        const navbar =
            document.querySelector('.navbar');


        if (
            menuBtn &&
            navbar
        ) {

            menuBtn.onclick = () => {

                navbar.classList.toggle(
                    'active'
                );

                menuBtn.classList.toggle(
                    'fa-times'
                );

            };


            window.addEventListener(
                'scroll',
                () => {

                    navbar.classList.remove(
                        'active'
                    );

                    menuBtn.classList.remove(
                        'fa-times'
                    );

                }
            );


            document.querySelectorAll(
                '.navbar a'
            ).forEach(link => {

                link.addEventListener(
                    'click',
                    () => {

                        navbar.classList.remove(
                            'active'
                        );

                        menuBtn.classList.remove(
                            'fa-times'
                        );

                    }
                );

            });

        }
    </script>


</body>

</html>
