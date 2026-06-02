<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';
requireLogin();

if (!isset($_SESSION['proses']) || !isset($_SESSION['proses']['berat_badan'])) {
    header('Location: halaman2.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$p       = $_SESSION['proses'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $konfirmasi = isset($_POST['konfirmasi']) ? 1 : 0;

    if ($konfirmasi !== 1) {
        $error = 'Anda harus menyetujui persetujuan terlebih dahulu';
    } else {
        $stmtCek = $conn->prepare("SELECT id FROM pendonor WHERE id_user = ?");
        $stmtCek->bind_param('i', $user_id);
        $stmtCek->execute();
        $sudahAda = $stmtCek->get_result()->fetch_assoc();

        // Handle nullable fields
        $tgl_donor  = (!empty($p['tanggal_donor_terakhir'])) ? $p['tanggal_donor_terakhir'] : null;
        $persen     = (!empty($p['persentase_donor']))        ? $p['persentase_donor']        : null;
        $ket_pek    = (!empty($p['keterangan_pekerjaan']))    ? $p['keterangan_pekerjaan']    : null;
        $penyakit   = (!empty($p['penyakit_bawaan']))         ? $p['penyakit_bawaan']         : null;
        $nama_obat  = (!empty($p['nama_obat']))               ? $p['nama_obat']               : null;
        $kondisi_w  = (!empty($p['kondisi_wanita']))          ? $p['kondisi_wanita']          : null;
        $golongan   = !empty($p['golongan_darah']) ? $p['golongan_darah'] : null;

        if ($sudahAda) {
            // UPDATE existing donor
            $stmt = $conn->prepare("
                UPDATE pendonor SET
                    nama_lengkap            = ?,
                    kode_donor              = ?,
                    tempat_lahir            = ?,
                    tanggal_lahir           = ?,
                    jenis_kelamin           = ?,
                    golongan_darah          = ?,
                    pekerjaan               = ?,
                    keterangan_pekerjaan    = ?,
                    alamat                  = ?,
                    id_provinsi             = ?,
                    id_kota                 = ?,
                    telepon                 = ?,
                    email                   = ?,
                    status_donor            = ?,
                    tanggal_donor_terakhir  = ?,
                    persentase_donor        = ?,
                    berat_badan             = ?,
                    penyakit_bawaan         = ?,
                    konsumsi_obat           = ?,
                    nama_obat               = ?,
                    riwayat_operasi         = ?,
                    riwayat_vaksinasi       = ?,
                    kondisi_wanita          = ?,
                    konfirmasi              = ?
                WHERE id_user = ?
            ");
            // 24 SET fields + 1 WHERE field = 25 total
            // sssssssssiisssssssssiisii
            $stmt->bind_param(
                'sssssssssiisssssssssiisii',
                $p['nama_lengkap'],
                $p['kode_donor'],
                $p['tempat_lahir'],
                $p['tanggal_lahir'],
                $p['jenis_kelamin'],
                $golongan,
                $p['pekerjaan'],
                $ket_pek,
                $p['alamat'],
                $p['id_provinsi'],
                $p['id_kota'],
                $p['telepon'],
                $p['email'],
                $p['status_donor'],
                $tgl_donor,
                $persen,
                $p['berat_badan'],
                $penyakit,
                $p['konsumsi_obat'],
                $nama_obat,
                $p['riwayat_operasi'],
                $p['riwayat_vaksinasi'],
                $kondisi_w,
                $konfirmasi,
                $user_id
            );
        } else {
            // INSERT new donor
            $stmt = $conn->prepare("
                INSERT INTO pendonor (
                    id_user, nama_lengkap, kode_donor, tempat_lahir, tanggal_lahir,
                    jenis_kelamin, golongan_darah, pekerjaan, keterangan_pekerjaan,
                    alamat, id_provinsi, id_kota, telepon, email, status_donor,
                    tanggal_donor_terakhir, persentase_donor, berat_badan,
                    penyakit_bawaan, konsumsi_obat, nama_obat,
                    riwayat_operasi, riwayat_vaksinasi, kondisi_wanita, konfirmasi
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            // 25 fields total
            // isssssssssiisssssssssiisi
            $stmt->bind_param(
                'isssssssssiisssssssssiisi',
                $user_id,
                $p['nama_lengkap'],
                $p['kode_donor'],
                $p['tempat_lahir'],
                $p['tanggal_lahir'],
                $p['jenis_kelamin'],
                $golongan,
                $p['pekerjaan'],
                $ket_pek,
                $p['alamat'],
                $p['id_provinsi'],
                $p['id_kota'],
                $p['telepon'],
                $p['email'],
                $p['status_donor'],
                $tgl_donor,
                $persen,
                $p['berat_badan'],
                $penyakit,
                $p['konsumsi_obat'],
                $nama_obat,
                $p['riwayat_operasi'],
                $p['riwayat_vaksinasi'],
                $kondisi_w,
                $konfirmasi
            );
        }

        if ($stmt->execute()) {
            unset($_SESSION['proses']);
            $_SESSION['donor_success'] = 'Data pendonor berhasil disimpan!';
            $success = 'berhasil';
        } else {
            $error = 'Gagal menyimpan data: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Konfirmasi - Ayodonor PMI</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
  * { font-family: 'Inter', sans-serif; }
  html { scroll-behavior: smooth; }
</style>
</head>
<body class="bg-gradient-to-br from-red-300 to-red-500 min-h-screen py-6 sm:py-10 px-4 sm:px-6">

<div class="max-w-3xl mx-auto">

  <!-- ══════════════════════════════════════════
       LANGKAH PROGRESS
       ══════════════════════════════════════════ -->
  <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-4 mb-8 px-2 sm:px-0">
    
    <!-- Step 1 -->
    <div class="flex items-center gap-2 flex-1">
      <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-green-400 text-white flex items-center justify-center text-xs sm:text-sm font-bold flex-shrink-0">✓</div>
      <span class="text-xs sm:text-sm text-white font-medium hidden sm:inline">Data Pribadi</span>
    </div>
    <div class="h-0.5 bg-white w-full sm:w-auto flex-1"></div>

    <!-- Step 2 -->
    <div class="flex items-center gap-2 flex-1">
      <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-green-400 text-white flex items-center justify-center text-xs sm:text-sm font-bold flex-shrink-0">✓</div>
      <span class="text-xs sm:text-sm text-white font-medium hidden sm:inline">Riwayat Kesehatan</span>
    </div>
    <div class="h-0.5 bg-white w-full sm:w-auto flex-1"></div>

    <!-- Step 3 -->
    <div class="flex items-center gap-2 flex-1">
      <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white text-red-600 flex items-center justify-center text-xs sm:text-sm font-bold shadow flex-shrink-0">3</div>
      <span class="text-xs sm:text-sm font-semibold text-white">Konfirmasi</span>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       CONTENT
       ══════════════════════════════════════════ -->
  <?php if ($success === 'berhasil') : ?>
  
  <!-- SUCCESS SCREEN -->
  <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-10 text-center">
    <h2 class="text-2xl sm:text-3xl font-bold text-green-700 mb-2">Data Berhasil Disimpan!</h2>
    <p class="text-gray-600 text-sm sm:text-base mb-8">Terima kasih telah mendaftarkan diri sebagai pendonor darah PMI.</p>
    <a href="../user/dashboard.php"
       class="inline-block bg-red-600 hover:bg-red-700 text-white font-bold px-6 sm:px-8 py-2.5 sm:py-3 rounded-lg transition text-sm sm:text-base">
      Kembali ke Dashboard
    </a>
  </div>

  <?php else : ?>

  <!-- FORM SCREEN -->
  <?php if ($error !== '') : ?>
  <div class="bg-red-100 text-red-700 text-sm px-4 py-3 rounded-lg mb-4 border border-red-200">
     <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 mb-6">
    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-1">Persetujuan & Konfirmasi</h2>
    <p class="text-gray-500 text-sm mb-6">Silakan baca dan setujui syarat & ketentuan</p>
    <hr class="mb-6">

    <!-- Teks Persetujuan -->
    <div class="max-w-2xl mx-auto border border-gray-200 rounded-xl p-4 sm:p-6 bg-gray-50 mb-6">
      <div class="modal-scroll max-h-48 sm:max-h-64 overflow-y-auto text-xs sm:text-sm text-gray-700 leading-relaxed space-y-3">
        
        <p class="font-semibold text-gray-800">📋 Pernyataan & Persetujuan Donor Darah</p>

        <p>
          Dengan ini saya menyatakan bahwa telah memberikan informasi yang <strong>benar, lengkap, dan jujur</strong> mengenai kondisi kesehatan dan riwayat medis saya dalam proses pendaftaran sebagai pendonor darah di Palang Merah Indonesia (PMI).
        </p>

        <p>
          Saya memahami bahwa donor darah adalah tindakan sukarela untuk membantu nyawa orang lain. Saya telah mempertimbangkan dengan matang dan percaya bahwa kesehatan saya memungkinkan untuk melakukan donor darah dengan aman.
        </p>

        <p>
          Saya menyetujui bahwa darah saya akan melalui serangkaian pemeriksaan kesehatan oleh petugas medis PMI yang berwenang. Saya juga memahami bahwa darah saya dapat digunakan untuk keperluan transfusi medis sesuai kebutuhan.
        </p>

        <p>
          <strong>Syarat & Ketentuan:</strong>
          <ul class="list-disc pl-5 mt-2 space-y-1">
            <li>Usia 17-60 tahun (untuk donor pertama kali)</li>
            <li>Berat badan minimal 45 kg</li>
            <li>Tekanan darah normal (90-140 / 60-90 mmHg)</li>
            <li>Hemoglobin pria: 12.5-16 g/dL, wanita: 12-14 g/dL</li>
            <li>Tidak sedang hamil atau menyusui (untuk wanita)</li>
            <li>Tidak memiliki penyakit menular atau kondisi kesehatan tertentu</li>
          </ul>
        </p>

        <p>
          Data pribadi dan kesehatan Anda akan dijaga kerahasiaannya sesuai dengan kebijakan privasi PMI dan peraturan perundangan yang berlaku.
        </p>

        <p class="text-xs text-gray-500 italic mt-4">
          Pernyataan ini dibuat dengan sepenuh kesadaran dan tanpa paksaan dari pihak manapun.
        </p>

      </div>
    </div>

    <!-- Checkbox Persetujuan -->
    <form method="POST" class="max-w-2xl mx-auto">
      <div class="flex items-start gap-3 mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <input type="checkbox" name="konfirmasi" id="cb-konfirmasi" value="1"
               class="w-5 h-5 mt-0.5 text-red-600 rounded focus:ring-red-400 flex-shrink-0 cursor-pointer">
        <label for="cb-konfirmasi" class="text-sm text-gray-700 cursor-pointer leading-relaxed">
          <span class="font-semibold">Saya menyetujui</span> semua syarat dan ketentuan yang telah saya baca. Data yang saya isi adalah benar, jujur, dan lengkap. Saya siap untuk melakukan donor darah dan menerima semua konsekuensi yang mungkin terjadi.
        </label>
      </div>

      <hr class="mb-6">

      <!-- Button -->
      <div class="flex flex-col-reverse sm:flex-row gap-3 sm:gap-4 justify-between">
        <a href="halaman2.php"
           class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-6 sm:px-8 py-2.5 sm:py-3 rounded-lg transition text-sm sm:text-base text-center">
          ← Kembali
        </a>
        <button type="submit" onclick="return cekKonfirmasi()"
                class="bg-red-600 hover:bg-red-700 active:bg-red-800 text-white font-bold px-8 sm:px-10 py-2.5 sm:py-3 rounded-lg transition text-sm sm:text-base">
           Konfirmasi
        </button>
      </div>
    </form>

  </div>

  <?php endif; ?>

</div>

<script>
function cekKonfirmasi() {
  const cb = document.getElementById('cb-konfirmasi');
  if (!cb || !cb.checked) {
    alert(' Anda harus menyetujui persetujuan terlebih dahulu untuk melanjutkan.');
    return false;
  }
  return true;
}
</script>
</body>
</html>