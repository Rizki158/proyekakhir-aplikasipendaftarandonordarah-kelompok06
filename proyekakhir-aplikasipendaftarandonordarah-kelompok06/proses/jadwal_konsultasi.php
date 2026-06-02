<?php
require_once('../config/koneksi.php');
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Form Jadwal Konsultasi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-rose-300 to-red-500 py-10 px-4">

<div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-2xl p-8 md:p-10">

    <div class="text-center mb-10">
        <div class="text-5xl mb-4">🩺</div>
        <h2 class="text-3xl font-bold text-gray-800">
            Form Jadwal Konsultasi & Tes Darah
        </h2>

        <p class="text-gray-500 mt-2">
            Lengkapi data berikut sebelum melakukan pemeriksaan.
        </p>
    </div>

<form action="simpan_konsultasi.php" method="POST" class="space-y-8">

    <!-- Identitas -->
    <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
            Identitas Pasien
        </h3>

        <div class="grid md:grid-cols-2 gap-4">

            <input
                type="text"
                name="nama"
                placeholder="Nama lengkap"
                required
                class="w-full rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
            >

            <input
                type="date"
                name="tgl_lahir"
                required
                class="w-full rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
            >

            <input
                type="text"
                name="telepon"
                placeholder="Nomor telepon"
                required
                class="w-full rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
            >

        </div>
    </div>

    <!-- Jadwal -->
    <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
            Detail Jadwal
        </h3>

        <div class="grid md:grid-cols-3 gap-4">

            <input
                type="date"
                name="tanggal_tes"
                required
                class="rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
            >

            <input
                type="time"
                name="jam_tes"
                required
                class="rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
            >

            <input
                type="text"
                name="lokasi"
                placeholder="Nama klinik / laboratorium"
                required
                class="rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
            >
        </div>
    </div>

    <!-- Puasa -->
    <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
            Riwayat Puasa
        </h3>

        <select
            name="puasa"
            required
            class="w-full rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
        >
            <option value="">Pilih</option>
            <option>Sudah puasa 8–12 jam</option>
            <option>Belum puasa</option>
            <option>Hanya minum air putih</option>
        </select>
    </div>

    <!-- Keluhan -->
    <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
            Riwayat Medis & Keluhan
        </h3>

        <textarea
            name="keluhan"
            rows="4"
            placeholder="Kondisi kesehatan / tujuan tes / keluhan saat ini"
            class="w-full rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
        ></textarea>
    </div>

    <!-- Obat -->
    <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
            Konsumsi Obat / Vitamin
        </h3>

        <textarea
            name="obat"
            rows="4"
            placeholder="Tuliskan obat, vitamin, atau suplemen"
            class="w-full rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
        ></textarea>
    </div>

    <!-- Alergi -->
    <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
            Riwayat Alergi
        </h3>

        <textarea
            name="alergi"
            rows="4"
            placeholder="Alergi lateks / plester / lainnya"
            class="w-full rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
        ></textarea>
    </div>

    <!-- Kondisi khusus -->
    <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
            Kondisi Khusus (Opsional)
        </h3>

        <textarea
            name="kondisi_khusus"
            rows="4"
            placeholder="Hamil / menyusui / jadwal menstruasi"
            class="w-full rounded-xl border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-red-400"
        ></textarea>
    </div>

    <!-- Consent -->
    <div class="bg-red-50 rounded-2xl p-5">

        <h3 class="text-xl font-semibold text-gray-800 mb-3">
            Persetujuan
        </h3>

        <label class="flex items-start gap-3">

            <input
                type="checkbox"
                required
                class="mt-1 w-5 h-5 accent-red-500"
            >

            <span class="text-gray-700">
                Saya menyatakan data yang diberikan benar
                dan bersedia menjalani prosedur tes darah.
            </span>

        </label>
    </div>

    <!-- Button -->
    <button
        type="submit"
        class="w-full bg-red-500 hover:bg-red-600 transition text-white font-semibold py-4 rounded-2xl text-lg shadow-md"
    >
        Kirim Jadwal Konsultasi
    </button>

</form>

</div>

</body>
</html>