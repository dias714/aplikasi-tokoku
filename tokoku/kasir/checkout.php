<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('kasir');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin_activity.php';

$midtransConfig = require __DIR__ . '/../config/midtrans.php';

$pageTitle = 'Transaksi Baru - Tokoku';
$errors = [];
$quantities = [];
$customerId = '';
$guestName = '';
$amountReceived = '';
$midtransOrderId = '';
$success = $_SESSION['cashier_flash'] ?? '';
unset($_SESSION['cashier_flash']);

function cashierMoneyToCents(string $amount): ?int
{
    if (!preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/', $amount)) {
        return null;
    }

    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
}

function cashierFormatCents(int $amount): string
{
    return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
}

function cashierRedirect(string $path): void
{
    header('Location: ' . appUrl($path));
    exit;
}

$hasMidtransOrderCol = false;
$hasPaymentStatusCol = false;
try {
    $hasMidtransOrderCol = (bool) $pdo->query("SHOW COLUMNS FROM tbl_transaksi LIKE 'midtrans_order_id'")->fetch();
    $hasPaymentStatusCol = (bool) $pdo->query("SHOW COLUMNS FROM tbl_transaksi LIKE 'status_pembayaran'")->fetch();
} catch (Throwable $e) {
    $hasMidtransOrderCol = false;
    $hasPaymentStatusCol = false;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $customerInput = $_POST['id_pelanggan'] ?? '';
    $guestInput = $_POST['nama_pelanggan'] ?? '';
    $receivedInput = $_POST['uang_diterima'] ?? '';
    $methodInput = $_POST['metode_pembayaran'] ?? 'Tunai';
    $midtransOrderInput = $_POST['midtrans_order_id'] ?? '';

    $customerId = is_string($customerInput) ? trim($customerInput) : '';
    $guestName = is_string($guestInput) ? trim($guestInput) : '';
    $amountReceived = is_string($receivedInput) ? trim($receivedInput) : '';
    $paymentMethod = is_string($methodInput) ? trim($methodInput) : 'Tunai';
    $midtransOrderId = is_string($midtransOrderInput) ? trim($midtransOrderInput) : '';
    $postedQuantities = $_POST['quantities'] ?? [];

    if (!in_array($paymentMethod, ['Tunai', 'QRIS'], true)) {
        $paymentMethod = 'Tunai';
    }

    if (
        !is_string($customerInput)
        || !is_string($guestInput)
        || (!is_string($receivedInput) && $paymentMethod !== 'QRIS')
    ) {
        $errors[] = 'Data pelanggan atau pembayaran tidak valid.';
    }

    $token = $_POST['csrf_token'] ?? '';
    if (
        !is_string($token)
        || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.';
    }

    if (!is_array($postedQuantities)) {
        $errors[] = 'Daftar jumlah produk tidak valid.';
        $postedQuantities = [];
    }

    if (mb_strlen($guestName) > 100) {
        $errors[] = 'Nama pelanggan maksimal 100 karakter.';
    }

    if ($customerId !== '' && !ctype_digit($customerId)) {
        $errors[] = 'Pelanggan yang dipilih tidak valid.';
    }

    if ($customerId !== '' && $guestName !== '') {
        $errors[] = 'Pilih pelanggan terdaftar atau isi nama pelanggan umum, bukan keduanya.';
    }

    foreach ($postedQuantities as $productId => $quantity) {
        if (!is_scalar($quantity) || !ctype_digit((string) $productId) || !ctype_digit((string) $quantity)) {
            $errors[] = 'Jumlah produk tidak valid.';
            break;
        }
        $quantity = (int) $quantity;
        if ($quantity < 0 || $quantity > 999) {
            $errors[] = 'Jumlah setiap produk harus antara 0 dan 999.';
            break;
        }
        if ($quantity > 0) {
            $quantities[(int) $productId] = $quantity;
        }
    }

    if (!$quantities) {
        $errors[] = 'Pilih minimal satu produk dengan jumlah lebih dari nol.';
    }

    $receivedCents = null;
    if ($paymentMethod === 'QRIS') {
        if ($midtransOrderId === '') {
            $errors[] = 'Selesaikan pembayaran QRIS melalui popup Midtrans terlebih dahulu.';
        }
        $receivedCents = 0;
    } else {
        $receivedCents = cashierMoneyToCents($amountReceived);
        if ($receivedCents === null) {
            $errors[] = 'Jumlah uang diterima wajib diisi dengan angka maksimal 2 desimal.';
        }
    }

    if (!$errors) {
        $ids = array_keys($quantities);
        sort($ids, SORT_NUMERIC);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $pdo->beginTransaction();

            $productStmt = $pdo->prepare(
                'SELECT id_produk, nama_produk, harga, stok
                 FROM tbl_produk
                 WHERE id_produk IN (' . $placeholders . ')
                 ORDER BY id_produk
                 FOR UPDATE'
            );
            $productStmt->execute($ids);
            $products = $productStmt->fetchAll();

            if (count($products) !== count($quantities)) {
                throw new RuntimeException('Salah satu produk tidak lagi tersedia.');
            }

            $totalCents = 0;
            foreach ($products as $product) {
                $id = (int) $product['id_produk'];
                if ($quantities[$id] > (int) $product['stok']) {
                    throw new RuntimeException('Stok ' . $product['nama_produk'] . ' tidak mencukupi.');
                }
                $priceCents = cashierMoneyToCents((string) $product['harga']);
                if ($priceCents === null || $priceCents > intdiv(999999999999 - $totalCents, $quantities[$id])) {
                    throw new RuntimeException('Total transaksi melebihi batas yang dapat diproses.');
                }
                $totalCents += $priceCents * $quantities[$id];
            }

            if ($paymentMethod === 'QRIS') {
                $receivedCents = $totalCents;
            } elseif ($receivedCents < $totalCents) {
                throw new RuntimeException('Uang diterima kurang dari total belanja.');
            }

            $selectedCustomerId = null;
            if ($customerId !== '') {
                $customerStmt = $pdo->prepare(
                    'SELECT id_pelanggan FROM tbl_pelanggan
                     WHERE id_pelanggan = :id_pelanggan LIMIT 1 FOR UPDATE'
                );
                $customerStmt->execute(['id_pelanggan' => (int) $customerId]);
                $foundCustomerId = $customerStmt->fetchColumn();
                if ($foundCustomerId === false) {
                    throw new RuntimeException('Pelanggan yang dipilih tidak ditemukan.');
                }
                $selectedCustomerId = (int) $foundCustomerId;
            } elseif ($guestName !== '') {
                $createCustomer = $pdo->prepare(
                    'INSERT INTO tbl_pelanggan (nama_pelanggan) VALUES (:nama_pelanggan)'
                );
                $createCustomer->execute(['nama_pelanggan' => $guestName]);
                $selectedCustomerId = (int) $pdo->lastInsertId();
            }

            $changeCents = $receivedCents - $totalCents;
            $paymentStatus = ($paymentMethod === 'QRIS') ? 'paid' : 'paid';
            $orderRef = ($paymentMethod === 'QRIS' && $midtransOrderId !== '') ? $midtransOrderId : null;

            $insertCols = [
                'tanggal',
                'id_pelanggan',
                'total_belanja',
                'metode_pembayaran',
                'uang_diterima',
                'kembalian',
                'id_kasir',
            ];
            $insertVals = [
                'CURDATE()',
                ':id_pelanggan',
                ':total_belanja',
                ':metode_pembayaran',
                ':uang_diterima',
                ':kembalian',
                ':id_kasir',
            ];
            $insertParams = [
                'id_pelanggan'      => $selectedCustomerId,
                'total_belanja'     => cashierFormatCents($totalCents),
                'metode_pembayaran' => $paymentMethod,
                'uang_diterima'     => cashierFormatCents($receivedCents),
                'kembalian'         => cashierFormatCents($changeCents),
                'id_kasir'          => (int) $_SESSION['user_id'],
            ];

            if ($hasMidtransOrderCol) {
                $insertCols[] = 'midtrans_order_id';
                $insertVals[] = ':midtrans_order_id';
                $insertParams['midtrans_order_id'] = $orderRef;
            }
            if ($hasPaymentStatusCol) {
                $insertCols[] = 'status_pembayaran';
                $insertVals[] = ':status_pembayaran';
                $insertParams['status_pembayaran'] = $paymentStatus;
            }

            $transactionStmt = $pdo->prepare(
                'INSERT INTO tbl_transaksi (' . implode(', ', $insertCols) . ')
                 VALUES (' . implode(', ', $insertVals) . ')'
            );
            $transactionStmt->execute($insertParams);
            $transactionId = (int) $pdo->lastInsertId();

            $detailStmt = $pdo->prepare(
                'INSERT INTO tbl_detail_transaksi
                 (id_transaksi, nama_produk, jumlah, harga, id_produk)
                 VALUES (:id_transaksi, :nama_produk, :jumlah, :harga, :id_produk)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE tbl_produk SET stok = stok - :quantity
                 WHERE id_produk = :id_produk AND stok >= :minimum_stock'
            );

            foreach ($products as $product) {
                $id = (int) $product['id_produk'];
                $quantity = $quantities[$id];
                $stockStmt->execute([
                    'quantity'      => $quantity,
                    'id_produk'     => $id,
                    'minimum_stock' => $quantity,
                ]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException('Stok ' . $product['nama_produk'] . ' berubah. Coba kembali.');
                }
                $detailStmt->execute([
                    'id_transaksi' => $transactionId,
                    'nama_produk'  => $product['nama_produk'],
                    'jumlah'       => $quantity,
                    'harga'        => $product['harga'],
                    'id_produk'    => $id,
                ]);
            }

            $activityNote = 'Mencatat transaksi TRX-' . str_pad((string) $transactionId, 3, '0', STR_PAD_LEFT) . ' (' . $paymentMethod . ')';
            if ($orderRef !== null) {
                $activityNote .= ' [' . $orderRef . ']';
            }

            recordAdminActivity(
                $pdo,
                'Transaksi kasir',
                $activityNote,
                $transactionId
            );
            $pdo->commit();

            cashierRedirect('kasir/receipt.php?id=' . $transactionId);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Database transaksi kasir: ' . $e->getMessage());
            $errors[] = 'Transaksi tidak dapat disimpan karena terjadi gangguan database.';
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Transaksi kasir: ' . $e->getMessage());
            $errors[] = 'Transaksi tidak dapat diproses. Periksa log aplikasi untuk detailnya.';
        }
    }
}

$hasBarcodeColumn = false;
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM tbl_produk LIKE 'barcode'");
    $hasBarcodeColumn = (bool) $colCheck->fetch();
} catch (Throwable $e) {
    $hasBarcodeColumn = false;
}

$productSelect = $hasBarcodeColumn
    ? 'SELECT id_produk, nama_produk, harga, stok, gambar, barcode FROM tbl_produk ORDER BY nama_produk'
    : 'SELECT id_produk, nama_produk, harga, stok, gambar FROM tbl_produk ORDER BY nama_produk';

$products = $pdo->query($productSelect)->fetchAll();

foreach ($products as &$prod) {
    $pid = (int) $prod['id_produk'];
    if (empty($prod['barcode'])) {
        $prod['barcode'] = 'TK' . str_pad((string) $pid, 8, '0', STR_PAD_LEFT);
    }
}
unset($prod);

$customers = $pdo->query(
    'SELECT id_pelanggan, nama_pelanggan
     FROM tbl_pelanggan
     ORDER BY nama_pelanggan, id_pelanggan'
)->fetchAll();

require_once __DIR__ . '/../includes/product_images.php';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-shell-layout">
    <?php
    $kasirActive = 'checkout';
    require_once __DIR__ . '/../includes/kasir_sidebar.php';
    ?>

    <main class="dashboard-shell-main">
        <header class="dashboard-command-topbar">
            <div class="topbar-left-col">
                <div class="topbar-breadcrumbs">
                    <span>Tokoku Kasir</span>
                    <span class="bc-divider">/</span>
                    <span class="bc-active">Terminal POS</span>
                </div>
                <h1 class="topbar-main-title">Transaksi Baru</h1>
            </div>

            <div class="topbar-right-col">
                <div class="topbar-live-time">
                    <span class="live-dot-pulse"></span>
                    <span><?= date('l, d F Y') ?></span>
                </div>
                <div class="topbar-quick-actions">
                    <a href="<?= e(appUrl('kasir/dashboard.php')) ?>" class="btn-topbar-action" style="background:#fff;color:#0F172A;border:1px solid #E2E8F0;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                            <path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        <span>Kembali</span>
                    </a>
                </div>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="user-alert user-alert-success" role="status" style="margin-bottom:16px;"><?= e($success) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="user-alert user-alert-error" role="alert" style="margin-bottom:12px;"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" class="cashier-checkout-form">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="midtrans_order_id" id="midtrans_order_id" value="<?= e($midtransOrderId) ?>">

            <div class="dashboard-command-center-grid" style="grid-template-columns: 1.4fr 1fr; gap: 20px;">
                <!-- LEFT: PRODUCT SELECTION -->
                <div class="cmd-col-main">
                    <section class="cmd-panel-card">
                        <div class="cmd-panel-header">
                            <div>
                                <h3>Pilih Produk</h3>
                                <p>Scan barcode atau masukkan jumlah. Stok diperbarui otomatis setelah transaksi.</p>
                            </div>
                            <span class="panel-count" style="font-size:13px;color:#64748B;font-weight:600;"><?= number_format(count($products)) ?> produk</span>
                        </div>

                        <div class="barcode-scan-bar" style="padding:0 16px 14px;">
                            <label for="barcode-scanner-input" style="display:block;font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">Scan / ketik barcode</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input
                                    id="barcode-scanner-input"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="Arahkan scanner ke barcode lalu Enter..."
                                    style="flex:1;padding:12px 14px;border:2px solid #059669;border-radius:10px;font-size:15px;font-weight:600;letter-spacing:0.5px;"
                                >
                                <span id="barcode-scan-feedback" style="font-size:12px;color:#64748B;min-width:120px;"></span>
                            </div>
                        </div>

                        <div class="cmd-table-responsive">
                            <table class="cmd-table cashier-product-table">
                                <thead>
                                    <tr>
                                        <th style="width:56px;">Gambar</th>
                                        <th>Produk</th>
                                        <th>Harga</th>
                                        <th>Stok</th>
                                        <th style="width:100px;">Jumlah</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($products as $product):
                                    $productId = (int) $product['id_produk'];
                                    $stok = (int) $product['stok'];
                                    $barcode = (string) ($product['barcode'] ?? ('TK' . str_pad((string) $productId, 8, '0', STR_PAD_LEFT)));
                                ?>
                                    <tr data-product-row data-barcode="<?= e($barcode) ?>" data-product-id="<?= $productId ?>">
                                        <td>
                                            <img
                                                src="<?= e(productImageUrl($product)) ?>"
                                                alt="<?= e($product['nama_produk']) ?>"
                                                class="cashier-product-thumb"
                                                width="48"
                                                height="48"
                                                loading="lazy"
                                                style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #E2E8F0;background:#F8FAFC;"
                                            >
                                        </td>
                                        <td>
                                            <strong style="display:block;margin-bottom:4px;"><?= e($product['nama_produk']) ?></strong>
                                            <div class="cashier-barcode-wrap" style="display:flex;flex-direction:column;gap:2px;align-items:flex-start;">
                                                <svg class="product-barcode-svg" data-barcode-value="<?= e($barcode) ?>" style="max-width:120px;height:36px;"></svg>
                                                <span style="font-size:10px;color:#94A3B8;font-family:ui-monospace,monospace;letter-spacing:0.4px;"><?= e($barcode) ?></span>
                                            </div>
                                        </td>
                                        <td>Rp <?= e(number_format((float) $product['harga'], 0, ',', '.')) ?></td>
                                        <td>
                                            <?php if ($stok <= 5): ?>
                                                <span class="stock-status-tag tag-danger"><?= number_format($stok) ?></span>
                                            <?php else: ?>
                                                <?= number_format($stok) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input
                                                class="cashier-quantity-input"
                                                type="number"
                                                name="quantities[<?= $productId ?>]"
                                                min="0"
                                                max="<?= min(999, $stok) ?>"
                                                value="<?= (int) ($quantities[$productId] ?? 0) ?>"
                                                data-price-cents="<?= (int) cashierMoneyToCents((string) $product['harga']) ?>"
                                                data-subtotal-target="cashier-subtotal-<?= $productId ?>"
                                                data-barcode="<?= e($barcode) ?>"
                                                aria-label="Jumlah <?= e($product['nama_produk']) ?>"
                                                <?= $stok < 1 ? 'disabled' : '' ?>
                                                style="width:80px;padding:8px 10px;border:1px solid #E2E8F0;border-radius:8px;text-align:center;font-weight:600;"
                                            >
                                        </td>
                                        <td class="cashier-line-subtotal" id="cashier-subtotal-<?= $productId ?>" data-cents="0">
                                            <strong class="text-emerald">Rp 0</strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$products): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Belum ada produk di katalog.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <!-- RIGHT: PAYMENT PANEL -->
                <div class="cmd-col-side">
                    <section class="cmd-panel-card" style="position:sticky;top:20px;">
                        <div class="cmd-panel-header">
                            <div>
                                <h3>Pelanggan &amp; Pembayaran</h3>
                                <p>Pelanggan boleh dikosongkan untuk transaksi umum.</p>
                            </div>
                        </div>

                        <div class="user-form cashier-payment-form" style="padding:0 4px;">
                            <dl class="cashier-live-summary" data-cashier-summary aria-live="polite" style="display:grid;gap:10px;margin-bottom:20px;padding:16px;background:#F8FAFC;border-radius:12px;border:1px solid #E2E8F0;">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <dt style="color:#64748B;font-size:13px;">Jumlah item</dt>
                                    <dd data-cashier-items style="font-weight:700;margin:0;">0</dd>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <dt style="color:#64748B;font-size:13px;">Total belanja</dt>
                                    <dd data-cashier-total style="font-weight:800;font-size:18px;color:#059669;margin:0;">Rp 0</dd>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <dt style="color:#64748B;font-size:13px;">Uang diterima</dt>
                                    <dd data-cashier-received style="font-weight:600;margin:0;">Rp 0</dd>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <dt style="color:#64748B;font-size:13px;">Kembalian</dt>
                                    <dd data-cashier-change style="font-weight:700;margin:0;">Rp 0</dd>
                                </div>
                                <p class="cashier-live-message" data-cashier-message style="margin:8px 0 0;font-size:12px;color:#94A3B8;">Pilih produk untuk menghitung total.</p>
                            </dl>

                            <div class="user-field" style="margin-bottom:14px;">
                                <label for="id_pelanggan" style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Pelanggan terdaftar (opsional)</label>
                                <select id="id_pelanggan" name="id_pelanggan" style="width:100%;padding:10px 12px;border:1px solid #E2E8F0;border-radius:10px;font-size:14px;background:#fff;">
                                    <option value="">Pelanggan umum / tanpa nama</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= (int) $customer['id_pelanggan'] ?>" <?= $customerId === (string) $customer['id_pelanggan'] ? 'selected' : '' ?>>
                                            <?= e($customer['nama_pelanggan'] ?: 'Pelanggan') ?> · #<?= (int) $customer['id_pelanggan'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="user-field" style="margin-bottom:14px;">
                                <label for="nama_pelanggan" style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Atau nama pelanggan umum</label>
                                <input id="nama_pelanggan" name="nama_pelanggan" type="text" maxlength="100" value="<?= e($guestName) ?>" placeholder="Opsional" style="width:100%;padding:10px 12px;border:1px solid #E2E8F0;border-radius:10px;font-size:14px;">
                            </div>

                            <div class="user-field" style="margin-bottom:16px;">
                                <span style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:8px;">Metode pembayaran</span>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                    <label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #E2E8F0;border-radius:10px;cursor:pointer;background:#fff;" data-pay-method-label="Tunai">
                                        <input type="radio" name="metode_pembayaran" value="Tunai" checked style="width:16px;height:16px;accent-color:#059669;">
                                        <span style="font-weight:600;font-size:14px;color:#0F172A;">Tunai</span>
                                    </label>
                                    <label style="display:flex;align-items:center;gap:8px;padding:12px;border:2px solid #E2E8F0;border-radius:10px;cursor:pointer;background:#fff;" data-pay-method-label="QRIS">
                                        <input type="radio" name="metode_pembayaran" value="QRIS" style="width:16px;height:16px;accent-color:#059669;">
                                        <span style="font-weight:600;font-size:14px;color:#0F172A;">QRIS (Midtrans)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="user-field" data-cash-fields style="margin-bottom:20px;">
                                <label for="uang_diterima" style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Uang diterima (Rp)</label>
                                <input id="uang_diterima" name="uang_diterima" type="number" min="0" step="0.01" value="<?= e($amountReceived) ?>" placeholder="Contoh: 100000" style="width:100%;padding:12px;border:2px solid #059669;border-radius:10px;font-size:16px;font-weight:700;">
                            </div>

                            <div data-qris-panel style="display:none;margin-bottom:20px;padding:16px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;text-align:center;">
                                <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#065F46;">Pembayaran QRIS via Midtrans Sandbox</p>
                                <p style="margin:0 0 10px;font-size:12px;color:#047857;" data-qris-amount>Total: Rp 0</p>
                                <p style="margin:0;font-size:11px;color:#475569;line-height:1.45;">Klik tombol di bawah untuk membuka popup Midtrans Snap &amp; memindai kode QRIS.</p>
                            </div>

                            <button class="user-btn user-btn-primary cashier-submit-button" type="submit" style="width:100%;padding:14px;background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;border-radius:12px;font-weight:700;font-size:15px;cursor:pointer;box-shadow:0 4px 14px rgba(5,150,105,0.35);">
                                Proses Transaksi &amp; Cetak Nota
                            </button>
                        </div>
                    </section>

                    <div style="margin-top:16px;padding:16px;background:#ECFDF5;border:1px solid #A7F3D0;border-radius:12px;">
                        <div style="display:flex;gap:10px;align-items:flex-start;">
                            <span style="font-size:18px;line-height:1;">✓</span>
                            <div>
                                <strong style="display:block;color:#065F46;font-size:13px;margin-bottom:4px;">Stok dan pembayaran aman</strong>
                                <p style="margin:0;font-size:12px;color:#047857;line-height:1.45;">Harga dan stok diambil ulang dari database. Transaksi, pengurangan stok, dan kembalian disimpan bersama atau dibatalkan seluruhnya.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <footer class="dashboard-shell-footer">
            <span>© <?= date('Y') ?> <strong>Tokoku POS</strong> · Terminal Kasir Resmi.</span>
            <span>Mode Cetak Roll 50mm Aktif</span>
        </footer>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="<?= e($midtransConfig['snap_js_url']) ?>" data-client-key="<?= e($midtransConfig['client_key']) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof JsBarcode === 'function') {
        document.querySelectorAll('.product-barcode-svg').forEach(function (svg) {
            var value = svg.getAttribute('data-barcode-value');
            if (!value) return;
            try {
                JsBarcode(svg, value, {
                    format: 'CODE128',
                    width: 1.4,
                    height: 32,
                    displayValue: false,
                    margin: 0,
                    background: 'transparent'
                });
            } catch (err) {
                svg.style.display = 'none';
            }
        });
    }

    var scanInput = document.getElementById('barcode-scanner-input');
    var feedback = document.getElementById('barcode-scan-feedback');
    if (scanInput) {
        scanInput.focus();
        scanInput.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var code = (scanInput.value || '').trim();
            scanInput.value = '';
            if (!code) return;

            var row = document.querySelector('tr[data-barcode="' + CSS.escape(code) + '"]');
            if (!row) {
                row = Array.prototype.find.call(
                    document.querySelectorAll('tr[data-barcode]'),
                    function (r) { return (r.getAttribute('data-barcode') || '').toUpperCase() === code.toUpperCase(); }
                );
            }
            if (!row) {
                if (feedback) {
                    feedback.textContent = 'Barcode tidak ditemukan';
                    feedback.style.color = '#E11D48';
                }
                return;
            }
            var qtyInput = row.querySelector('.cashier-quantity-input');
            if (!qtyInput || qtyInput.disabled) {
                if (feedback) {
                    feedback.textContent = 'Stok habis';
                    feedback.style.color = '#E11D48';
                }
                return;
            }
            var max = parseInt(qtyInput.getAttribute('max') || '999', 10);
            var current = parseInt(qtyInput.value || '0', 10) || 0;
            if (current >= max) {
                if (feedback) {
                    feedback.textContent = 'Mencapai batas stok';
                    feedback.style.color = '#E11D48';
                }
                return;
            }
            qtyInput.value = String(current + 1);
            qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.background = '#ECFDF5';
            setTimeout(function () { row.style.background = ''; }, 600);
            if (feedback) {
                feedback.textContent = '+1 ditambahkan';
                feedback.style.color = '#059669';
            }
        });
    }

    var cashFields = document.querySelector('[data-cash-fields]');
    var qrisPanel = document.querySelector('[data-qris-panel]');
    var qrisAmountEl = document.querySelector('[data-qris-amount]');
    var cashInput = document.getElementById('uang_diterima');
    var itemsEl = document.querySelector('[data-cashier-items]');
    var totalEl = document.querySelector('[data-cashier-total]');
    var receivedEl = document.querySelector('[data-cashier-received]');
    var changeEl = document.querySelector('[data-cashier-change]');
    var msgEl = document.querySelector('[data-cashier-message]');
    var midtransOrderInput = document.getElementById('midtrans_order_id');
    var checkoutForm = document.querySelector('.cashier-checkout-form');

    function formatRupiahFromCents(cents) {
        var rupiah = Math.round((cents || 0) / 100);
        return 'Rp ' + rupiah.toLocaleString('id-ID');
    }

    function getSelectedPaymentMethod() {
        var checked = document.querySelector('input[name="metode_pembayaran"]:checked');
        return checked ? checked.value : 'Tunai';
    }

    function updateCheckoutSummary() {
        var totalItems = 0;
        var totalCents = 0;

        document.querySelectorAll('.cashier-quantity-input').forEach(function (input) {
            var qty = Math.max(0, parseInt(input.value || '0', 10) || 0);
            var priceCents = parseInt(input.getAttribute('data-price-cents') || '0', 10) || 0;
            var lineCents = qty * priceCents;
            var targetId = input.getAttribute('data-subtotal-target');
            var subtotalCell = targetId ? document.getElementById(targetId) : null;

            if (subtotalCell) {
                subtotalCell.setAttribute('data-cents', String(lineCents));
                subtotalCell.innerHTML = '<strong class="text-emerald">' + formatRupiahFromCents(lineCents) + '</strong>';
            }

            if (qty > 0) {
                totalItems += qty;
                totalCents += lineCents;
            }
        });

        var method = getSelectedPaymentMethod();
        var isQris = method === 'QRIS';

        if (cashFields) cashFields.style.display = isQris ? 'none' : '';
        if (qrisPanel) qrisPanel.style.display = isQris ? '' : 'none';
        if (cashInput) {
            cashInput.disabled = isQris;
            if (!isQris && (cashInput.value === '' || parseFloat(cashInput.value) === 0) && totalCents > 0) {
                cashInput.value = String(Math.round(totalCents / 100));
            }
        }

        document.querySelectorAll('[data-pay-method-label]').forEach(function (lbl) {
            var active = lbl.getAttribute('data-pay-method-label') === method;
            lbl.style.borderColor = active ? '#059669' : '#E2E8F0';
            lbl.style.background = active ? '#ECFDF5' : '#fff';
        });

        var receivedCents = 0;
        var changeCents = 0;

        if (isQris) {
            receivedCents = totalCents;
            changeCents = 0;
            if (qrisAmountEl) qrisAmountEl.textContent = 'Total: ' + formatRupiahFromCents(totalCents);
        } else {
            var rawCash = cashInput ? parseFloat(cashInput.value || '0') : 0;
            receivedCents = isNaN(rawCash) ? 0 : Math.round(rawCash * 100);
            changeCents = Math.max(0, receivedCents - totalCents);
        }

        if (itemsEl) itemsEl.textContent = String(totalItems);
        if (totalEl) totalEl.textContent = formatRupiahFromCents(totalCents);
        if (receivedEl) receivedEl.textContent = formatRupiahFromCents(receivedCents);
        if (changeEl) changeEl.textContent = formatRupiahFromCents(changeCents);

        if (msgEl) {
            if (totalCents <= 0) {
                msgEl.textContent = 'Pilih produk untuk menghitung total.';
            } else if (isQris) {
                msgEl.textContent = 'Pembayaran QRIS diproses melalui Midtrans Snap.';
            } else if (receivedCents < totalCents) {
                msgEl.textContent = 'Uang diterima masih kurang dari total belanja.';
            } else {
                msgEl.textContent = 'Pembayaran tunai siap diproses.';
            }
        }
    }

    document.querySelectorAll('.cashier-quantity-input').forEach(function (input) {
        input.addEventListener('input', function () {
            if (midtransOrderInput) midtransOrderInput.value = '';
            updateCheckoutSummary();
        });
        input.addEventListener('change', function () {
            if (midtransOrderInput) midtransOrderInput.value = '';
            updateCheckoutSummary();
        });
    });

    if (cashInput) {
        cashInput.addEventListener('input', updateCheckoutSummary);
    }

    document.querySelectorAll('input[name="metode_pembayaran"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (midtransOrderInput) midtransOrderInput.value = '';
            updateCheckoutSummary();
        });
    });

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', async function (e) {
            var method = getSelectedPaymentMethod();
            if (method !== 'QRIS') return;
            if (midtransOrderInput && midtransOrderInput.value !== '') return;

            e.preventDefault();

            var quantities = {};
            document.querySelectorAll('.cashier-quantity-input').forEach(function (inp) {
                var m = (inp.name || '').match(/\[(\d+)\]/);
                var val = parseInt(inp.value || '0', 10);
                if (m && val > 0) quantities[m[1]] = val;
            });

            if (Object.keys(quantities).length === 0) {
                alert('Pilih minimal satu produk terlebih dahulu.');
                return;
            }

            var csrfToken = (checkoutForm.querySelector('input[name="csrf_token"]') || {}).value || '';
            var guestName = (document.getElementById('nama_pelanggan') || {}).value || '';

            try {
                var res = await fetch('<?= e(appUrl('kasir/midtrans_snap.php')) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        quantities: quantities,
                        nama_pelanggan: guestName
                    })
                });
                var data = await res.json();

                if (!res.ok || !data.snap_token) {
                    alert(data.error || 'Gagal membuat transaksi Midtrans.');
                    return;
                }

                if (typeof window.snap === 'undefined' || typeof window.snap.pay !== 'function') {
                    alert('Script Midtrans Snap belum termuat. Periksa koneksi internet atau Client Key.');
                    return;
                }

                window.snap.pay(data.snap_token, {
                    onSuccess: function () {
                        if (midtransOrderInput) midtransOrderInput.value = data.order_id || '';
                        checkoutForm.submit();
                    },
                    onPending: function () {
                        alert('Pembayaran masih pending. Selesaikan pembayaran di simulator Midtrans Sandbox.');
                    },
                    onError: function () {
                        alert('Pembayaran Midtrans gagal.');
                    },
                    onClose: function () {
                        // Kasir menutup popup sebelum pembayaran selesai
                    }
                });
            } catch (err) {
                alert('Terjadi kesalahan saat menghubungi server Midtrans.');
            }
        });
    }

    updateCheckoutSummary();
});
</script>