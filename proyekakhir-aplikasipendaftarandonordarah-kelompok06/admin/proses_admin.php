<?php
require_once '../config/koneksi.php';
require_once '../config/auth.php';

if (!isLoggedIn() || getRole() !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'tambah_user') {
    $nama     = trim($_POST['nama']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($nama !== '' && $email !== '' && $password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'user')");
        $stmt->bind_param('sss', $nama, $email, $hash);
        $stmt->execute();
    }
    header('Location: index.php');
    exit;
}

if ($aksi === 'edit_user') {
    $id       = intval($_POST['id']       ?? 0);
    $nama     = trim($_POST['nama']       ?? '');
    $email    = trim($_POST['email']      ?? '');
    $password = trim($_POST['password']   ?? '');

    if ($id > 0 && $nama !== '' && $email !== '') {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET nama = ?, email = ?, password = ? WHERE id = ? AND role = 'user'");
            $stmt->bind_param('sssi', $nama, $email, $hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama = ?, email = ? WHERE id = ? AND role = 'user'");
            $stmt->bind_param('ssi', $nama, $email, $id);
        }
        $stmt->execute();
    }
    header('Location: index.php');
    exit;
}

if ($aksi === 'hapus_user') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    header('Location: index.php');
    exit;
}

if ($aksi === 'hapus_donor') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmtAmbil = $conn->prepare("SELECT id_kota, golongan_darah FROM pendonor WHERE id = ?");
        $stmtAmbil->bind_param('i', $id);
        $stmtAmbil->execute();
        $donor = $stmtAmbil->get_result()->fetch_assoc();

        if ($donor && $donor['id_kota'] && $donor['golongan_darah']) {
            $stmtKurang = $conn->prepare("UPDATE stok_darah SET jumlah = GREATEST(jumlah - 1, 0) WHERE id_kota = ? AND golongan_darah = ?");
            $stmtKurang->bind_param('is', $donor['id_kota'], $donor['golongan_darah']);
            $stmtKurang->execute();

            // Log pengurangan
            $ket = 'Pendonor dihapus oleh admin';
            $stmtLog = $conn->prepare("INSERT INTO log_stok (id_kota, golongan_darah, perubahan, jenis, keterangan) VALUES (?, ?, 1, 'kurang', ?)");
            $stmtLog->bind_param('iss', $donor['id_kota'], $donor['golongan_darah'], $ket);
            $stmtLog->execute();
        }

        $stmt = $conn->prepare("DELETE FROM pendonor WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    header('Location: index.php');
    exit;
}

if ($aksi === 'tambah_stok') {
    $id_kota        = intval($_POST['id_kota']       ?? 0);
    $golongan_darah = strtoupper(trim($_POST['golongan_darah'] ?? ''));
    $jumlah         = intval($_POST['jumlah']        ?? 0);
    $valid_gol      = ['A', 'B', 'O', 'AB'];

    if ($id_kota > 0 && in_array($golongan_darah, $valid_gol) && $jumlah > 0) {
        $stmtCek = $conn->prepare("SELECT id FROM stok_darah WHERE id_kota = ? AND golongan_darah = ?");
        $stmtCek->bind_param('is', $id_kota, $golongan_darah);
        $stmtCek->execute();
        $ada = $stmtCek->get_result()->fetch_assoc();

        if ($ada) {
            $stmt = $conn->prepare("UPDATE stok_darah SET jumlah = jumlah + ? WHERE id_kota = ? AND golongan_darah = ?");
            $stmt->bind_param('iis', $jumlah, $id_kota, $golongan_darah);
        } else {
            $stmt = $conn->prepare("INSERT INTO stok_darah (id_kota, golongan_darah, jumlah) VALUES (?, ?, ?)");
            $stmt->bind_param('isi', $id_kota, $golongan_darah, $jumlah);
        }
        $stmt->execute();

        // Ambil info kota untuk log
        $stmtInfo = $conn->prepare("SELECT k.nama_kota, p.nama_provinsi FROM kota k JOIN provinsi p ON p.id = k.id_provinsi WHERE k.id = ?");
        $stmtInfo->bind_param('i', $id_kota);
        $stmtInfo->execute();
        $info = $stmtInfo->get_result()->fetch_assoc();

        $ket = 'Stok ditambah admin - ' . ($info['nama_kota'] ?? '') . ', ' . ($info['nama_provinsi'] ?? '');
        $stmtLog = $conn->prepare("INSERT INTO log_stok (id_kota, golongan_darah, perubahan, jenis, keterangan) VALUES (?, ?, ?, 'tambah', ?)");
        $stmtLog->bind_param('isis', $id_kota, $golongan_darah, $jumlah, $ket);
        $stmtLog->execute();

        // Simpan notifikasi ke session untuk ditampilkan
        $_SESSION['notif_stok'] = [
            'kota'          => $info['nama_kota'] ?? '-',
            'provinsi'      => $info['nama_provinsi'] ?? '-',
            'golongan'      => $golongan_darah,
            'jumlah'        => $jumlah,
            'jenis'         => 'tambah',
        ];
    }
    header('Location: index.php#hal-stok');
    exit;
}

if ($aksi === 'edit_stok') {
    $id_kota = intval($_POST['id_kota'] ?? 0);
    $stok_a  = intval($_POST['stok_a']  ?? 0);
    $stok_b  = intval($_POST['stok_b']  ?? 0);
    $stok_o  = intval($_POST['stok_o']  ?? 0);
    $stok_ab = intval($_POST['stok_ab'] ?? 0);

    if ($id_kota > 0) {
        $golongans = ['A' => $stok_a, 'B' => $stok_b, 'O' => $stok_o, 'AB' => $stok_ab];

        $stmtInfo = $conn->prepare("SELECT k.nama_kota, p.nama_provinsi FROM kota k JOIN provinsi p ON p.id = k.id_provinsi WHERE k.id = ?");
        $stmtInfo->bind_param('i', $id_kota);
        $stmtInfo->execute();
        $info = $stmtInfo->get_result()->fetch_assoc();

        foreach ($golongans as $gol => $jml) {
            // Ambil stok lama
            $stmtLama = $conn->prepare("SELECT jumlah FROM stok_darah WHERE id_kota = ? AND golongan_darah = ?");
            $stmtLama->bind_param('is', $id_kota, $gol);
            $stmtLama->execute();
            $resLama = $stmtLama->get_result()->fetch_assoc();
            $stokLama = $resLama ? intval($resLama['jumlah']) : 0;

            $stmtCek = $conn->prepare("SELECT id FROM stok_darah WHERE id_kota = ? AND golongan_darah = ?");
            $stmtCek->bind_param('is', $id_kota, $gol);
            $stmtCek->execute();
            $ada = $stmtCek->get_result()->fetch_assoc();

            if ($ada) {
                $stmt = $conn->prepare("UPDATE stok_darah SET jumlah = ? WHERE id_kota = ? AND golongan_darah = ?");
                $stmt->bind_param('iis', $jml, $id_kota, $gol);
            } else {
                $stmt = $conn->prepare("INSERT INTO stok_darah (id_kota, golongan_darah, jumlah) VALUES (?, ?, ?)");
                $stmt->bind_param('isi', $id_kota, $gol, $jml);
            }
            $stmt->execute();

            // Log perubahan
            $selisih = $jml - $stokLama;
            if ($selisih !== 0) {
                $jenis   = $selisih > 0 ? 'tambah' : 'kurang';
                $abs     = abs($selisih);
                $ket     = 'Edit stok admin - ' . ($info['nama_kota'] ?? '') . ', ' . ($info['nama_provinsi'] ?? '');
                $stmtLog = $conn->prepare("INSERT INTO log_stok (id_kota, golongan_darah, perubahan, jenis, keterangan) VALUES (?, ?, ?, ?, ?)");
                $stmtLog->bind_param('isiss', $id_kota, $gol, $abs, $jenis, $ket);
                $stmtLog->execute();
            }
        }
    }
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
?>