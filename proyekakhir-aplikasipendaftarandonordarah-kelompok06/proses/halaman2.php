<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
requireLogin();

if (!isset($_SESSION['proses'])) {
    header('Location: halaman1.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$p1      = $_SESSION['proses'];

$stmtDonor = $conn->prepare("SELECT * FROM pendonor WHERE id_user = ?");
$stmtDonor->bind_param('i', $user_id);
$stmtDonor->execute();
$donor = $stmtDonor->get_result()->fetch_assoc();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $berat_badan       = trim($_POST['berat_badan']       ?? '');
    $penyakit_bawaan   = trim($_POST['penyakit_bawaan']   ?? '');
    $konsumsi_obat     = trim($_POST['konsumsi_obat']     ?? '');
    $nama_obat         = trim($_POST['nama_obat']         ?? '');
    $riwayat_operasi   = isset($_POST['riwayat_operasi'])   ? 1 : 0;
    $riwayat_vaksinasi = isset($_POST['riwayat_vaksinasi']) ? 1 : 0;
    $kondisi_wanita    = '';

    if ($p1['jenis_kelamin'] === 'Perempuan') {
        $pilihan = $_POST['kondisi_wanita'] ?? [];
        $kondisi_wanita = implode(',', $pilihan);
    }

    if ($berat_badan === '' || $konsumsi_obat === '') {
        $error = 'Berat badan dan konsumsi obat wajib diisi';
    } else {
        $_SESSION['proses']['berat_badan']       = $berat_badan;
        $_SESSION['proses']['penyakit_bawaan']   = $penyakit_bawaan;
        $_SESSION['proses']['konsumsi_obat']     = $konsumsi_obat;
        $_SESSION['proses']['nama_obat']         = $nama_obat;
        $_SESSION['proses']['riwayat_operasi']   = $riwayat_operasi;
        $_SESSION['proses']['riwayat_vaksinasi'] = $riwayat_vaksinasi;
        $_SESSION['proses']['kondisi_wanita']    = $kondisi_wanita;
        header('Location: halaman3.php');
        exit;
    }
}

function ambil2($p, $donor, $key, $default = '') {
    if (isset($p[$key]) && $p[$key] !== '') {
        return htmlspecialchars($p[$key]);
    }
    if ($donor && isset($donor[$key]) && $donor[$key] !== null) {
        return htmlspecialchars($donor[$key]);
    }
    return $default;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Kesehatan - Ayodonor PMI</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-l from-red-400 via-white to-red-400 min-h-screen">

<div class="max-w-3xl mx-auto px-4 py-10">

  <!-- Langkah -->
  <div class="flex items-center gap-2 mb-8">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center text-sm font-bold">✓</div>
      <span class="text-sm text-black text-semibold">Data Pribadi</span>
    </div>
    <div class="flex-1 h-0.5 bg-red-600"></div>
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center text-sm font-bold">2</div>
      <span class="text-sm font-semibold text-red-600">Riwayat Kesehatan</span>
    </div>
    <div class="flex-1 h-0.5 bg-gray-300"></div>
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 flex items-center justify-center text-sm font-bold">3</div>
      <span class="text-sm text-gray-400">Konfirmasi</span>
    </div>
  </div>

  <?php if ($error !== '') : ?>
  <div class="bg-red-50 text-red-600 text-sm px-4 py-2 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="POST" onsubmit="return validasiH2()">

    <!-- KONDISI FISIK -->
    <div class="bg-white rounded-xl shadow p-8 mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Kondisi Fisik</h2>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Berat Badan (kg)</label>
        <input name="berat_badan" type="number" id="inp-berat_badan" min="1"
               value="<?php echo ambil2($p1, $donor, 'berat_badan'); ?>"
               placeholder="minimal 45 kg"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-berat_badan" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Penyakit Bawaan</label>
        <input name="penyakit_bawaan" type="text"
               value="<?php echo ambil2($p1, $donor, 'penyakit_bawaan'); ?>"
               placeholder="paru-paru, jantung, kanker, diabetes, epilepsi dan hepatitis"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
    </div>

    <!-- KONSUMSI OBAT -->
    <div class="bg-white rounded-xl shadow p-8 mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Konsumsi Obat</h2>

      <div class="mb-2">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Apakah sedang mengonsumsi obat?</label>
        <select name="konsumsi_obat" id="inp-konsumsi_obat" onchange="cekObat()"
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          <option value="">-- Pilih --</option>
          <option value="Ada"   <?php echo ambil2($p1, $donor, 'konsumsi_obat') === 'Ada'   ? 'selected' : ''; ?>>Ada</option>
          <option value="Tidak" <?php echo ambil2($p1, $donor, 'konsumsi_obat') === 'Tidak' ? 'selected' : ''; ?>>Tidak</option>
        </select>
        <p id="err-konsumsi_obat" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div id="box-nama-obat" class="mt-3 hidden">
        <input name="nama_obat" type="text"
               value="<?php echo ambil2($p1, $donor, 'nama_obat'); ?>"
               placeholder="Apa obat yang sedang anda konsumsi?"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      </div>
    </div>

    <!-- RIWAYAT MEDIS -->
    <div class="bg-white rounded-xl shadow p-8 mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Riwayat Medis</h2>

      <div class="flex items-center gap-3 mb-4">
        <input type="checkbox" name="riwayat_operasi" id="cb-operasi" value="1"
               <?php echo ambil2($p1, $donor, 'riwayat_operasi') == '1' ? 'checked' : ''; ?>
               class="w-4 h-4 text-red-600 rounded focus:ring-red-400">
        <label for="cb-operasi" class="text-sm text-gray-700">Apakah Kamu pernah menjalani Operasi?</label>
      </div>

      <div class="flex items-center gap-3">
        <input type="checkbox" name="riwayat_vaksinasi" id="cb-vaksin" value="1"
               <?php echo ambil2($p1, $donor, 'riwayat_vaksinasi') == '1' ? 'checked' : ''; ?>
               class="w-4 h-4 text-red-600 rounded focus:ring-red-400">
        <label for="cb-vaksin" class="text-sm text-gray-700">Apakah kamu pernah melakukan vaksinasi dalam waktu tertentu?</label>
      </div>
    </div>

    <!-- KHUSUS WANITA -->
    <?php if ($p1['jenis_kelamin'] === 'Perempuan') : ?>
    <?php
    $kondisiArr = [];
    $kondisiVal = ambil2($p1, $donor, 'kondisi_wanita');
    if ($kondisiVal !== '') {
        $kondisiArr = explode(',', $kondisiVal);
    }
    ?>
    <div class="bg-white rounded-xl shadow p-8 mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Khusus Wanita</h2>

      <div class="flex items-center gap-3 mb-3">
        <input type="checkbox" name="kondisi_wanita[]" id="cb-menstruasi" value="Menstruasi"
               <?php echo in_array('Menstruasi', $kondisiArr) ? 'checked' : ''; ?>
               class="w-4 h-4 text-red-600 rounded focus:ring-red-400">
        <label for="cb-menstruasi" class="text-sm text-gray-700">Menstruasi</label>
      </div>

      <div class="flex items-center gap-3 mb-3">
        <input type="checkbox" name="kondisi_wanita[]" id="cb-hamil" value="Hamil"
               <?php echo in_array('Hamil', $kondisiArr) ? 'checked' : ''; ?>
               class="w-4 h-4 text-red-600 rounded focus:ring-red-400">
        <label for="cb-hamil" class="text-sm text-gray-700">Hamil</label>
      </div>

      <div class="flex items-center gap-3">
        <input type="checkbox" name="kondisi_wanita[]" id="cb-menyusui" value="Menyusui"
               <?php echo in_array('Menyusui', $kondisiArr) ? 'checked' : ''; ?>
               class="w-4 h-4 text-red-600 rounded focus:ring-red-400">
        <label for="cb-menyusui" class="text-sm text-gray-700">Menyusui</label>
      </div>
    </div>
    <?php endif; ?>

    <hr class="mb-6">

    <div class="flex justify-between">
      <a href="halaman1.php"
         class="bg-red-500 hover:bg-gray-300 text-white font-bold px-8 py-3 rounded-lg transition">
        ← Kembali
      </a>
      <button type="submit"
              class="bg-red-600 hover:bg-red-700 text-white font-bold px-10 py-3 rounded-lg transition">
        Lanjut →
      </button>
    </div>

  </form>
</div>

<script>
function cekObat() {
  const val = document.getElementById('inp-konsumsi_obat').value;
  const box = document.getElementById('box-nama-obat');
  if (val === 'Ada') {
    box.classList.remove('hidden');
  } else {
    box.classList.add('hidden');
  }
}

function validasiH2() {
  let valid = true;

  const berat    = document.getElementById('inp-berat_badan');
  const errBerat = document.getElementById('err-berat_badan');
  if (berat.value.trim() === '') {
    errBerat.classList.remove('hidden');
    valid = false;
  } else {
    errBerat.classList.add('hidden');
  }

  const obat    = document.getElementById('inp-konsumsi_obat');
  const errObat = document.getElementById('err-konsumsi_obat');
  if (obat.value.trim() === '') {
    errObat.classList.remove('hidden');
    valid = false;
  } else {
    errObat.classList.add('hidden');
  }

  return valid;
}

window.addEventListener('DOMContentLoaded', function() {
  cekObat();
});
</script>
</body>
</html>