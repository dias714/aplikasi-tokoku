<?php
require_once __DIR__ . '/config/database.php';
$midtrans = require __DIR__ . '/config/midtrans.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$notif = json_decode($raw ?: '{}', true);

$orderId           = (string) ($notif['order_id'] ?? '');
$statusCode        = (string) ($notif['status_code'] ?? '');
$grossAmount       = (string) ($notif['gross_amount'] ?? '');
$signatureKey      = (string) ($notif['signature_key'] ?? '');
$transactionStatus = (string) ($notif['transaction_status'] ?? '');
$fraudStatus       = (string) ($notif['fraud_status'] ?? 'accept');

$expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $midtrans['server_key']);
if (!hash_equals($expectedSignature, $signatureKey)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$newStatus = 'pending';
if ($transactionStatus === 'capture') {
    $newStatus = ($fraudStatus === 'accept') ? 'paid' : 'challenge';
} elseif ($transactionStatus === 'settlement') {
    $newStatus = 'paid';
} elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'], true)) {
    $newStatus = 'failed';
}

$stmt = $pdo->prepare(
    'UPDATE tbl_transaksi
     SET status_pembayaran = :status
     WHERE midtrans_order_id = :order_id'
);
$stmt->execute([
    'status'   => $newStatus,
    'order_id' => $orderId,
]);

echo json_encode(['ok' => true, 'status' => $newStatus]);