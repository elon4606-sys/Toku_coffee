<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: login/login.php");
        exit;
    }
}

function requireRole($roles = [])
{
    requireLogin();

    if (!isset($_SESSION['role'])) {
        session_destroy();

        header("Location: login/login.php");
        exit;
    }

    if (
        !empty($roles) &&
        !in_array($_SESSION['role'], $roles)
    ) {

        header("Location: ../dashboard.php?error=akses_ditolak");
        exit;
    }
}

function currentUser()
{
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['full_name'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ];
}
