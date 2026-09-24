<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

function redirectBack($url)
{
    header("Location: " . $url);
    exit;
}

function getUserById($id)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id, full_name, username, email, role, status
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}
