<?php
require_once "../config/koneksi.php";
require_once "../config/session.php";
require_once "../config/sales_setup.php";

requireRole(['customer']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
    setupSalesPengiriman($conn);
} catch (Throwable $e) {
}

function rupiahSalesTrack($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function eTrack($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function waktuTrack($v): string
{
    $t = strtotime($v);
    return $t ? date('d M Y • H:i', $t) : '-';
}

function statusClassTrack($v): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim((string)$v)));
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$pesananId = (int)($_GET['pesanan'] ?? 0);
$error = '';
$orders = [];
$shipment = null;
$events = [];

$result = $conn->prepare("
    SELECT p.id,p.invoice,p.total,p.tanggal_pesanan,p.status,
           ps.id AS pengiriman_id, ps.status AS shipping_status, ps.resi, ps.estimasi_tiba,
           ps.kurir_nama, ps.kurir_telepon, ps.tujuan_lat, ps.tujuan_lng, ps.tujuan_label,
           j.nama_jasa, j.gps_status, j.gps_lat, j.gps_lng, j.gps_update
    FROM pesanan p
    LEFT JOIN pengiriman_sales ps ON ps.pesanan_id=p.id AND ps.status<>'Dibatalkan'
    LEFT JOIN jasa_pengiriman j ON j.id=ps.jasa_id
    WHERE p.user_id=?
    ORDER BY p.tanggal_pesanan DESC
    LIMIT 20
");
$result->bind_param('i', $userId);
$result->execute();
$q = $result->get_result();
while ($r = $q->fetch_assoc()) $orders[] = $r;
$result->close();

if ($pesananId <= 0 && !empty($orders)) $pesananId = (int)$orders[0]['id'];

if ($pesananId > 0) {
    $stmt = $conn->prepare("
        SELECT p.id,p.invoice,p.total,p.tanggal_pesanan,p.status,p.alamat_pengiriman,
               ps.id AS pengiriman_id, ps.status AS shipping_status, ps.resi, ps.estimasi_tiba,
               ps.kurir_nama,ps.kurir_telepon,ps.tujuan_lat,ps.tujuan_lng,ps.tujuan_label,
               j.nama_jasa,j.gps_status,j.gps_lat,j.gps_lng,j.gps_update
        FROM pesanan p
        LEFT JOIN pengiriman_sales ps ON ps.pesanan_id=p.id AND ps.status<>'Dibatalkan'
        LEFT JOIN jasa_pengiriman j ON j.id=ps.jasa_id
        WHERE p.id=? AND p.user_id=?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $pesananId, $userId);
    $stmt->execute();
    $shipment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$shipment) {
        $error = 'Pesanan tidak ditemukan.';
    } elseif ($shipment['pengiriman_id']) {
        $stmt = $conn->prepare("SELECT * FROM pengiriman_tracking WHERE pengiriman_id=? ORDER BY waktu_event ASC,id ASC");
        $sid = (int)$shipment['pengiriman_id'];
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $q = $stmt->get_result();
        while ($r = $q->fetch_assoc()) $events[] = $r;
        $stmt->close();
    }
}

$labels = [
    'Dijadwalkan'      => 'Menunggu Pick Up',
    'Dalam Perjalanan' => 'Sedang Dikirim',
    'Terkirim'         => 'Pesanan Diterima',
    'Dibatalkan'       => 'Dibatalkan'
];

$currentStatus = $shipment['shipping_status'] ?? ($shipment['status'] ?? 'Menunggu');
$currentLabel = $labels[$currentStatus] ?? $currentStatus;
$currentClass = statusClassTrack($currentStatus);

$trackingSteps = [
    ['status' => 'Dijadwalkan', 'label' => 'Diproses', 'icon' => 'fa-box'],
    ['status' => 'Dalam Perjalanan', 'label' => 'Dikirim', 'icon' => 'fa-truck-fast'],
    ['status' => 'Terkirim', 'label' => 'Selesai', 'icon' => 'fa-circle-check']
];

$progressIndex = -1;
if ($currentStatus === 'Dijadwalkan') $progressIndex = 0;
if ($currentStatus === 'Dalam Perjalanan') $progressIndex = 1;
if ($currentStatus === 'Terkirim') $progressIndex = 2;
$isCancelled = $currentStatus === 'Dibatalkan';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak Pengiriman - Toku Coffee</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <style>
        :root {
            --main-color: #443;
            --border-radius: 95% 4% 97% 5% / 4% 94% 3% 95%;
            --border-radius-hover: 4% 95% 6% 95% / 95% 4% 92% 5%;
            --border: .2rem solid var(--main-color);
            --border-hover: .2rem dashed var(--main-color);
            --bg: #faf9f5;
            --white: #fff;
            --green: #527853;
            --orange: #c68b3c;
            --red: #a94442;
            --blue: #557a95;
            --gray: #888;
            --soft: #f7f4ec;
            --line: #e9e7df;
            --shadow: 0 1rem 2.5rem rgba(68, 68, 51, .07);
        }

        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            outline: none;
            border: none;
            text-decoration: none;
            transition: all .2s linear;
        }

        html {
            font-size: 56%;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        body {
            background: var(--bg);
            color: var(--main-color);
            min-height: 100vh;
        }

        a {
            color: inherit;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .topbar {
            min-height: 6.8rem;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 3rem;
            box-shadow: 0 .3rem 1rem rgba(0, 0, 0, .05);
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .topbar-left,
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1.3rem;
        }

        .back-btn {
            width: 3.5rem;
            height: 3.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: .1rem solid #ddd;
            border-radius: 45% 55% 60% 40%;
            background: #fff;
            color: var(--main-color);
        }

        .back-btn:hover {
            border: var(--border);
            transform: translateX(-.2rem);
            background: var(--soft);
        }

        .logo {
            color: var(--main-color);
            font-size: 2rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: .7rem;
        }

        .logo i {
            font-size: 2.1rem;
        }

        .logo:hover {
            transform: scale(1.02);
        }

        .divider {
            width: .1rem;
            height: 2.8rem;
            background: #eee;
        }

        .page-title h2 {
            font-size: 1.5rem;
            line-height: 1.2;
        }

        .page-title p {
            font-size: .9rem;
            color: #999;
            margin-top: .25rem;
        }

        .customer-chip {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .65rem 1rem;
            background: #faf8f1;
            border: .1rem solid #eee;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        .main {
            max-width: 120rem;
            margin: 0 auto;
            padding: 2rem 3rem 3rem;
            animation: fadeUp .5s ease;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: .6rem;
            color: #999;
            font-size: .9rem;
            margin-bottom: 1.3rem;
        }

        .breadcrumb a:hover {
            color: var(--main-color);
        }

        .breadcrumb i {
            font-size: .75rem;
        }

        .section-heading {
            margin-bottom: 1.3rem;
        }

        .section-heading h2 {
            font-size: 1.7rem;
        }

        .section-heading p {
            color: #999;
            font-size: .95rem;
            margin-top: .25rem;
        }

        .orders {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding: .2rem .1rem 1.7rem;
            margin-bottom: 1rem;
            scrollbar-width: thin;
        }

        .order-card {
            flex: 0 0 23rem;
            background: #fff;
            border: .1rem solid #ddd;
            border-radius: 1.5rem;
            padding: 1.2rem;
            position: relative;
        }

        .order-card:hover {
            border: var(--border);
            background: #fff;
            transform: translateY(-.2rem);
            box-shadow: var(--shadow);
        }

        .order-card.active {
            border: var(--border);
            background: #f3f0e8;
            box-shadow: 0 .6rem 1.5rem rgba(68, 68, 51, .06);
        }

        .order-card .order-top {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            margin-bottom: .8rem;
        }

        .order-card .order-icon {
            width: 3.2rem;
            height: 3.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: .1rem solid var(--main-color);
            border-radius: 45% 55% 60% 40%;
            font-size: 1.35rem;
        }

        .order-card.active .order-icon {
            background: var(--main-color);
            color: #fff;
        }

        .order-card strong {
            display: block;
            font-size: .95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .order-card small {
            display: block;
            color: #999;
            font-size: .9rem;
            margin-top: .35rem;
        }

        .order-status-mini {
            display: inline-flex;
            margin-top: .8rem;
            padding: .35rem .8rem;
            border: .1rem solid currentColor;
            border-radius: var(--border-radius);
            font-size: .82rem;
        }

        .hero {
            background: #fff;
            border: var(--border);
            border-radius: var(--border-radius);
            padding: 1.8rem;
            margin-bottom: 1.6rem;
            box-shadow: var(--shadow);
        }

        .hero:hover,
        .panel:hover {
            border: var(--border-hover);
            border-radius: var(--border-radius-hover);
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            align-items: flex-start;
        }

        .eyebrow {
            font-size: .9rem;
            color: #999;
            margin-bottom: .3rem;
        }

        .hero h1 {
            font-size: 2rem;
            line-height: 1.3;
        }

        .address {
            font-size: .95rem;
            color: #777;
            margin-top: .7rem;
            line-height: 1.55;
            max-width: 76rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .65rem 1rem;
            border: .1rem solid currentColor;
            border-radius: var(--border-radius);
            font-size: .88rem;
            white-space: nowrap;
        }

        .dijadwalkan {
            color: var(--orange);
            background: #fff7e9
        }

        .dalam-perjalanan {
            color: var(--blue);
            background: #eef5f9
        }

        .terkirim {
            color: var(--green);
            background: #f0f7f0
        }

        .dibatalkan {
            color: #777;
            background: #f1f1f1
        }

        .menunggu {
            color: var(--red);
            background: #fff0ef
        }

        .progress {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            margin: 1.8rem 0 1.2rem;
            padding: 0 .4rem;
        }

        .step {
            position: relative;
            text-align: center;
            color: #aaa;
            padding-top: 3.5rem;
        }

        .step:before {
            content: '';
            position: absolute;
            top: 1.35rem;
            left: 0;
            right: 0;
            height: .18rem;
            background: #ddd;
            z-index: 0;
        }

        .step:first-child:before {
            left: 50%;
        }

        .step:last-child:before {
            right: 50%;
        }

        .step-dot {
            position: absolute;
            top: .3rem;
            left: 50%;
            transform: translateX(-50%);
            width: 2.2rem;
            height: 2.2rem;
            border-radius: 50%;
            background: #fff;
            border: .25rem solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            font-size: .85rem;
        }

        .step.done {
            color: var(--green);
        }

        .step.done:before,
        .step.current:before {
            background: var(--green);
        }

        .step.done .step-dot,
        .step.current .step-dot {
            border-color: var(--green);
            background: #f0f7f0;
            color: var(--green);
        }

        .step.current {
            color: var(--main-color);
            font-weight: 500;
        }

        .step.current .step-dot {
            box-shadow: 0 0 0 .45rem rgba(82, 120, 83, .08);
        }

        .step span {
            display: block;
            font-size: .88rem;
        }

        .cancel-note {
            margin-top: 1rem;
            padding: 1rem 1.2rem;
            background: #fff0ef;
            border: .1rem solid #f0d1ce;
            border-radius: var(--border-radius);
            color: var(--red);
            font-size: .9rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .9rem;
            margin-top: 1.5rem;
        }

        .info-box {
            background: #faf8f1;
            border: .1rem solid #eee;
            border-radius: 1.4rem;
            padding: 1.1rem 1.2rem;
            min-height: 7rem;
        }

        .info-box:hover {
            background: #f7f4ec;
            transform: translateY(-.15rem);
        }

        .info-box strong {
            display: block;
            font-size: .82rem;
            color: #999;
            margin-bottom: .4rem;
        }

        .info-box span {
            display: block;
            font-size: 1rem;
            font-weight: 500;
            word-break: break-word;
            line-height: 1.45;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .7rem;
            margin-top: 1.3rem;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.25fr .85fr;
            gap: 1.5rem;
            align-items: start;
        }

        .panel {
            background: #fff;
            border: var(--border);
            border-radius: var(--border-radius);
            padding: 1.6rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.3rem;
        }

        .panel-header h2 {
            font-size: 1.45rem;
        }

        .panel-header p {
            color: #999;
            font-size: .85rem;
            margin-top: .2rem;
        }

        .panel-header>i {
            font-size: 1.8rem !important;
        }

        .map {
            height: 36rem;
            width: 100%;
            border: .1rem solid #ddd;
            border-radius: 1.4rem;
            overflow: hidden;
            background: #eee;
        }

        .map-note {
            margin-top: .9rem;
            padding: 1rem 1.2rem;
            border-radius: 1.2rem;
            background: #faf8f1;
            color: #777;
            font-size: .86rem;
            line-height: 1.55;
        }

        .courier-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.1rem;
            background: #faf8f1;
            border: .1rem solid #eee;
            border-radius: 1.3rem;
            margin-bottom: 1rem;
        }

        .courier-avatar {
            width: 4rem;
            height: 4rem;
            border-radius: 45% 55% 60% 40%;
            background: var(--main-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex: 0 0 4rem;
        }

        .courier-main {
            flex: 1;
            min-width: 0;
        }

        .courier-main strong {
            display: block;
            font-size: 1rem;
        }

        .courier-main small {
            display: block;
            margin-top: .25rem;
            color: #999;
            font-size: .82rem;
        }

        .call-btn {
            width: 3.4rem;
            height: 3.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: .1rem solid var(--green);
            border-radius: 50%;
            color: var(--green);
            background: #fff;
        }

        .call-btn:hover {
            background: #f0f7f0;
            transform: scale(1.05);
        }

        .delivery-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: .9rem 0;
            border-bottom: .1rem solid #eee;
        }

        .delivery-row:last-child {
            border-bottom: 0;
        }

        .delivery-row strong {
            font-size: .82rem;
            color: #999;
            display: block;
            margin-bottom: .2rem;
        }

        .delivery-row span {
            font-size: .95rem;
            line-height: 1.45;
        }

        .tracking-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .35rem .65rem;
            border-radius: var(--border-radius);
            font-size: .78rem;
        }

        .tracking-chip.online {
            color: var(--green);
            background: #f0f7f0
        }

        .tracking-chip.offline {
            color: #777;
            background: #f1f1f1
        }

        .timeline {
            position: relative;
            padding-left: 2.8rem;
        }

        .timeline:before {
            content: '';
            position: absolute;
            left: .78rem;
            top: .6rem;
            bottom: .6rem;
            width: .18rem;
            background: #ddd;
        }

        .event {
            position: relative;
            padding-bottom: 1.8rem;
        }

        .event:last-child {
            padding-bottom: .3rem;
        }

        .event-dot {
            position: absolute;
            left: -2.47rem;
            top: .1rem;
            width: 1.55rem;
            height: 1.55rem;
            background: #fff;
            border: .25rem solid #aaa;
            border-radius: 50%;
        }

        .event.active .event-dot {
            border-color: var(--green);
            background: #f0f7f0;
            box-shadow: 0 0 0 .4rem rgba(82, 120, 83, .08);
        }

        .event h4 {
            margin: 0;
            font-size: .95rem;
        }

        .event p {
            margin: .35rem 0;
            font-size: .85rem;
            color: #777;
            line-height: 1.55;
        }

        .event small {
            font-size: .78rem;
            color: #999;
            line-height: 1.45;
        }

        .note {
            margin-top: 1.4rem;
            padding: 1rem 1.2rem;
            background: #f7f4ec;
            border: .1rem solid #eee;
            border-radius: 1.2rem;
            font-size: .85rem;
            color: #777;
            line-height: 1.55;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            padding: .75rem 1.15rem;
            border: var(--border);
            border-radius: var(--border-radius);
            color: var(--main-color);
            background: #fff;
            cursor: pointer;
            font-size: .85rem;
        }

        .btn:hover {
            border: var(--border-hover);
            border-radius: var(--border-radius-hover);
            background: #f7f4ec;
            transform: translateY(-.15rem);
        }

        .btn.green {
            border-color: var(--green);
            color: var(--green);
        }

        .btn.green:hover {
            background: #f0f7f0;
        }

        .flash {
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            border: .1rem solid currentColor;
            border-radius: var(--border-radius);
            font-size: .9rem;
        }

        .flash.error {
            color: var(--red);
            background: #fff0ef;
        }

        .empty {
            text-align: center;
            padding: 3rem 1rem;
            color: #999;
            font-size: .9rem;
        }

        .empty i {
            display: block;
            font-size: 3rem;
            margin-bottom: .8rem;
        }

        .leaflet-popup-content {
            font-family: Poppins, sans-serif;
            font-size: 1rem;
            line-height: 1.5;
        }

        .leaflet-control-zoom a {
            color: var(--main-color) !important;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(1rem);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media(max-width:1000px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .topbar {
                padding: 1rem 2rem;
            }

            .main {
                padding: 1.6rem 2rem 2.5rem;
            }
        }

        @media(max-width:650px) {
            html {
                font-size: 52%;
            }

            .topbar {
                min-height: 6rem;
            }

            .topbar-left {
                gap: .8rem;
            }

            .page-title {
                display: none;
            }

            .customer-chip {
                display: none;
            }

            .main {
                padding: 1.3rem 1.2rem 2rem;
            }

            .hero {
                padding: 1.3rem;
            }

            .hero-top {
                flex-direction: column;
            }

            .info-grid {
                grid-template-columns: 1fr 1fr;
            }

            .map {
                height: 31rem;
            }

            .progress {
                margin-top: 1.4rem;
            }
        }

        @media(max-width:430px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .logo {
                font-size: 1.75rem;
            }

            .back-btn {
                width: 3.1rem;
                height: 3.1rem;
            }
        }

        /* =====================================================
           UKURAN KOMPAK - MENYESUAIKAN HALAMAN PEMBAYARAN
        ===================================================== */
        html {
            font-size: 62.5%;
        }

        .topbar {
            min-height: 58px;
            padding: 0 20px;
        }

        .main {
            max-width: 1050px;
            padding: 24px 15px 32px;
        }

        .logo {
            font-size: 20px;
            gap: 7px;
        }

        .logo i {
            font-size: 20px;
        }

        .back-btn {
            width: 34px;
            height: 34px;
        }

        .page-title h2 {
            font-size: 14px;
        }

        .page-title p {
            font-size: 10px;
        }

        .customer-chip {
            padding: 6px 9px;
            font-size: 10px;
        }

        .breadcrumb {
            font-size: 10px;
            margin-bottom: 12px;
        }

        .section-heading {
            margin-bottom: 12px;
        }

        .section-heading h2 {
            font-size: 17px;
        }

        .section-heading p {
            font-size: 10px;
        }

        .orders {
            gap: 12px;
            padding: 2px 1px 14px;
            margin-bottom: 8px;
        }

        .order-card {
            flex: 0 0 210px;
            border-radius: 8px;
            padding: 12px;
        }

        .order-card .order-top {
            margin-bottom: 7px;
        }

        .order-card .order-icon {
            width: 30px;
            height: 30px;
            font-size: 13px;
        }

        .order-card strong {
            font-size: 11px;
        }

        .order-card small {
            font-size: 9px;
            margin-top: 3px;
        }

        .order-status-mini {
            margin-top: 7px;
            padding: 3px 7px;
            font-size: 8px;
        }

        .hero {
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .hero-top {
            gap: 14px;
        }

        .eyebrow {
            font-size: 10px;
        }

        .hero h1 {
            font-size: 19px;
        }

        .address {
            font-size: 11px;
            margin-top: 6px;
        }

        .badge {
            padding: 5px 9px;
            font-size: 9px;
        }

        .progress {
            margin: 16px 0 12px;
        }

        .step {
            padding-top: 30px;
        }

        .step-dot {
            width: 21px;
            height: 21px;
            font-size: 8px;
        }

        .step span {
            font-size: 9px;
        }

        .step:before {
            top: 12px;
            height: 2px;
        }

        .info-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .info-box {
            min-height: 70px;
            padding: 10px 11px;
            border-radius: 8px;
        }

        .info-box strong {
            font-size: 8px;
            margin-bottom: 4px;
        }

        .info-box span {
            font-size: 10px;
        }

        .hero-actions {
            gap: 7px;
            margin-top: 12px;
        }

        .grid {
            gap: 16px;
        }

        .panel {
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .panel-header {
            margin-bottom: 12px;
        }

        .panel-header h2 {
            font-size: 15px;
        }

        .panel-header p {
            font-size: 9px;
        }

        .panel-header>i {
            font-size: 16px !important;
        }

        .map {
            height: 360px;
            border-radius: 8px;
        }

        .map-note {
            margin-top: 9px;
            padding: 10px 11px;
            font-size: 9px;
        }

        .courier-card {
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .courier-avatar {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            font-size: 15px;
        }

        .courier-main strong {
            font-size: 10px;
        }

        .courier-main small {
            font-size: 8px;
        }

        .call-btn {
            width: 32px;
            height: 32px;
            font-size: 11px;
        }

        .delivery-row {
            padding: 8px 0;
            gap: 10px;
        }

        .delivery-row strong {
            font-size: 8px;
            margin-bottom: 2px;
        }

        .delivery-row span {
            font-size: 10px;
        }

        .tracking-chip {
            padding: 3px 6px;
            font-size: 8px;
        }

        .timeline {
            padding-left: 25px;
        }

        .timeline:before {
            left: 7px;
            width: 2px;
        }

        .event {
            padding-bottom: 17px;
        }

        .event-dot {
            left: -21px;
            width: 13px;
            height: 13px;
            border-width: 2px;
        }

        .event h4 {
            font-size: 10px;
        }

        .event p {
            margin: 3px 0;
            font-size: 9px;
        }

        .event small {
            font-size: 8px;
        }

        .note {
            margin-top: 12px;
            padding: 9px 11px;
            font-size: 9px;
        }

        .btn {
            padding: 7px 11px;
            font-size: 9px;
        }

        .flash {
            padding: 9px 11px;
            margin-bottom: 14px;
            font-size: 9px;
        }

        .empty {
            padding: 35px 10px;
            font-size: 9px;
        }

        .empty i {
            font-size: 28px;
            margin-bottom: 8px;
        }

        @media(max-width:1000px) {
            .main {
                padding: 20px 15px 28px;
            }

            .topbar {
                padding: 0 15px;
            }
        }

        @media(max-width:650px) {
            html {
                font-size: 60%;
            }

            .topbar {
                min-height: 56px;
            }

            .main {
                padding: 16px 10px 22px;
            }

            .hero {
                padding: 15px;
            }

            .panel {
                padding: 15px;
            }

            .map {
                height: 310px;
            }
        }

        @media(max-width:430px) {
            .info-grid {
                grid-template-columns: 1fr 1fr;
            }

            .order-card {
                flex-basis: 190px;
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <div class="topbar-left">
            <a href="akun-pelanggan.php" class="back-btn" title="Kembali">
                <i class="fas fa-arrow-left"></i>
            </a>
            <a href="produk.php" class="logo">
                <i class="fas fa-mug-hot"></i> TOKU COFFEE
            </a>
            <span class="divider"></span>
            <div class="page-title">
                <h2>Lacak Pengiriman</h2>
                <p>Pantau pesanan seperti halaman tracking marketplace.</p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="customer-chip">
                <i class="fas fa-user-circle"></i>
                <span><?= eTrack($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Customer') ?></span>
            </div>
            <a href="produk.php" class="btn"><i class="fas fa-store"></i> Belanja</a>
        </div>
    </header>

    <main class="main">
        <div class="breadcrumb">
            <a href="akun-pelanggan.php">Akun Saya</a>
            <i class="fas fa-chevron-right"></i>
            <span>Pesanan</span>
            <i class="fas fa-chevron-right"></i>
            <strong>Pelacakan</strong>
        </div>

        <?php if ($error): ?>
            <div class="flash error"><i class="fas fa-circle-exclamation"></i> <?= eTrack($error) ?></div>
        <?php endif; ?>

        <div class="section-heading">
            <h2>Pesanan Saya</h2>
            <p>Pilih pesanan untuk melihat status, kurir, peta, dan riwayat perjalanan.</p>
        </div>

        <?php if ($orders): ?>
            <div class="orders">
                <?php foreach ($orders as $o): ?>
                    <?php
                    $miniStatus = $o['shipping_status'] ?: $o['status'];
                    $miniLabel = $labels[$miniStatus] ?? $miniStatus;
                    $miniClass = statusClassTrack($miniStatus);
                    ?>
                    <a class="order-card <?= (int)$o['id'] === $pesananId ? 'active' : '' ?>" href="tracking.php?pesanan=<?= (int)$o['id'] ?>">
                        <div class="order-top">
                            <span class="order-icon"><i class="fas fa-box"></i></span>
                            <span class="order-status-mini <?= eTrack($miniClass) ?>"><?= eTrack($miniLabel) ?></span>
                        </div>
                        <strong><?= eTrack($o['invoice']) ?></strong>
                        <small><?= eTrack($o['tanggal_pesanan']) ?></small>
                        <small><?= rupiahSalesTrack($o['total'] ?? 0) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($shipment): ?>
            <section class="hero">
                <div class="hero-top">
                    <div>
                        <div class="eyebrow">Pesanan <?= eTrack($shipment['invoice']) ?></div>
                        <h1><?= eTrack($currentLabel) ?></h1>
                        <div class="address"><i class="fas fa-location-dot"></i> <?= eTrack($shipment['alamat_pengiriman'] ?? $shipment['tujuan_label'] ?? '-') ?></div>
                    </div>
                    <span class="badge <?= eTrack($currentClass) ?>"><i class="fas fa-circle"></i><?= eTrack($currentLabel) ?></span>
                </div>

                <?php if (!$isCancelled): ?>
                    <div class="progress">
                        <?php foreach ($trackingSteps as $idx => $step): ?>
                            <?php $done = $progressIndex >= $idx;
                            $current = $progressIndex === $idx; ?>
                            <div class="step <?= $done ? 'done' : '' ?> <?= $current ? 'current' : '' ?>">
                                <span class="step-dot"><i class="fas <?= eTrack($step['icon']) ?>"></i></span>
                                <span><?= eTrack($step['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="cancel-note"><i class="fas fa-circle-xmark"></i> Pesanan ini dibatalkan dan tidak melanjutkan proses pengiriman.</div>
                <?php endif; ?>

                <div class="info-grid">
                    <div class="info-box"><strong>NO. RESI</strong><span><?= eTrack($shipment['resi'] ?: 'Belum tersedia') ?></span></div>
                    <div class="info-box"><strong>JASA / KURIR</strong><span><?= eTrack($shipment['kurir_nama'] ?: ($shipment['nama_jasa'] ?: '-')) ?></span></div>
                    <div class="info-box"><strong>ESTIMASI TIBA</strong><span><?= $shipment['estimasi_tiba'] ? eTrack(date('d M Y', strtotime($shipment['estimasi_tiba']))) : 'Belum tersedia' ?></span></div>
                    <div class="info-box"><strong>STATUS GPS</strong><span><i class="fas fa-location-crosshairs"></i> <?= eTrack(ucfirst($shipment['gps_status'] ?? 'offline')) ?></span></div>
                </div>

                <div class="hero-actions">
                    <?php if ($shipment['kurir_telepon']): ?>
                        <a class="btn green" href="tel:<?= eTrack($shipment['kurir_telepon']) ?>"><i class="fas fa-phone"></i> Hubungi Kurir</a>
                    <?php endif; ?>
                    <?php if ($shipment['resi'] && (stripos((string)$shipment['nama_jasa'], 'J&T') !== false || stripos((string)$shipment['nama_jasa'], 'JNT') !== false)): ?>
                        <a class="btn" target="_blank" rel="noopener" href="https://www.jet.co.id/track"><i class="fas fa-up-right-from-square"></i> Lacak di J&T</a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($shipment['pengiriman_id']): ?>
                <div class="grid">
                    <section class="panel">
                        <div class="panel-header">
                            <div>
                                <h2>Posisi Pengiriman</h2>
                                <p>Lokasi kurir terakhir yang tersimpan di sistem.</p>
                            </div>
                            <i class="fas fa-map-location-dot"></i>
                        </div>
                        <div id="trackMap" class="map"></div>
                        <div class="map-note"><i class="fas fa-circle-info"></i> Titik kurir dan tujuan hanya menggunakan koordinat yang tersedia pada data pengiriman Toku Coffee.</div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <div>
                                <h2>Detail Pengiriman</h2>
                                <p>Informasi kurir dan riwayat perjalanan.</p>
                            </div>
                            <i class="fas fa-truck-fast"></i>
                        </div>

                        <div class="courier-card">
                            <div class="courier-avatar"><i class="fas fa-user-shield"></i></div>
                            <div class="courier-main">
                                <strong><?= eTrack($shipment['kurir_nama'] ?: ($shipment['nama_jasa'] ?: 'Kurir')) ?></strong>
                                <small><?= eTrack($shipment['nama_jasa'] ?: '-') ?></small>
                            </div>
                            <?php if ($shipment['kurir_telepon']): ?>
                                <a class="call-btn" href="tel:<?= eTrack($shipment['kurir_telepon']) ?>" title="Hubungi kurir"><i class="fas fa-phone"></i></a>
                            <?php endif; ?>
                        </div>

                        <div class="delivery-row">
                            <div><strong>STATUS PENGIRIMAN</strong><span><?= eTrack($currentLabel) ?></span></div>
                            <span class="tracking-chip <?= ($shipment['gps_status'] ?? 'offline') === 'online' ? 'online' : 'offline' ?>"><i class="fas fa-location-dot"></i><?= eTrack(ucfirst($shipment['gps_status'] ?? 'offline')) ?></span>
                        </div>
                        <div class="delivery-row">
                            <div><strong>TUJUAN</strong><span><?= eTrack($shipment['tujuan_label'] ?: ($shipment['alamat_pengiriman'] ?? '-')) ?></span></div>
                        </div>
                        <div class="delivery-row">
                            <div><strong>UPDATE GPS TERAKHIR</strong><span><?= $shipment['gps_update'] ? eTrack(waktuTrack($shipment['gps_update'])) : '-' ?></span></div>
                        </div>

                        <div style="margin:1.3rem 0 1rem;border-top:.1rem solid #eee"></div>

                        <div class="panel-header" style="margin-bottom:1rem">
                            <div>
                                <h2>Perjalanan Paket</h2>
                                <p>Riwayat status dari sistem.</p>
                            </div>
                            <i class="fas fa-route"></i>
                        </div>

                        <?php if ($events): ?>
                            <div class="timeline">
                                <?php foreach (array_reverse($events) as $idx => $event): ?>
                                    <div class="event <?= $idx === 0 ? 'active' : '' ?>">
                                        <span class="event-dot"></span>
                                        <h4><?= eTrack($event['judul']) ?></h4>
                                        <p><?= eTrack($event['keterangan']) ?></p>
                                        <small><?= eTrack(waktuTrack($event['waktu_event'])) ?><?= $event['lokasi'] ? ' • ' . eTrack($event['lokasi']) : '' ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty"><i class="fas fa-route"></i>Belum ada update tracking dari kurir.</div>
                        <?php endif; ?>

                        <div class="note"><i class="fas fa-circle-info"></i> Status mengikuti pembaruan dari admin/staff. Saat status menjadi <b>Pesanan Diterima</b>, pesanan dianggap selesai.</div>
                    </section>
                </div>
            <?php else: ?>
                <section class="panel">
                    <div class="empty"><i class="fas fa-box-open"></i>Pesanan ini belum memiliki jasa pengiriman.</div>
                </section>
            <?php endif; ?>

        <?php elseif (!$error): ?>
            <section class="panel">
                <div class="empty"><i class="fas fa-box-open"></i>Belum ada pesanan yang dapat dilacak.</div>
            </section>
        <?php endif; ?>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <?php if ($shipment && $shipment['pengiriman_id'] && $shipment['gps_lat'] !== null && $shipment['gps_lng'] !== null): ?>
        <script>
            const courierLat = <?= (float)$shipment['gps_lat'] ?>;
            const courierLng = <?= (float)$shipment['gps_lng'] ?>;

            const m = L.map('trackMap').setView([courierLat, courierLng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(m);

            const courier = L.marker([courierLat, courierLng])
                .addTo(m)
                .bindPopup(
                    '<b><?= eTrack($shipment['nama_jasa'] ?: $shipment['kurir_nama']) ?></b><br>' +
                    'Lokasi kurir saat ini<br>' +
                    'GPS: ' + courierLat.toFixed(7) + ', ' + courierLng.toFixed(7)
                )
                .openPopup();

            <?php if ($shipment['tujuan_lat'] !== null && $shipment['tujuan_lng'] !== null): ?>
                const tujuanLat = <?= (float)$shipment['tujuan_lat'] ?>;
                const tujuanLng = <?= (float)$shipment['tujuan_lng'] ?>;

                const dest = L.marker([tujuanLat, tujuanLng])
                    .addTo(m)
                    .bindPopup(
                        '<b>Tujuan Pengiriman</b><br>' +
                        '<?= eTrack($shipment['tujuan_label']) ?>'
                    );

                L.polyline([
                    [courierLat, courierLng],
                    [tujuanLat, tujuanLng]
                ], {
                    dashArray: '8 7'
                }).addTo(m);

                m.fitBounds([
                    [courierLat, courierLng],
                    [tujuanLat, tujuanLng]
                ], {
                    padding: [20, 20]
                });
            <?php endif; ?>

            setTimeout(() => location.reload(), 30000);
        </script>
    <?php else: ?>
        <script>
            const el = document.getElementById('trackMap');
            if (el) {
                el.innerHTML = `
                    <div style="height:100%;display:flex;align-items:center;justify-content:center;text-align:center;padding:2rem;color:#999;">
                        <div>
                            <i class="fas fa-location-crosshairs" style="font-size:3.5rem;display:block;margin-bottom:1rem;"></i>
                            Lokasi GPS kurir belum tersedia.
                        </div>
                    </div>
                `;
            }
        </script>
    <?php endif; ?>

</body>

</html>
