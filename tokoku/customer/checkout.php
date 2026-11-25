<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('pelanggan');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';
require_once __DIR__ . '/../includes/store.php';

$midtransConfig = require __DIR__ . '/../config/midtrans.php';

$pageTitle = 'Checkout Pesanan - Tokoku';
$cart = customerCart();
if (!$cart) {
    $_SESSION['cart_error'] = 'Keranjang Anda masih kosong. Silakan pilih produk terlebih dahulu.';
    header('Location: ' . appUrl('customer/cart.php'));
    exit;
}

$errors = [];
$city = trim((string) ($_POST['kota'] ?? ''));
if ($city === '') {
    $customerStmt = $pdo->prepare('SELECT kota FROM tbl_pelanggan WHERE id_user = :id_user LIMIT 1');
    $customerStmt->execute(['id_user' => (int) $_SESSION['user_id']]);
    $city = (string) ($customerStmt->fetchColumn() ?: '');
}

$paymentMethod = 'QRIS';
$midtransOrderId = trim((string) ($_POST['midtrans_order_id'] ?? ''));

$txCols = [];
try {
    foreach ($pdo->query('SHOW COLUMNS FROM tbl_transaksi')->fetchAll() as $col) {
        $txCols[$col['Field']] = true;
    }
} catch (Throwable $e) {
    $txCols = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!validCustomerCsrf()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.';
    }
    if ($city === '' || mb_strlen($city) > 50) {
        $errors[] = 'Kota wajib diisi dan maksimal 50 karakter.';
    }
    if ($midtransOrderId === '') {
        $errors[] = 'Selesaikan pembayaran QRIS melalui Midtrans terlebih dahulu.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $products = customerCartProducts($pdo, $cart, true);
            if (count($products) !== count($cart)) {
                throw new RuntimeException('Salah satu produk tidak lagi tersedia.');
            }

            $total = 0.0;
            foreach ($products as $product) {
                $quantity = (int) ($cart[(int) $product['id_produk']] ?? 0);
                if ($quantity < 1 || $quantity > (int) $product['stok']) {
                    throw new RuntimeException(
                        'Stok ' . $product['nama_produk'] . ' tidak mencukupi. Perbarui keranjang Anda.'
                    );
                }
                $total += (float) $product['harga'] * $quantity;
            }
            if (!is_finite($total) || $total > 9999999999.99) {
                throw new RuntimeException('Total belanja melebihi batas transaksi.');
            }

            $userId = (int) $_SESSION['user_id'];
            $customerStmt = $pdo->prepare(
                'SELECT id_pelanggan FROM tbl_pelanggan WHERE id_user = :id_user LIMIT 1 FOR UPDATE'
            );
            $customerStmt->execute(['id_user' => $userId]);
            $customerId = $customerStmt->fetchColumn();
            if ($customerId === false) {
                $createCustomer = $pdo->prepare(
                    'INSERT INTO tbl_pelanggan (nama_pelanggan, kota, id_user)
                     VALUES (:nama_pelanggan, :kota, :id_user)'
                );
                $createCustomer->execute([
                    'nama_pelanggan' => mb_substr((string) $_SESSION['name'], 0, 100),
                    'kota' => $city,
                    'id_user' => $userId,
                ]);
                $customerId = (int) $pdo->lastInsertId();
            } else {
                $customerId = (int) $customerId;
                $updateCustomer = $pdo->prepare(
                    'UPDATE tbl_pelanggan SET nama_pelanggan = :nama_pelanggan, kota = :kota
                     WHERE id_pelanggan = :id_pelanggan'
                );
                $updateCustomer->execute([
                    'nama_pelanggan' => mb_substr((string) $_SESSION['name'], 0, 100),
                    'kota' => $city,
                    'id_pelanggan' => $customerId,
                ]);
            }

            $formattedTotal = number_format($total, 2, '.', '');
            $insertCols = ['tanggal', 'id_pelanggan', 'total_belanja'];
            $insertVals = ['CURDATE()', ':id_pelanggan', ':total_belanja'];
            $insertParams = [
                'id_pelanggan'  => $customerId,
                'total_belanja' => $formattedTotal,
            ];

            if (isset($txCols['metode_pembayaran'])) {
                $insertCols[] = 'metode_pembayaran';
                $insertVals[] = ':metode_pembayaran';
                $insertParams['metode_pembayaran'] = 'QRIS';
            }
            if (isset($txCols['uang_diterima'])) {
                $insertCols[] = 'uang_diterima';
                $insertVals[] = ':uang_diterima';
                $insertParams['uang_diterima'] = $formattedTotal;
            }
            if (isset($txCols['kembalian'])) {
                $insertCols[] = 'kembalian';
                $insertVals[] = ':kembalian';
                $insertParams['kembalian'] = '0.00';
            }
            if (isset($txCols['midtrans_order_id'])) {
                $insertCols[] = 'midtrans_order_id';
                $insertVals[] = ':midtrans_order_id';
                $insertParams['midtrans_order_id'] = $midtransOrderId;
            }
            if (isset($txCols['status_pembayaran'])) {
                $insertCols[] = 'status_pembayaran';
                $insertVals[] = ':status_pembayaran';
                $insertParams['status_pembayaran'] = 'paid';
            }

            $createOrder = $pdo->prepare(
                'INSERT INTO tbl_transaksi (' . implode(', ', $insertCols) . ')
                 VALUES (' . implode(', ', $insertVals) . ')'
            );
            $createOrder->execute($insertParams);
            $orderId = (int) $pdo->lastInsertId();

            $insertDetail = $pdo->prepare(
                'INSERT INTO tbl_detail_transaksi
                 (id_transaksi, nama_produk, jumlah, harga, id_produk)
                 VALUES (:id_transaksi, :nama_produk, :jumlah, :harga, :id_produk)'
            );
            $decreaseStock = $pdo->prepare(
                'UPDATE tbl_produk SET stok = stok - :quantity
                 WHERE id_produk = :id_produk AND stok >= :minimum_stock'
            );
            foreach ($products as $product) {
                $productId = (int) $product['id_produk'];
                $quantity = (int) $cart[$productId];
                $decreaseStock->execute([
                    'quantity' => $quantity,
                    'id_produk' => $productId,
                    'minimum_stock' => $quantity,
                ]);
                if ($decreaseStock->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Stok ' . $product['nama_produk'] . ' berubah. Silakan coba checkout kembali.'
                    );
                }
                $insertDetail->execute([
                    'id_transaksi' => $orderId,
                    'nama_produk' => $product['nama_produk'],
                    'jumlah' => $quantity,
                    'harga' => $product['harga'],
                    'id_produk' => $productId,
                ]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            $_SESSION['order_flash'] = 'Pembayaran QRIS berhasil! Nomor pesanan: TRX-'
                . str_pad((string) $orderId, 3, '0', STR_PAD_LEFT) . '.';
            header('Location: ' . appUrl('customer/order.php?id=' . $orderId));
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout pelanggan: ' . $e->getMessage());
            $errors[] = 'Pesanan tidak dapat diproses saat ini. Silakan coba kembali.';
        }
    }
}

$products = customerCartProducts($pdo, $cart);
$total = array_sum(array_column($products, 'subtotal'));
$totalItems = array_sum($cart);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="store-page-checkout">
    <?php customerStoreHeader('cart'); ?>

    <div class="checkout-page-container">
        <!-- STEPPER BAR -->
        <div class="cart-stepper-bar">
            <div class="stepper-step completed">
                <span class="step-num">✓</span>
                <span class="step-text">Keranjang Belanja</span>
            </div>
            <div class="stepper-line completed"></div>
            <div class="stepper-step active">
                <span class="step-num">2</span>
                <span class="step-text">Informasi &amp; Pembayaran QRIS</span>
            </div>
            <div class="stepper-line"></div>
            <div class="stepper-step">
                <span class="step-num">3</span>
                <span class="step-text">Pesanan Selesai</span>
            </div>
        </div>

        <!-- ERROR ALERTS -->
        <?php foreach ($errors as $error): ?>
            <div class="user-alert user-alert-error" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endforeach; ?>

        <div class="checkout-layout-grid">
            <!-- LEFT COLUMN: FORM PENGIRIMAN & PEMBAYARAN -->
            <div class="checkout-form-column">
                <form method="post" class="checkout-main-form" id="checkoutForm">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="metode_pembayaran" value="QRIS">
                    <input type="hidden" name="midtrans_order_id" id="midtrans_order_id" value="<?= e($midtransOrderId) ?>">

                    <!-- 1. INFORMASI PELANGGAN -->
                    <div class="checkout-card-section">
                        <div class="section-badge-header">
                            <span class="section-badge-num">1</span>
                            <h3>Informasi Pelanggan &amp; Pengiriman</h3>
                        </div>

                        <div class="form-fields-group">
                            <div class="form-field-row">
                                <label>Nama Lengkap Penerima</label>
                                <input type="text" value="<?= e($_SESSION['name'] ?? 'Pelanggan') ?>" readonly class="input-readonly">
                                <span class="field-hint">Terhubung langsung dengan akun Tokoku Anda.</span>
                            </div>

                            <div class="form-field-row">
                                <label for="checkoutCityInput">Kota Domisili / Tujuan <span class="req-star">*</span></label>
                                <input type="text" id="checkoutCityInput" name="kota" value="<?= e($city) ?>" placeholder="Contoh: Surabaya, Jakarta, Ponorogo, Madiun" required maxlength="50">
                                <span class="field-hint">Kota wajib dicantumkan untuk pendataan pesanan.</span>
                            </div>

                            <div class="form-field-row">
                                <label for="checkoutNotes">Catatan Tambahan untuk Toko (Opsional)</label>
                                <textarea id="checkoutNotes" rows="2" placeholder="Catatan khusus, misalnya waktu pengambilan atau instruksi kemasan..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 2. METODE PEMBAYARAN (KHUSUS QRIS MIDTRANS) -->
                    <div class="checkout-card-section">
                        <div class="section-badge-header">
                            <span class="section-badge-num">2</span>
                            <h3>Metode Pembayaran</h3>
                        </div>

                        <div class="payment-methods-grid">
                            <div class="payment-method-card is-selected" style="cursor:default;">
                                <div class="payment-card-body">
                                    <div class="payment-icon">📱</div>
                                    <div class="payment-info">
                                        <strong>QRIS Instan via Midtrans (Sandbox)</strong>
                                        <span>Scan QRIS menggunakan GoPay, OVO, DANA, ShopeePay, atau Mobile Banking. Pembayaran tunai tidak tersedia di katalog.</span>
                                    </div>
                                    <span class="payment-select-dot"></span>
                                </div>
                            </div>
                        </div>

                        <div class="checkout-notice-box">
                            <span class="notice-icon">ℹ️</span>
                            <p>
                                Klik tombol <strong>Bayar dengan QRIS Sekarang</strong> untuk memunculkan kode QRIS Midtrans. Setelah pembayaran berhasil, pesanan otomatis tercatat dan mengurangi stok barang.
                            </p>
                        </div>
                    </div>

                    <div class="checkout-submit-bar">
                        <button type="submit" class="btn-confirm-order" id="btnPayQris">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span>Bayar dengan QRIS Sekarang</span>
                        </button>
                        <a href="<?= e(appUrl('customer/cart.php')) ?>" class="btn-back-to-cart">
                            ← Kembali ke Keranjang
                        </a>
                    </div>
                </form>
            </div>

            <!-- RIGHT COLUMN: RINGKASAN PRODUK PESANAN -->
            <div class="checkout-summary-column">
                <div class="checkout-review-card">
                    <div class="review-card-header">
                        <h3>Ringkasan Pesanan</h3>
                        <span class="review-items-badge"><?= number_format($totalItems) ?> Unit</span>
                    </div>

                    <div class="review-items-list">
                        <?php foreach ($products as $product): ?>
                            <div class="review-item-row">
                                <div class="review-item-img">
                                    <img src="<?= e(productImageUrl($product)) ?>" alt="<?= e($product['nama_produk']) ?>">
                                </div>
                                <div class="review-item-info">
                                    <strong><?= e($product['nama_produk']) ?></strong>
                                    <span><?= number_format((int) $product['jumlah_keranjang']) ?> unit × Rp <?= number_format((float) $product['harga'], 0, ',', '.') ?></span>
                                </div>
                                <div class="review-item-subtotal">
                                    Rp <?= number_format((float) $product['subtotal'], 0, ',', '.') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="review-divider"></div>

                    <div class="review-cost-breakdown">
                        <div class="cost-row">
                            <span>Subtotal Belanja</span>
                            <strong>Rp <?= number_format($total, 0, ',', '.') ?></strong>
                        </div>
                        <div class="cost-row">
                            <span>Metode Pembayaran</span>
                            <strong>QRIS (Midtrans)</strong>
                        </div>
                        <div class="cost-row">
                            <span>Biaya Pengemasan</span>
                            <span class="text-success">Gratis</span>
                        </div>
                        <div class="cost-row">
                            <span>Biaya Layanan</span>
                            <span class="text-success">Rp 0 (Bebas Biaya)</span>
                        </div>
                    </div>

                    <div class="review-divider"></div>

                    <div class="review-total-box">
                        <span>Total yang Harus Dibayar</span>
                        <strong class="total-highlight">Rp <?= number_format($total, 0, ',', '.') ?></strong>
                    </div>

                    <div class="review-safety-notes">
                        <span>🛡️ Transaksi QRIS Terenkripsi via Midtrans</span>
                        <span>📦 Barang Disiapkan Langsung oleh Tim Tokoku</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php customerStoreFooter(); ?>
</main>

<script src="<?= e($midtransConfig['snap_js_url']) ?>" data-client-key="<?= e($midtransConfig['client_key']) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('checkoutForm');
    var orderInput = document.getElementById('midtrans_order_id');
    var cityInput = document.getElementById('checkoutCityInput');
    var payBtn = document.getElementById('btnPayQris');

    if (!form) return;

    form.addEventListener('submit', async function (e) {
        if (orderInput && orderInput.value !== '') {
            return;
        }

        e.preventDefault();

        var cityVal = cityInput ? cityInput.value.trim() : '';
        if (!cityVal) {
            alert('Kota domisili/tujuan wajib diisi.');
            if (cityInput) cityInput.focus();
            return;
        }

        var csrfInput = form.querySelector('input[name="csrf_token"]');
        if (payBtn) payBtn.disabled = true;

        try {
            var res = await fetch('<?= e(appUrl('customer/midtrans_snap.php')) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrfInput ? csrfInput.value : '',
                    kota: cityVal
                })
            });
            var data = await res.json();

            if (!res.ok || !data.snap_token) {
                alert(data.error || 'Gagal membuat transaksi QRIS Midtrans.');
                if (payBtn) payBtn.disabled = false;
                return;
            }

            if (typeof window.snap === 'undefined' || typeof window.snap.pay !== 'function') {
                alert('Script Midtrans Snap belum termuat. Periksa koneksi internet atau Client Key.');
                if (payBtn) payBtn.disabled = false;
                return;
            }

            window.snap.pay(data.snap_token, {
                onSuccess: function () {
                    if (orderInput) orderInput.value = data.order_id || '';
                    form.submit();
                },
                onPending: function () {
                    alert('Pembayaran QRIS masih pending. Selesaikan pembayaran di simulator Midtrans Sandbox.');
                    if (payBtn) payBtn.disabled = false;
                },
                onError: function () {
                    alert('Pembayaran QRIS gagal.');
                    if (payBtn) payBtn.disabled = false;
                },
                onClose: function () {
                    if (payBtn) payBtn.disabled = false;
                }
            });
        } catch (err) {
            alert('Terjadi kesalahan jaringan saat menghubungi Midtrans.');
            if (payBtn) payBtn.disabled = false;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>