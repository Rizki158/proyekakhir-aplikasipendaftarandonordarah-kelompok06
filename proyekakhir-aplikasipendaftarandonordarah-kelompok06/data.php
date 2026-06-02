<?php
require_once 'config/koneksi.php';

$action = $_GET['action'] ?? '';

if ($action === 'stok_darah') {
    $result = $conn->query("
        SELECT golongan_darah, SUM(jumlah) as jumlah
        FROM stok_darah
        GROUP BY golongan_darah
        ORDER BY golongan_darah
    ");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'pendonor_provinsi') {
    $result = $conn->query("
        SELECT p.nama_provinsi as provinsi, COUNT(u.id) as jumlah
        FROM provinsi p
        LEFT JOIN kota k ON k.id_provinsi = p.id
        LEFT JOIN pendonor dn ON dn.id_kota = k.id
        LEFT JOIN users u ON u.id = dn.id_user AND u.role = 'user'
        GROUP BY p.id, p.nama_provinsi
        HAVING jumlah > 0
        ORDER BY jumlah DESC
    ");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'cari_provinsi') {
    $cari = trim($_GET['provinsi'] ?? '');
    if ($cari === '') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Provinsi wajib diisi']);
        exit;
    }

    $like = '%' . $cari . '%';
    $stmt = $conn->prepare("
        SELECT dn.nama_lengkap as nama, k.nama_kota as kota,
               dn.kode_donor, sd.golongan_darah, sd.jumlah as stok
        FROM pendonor dn
        JOIN kota k ON k.id = dn.id_kota
        JOIN provinsi p ON p.id = k.id_provinsi
        LEFT JOIN stok_darah sd ON sd.id_kota = k.id
        WHERE p.nama_provinsi LIKE ? OR k.nama_kota LIKE ?
        ORDER BY p.nama_provinsi, k.nama_kota, dn.nama_lengkap
    ");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $data  = [];
    $total = ['A' => 0, 'B' => 0, 'O' => 0, 'AB' => 0, 'donor' => 0];
    $seen  = [];

    while ($row = $result->fetch_assoc()) {
        $key = $row['kode_donor'];
        if (!in_array($key, $seen)) {
            $seen[]  = $key;
            $data[]  = $row;
            $total['donor']++;
            if (isset($total[$row['golongan_darah']])) {
                $total[$row['golongan_darah']]++;
            }
        }
    }
    $total['orang'] = $total['donor'];

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $data, 'total' => $total]);
    exit;
}

if ($action === 'get_kota') {
    $id_provinsi = intval($_GET['id_provinsi'] ?? 0);
    $stmt = $conn->prepare("SELECT id, nama_kota FROM kota WHERE id_provinsi = ? ORDER BY nama_kota");
    $stmt->bind_param('i', $id_provinsi);
    $stmt->execute();
    $result = $stmt->get_result();
    $data   = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'donor_submit') {
    require_once 'config/auth.php';
    if (!isLoggedIn()) {
        header('Location: user/login.php');
        exit;
    }

    $user_id  = intval($_SESSION['user_id']);
    $id_kota  = intval($_GET['id_kota']  ?? 0);
    $golongan = strtoupper(trim($_GET['golongan'] ?? ''));
    $valid_gol = ['A', 'B', 'O', 'AB'];

    if ($id_kota === 0 || !in_array($golongan, $valid_gol)) {
        $_SESSION['donor_error'] = 'Data tidak valid';
        header('Location: user/dashboard.php#pendonoran');
        exit;
    }

    $stmtU = $conn->prepare("UPDATE users SET id_kota = ? WHERE id = ?");
    $stmtU->bind_param('ii', $id_kota, $user_id);
    $stmtU->execute();

    $stmtS = $conn->prepare("UPDATE stok_darah SET jumlah = jumlah + 1 WHERE id_kota = ? AND golongan_darah = ?");
    $stmtS->bind_param('is', $id_kota, $golongan);
    $stmtS->execute();

    if ($stmtS->affected_rows > 0) {
        $_SESSION['donor_success'] = 'Pendaftaran donor berhasil! Terima kasih.';
    } else {
        $_SESSION['donor_error'] = 'Gagal memperbarui stok';
    }

    header('Location: user/dashboard.php#pendonoran');
    exit;
}
?>

