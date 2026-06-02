<?php
require_once '../config/auth.php';
requireLogin();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Konsultasi Online</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-red-300 to-red-500 min-h-screen flex items-center justify-center px-4">

<div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl p-10">

    <div class="text-center">

        <div class="text-6xl mb-5">
            👨‍⚕️
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-4">
            Konsultasi & Tes Golongan Darah
        </h1>

        <p class="text-gray-600 leading-relaxed mb-8">
            Anda belum mengetahui golongan darah.
            Silakan lakukan konsultasi online terlebih dahulu
            untuk penjadwalan pemeriksaan golongan darah
            di PMI terdekat.
        </p>

    </div>

    <div class="grid md:grid-cols-2 gap-4 mb-8">

        <div class="border rounded-2xl p-5">
            <h3 class="font-bold text-gray-800 mb-2">
                📅 Jadwal Konsultasi
            </h3>

            <p class="text-sm text-gray-600">
                Pilih jadwal konsultasi online sesuai waktu yang tersedia.
            </p>
        </div>

        <div class="border rounded-2xl p-5">
            <h3 class="font-bold text-gray-800 mb-2">
                🩸 Tes Golongan Darah
            </h3>

            <p class="text-sm text-gray-600">
                Pemeriksaan dilakukan oleh petugas PMI secara aman dan cepat.
            </p>
        </div>

    </div>

    <div class="flex flex-col md:flex-row gap-4">

        <a href="../proses/jadwal_konsultasi.php"
           class="flex-1 bg-red-500 hover:bg-red-600 text-white text-center font-semibold py-4 rounded-xl transition">
            Jadwalkan Konsultasi
        </a>

        <a href="halaman1.php"
           class="flex-1 border-2 border-red-500 hover:bg-red-50 text-red-500 text-center font-semibold py-4 rounded-xl transition">
            Lewati Sementara
        </a>

    </div>

</div>

</body>
</html>