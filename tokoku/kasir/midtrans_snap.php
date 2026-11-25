<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('kasir');
require_once __DIR__ . '/../config/database.php';
$midtrans = require __DIR__ . '/../config/midtrans.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);

$token = $payload['csrf_token'] ?? '';
if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sesi formulir tidak valid. Muat ulang halaman.']);
    exit;
}

$postedQuantities = $payload['quantities'] ?? [];
$quantities = [];
if (is_array($postedQuantities)) {
    foreach ($postedQuantities as $pid => $qty) {
        $pidInt = (int) $pid;
        $qtyInt = (int) $qty;
        if ($pidInt > 0 && $qtyInt > 0) {
            $quantities[$pidInt] = $qtyInt;
        }
    }
}

if (!$quantities) {
    http_response_code(422);
    echo json_encode(['error' => 'Pilih minimal satu produk terlebih dahulu.']);
    exit;
}

$ids = array_keys($quantities);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id_produk, nama_produk, harga, stok FROM tbl_produk WHERE id_produk IN ($placeholders)");
$stmt->execute($ids);
$products = $stmt->fetchAll();

if (count($products) !== count($quantities)) {
    http_response_code(422);
    echo json_encode(['error' => 'Salah satu produk tidak ditemukan.']);
    exit;
}

$grossAmount = 0;
$itemDetails = [];

foreach ($products as $product) {
    $id = (int) $product['id_produk'];
    $qty = $quantities[$id];
    if ($qty > (int) $product['stok']) {
        http_response_code(422);
        echo json_encode(['error' => 'Stok ' . $product['nama_produk'] . ' tidak mencukupi.']);
        exit;
    }
    $price = (int) round((float) $product['harga']);
    $grossAmount += $price * $qty;
    $itemDetails[] = [
        'id'       => (string) $id,
        'price'    => $price,
        'quantity' => $qty,
        'name'     => mb_substr($product['nama_produk'], 0, 50),
    ];
}

$orderId = 'TOKOKU-' . date('YmdHis') . '-' . random_int(100, 999);
$customerName = trim((string) ($payload['nama_pelanggan'] ?? 'Pelanggan Umum'));
if ($customerName === '') {
    $customerName = 'Pelanggan Umum';
}

$requestBody = [
    'transaction_details' => [
        'order_id'     => $orderId,
        'gross_amount' => $grossAmount,
    ],
    'item_details' => $itemDetails,
    'customer_details' => [
        'first_name' => mb_substr($customerName, 0, 50),
    ],
    // Aktifkan QRIS / GoPay / e-wallet & VA di Snap Sandbox
    'enabled_payments' => ['other_qris', 'gopay', 'shopeepay', 'bca_va', 'bni_va', 'bri_va'],
];

$ch = curl_init($midtrans['snap_api_url']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($midtrans['server_key'] . ':'),
    ],
    CURLOPT_POSTFIELDS     => json_encode($requestBody),
    CURLOPT_TIMEOUT        => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Gagal terhubung ke Midtrans Sandbox.',
        'detail' => $curlErr ?: $response,
    ]);
    exit;
}

$data = json_decode($response, true);
echo json_encode([
    'snap_token'   => $data['token'] ?? null,
    'redirect_url' => $data['redirect_url'] ?? null,
    'order_id'     => $orderId,
    'gross_amount' => $grossAmount,
]);