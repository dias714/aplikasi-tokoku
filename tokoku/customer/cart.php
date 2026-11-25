<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';
require_once __DIR__ . '/../includes/store.php';

$pageTitle = 'Keranjang Belanja - Tokoku';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!validCustomerCsrf()) {
        $_SESSION['cart_error'] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.';
        header('Location: ' . appUrl('customer/cart.php'));
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $cart = customerCart();
    $productId = filter_var($_POST['id_produk'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'add') {
        $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
        $stmt = $pdo->prepare('SELECT nama_produk, stok FROM tbl_produk WHERE id_produk = :id LIMIT 1');
        $stmt->execute(['id' => $productId && $productId > 0 ? $productId : 0]);
        $product = $stmt->fetch();
        if (!$product) {
            $_SESSION['cart_error'] = 'Produk tidak ditemukan.';
        } elseif (!$quantity || $quantity < 1 || $quantity > 999) {
            $_SESSION['cart_error'] = 'Jumlah produk tidak valid.';
        } elseif ((int) $product['stok'] < 1) {
            $_SESSION['cart_error'] = 'Stok produk sedang habis.';
        } elseif (($cart[$productId] ?? 0) + $quantity > (int) $product['stok']) {
            $_SESSION['cart_error'] = 'Jumlah yang diminta melebihi sisa stok tersedia (' . number_format((int) $product['stok']) . ').';
        } else {
            $cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
            $_SESSION['cart'] = $cart;
            $_SESSION['cart_flash'] = '"' . $product['nama_produk'] . '" berhasil ditambahkan ke keranjang.';
        }
    } elseif ($action === 'update') {
        $quantities = $_POST['quantities'] ?? [];
        if (!is_array($quantities)) {
            $_SESSION['cart_error'] = 'Data jumlah produk tidak valid.';
        } else {
            $products = customerCartProducts($pdo, $cart);
            $productById = [];
            foreach ($products as $product) {
                $productById[(int) $product['id_produk']] = $product;
            }
            foreach ($quantities as $id => $quantityValue) {
                $id = filter_var($id, FILTER_VALIDATE_INT);
                $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);
                if (!$id || !isset($cart[$id])) {
                    continue;
                }
                if ($quantity === 0) {
                    unset($cart[$id]);
                    continue;
                }
                if (
                    !$quantity
                    || $quantity < 1
                    || $quantity > 999
                    || !isset($productById[$id])
                    || $quantity > (int) $productById[$id]['stok']
                ) {
                    $_SESSION['cart_error'] = 'Jumlah produk harus sesuai ketersediaan stok.';
                    break;
                }
                $cart[$id] = $quantity;
            }
            if (!isset($_SESSION['cart_error'])) {
                $_SESSION['cart'] = $cart;
                $_SESSION['cart_flash'] = 'Keranjang belanja berhasil diperbarui.';
            }
        }
    } elseif ($action === 'remove' && $productId) {
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;
        $_SESSION['cart_flash'] = 'Produk telah dihapus dari keranjang.';
    }

    $redirectTo = !empty($_POST['redirect']) ? (string) $_POST['redirect'] : appUrl('customer/cart.php');
    header('Location: ' . $redirectTo);
    exit;
}

$cart = customerCart();
$products = customerCartProducts($pdo, $cart);
$subtotal = array_sum(array_column($products, 'subtotal'));
$totalItems = array_sum($cart);

$success = $_SESSION['cart_flash'] ?? '';
$error = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_flash'], $_SESSION['cart_error']);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="store-page-cart">
    <?php customerStoreHeader('cart'); ?>

    <div class="cart-page-container">
        <!-- CHECKOUT PROGRESS STEPPER -->
        <div class="cart-stepper-bar">
            <div class="stepper-step active">
                <span class="step-num">1</span>
                <span class="step-text">Keranjang Belanja</span>
            </div>
            <div class="stepper-line"></div>
            <div class="stepper-step">
                <span class="step-num">2</span>
                <span class="step-text">Informasi &amp; Checkout</span>
            </div>
            <div class="stepper-line"></div>
            <div class="stepper-step">
                <span class="step-num">3</span>
                <span class="step-text">Pesanan Selesai</span>
            </div>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if ($success): ?>
            <div class="user-alert user-alert-success" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="user-alert user-alert-error" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div class="cart-empty-state">
                <div class="cart-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </div>
                <h2>Keranjang Belanja Masih Kosong</h2>
                <p>Belum ada produk yang Anda tambahkan ke keranjang. Jelajahi katalog lengkap kami untuk menemukan produk menarik!</p>
                <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-empty-start-shop">
                    <span>Mulai Belanja Sekarang</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
            </div>
        <?php else: ?>
            <div class="cart-grid-layout">
                <!-- LEFT COLUMN: CART ITEMS LIST & UPDATE FORM -->
                <div class="cart-items-column">
                    <div class="cart-items-card">
                        <div class="cart-card-header">
                            <div>
                                <h2>Daftar Barang Belanja</h2>
                                <span class="cart-items-count"><?= number_format($totalItems) ?> item dari <?= count($products) ?> jenis produk</span>
                            </div>
                            <a href="<?= e(appUrl('customer/index.php')) ?>" class="link-continue-shop">+ Tambah Produk Lain</a>
                        </div>

                        <form method="post" action="<?= e(appUrl('customer/cart.php')) ?>" id="cartUpdateForm">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="action" value="update">

                            <div class="cart-items-list">
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $pId = (int) $product['id_produk'];
                                    $qty = (int) $product['jumlah_keranjang'];
                                    $stk = (int) $product['stok'];
                                    ?>
                                    <div class="cart-item-row">
                                        <div class="cart-item-image-col">
                                            <a href="<?= e(appUrl('customer/product.php?id=' . $pId)) ?>">
                                                <img src="<?= e(productImageUrl($product)) ?>" alt="<?= e($product['nama_produk']) ?>">
                                            </a>
                                        </div>

                                        <div class="cart-item-info-col">
                                            <span class="cart-item-cat"><?= e($product['nama_kategori'] ?? 'Umum') ?></span>
                                            <h3 class="cart-item-name">
                                                <a href="<?= e(appUrl('customer/product.php?id=' . $pId)) ?>"><?= e($product['nama_produk']) ?></a>
                                            </h3>
                                            <div class="cart-item-unit-price">
                                                Rp <?= number_format((float) $product['harga'], 0, ',', '.') ?> / unit
                                            </div>
                                            <span class="cart-item-stock-info <?= $stk <= 5 ? 'text-warning' : 'text-muted' ?>">
                                                Stok tersedia: <?= number_format($stk) ?>
                                            </span>
                                        </div>

                                        <div class="cart-item-qty-col">
                                            <div class="stepper-controls stepper-sm">
                                                <button type="button" class="stepper-btn" onclick="updateItemQty(<?= $pId ?>, -1, <?= $stk ?>)">-</button>
                                                <input type="number" id="qtyInput_<?= $pId ?>" name="quantities[<?= $pId ?>]" value="<?= $qty ?>" min="1" max="<?= $stk ?>" onchange="document.getElementById('cartUpdateForm').submit()">
                                                <button type="button" class="stepper-btn" onclick="updateItemQty(<?= $pId ?>, 1, <?= $stk ?>)">+</button>
                                            </div>
                                        </div>

                                        <div class="cart-item-subtotal-col">
                                            <span class="subtotal-label">Subtotal</span>
                                            <strong class="subtotal-val">Rp <?= number_format((float) $product['subtotal'], 0, ',', '.') ?></strong>
                                        </div>

                                        <div class="cart-item-remove-col">
                                            <button type="button" class="btn-remove-cart-item" title="Hapus produk ini" onclick="removeCartItem(<?= $pId ?>)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="cart-table-footer">
                                <button type="submit" class="btn-update-cart-inline">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 4v6h-6"></path>
                                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                    </svg>
                                    <span>Perbarui Keranjang</span>
                                </button>
                            </div>
                        </form>

                        <!-- REMOVE ITEM HIDDEN FORM -->
                        <form method="post" action="<?= e(appUrl('customer/cart.php')) ?>" id="cartRemoveItemForm">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="id_produk" id="removeProductIdInput" value="">
                        </form>
                    </div>
                </div>

                <!-- RIGHT COLUMN: ORDER SUMMARY CARD -->
                <div class="cart-summary-column">
                    <div class="cart-summary-card">
                        <h3 class="summary-card-title">Ringkasan Pesanan</h3>

                        <div class="summary-rows-group">
                            <div class="summary-row">
                                <span>Total Item</span>
                                <strong><?= number_format($totalItems) ?> unit</strong>
                            </div>
                            <div class="summary-row">
                                <span>Total Subtotal</span>
                                <strong>Rp <?= number_format($subtotal, 0, ',', '.') ?></strong>
                            </div>
                            <div class="summary-row">
                                <span>Estimasi Ongkir</span>
                                <strong class="text-success">Gratis (Ambil di Toko)</strong>
                            </div>
                            <div class="summary-row">
                                <span>Pajak Transaksi</span>
                                <span>Termasuk</span>
                            </div>
                        </div>

                        <div class="summary-divider"></div>

                        <div class="summary-total-row">
                            <span>Total Pembayaran</span>
                            <strong class="total-big-val">Rp <?= number_format($subtotal, 0, ',', '.') ?></strong>
                        </div>

                        <div class="summary-actions">
                            <a href="<?= e(appUrl('customer/checkout.php')) ?>" class="btn-proceed-checkout">
                                <span>Lanjut ke Checkout</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                            </a>
                            <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-summary-back-shop">
                                <span>← Lanjutkan Memilih Produk</span>
                            </a>
                        </div>

                        <!-- TRUST BADGES -->
                        <div class="summary-trust-strip">
                            <div class="trust-badge-item">
                                <span>🔒</span>
                                <span>Transaksi Aman &amp; Terpercaya</span>
                            </div>
                            <div class="trust-badge-item">
                                <span>⚡</span>
                                <span>Proses Konfirmasi Instan</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php customerStoreFooter(); ?>
</main>

<script>
function updateItemQty(productId, delta, maxStock) {
    const input = document.getElementById('qtyInput_' + productId);
    if (!input) return;
    const current = parseInt(input.value) || 1;
    const updated = Math.max(1, Math.min(maxStock, current + delta));
    input.value = updated;
    document.getElementById('cartUpdateForm').submit();
}

function removeCartItem(productId) {
    if (confirm('Apakah Anda yakin ingin menghapus produk ini dari keranjang?')) {
        document.getElementById('removeProductIdInput').value = productId;
        document.getElementById('cartRemoveItemForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
