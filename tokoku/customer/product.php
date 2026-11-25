<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';
require_once __DIR__ . '/../includes/store.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare(
    'SELECT p.id_produk, p.nama_produk, p.deskripsi, p.harga, p.stok,
            p.gambar, p.id_kategori, k.nama_kategori
     FROM tbl_produk p
     LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori
     WHERE p.id_produk = :id_produk LIMIT 1'
);
$stmt->execute(['id_produk' => $productId && $productId > 0 ? $productId : 0]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
}

$pageTitle = $product ? $product['nama_produk'] . ' - Detail Produk Tokoku' : 'Produk tidak ditemukan - Tokoku';
$success = $_SESSION['cart_flash'] ?? '';
$error = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_flash'], $_SESSION['cart_error']);

// Fetch related products from the same category or random
$relatedProducts = [];
if ($product) {
    if (!empty($product['id_kategori'])) {
        $relStmt = $pdo->prepare(
            'SELECT p.id_produk, p.nama_produk, p.deskripsi, p.harga, p.stok,
                    p.gambar, k.nama_kategori
             FROM tbl_produk p
             LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori
             WHERE p.id_produk != :current_id
               AND p.id_kategori = :cat_id
             ORDER BY p.id_produk DESC
             LIMIT 4'
        );
        $relStmt->execute([
            'current_id' => (int) $product['id_produk'],
            'cat_id' => (int) $product['id_kategori'],
        ]);
        $relatedProducts = $relStmt->fetchAll();
    }

    // If related in same category is few, fill with other latest products
    if (count($relatedProducts) < 4) {
        $excludeIds = array_merge([(int) $product['id_produk']], array_column($relatedProducts, 'id_produk'));
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $limit = 4 - count($relatedProducts);
        $fillStmt = $pdo->prepare(
            'SELECT p.id_produk, p.nama_produk, p.deskripsi, p.harga, p.stok,
                    p.gambar, k.nama_kategori
             FROM tbl_produk p
             LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori
             WHERE p.id_produk NOT IN (' . $placeholders . ')
             ORDER BY p.id_produk DESC
             LIMIT ' . (int) $limit
        );
        $fillStmt->execute($excludeIds);
        $relatedProducts = array_merge($relatedProducts, $fillStmt->fetchAll());
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="store-page-detail">
    <?php customerStoreHeader('shop'); ?>

    <div class="store-detail-container">
        <!-- BREADCRUMB -->
        <nav class="detail-breadcrumb-nav" aria-label="Breadcrumb">
            <a href="<?= e(appUrl('customer/index.php')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Katalog</span>
            </a>
            <span class="bc-sep">/</span>
            <?php if ($product && !empty($product['id_kategori'])): ?>
                <a href="<?= e(appUrl('customer/index.php?category=' . (int) $product['id_kategori'])) ?>">
                    <?= e($product['nama_kategori'] ?? 'Kategori') ?>
                </a>
                <span class="bc-sep">/</span>
            <?php endif; ?>
            <span class="bc-current"><?= e($product['nama_produk'] ?? 'Detail Produk') ?></span>
        </nav>

        <!-- FLASH NOTIFICATIONS -->
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

        <?php if ($product): ?>
            <?php
            $stock = (int) $product['stok'];
            $isAvailable = $stock > 0;
            $categoryName = $product['nama_kategori'] ?? 'Umum';
            $pId = (int) $product['id_produk'];
            $rating = number_format(4.7 + (($pId % 4) * 0.1), 1);
            $sold = 25 + (($pId * 17) % 180);
            ?>

            <article class="product-detail-card">
                <!-- LEFT: IMAGE GALLERY & BADGES -->
                <div class="detail-gallery-column">
                    <div class="detail-main-image-wrap">
                        <img class="detail-main-image" src="<?= e(productImageUrl($product)) ?>" alt="<?= e($product['nama_produk']) ?>">
                        <span class="detail-badge-cat"><?= e($categoryName) ?></span>
                        <?php if ($isAvailable): ?>
                            <?php if ($stock <= 5): ?>
                                <span class="detail-badge-stock badge-stock-low">Stok Menipis (<?= $stock ?>)</span>
                            <?php else: ?>
                                <span class="detail-badge-stock badge-stock-ready">Ready Stock (<?= number_format($stock) ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="detail-badge-stock badge-stock-empty">Stok Habis</span>
                        <?php endif; ?>
                    </div>

                    <!-- ASSURANCE PILLS UNDER IMAGE -->
                    <div class="detail-assurance-row">
                        <div class="assurance-item">
                            <span class="ass-icon">🛡️</span>
                            <span>100% Produk Original</span>
                        </div>
                        <div class="assurance-item">
                            <span class="ass-icon">📦</span>
                            <span>Pengemasan Rapi & Aman</span>
                        </div>
                        <div class="assurance-item">
                            <span class="ass-icon">🔄</span>
                            <span>Jaminan Kepuasan Toko</span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: PRODUCT INFORMATION & ORDER ACTIONS -->
                <div class="detail-info-column">
                    <div class="detail-info-header">
                        <span class="detail-cat-link">
                            <?= customerCategoryIcon($categoryName) ?> <?= e($categoryName) ?>
                        </span>
                        <h1 class="detail-product-title"><?= e($product['nama_produk']) ?></h1>

                        <div class="detail-rating-strip">
                            <div class="rating-stars">
                                <span class="star-icon">★</span>
                                <span class="rating-score"><?= $rating ?></span>
                            </div>
                            <span class="dot-sep">•</span>
                            <span class="sold-count"><?= $sold ?> Terjual</span>
                            <span class="dot-sep">•</span>
                            <span class="product-id-tag">Kode: PRD-<?= str_pad((string) $product['id_produk'], 4, '0', STR_PAD_LEFT) ?></span>
                        </div>
                    </div>

                    <!-- PRICE BOX -->
                    <div class="detail-price-box">
                        <div class="price-main-row">
                            <span class="price-currency">Rp</span>
                            <strong class="price-amount"><?= number_format((float) $product['harga'], 0, ',', '.') ?></strong>
                        </div>
                        <span class="price-tax-badge">Harga pas &amp; sudah termasuk ketersediaan stok realtime</span>
                    </div>

                    <!-- STOCK INVENTORY STATUS -->
                    <div class="detail-stock-status">
                        <div class="stock-status-header">
                            <span>Status Ketersediaan:</span>
                            <?php if ($isAvailable): ?>
                                <strong class="text-success">Tersedia (<?= number_format($stock) ?> unit siap dikirim)</strong>
                            <?php else: ?>
                                <strong class="text-danger">Stok Habis</strong>
                            <?php endif; ?>
                        </div>
                        <div class="stock-progress-bar">
                            <div class="stock-progress-fill <?= $stock <= 5 ? 'fill-low' : 'fill-ready' ?>" style="width: <?= min(100, max(10, $stock * 3)) ?>%"></div>
                        </div>
                    </div>

                    <!-- DESCRIPTION -->
                    <div class="detail-description-box">
                        <h3>Deskripsi Produk</h3>
                        <div class="description-content">
                            <?php if ($product['deskripsi'] !== null && trim($product['deskripsi']) !== ''): ?>
                                <?= nl2br(e($product['deskripsi'])) ?>
                            <?php else: ?>
                                <p class="text-muted">Belum ada keterangan rinci untuk produk ini. Produk dijamin dalam kondisi baru dan asli.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ADD TO CART & BUY NOW FORM -->
                    <form method="post" action="<?= e(appUrl('customer/cart.php')) ?>" class="detail-purchase-form" id="detailPurchaseForm">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id_produk" value="<?= (int) $product['id_produk'] ?>">

                        <div class="quantity-stepper-wrap">
                            <label for="detailQtyInput">Jumlah Pesanan:</label>
                            <div class="stepper-controls">
                                <button type="button" class="stepper-btn btn-step-minus" onclick="adjustQty(-1)" aria-label="Kurangi jumlah">-</button>
                                <input type="number" id="detailQtyInput" name="quantity" min="1" max="<?= max(1, $stock) ?>" value="1" <?= !$isAvailable ? 'disabled' : '' ?>>
                                <button type="button" class="stepper-btn btn-step-plus" onclick="adjustQty(1)" aria-label="Tambah jumlah">+</button>
                            </div>
                            <span class="max-qty-label">(Maks. <?= number_format($stock) ?>)</span>
                        </div>

                        <div class="detail-action-buttons">
                            <button type="submit" class="btn-detail-add-cart" <?= !$isAvailable ? 'disabled' : '' ?>>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                                <span>Tambah ke Keranjang</span>
                            </button>

                            <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-detail-back-catalog">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="19" y1="12" x2="5" y2="12"></line>
                                    <polyline points="12 19 5 12 12 5"></polyline>
                                </svg>
                                <span>Kembali ke Katalog</span>
                            </a>
                        </div>
                    </form>
                </div>
            </article>

            <!-- RELATED PRODUCTS SECTION -->
            <?php if (!empty($relatedProducts)): ?>
                <section class="related-products-section">
                    <div class="related-section-header">
                        <div>
                            <h2>Rekomendasi Produk Pilihan Lainnya</h2>
                            <p>Koleksi produk berkualitas yang mungkin juga Anda sukai</p>
                        </div>
                        <a href="<?= e(appUrl('customer/index.php')) ?>" class="see-all-link">Lihat Semua Katalog →</a>
                    </div>
                    <div class="catalog-grid-view">
                        <?php foreach ($relatedProducts as $relProduct): ?>
                            <?php customerProductCard($relProduct, 'grid'); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        <?php else: ?>
            <div class="catalog-empty-box">
                <div class="empty-icon-art">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h3>Produk Tidak Ditemukan</h3>
                <p>Produk yang Anda cari tidak tersedia atau telah dihapus dari katalog kami.</p>
                <div class="empty-actions">
                    <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-empty-reset">Kembali ke Katalog Utama</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php customerStoreFooter(); ?>
</main>

<script>
function adjustQty(delta) {
    const input = document.getElementById('detailQtyInput');
    if (!input) return;
    const current = parseInt(input.value) || 1;
    const min = parseInt(input.min) || 1;
    const max = parseInt(input.max) || 999;
    const updated = Math.max(min, Math.min(max, current + delta));
    input.value = updated;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
