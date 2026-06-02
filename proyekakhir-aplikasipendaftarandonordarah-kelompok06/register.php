<?php
require_once 'config/koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: user/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($nama === '' || $email === '' || $password === '') {
        $error = 'Semua field wajib diisi';
    } elseif (!str_ends_with(strtolower($email), '@gmail.com')) {
        $error = 'Email harus menggunakan @gmail.com';
    } else {
        $cek = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $cek->bind_param('s', $email);
        $cek->execute();
        $ada = $cek->get_result()->num_rows > 0;

        if ($ada) {
            $error = 'Email sudah terdaftar';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param('sss', $nama, $email, $hash);
            if ($stmt->execute()) {
                header('Location: login.php?register=1');
                exit;
            } else {
                $error = 'Registrasi gagal, coba lagi';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrasi - Ayodonor PMI</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-b from-red-400 to-red-600 flex items-center justify-center p-4">

<!-- MODAL INFORMASI -->
<div id="modal-info-reg"
     class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden items-center justify-center p-4"
     onclick="if(event.target===this)tutupInfoReg()">
  <div class="bg-white rounded-2xl max-w-sm w-full p-8 text-center" onclick="event.stopPropagation()">
    <div class="flex flex-col items-center mb-5">
      <svg width="70" height="70" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="mb-3">
        <path d="M50 5C25 5 5 25 5 50S25 95 50 95 95 75 95 50 75 5 50 5Z" fill="white" stroke="#DC2626" stroke-width="4"/>
        <rect x="42" y="25" width="16" height="50" rx="4" fill="#DC2626"/>
        <rect x="25" y="42" width="50" height="16" rx="4" fill="#DC2626"/>
        <path d="M50 5Q60 8 68 15Q80 8 88 18Q95 30 90 45Q95 55 88 65Q80 75 68 78Q60 90 50 92Q40 90 32 78Q20 75 12 65Q5 55 10 45Q5 30 12 18Q20 8 32 15Q40 8 50 5Z" fill="none" stroke="#DC2626" stroke-width="3"/>
      </svg>
      <p class="font-bold text-gray-800 text-base">Palang Merah Indonesia</p>
    </div>
    <ul class="text-left list-disc pl-5 text-gray-600 text-sm space-y-2 mb-6">
      <li>[Isi informasi registrasi poin 1]</li>
      <li>[Isi informasi registrasi poin 2]</li>
      <li>[Isi informasi registrasi poin 3]</li>
    </ul>
    <button onclick="tutupInfoReg()"
            class="bg-red-500 hover:bg-red-600 text-white px-8 py-2 rounded-lg font-semibold text-sm transition">
      Tutup
    </button>
  </div>
</div>

<!-- CARD REGISTER -->
<div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8">
  <div class="flex flex-col items-center mb-4">
    <svg width="80" height="80" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="mb-2">
      <path d="M50 5C25 5 5 25 5 50S25 95 50 95 95 75 95 50 75 5 50 5Z" fill="white" stroke="#DC2626" stroke-width="4"/>
      <rect x="42" y="25" width="16" height="50" rx="4" fill="#DC2626"/>
      <rect x="25" y="42" width="50" height="16" rx="4" fill="#DC2626"/>
      <path d="M50 5Q60 8 68 15Q80 8 88 18Q95 30 90 45Q95 55 88 65Q80 75 68 78Q60 90 50 92Q40 90 32 78Q20 75 12 65Q5 55 10 45Q5 30 12 18Q20 8 32 15Q40 8 50 5Z" fill="none" stroke="#DC2626" stroke-width="3"/>
    </svg>
    <p class="font-bold text-gray-800 text-sm">Palang Merah Indonesia</p>
  </div>

  <div class="flex justify-end mb-2">
    <button onclick="bukaInfoReg()"
            class="flex items-center gap-1 text-yellow-500 font-semibold text-sm hover:text-yellow-600 transition">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      Informasi
    </button>
  </div>

  <h2 class="text-xl font-bold text-red-500 text-center mb-1">Ayo Donorkan darah anda sekarang</h2>
  <p class="text-gray-500 text-xs text-center mb-4">
    Jadilah pendonor agar masyarakat yang membutuhkan senang atas bantuan anda
  </p>

  <?php if ($error !== '') : ?>
  <div class="bg-red-50 text-red-600 text-sm text-center px-4 py-2 rounded-lg mb-3">
    <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>

  <form method="POST" onsubmit="return validasiRegister()" class="space-y-2 mb-3">
    <div>
      <input name="nama" type="text" placeholder="Masukkan nama"
             value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>"
             class="w-full bg-gray-100 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      <p id="err-nama" class="text-red-500 text-xs ml-4 mt-1 hidden">*Wajib diisi</p>
    </div>
    <div>
      <input name="email" type="text" placeholder="contoh@gmail.com"
             value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
             class="w-full bg-gray-100 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      <p id="err-email" class="text-red-500 text-xs ml-4 mt-1 hidden">*Wajib diisi</p>
      <p id="err-email-format" class="text-red-500 text-xs ml-4 mt-1 hidden">*tolong masukkan input dengan benar</p>
    </div>
    <div>
      <input name="password" type="password" placeholder="Password"
             class="w-full bg-gray-100 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
      <p id="err-password" class="text-red-500 text-xs ml-4 mt-1 hidden">*Wajib diisi</p>
    </div>
    <button type="submit"
            class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2.5 rounded-full text-sm transition">
      DAFTAR SEKARANG
    </button>
  </form>

  <div class="text-center">
    <a href="login.php" class="text-blue-500 text-sm hover:underline">Sudah punya akun? Login di sini</a>
  </div>
</div>

<script>
function bukaInfoReg() {
  const m = document.getElementById('modal-info-reg');
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function tutupInfoReg() {
  const m = document.getElementById('modal-info-reg');
  m.classList.add('hidden');
  m.classList.remove('flex');
}

function validasiRegister() {
  const nama  = document.querySelector('input[name="nama"]').value.trim();
  const email = document.querySelector('input[name="email"]').value.trim();
  const pass  = document.querySelector('input[name="password"]').value.trim();
  let valid   = true;

  const errNama        = document.getElementById('err-nama');
  const errEmail       = document.getElementById('err-email');
  const errEmailFormat = document.getElementById('err-email-format');
  const errPass        = document.getElementById('err-password');

  if (nama === '') {
    errNama.classList.remove('hidden');
    valid = false;
  } else {
    errNama.classList.add('hidden');
  }

  if (email === '') {
    errEmail.classList.remove('hidden');
    errEmailFormat.classList.add('hidden');
    valid = false;
  } else {
    errEmail.classList.add('hidden');
    if (!email.toLowerCase().endsWith('@gmail.com')) {
      errEmailFormat.classList.remove('hidden');
      valid = false;
    } else {
      errEmailFormat.classList.add('hidden');
    }
  }

  if (pass === '') {
    errPass.classList.remove('hidden');
    valid = false;
  } else {
    errPass.classList.add('hidden');
  }

  return valid;
}
</script>
</body>
</html>