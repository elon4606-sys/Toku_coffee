<?php

require_once "config/koneksi.php";
require_once "config/session.php";
require_once "config/notifikasi.php";

requireRole(['admin', 'staff']);


/* =========================================================
   HELPER
========================================================= */

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

function timeAgo($datetime)
{
    $time = strtotime($datetime);

    if (!$time) {
        return '-';
    }

    $diff = time() - $time;

    if ($diff < 60) {
        return 'Baru saja';
    }

    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }

    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }

    if ($diff < 604800) {
        return floor($diff / 86400) . ' hari lalu';
    }

    return date('d M Y, H:i', $time);
}

function typeLabel($type)
{
    $labels = [
        'order'    => 'Pesanan',
        'stock'    => 'Inventory',
        'payment'  => 'Pembayaran',
        'customer' => 'Pelanggan',
        'supplier' => 'Supplier',
        'finance'  => 'Keuangan',
        'system'   => 'Sistem'
    ];

    return $labels[$type] ?? 'Sistem';
}

function typeClass($type)
{
    $allowed = [
        'order',
        'stock',
        'payment',
        'customer',
        'supplier',
        'finance',
        'system'
    ];

    return in_array($type, $allowed, true)
        ? $type
        : 'system';
}


/* =========================================================
   USER
========================================================= */

$currentUserId = getCurrentUserId($conn);

if ($currentUserId <= 0) {
    header("Location: login.php");
    exit;
}

$namaUser = $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Admin ERP';

$roleUser = $_SESSION['role']
    ?? 'Administrator';

$avatarName = urlencode($namaUser);


/* =========================================================
   CSRF
========================================================= */

if (empty($_SESSION['csrf_notifications'])) {
    $_SESSION['csrf_notifications'] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_notifications'];


/* =========================================================
   FILTER
========================================================= */

$filter = $_GET['filter'] ?? 'all';

$allowedFilters = [
    'all',
    'unread',
    'order',
    'stock',
    'payment',
    'customer',
    'supplier',
    'finance',
    'system'
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}


/* =========================================================
   BACKEND ACTION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        empty($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        die('Token keamanan tidak valid.');
    }

    $action = $_POST['action'] ?? '';


    /* =====================================================
       MARK READ
    ===================================================== */

    if ($action === 'mark_read') {

        $notificationId =
            (int)($_POST['notification_id'] ?? 0);

        if ($notificationId > 0) {

            $stmt = $conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                AND user_id = ?
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ii",
                    $notificationId,
                    $currentUserId
                );

                $stmt->execute();
                $stmt->close();
            }
        }
    }


    /* =====================================================
       MARK ALL READ
    ===================================================== */ elseif ($action === 'mark_all_read') {

        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
            AND is_read = 0
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $currentUserId
            );

            $stmt->execute();
            $stmt->close();
        }
    }


    /* =====================================================
       DELETE ONE
    ===================================================== */ elseif ($action === 'delete') {

        $notificationId =
            (int)($_POST['notification_id'] ?? 0);

        if ($notificationId > 0) {

            $stmt = $conn->prepare("
                DELETE FROM notifications
                WHERE id = ?
                AND user_id = ?
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ii",
                    $notificationId,
                    $currentUserId
                );

                $stmt->execute();
                $stmt->close();
            }
        }
    }


    /* =====================================================
       DELETE READ
    ===================================================== */ elseif ($action === 'delete_read') {

        $stmt = $conn->prepare("
            DELETE FROM notifications
            WHERE user_id = ?
            AND is_read = 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $currentUserId
            );

            $stmt->execute();
            $stmt->close();
        }
    }


    /* =====================================================
       REDIRECT
    ===================================================== */

    header(
        "Location: notifications.php?filter="
            . urlencode($filter)
    );

    exit;
}


/* =========================================================
   STATISTICS
========================================================= */

$unreadCount = getUnreadNotificationCount(
    $conn,
    $currentUserId
);


/* =========================================================
   TOTAL NOTIFICATION
========================================================= */

$stmtTotal = $conn->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
");

$totalNotifications = 0;

if ($stmtTotal) {

    $stmtTotal->bind_param(
        "i",
        $currentUserId
    );

    $stmtTotal->execute();

    $stmtTotal->bind_result(
        $totalNotifications
    );

    $stmtTotal->fetch();

    $stmtTotal->close();
}

$totalNotifications = (int)$totalNotifications;


/* =========================================================
   TODAY
========================================================= */

$stmtToday = $conn->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
    AND DATE(created_at) = CURDATE()
");

$todayNotifications = 0;

if ($stmtToday) {

    $stmtToday->bind_param(
        "i",
        $currentUserId
    );

    $stmtToday->execute();

    $stmtToday->bind_result(
        $todayNotifications
    );

    $stmtToday->fetch();

    $stmtToday->close();
}

$todayNotifications = (int)$todayNotifications;


/* =========================================================
   QUERY NOTIFICATIONS
========================================================= */

$sql = "
    SELECT
        id,
        title,
        message,
        type,
        icon,
        link,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
";

if ($filter === 'unread') {

    $sql .= "
        AND is_read = 0
    ";
} elseif ($filter !== 'all') {

    $sql .= "
        AND type = ?
    ";
}

$sql .= "
    ORDER BY
        is_read ASC,
        created_at DESC
    LIMIT 100
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    die("
        <div style='
            font-family:Arial,sans-serif;
            padding:30px;
            background:#fff5f4;
            color:#a94442;
            border:1px solid #e7caca;
            margin:30px;
            border-radius:15px;
        '>

            <h2>Query Notifikasi Gagal</h2>

            <p>
                <strong>Error MySQL:</strong>
            </p>

            <pre style='
                white-space:pre-wrap;
                background:#fff;
                padding:15px;
                border-radius:10px;
                color:#333;
            >"
        . e($conn->error) .
        "</pre>

            <p>
                Periksa tabel
                <strong>notifications</strong>
                pada database.
            </p>

        </div>
    ");
}


/* =========================================================
   BIND PARAMETER
========================================================= */

if (
    $filter === 'unread' ||
    $filter === 'all'
) {

    $stmt->bind_param(
        "i",
        $currentUserId
    );
} else {

    $stmt->bind_param(
        "is",
        $currentUserId,
        $filter
    );
}


/* =========================================================
   EXECUTE
========================================================= */

if (!$stmt->execute()) {

    die("Gagal menjalankan query: "
        . e($stmt->error));
}


$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Notifikasi | Toku Coffee ERP
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
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
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
            --bg: #faf9f5;
            --white: #fff;

            --green: #527853;
            --orange: #c68b3c;
            --red: #a94442;
            --blue: #557a95;
            --purple: #8064a2;

            --gray: #888;
            --text: #665;
            --border: #e5e2da;
            --light: #f3f0e8;

            --shadow:
                0 1rem 3rem rgba(68, 68, 51, .08);
        }


        /* =====================================================
           RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }


        html {
            font-size: 62.5%;
            scroll-behavior: smooth;
        }


        body {
            background: var(--bg);
            color: var(--main-color);
            min-height: 100vh;
        }


        a {
            text-decoration: none;
            color: inherit;
        }


        button,
        input {
            font-family: inherit;
        }


        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            width: 100%;
            min-height: 100vh;
        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {

            width: 100%;
            height: 8rem;

            background: white;

            border-bottom: .15rem solid #eee;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 4rem;

            position: sticky;

            top: 0;

            z-index: 500;
        }


        .topbar-left {

            display: flex;

            align-items: center;

            gap: 1.5rem;
        }


        .page-title {

            font-size: 2rem;

            font-weight: 600;
        }


        .page-title span {

            color: var(--gray);

            font-size: 1.1rem;

            display: block;

            font-weight: 400;

            margin-top: .2rem;
        }


        /* =====================================================
           TOPBAR RIGHT
        ===================================================== */

        .topbar-right {

            display: flex;

            align-items: center;

            gap: 2rem;
        }


        /* =====================================================
           NOTIFICATION BUTTON
        ===================================================== */

        .notification-btn {

            width: 4.5rem;
            height: 4.5rem;

            border: .15rem solid #ddd;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            position: relative;

            transition: .3s;
        }


        .notification-btn:hover {

            border-color: var(--main-color);

            transform: translateY(-.2rem);
        }


        .notification-btn i {

            font-size: 1.7rem;
        }


        .notification-count {

            position: absolute;

            top: -.5rem;
            right: -.3rem;

            min-width: 2rem;
            height: 2rem;

            padding: 0 .5rem;

            background: var(--red);

            color: white;

            border-radius: 2rem;

            font-size: .85rem;

            display: flex;

            align-items: center;

            justify-content: center;

            border: .15rem solid white;
        }


        /* =====================================================
           PROFILE
        ===================================================== */

        .profile {

            display: flex;

            align-items: center;

            gap: 1rem;
        }


        .profile img {

            width: 4.5rem;
            height: 4.5rem;

            border-radius: 50%;

            border: .2rem solid var(--main-color);
        }


        .profile-info strong {

            display: block;

            font-size: 1.2rem;
        }


        .profile-info span {

            color: var(--gray);

            font-size: 1rem;
        }


        /* =====================================================
           CONTENT
        ===================================================== */

        .content {

            width: 100%;

            max-width: 160rem;

            margin: 0 auto;

            padding: 3rem 4rem 5rem;
        }


        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .page-header {

            background: var(--white);

            border: .2rem solid var(--main-color);

            border-radius:
                95% 4% 97% 5% / 4% 94% 3% 95%;

            padding: 2.8rem;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 2rem;

            margin-bottom: 2rem;

            animation: fadeUp .5s ease;
        }


        .page-header-left {

            display: flex;

            align-items: center;

            gap: 1.5rem;

            min-width: 0;
        }


        .page-header-icon {

            width: 6rem;
            height: 6rem;

            background: var(--main-color);

            color: white;

            border-radius:
                95% 5% 95% 5% / 5% 95% 5% 95%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 2.4rem;

            flex-shrink: 0;
        }


        .page-header h1 {

            font-size: 2.5rem;

            margin-bottom: .4rem;
        }


        .page-header p {

            color: var(--gray);

            font-size: 1.15rem;

            line-height: 1.6;
        }


        /* =====================================================
           BACK TO DASHBOARD
        ===================================================== */

        .back-dashboard {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: .8rem;

            padding: 1rem 1.5rem;

            background: var(--main-color);

            color: white;

            border: .15rem solid var(--main-color);

            border-radius:
                95% 5% 95% 5% / 5% 95% 5% 95%;

            font-size: 1rem;

            font-weight: 500;

            white-space: nowrap;

            transition: .3s ease;

            flex-shrink: 0;
        }


        .back-dashboard i {

            font-size: 1.2rem;

            transition: .3s ease;
        }


        .back-dashboard:hover {

            background: white;

            color: var(--main-color);

            transform: translateX(-.3rem);

            box-shadow: var(--shadow);
        }


        .back-dashboard:hover i {

            transform: translateX(-.3rem);
        }


        /* =====================================================
           STATISTICS
        ===================================================== */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 1.5rem;

            margin-bottom: 2rem;
        }


        .stat-card {

            background: white;

            border: .15rem solid var(--border);

            border-radius:
                95% 4% 97% 5% / 4% 94% 3% 95%;

            padding: 1.8rem;

            display: flex;

            align-items: center;

            gap: 1.5rem;

            transition: .3s;

            animation: cardAppear .5s ease;
        }


        .stat-card:hover {

            border-color: var(--main-color);

            transform: translateY(-.3rem);

            box-shadow: var(--shadow);
        }


        .stat-icon {

            width: 4.8rem;
            height: 4.8rem;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 1.7rem;

            flex-shrink: 0;
        }


        .stat-icon.total {

            background: #f3f0e8;

            color: var(--main-color);
        }


        .stat-icon.unread {

            background: #fff0ef;

            color: var(--red);
        }


        .stat-icon.today {

            background: #edf4f8;

            color: var(--blue);
        }


        .stat-info span {

            color: var(--gray);

            font-size: 1rem;

            display: block;

            margin-bottom: .2rem;
        }


        .stat-info strong {

            font-size: 2rem;

            display: block;
        }


        /* =====================================================
           TOOLBAR
        ===================================================== */

        .toolbar {

            background: white;

            border: .15rem solid var(--border);

            border-radius:
                95% 4% 97% 5% / 4% 94% 3% 95%;

            padding: 1.5rem;

            margin-bottom: 2rem;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 1.5rem;

            flex-wrap: wrap;
        }


        /* =====================================================
           FILTER
        ===================================================== */

        .filters {

            display: flex;

            align-items: center;

            gap: .7rem;

            flex-wrap: wrap;
        }


        .filter {

            padding: .8rem 1.2rem;

            border: .15rem solid #ddd;

            border-radius: 2rem;

            font-size: 1rem;

            color: var(--text);

            transition: .3s;

            white-space: nowrap;
        }


        .filter:hover {

            border-color: var(--main-color);

            color: var(--main-color);
        }


        .filter.active {

            background: var(--main-color);

            color: white;

            border-color: var(--main-color);
        }


        /* =====================================================
           TOOLBAR ACTIONS
        ===================================================== */

        .toolbar-actions {

            display: flex;

            align-items: center;

            gap: .7rem;

            flex-wrap: wrap;
        }


        .toolbar-actions form {

            margin: 0;
        }


        .btn {

            border: .15rem solid var(--main-color);

            background: white;

            color: var(--main-color);

            padding: .9rem 1.3rem;

            border-radius:
                95% 5% 95% 5% / 5% 95% 5% 95%;

            cursor: pointer;

            transition: .3s;

            font-size: 1rem;

            display: inline-flex;

            align-items: center;

            gap: .6rem;

            white-space: nowrap;
        }


        .btn:hover {

            background: var(--main-color);

            color: white;

            transform: translateY(-.1rem);
        }


        .btn-danger {

            border-color: var(--red);

            color: var(--red);
        }


        .btn-danger:hover {

            background: var(--red);

            color: white;
        }


        /* =====================================================
           SECTION TITLE
        ===================================================== */

        .section-title {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 1.2rem;

            gap: 1rem;
        }


        .section-title h2 {

            font-size: 1.5rem;
        }


        .section-title span {

            color: var(--gray);

            font-size: 1rem;

            text-align: right;
        }


        /* =====================================================
           NOTIFICATION LIST
        ===================================================== */

        .notification-list {

            display: flex;

            flex-direction: column;

            gap: 1rem;
        }


        /* =====================================================
           NOTIFICATION CARD
        ===================================================== */

        .notification-card {

            background: white;

            border: .15rem solid var(--border);

            border-radius:
                95% 4% 97% 5% / 4% 94% 3% 95%;

            padding: 1.7rem;

            display: flex;

            align-items: flex-start;

            gap: 1.5rem;

            transition: .3s;

            animation: cardAppear .45s ease;
        }


        .notification-card:hover {

            border-color: var(--main-color);

            box-shadow: var(--shadow);

            transform: translateY(-.2rem);
        }


        /* =====================================================
           UNREAD
        ===================================================== */

        .notification-card.unread {

            border-left: .45rem solid var(--main-color);

            background: #fffdf8;
        }


        /* =====================================================
           NOTIFICATION ICON
        ===================================================== */

        .notification-icon {

            width: 4.8rem;

            height: 4.8rem;

            flex: 0 0 4.8rem;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 1.7rem;
        }


        .notification-icon.order {

            background: #edf4f8;

            color: var(--blue);
        }


        .notification-icon.stock {

            background: #fff5e6;

            color: var(--orange);
        }


        .notification-icon.payment {

            background: #eef7ee;

            color: var(--green);
        }


        .notification-icon.customer {

            background: #f2edf8;

            color: var(--purple);
        }


        .notification-icon.supplier {

            background: #f7efe7;

            color: #9a6842;
        }


        .notification-icon.finance {

            background: #edf7f2;

            color: var(--green);
        }


        .notification-icon.system {

            background: #f2f2ed;

            color: var(--main-color);
        }


        /* =====================================================
           NOTIFICATION BODY
        ===================================================== */

        .notification-body {

            flex: 1;

            min-width: 0;
        }


        .notification-title {

            display: flex;

            align-items: center;

            gap: .7rem;

            flex-wrap: wrap;

            margin-bottom: .5rem;
        }


        .notification-title h3 {

            font-size: 1.3rem;

            font-weight: 600;

            word-break: break-word;
        }


        .unread-dot {

            width: .8rem;

            height: .8rem;

            border-radius: 50%;

            background: var(--red);

            flex-shrink: 0;
        }


        .notification-message {

            color: #666;

            line-height: 1.7;

            font-size: 1.05rem;

            margin-bottom: 1rem;

            word-break: break-word;
        }


        .notification-meta {

            display: flex;

            align-items: center;

            gap: 1rem;

            flex-wrap: wrap;

            color: var(--gray);

            font-size: .9rem;
        }


        .notification-meta i {

            margin-right: .3rem;
        }


        .type-badge {

            padding: .35rem .9rem;

            border-radius: 2rem;

            background: var(--light);

            color: var(--main-color);

            font-size: .85rem;

            font-weight: 500;
        }


        /* =====================================================
           ACTION
        ===================================================== */

        .notification-actions {

            display: flex;

            align-items: center;

            justify-content: flex-end;

            gap: .5rem;

            flex-wrap: wrap;

            flex-shrink: 0;
        }


        .notification-actions form {

            margin: 0;
        }


        .icon-btn {

            width: 3.7rem;

            height: 3.7rem;

            border: .15rem solid #ddd;

            background: white;

            border-radius: 50%;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            transition: .3s;

            color: var(--main-color);
        }


        .icon-btn:hover {

            border-color: var(--main-color);

            transform: scale(1.05);
        }


        .icon-btn.delete {

            color: var(--red);

            border-color: #e7caca;
        }


        .icon-btn.delete:hover {

            background: var(--red);

            color: white;

            border-color: var(--red);
        }


        .open-link {

            padding: .8rem 1.1rem;

            border: .15rem solid var(--main-color);

            border-radius: 2rem;

            font-size: .9rem;

            transition: .3s;

            display: inline-flex;

            align-items: center;

            gap: .5rem;

            white-space: nowrap;
        }


        .open-link:hover {

            background: var(--main-color);

            color: white;
        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            background: white;

            border: .2rem dashed #ccc;

            border-radius:
                95% 4% 97% 5% / 4% 94% 3% 95%;

            padding: 6rem 2rem;

            text-align: center;
        }


        .empty-icon {

            width: 7rem;

            height: 7rem;

            margin: 0 auto 1.5rem;

            border-radius: 50%;

            background: var(--light);

            display: flex;

            align-items: center;

            justify-content: center;
        }


        .empty-icon i {

            font-size: 3rem;

            color: #aaa;
        }


        .empty h3 {

            font-size: 1.5rem;

            margin-bottom: .5rem;
        }


        .empty p {

            color: var(--gray);

            font-size: 1rem;
        }


        /* =====================================================
           ANIMATION
        ===================================================== */

        @keyframes fadeUp {

            from {

                opacity: 0;

                transform: translateY(2rem);
            }

            to {

                opacity: 1;

                transform: translateY(0);
            }
        }


        @keyframes cardAppear {

            from {

                opacity: 0;

                transform: translateY(1rem);
            }

            to {

                opacity: 1;

                transform: translateY(0);
            }
        }


        /* =====================================================
           1200px
        ===================================================== */

        @media(max-width:1200px) {

            .content {

                padding: 2.5rem;
            }


            .topbar {

                padding: 0 2.5rem;
            }

        }


        /* =====================================================
           900px
        ===================================================== */

        @media(max-width:900px) {

            .topbar {

                padding: 0 1.5rem;
            }


            .page-title span {

                display: none;
            }


            .content {

                padding: 2rem 1.5rem;
            }


            .stats {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .page-header {

                align-items: flex-start;

                flex-direction: column;
            }


            .page-header-left {

                width: 100%;
            }


            .back-dashboard {

                width: 100%;
            }


            .notification-card {

                flex-wrap: wrap;
            }


            .notification-actions {

                width: 100%;

                justify-content: flex-end;
            }

        }


        /* =====================================================
           600px
        ===================================================== */

        @media(max-width:600px) {

            html {

                font-size: 55%;
            }


            .topbar {

                height: 7rem;
            }


            .page-title {

                font-size: 1.7rem;
            }


            .profile-info {

                display: none;
            }


            .profile img {

                width: 4rem;

                height: 4rem;
            }


            .content {

                padding: 1.5rem 1rem 3rem;
            }


            .page-header {

                padding: 2rem;
            }


            .page-header-left {

                align-items: flex-start;

                gap: 1rem;
            }


            .page-header-icon {

                width: 5rem;

                height: 5rem;

                flex-shrink: 0;
            }


            .page-header h1 {

                font-size: 2rem;
            }


            .page-header p {

                font-size: 1rem;
            }


            .back-dashboard {

                width: 100%;

                margin-top: .5rem;
            }


            .stats {

                grid-template-columns: 1fr;
            }


            .toolbar {

                align-items: stretch;
            }


            .filters {

                width: 100%;
            }


            .filter {

                flex: 1;

                text-align: center;

                min-width:
                    calc(50% - .5rem);
            }


            .toolbar-actions {

                width: 100%;
            }


            .toolbar-actions form {

                flex: 1;
            }


            .toolbar-actions .btn {

                width: 100%;

                justify-content: center;
            }


            .section-title {

                align-items: flex-start;

                flex-direction: column;
            }


            .section-title span {

                text-align: left;
            }


            .notification-card {

                padding: 1.3rem;

                gap: 1rem;
            }


            .notification-icon {

                width: 4rem;

                height: 4rem;

                flex: 0 0 4rem;
            }


            .notification-actions {

                justify-content: flex-start;

                width: 100%;
            }


            .open-link {

                flex: 1;

                justify-content: center;
            }

        }


        /* =====================================================
           450px
        ===================================================== */

        @media(max-width:450px) {

            .topbar-right {

                gap: .8rem;
            }


            .notification-btn {

                width: 4rem;

                height: 4rem;
            }


            .profile img {

                width: 3.8rem;

                height: 3.8rem;
            }


            .page-header-icon {

                width: 4.5rem;

                height: 4.5rem;

                font-size: 1.8rem;
            }


            .page-header h1 {

                font-size: 1.8rem;
            }


            .page-header p {

                font-size: 1rem;
            }


            .notification-actions {

                width: 100%;
            }


            .notification-actions form {

                flex: 0 0 auto;
            }


            .notification-actions .open-link {

                width: 100%;

                flex: 1 1 100%;
            }

        }
    </style>

</head>


<body>


    <main class="main">


        <!-- =====================================================
         TOPBAR
        ====================================================== -->

        <header class="topbar">

            <div class="topbar-left">

                <div class="page-title">

                    Pusat Notifikasi

                    <span>
                        Monitoring aktivitas E-Commerce & ERP Toku Coffee
                    </span>

                </div>

            </div>


            <!-- TOP RIGHT -->
            <div class="profile">

                <img
                    src="https://ui-avatars.com/api/?name=<?= $avatarName ?>&background=443&color=fff"
                    alt="Profile">


                <div class="profile-info">

                    <strong>
                        <?= e($namaUser) ?>
                    </strong>

                    <span>
                        <?= e(ucfirst($roleUser)) ?>
                    </span>

                </div>

            </div>

            </div>

        </header>


        <!-- =====================================================
         CONTENT
         ====================================================== -->

        <section class="content">


            <!-- =================================================
             PAGE HEADER
            ================================================== -->

            <div class="page-header">

                <div class="page-header-left">

                    <div class="page-header-icon">

                        <i class="fas fa-bell"></i>

                    </div>


                    <div>

                        <h1>
                            Notifikasi ERP
                        </h1>

                        <p>
                            Pantau seluruh aktivitas penting
                            pada sistem Toku Coffee.
                        </p>

                    </div>

                </div>


                <!-- KEMBALI KE DASHBOARD -->

                <a
                    href="dashboar.php"
                    class="back-dashboard">

                    <i class="fas fa-arrow-left"></i>

                    <span>
                        Kembali ke Dashboard
                    </span>

                </a>

            </div>


            <!-- =================================================
             STATISTICS
            ================================================== -->

            <div class="stats">


                <!-- TOTAL -->

                <div class="stat-card">

                    <div class="stat-icon total">

                        <i class="fas fa-bell"></i>

                    </div>

                    <div class="stat-info">

                        <span>
                            Total Notifikasi
                        </span>

                        <strong>
                            <?= number_format(
                                $totalNotifications
                            ) ?>
                        </strong>

                    </div>

                </div>


                <!-- UNREAD -->

                <div class="stat-card">

                    <div class="stat-icon unread">

                        <i class="fas fa-envelope"></i>

                    </div>

                    <div class="stat-info">

                        <span>
                            Belum Dibaca
                        </span>

                        <strong>
                            <?= number_format(
                                $unreadCount
                            ) ?>
                        </strong>

                    </div>

                </div>

                <!-- TODAY -->

                <div class="stat-card">

                    <div class="stat-icon today">

                        <i class="fas fa-calendar-day"></i>

                    </div>

                    <div class="stat-info">

                        <span>
                            Aktivitas Hari Ini
                        </span>

                        <strong>
                            <?= number_format(
                                $todayNotifications
                            ) ?>
                        </strong>

                    </div>

                </div>


            </div>


            <!-- =================================================
             TOOLBAR
        ================================================== -->

            <div class="toolbar">


                <!-- FILTER -->

                <div class="filters">


                    <a
                        href="notifications.php?filter=all"
                        class="filter <?= $filter === 'all' ? 'active' : '' ?>">

                        Semua

                    </a>


                    <a
                        href="notifications.php?filter=unread"
                        class="filter <?= $filter === 'unread' ? 'active' : '' ?>">

                        Belum Dibaca

                    </a>


                    <a
                        href="notifications.php?filter=order"
                        class="filter <?= $filter === 'order' ? 'active' : '' ?>">

                        Pesanan

                    </a>


                    <a
                        href="notifications.php?filter=stock"
                        class="filter <?= $filter === 'stock' ? 'active' : '' ?>">

                        Inventory

                    </a>


                    <a
                        href="notifications.php?filter=payment"
                        class="filter <?= $filter === 'payment' ? 'active' : '' ?>">

                        Pembayaran

                    </a>


                    <a
                        href="notifications.php?filter=customer"
                        class="filter <?= $filter === 'customer' ? 'active' : '' ?>">

                        Pelanggan

                    </a>


                    <a
                        href="notifications.php?filter=supplier"
                        class="filter <?= $filter === 'supplier' ? 'active' : '' ?>">

                        Supplier

                    </a>


                    <a
                        href="notifications.php?filter=finance"
                        class="filter <?= $filter === 'finance' ? 'active' : '' ?>">

                        Keuangan

                    </a>


                    <a
                        href="notifications.php?filter=system"
                        class="filter <?= $filter === 'system' ? 'active' : '' ?>">

                        Sistem

                    </a>


                </div>


                <!-- =================================================
                 ACTION
            ================================================== -->

                <div class="toolbar-actions">


                    <?php if ($unreadCount > 0): ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($csrfToken) ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="mark_all_read">

                            <button
                                type="submit"
                                class="btn">

                                <i class="fas fa-check-double"></i>

                                Tandai Semua

                            </button>

                        </form>

                    <?php endif; ?>


                    <?php if ($totalNotifications > $unreadCount): ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($csrfToken) ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="delete_read">

                            <button
                                type="submit"
                                class="btn btn-danger">

                                <i class="fas fa-trash"></i>

                                Hapus Dibaca

                            </button>

                        </form>

                    <?php endif; ?>


                </div>

            </div>


            <!-- =================================================
             SECTION TITLE
        ================================================== -->

            <div class="section-title">

                <h2>
                    Aktivitas Terbaru
                </h2>

                <span>

                    <?= count($notifications) ?>

                    aktivitas ditampilkan

                </span>

            </div>


            <!-- =================================================
             NOTIFICATION LIST
        ================================================== -->

            <div class="notification-list">


                <?php if (empty($notifications)): ?>


                    <!-- EMPTY -->

                    <div class="empty">

                        <div class="empty-icon">

                            <i class="fas fa-bell-slash"></i>

                        </div>


                        <h3>
                            Tidak Ada Notifikasi
                        </h3>


                        <p>
                            Belum ada aktivitas ERP
                            yang perlu ditampilkan.
                        </p>

                    </div>


                <?php else: ?>


                    <?php foreach ($notifications as $item): ?>


                        <?php

                        $type = typeClass(
                            $item['type']
                        );

                        $isUnread =
                            (int)$item['is_read'] === 0;

                        $icon =
                            !empty($item['icon'])
                            ? $item['icon']
                            : 'fa-bell';

                        ?>


                        <!-- NOTIFICATION CARD -->

                        <article
                            class="notification-card <?= $isUnread ? 'unread' : '' ?>">


                            <!-- ICON -->

                            <div
                                class="notification-icon <?= e($type) ?>">

                                <i
                                    class="fas <?= e($icon) ?>">
                                </i>

                            </div>


                            <!-- BODY -->

                            <div class="notification-body">


                                <div class="notification-title">

                                    <h3>
                                        <?= e(
                                            $item['title']
                                        ) ?>
                                    </h3>


                                    <?php if ($isUnread): ?>

                                        <span
                                            class="unread-dot"
                                            title="Belum dibaca">
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <div class="notification-message">

                                    <?= e(
                                        $item['message']
                                    ) ?>

                                </div>


                                <div class="notification-meta">


                                    <span class="type-badge">

                                        <?= e(
                                            typeLabel(
                                                $item['type']
                                            )
                                        ) ?>

                                    </span>


                                    <span>

                                        <i class="far fa-clock"></i>

                                        <?= e(
                                            timeAgo(
                                                $item['created_at']
                                            )
                                        ) ?>

                                    </span>


                                    <span>

                                        <i class="far fa-calendar"></i>

                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime(
                                                $item['created_at']
                                            )
                                        ) ?>

                                    </span>


                                </div>

                            </div>


                            <!-- ACTION -->

                            <div class="notification-actions">


                                <!-- BUKA -->

                                <?php if (!empty($item['link'])): ?>

                                    <a
                                        href="<?= e($item['link']) ?>"
                                        class="open-link">

                                        Buka

                                        <i class="fas fa-arrow-right"></i>

                                    </a>

                                <?php endif; ?>


                                <!-- MARK READ -->

                                <?php if ($isUnread): ?>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrfToken) ?>">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="mark_read">

                                        <input
                                            type="hidden"
                                            name="notification_id"
                                            value="<?= (int)$item['id'] ?>">

                                        <button
                                            type="submit"
                                            class="icon-btn"
                                            title="Tandai sudah dibaca">

                                            <i class="fas fa-check"></i>

                                        </button>

                                    </form>

                                <?php endif; ?>


                                <!-- DELETE -->

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($csrfToken) ?>">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete">

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= (int)$item['id'] ?>">

                                    <button
                                        type="submit"
                                        class="icon-btn delete"
                                        title="Hapus">

                                        <i class="fas fa-trash"></i>

                                    </button>

                                </form>


                            </div>


                        </article>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </section>


    </main>


    <!-- =========================================================
     JAVASCRIPT
========================================================= -->

    <script>
        document
            .querySelectorAll('form')
            .forEach(function(form) {

                form.addEventListener(
                    'submit',
                    function(event) {

                        const action =
                            form.querySelector(
                                'input[name="action"]'
                            );


                        /* DELETE ONE */

                        if (
                            action &&
                            action.value === 'delete'
                        ) {

                            if (
                                !confirm(
                                    'Hapus notifikasi ini?'
                                )
                            ) {

                                event.preventDefault();

                            }

                        }


                        /* DELETE READ */

                        if (
                            action &&
                            action.value === 'delete_read'
                        ) {

                            if (
                                !confirm(
                                    'Hapus semua notifikasi yang sudah dibaca?'
                                )
                            ) {

                                event.preventDefault();

                            }

                        }

                    }
                );

            });
    </script>


</body>

</html>
