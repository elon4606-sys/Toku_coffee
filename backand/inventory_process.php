<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

requireRole(['admin', 'staff']);

$action = $_POST['action'] ?? '';

if ($action === 'mutasi') {

    $produk_id = intval($_POST['produk_id']);
    $gudang_id = intval($_POST['gudang_id']);
    $tipe = $_POST['tipe'];
    $jumlah = intval($_POST['jumlah']);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($jumlah <= 0) {
        header("Location: ../inventory.php?error=jumlah_tidak_valid");
        exit;
    }

    $conn->begin_transaction();

    try {

        $stmt = $conn->prepare("
            SELECT stok
            FROM inventory
            WHERE produk_id = ?
            AND gudang_id = ?
            FOR UPDATE
        ");

        $stmt->bind_param(
            "ii",
            $produk_id,
            $gudang_id
        );

        $stmt->execute();

        $result = $stmt->get_result();
        $inventory = $result->fetch_assoc();

        if (!$inventory) {
            throw new Exception("Data inventory tidak ditemukan");
        }

        $stok_sebelum = intval($inventory['stok']);
        $stok_sesudah = $stok_sebelum;

        if ($tipe === 'Masuk') {

            $stok_sesudah =
                $stok_sebelum + $jumlah;
        } elseif ($tipe === 'Keluar') {

            $stok_sesudah =
                $stok_sebelum - $jumlah;

            if ($stok_sesudah < 0) {
                throw new Exception("Stok tidak mencukupi");
            }
        } elseif ($tipe === 'Penyesuaian') {

            $stok_sesudah = $jumlah;
        }

        $stmt = $conn->prepare("
            UPDATE inventory
            SET stok = ?
            WHERE produk_id = ?
            AND gudang_id = ?
        ");

        $stmt->bind_param(
            "iii",
            $stok_sesudah,
            $produk_id,
            $gudang_id
        );

        $stmt->execute();

        /* update stok produk */

        $stmt = $conn->prepare("
            UPDATE produk
            SET stok = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ii",
            $stok_sesudah,
            $produk_id
        );

        $stmt->execute();

        /* simpan riwayat */

        $user_id = $_SESSION['user_id'];

        $stmt = $conn->prepare("
            INSERT INTO mutasi_stok
            (
                produk_id,
                gudang_id,
                user_id,
                tipe,
                jumlah,
                stok_sebelum,
                stok_sesudah,
                keterangan
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iiisiiss",
            $produk_id,
            $gudang_id,
            $user_id,
            $tipe,
            $jumlah,
            $stok_sebelum,
            $stok_sesudah,
            $keterangan
        );

        $stmt->execute();

        $conn->commit();

        header("Location: ../inventory.php?success=stok_diperbarui");
        exit;
    } catch (Exception $e) {

        $conn->rollback();

        header(
            "Location: ../inventory.php?error=" .
                urlencode($e->getMessage())
        );

        exit;
    }
}

header("Location: ../inventory.php");
