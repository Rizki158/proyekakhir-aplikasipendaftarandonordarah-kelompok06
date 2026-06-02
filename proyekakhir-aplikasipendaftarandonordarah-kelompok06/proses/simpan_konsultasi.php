<?php
require_once('../config/koneksi.php');

$nama = $_POST['nama'];
$tgl_lahir = $_POST['tgl_lahir'];
$telepon = $_POST['telepon'];

$tanggal_tes = $_POST['tanggal_tes'];
$jam_tes = $_POST['jam_tes'];
$lokasi = $_POST['lokasi'];

$puasa = $_POST['puasa'];

$keluhan = $_POST['keluhan'];
$obat = $_POST['obat'];
$alergi = $_POST['alergi'];
$kondisi_khusus = $_POST['kondisi_khusus'];

$stmt = $conn->prepare("
INSERT INTO jadwal_konsultasi
(
nama,
tgl_lahir,
telepon,
rekam_medis,
tanggal_tes,
jam_tes,
lokasi,
puasa,
keluhan,
obat,
alergi,
kondisi_khusus
)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
    "ssssssssssss",
    $nama,
    $tgl_lahir,
    $telepon,
    $rekam_medis,
    $tanggal_tes,
    $jam_tes,
    $lokasi,
    $puasa,
    $keluhan,
    $obat,
    $alergi,
    $kondisi_khusus
);

$berhasil = $stmt->execute();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Status Konsultasi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-rose-300 to-red-500 flex items-center justify-center px-4">

<div class="bg-white max-w-lg w-full rounded-3xl shadow-2xl p-8 text-center">

<?php if($berhasil): ?>

    <div class="text-6xl mb-4">✅</div>

    <h2 class="text-3xl font-bold text-gray-800 mb-3">
        Data Berhasil Dikirim
    </h2>

    <p class="text-gray-600 leading-relaxed mb-6">
        Terima kasih
        <span class="font-semibold text-red-500">
            <?= htmlspecialchars($nama); ?>
        </span>

        data konsultasi dan jadwal tes darah Anda
        sudah berhasil kami terima.
    </p>

    <div class="bg-red-50 rounded-2xl p-4 text-left mb-6">

        <p class="text-sm text-gray-700 mb-2">
            📅 <strong>Tanggal Tes:</strong>
            <?= htmlspecialchars($tanggal_tes); ?>
        </p>

        <p class="text-sm text-gray-700 mb-2">
            ⏰ <strong>Jam:</strong>
            <?= htmlspecialchars($jam_tes); ?>
        </p>

        <p class="text-sm text-gray-700">
            🏥 <strong>Lokasi:</strong>
            <?= htmlspecialchars($lokasi); ?>
        </p>

    </div>

    <a
        href="../user/dashboard.php"
        class="block w-full bg-red-500 hover:bg-red-600 transition text-white font-semibold py-4 rounded-2xl"
    >
        Kembali ke Beranda
    </a>

<?php else: ?>

    <div class="text-6xl mb-4">❌</div>

    <h2 class="text-3xl font-bold text-gray-800 mb-3">
        Gagal Menyimpan
    </h2>

    <p class="text-gray-600 mb-6">
        <?= $conn->error; ?>
    </p>

    <a
        href="jadwal_konsultasi.php"
        class="block w-full bg-red-500 hover:bg-red-600 transition text-white font-semibold py-4 rounded-2xl"
    >
        Coba Lagi
    </a>

<?php endif; ?>

</div>

</body>
</html>