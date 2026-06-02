<?php
require_once 'config/koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: user/dashboard.php');
    exit;
}

$error   = '';
$success = isset($_GET['register']) ? 'Registrasi berhasil! Silakan login.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama']     ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($nama === '' || $password === '') {
        $error = 'Nama dan password wajib diisi.';
    } else {
        // Cari user berdasarkan nama saja, tanpa peduli role
        $stmt = $conn->prepare("SELECT * FROM users WHERE nama = ? LIMIT 1");
        $stmt->bind_param('s', $nama);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['role']    = $user['role'];

            // Arahkan sesuai role
            if ($user['role'] === 'admin') {
                header('Location: admin/index.php');
            } else {
                header('Location: user/dashboard.php');
            }
            exit;
        } else {
            $error = 'Nama atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Ayodonor PMI</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-b from-red-400 to-red-600 flex items-center justify-center p-4">

<!-- MODAL INFORMASI -->
<div id="modal-info"
     class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this)tutupInfo()">
  <div class="bg-white rounded-2xl max-w-sm w-full p-8 text-center" onclick="event.stopPropagation()">
    <div class="flex flex-col items-center mb-5">
      <img width="70" height="70" src="assets/logo_PMI.png" class="mb-3">
      <p class="font-bold text-gray-800 text-base">Palang Merah Indonesia</p>
    </div>
    <ul class="text-left list-disc pl-5 text-gray-600 text-sm space-y-2 mb-6">
      <li>Silahkan login dengan memasukkan nama dan password Anda.</li>
      <li>Jika Anda belum memiliki akun, silakan melakukan registrasi akun.</li>
      <li>Jika anda bukan pendonor dan tidak dapat memiliki akun anda bisa menggunakan Mode Tamu</li>
    </ul>
    <button onclick="tutupInfo()"
            class="bg-red-500 hover:bg-red-600 text-white px-8 py-2 rounded-lg font-semibold text-sm transition">
      Tutup
    </button>
  </div>
</div>

<!-- CARD LOGIN -->
<div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8">

  <!-- Logo -->
  <div class="flex flex-col items-center mb-5">
    <svg width="90" height="90" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="mb-3">
      <path d="M50 5C25 5 5 25 5 50S25 95 50 95 95 75 95 50 75 5 50 5Z" fill="white" stroke="#DC2626" stroke-width="4"/>
      <rect x="42" y="25" width="16" height="50" rx="4" fill="#DC2626"/>
      <rect x="25" y="42" width="50" height="16" rx="4" fill="#DC2626"/>
    </svg>
    <p class="font-bold text-gray-800">Palang Merah Indonesia</p>
  </div>

  <h2 class="text-2xl font-bold text-red-500 text-center mb-1">Selamat Datang</h2>
  <p class="text-gray-500 text-sm text-center mb-5">Silakan login untuk melanjutkan</p>

  <!-- Tombol Info -->
  <div class="flex justify-end mb-4">
    <button onclick="bukaInfo()"
            class="flex items-center gap-1 text-yellow-500 font-semibold text-sm hover:text-yellow-600 transition">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      Informasi
    </button>
  </div>

  <!-- Alert error / success -->
  <?php if ($error !== ''): ?>
  <div class="bg-red-50 text-red-600 text-sm text-center px-4 py-2 rounded-lg mb-4">
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if ($success !== ''): ?>
  <div class="bg-green-50 text-green-600 text-sm text-center px-4 py-2 rounded-lg mb-4">
    <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>

  <!-- Form Login -->
  <form method="POST" onsubmit="return validasiLogin()" class="space-y-3 mb-3">

    <div>
      <input name="nama" type="text" id="inp-nama" placeholder="Nama"
             value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
             class="w-full bg-gray-100 rounded-full px-5 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      <p id="err-nama" class="text-red-500 text-xs ml-4 mt-1 hidden">*Wajib diisi</p>
    </div>

    <div>
      <input name="password" id="inp-password" type="password" placeholder="Password"
             class="w-full bg-gray-100 rounded-full px-5 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      <p id="err-password" class="text-red-500 text-xs ml-4 mt-1 hidden">*Wajib diisi</p>
    </div>

    <button type="submit"
            class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-full text-sm transition">
      LOGIN
    </button>
  </form>

  <!-- Masuk sebagai Tamu -->
  <a href="user/dashboard.php"
     class="block w-full text-center border-2 border-red-400 text-red-500 font-bold py-3 rounded-full text-sm hover:bg-red-50 mb-4 transition">
    Masuk sebagai Tamu
  </a>

  <!-- Daftar -->
  <div class="text-center">
    <a href="register.php" class="text-blue-500 text-sm hover:underline">
      Belum punya akun? Ayo jadilah pendonor sekarang
    </a>
  </div>

</div><!-- end card -->

<script>
function bukaInfo() {
  const m = document.getElementById('modal-info');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function tutupInfo() {
  const m = document.getElementById('modal-info');
  m.classList.add('hidden');
  m.classList.remove('flex');
}

function validasiLogin() {
  const nama = document.getElementById('inp-nama').value.trim();
  const pass = document.getElementById('inp-password').value.trim();
  let valid  = true;

  if (nama === '') {
    document.getElementById('err-nama').classList.remove('hidden');
    valid = false;
  } else {
    document.getElementById('err-nama').classList.add('hidden');
  }

  if (pass === '') {
    document.getElementById('err-password').classList.remove('hidden');
    valid = false;
  } else {
    document.getElementById('err-password').classList.add('hidden');
  }

  return valid;
}
</script>
</body>
</html>