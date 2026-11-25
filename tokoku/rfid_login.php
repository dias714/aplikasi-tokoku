<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);

$token = $payload['csrf_token'] ?? '';
if (
    !is_string($token)
    || !isset($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sesi formulir tidak valid. Muat ulang halaman.']);
    exit;
}

$uidInput = $payload['rfid_uid'] ?? '';
$rfidUid = is_string($uidInput) ? strtoupper(trim($uidInput)) : '';

if ($rfidUid === '' || mb_strlen($rfidUid) > 64) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'UID kartu RFID tidak valid.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, name, email, role
         FROM users
         WHERE UPPER(rfid_uid) = :rfid_uid
         LIMIT 1'
    );
    $stmt->execute(['rfid_uid' => $rfidUid]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Kartu RFID belum terdaftar untuk akun mana pun.']);
        exit;
    }

    $role = strtolower((string) ($user['role'] ?? ''));
    $routes = [
        'admin'     => 'admin/dashboard.php',
        'kasir'     => 'kasir/dashboard.php',
        'pelanggan' => 'customer/index.php',
    ];

    if (!isset($routes[$role])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Role akun tidak dikenali.']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = (string) ($user['name'] ?? '');
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
    $_SESSION['role'] = $role;

    echo json_encode([
        'ok'           => true,
        'message'      => 'Login RFID berhasil sebagai ' . strtoupper($role) . '.',
        'role'         => $role,
        'redirect_url' => appUrl($routes[$role]),
    ]);
} catch (Throwable $e) {
    error_log('Login RFID: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Terjadi gangguan saat memproses login RFID.']);
}