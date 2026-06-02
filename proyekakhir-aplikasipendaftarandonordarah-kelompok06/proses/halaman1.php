<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

$stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->bind_param('i', $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$stmtDonor = $conn->prepare("SELECT * FROM pendonor WHERE id_user = ?");
$stmtDonor->bind_param('i', $user_id);
$stmtDonor->execute();
$donor = $stmtDonor->get_result()->fetch_assoc();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap           = trim($_POST['nama_lengkap']           ?? '');
    $kode_donor             = trim($_POST['kode_donor']             ?? '');
    $tempat_lahir           = trim($_POST['tempat_lahir']           ?? '');
    $tanggal_lahir          = trim($_POST['tanggal_lahir']          ?? '');
    $jenis_kelamin          = trim($_POST['jenis_kelamin']          ?? '');
    $golongan_darah         = strtoupper(trim($_POST['golongan_darah'] ?? ''));
    $pekerjaan              = trim($_POST['pekerjaan']              ?? '');
    $keterangan_pekerjaan   = trim($_POST['keterangan_pekerjaan']   ?? '');
    $alamat                 = trim($_POST['alamat']                 ?? '');
    $kota_donor             = trim($_POST['kota_donor']             ?? '');
    $provinsi_donor         = trim($_POST['provinsi_donor']         ?? '');
    $telepon                = trim($_POST['telepon']                ?? '');
    $email                  = trim($_POST['email']                  ?? '');
    $status_donor           = trim($_POST['status_donor']           ?? '');
    $tanggal_donor_terakhir = trim($_POST['tanggal_donor_terakhir'] ?? '');
    $persentase_donor       = trim($_POST['persentase_donor']       ?? '');

    if ($nama_lengkap === '' || $kode_donor === '' || $tempat_lahir === '' ||
        $tanggal_lahir === '' || $jenis_kelamin === '' || $golongan_darah === '' ||
        $pekerjaan === '' || $alamat === '' || $kota_donor === '' ||
        $provinsi_donor === '' || $telepon === '' || $email === '' || $status_donor === '') {
        $error = 'wajib';
    }

    if ($error === '' && !str_ends_with(strtolower($email), '@gmail.com')) {
        $error = 'email_format';
    }

    if ($error === '') {
        // Cari atau buat provinsi
        $stmtCekProv = $conn->prepare("SELECT id FROM provinsi WHERE nama_provinsi = ?");
        $stmtCekProv->bind_param('s', $provinsi_donor);
        $stmtCekProv->execute();
        $resProv = $stmtCekProv->get_result()->fetch_assoc();

        if ($resProv) {
            $id_provinsi = $resProv['id'];
        } else {
            $stmtInsProv = $conn->prepare("INSERT INTO provinsi (nama_provinsi) VALUES (?)");
            $stmtInsProv->bind_param('s', $provinsi_donor);
            $stmtInsProv->execute();
            $id_provinsi = $conn->insert_id;
        }

        // Cari atau buat kota
        $stmtCekKota = $conn->prepare("SELECT id FROM kota WHERE nama_kota = ? AND id_provinsi = ?");
        $stmtCekKota->bind_param('si', $kota_donor, $id_provinsi);
        $stmtCekKota->execute();
        $resKota = $stmtCekKota->get_result()->fetch_assoc();

        if ($resKota) {
            $id_kota = $resKota['id'];
        } else {
            $stmtInsKota = $conn->prepare("INSERT INTO kota (id_provinsi, nama_kota) VALUES (?, ?)");
            $stmtInsKota->bind_param('is', $id_provinsi, $kota_donor);
            $stmtInsKota->execute();
            $id_kota = $conn->insert_id;

            $golongans = ['A', 'B', 'O', 'AB'];
            foreach ($golongans as $gol) {
                $stmtStok = $conn->prepare("INSERT INTO stok_darah (id_kota, golongan_darah, jumlah) VALUES (?, ?, 0)");
                $stmtStok->bind_param('is', $id_kota, $gol);
                $stmtStok->execute();
            }
        }

        // Update stok darah berdasarkan golongan darah pendonor
        // Cek apakah sebelumnya sudah ada golongan darah berbeda
        if ($donor && $donor['golongan_darah'] && $donor['golongan_darah'] !== $golongan_darah && $donor['id_kota']) {
            // Kurangi stok golongan lama
            $stmtKurang = $conn->prepare("UPDATE stok_darah SET jumlah = GREATEST(jumlah - 1, 0) WHERE id_kota = ? AND golongan_darah = ?");
            $gol_lama   = $donor['golongan_darah'];
            $kota_lama  = $donor['id_kota'];
            $stmtKurang->bind_param('is', $kota_lama, $gol_lama);
            $stmtKurang->execute();
        }

        // Tambah stok golongan baru jika belum ada data donor sebelumnya atau golongan berubah
        if (!$donor || $donor['golongan_darah'] !== $golongan_darah) {
            $stmtTambah = $conn->prepare("UPDATE stok_darah SET jumlah = jumlah + 1 WHERE id_kota = ? AND golongan_darah = ?");
            $stmtTambah->bind_param('is', $id_kota, $golongan_darah);
            $stmtTambah->execute();
        }

        $_SESSION['proses'] = [
            'nama_lengkap'           => $nama_lengkap,
            'kode_donor'             => $kode_donor,
            'tempat_lahir'           => $tempat_lahir,
            'tanggal_lahir'          => $tanggal_lahir,
            'jenis_kelamin'          => $jenis_kelamin,
            'golongan_darah'         => $golongan_darah,
            'pekerjaan'              => $pekerjaan,
            'keterangan_pekerjaan'   => $keterangan_pekerjaan,
            'alamat'                 => $alamat,
            'kota_donor'             => $kota_donor,
            'provinsi_donor'         => $provinsi_donor,
            'id_provinsi'            => $id_provinsi,
            'id_kota'                => $id_kota,
            'telepon'                => $telepon,
            'email'                  => $email,
            'status_donor'           => $status_donor,
            'tanggal_donor_terakhir' => $tanggal_donor_terakhir,
            'persentase_donor'       => $persentase_donor,
        ];

        header('Location: halaman2.php');
        exit;
    }
}

$p = $_SESSION['proses'] ?? [];

function ambil($p, $donor, $key, $default = '') {
    if (isset($p[$key]) && $p[$key] !== '') {
        return htmlspecialchars($p[$key]);
    }
    if ($donor && isset($donor[$key]) && $donor[$key] !== null && $donor[$key] !== '') {
        return htmlspecialchars($donor[$key]);
    }
    return $default;
}

$kotaDonorVal     = ambil($p, null, 'kota_donor');
$provinsiDonorVal = ambil($p, null, 'provinsi_donor');

if ($kotaDonorVal === '' && $donor && $donor['id_kota']) {
    $stmtKN = $conn->prepare("SELECT nama_kota FROM kota WHERE id = ?");
    $stmtKN->bind_param('i', $donor['id_kota']);
    $stmtKN->execute();
    $resKN = $stmtKN->get_result()->fetch_assoc();
    if ($resKN) $kotaDonorVal = htmlspecialchars($resKN['nama_kota']);
}

if ($provinsiDonorVal === '' && $donor && $donor['id_provinsi']) {
    $stmtPN = $conn->prepare("SELECT nama_provinsi FROM provinsi WHERE id = ?");
    $stmtPN->bind_param('i', $donor['id_provinsi']);
    $stmtPN->execute();
    $resPN = $stmtPN->get_result()->fetch_assoc();
    if ($resPN) $provinsiDonorVal = htmlspecialchars($resPN['nama_provinsi']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Pribadi - Ayodonor PMI</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-red-300 to-red-500 min-h-screen py-10">

<div class="max-w-3xl mx-auto px-4">

  <!-- Langkah -->
  <div class="flex items-center gap-2 mb-8">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-white text-red-600 flex items-center justify-center text-sm font-bold shadow">1</div>
      <span class="text-sm font-semibold text-white">Data Pribadi</span>
    </div>
    <div class="flex-1 h-0.5 bg-white bg-opacity-40"></div>
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center text-sm font-bold">2</div>
      <span class="text-sm text-white text-opacity-70">Riwayat Kesehatan</span>
    </div>
    <div class="flex-1 h-0.5 bg-white bg-opacity-40"></div>
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center text-sm font-bold">3</div>
      <span class="text-sm text-white text-opacity-70">Konfirmasi</span>
    </div>
  </div>

  <?php if ($error === 'email_format') : ?>
  <div class="bg-red-100 text-red-700 text-sm px-4 py-2 rounded-lg mb-4">Email harus menggunakan @gmail.com</div>
  <?php elseif ($error === 'wajib') : ?>
  <div class="bg-red-100 text-red-700 text-sm px-4 py-2 rounded-lg mb-4">Semua field wajib diisi</div>
  <?php endif; ?>

  <form method="POST" onsubmit="return validasiH1()">

    <!-- DATA DIRI -->
    <div class="bg-white rounded-2xl shadow-lg p-8 mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Data Diri</h2>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Nama Lengkap</label>
        <input name="nama_lengkap" type="text" id="inp-nama_lengkap"
               value="<?php echo ambil($p, $donor, 'nama_lengkap'); ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-nama_lengkap" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Kode Donor</label>
        <input name="kode_donor" type="text" id="inp-kode_donor"
               value="<?php echo ambil($p, $donor, 'kode_donor'); ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-kode_donor" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Tempat Lahir</label>
        <input name="tempat_lahir" type="text" id="inp-tempat_lahir"
               value="<?php echo ambil($p, $donor, 'tempat_lahir'); ?>"
               placeholder="Kota tempat lahir"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-tempat_lahir" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Lahir</label>
        <input name="tanggal_lahir" type="date" id="inp-tanggal_lahir"
               value="<?php echo ambil($p, $donor, 'tanggal_lahir'); ?>"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-tanggal_lahir" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis Kelamin</label>
        <select name="jenis_kelamin" id="inp-jenis_kelamin"
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          <option value="">-- Pilih --</option>
          <option value="Laki-laki" <?php echo ambil($p, $donor, 'jenis_kelamin') === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
          <option value="Perempuan" <?php echo ambil($p, $donor, 'jenis_kelamin') === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
        </select>
        <p id="err-jenis_kelamin" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <!-- GOLONGAN DARAH -->
      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Golongan Darah</label>
        <select name="golongan_darah" id="inp-golongan_darah"
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          <option value="">-- Pilih --</option>
          <?php
          $golongans = ['A', 'B', 'O', 'AB'];
          $golVal    = ambil($p, $donor, 'golongan_darah');
          foreach ($golongans as $gol) {
              $sel = $golVal === $gol ? 'selected' : '';
              echo '<option value="' . $gol . '" ' . $sel . '>' . $gol . '</option>';
          }
          ?>
        </select>
        <p id="err-golongan_darah" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-2">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Pekerjaan</label>
        <select name="pekerjaan" id="inp-pekerjaan" onchange="cekPekerjaan()"
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          <option value="">-- Pilih --</option>
          <?php
          $pekerjaans = [
              'PNS/TNI/Polri',
              'Karyawan Swasta/BUMN',
              'Wiraswasta/Pedagang',
              'Petani/Nelayan',
              'Ibu Rumah Tangga (IRT)',
              'Pensiunan',
              'Lainnya'
          ];
          $pekVal = ambil($p, $donor, 'pekerjaan');
          foreach ($pekerjaans as $pek) {
              $sel = $pekVal === $pek ? 'selected' : '';
              echo '<option value="' . $pek . '" ' . $sel . '>' . $pek . '</option>';
          }
          ?>
        </select>
        <p id="err-pekerjaan" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div id="box-keterangan-pekerjaan" class="mt-2 mb-4 hidden">
        <textarea name="keterangan_pekerjaan" id="inp-keterangan_pekerjaan" rows="3"
                  placeholder="pensiunan apa?"
                  class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"><?php echo ambil($p, $donor, 'keterangan_pekerjaan'); ?></textarea>
      </div>
    </div>

    <!-- KONTAK DAN DOMISILI -->
    <div class="bg-white rounded-2xl shadow-lg p-8 mb-6">
      <h2 class="text-xl font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Kontak dan Domisili</h2>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Alamat Tempat Tinggal</label>
        <textarea name="alamat" id="inp-alamat" rows="3"
                  placeholder="Masukkan alamat lengkap"
                  class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"><?php echo ambil($p, $donor, 'alamat'); ?></textarea>
        <p id="err-alamat" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Ingin Donor di Kota</label>
        <input name="kota_donor" type="text" id="inp-kota_donor"
               value="<?php echo $kotaDonorVal; ?>"
               placeholder="Nama kota tempat donor"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-kota_donor" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Ingin Donor di Provinsi</label>
        <input name="provinsi_donor" type="text" id="inp-provinsi_donor"
               value="<?php echo $provinsiDonorVal; ?>"
               placeholder="Nama provinsi tempat donor"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-provinsi_donor" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Nomor Telepon</label>
        <input name="telepon" type="text" id="inp-telepon"
               value="<?php echo ambil($p, $donor, 'telepon'); ?>"
               placeholder="Nomor telepon aktif"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-telepon" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div class="mb-2">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
        <input name="email" type="text" id="inp-email" onblur="cekEmail()"
               value="<?php echo ambil($p, $donor, 'email'); ?>"
               placeholder="contoh@gmail.com"
               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        <p id="err-email" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
        <p id="err-email-format" class="text-red-500 text-xs mt-1 hidden">*tolong masukkan input dengan benar</p>
      </div>
    </div>

    <!-- RIWAYAT DONOR -->
    <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
      <h2 class="text-xl font-bold text-gray-800 mb-6 pb-2 border-b border-gray-200">Riwayat Donor</h2>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Status Donor</label>
        <select name="status_donor" id="inp-status_donor" onchange="cekStatusDonor()"
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
          <option value="">-- Pilih --</option>
          <option value="Sudah pernah" <?php echo ambil($p, $donor, 'status_donor') === 'Sudah pernah' ? 'selected' : ''; ?>>Sudah pernah</option>
          <option value="Belum pernah" <?php echo ambil($p, $donor, 'status_donor') === 'Belum pernah' ? 'selected' : ''; ?>>Belum pernah</option>
        </select>
        <p id="err-status_donor" class="text-red-500 text-xs mt-1 hidden">*Wajib diisi</p>
      </div>

      <div id="box-riwayat-donor" class="hidden">
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Donor Terakhir</label>
          <input name="tanggal_donor_terakhir" type="date"
                 value="<?php echo ambil($p, $donor, 'tanggal_donor_terakhir'); ?>"
                 class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
        </div>

        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Persentase Donor</label>
          <select name="persentase_donor"
                  class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
            <option value="">-- Pilih --</option>
            <?php
            $persens = ['5%','6%','7%','8%','9%','10%'];
            $persVal = ambil($p, $donor, 'persentase_donor');
            foreach ($persens as $ps) {
                $sel = $persVal === $ps ? 'selected' : '';
                echo '<option value="' . $ps . '" ' . $sel . '>' . $ps . '</option>';
            }
            ?>
          </select>
        </div>
      </div>
    </div>

    <div class="flex justify-end mb-10">
      <button type="submit"
              class="bg-white text-red-600 font-bold px-10 py-3 rounded-lg shadow hover:bg-red-50 transition">
        Lanjut →
      </button>
    </div>

  </form>
</div>

<script>
function cekPekerjaan() {
  const pek  = document.getElementById('inp-pekerjaan').value;
  const box  = document.getElementById('box-keterangan-pekerjaan');
  const area = document.getElementById('inp-keterangan_pekerjaan');

  if (pek === 'Pensiunan') {
    box.classList.remove('hidden');
    area.placeholder = 'pensiunan apa?';
  } else if (pek === 'Lainnya') {
    box.classList.remove('hidden');
    area.placeholder = '*Wajib diisi';
  } else {
    box.classList.add('hidden');
  }
}

function cekStatusDonor() {
  const status = document.getElementById('inp-status_donor').value;
  const box    = document.getElementById('box-riwayat-donor');
  if (status === 'Sudah pernah') {
    box.classList.remove('hidden');
  } else {
    box.classList.add('hidden');
  }
}

function cekEmail() {
  const email     = document.getElementById('inp-email').value.trim().toLowerCase();
  const errFormat = document.getElementById('err-email-format');
  if (email !== '' && !email.endsWith('@gmail.com')) {
    errFormat.classList.remove('hidden');
  } else {
    errFormat.classList.add('hidden');
  }
}

function validasiH1() {
  const fields = [
    'nama_lengkap', 'kode_donor', 'tempat_lahir', 'tanggal_lahir',
    'jenis_kelamin', 'golongan_darah', 'pekerjaan', 'alamat',
    'kota_donor', 'provinsi_donor', 'telepon', 'email', 'status_donor'
  ];
  let valid = true;

  fields.forEach(function(f) {
    const el  = document.getElementById('inp-' + f);
    const err = document.getElementById('err-' + f);
    if (!el || !err) return;
    if (el.value.trim() === '') {
      err.classList.remove('hidden');
      valid = false;
    } else {
      err.classList.add('hidden');
    }
  });

  const email     = document.getElementById('inp-email').value.trim().toLowerCase();
  const errFormat = document.getElementById('err-email-format');
  const errEmail  = document.getElementById('err-email');
  if (email !== '' && !email.endsWith('@gmail.com')) {
    errFormat.classList.remove('hidden');
    errEmail.classList.add('hidden');
    valid = false;
  } else if (email !== '') {
    errFormat.classList.add('hidden');
  }

  return valid;
}

window.addEventListener('DOMContentLoaded', function() {
  cekPekerjaan();
  cekStatusDonor();
});
</script>
</body>
</html>