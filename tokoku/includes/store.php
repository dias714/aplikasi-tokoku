<?php

function validCustomerCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function customerCart(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $cart = [];
    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $id = filter_var($productId, FILTER_VALIDATE_INT);
        $qty = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($id && $id > 0 && $qty && $qty > 0) {
            $cart[$id] = min($qty, 999);
        }
    }
    $_SESSION['cart'] = $cart;

    return $cart;
}

function customerCartProducts(PDO $pdo, array $cart, bool $lockRows = false): array
{
    if (!$cart) {
        return [];
    }

    $ids = array_map('intval', array_keys($cart));
    sort($ids, SORT_NUMERIC);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT p.id_produk, p.nama_produk, p.deskripsi, p.harga, p.stok,
                   p.gambar, k.nama_kategori
            FROM tbl_produk p
            LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori
            WHERE p.id_produk IN (' . $placeholders . ')
            ORDER BY p.nama_produk';
    if ($lockRows) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
    foreach ($products as &$product) {
        $product['jumlah_keranjang'] = $cart[(int) $product['id_produk']] ?? 0;
        $product['subtotal'] = (float) $product['harga'] * $product['jumlah_keranjang'];
    }
    unset($product);

    return $products;
}

function customerCategoryIcon(string $categoryName): string
{
    $lower = mb_strtolower($categoryName);
    if (str_contains($lower, 'makan') || str_contains($lower, 'minum') || str_contains($lower, 'kuliner')) {
        return '🍱';
    }
    if (str_contains($lower, 'pakaian') || str_contains($lower, 'mode') || str_contains($lower, 'baju') || str_contains($lower, 'fashion')) {
        return '👕';
    }
    if (str_contains($lower, 'elektronik') || str_contains($lower, 'gadget') || str_contains($lower, 'hp') || str_contains($lower, 'komputer')) {
        return '⚡';
    }
    if (str_contains($lower, 'rumah') || str_contains($lower, 'dapur') || str_contains($lower, 'perabot')) {
        return '🏠';
    }
    if (str_contains($lower, 'buku') || str_contains($lower, 'tulis') || str_contains($lower, 'kantor') || str_contains($lower, 'atk')) {
        return '📚';
    }
    if (str_contains($lower, 'kesehatan') || str_contains($lower, 'obat') || str_contains($lower, 'medis')) {
        return '💊';
    }
    if (str_contains($lower, 'olahraga') || str_contains($lower, 'sport')) {
        return '⚽';
    }
    return '🏷️';
}

function customerProductCard(array $product, string $viewMode = 'grid'): void
{
    $productUrl = appUrl('customer/product.php?id=' . (int) $product['id_produk']);
    $imageUrl = productImageUrl($product);
    $stock = (int) $product['stok'];
    $categoryName = $product['nama_kategori'] ?? 'Umum';
    $isAvailable = $stock > 0;

    // Deterministic simulation for rating & sold counts based on product ID
    $id = (int) $product['id_produk'];
    $rating = number_format(4.7 + (($id % 4) * 0.1), 1);
    $sold = 25 + (($id * 17) % 180);
    ?>
    <article class="store-product-card <?= $viewMode === 'list' ? 'store-product-card-list' : '' ?>">
        <div class="store-card-image-wrapper">
            <a class="store-product-image-link" href="<?= e($productUrl) ?>" aria-label="Lihat detail <?= e($product['nama_produk']) ?>">
                <img class="store-product-image" src="<?= e($imageUrl) ?>" alt="<?= e($product['nama_produk']) ?>" loading="lazy">
            </a>
            <span class="store-badge-category"><?= e($categoryName) ?></span>
            <?php if ($isAvailable): ?>
                <?php if ($stock <= 5): ?>
                    <span class="store-badge-stock badge-stock-low">Sisa <?= $stock ?> unit</span>
                <?php else: ?>
                    <span class="store-badge-stock badge-stock-ready">Ready (<?= number_format($stock) ?>)</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="store-badge-stock badge-stock-empty">Habis</span>
            <?php endif; ?>
        </div>

        <div class="store-product-body">
            <div class="store-product-meta">
                <span class="store-product-cat-name"><?= e($categoryName) ?></span>
                <div class="store-product-rating" title="Rating kepuasan pelanggan">
                    <span class="star-icon" aria-hidden="true">★</span>
                    <span class="rating-val"><?= $rating ?></span>
                    <span class="sold-val">(<?= $sold ?> terjual)</span>
                </div>
            </div>

            <h3 class="store-product-title">
                <a href="<?= e($productUrl) ?>"><?= e($product['nama_produk']) ?></a>
            </h3>

            <?php if (!empty($product['deskripsi'])): ?>
                <p class="store-product-excerpt">
                    <?= e(mb_strimwidth(strip_tags((string) $product['deskripsi']), 0, 85, '...')) ?>
                </p>
            <?php endif; ?>

            <div class="store-product-footer-row">
                <div class="store-price-container">
                    <span class="price-prefix">Rp</span>
                    <strong class="store-product-price"><?= number_format((float) $product['harga'], 0, ',', '.') ?></strong>
                </div>

                <div class="store-card-actions">
                    <form method="post" action="<?= e(appUrl('customer/cart.php')) ?>" class="store-quick-add-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id_produk" value="<?= (int) $product['id_produk'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button class="user-btn btn-cart-quick" type="submit" <?= !$isAvailable ? 'disabled' : '' ?> title="Tambah ke Keranjang">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            <span>+ Keranjang</span>
                        </button>
                    </form>
                    <a class="btn-detail-link" href="<?= e($productUrl) ?>" title="Lihat rincian lengkap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </article>
    <?php
}

function customerStoreHeader(string $activePage): void
{
    $cart = customerCart();
    $cartCount = array_sum($cart);
    $userRole = $_SESSION['role'] ?? '';
    $userName = $_SESSION['name'] ?? '';
    $isLoggedIn = !empty($_SESSION['user_id']);
    ?>
    <!-- TOP ANNOUNCEMENT BAR -->
    <aside class="store-topbar">
        <div class="store-topbar-inner">
            <div class="store-topbar-left">
                <span class="store-topbar-badge">🌟 TOKO RESMI</span>
                <span>Katalog Belanja Lengkap • 100% Produk Original • Transaksi Mudah</span>
            </div>
            <div class="store-topbar-right">
                <span>⏱️ Buka 24 Jam Online</span>
                <span class="topbar-sep">•</span>
                <span>🛡️ Garansi Kualitas</span>
                <?php if ($userRole === 'admin'): ?>
                    <span class="topbar-sep">•</span>
                    <a href="<?= e(appUrl('admin/dashboard.php')) ?>" class="topbar-dashboard-link topbar-dashboard-btn">← Kembali ke Dashboard Admin</a>
                <?php elseif ($userRole === 'kasir'): ?>
                    <span class="topbar-sep">•</span>
                    <a href="<?= e(appUrl('kasir/dashboard.php')) ?>" class="topbar-dashboard-link topbar-dashboard-btn">← Kembali ke Dashboard Kasir</a>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <!-- MAIN NAVBAR -->
    <header class="store-header">
        <div class="store-header-inner">
            <!-- BRAND LOGO -->
            <a class="store-brand" href="<?= e(appUrl('customer/index.php')) ?>">
                <div class="store-brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                </div>
                <div class="store-brand-text">
                    <span class="brand-title">Tokoku</span>
                    <span class="brand-tagline">Katalog Lengkap & Terstruktur</span>
                </div>
            </a>

            <!-- HEADER SEARCH BAR (FAST ACCESS) -->
            <form method="get" action="<?= e(appUrl('customer/index.php')) ?>" class="store-header-search">
                <div class="header-search-wrapper">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari barang, kategori, atau produk kebutuhan Anda..." aria-label="Cari produk">
                    <button type="submit" class="header-search-btn">Cari</button>
                </div>
            </form>

            <!-- NAVIGATION LINKS -->
            <nav class="store-navigation" aria-label="Navigasi pelanggan">
                <a class="nav-item <?= $activePage === 'shop' ? 'active' : '' ?>" href="<?= e(appUrl('customer/index.php')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Katalog</span>
                </a>

                <?php if ($isLoggedIn && $userRole === 'pelanggan'): ?>
                    <a class="nav-item <?= $activePage === 'orders' ? 'active' : '' ?>" href="<?= e(appUrl('customer/orders.php')) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <span>Pesanan Saya</span>
                    </a>
                <?php endif; ?>

                <?php if ($userRole === 'kasir'): ?>
                    <a class="nav-item nav-item-dashboard" href="<?= e(appUrl('kasir/dashboard.php')) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        <span>Dashboard Kasir</span>
                    </a>
                <?php elseif ($userRole === 'admin'): ?>
                    <a class="nav-item nav-item-dashboard" href="<?= e(appUrl('admin/dashboard.php')) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        <span>Dashboard Admin</span>
                    </a>
                <?php endif; ?>

                <!-- CART LINK -->
                <a class="nav-item store-cart-btn <?= $activePage === 'cart' ? 'active' : '' ?>" href="<?= e(appUrl('customer/cart.php')) ?>">
                    <div class="cart-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span class="cart-badge-count <?= $cartCount > 0 ? 'has-items' : '' ?>" id="headerCartCount"><?= number_format($cartCount) ?></span>
                    </div>
                    <span>Keranjang</span>
                </a>
            </nav>

            <!-- USER ACCOUNT / AUTH -->
            <div class="store-user-actions">
                <?php if ($isLoggedIn): ?>
                    <div class="user-profile-menu">
                        <div class="user-avatar-badge" title="Login sebagai <?= e($userRole) ?>">
                            <span class="user-initial"><?= e(mb_strtoupper(mb_substr($userName ?: 'P', 0, 1))) ?></span>
                        </div>
                        <div class="user-profile-info">
                            <strong class="user-name-display"><?= e($userName ?: 'Akun Anda') ?></strong>
                            <span class="user-role-display"><?= e(ucfirst($userRole)) ?></span>
                        </div>
                        <a href="<?= e(appUrl('index.php?logout=1')) ?>" class="btn-logout-link" title="Keluar akun">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="guest-auth-buttons">
                        <a href="<?= e(appUrl('index.php')) ?>" class="btn-guest-login">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                            <span>Masuk / Login</span>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- MOBILE BURGER BUTTON -->
                <button class="mobile-menu-toggle" type="button" aria-label="Buka menu navigasi" id="btnToggleMobileMenu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>

        <!-- MOBILE DRAWER -->
        <div class="mobile-drawer-overlay" id="mobileDrawerOverlay"></div>
        <div class="mobile-drawer-menu" id="mobileDrawerMenu">
            <div class="mobile-drawer-header">
                <div class="store-brand">
                    <div class="store-brand-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                        </svg>
                    </div>
                    <span class="brand-title">Tokoku</span>
                </div>
                <button class="mobile-drawer-close" type="button" id="btnCloseMobileDrawer" aria-label="Tutup menu">×</button>
            </div>

            <div class="mobile-drawer-body">
                <?php if ($isLoggedIn): ?>
                    <div class="mobile-user-card">
                        <div class="user-avatar-badge">
                            <span><?= e(mb_strtoupper(mb_substr($userName ?: 'P', 0, 1))) ?></span>
                        </div>
                        <div>
                            <strong><?= e($userName) ?></strong>
                            <span class="role-sub">Role: <?= e(ucfirst($userRole)) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <nav class="mobile-nav-list">
                    <a href="<?= e(appUrl('customer/index.php')) ?>" class="<?= $activePage === 'shop' ? 'active' : '' ?>">
                        <span>📦 Katalog Produk</span>
                    </a>
                    <?php if ($isLoggedIn && $userRole === 'pelanggan'): ?>
                        <a href="<?= e(appUrl('customer/orders.php')) ?>" class="<?= $activePage === 'orders' ? 'active' : '' ?>">
                            <span>📋 Pesanan Saya</span>
                        </a>
                    <?php endif; ?>
                    <a href="<?= e(appUrl('customer/cart.php')) ?>" class="<?= $activePage === 'cart' ? 'active' : '' ?>">
                        <span>🛒 Keranjang Belanja (<?= number_format($cartCount) ?>)</span>
                    </a>
                    <?php if ($userRole === 'admin'): ?>
                        <a href="<?= e(appUrl('admin/dashboard.php')) ?>"><span>← Kembali ke Dashboard Admin</span></a>
                    <?php elseif ($userRole === 'kasir'): ?>
                        <a href="<?= e(appUrl('kasir/dashboard.php')) ?>"><span>← Kembali ke Dashboard Kasir</span></a>
                    <?php endif; ?>
                </nav>

                <div class="mobile-drawer-footer">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= e(appUrl('index.php?logout=1')) ?>" class="mobile-drawer-logout">
                            <span>🚪 Keluar dari Akun</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= e(appUrl('index.php')) ?>" class="mobile-drawer-login-btn">
                            <span>🔑 Masuk ke Akun</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <?php
}

function customerStoreFooter(): void
{
    ?>
    <footer class="store-structured-footer">
        <div class="store-footer-top">
            <div class="store-footer-col brand-col">
                <div class="store-brand">
                    <div class="store-brand-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <span class="brand-title">Tokoku</span>
                </div>
                <p class="brand-desc">
                    Platform e-commerce katalog terstruktur dan terpercaya. Memberikan kemudahan berbelanja berbagai kebutuhan berkualitas dengan harga transparan dan stok realtime.
                </p>
                <div class="store-cert-badges">
                    <span class="cert-pill">✓ 100% Original</span>
                    <span class="cert-pill">✓ Bergaransi</span>
                    <span class="cert-pill">✓ Stok Akurat</span>
                </div>
            </div>

            <div class="store-footer-col">
                <h4>Katalog Pilihan</h4>
                <ul class="footer-links">
                    <li><a href="<?= e(appUrl('customer/index.php')) ?>">Semua Produk</a></li>
                    <li><a href="<?= e(appUrl('customer/index.php?sort=latest')) ?>">Produk Terbaru</a></li>
                    <li><a href="<?= e(appUrl('customer/index.php?stock=instock')) ?>">Ready Stock Siap Kirim</a></li>
                    <li><a href="<?= e(appUrl('customer/index.php?sort=price_asc')) ?>">Koleksi Hemat</a></li>
                </ul>
            </div>

            <div class="store-footer-col">
                <h4>Bantuan & Transaksi</h4>
                <ul class="footer-links">
                    <li><a href="<?= e(appUrl('customer/cart.php')) ?>">Keranjang Belanja</a></li>
                    <li><a href="<?= e(appUrl('customer/orders.php')) ?>">Cek Status Pesanan</a></li>
                    <li><a href="<?= e(appUrl('index.php')) ?>">Pusat Akses Akun</a></li>
                    <li><span>Metode: Tunai, QRIS, Transfer</span></li>
                </ul>
            </div>

            <div class="store-footer-col">
                <h4>Layanan Pelanggan</h4>
                <div class="footer-contact-info">
                    <p><strong>Operasional:</strong> Senin - Minggu (24 Jam)</p>
                    <p><strong>Layanan Kasir:</strong> 08:00 - 21:00 WIB</p>
                    <p><strong>Lokasi:</strong> Toko Pusat & Cabang Mitra</p>
                </div>
            </div>
        </div>

        <div class="store-footer-bottom">
            <p>© <?= date('Y') ?> <strong>Tokoku</strong>. Seluruh hak cipta dilindungi. Didesain dengan standar Katalog Lengkap & Terstruktur.</p>
        </div>
    </footer>
    <?php
}
