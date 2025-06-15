<?php
// Buat file baru: delete_payment.php
// filepath: c:\xampp\htdocs\Paling_Baru\delete_payment.php

require_once 'config.php';
require_once 'functions.php';

// Mulai session jika belum
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Validasi user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Anda harus login terlebih dahulu.";
    header("Location: index.php");
    exit;
}

// Validasi parameter ID
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID Pembayaran tidak valid.";
    header("Location: payments.php");
    exit;
}

$paymentId = (int)$_GET['id'];

// Hapus pembayaran
$query = "DELETE FROM payments WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $paymentId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success'] = "Pembayaran berhasil dihapus.";
} else {
    $_SESSION['error'] = "Gagal menghapus pembayaran. " . $conn->error;
}

// Redirect kembali ke halaman payments dengan parameter yang sama
$redirectParams = [];
foreach (['month', 'year', 'area', 'status', 'user', 'q'] as $param) {
    if (isset($_GET[$param]) && !empty($_GET[$param])) {
        $redirectParams[$param] = $_GET[$param];
    }
}

$redirectUrl = "payments.php";
if (!empty($redirectParams)) {
    $redirectUrl .= "?" . http_build_query($redirectParams);
}

header("Location: $redirectUrl");
exit;
?>