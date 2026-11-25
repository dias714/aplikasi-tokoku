<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pelanggan');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/store.php';
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

$city = trim((string) ($payload['kota'] ?? ''));
if ($city === '' || mb_strlen($city) > 50) {
    http_response_code(422);
    echo json_encode(['error' => 'Kota wajib diisi dan maksimal 50 karakter.']);
    exit;
}

$cart = customerCart();
if (!$cart) {
    http_response_code(422);
    echo json_encode(['error' => 'Keranjang Anda masih kosong.']);
    exit;
}

$products = customerCartProducts($pdo, $cart);
if (count($products) !== count($cart)) {
    http_response_code(422);
    echo json_encode(['error' => 'Salah satu produk tidak lagi tersedia.']);
    exit;
}

$grossAmount = 0;
$itemDetails = [];

foreach ($products as $product) {
    $pid = (int) $product['id_produk'];
    $qty = (int) ($cart[$pid] ?? 0);
    if ($qty < 1 || $qty > (int) $product['stok']) {
        http_response_code(422);
        echo json_encode(['error' => 'Stok ' . $product['nama_produk'] . ' tidak mencukupi.']);
        exit;
    }
    $price = (int) round((float) $product['harga']);
    $grossAmount += $price * $qty;
    $itemDetails[] = [
        'id'       => (string) $pid,
        'price'    => $price,
        'quantity' => $qty,
        'name'     => mb_substr((string) $product['nama_produk'], 0, 50),
    ];
}

if ($grossAmount <= 0 || !$itemDetails) {
    http_response_code(422);
    echo json_encode(['error' => 'Total pembayaran tidak valid.']);
    exit;
}

$orderId = 'KATALOG-QRIS-' . date('YmdHis') . '-' . random_int(100, 999);
$customerName = mb_substr(trim((string) ($_SESSION['name'] ?? 'Pelanggan')), 0, 50) ?: 'Pelanggan';

$requestBody = [
    'transaction_details' => [
        'order_id'     => $orderId,
        'gross_amount' => $grossAmount,
    ],
    'item_details' => $itemDetails,
    'customer_details' => [
        'first_name' => $customerName,
    ],
    'enabled_payments' => ['other_qris'],
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
        'error'  => 'Gagal terhubung ke Midtrans Sandbox.',
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