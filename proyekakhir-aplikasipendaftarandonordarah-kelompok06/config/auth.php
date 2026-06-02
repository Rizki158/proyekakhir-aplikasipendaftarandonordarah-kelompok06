<?php
require_once __DIR__ . '/koneksi.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getRole() {
    return $_SESSION['role'] ?? null;
}

function getNama() {
    return $_SESSION['nama'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}
?>