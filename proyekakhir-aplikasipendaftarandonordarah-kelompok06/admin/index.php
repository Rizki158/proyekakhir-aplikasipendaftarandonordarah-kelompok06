<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

if (!isLoggedIn() || getRole() !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$namaAdmin = getNama();

// Statistik
$totalUser    = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='user'")->fetch_assoc()['total'];
$totalDonor   = $conn->query("SELECT COUNT(*) as total FROM pendonor")->fetch_assoc()['total'];
$totalProvinsi= $conn->query("SELECT COUNT(*) as total FROM provinsi")->fetch_assoc()['total'];
$totalStok    = $conn->query("SELECT SUM(jumlah) as total FROM stok_darah")->fetch_assoc()['total'] ?? 0;

// Stok darah
$stokResult = $conn->query("SELECT golongan_darah, SUM(jumlah) as jumlah FROM stok_darah GROUP BY golongan_darah ORDER BY golongan_darah");
$stokList = [];
while ($s = $stokResult->fetch_assoc()) $stokList[] = $s;

// Pendonor per provinsi — SEMUA provinsi tampil meski 0
$provResult = $conn->query("
    SELECT p.id, p.nama_provinsi as provinsi,
           COUNT(dn.id) as jumlah
    FROM provinsi p
    LEFT JOIN kota k ON k.id_provinsi = p.id
    LEFT JOIN pendonor dn ON dn.id_kota = k.id
    GROUP BY p.id, p.nama_provinsi
    ORDER BY jumlah DESC, p.nama_provinsi ASC
");
$provList = [];
while ($p = $provResult->fetch_assoc()) $provList[] = $p;

// Stok per kota
$stokKotaResult = $conn->query("
    SELECT k.id as id_kota, k.nama_kota, p.nama_provinsi,
      SUM(CASE WHEN sd.golongan_darah='A'  THEN sd.jumlah ELSE 0 END) as stok_a,
      SUM(CASE WHEN sd.golongan_darah='B'  THEN sd.jumlah ELSE 0 END) as stok_b,
      SUM(CASE WHEN sd.golongan_darah='O'  THEN sd.jumlah ELSE 0 END) as stok_o,
      SUM(CASE WHEN sd.golongan_darah='AB' THEN sd.jumlah ELSE 0 END) as stok_ab,
      SUM(sd.jumlah) as total
    FROM kota k
    JOIN provinsi p ON p.id = k.id_provinsi
    LEFT JOIN stok_darah sd ON sd.id_kota = k.id
    GROUP BY k.id, k.nama_kota, p.nama_provinsi
    ORDER BY total DESC
");
$stokKotaList = [];
while ($sk = $stokKotaResult->fetch_assoc()) $stokKotaList[] = $sk;

// Data users
$usersResult = $conn->query("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC");
$usersList = [];
while ($u = $usersResult->fetch_assoc()) $usersList[] = $u;

// Data pendonor dengan kode format LPAD
$donorsResult = $conn->query("
    SELECT
        dn.*,
        k.nama_kota,
        p.nama_provinsi,
        p.id as pid,
        k.id as kid,
        u.id as uid,
        CONCAT(
            LPAD(IFNULL(p.id,0), 2, '0'), '-',
            LPAD(IFNULL(k.id,0), 2, '0'), '-',
            IFNULL(YEAR(u.created_at), YEAR(NOW())), '-',
            LPAD(IFNULL(u.id,0), 2, '0'),
            LPAD(dn.id, 2, '0')
        ) as kode_format
    FROM pendonor dn
    LEFT JOIN kota k ON k.id = dn.id_kota
    LEFT JOIN provinsi p ON p.id = dn.id_provinsi
    LEFT JOIN users u ON u.id = dn.id_user
    ORDER BY dn.created_at DESC
");
$donorsList = [];
while ($d = $donorsResult->fetch_assoc()) $donorsList[] = $d;

// Log stok untuk laporan
$logStokResult = $conn->query("
    SELECT ls.*, k.nama_kota, p.nama_provinsi
    FROM log_stok ls
    JOIN kota k ON k.id = ls.id_kota
    JOIN provinsi p ON p.id = k.id_provinsi
    ORDER BY ls.created_at DESC
    LIMIT 100
");
$logStokList = [];
while ($ls = $logStokResult->fetch_assoc()) $logStokList[] = $ls;

// Log pendonor untuk riwayat laporan (setiap pendonor = +1 kantong)
$logDonorResult = $conn->query("
    SELECT dn.id, dn.nama_lengkap, dn.kode_donor, dn.golongan_darah,
           dn.created_at,
           CONCAT(
               LPAD(IFNULL(p.id,0), 2, '0'), '-',
               LPAD(IFNULL(k.id,0), 2, '0'), '-',
               IFNULL(YEAR(u.created_at), YEAR(NOW())), '-',
               LPAD(IFNULL(u.id,0), 2, '0'),
               LPAD(dn.id, 2, '0')
           ) as kode_format
    FROM pendonor dn
    LEFT JOIN kota k ON k.id = dn.id_kota
    LEFT JOIN provinsi p ON p.id = dn.id_provinsi
    LEFT JOIN users u ON u.id = dn.id_user
    ORDER BY dn.created_at DESC
    LIMIT 50
");
$logDonorList = [];
while ($ld = $logDonorResult->fetch_assoc()) $logDonorList[] = $ld;

// Notifikasi dari session
$notifStok = $_SESSION['notif_stok'] ?? null;
unset($_SESSION['notif_stok']);

// Jadwal PMI hari ini
$hariIni  = date('N'); // 1=Senin, 7=Minggu
$namaHari = ['','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
$isLibur  = ($hariIni == 6 || $hariIni == 7);

// Kota list untuk dropdown
$kotaListResult = $conn->query("SELECT k.id, k.nama_kota, p.nama_provinsi FROM kota k JOIN provinsi p ON p.id = k.id_provinsi ORDER BY p.nama_provinsi, k.nama_kota");
$kotaDropdown = [];
while ($kt = $kotaListResult->fetch_assoc()) $kotaDropdown[] = $kt;
?>
<!DOCTYPE html>
<html lang="id" id="html-root">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - Ayodonor PMI</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
  * { font-family: 'Inter', sans-serif; }
  .dark { background-color: #1a1a2e; color: #e0e0e0; }
  .dark .sidebar   { background-color: #16213e; }
  .dark .topbar    { background-color: #16213e; border-color: #0f3460; }
  .dark .main-card { background-color: #16213e; border-color: #0f3460; }
  .dark .table-row-even { background-color: #1a1a2e; }
  .dark .table-row-odd  { background-color: #16213e; }
  .dark .text-dark  { color: #e0e0e0; }
  .dark .border-dark { border-color: #0f3460; }
  .dark input, .dark select, .dark textarea {
    background-color: #0f3460; color: #e0e0e0; border-color: #1a4a8a;
  }
  html { scroll-behavior: smooth; }
  .modal-scroll { max-height: 80vh; overflow-y: auto; }
  .nav-item { transition: all 0.2s; }
  .nav-item:hover { background-color: rgba(220,38,38,0.15); }
  .nav-item.active { background-color: rgba(220,38,38,0.25); border-left: 3px solid #DC2626; }
  @keyframes slideIn { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
  .notif-anim { animation: slideIn 0.3s ease; }
</style>
</head>
<body class="bg-gray-100 min-h-screen" id="body-root">

<!-- TOPBAR -->
<header class="topbar fixed top-0 left-0 right-0 bg-white shadow-sm z-50 h-14 flex items-center px-6 gap-4 border-b border-gray-200">
  <div class="flex items-center gap-2 flex-shrink-0">
    <?php if (file_exists('../assets/logo_PMI.png')) : ?>
    <img src="../assets/logo_PMI.png" alt="PMI" class="h-8 w-8 object-contain">
    <?php else : ?>
    <svg width="32" height="32" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
      <path d="M50 5C25 5 5 25 5 50S25 95 50 95 95 75 95 50 75 5 50 5Z" fill="white" stroke="#DC2626" stroke-width="4"/>
      <rect x="42" y="25" width="16" height="50" rx="4" fill="#DC2626"/>
      <rect x="25" y="42" width="50" height="16" rx="4" fill="#DC2626"/>
    </svg>
    <?php endif; ?>
    <span class="font-bold text-gray-800 text-sm text-dark leading-tight">Palang Merah<br>Indonesia</span>
  </div>
  <div class="flex-1"></div>
  <div class="flex items-center gap-3">
    <span class="text-sm font-semibold text-gray-700 text-dark">Dashboard Admin</span>
    <button onclick="bukaSettings()" title="Pengaturan"
            class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center hover:bg-red-50 transition">
      <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
    </button>
    <a href="../logout.php" class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center hover:bg-red-200 transition" title="Logout">
      <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
    </a>
  </div>
</header>

<!-- SETTINGS -->
<div id="page-settings" class="fixed inset-0 bg-gray-100 z-40 hidden flex-col" style="padding-top:56px">
  <div class="topbar bg-white shadow-sm h-14 flex items-center px-6 gap-4 border-b border-gray-200">
    <button onclick="tutupSettings()" class="flex items-center gap-2 text-gray-600 hover:text-red-600 transition text-sm font-medium">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Kembali
    </button>
    <span class="font-bold text-gray-800 text-lg">Pengaturan</span>
  </div>
  <div class="flex-1 p-8 overflow-auto">
    <div class="max-w-md mx-auto bg-white rounded-xl shadow p-8 main-card">
      <h2 class="text-lg font-bold text-gray-800 text-dark mb-6 pb-2 border-b border-dark">Preferensi Tampilan</h2>
      <div class="flex items-center justify-between mb-6">
        <div>
          <p class="font-semibold text-gray-700 text-dark text-sm">Tema</p>
          <p class="text-gray-400 text-xs mt-0.5">Pilih tema tampilan dashboard</p>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs text-gray-500 text-dark">Terang</span>
          <button onclick="toggleTheme()" id="theme-toggle"
                  class="relative w-14 h-7 rounded-full transition-colors duration-300 focus:outline-none"
                  style="background-color: #e5e7eb;">
            <span id="theme-circle" class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow transition-transform duration-300"></span>
          </button>
          <span class="text-xs text-gray-500 text-dark">Gelap</span>
        </div>
      </div>
      <div class="border-t border-gray-200 border-dark pt-4">
        <p class="text-xs text-gray-400">Tema aktif: <span id="tema-label" class="font-semibold text-gray-600 text-dark">Terang</span></p>
      </div>
    </div>
  </div>
</div>

<!-- LAYOUT -->
<div class="flex min-h-screen pt-14">

  <!-- SIDEBAR -->
  <aside class="sidebar fixed left-0 top-14 bottom-0 w-52 bg-white shadow-sm border-r border-gray-200 z-30 flex flex-col">
    <nav class="flex-1 py-4 px-3">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-3">Menu</p>
      <button onclick="tampilHalaman('home')" id="nav-home"
              class="nav-item active w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-700 text-dark mb-1">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        Home
      </button>
      <button onclick="tampilHalaman('service')" id="nav-service"
              class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-700 text-dark mb-1">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Service
      </button>
      <button onclick="tampilHalaman('stok')" id="nav-stok"
              class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-700 text-dark mb-1">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Stok Darah
      </button>
      <button onclick="tampilHalaman('laporan')" id="nav-laporan"
              class="nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-700 text-dark mb-1">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Laporan
      </button>
    </nav>
    <div class="px-3 py-4 border-t border-gray-200 border-dark">
      <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-red-50">
        <div class="w-7 h-7 rounded-full bg-red-600 flex items-center justify-center text-white text-xs font-bold">
          <?php echo strtoupper(substr($namaAdmin, 0, 1)); ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-xs font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($namaAdmin); ?></p>
          <p class="text-xs text-gray-400">Admin</p>
        </div>
      </div>
    </div>
  </aside>

  <!-- KONTEN -->
  <main class="ml-52 flex-1 p-6">

    <!-- ═══ HOME ═══ -->
    <div id="hal-home">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 text-dark">Dashboard</h1>
        <p class="text-gray-500 text-sm">Selamat datang, <?php echo htmlspecialchars($namaAdmin); ?></p>
      </div>

      <!-- Stat Cards -->
      <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="main-card bg-white rounded-xl shadow p-5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <span class="text-sm text-gray-500">Total User</span>
          </div>
          <p class="text-3xl font-bold text-gray-800 text-dark"><?php echo $totalUser; ?></p>
        </div>
        <div class="main-card bg-white rounded-xl shadow p-5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </div>
            <span class="text-sm text-gray-500">Total Pendonor</span>
          </div>
          <p class="text-3xl font-bold text-gray-800 text-dark"><?php echo $totalDonor; ?></p>
        </div>
        <div class="main-card bg-white rounded-xl shadow p-5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <span class="text-sm text-gray-500">Total Provinsi</span>
          </div>
          <p class="text-3xl font-bold text-gray-800 text-dark"><?php echo $totalProvinsi; ?></p>
        </div>
        <div class="main-card bg-white rounded-xl shadow p-5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <span class="text-sm text-gray-500">Total Stok Darah</span>
          </div>
          <p class="text-3xl font-bold text-gray-800 text-dark"><?php echo $totalStok; ?></p>
        </div>
      </div>

      <!-- ═══ SECTION 1: Jadwal PMI (kiri) + Pendonor per Provinsi (kanan) ═══ -->
      <div class="grid grid-cols-2 gap-6 mb-6">

        <!-- Kiri: Jadwal PMI -->
        <div class="main-card bg-white rounded-xl shadow p-6">
          <h3 class="text-base font-bold text-red-600 mb-4">Jadwal PMI</h3>

          <?php if ($isLibur) : ?>
          <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
            <div class="flex items-center gap-2 mb-1">
              <i class="bi bi-exclamation-circle-fill"></i>
              <span class="font-bold text-red-600 text-sm">Hari Ini Libur</span>
            </div>
            <p class="text-red-500 text-xs">PMI tidak beroperasi pada hari Sabtu &amp; Minggu</p>
          </div>
          <?php else : ?>
          <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
            <div class="flex items-center gap-2 mb-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="green" viewBox="0 0 16 16">
                <path d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0"/>
                <path d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"/>
              </svg>
              <span class="font-bold text-green-600 text-sm">PMI Buka Hari Ini (<?php echo $namaHari[$hariIni]; ?>)</span>
            </div>
            <?php if ($hariIni <= 3) : ?>
            <p class="text-green-600 text-xs font-medium">Jam 10.00 - 15.00 WIB</p>
            <?php else : ?>
            <p class="text-green-600 text-xs font-medium">Jam 07.00 - 12.00 WIB</p>
             <?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
              <div>
                <p class="text-sm font-semibold text-gray-700">Senin - Rabu</p>
                <p class="text-xs text-gray-500">Layanan donor darah</p>
              </div>
              <span class="text-sm font-bold text-blue-600">10.00 - 15.00</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
              <div>
                <p class="text-sm font-semibold text-gray-700">Kamis - Jumat</p>
                <p class="text-xs text-gray-500">Layanan donor darah</p>
              </div>
              <span class="text-sm font-bold text-yellow-600">07.00 - 12.00</span>
            </div>
            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
              <div>
                <p class="text-sm font-semibold text-gray-700">Sabtu - Minggu</p>
                <p class="text-xs text-gray-500">Hari Libur</p>
              </div>
              <span class="text-sm font-bold text-red-600">LIBUR</span>
            </div>
          </div>
        </div>

        <!-- Kanan: Pendonor per Provinsi -->
        <div class="main-card bg-white rounded-xl shadow p-6">
          <h3 class="text-base font-bold text-red-600 mb-4">Pendonor per Provinsi</h3>
          <div class="overflow-auto max-h-72">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b-2 border-gray-200 border-dark">
                  <th class="text-left py-2 px-2 font-semibold text-gray-700 text-dark">Provinsi</th>
                  <th class="text-right py-2 px-2 font-semibold text-gray-700 text-dark">Jumlah</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($provList as $i => $pv) : ?>
                <tr class="<?php echo $i % 2 === 0 ? 'table-row-even' : 'table-row-odd'; ?>">
                  <td class="py-2 px-2 text-blue-600"><?php echo htmlspecialchars($pv['provinsi']); ?></td>
                  <td class="py-2 px-2 text-right font-semibold text-gray-700 text-dark"><?php echo $pv['jumlah']; ?> Orang</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ═══ SECTION 2: Tentang + Stok UDD ═══ -->
      <div class="grid gap-6 mb-6">
        <div class="main-card bg-white rounded-xl shadow p-6">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-6 h-0.5 bg-red-600"></div>
            <span class="text-red-600 font-semibold text-sm uppercase tracking-wider">Tentang Kami</span>
          </div>
          <h2 class="text-xl font-bold text-gray-800 text-dark mb-3">Ayodonor - Palang Merah Indonesia</h2>
          <p class="text-gray-600 text-sm leading-relaxed">Setiap menit terdapat 1 orang yang membutuhkan transfusi darah di Indonesia. PMI hadir untuk membantu masyarakat mendapatkan layanan donor darah dengan mudah melalui platform Ayodonor.</p>
        </div>
      </div>

    </div>
    <!-- ═══ AKHIR HOME ═══ -->

    <!-- ═══ SERVICE ═══ -->
    <div id="hal-service" class="hidden">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 text-dark">Service</h1>
          <p class="text-gray-500 text-sm">Kelola data user dan pendonor</p>
        </div>
        <button onclick="bukaModalTambahUser()"
                class="bg-red-600 hover:bg-red-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Tambah User
        </button>
      </div>

      <div class="flex gap-2 mb-4">
        <button onclick="gantiTab('user')" id="btn-tab-user"
                class="px-5 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white transition">Data User</button>
        <button onclick="gantiTab('donor')" id="btn-tab-donor"
                class="px-5 py-2 rounded-lg text-sm font-semibold bg-gray-200 text-gray-700 transition text-dark">Data Pendonor</button>
      </div>

      <!-- Tabel User -->
      <div id="tab-user">
        <div class="main-card bg-white rounded-xl shadow overflow-hidden">
          <div class="p-4 border-b border-gray-200 border-dark flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-dark text-sm">Data User Terdaftar</h3>
            <input type="text" placeholder="Cari user..." oninput="filterTabel('user-row', this.value)"
                   class="pl-4 pr-4 py-1.5 border border-gray-300 border-dark rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50">
                <tr class="border-b border-gray-200 border-dark">
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">No</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Nama</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Email</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Password</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Terdaftar</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($usersList as $no => $u) : ?>
                <tr class="border-b border-gray-100 border-dark hover:bg-gray-50 user-row">
                  <td class="py-3 px-4 text-gray-600 text-dark"><?php echo $no + 1; ?></td>
                  <td class="py-3 px-4 font-medium text-gray-800 text-dark"><?php echo htmlspecialchars($u['nama']); ?></td>
                  <td class="py-3 px-4 text-gray-600 text-dark"><?php echo htmlspecialchars($u['email']); ?></td>
                  <td class="py-3 px-4">
                    <span class="font-mono text-xs text-gray-400 cursor-pointer hover:text-gray-700"
                          title="Klik untuk lihat/sembunyikan"
                          onclick="this.textContent = this.textContent === '••••••••' ? '<?php echo addslashes($u['password']); ?>' : '••••••••'">
                      ••••••••
                    </span>
                  </td>
                  <td class="py-3 px-4 text-gray-500 text-dark text-xs"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                  <td class="py-3 px-4">
                    <div class="flex gap-2">
                      <button onclick="bukaEditUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nama']); ?>', '<?php echo htmlspecialchars($u['email']); ?>')"
                              class="bg-yellow-100 text-yellow-700 hover:bg-yellow-200 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Edit</button>
                      <button onclick="hapusUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nama']); ?>')"
                              class="bg-red-100 text-red-700 hover:bg-red-200 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tabel Pendonor -->
      <div id="tab-donor" class="hidden">
        <div class="main-card bg-white rounded-xl shadow overflow-hidden">
          <div class="p-4 border-b border-gray-200 border-dark flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-dark text-sm">Data Pendonor Terdaftar</h3>
            <input type="text" placeholder="Cari pendonor..." oninput="filterTabel('donor-row', this.value)"
                   class="pl-4 pr-4 py-1.5 border border-gray-300 border-dark rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50">
                <tr class="border-b border-gray-200 border-dark">
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">No. Pendonor</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Nama Lengkap</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Kode Donor</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Gol. Darah</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Kota</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Telepon</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Status</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($donorsList as $d) : ?>
                <tr class="border-b border-gray-100 border-dark hover:bg-gray-50 donor-row">
                  <td class="py-3 px-4 font-mono text-gray-600 text-dark text-xs"><?php echo $d['id']; ?></td>
                  <td class="py-3 px-4 font-medium text-gray-800 text-dark"><?php echo htmlspecialchars($d['nama_lengkap']); ?></td>
                  <td class="py-3 px-4 font-mono text-gray-600 text-dark text-xs"><?php echo htmlspecialchars($d['kode_format'] ?? $d['kode_donor']); ?></td>
                  <td class="py-3 px-4">
                    <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full">
                      <?php echo htmlspecialchars($d['golongan_darah'] ?? '-'); ?>
                    </span>
                  </td>
                  <td class="py-3 px-4 text-gray-600 text-dark text-xs"><?php echo htmlspecialchars($d['nama_kota'] ?? '-'); ?></td>
                  <td class="py-3 px-4 text-gray-600 text-dark text-xs"><?php echo htmlspecialchars($d['telepon']); ?></td>
                  <td class="py-3 px-4">
                    <?php if ($d['status_donor'] === 'Sudah pernah') : ?>
                    <span class="bg-green-100 text-green-700 text-xs font-semibold px-2 py-0.5 rounded-full">Pernah</span>
                    <?php else : ?>
                    <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5 rounded-full">Belum</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex gap-2">
                      <button onclick="lihatDonor(<?php echo $d['id']; ?>)"
                              class="bg-blue-100 text-blue-700 hover:bg-blue-200 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Lihat</button>
                      <button onclick="hapusDonor(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars($d['nama_lengkap']); ?>')"
                              class="bg-red-100 text-red-700 hover:bg-red-200 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Hapus</button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ STOK DARAH ═══ -->
    <div id="hal-stok" class="hidden">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 text-dark">Stok Darah</h1>
          <p class="text-gray-500 text-sm">Kelola stok darah per kota</p>
        </div>
        <button onclick="bukaModalTambahStok()"
                class="bg-red-600 hover:bg-red-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Tambah Stok
        </button>
      </div>

      <!-- Notifikasi 5 detik -->
      <?php if ($notifStok) : ?>
      <div id="notif-stok-sementara" class="notif-anim mb-4 bg-green-50 border border-green-300 rounded-xl p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="font-bold text-green-600 text-sm">#</span>
            <span class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($notifStok['kota']); ?></span>
            <span class="text-xs text-gray-500"><?php echo htmlspecialchars($notifStok['provinsi']); ?></span>
            <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full"><?php echo $notifStok['golongan']; ?></span>
            <span class="text-green-600 font-bold text-lg">+</span>
            <span class="text-xs text-green-700 font-semibold">Stok <?php echo $notifStok['golongan']; ?> berhasil ditambahkan (+<?php echo $notifStok['jumlah']; ?>)</span>
          </div>
          <span id="notif-countdown" class="text-2xl font-bold text-green-500">5</span>
        </div>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-2 gap-6 mb-6">
        <div class="main-card bg-white rounded-xl shadow p-6">
          <h3 class="text-base font-bold text-gray-800 text-dark mb-4">Grafik Stok Darah</h3>
          <canvas id="chartStok" height="220"></canvas>
        </div>
        <div class="main-card bg-white rounded-xl shadow p-6">
          <h3 class="text-base font-bold text-gray-800 text-dark mb-4">Ringkasan Golongan Darah</h3>
          <div class="space-y-3">
            <?php foreach ($stokList as $st) : ?>
            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50">
              <div class="flex items-center gap-3">
                <span class="w-8 h-8 bg-red-600 text-white rounded-full flex items-center justify-center text-xs font-bold"><?php echo $st['golongan_darah']; ?></span>
                <span class="text-sm font-medium text-gray-700 text-dark">Golongan <?php echo $st['golongan_darah']; ?></span>
              </div>
              <span class="text-lg font-bold text-red-600"><?php echo $st['jumlah']; ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="main-card bg-white rounded-xl shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200 border-dark">
          <h3 class="font-semibold text-gray-800 text-dark text-sm">Stok Darah per Kota</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50">
              <tr class="border-b border-gray-200 border-dark">
                <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">No</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Kota</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Provinsi</th>
                <th class="text-center py-3 px-4 font-semibold text-gray-600 text-dark">A</th>
                <th class="text-center py-3 px-4 font-semibold text-gray-600 text-dark">B</th>
                <th class="text-center py-3 px-4 font-semibold text-gray-600 text-dark">O</th>
                <th class="text-center py-3 px-4 font-semibold text-gray-600 text-dark">AB</th>
                <th class="text-center py-3 px-4 font-semibold text-gray-600 text-dark">Total</th>
                <th class="text-left py-3 px-4 font-semibold text-gray-600 text-dark">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stokKotaList as $no => $sk) : ?>
              <tr class="border-b border-gray-100 border-dark hover:bg-gray-50">
                <td class="py-3 px-4 text-gray-600 text-dark"><?php echo $no + 1; ?></td>
                <td class="py-3 px-4 font-medium text-gray-800 text-dark"><?php echo htmlspecialchars($sk['nama_kota']); ?></td>
                <td class="py-3 px-4 text-gray-600 text-dark text-xs"><?php echo htmlspecialchars($sk['nama_provinsi']); ?></td>
                <td class="py-3 px-4 text-center font-bold text-red-600"><?php echo $sk['stok_a']; ?></td>
                <td class="py-3 px-4 text-center font-bold text-blue-600"><?php echo $sk['stok_b']; ?></td>
                <td class="py-3 px-4 text-center font-bold text-yellow-600"><?php echo $sk['stok_o']; ?></td>
                <td class="py-3 px-4 text-center font-bold text-purple-600"><?php echo $sk['stok_ab']; ?></td>
                <td class="py-3 px-4 text-center font-bold text-gray-800 text-dark"><?php echo $sk['total']; ?></td>
                <td class="py-3 px-4">
                  <button onclick="bukaEditStok(<?php echo $sk['id_kota']; ?>, '<?php echo htmlspecialchars($sk['nama_kota']); ?>', <?php echo $sk['stok_a']; ?>, <?php echo $sk['stok_b']; ?>, <?php echo $sk['stok_o']; ?>, <?php echo $sk['stok_ab']; ?>)"
                          class="bg-yellow-100 text-yellow-700 hover:bg-yellow-200 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Edit Stok</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ LAPORAN ═══ -->
    <div id="hal-laporan" class="hidden">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 text-dark">Laporan</h1>
        <p class="text-gray-500 text-sm">Statistik dan riwayat perubahan stok darah</p>
      </div>

      <!-- Chart 4 Golongan Darah — sumbu Y dari tengah (positif/negatif) -->
      <div class="main-card bg-white rounded-xl shadow p-6 mb-6">
        <h3 class="text-base font-bold text-gray-800 text-dark mb-1">Statistik Perubahan Stok Darah</h3>
        <p class="text-xs text-gray-400 mb-4">
          Naik = stok bertambah &nbsp;|&nbsp; Turun = stok berkurang &nbsp;|&nbsp; 7 hari terakhir per golongan darah
        </p>
        <canvas id="chartLaporan" height="130"></canvas>
      </div>

      <!-- Riwayat Permanen: gabungan log_stok + pendonor -->
      <div class="main-card bg-white rounded-xl shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200 border-dark">
          <h3 class="font-semibold text-gray-800 text-dark text-sm">Riwayat Perubahan Stok</h3>
        </div>
        <div class="p-4 space-y-2 overflow-auto max-h-96">
          <?php
          // Gabungkan log_stok + pendonor, urutkan by waktu DESC
          $riwayatGabung = [];

          foreach ($logStokList as $ls) {
              $riwayatGabung[] = [
                  'waktu'      => $ls['created_at'],
                  'nama'       => $ls['nama_kota'] . ', ' . $ls['nama_provinsi'],
                  'kode'       => '',
                  'golongan'   => $ls['golongan_darah'],
                  'jenis'      => $ls['jenis'],
                  'jumlah'     => $ls['perubahan'],
                  'keterangan' => $ls['keterangan'] ?? '',
                  'tipe'       => 'stok',
              ];
          }

          foreach ($logDonorList as $ld) {
              $riwayatGabung[] = [
                  'waktu'      => $ld['created_at'],
                  'nama'       => $ld['nama_lengkap'],
                  'kode'       => $ld['kode_format'] ?? $ld['kode_donor'],
                  'golongan'   => $ld['golongan_darah'] ?? '-',
                  'jenis'      => 'tambah',
                  'jumlah'     => 1,
                  'keterangan' => 'Donor berhasil',
                  'tipe'       => 'donor',
              ];
          }

          usort($riwayatGabung, function($a, $b) {
              return strtotime($b['waktu']) - strtotime($a['waktu']);
          });

          $golColors = [
              'A'  => 'text-red-600 border-red-400',
              'B'  => 'text-blue-600 border-blue-400',
              'O'  => 'text-yellow-600 border-yellow-400',
              'AB' => 'text-purple-600 border-purple-400',
          ];
          ?>

          <?php if (empty($riwayatGabung)) : ?>
          <p class="text-center text-gray-400 py-8">Belum ada riwayat perubahan stok</p>
          <?php else : ?>
          <?php foreach ($riwayatGabung as $no => $rv) :
              $isTambah = ($rv['jenis'] === 'tambah');
              // Pendonor → hijau; admin kurang → merah; admin tambah → hijau
              $bgClass  = $isTambah
                  ? 'bg-green-50 border-green-200'
                  : 'bg-red-50 border-red-200';
              $golCls   = $golColors[$rv['golongan']] ?? 'text-gray-600 border-gray-400';
          ?>
          <div class="flex items-center gap-3 p-3 rounded-lg border <?php echo $bgClass; ?>">
            <span class="text-xs font-bold text-gray-400 w-6 flex-shrink-0"><?php echo $no + 1; ?></span>

            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($rv['nama']); ?></p>
              <?php if (!empty($rv['kode'])) : ?>
              <p class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($rv['kode']); ?></p>
              <?php endif; ?>
            </div>

            <!-- Badge golongan dengan warna per tipe -->
            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-white border <?php echo $golCls; ?> flex-shrink-0">
              <?php echo htmlspecialchars($rv['golongan']); ?>
            </span>

            <!-- Simbol + / - -->
            <span class="text-lg font-bold flex-shrink-0 <?php echo $isTambah ? 'text-green-600' : 'text-red-600'; ?>">
              <?php echo $isTambah ? '+' : '-'; ?><?php echo $rv['jumlah']; ?>
            </span>

            <div class="text-right flex-shrink-0">
              <p class="text-xs text-gray-400"><?php echo date('d M Y H:i', strtotime($rv['waktu'])); ?></p>
              <p class="text-xs text-gray-500"><?php echo htmlspecialchars($rv['keterangan']); ?></p>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- MODAL TAMBAH USER -->
<div id="modal-tambah-user" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)tutupModal('modal-tambah-user')">
  <div class="bg-white main-card rounded-xl max-w-md w-full p-8" onclick="event.stopPropagation()">
    <h2 class="text-lg font-bold text-gray-800 text-dark mb-6">Tambah User Baru</h2>
    <form method="POST" action="proses_admin.php">
      <input type="hidden" name="aksi" value="tambah_user">
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Nama</label>
        <input name="nama" type="text" required class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Email</label>
        <input name="email" type="text" required class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
      <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Password</label>
        <input name="password" type="password" required class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
      <div class="flex justify-between">
        <button type="button" onclick="tutupModal('modal-tambah-user')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-lg text-sm transition">Batal</button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT USER -->
<div id="modal-edit-user" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)tutupModal('modal-edit-user')">
  <div class="bg-white main-card rounded-xl max-w-md w-full p-8" onclick="event.stopPropagation()">
    <h2 class="text-lg font-bold text-gray-800 text-dark mb-6">Edit User</h2>
    <form method="POST" action="proses_admin.php">
      <input type="hidden" name="aksi" value="edit_user">
      <input type="hidden" name="id" id="edit-user-id">
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Nama</label>
        <input name="nama" id="edit-user-nama" type="text" required class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Email</label>
        <input name="email" id="edit-user-email" type="text" required class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
      <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Password Baru <span class="text-gray-400 font-normal">(kosongkan jika tidak diubah)</span></label>
        <input name="password" type="password" placeholder="Password baru..." class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
      <div class="flex justify-between">
        <button type="button" onclick="tutupModal('modal-edit-user')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-lg text-sm transition">Batal</button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL HAPUS USER -->
<div id="modal-hapus-user" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)tutupModal('modal-hapus-user')">
  <div class="bg-white main-card rounded-xl max-w-sm w-full p-8 text-center" onclick="event.stopPropagation()">
    <div class="flex items-center justify-center gap-3 mb-4 mt-2">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="red" viewBox="0 0 16 16">
        <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/>
        <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>
      </svg>
      <h2 class="text-lg font-bold text-gray-800 text-dark">Hapus User?</h2>
    </div>
    <p class="text-gray-500 text-sm mb-6">Data "<span id="hapus-user-nama" class="font-semibold"></span>" akan dihapus permanen.</p>
    <form method="POST" action="proses_admin.php">
      <input type="hidden" name="aksi" value="hapus_user">
      <input type="hidden" name="id" id="hapus-user-id">
      <div class="flex gap-4 justify-center">
        <button type="button" onclick="tutupModal('modal-hapus-user')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-lg text-sm transition">Batal</button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition">Hapus</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL HAPUS DONOR -->
<div id="modal-hapus-donor" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)tutupModal('modal-hapus-donor')">
  <div class="bg-white main-card rounded-xl max-w-sm w-full p-8 text-center" onclick="event.stopPropagation()">
    <div class="text-4xl mb-4">⚠️</div>
    <h2 class="text-lg font-bold text-gray-800 text-dark mb-2">Hapus Data Pendonor?</h2>
    <p class="text-gray-500 text-sm mb-6">Data "<span id="hapus-donor-nama" class="font-semibold"></span>" akan dihapus permanen.</p>
    <form method="POST" action="proses_admin.php">
      <input type="hidden" name="aksi" value="hapus_donor">
      <input type="hidden" name="id" id="hapus-donor-id">
      <div class="flex gap-4 justify-center">
        <button type="button" onclick="tutupModal('modal-hapus-donor')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-lg text-sm transition">Batal</button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition">Hapus</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL LIHAT DONOR -->
<div id="modal-lihat-donor" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)tutupModal('modal-lihat-donor')">
  <div class="bg-white main-card rounded-xl max-w-2xl w-full p-8 modal-scroll" onclick="event.stopPropagation()">
    <h2 class="text-lg font-bold text-gray-800 text-dark mb-4">Detail Pendonor</h2>
    <hr class="mb-4">
    <div id="detail-donor-isi" class="text-sm text-gray-600 space-y-2"></div>
    <hr class="mt-6 mb-4">
    <button onclick="tutupModal('modal-lihat-donor')" class="text-red-600 font-semibold hover:underline text-sm">Tutup</button>
  </div>
</div>

<!-- MODAL TAMBAH STOK -->
<div id="modal-tambah-stok" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)tutupModal('modal-tambah-stok')">
  <div class="bg-white main-card rounded-xl max-w-md w-full p-8" onclick="event.stopPropagation()">
    <h2 class="text-lg font-bold text-gray-800 text-dark mb-6">Tambah Stok Darah</h2>
    <form method="POST" action="proses_admin.php">
      <input type="hidden" name="aksi" value="tambah_stok">
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Kota</label>
        <select name="id_kota" required class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          <option value="">-- Pilih Kota --</option>
          <?php foreach ($kotaDropdown as $kt) : ?>
          <option value="<?php echo $kt['id']; ?>"><?php echo htmlspecialchars($kt['nama_kota']); ?> - <?php echo htmlspecialchars($kt['nama_provinsi']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Golongan Darah</label>
        <select name="golongan_darah" required class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          <option value="">-- Pilih --</option>
          <option value="A">A</option>
          <option value="B">B</option>
          <option value="O">O</option>
          <option value="AB">AB</option>
        </select>
      </div>
      <div class="mb-6">
        <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Jumlah Tambah</label>
        <input name="jumlah" type="number" min="1" required class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
      <div class="flex justify-between">
        <button type="button" onclick="tutupModal('modal-tambah-stok')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-lg text-sm transition">Batal</button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT STOK -->
<div id="modal-edit-stok" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4" onclick="if(event.target===this)tutupModal('modal-edit-stok')">
  <div class="bg-white main-card rounded-xl max-w-md w-full p-8" onclick="event.stopPropagation()">
    <h2 class="text-lg font-bold text-gray-800 text-dark mb-2">Edit Stok Darah</h2>
    <p id="edit-stok-kota-label" class="text-gray-500 text-sm mb-6"></p>
    <form method="POST" action="proses_admin.php">
      <input type="hidden" name="aksi" value="edit_stok">
      <input type="hidden" name="id_kota" id="edit-stok-id-kota">
      <div class="grid grid-cols-2 gap-4 mb-6">
        <div>
          <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Golongan A</label>
          <input name="stok_a" id="edit-stok-a" type="number" min="0" class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Golongan B</label>
          <input name="stok_b" id="edit-stok-b" type="number" min="0" class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Golongan O</label>
          <input name="stok_o" id="edit-stok-o" type="number" min="0" class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 text-dark mb-1">Golongan AB</label>
          <input name="stok_ab" id="edit-stok-ab" type="number" min="0" class="w-full border border-gray-300 border-dark rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        </div>
      </div>
      <div class="flex justify-between">
        <button type="button" onclick="tutupModal('modal-edit-stok')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-lg text-sm transition">Batal</button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- DATA JSON -->
<script>
const stokData     = <?php echo json_encode($stokList); ?>;
const logStokData  = <?php echo json_encode($logStokList); ?>;
const logDonorData = <?php echo json_encode($logDonorList); ?>;

const dataDonorJSON = <?php
    $arrDonor = [];
    foreach ($donorsList as $d) $arrDonor[] = $d;
    echo json_encode($arrDonor);
?>;
</script>

<script>
// ── TEMA ──────────────────────────────────────────────
let isDark = localStorage.getItem('admin-theme') === 'dark';

function terapkanTema() {
  const body   = document.getElementById('body-root');
  const toggle = document.getElementById('theme-toggle');
  const circle = document.getElementById('theme-circle');
  const label  = document.getElementById('tema-label');
  if (isDark) {
    body.classList.add('dark');
    toggle.style.backgroundColor = '#374151';
    circle.style.transform = 'translateX(28px)';
    if (label) label.textContent = 'Gelap';
  } else {
    body.classList.remove('dark');
    toggle.style.backgroundColor = '#e5e7eb';
    circle.style.transform = 'translateX(0)';
    if (label) label.textContent = 'Terang';
  }
}
function toggleTheme() {
  isDark = !isDark;
  localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
  terapkanTema();
}

// ── SETTINGS ──────────────────────────────────────────
function bukaSettings() {
  const pg = document.getElementById('page-settings');
  pg.classList.remove('hidden');
  pg.classList.add('flex');
}
function tutupSettings() {
  const pg = document.getElementById('page-settings');
  pg.classList.add('hidden');
  pg.classList.remove('flex');
}

// ── NAVIGASI ──────────────────────────────────────────
function tampilHalaman(hal) {
  const pages = ['home', 'service', 'stok', 'laporan'];
  pages.forEach(function(p) {
    document.getElementById('hal-' + p).classList.add('hidden');
    document.getElementById('nav-' + p).classList.remove('active');
  });
  document.getElementById('hal-' + hal).classList.remove('hidden');
  document.getElementById('nav-' + hal).classList.add('active');
}

// ── TAB SERVICE ───────────────────────────────────────
function gantiTab(tab) {
  document.getElementById('tab-user').classList.add('hidden');
  document.getElementById('tab-donor').classList.add('hidden');
  document.getElementById('tab-' + tab).classList.remove('hidden');
  document.getElementById('btn-tab-user').className  = 'px-5 py-2 rounded-lg text-sm font-semibold bg-gray-200 text-gray-700 transition text-dark';
  document.getElementById('btn-tab-donor').className = 'px-5 py-2 rounded-lg text-sm font-semibold bg-gray-200 text-gray-700 transition text-dark';
  document.getElementById('btn-tab-' + tab).className = 'px-5 py-2 rounded-lg text-sm font-semibold bg-red-600 text-white transition';
}

// ── MODAL ─────────────────────────────────────────────
function tutupModal(id) {
  const el = document.getElementById(id);
  el.classList.add('hidden');
  el.classList.remove('flex');
}
function bukaModalTambahUser() {
  const m = document.getElementById('modal-tambah-user');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function bukaEditUser(id, nama, email) {
  document.getElementById('edit-user-id').value    = id;
  document.getElementById('edit-user-nama').value  = nama;
  document.getElementById('edit-user-email').value = email;
  const m = document.getElementById('modal-edit-user');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function hapusUser(id, nama) {
  document.getElementById('hapus-user-id').value = id;
  document.getElementById('hapus-user-nama').textContent = nama;
  const m = document.getElementById('modal-hapus-user');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function hapusDonor(id, nama) {
  document.getElementById('hapus-donor-id').value = id;
  document.getElementById('hapus-donor-nama').textContent = nama;
  const m = document.getElementById('modal-hapus-donor');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function lihatDonor(id) {
  let donor = null;
  dataDonorJSON.forEach(function(d) {
    if (parseInt(d.id) === id) donor = d;
  });
  if (!donor) return;
  const fields = [
    ['Nama Lengkap',      donor.nama_lengkap],
    ['Kode Donor',        donor.kode_format || donor.kode_donor],
    ['Tempat Lahir',      donor.tempat_lahir],
    ['Tanggal Lahir',     donor.tanggal_lahir],
    ['Jenis Kelamin',     donor.jenis_kelamin],
    ['Golongan Darah',    donor.golongan_darah],
    ['Pekerjaan',         donor.pekerjaan],
    ['Alamat',            donor.alamat],
    ['Kota Donor',        donor.nama_kota],
    ['Provinsi Donor',    donor.nama_provinsi],
    ['Telepon',           donor.telepon],
    ['Email',             donor.email],
    ['Status Donor',      donor.status_donor],
    ['Berat Badan',       (donor.berat_badan || '-') + ' kg'],
    ['Penyakit Bawaan',   donor.penyakit_bawaan || '-'],
    ['Konsumsi Obat',     donor.konsumsi_obat || '-'],
    ['Riwayat Operasi',   donor.riwayat_operasi == 1 ? 'Pernah' : 'Tidak'],
    ['Riwayat Vaksinasi', donor.riwayat_vaksinasi == 1 ? 'Pernah' : 'Tidak'],
  ];
  let html = '';
  fields.forEach(function(f) {
    html += '<div class="flex gap-2"><span class="w-40 font-semibold text-gray-700 flex-shrink-0">' + f[0] + '</span><span class="text-gray-500">:</span><span class="flex-1">' + (f[1] || '-') + '</span></div>';
  });
  document.getElementById('detail-donor-isi').innerHTML = html;
  const m = document.getElementById('modal-lihat-donor');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function bukaModalTambahStok() {
  const m = document.getElementById('modal-tambah-stok');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function bukaEditStok(idKota, namaKota, a, b, o, ab) {
  document.getElementById('edit-stok-id-kota').value = idKota;
  document.getElementById('edit-stok-kota-label').textContent = 'Kota: ' + namaKota;
  document.getElementById('edit-stok-a').value  = a;
  document.getElementById('edit-stok-b').value  = b;
  document.getElementById('edit-stok-o').value  = o;
  document.getElementById('edit-stok-ab').value = ab;
  const m = document.getElementById('modal-edit-stok');
  m.classList.remove('hidden');
  m.classList.add('flex');
}

// ── FILTER TABEL ──────────────────────────────────────
function filterTabel(cls, q) {
  const rows = document.querySelectorAll('.' + cls);
  q = q.toLowerCase();
  rows.forEach(function(row) {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ── INIT ──────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', function() {
  terapkanTema();

  // Notif countdown stok
  const notifEl     = document.getElementById('notif-stok-sementara');
  const countdownEl = document.getElementById('notif-countdown');
  if (notifEl && countdownEl) {
    tampilHalaman('stok');
    let sisa = 5;
    const timer = setInterval(function() {
      sisa--;
      countdownEl.textContent = sisa;
      if (sisa <= 0) {
        clearInterval(timer);
        notifEl.style.transition = 'opacity 0.5s';
        notifEl.style.opacity    = '0';
        setTimeout(function() { notifEl.remove(); }, 500);
      }
    }, 1000);
  }

  const barColors = [
    'rgba(255,99,132,0.7)',
    'rgba(54,162,235,0.7)',
    'rgba(255,206,86,0.7)',
    'rgba(153,102,255,0.7)'
  ];

  // ── Chart Home (bar stok) ──────────────────────────
  const ctxHome = document.getElementById('chartHome');
  if (ctxHome) {
    new Chart(ctxHome.getContext('2d'), {
      type: 'bar',
      data: {
        labels: stokData.map(function(d) { return d.golongan_darah; }),
        datasets: [{
          label: 'Stok Darah',
          data: stokData.map(function(d) { return parseInt(d.jumlah); }),
          backgroundColor: barColors,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // ── Chart Stok ────────────────────────────────────
  const ctxStok = document.getElementById('chartStok');
  if (ctxStok) {
    new Chart(ctxStok.getContext('2d'), {
      type: 'bar',
      data: {
        labels: stokData.map(function(d) { return d.golongan_darah; }),
        datasets: [{
          label: 'Stok Darah',
          data: stokData.map(function(d) { return parseInt(d.jumlah); }),
          backgroundColor: barColors,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // ── Chart Laporan — 4 garis per golongan, sumbu Y dari TENGAH (negatif/positif) ──
  const ctxLaporan = document.getElementById('chartLaporan');
  if (ctxLaporan) {
    const today = new Date();

    // Label 7 hari terakhir
    const last7Labels = [];
    for (let i = 6; i >= 0; i--) {
      const d = new Date(today);
      d.setDate(d.getDate() - i);
      last7Labels.push(d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }));
    }

    const golonganList   = ['A', 'B', 'O', 'AB'];
    const golonganColors = {
      'A':  { border: 'rgba(239,68,68,0.9)',   point: '#EF4444' },
      'B':  { border: 'rgba(59,130,246,0.9)',  point: '#3B82F6' },
      'O':  { border: 'rgba(234,179,8,0.9)',   point: '#EAB308' },
      'AB': { border: 'rgba(168,85,247,0.9)',  point: '#A855F7' }
    };

    // Net per golongan per hari (positif = tambah, negatif = kurang)
    // Dimulai dari 0 (tengah), bukan dari bawah
    const nilaiPerGol = {};
    golonganList.forEach(function(gol) {
      nilaiPerGol[gol] = [0, 0, 0, 0, 0, 0, 0];
    });

    // Dari log_stok (admin tambah/edit/hapus)
    logStokData.forEach(function(log) {
      const tgl  = new Date(log.created_at);
      const diff = Math.floor((today - tgl) / (1000 * 60 * 60 * 24));
      if (diff >= 0 && diff < 7) {
        const idx = 6 - diff;
        const gol = log.golongan_darah;
        if (nilaiPerGol[gol] !== undefined) {
          const jml = parseInt(log.perubahan);
          // tambah = positif (naik), kurang = negatif (turun)
          nilaiPerGol[gol][idx] += (log.jenis === 'tambah' ? jml : -jml);
        }
      }
    });

    // Dari pendonor (setiap pendonor = +1 kantong)
    logDonorData.forEach(function(ld) {
      const tgl  = new Date(ld.created_at);
      const diff = Math.floor((today - tgl) / (1000 * 60 * 60 * 24));
      if (diff >= 0 && diff < 7) {
        const idx = 6 - diff;
        const gol = ld.golongan_darah;
        if (gol && nilaiPerGol[gol] !== undefined) {
          nilaiPerGol[gol][idx] += 1; // donor = tambah +1
        }
      }
    });

    const datasets = golonganList.map(function(gol) {
      return {
        label: 'Gol. ' + gol,
        data: nilaiPerGol[gol],
        borderColor: golonganColors[gol].border,
        backgroundColor: 'transparent',
        pointBackgroundColor: golonganColors[gol].point,
        pointRadius: 5,
        tension: 0.3,
        fill: false
      };
    });

    new Chart(ctxLaporan.getContext('2d'), {
      type: 'line',
      data: { labels: last7Labels, datasets: datasets },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: { usePointStyle: true, pointStyle: 'circle', padding: 16 }
          }
        },
        scales: {
          y: {
            // ← Kunci: sumbu Y dimulai dari tengah, tidak forced beginAtZero
            // Chart.js otomatis pusat jika ada nilai positif dan negatif
            // Tambahkan garis 0 di tengah
            grid: {
              color: function(context) {
                // Garis y=0 lebih tebal dan menonjol
                return context.tick.value === 0
                  ? 'rgba(0,0,0,0.3)'
                  : 'rgba(0,0,0,0.07)';
              },
              lineWidth: function(context) {
                return context.tick.value === 0 ? 2 : 1;
              }
            },
            ticks: {
              precision: 0,
              stepSize: 1,
              callback: function(value) {
                // Tampilkan tanda + untuk positif agar jelas
                return value > 0 ? '+' + value : value;
              }
            },
            title: {
              display: true,
              text: 'Perubahan Kantong (+ tambah / - kurang)'
            }
          }
        }
      }
    });
  }

});
</script>
</body>
</html>