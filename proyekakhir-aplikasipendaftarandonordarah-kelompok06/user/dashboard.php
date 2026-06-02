<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

$isLoggedIn = isLoggedIn();
$namaUser   = getNama();
$role       = getRole();

$donorSuccess = $_SESSION['donor_success'] ?? '';
$donorError   = $_SESSION['donor_error']   ?? '';
unset($_SESSION['donor_success'], $_SESSION['donor_error']);

// Stok darah
$stokResult = $conn->query("
    SELECT golongan_darah, SUM(jumlah) as jumlah
    FROM stok_darah
    GROUP BY golongan_darah
    ORDER BY golongan_darah
");
$stokList = [];
while ($s = $stokResult->fetch_assoc()) {
    $stokList[] = $s;
}

// ── Pendonor per provinsi — SEMUA provinsi tampil meski 0 ──
// PERUBAHAN: hanya 1 query, join langsung ke pendonor.id_provinsi
$provResult = $conn->query("
    SELECT p.nama_provinsi as provinsi,
           COUNT(dn.id) as jumlah
    FROM provinsi p
    LEFT JOIN pendonor dn ON dn.id_provinsi = p.id
    GROUP BY p.id, p.nama_provinsi
    ORDER BY jumlah DESC, p.nama_provinsi ASC
");
$provList = [];
while ($p = $provResult->fetch_assoc()) $provList[] = $p;

// Provinsi untuk dropdown form donor
$provDropdown = $conn->query("SELECT id, nama_provinsi FROM provinsi ORDER BY nama_provinsi");
$provinsiList = [];
while ($pv = $provDropdown->fetch_assoc()) {
    $provinsiList[] = $pv;
}

// Jadwal PMI hari ini
$hariIni  = date('N'); // 1=Senin, 7=Minggu
$namaHari = ['','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
$isLibur  = ($hariIni == 6 || $hariIni == 7);

// Data pendonor user yang login (untuk form donor)
$donorUser = null;
if ($isLoggedIn) {
    $stmtDn = $conn->prepare("SELECT * FROM pendonor WHERE id_user = ?");
    $uid = $_SESSION['user_id'];
    $stmtDn->bind_param('i', $uid);
    $stmtDn->execute();
    $donorUser = $stmtDn->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ayodonor - Palang Merah Indonesia</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
  * { font-family: 'Inter', sans-serif; }
  html { scroll-behavior: smooth; }
  .modal-scroll { max-height: 80vh; overflow-y: auto; }
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
</style>
</head>
<body class="bg-white">

<!-- ═══════════ NAVBAR ═══════════ -->
<nav class="fixed top-0 left-0 right-0 bg-white shadow-md z-50">
  <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
    <div class="flex items-center gap-3">
      <svg width="48" height="48" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <path d="M50 5C25 5 5 25 5 50S25 95 50 95 95 75 95 50 75 5 50 5Z" fill="white" stroke="#DC2626" stroke-width="4"/>
        <rect x="42" y="25" width="16" height="50" rx="4" fill="#DC2626"/>
        <rect x="25" y="42" width="50" height="16" rx="4" fill="#DC2626"/>
        <path d="M50 5Q60 8 68 15Q80 8 88 18Q95 30 90 45Q95 55 88 65Q80 75 68 78Q60 90 50 92Q40 90 32 78Q20 75 12 65Q5 55 10 45Q5 30 12 18Q20 8 32 15Q40 8 50 5Z" fill="none" stroke="#DC2626" stroke-width="3"/>
      </svg>
      <div class="leading-tight">
        <div class="font-bold text-gray-800 text-sm">Palang Merah</div>
        <div class="font-bold text-gray-800 text-sm">Indonesia</div>
      </div>
    </div>
    <div class="hidden md:flex items-center gap-5">
      <a href="#beranda" class="text-red-600 font-semibold text-sm">Beranda</a>
      <a href="#tentang"  class="text-gray-700 hover:text-red-600 text-sm font-medium gate-link">Tentang Kami</a>
      <a href="#stok"     class="text-gray-700 hover:text-red-600 text-sm font-medium gate-link">Stok Darah</a>
      <a href="#berita"   class="text-gray-700 hover:text-red-600 text-sm font-medium gate-link">Berita PMI</a>
      <div class="relative group">
        <button class="text-gray-700 hover:text-red-600 text-sm font-medium flex items-center gap-1 gate-btn">
          Info Donor
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div class="absolute top-full left-0 bg-white shadow-lg rounded-lg py-2 hidden group-hover:block min-w-44 z-50">
          <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-red-50 gate-link">Info Data Donor</a>
          <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-red-50 gate-link">Kebijakan Privasi</a>
        </div>
      </div>
      <a href="#footer" class="text-gray-700 hover:text-red-600 text-sm font-medium">Kontak PMI</a>
      <a href="<?php echo $isLoggedIn ? '#pendonoran' : '../login.php'; ?>"
         class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
        Donor Sekarang
      </a>
      <?php if ($isLoggedIn) : ?>
      <a href="../logout.php"
         class="bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
        Logout
      </a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- ═══════════ SECTION 1: HERO FULL WIDTH ═══════════ -->
<section id="beranda" class="pt-16">
  <div class="relative overflow-hidden bg-red-600" style="height:520px">
    <div id="hero-slider" class="flex h-full transition-transform duration-500 ease-in-out">
      <div class="min-w-full h-full flex flex-col items-center justify-center text-white px-8">
        <div class="mb-6 w-44 h-44 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center">
          <svg width="110" height="110" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <path d="M50 5C25 5 5 25 5 50S25 95 50 95 95 75 95 50 75 5 50 5Z" fill="white" stroke="white" stroke-width="2"/>
            <rect x="42" y="25" width="16" height="50" rx="4" fill="#DC2626"/>
            <rect x="25" y="42" width="50" height="16" rx="4" fill="#DC2626"/>
          </svg>
        </div>
        <h2 class="text-4xl font-extrabold text-center uppercase tracking-wide mb-3">Untuk Kemudahan Donor Darah Anda</h2>
        <p class="text-red-100 text-center mb-6 text-lg">PMI membantu masyarakat mendapatkan donor darah dengan mudah.</p>
        <a href="<?php echo $isLoggedIn ? '#pendonoran' : '../login.php'; ?>"
           class="bg-white text-red-600 font-bold px-10 py-3 rounded-full hover:bg-red-50 transition">
          Mulai Sekarang
        </a>
      </div>

      <div class="relative min-w-full min-h-screen flex items-center justify-center bg-[url('../assets/markas.jpg')] bg-cover bg-center bg-no-repeat">
        <div class="absolute inset-0 bg-black/40"></div>
      </div>

      <div class="relative min-w-full min-h-screen flex items-center justify-center bg-[url('../assets/siluet.jpg')] bg-cover bg-center bg-no-repeat">
        <div class="text-center text-white">
          <div class="text-8xl mb-6">❤️</div>
          <p class="text-3xl font-bold mb-2">Selamatkan Jiwa</p>
          <p class="text-red-100 text-lg">Satu kantong darah, satu nyawa terselamatkan</p>
        </div>
      </div>
    </div>

    <button onclick="heroSlide(-1)"
            class="absolute left-4 top-1/2 -translate-y-1/2 bg-white bg-opacity-20 hover:bg-opacity-40 rounded-full w-11 h-11 flex items-center justify-center transition z-10">
      <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
    </button>
    <button onclick="heroSlide(1)"
            class="absolute right-4 top-1/2 -translate-y-1/2 bg-white bg-opacity-20 hover:bg-opacity-40 rounded-full w-11 h-11 flex items-center justify-center transition z-10">
      <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
    </button>
  </div>
</section>

<!-- ═══════════ SECTION 2: TENTANG KAMI ═══════════ -->
<section id="tentang" class="py-16 bg-white">
  <div class="max-w-7xl mx-auto px-4 flex gap-8 items-start">
    <div class="w-1/2 pr-8">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-8 h-0.5 bg-red-600"></div>
        <span class="text-red-600 font-semibold text-sm uppercase tracking-wider">Tentang Kami</span>
      </div>
      <h2 class="text-3xl font-bold text-gray-900 mb-4">Ayodonor - Palang Merah Indonesia</h2>
      <p class="text-gray-700 font-semibold mb-3">Salam Kemanusiaan,</p>
      <p class="text-gray-600 leading-relaxed mb-4">
        Setiap menit terdapat 1 (satu) Orang yang membutuhkan transfusi darah di Indonesia. Palang Merah Indonesia merupakan organisasi yang sah secara Undang-undang untuk membantu Pemerintah dalam memenuhi kebutuhan pelayanan darah transfusi dan pelayanan sosial kemanusiaan di Indonesia.
      </p>
      <p class="text-gray-600 leading-relaxed mb-4">
        AYODONOR adalah aplikasi dan portal informasi yang dikemas untuk memudahkan pelayanan darah transfusi melalui Palang Merah Indonesia di seluruh Kota / Kabupaten di Indonesia.
      </p>
      <p class="text-gray-600 leading-relaxed">
        Seiring donasi menjadi lebih mudah, semoga akan lebih banyak yang dapat kita bantu.
      </p>
    </div>
    <div class="w-1/2">
      <img src="../assets/poster.png" alt="Ayodonor Logo" class="mx-auto mb-4" style="max-width: 300px">
    </div>
  </div>
</section>

<!-- ═══════════ SECTION 3: INFO PMI (Jadwal + Pendonor per Provinsi) ═══════════ -->
<section id="stok" class="py-16 bg-gray-50">
  <div class="max-w-7xl mx-auto px-4">

    <div class="text-center mb-10">
      <p class="text-red-600 font-semibold text-sm uppercase tracking-wider mb-1">INFO PMI</p>
      <h2 class="text-3xl font-bold text-gray-900">Jadwal &amp; Pendonor</h2>
    </div>

    <!-- ═══ Grid 2 kolom: Jadwal PMI (kiri) + Pendonor per Provinsi (kanan) ═══ -->
    <div class="grid grid-cols-2 gap-6 mb-6">

      <!-- Kiri: Jadwal PMI -->
      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-base font-bold text-red-600 mb-4">Jadwal PMI</h3>

        <?php if ($isLibur) : ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
          <div class="flex items-center gap-2 mb-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#DC2626" viewBox="0 0 16 16">
              <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
            </svg>
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

      <!-- ═══ Kanan: Pendonor per Provinsi (DIPERBARUI) ═══ -->
      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-base font-bold text-red-600 mb-4">Pendonor per Provinsi</h3>
        <div class="overflow-auto max-h-72">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b-2 border-gray-200">
                <th class="text-left py-2 px-3 font-semibold text-gray-700">Provinsi</th>
                <th class="text-right py-2 px-3 font-semibold text-gray-700">Jumlah</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($provList as $i => $pv) : ?>
              <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="py-2.5 px-3 text-blue-600 font-medium">
                  <?php echo htmlspecialchars($pv['provinsi']); ?>
                </td>
                <td class="py-2.5 px-3 text-right font-semibold text-gray-700">
                  <?php echo $pv['jumlah']; ?> Orang
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

  </div>
</section>

<!-- ═══════════ SECTION 4: BERITA + CARDS + BANNER ═══════════ -->
<section id="berita" class="py-16 bg-white">
  <div class="max-w-7xl mx-auto px-4">
    <div class="text-center mb-10">
      <h2 class="text-3xl font-bold text-gray-900 mb-2">Berita PMI</h2>
      <p class="text-gray-500">informasi PMI dari setiap provinsi</p>
    </div>
    <div class="relative flex items-center gap-4 mb-10">
      <button onclick="beritaSlide(-1)"
              class="flex-shrink-0 bg-red-100 hover:bg-red-200 rounded-full w-10 h-10 flex items-center justify-center transition">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 overflow-hidden">
        <div id="berita-wrapper" class="flex transition-transform duration-500">
          <div class="min-w-full flex gap-6 items-start px-2">
            <div class="w-1/2 bg-gray-200 rounded-xl flex items-center justify-center" style="min-height:260px">
              <div class="text-6xl"><img src="../assets/sbs.jpg" alt="Berita PMI" class="w-full h-full object-cover"></div>
            </div>
            <div class="w-1/2">
              <h3 class="text-xl font-bold text-gray-900 mb-2">PMI SOLO TERIMA PENGHARGAAN SBS</h3>
              <div class="flex items-center gap-2 text-gray-500 text-sm mb-4">
                <span>Solo</span><span>|</span><span>21 April 2026</span>
              </div>
              <p class="text-gray-600 text-sm leading-relaxed mb-4">Dalam acara tersebut PMI Kota Surakarta mendapatkan penghargaan atas partisipasi aktif pengerahan relawan dalam setiap kegiatan pembagian Paket Sembako yg di Gelar rutin setiap tahun oleh SBS.</p>
              <a href="https://share.google/zu3Guf8tnqGwfnB0X" class="inline-block bg-red-600 hover:bg-red-700 text-white text-sm px-5 py-2 rounded-lg font-semibold transition gate-link">Selengkapnya</a>
            </div>
          </div>
          <div class="min-w-full flex gap-6 items-start px-2">
            <div class="w-1/2 bg-gray-200 rounded-xl flex items-center justify-center" style="min-height:260px">
              <div class="text-6xl"><img src="../assets/kebumen.jpg" alt="Berita PMI" class="w-full h-full object-cover"></div>
            </div>
            <div class="w-1/2">
              <h3 class="text-xl font-bold text-gray-900 mb-2">Jusuf Kalla Buka Latgab dan Bhakti Sibat PMI Nasional III di Kebumen</h3>
              <div class="flex items-center gap-2 text-gray-500 text-sm mb-4">
                <span>Kebumen</span><span>|</span><span>25 September 2024</span>
              </div>
              <p class="text-gray-600 text-sm leading-relaxed mb-4">PMI bersama seluruh masyarakat membentuk Siaga Bencana Berbasis Masyarakat (Sibat) artinya relawan PMI bukan hanya yang ada tetapi seluruh masyarakat Indonesia untuk menjaga lingkungannya dengan cara memperbaiki iklim dan menanam pohon sebanyak-banyaknya baik di kawasan gunung, bukit atau sekeliling kita.</p>
              <a href="https://share.google/dULXe6p3qOZaXwryR" class="inline-block bg-red-600 hover:bg-red-700 text-white text-sm px-5 py-2 rounded-lg font-semibold transition gate-link">Selengkapnya</a>
            </div>
          </div>
        </div>
      </div>
      <button onclick="beritaSlide(1)"
              class="flex-shrink-0 bg-red-100 hover:bg-red-200 rounded-full w-10 h-10 flex items-center justify-center transition">
        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </button>
    </div>

    <!-- 4 Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div onclick="bukaModal('modal-tentang')"
           class="bg-gray-800 rounded-xl p-6 text-white cursor-pointer hover:bg-gray-700 transition">
        <h3 class="text-lg font-bold mb-2">Tentang Donor darah</h3>
        <p class="text-gray-300 text-sm">mari kita donorkan darah kita</p>
      </div>
      <div onclick="bukaModal('modal-cara')"
           class="bg-red-600 rounded-xl p-6 text-white cursor-pointer hover:bg-red-700 transition">
        <h3 class="text-lg font-bold mb-2">CARA DONOR</h3>
        <p class="text-red-100 text-sm">Cara dan langkah-langkah donor darah melalui Ayodonor PMI</p>
      </div>
      <div onclick="bukaModal('modal-kebijakan')"
           class="bg-blue-600 rounded-xl p-6 text-white cursor-pointer hover:bg-blue-700 transition">
        <h3 class="text-lg font-bold mb-2">KEBIJAKAN DONOR</h3>
        <p class="text-blue-100 text-sm">ketentuan dan persetujuan Donor melalui Ayodonor PMI</p>
      </div>
      <div onclick="bukaModal('modal-laporan')"
           class="bg-green-600 rounded-xl p-6 text-white cursor-pointer hover:bg-green-700 transition">
        <h3 class="text-lg font-bold mb-2">LAPORAN DONOR</h3>
        <p class="text-green-100 text-sm">laporan pertanggung jawaban Donor Ayodonor PMI</p>
      </div>
    </div>

    <!-- Banner Ayo Donor -->
    <div class="bg-red-600 rounded-xl flex items-center justify-between px-10 py-6 mb-0">
      <p class="text-white text-2xl font-extrabold tracking-wide">AYO DONOR SEKARANG !!</p>
      <a href="#pendonoran"
         class="bg-white text-red-600 font-bold px-8 py-3 rounded-lg hover:bg-red-50 transition text-sm">
        DONOR DARAH
      </a>
    </div>
  </div>
</section>

<!-- ═══════════ SECTION 5: FORM PENDONORAN ═══════════ -->
<section id="pendonoran" class="py-16 bg-gray-50">
  <div class="max-w-2xl mx-auto px-4">
    <div class="text-center mb-8">
      <p class="text-red-600 font-semibold text-sm uppercase tracking-wider mb-1">PMI AYODONOR</p>
      <h2 class="text-3xl font-bold text-gray-900">Form Pendonoran</h2>
      <p class="text-gray-500 mt-2">Lengkapi data diri Anda untuk mendaftar donor darah</p>
    </div>
    <?php if ($isLoggedIn) : ?>
    <?php if ($donorSuccess !== '') : ?>
    <div class="bg-green-50 text-green-700 text-sm px-4 py-2 rounded-lg mb-4"><?php echo htmlspecialchars($donorSuccess); ?></div>
    <?php endif; ?>
    <?php if ($donorError !== '') : ?>
    <div class="bg-red-50 text-red-600 text-sm px-4 py-2 rounded-lg mb-4"><?php echo htmlspecialchars($donorError); ?></div>
    <?php endif; ?>
    <div class="bg-white rounded-xl shadow p-8 text-center">
      <div class="text-5xl mb-4">🩸</div>
      <p class="text-gray-600 mb-6">Klik tombol di bawah untuk melengkapi data pendonoran Anda melalui 3 langkah mudah.</p>
      <?php if ($donorUser) : ?>
      <p class="text-green-600 text-sm mb-4 font-medium">✓ Data pendonoran Anda sudah tersimpan. Anda bisa memperbarui data.</p>
      <?php endif; ?>
      <a href="../proses/cek_goldar.php"
         class="inline-block bg-red-600 hover:bg-red-700 text-white font-bold px-10 py-3 rounded-lg transition">
        <?php echo $donorUser ? 'Perbarui Data Donor' : 'Mulai Isi Data Donor'; ?>
      </a>
    </div>
    <?php else : ?>
    <div class="bg-white rounded-xl shadow p-10 text-center">
      <div class="text-5xl mb-4">🩸</div>
      <p class="text-gray-600 mb-6">Silakan login terlebih dahulu untuk mendaftar donor darah.</p>
      <a href="/login.php"
         class="inline-block bg-red-600 hover:bg-red-700 text-white font-bold px-8 py-3 rounded-lg transition">
        Login Sekarang
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══════════ FOOTER ═══════════ -->
<footer id="footer" class="bg-red-600 text-white py-10">
  <div class="max-w-7xl mx-auto px-4 grid grid-cols-3 gap-8 items-center">
    <div class="flex gap-4 justify-center">
      <a href="#" class="bg-white rounded-full w-10 h-10 flex items-center justify-center text-red-600 hover:bg-red-100 transition">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
      </a>
      <a href="#" class="bg-white rounded-full w-10 h-10 flex items-center justify-center text-red-600 hover:bg-red-100 transition">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
      </a>
      <a href="#" class="bg-white rounded-full w-10 h-10 flex items-center justify-center text-red-600 hover:bg-red-100 transition">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" fill="white"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
      </a>
    </div>
    <div class="flex flex-col items-center gap-1">
      <div class="font-extrabold text-2xl tracking-wider">AYODONOR</div>
      <div class="text-xs text-red-200 italic">kita sehat mereka selamat</div>
    </div>
    <div class="text-center">
      <p class="font-bold text-sm mb-1">PALANG MERAH INDONESIA</p>
      <p class="text-red-100 text-sm mb-1">Jl. Gatot Subroto Kav.96 Jakarta Selatan</p>
      <p class="text-red-100 text-sm">
        <a href="https://wa.me/6284114764701" target="_blank" class="hover:underline">Tel. +62084114764701</a>
        &nbsp;Fax. +62217995188
      </p>
    </div>
  </div>
</footer>

<!-- ═══════════ OVERLAY: STOK PROVINSI ═══════════ -->
<div id="page-stok" class="fixed inset-0 bg-white z-40 hidden flex-col overflow-auto">
  <div class="bg-white shadow-md px-8 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <svg width="36" height="36" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <path d="M50 5C25 5 5 25 5 50S25 95 50 95 95 75 95 50 75 5 50 5Z" fill="white" stroke="#DC2626" stroke-width="4"/>
        <rect x="42" y="25" width="16" height="50" rx="4" fill="#DC2626"/>
        <rect x="25" y="42" width="50" height="16" rx="4" fill="#DC2626"/>
      </svg>
      <span class="font-bold text-gray-800">Palang Merah Indonesia</span>
    </div>
    <button onclick="tutupStok()" class="text-gray-600 hover:text-red-600 font-medium text-sm">← Kembali</button>
  </div>
  <div class="flex-1 p-8">
    <div class="max-w-5xl mx-auto">
      <p class="text-red-600 font-semibold text-sm text-center mb-1">INFO PMI</p>
      <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">Stok Darah | Giat Donor</h2>
      <div id="stok-tabel-hasil"></div>
      <p class="text-gray-500 text-xs mt-4">* Jumlah Stok Darah dapat berubah sewaktu-waktu, untuk Info stok darah terkini silahkan menghubungi kami dari Ayo donor</p>
    </div>
  </div>
  <footer class="bg-red-600 text-white py-6">
    <div class="max-w-7xl mx-auto px-4 grid grid-cols-3 gap-4 items-center text-center text-sm">
      <div>Ikuti kami di sosial media</div>
      <div class="font-extrabold text-lg">AYODONOR</div>
      <div><p class="font-bold">PALANG MERAH INDONESIA</p><p class="text-red-200 text-xs">Jl. Gatot Subroto Kav.96 Jakarta Selatan</p></div>
    </div>
  </footer>
</div>

<!-- ═══════════ MODAL: TENTANG DONOR ═══════════ -->
<div id="modal-tentang"
     class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this)tutupModal('modal-tentang')">
  <div class="bg-white rounded-xl max-w-2xl w-full p-8 modal-scroll" onclick="event.stopPropagation()">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">Tentang Donor Darah</h2>
    <hr class="mb-4">
    <p class="text-gray-600 leading-relaxed mb-4">Donor darah adalah proses pengambilan darah dari seseorang secara sukarela untuk disimpan di bank darah dan digunakan untuk transfusi darah kepada orang yang membutuhkan.</p>
    <ul class="list-disc pl-6 text-gray-600 mb-4 space-y-2">
      <li>Membantu pasien yang membutuhkan transfusi darah segera</li>
      <li>Meningkatkan kesehatan pendonor karena tubuh memproduksi sel darah baru</li>
      <li>Mengurangi risiko penyakit jantung dan stroke</li>
      <li>Mendapat pemeriksaan kesehatan gratis setiap kali donor</li>
      <li>Berkontribusi nyata bagi kemanusiaan</li>
    </ul>
    <p class="text-gray-600 leading-relaxed mb-6">Kami <strong>percaya</strong> bahwa setiap tetes darah yang Anda donasikan membawa harapan bagi mereka yang membutuhkan. Bersama-sama, kita dapat menciptakan Indonesia yang lebih sehat dan saling peduli.</p>
    <hr class="mb-4">
    <button onclick="tutupModal('modal-tentang')" class="text-red-600 font-semibold hover:underline text-sm">Tutup</button>
  </div>
</div>

<!-- ═══════════ MODAL: CARA DONOR ═══════════ -->
<div id="modal-cara"
     class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this)tutupModal('modal-cara')">
  <div class="bg-white rounded-xl max-w-2xl w-full p-8" onclick="event.stopPropagation()">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">CARA ANDA MENDONOR</h2>
    <hr class="mb-6">
    <div class="flex gap-6 mb-6">
      <div class="w-1/2 bg-gray-100 rounded-lg flex items-center justify-center" style="min-height:180px">
        <div class="text-center p-4"><div class="text-5xl mb-2">🩸</div><p class="text-gray-400 text-xs">[ Gambar Cara Donor ]</p></div>
      </div>
      <div class="w-1/2 text-sm text-gray-600 space-y-3">
        <p class="font-semibold text-gray-800">Langkah-langkah Donor Darah:</p>
        <p><span class="font-bold text-red-600">1.</span> Datang ke kantor PMI atau unit donor darah terdekat.</p>
        <p><span class="font-bold text-red-600">2.</span> Isi formulir pendaftaran dan tunjukkan identitas diri.</p>
        <p><span class="font-bold text-red-600">3.</span> Lakukan pemeriksaan kesehatan awal.</p>
        <p><span class="font-bold text-red-600">4.</span> Proses pengambilan darah oleh petugas medis terlatih.</p>
      </div>
    </div>
    <hr class="mb-4">
    <button onclick="tutupModal('modal-cara')" class="text-red-600 font-semibold hover:underline text-sm">Tutup</button>
  </div>
</div>

<!-- ═══════════ MODAL: KEBIJAKAN ═══════════ -->
<div id="modal-kebijakan"
     class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this)tutupModal('modal-kebijakan')">
  <div class="bg-white rounded-xl max-w-2xl w-full p-8 modal-scroll" onclick="event.stopPropagation()">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">KEBIJAKAN &amp; PRIVASI</h2>
    <hr class="mb-4">
    <p class="text-gray-600 leading-relaxed mb-4">Selamat datang di platform Ayodonor PMI. Kebijakan privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi informasi pribadi Anda saat menggunakan layanan kami.</p>
    <p class="text-gray-600 leading-relaxed mb-4">Kami berkomitmen untuk melindungi privasi Anda. Informasi yang Anda berikan hanya akan digunakan untuk keperluan layanan donor darah dan tidak akan dibagikan kepada pihak ketiga tanpa persetujuan Anda.</p>
    <p class="text-gray-600 leading-relaxed mb-4">Data medis Anda bersifat rahasia dan hanya dapat diakses oleh petugas medis PMI yang berwenang. Kami menggunakan teknologi enkripsi untuk melindungi data Anda.</p>
    <p class="text-gray-600 leading-relaxed mb-4">Dengan mendaftar dan menggunakan layanan Ayodonor PMI, Anda menyetujui kebijakan privasi ini. Kami berhak memperbarui kebijakan ini sewaktu-waktu.</p>
    <hr class="mb-4">
    <div class="flex justify-end">
      <button onclick="tutupModal('modal-kebijakan')" class="text-red-600 font-semibold hover:underline text-sm">Tutup</button>
    </div>
  </div>
</div>

<!-- ═══════════ MODAL: LAPORAN ═══════════ -->
<div id="modal-laporan"
     class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this)tutupModal('modal-laporan')">
  <div class="bg-white rounded-xl max-w-sm w-full p-8 text-center" onclick="event.stopPropagation()">
    <p class="text-gray-800 font-semibold text-lg mb-6">Ingin berpindah ke Laporan Donor?</p>
    <div class="flex gap-4 justify-center">
      <button onclick="window.location.href='#'"
              class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-semibold transition">Iya</button>
      <button onclick="tutupModal('modal-laporan')"
              class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg font-semibold transition">Tidak</button>
    </div>
  </div>
</div>

<!-- ═══════════ JAVASCRIPT ═══════════ -->
<script>
const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

// Gate links
document.querySelectorAll('.gate-link').forEach(function(el) {
  el.addEventListener('click', function(e) {
    const href = this.getAttribute('href');
    if (!isLoggedIn && (!href || !href.startsWith('#'))) {
      e.preventDefault();
      window.location.href = '../login.php';
    }
  });
});
document.querySelectorAll('.gate-btn').forEach(function(el) {
  el.addEventListener('click', function(e) {
    if (!isLoggedIn) {
      e.preventDefault();
      window.location.href = '../login.php';
    }
  });
});

// Hero Slider
let heroIdx    = 0;
const heroTotal = 3;
function heroSlide(dir) {
  heroIdx = (heroIdx + dir + heroTotal) % heroTotal;
  document.getElementById('hero-slider').style.transform = 'translateX(-' + (heroIdx * 100) + '%)';
}

// Berita Slider
let beritaIdx    = 0;
const beritaTotal = 2;
function beritaSlide(dir) {
  beritaIdx = (beritaIdx + dir + beritaTotal) % beritaTotal;
  document.getElementById('berita-wrapper').style.transform = 'translateX(-' + (beritaIdx * 100) + '%)';
}

// Modal
function bukaModal(id) {
  if (!isLoggedIn) { window.location.href = '../login.php'; return; }
  const el = document.getElementById(id);
  el.classList.remove('hidden');
  el.classList.add('flex');
}
function tutupModal(id) {
  const el = document.getElementById(id);
  el.classList.add('hidden');
  el.classList.remove('flex');
}

// Cari Provinsi
function cariProvinsi() {
  if (!isLoggedIn) { window.location.href = '../login.php'; return; }
  const prov = document.getElementById('inputProvinsi').value.trim();
  if (prov === '') return;

  const xhr = new XMLHttpRequest();
  xhr.open('GET', '../data.php?action=cari_provinsi&provinsi=' + encodeURIComponent(prov), true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      const data = JSON.parse(xhr.responseText);
      if (data.status !== 'success') return;

      let rows = '';
      let no   = 1;
      data.data.forEach(function(d) {
        rows += '<tr class="' + (no % 2 === 0 ? 'bg-white' : 'bg-gray-50') + '">' +
          '<td class="py-3 px-4 text-sm">' + no + '</td>' +
          '<td class="py-3 px-4 text-sm">' + d.nama + '</td>' +
          '<td class="py-3 px-4 text-sm">' + d.kota + '</td>' +
          '<td class="py-3 px-4 text-sm font-mono">' + d.kode_donor + '</td>' +
          '<td class="py-3 px-4 text-sm text-center">' + (d.golongan_darah === 'A'  ? d.stok : '') + '</td>' +
          '<td class="py-3 px-4 text-sm text-center">' + (d.golongan_darah === 'B'  ? d.stok : '') + '</td>' +
          '<td class="py-3 px-4 text-sm text-center">' + (d.golongan_darah === 'O'  ? d.stok : '') + '</td>' +
          '<td class="py-3 px-4 text-sm text-center">' + (d.golongan_darah === 'AB' ? d.stok : '') + '</td>' +
          '<td class="py-3 px-4 text-sm text-center">' + d.stok + '</td>' +
          '</tr>';
        no++;
      });

      if (rows === '') {
        rows = '<tr><td colspan="9" class="text-center py-6 text-gray-400">Data tidak ditemukan</td></tr>';
      }

      document.getElementById('stok-tabel-hasil').innerHTML =
        '<div class="overflow-x-auto">' +
        '<table class="w-full border border-gray-200 rounded-xl overflow-hidden text-sm">' +
        '<thead class="bg-red-600 text-white">' +
        '<tr>' +
        '<th class="py-3 px-4 text-left">No</th>' +
        '<th class="py-3 px-4 text-left">Nama</th>' +
        '<th class="py-3 px-4 text-left">Kota</th>' +
        '<th class="py-3 px-4 text-left">Kode Donor</th>' +
        '<th class="py-3 px-4 text-center">A</th>' +
        '<th class="py-3 px-4 text-center">B</th>' +
        '<th class="py-3 px-4 text-center">O</th>' +
        '<th class="py-3 px-4 text-center">AB</th>' +
        '<th class="py-3 px-4 text-center">Jumlah Donor</th>' +
        '</tr></thead><tbody>' + rows +
        '<tr class="bg-red-50 font-bold border-t-2 border-red-300">' +
        '<td colspan="4" class="py-3 px-4 text-sm">TOTAL</td>' +
        '<td class="py-3 px-4 text-sm text-center">' + data.total.A  + '</td>' +
        '<td class="py-3 px-4 text-sm text-center">' + data.total.B  + '</td>' +
        '<td class="py-3 px-4 text-sm text-center">' + data.total.O  + '</td>' +
        '<td class="py-3 px-4 text-sm text-center">' + data.total.AB + '</td>' +
        '<td class="py-3 px-4 text-sm text-center">' + data.total.donor + '</td>' +
        '</tr></tbody></table></div>';

      const pg = document.getElementById('page-stok');
      pg.classList.remove('hidden');
      pg.classList.add('flex');
    }
  };
  xhr.send();
}

function tutupStok() {
  const pg = document.getElementById('page-stok');
  pg.classList.add('hidden');
  pg.classList.remove('flex');
}

// Chart Stok Darah
const stokData = <?php echo json_encode($stokList); ?>;
const ctx = document.getElementById('chartDarah') ? document.getElementById('chartDarah').getContext('2d') : null;
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: stokData.map(function(d) { return d.golongan_darah; }),
      datasets: [{
        label: 'STOK DARAH',
        data: stokData.map(function(d) { return parseInt(d.jumlah); }),
        backgroundColor: [
          'rgba(255,99,132,0.6)',
          'rgba(54,162,235,0.6)',
          'rgba(255,206,86,0.6)',
          'rgba(153,102,255,0.6)'
        ],
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: true } },
      scales:  { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });
}
</script>
</body>
</html>