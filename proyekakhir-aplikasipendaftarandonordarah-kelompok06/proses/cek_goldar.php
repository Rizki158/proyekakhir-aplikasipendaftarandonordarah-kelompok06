<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $status = $_POST['tahu_golongan'] ?? '';

    if ($status === 'sudah') {

        $_SESSION['sudah_tahu_golongan'] = true;

        header("Location: halaman1.php");
        exit;
    }

    if ($status === 'belum') {

        $_SESSION['sudah_tahu_golongan'] = false;

        header("Location: konsultasi_online.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cek Golongan Darah</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-red-300 to-red-500 min-h-screen flex items-center justify-center px-4">

<div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl p-10">

    <div class="text-center mb-8">

        <div class="w-20 h-20 mx-auto rounded-full bg-red-100 flex items-center justify-center text-4xl mb-4">
            🩸
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-3">
            Informasi Golongan Darah
        </h1>

        <p class="text-gray-600 leading-relaxed">
            Sebelum melanjutkan proses donor darah,
            apakah Anda sudah mengetahui golongan darah Anda?
        </p>

    </div>

    <form method="POST">

        <button
            type="submit"
            name="tahu_golongan"
            value="sudah"
            class="w-full mb-4 bg-red-500 hover:bg-red-600 text-white font-semibold py-4 rounded-xl transition duration-200 shadow"
        >
            Ya, Saya Sudah Tahu
        </button>

        <button
            type="submit"
            name="tahu_golongan"
            value="belum"
            class="w-full bg-white border-2 border-red-500 hover:bg-red-50 text-red-500 font-semibold py-4 rounded-xl transition duration-200"
        >
            Belum Tahu Golongan Darah
        </button>

    </form>

    <div class="mt-8 bg-red-50 border border-red-100 rounded-2xl p-5">

        <h3 class="font-bold text-red-600 mb-2">
            Kenapa ini penting?
        </h3>

        <p class="text-sm text-gray-700 leading-relaxed">
            Informasi golongan darah diperlukan untuk proses donor,
            pengecekan stok darah, dan pencocokan kebutuhan pasien.
            Jika belum mengetahui golongan darah, Anda dapat melakukan
            konsultasi online dan penjadwalan pemeriksaan terlebih dahulu.
        </p>

    </div>

</div>

</body>
</html>