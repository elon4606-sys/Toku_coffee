<?php

require_once "../config/koneksi.php";
require_once "../config/session.php";
require_once "../config/sales_setup.php";

/* =========================================================
   SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   CEK LOGIN & ROLE
   Customer hanya boleh melihat pesanannya sendiri.
   Admin/Staff boleh membuka tracking dari halaman Sales.
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = (string)($_SESSION['role'] ?? '');

$allowedRoles = ['customer', 'admin', 'staff'];

if (!in_array($role, $allowedRoles, true)) {
    header("Location: ../login/login.php?error=akses_ditolak");
    exit;
}

/* =========================================================
   SIAPKAN TABEL SALES
========================================================= */

try {
    setupSalesPengiriman($conn);
} catch (Throwable $e) {
    // Data akan tetap dicoba dimuat. Jika gagal, pesan akan ditampilkan.
}

/* =========================================================
   HELPER
========================================================= */

function eTrack($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function waktuTrack($value): string
{
    if (empty($value)) {
        return '-';
    }

    $time = strtotime($value);

    return $time
        ? date('d M Y • H:i', $time)
        : '-';
}

function rupiahTrack($value): string
{
    return 'Rp ' . number_format(
        (float)$value,
        0,
        ',',
        '.'
    );
}

function statusClassTrack($value): string
{
    return strtolower(
        preg_replace(
            '/[^a-z0-9]+/i',
            '-',
            trim((string)$value)
        )
    );
}

function labelStatusTrack($status): string
{
    $labels = [
        'Dijadwalkan'      => 'Menunggu Pick Up',
        'Dalam Perjalanan' => 'Sedang Dikirim',
        'Terkirim'         => 'Pesanan Diterima',
        'Dibatalkan'       => 'Dibatalkan'
    ];

    return $labels[$status] ?? ($status ?: 'Menunggu');
}

function progressStatusTrack($status): int
{
    switch ($status) {
        case 'Dijadwalkan':
            return 1;
        case 'Dalam Perjalanan':
            return 2;
        case 'Terkirim':
            return 3;
        case 'Dibatalkan':
            return 0;
        default:
            return 1;
    }
}

/* =========================================================
   DATA DASAR
========================================================= */

$pesananId = (int)($_GET['pesanan'] ?? 0);

$error = '';
$dbError = '';

$orders = [];
$shipment = null;
$events = [];

/* =========================================================
   DAFTAR PESANAN

   Customer : hanya pesanan miliknya.
   Admin/Staff : dapat memilih seluruh pesanan untuk kebutuhan
                 monitoring dari halaman Sales.
========================================================= */

try {

    if ($role === 'customer') {

        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.invoice,
                p.total,
                p.tanggal_pesanan,
                p.status,
                ps.status AS shipping_status,
                ps.resi
            FROM pesanan p
            LEFT JOIN pengiriman_sales ps
                ON ps.pesanan_id = p.id
                AND ps.status <> 'Dibatalkan'
            WHERE p.user_id = ?
            ORDER BY p.tanggal_pesanan DESC
            LIMIT 20
        ");

        $stmt->bind_param('i', $userId);
    } else {

        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.invoice,
                p.total,
                p.tanggal_pesanan,
                p.status,
                ps.status AS shipping_status,
                ps.resi
            FROM pesanan p
            LEFT JOIN pengiriman_sales ps
                ON ps.pesanan_id = p.id
                AND ps.status <> 'Dibatalkan'
            ORDER BY p.tanggal_pesanan DESC
            LIMIT 20
        ");
    }

    if (!$stmt) {
        throw new Exception('Query daftar pesanan tidak dapat dibuat.');
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $stmt->close();
} catch (Throwable $e) {
    $dbError = 'Daftar pesanan belum dapat dimuat.';
}

/* =========================================================
   PESANAN DEFAULT
========================================================= */

if ($pesananId <= 0 && !empty($orders)) {
    $pesananId = (int)$orders[0]['id'];
}

/* =========================================================
   DETAIL PESANAN + PENGIRIMAN
========================================================= */

if ($pesananId > 0) {

    try {

        if ($role === 'customer') {

            $stmt = $conn->prepare("
                SELECT
                    p.id,
                    p.invoice,
                    p.total,
                    p.tanggal_pesanan,
                    p.status,
                    p.alamat_pengiriman,

                    ps.id AS pengiriman_id,
                    ps.status AS shipping_status,
                    ps.resi,
                    ps.estimasi_tiba,
                    ps.kurir_nama,
                    ps.kurir_telepon,
                    ps.tujuan_lat,
                    ps.tujuan_lng,
                    ps.tujuan_label,
                    ps.created_at AS pengiriman_created_at,
                    ps.updated_at AS pengiriman_updated_at,

                    j.nama_jasa,
                    j.gps_status,
                    j.gps_lat,
                    j.gps_lng,
                    j.gps_update,
                    j.gps_device_id

                FROM pesanan p

                LEFT JOIN pengiriman_sales ps
                    ON ps.pesanan_id = p.id
                    AND ps.status <> 'Dibatalkan'

                LEFT JOIN jasa_pengiriman j
                    ON j.id = ps.jasa_id

                WHERE p.id = ?
                AND p.user_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                'ii',
                $pesananId,
                $userId
            );
        } else {

            $stmt = $conn->prepare("
                SELECT
                    p.id,
                    p.invoice,
                    p.total,
                    p.tanggal_pesanan,
                    p.status,
                    p.alamat_pengiriman,

                    ps.id AS pengiriman_id,
                    ps.status AS shipping_status,
                    ps.resi,
                    ps.estimasi_tiba,
                    ps.kurir_nama,
                    ps.kurir_telepon,
                    ps.tujuan_lat,
                    ps.tujuan_lng,
                    ps.tujuan_label,
                    ps.created_at AS pengiriman_created_at,
                    ps.updated_at AS pengiriman_updated_at,

                    j.nama_jasa,
                    j.gps_status,
                    j.gps_lat,
                    j.gps_lng,
                    j.gps_update,
                    j.gps_device_id

                FROM pesanan p

                LEFT JOIN pengiriman_sales ps
                    ON ps.pesanan_id = p.id
                    AND ps.status <> 'Dibatalkan'

                LEFT JOIN jasa_pengiriman j
                    ON j.id = ps.jasa_id

                WHERE p.id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                'i',
                $pesananId
            );
        }

        if (!$stmt) {
            throw new Exception('Query detail pesanan tidak dapat dibuat.');
        }

        $stmt->execute();

        $shipment = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$shipment) {

            $error = 'Pesanan tidak ditemukan.';
        } elseif (!empty($shipment['pengiriman_id'])) {

            $shipmentId = (int)$shipment['pengiriman_id'];

            $stmt = $conn->prepare("
                SELECT
                    id,
                    pengiriman_id,
                    status,
                    judul,
                    keterangan,
                    lokasi,
                    latitude,
                    longitude,
                    waktu_event,
                    dibuat_oleh,
                    created_at
                FROM pengiriman_tracking
                WHERE pengiriman_id = ?
                ORDER BY waktu_event ASC, id ASC
            ");

            $stmt->bind_param(
                'i',
                $shipmentId
            );

            $stmt->execute();

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }

            $stmt->close();
        }
    } catch (Throwable $e) {
        $error = 'Data tracking belum dapat ditampilkan.';
    }
}

/* =========================================================
   DATA TAMPILAN
========================================================= */

$shippingStatus = $shipment['shipping_status'] ?? '';

$displayStatus = $shippingStatus !== ''
    ? $shippingStatus
    : ($shipment['status'] ?? 'Menunggu');

$statusLabel = labelStatusTrack($displayStatus);
$statusClass = statusClassTrack($displayStatus);
$progress = progressStatusTrack($shippingStatus);

$namaJasa = trim((string)($shipment['nama_jasa'] ?? ''));
$namaKurir = trim((string)($shipment['kurir_nama'] ?? ''));
$teleponKurir = trim((string)($shipment['kurir_telepon'] ?? ''));

$namaKurirTampil = $namaKurir !== ''
    ? $namaKurir
    : ($namaJasa !== '' ? $namaJasa : '-');

$gpsLat = $shipment['gps_lat'] ?? null;
$gpsLng = $shipment['gps_lng'] ?? null;
$tujuanLat = $shipment['tujuan_lat'] ?? null;
$tujuanLng = $shipment['tujuan_lng'] ?? null;

$hasGps = $shipment
    && $shipment['pengiriman_id']
    && $gpsLat !== null
    && $gpsLng !== null;

$hasDestination = $shipment
    && $shipment['tujuan_lat'] !== null
    && $shipment['tujuan_lng'] !== null;

$destinationLabel = trim((string)($shipment['tujuan_label'] ?? ''));

/* =========================================================
   INFORMASI ROLE / NAVIGASI
========================================================= */

if ($role === 'customer') {
    $backUrl = 'produk.php';
    $backLabel = 'Belanja';
    $roleLabel = 'Customer';
} else {
    $backUrl = '../sales.php';
    $backLabel = 'Kembali ke Sales';
    $roleLabel = ucfirst($role);
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Lacak Pengiriman - Toku Coffee</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <style>
        * {
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        :root {
            --main-color: #443;
            --bg: #f5f5f5;
            --white: #fff;
            --border: #e5e5e5;
            --green: #527853;
            --orange: #c68b3c;
            --blue: #557a95;
            --red: #a94442;
            --muted: #999;
            --soft: #faf8f4;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: #333;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input,
        select {
            font: inherit;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .top {
            background: #fff;
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .topin {
            width: 100%;
            max-width: 1050px;
            margin: auto;
            padding: 14px 15px;
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--main-color);
            font-size: 17px;
            font-weight: 700;
        }

        .brand i {
            font-size: 17px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 11px;
            border: 1px solid #ddd;
            border-radius: 7px;
            background: #fff;
            color: #444;
            font-size: 11px;
        }

        .top-btn:hover {
            border-color: var(--main-color);
        }

        /* =====================================================
           PAGE
        ===================================================== */

        .page {
            width: 100%;
            max-width: 1050px;
            margin: auto;
            padding: 22px 15px 35px;
        }

        .page-title {
            margin-bottom: 16px;
        }

        .page-title h1 {
            margin: 0 0 4px;
            font-size: 22px;
            color: var(--main-color);
            font-weight: 700;
        }

        .page-title p {
            margin: 0;
            color: #888;
            font-size: 11px;
        }

        /* =====================================================
           ORDER SELECTOR
        ===================================================== */

        .orders-wrap {
            margin-bottom: 14px;
        }

        .orders {
            display: flex;
            gap: 9px;
            overflow-x: auto;
            padding: 2px 2px 8px;
            scrollbar-width: thin;
        }

        .order-card {
            min-width: 225px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 11px 12px;
            transition: .2s ease;
        }

        .order-card:hover {
            border-color: #cfcfcf;
            transform: translateY(-1px);
        }

        .order-card.active {
            border-color: var(--main-color);
            background: #f7f4ec;
        }

        .order-card strong {
            display: block;
            color: #333;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .order-card small {
            display: block;
            margin-top: 3px;
            color: #999;
            font-size: 9px;
        }

        /* =====================================================
           ERROR
        ===================================================== */

        .error-box {
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #edc1c1;
            border-radius: 7px;
            background: #fff1f1;
            color: var(--red);
            font-size: 11px;
        }

        /* =====================================================
           MAIN HERO
        ===================================================== */

        .hero {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 9px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
            padding: 18px;
            margin-bottom: 14px;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
        }

        .invoice-label {
            color: #999;
            font-size: 10px;
            margin-bottom: 3px;
        }

        .hero h2 {
            margin: 0 0 4px;
            color: #252525;
            font-size: 19px;
            font-weight: 600;
        }

        .destination {
            color: #777;
            font-size: 10px;
            line-height: 1.55;
            max-width: 760px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 9px;
            border: 1px solid currentColor;
            border-radius: 6px;
            font-size: 9px;
            white-space: nowrap;
        }

        .dijadwalkan {
            color: var(--orange);
            background: #fff7e9;
        }

        .dalam-perjalanan {
            color: var(--blue);
            background: #eef5f9;
        }

        .terkirim,
        .selesai {
            color: var(--green);
            background: #f0f7f0;
        }

        .dibatalkan {
            color: #777;
            background: #f1f1f1;
        }

        /* =====================================================
           PROGRESS
        ===================================================== */

        .progress {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }

        .progress-item {
            position: relative;
            text-align: center;
            color: #aaa;
            font-size: 9px;
        }

        .progress-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 9px;
            left: calc(50% + 15px);
            right: calc(-50% + 15px);
            height: 2px;
            background: #ddd;
        }

        .progress-dot {
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 6px;
            border-radius: 50%;
            border: 2px solid #ddd;
            background: #fff;
            position: relative;
            z-index: 1;
            font-size: 8px;
        }

        .progress-item.done {
            color: var(--green);
        }

        .progress-item.done .progress-dot {
            border-color: var(--green);
            background: var(--green);
            color: #fff;
        }

        .progress-item.done:not(:last-child)::after {
            background: var(--green);
        }

        .progress-item.current {
            color: var(--main-color);
            font-weight: 600;
        }

        .progress-item.current .progress-dot {
            border-color: var(--main-color);
            box-shadow: 0 0 0 3px #f3f0e8;
        }

        /* =====================================================
           INFO BOXES
        ===================================================== */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 9px;
            margin-top: 14px;
        }

        .info-box {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 7px;
            padding: 11px 12px;
        }

        .info-box strong {
            display: block;
            color: #999;
            font-size: 9px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .info-box span {
            display: block;
            color: #333;
            font-size: 11px;
            font-weight: 500;
            word-break: break-word;
        }

        .gps-online {
            color: var(--green) !important;
        }

        .gps-offline {
            color: #777 !important;
        }

        /* =====================================================
           GRID TRACKING
        ===================================================== */

        .tracking-grid {
            display: grid;
            grid-template-columns: 1.25fr .75fr;
            gap: 14px;
            align-items: start;
        }

        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 9px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
            padding: 17px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .card h3 {
            margin: 0;
            color: #292929;
            font-size: 15px;
            font-weight: 600;
        }

        .card-subtitle {
            color: #999;
            font-size: 9px;
        }

        /* =====================================================
           MAP
        ===================================================== */

        #trackMap {
            width: 100%;
            height: 360px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #eee;
        }

        .map-note {
            margin: 9px 0 0;
            color: #999;
            font-size: 9px;
            line-height: 1.5;
        }

        /* =====================================================
           COURIER
        ===================================================== */

        .courier-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 12px;
            padding: 10px 11px;
            border: 1px solid #eee;
            border-radius: 7px;
            background: #fafafa;
        }

        .courier-main {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
        }

        .courier-icon {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            border-radius: 8px;
            background: #f3f0e8;
            color: var(--main-color);
        }

        .courier-info strong {
            display: block;
            color: #333;
            font-size: 10px;
        }

        .courier-info span {
            display: block;
            margin-top: 2px;
            color: #999;
            font-size: 9px;
        }

        .call-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 9px;
            border: 1px solid var(--main-color);
            border-radius: 6px;
            color: var(--main-color);
            background: #fff;
            font-size: 9px;
        }

        .call-btn:hover {
            background: #f7f4ec;
        }

        /* =====================================================
           TIMELINE
        ===================================================== */

        .timeline {
            position: relative;
            padding-left: 25px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: #e2e2e2;
        }

        .event {
            position: relative;
            padding: 0 0 18px 8px;
        }

        .event:last-child {
            padding-bottom: 0;
        }

        .event-dot {
            position: absolute;
            left: -24px;
            top: 1px;
            width: 14px;
            height: 14px;
            border: 3px solid #bbb;
            border-radius: 50%;
            background: #fff;
        }

        .event.active .event-dot {
            border-color: var(--green);
            background: var(--green);
            box-shadow: 0 0 0 3px #f0f7f0;
        }

        .event h4 {
            margin: 0;
            color: #333;
            font-size: 10px;
            font-weight: 600;
        }

        .event p {
            margin: 4px 0;
            color: #777;
            font-size: 9px;
            line-height: 1.5;
        }

        .event small {
            color: #aaa;
            font-size: 8px;
            line-height: 1.5;
        }

        .note {
            margin-top: 14px;
            padding: 10px 11px;
            border-radius: 7px;
            background: #fffaf0;
            border: 1px solid #f0dfb7;
            color: #755d2b;
            font-size: 9px;
            line-height: 1.5;
        }

        /* =====================================================
           ACTIONS
        ===================================================== */

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 12px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 7px;
            background: #fff;
            color: #444;
            font-size: 9px;
        }

        .action-btn:hover {
            border-color: var(--main-color);
        }

        .empty {
            padding: 35px 12px;
            text-align: center;
            color: #999;
            font-size: 11px;
        }

        .empty i {
            display: block;
            margin-bottom: 9px;
            font-size: 30px;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 850px) {

            .tracking-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 550px) {

            .topin,
            .page {
                padding-left: 10px;
                padding-right: 10px;
            }

            .topin {
                min-height: 52px;
            }

            .brand {
                font-size: 15px;
            }

            .top-btn span {
                display: none;
            }

            .page {
                padding-top: 16px;
            }

            .page-title h1 {
                font-size: 19px;
            }

            .hero {
                padding: 14px;
            }

            .hero-top {
                flex-direction: column;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .progress {
                gap: 5px;
            }

            .progress-item {
                font-size: 8px;
            }

            #trackMap {
                height: 310px;
            }

            .courier-box {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

</head>

<body>

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="top">

        <div class="topin">

            <a
                href="<?= eTrack($backUrl) ?>"
                class="brand">
                <i class="fas fa-mug-hot"></i>
                TOKU COFFEE
            </a>

            <div class="header-actions">

                <a
                    href="<?= eTrack($backUrl) ?>"
                    class="top-btn">
                    <i class="fas <?= $role === 'customer' ? 'fa-store' : 'fa-arrow-left' ?>"></i>
                    <span><?= eTrack($backLabel) ?></span>
                </a>

            </div>

        </div>

    </header>

    <!-- =====================================================
         PAGE
    ====================================================== -->

    <main class="page">

        <div class="page-title">

            <h1>Lacak Pengiriman</h1>

            <p>
                Pantau status, resi, estimasi tiba, posisi kurir,
                dan perjalanan pesanan.
            </p>

        </div>

        <?php if ($dbError): ?>

            <div class="error-box">
                <i class="fas fa-circle-exclamation"></i>
                <?= eTrack($dbError) ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="error-box">
                <i class="fas fa-circle-exclamation"></i>
                <?= eTrack($error) ?>
            </div>

        <?php endif; ?>

        <!-- =====================================================
             DAFTAR PESANAN
        ====================================================== -->

        <?php if ($orders): ?>

            <div class="orders-wrap">

                <div class="orders">

                    <?php foreach ($orders as $order): ?>

                        <?php
                        $orderShippingStatus = $order['shipping_status'] ?? '';
                        $orderStatusText = $orderShippingStatus !== ''
                            ? labelStatusTrack($orderShippingStatus)
                            : ($order['status'] ?? 'Menunggu');
                        ?>

                        <a
                            href="tracking.php?pesanan=<?= (int)$order['id'] ?>"
                            class="order-card <?= (int)$order['id'] === $pesananId ? 'active' : '' ?>">

                            <strong>
                                <?= eTrack($order['invoice']) ?>
                            </strong>

                            <small>
                                <?= eTrack(waktuTrack($order['tanggal_pesanan'])) ?>
                            </small>

                            <small>
                                <?= eTrack($orderStatusText) ?>
                            </small>

                        </a>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>

        <?php if ($shipment): ?>

            <!-- =================================================
                 HERO DETAIL
            ================================================== -->

            <section class="hero">

                <div class="hero-top">

                    <div>

                        <div class="invoice-label">
                            Pesanan <?= eTrack($shipment['invoice']) ?>
                        </div>

                        <h2>
                            <?= eTrack($statusLabel) ?>
                        </h2>

                        <div class="destination">

                            <i class="fas fa-location-dot"></i>

                            <?= eTrack(
                                $shipment['alamat_pengiriman']
                                    ?: ($shipment['tujuan_label'] ?? 'Alamat tujuan belum tersedia')
                            ) ?>

                        </div>

                    </div>

                    <span class="badge <?= eTrack($statusClass) ?>">
                        <?= eTrack($statusLabel) ?>
                    </span>

                </div>

                <?php if ($shippingStatus !== 'Dibatalkan'): ?>

                    <div class="progress">

                        <div class="progress-item <?= $progress >= 1 ? 'done' : '' ?> <?= $progress === 1 ? 'current' : '' ?>">

                            <div class="progress-dot">
                                <i class="fas fa-clipboard-check"></i>
                            </div>

                            Pesanan Diproses

                        </div>

                        <div class="progress-item <?= $progress >= 2 ? 'done' : '' ?> <?= $progress === 2 ? 'current' : '' ?>">

                            <div class="progress-dot">
                                <i class="fas fa-truck-fast"></i>
                            </div>

                            Sedang Dikirim

                        </div>

                        <div class="progress-item <?= $progress >= 3 ? 'done' : '' ?> <?= $progress === 3 ? 'current' : '' ?>">

                            <div class="progress-dot">
                                <i class="fas fa-circle-check"></i>
                            </div>

                            Pesanan Diterima

                        </div>

                    </div>

                <?php else: ?>

                    <div class="progress">

                        <div class="progress-item current" style="grid-column:1/-1">

                            <div class="progress-dot" style="margin-bottom:6px">
                                <i class="fas fa-xmark"></i>
                            </div>

                            Pengiriman dibatalkan

                        </div>

                    </div>

                <?php endif; ?>

                <div class="info-grid">

                    <div class="info-box">

                        <strong>NO. RESI</strong>

                        <span>
                            <?= eTrack($shipment['resi'] ?: 'Belum tersedia') ?>
                        </span>

                    </div>

                    <div class="info-box">

                        <strong>JASA / KURIR</strong>

                        <span>
                            <?= eTrack($namaKurirTampil) ?>
                        </span>

                    </div>

                    <div class="info-box">

                        <strong>ESTIMASI TIBA</strong>

                        <span>
                            <?php if (!empty($shipment['estimasi_tiba'])): ?>
                                <?= eTrack(date('d M Y', strtotime($shipment['estimasi_tiba']))) ?>
                            <?php else: ?>
                                Belum tersedia
                            <?php endif; ?>
                        </span>

                    </div>

                    <div class="info-box">

                        <strong>STATUS GPS</strong>

                        <span class="<?= ($shipment['gps_status'] ?? '') === 'online' ? 'gps-online' : 'gps-offline' ?>">
                            <?= eTrack(ucfirst($shipment['gps_status'] ?? 'offline')) ?>
                        </span>

                    </div>

                </div>

            </section>

            <?php if (!empty($shipment['pengiriman_id'])): ?>

                <div class="tracking-grid">

                    <!-- =========================================
                         MAP
                    ========================================== -->

                    <section class="card">

                        <div class="card-header">

                            <div>
                                <h3>Posisi Pengiriman</h3>
                                <div class="card-subtitle">
                                    Lokasi terakhir kurir dari sistem Toku Coffee
                                </div>
                            </div>

                            <i
                                class="fas fa-map-location-dot"
                                style="font-size:17px;color:#557a95"></i>

                        </div>

                        <div id="trackMap"></div>

                        <?php if ($hasGps): ?>

                            <div class="actions">

                                <a
                                    class="action-btn"
                                    target="_blank"
                                    rel="noopener"
                                    href="https://www.google.com/maps?q=<?= urlencode((string)$gpsLat) ?>,<?= urlencode((string)$gpsLng) ?>">
                                    <i class="fas fa-location-dot"></i>
                                    Buka Google Maps
                                </a>

                            </div>

                            <p class="map-note">
                                GPS terakhir diperbarui:
                                <?= eTrack(waktuTrack($shipment['gps_update'])) ?>
                            </p>

                        <?php else: ?>

                            <p class="map-note">
                                Lokasi GPS kurir belum tersedia.
                            </p>

                        <?php endif; ?>

                        <div class="courier-box">

                            <div class="courier-main">

                                <div class="courier-icon">
                                    <i class="fas fa-motorcycle"></i>
                                </div>

                                <div class="courier-info">

                                    <strong>
                                        <?= eTrack($namaKurirTampil) ?>
                                    </strong>

                                    <span>
                                        <?= eTrack(
                                            $teleponKurir !== ''
                                                ? $teleponKurir
                                                : 'Nomor kurir belum tersedia'
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                            <?php if ($teleponKurir !== ''): ?>

                                <a
                                    href="tel:<?= eTrack($teleponKurir) ?>"
                                    class="call-btn">
                                    <i class="fas fa-phone"></i>
                                    Hubungi
                                </a>

                            <?php endif; ?>

                        </div>

                    </section>

                    <!-- =========================================
                         TIMELINE
                    ========================================== -->

                    <section class="card">

                        <div class="card-header">

                            <div>
                                <h3>Perjalanan Paket</h3>
                                <div class="card-subtitle">
                                    Riwayat perubahan status pengiriman
                                </div>
                            </div>

                            <i
                                class="fas fa-route"
                                style="font-size:16px;color:#527853"></i>

                        </div>

                        <?php if ($events): ?>

                            <div class="timeline">

                                <?php foreach (array_reverse($events) as $index => $event): ?>

                                    <div class="event <?= $index === 0 ? 'active' : '' ?>">

                                        <span class="event-dot"></span>

                                        <h4>
                                            <?= eTrack($event['judul']) ?>
                                        </h4>

                                        <?php if (!empty($event['keterangan'])): ?>

                                            <p>
                                                <?= eTrack($event['keterangan']) ?>
                                            </p>

                                        <?php endif; ?>

                                        <small>
                                            <?= eTrack(waktuTrack($event['waktu_event'])) ?>

                                            <?php if (!empty($event['lokasi'])): ?>
                                                • <?= eTrack($event['lokasi']) ?>
                                            <?php endif; ?>
                                        </small>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="empty">

                                <i class="fas fa-route"></i>

                                Belum ada pembaruan tracking.

                            </div>

                        <?php endif; ?>

                        <div class="note">

                            <i class="fas fa-circle-info"></i>

                            Status pengiriman akan mengikuti pembaruan dari
                            admin/staff melalui halaman Sales.

                        </div>

                    </section>

                </div>

            <?php else: ?>

                <section class="card">

                    <div class="empty">

                        <i class="fas fa-box-open"></i>

                        Pesanan ini belum memiliki jasa pengiriman.

                    </div>

                </section>

            <?php endif; ?>

        <?php elseif (!$error && !$dbError): ?>

            <section class="card">

                <div class="empty">

                    <i class="fas fa-box-open"></i>

                    Belum ada pesanan yang dapat dilacak.

                </div>

            </section>

        <?php endif; ?>

    </main>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <?php if ($hasGps): ?>

        <script>
            const gpsLat = <?= json_encode((float)$gpsLat) ?>;
            const gpsLng = <?= json_encode((float)$gpsLng) ?>;

            const trackMap = L.map('trackMap', {
                scrollWheelZoom: false
            }).setView([gpsLat, gpsLng], 15);

            L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }
            ).addTo(trackMap);

            const courierMarker = L.marker([
                gpsLat,
                gpsLng
            ]).addTo(trackMap);

            courierMarker
                .bindPopup(
                    '<b><?= eTrack($namaKurirTampil) ?></b><br>' +
                    'Lokasi kurir terakhir'
                )
                .openPopup();

            <?php if ($hasDestination): ?>

                const tujuanLat = <?= json_encode((float)$tujuanLat) ?>;
                const tujuanLng = <?= json_encode((float)$tujuanLng) ?>;

                const destinationMarker = L.marker([
                    tujuanLat,
                    tujuanLng
                ]).addTo(trackMap);

                destinationMarker.bindPopup(
                    '<b>Tujuan</b><br><?= eTrack($destinationLabel !== '' ? $destinationLabel : 'Alamat tujuan') ?>'
                );

                L.polyline(
                    [
                        [gpsLat, gpsLng],
                        [tujuanLat, tujuanLng]
                    ], {
                        color: '#557a95',
                        weight: 3,
                        dashArray: '7 7'
                    }
                ).addTo(trackMap);

                trackMap.fitBounds(
                    [
                        [gpsLat, gpsLng],
                        [tujuanLat, tujuanLng]
                    ], {
                        padding: [25, 25]
                    }
                );

            <?php endif; ?>

            setTimeout(function() {
                location.reload();
            }, 30000);
        </script>

    <?php else: ?>

        <script>
            const mapElement = document.getElementById('trackMap');

            if (mapElement) {
                mapElement.innerHTML =
                    '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:#999;font-size:11px">' +
                    '<i class="fas fa-location-crosshairs" style="margin-right:6px"></i>' +
                    'Lokasi GPS kurir belum tersedia.' +
                    '</div>';
            }
        </script>

    <?php endif; ?>

</body>

</html>
