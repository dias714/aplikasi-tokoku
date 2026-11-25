<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/product_images.php';
require_once __DIR__ . '/../includes/store.php';

$pageTitle = 'Katalog Lengkap & Terstruktur - Tokoku';

// Filters & Query Parameters
$search = trim((string) ($_GET['q'] ?? ''));
$categoryId = trim((string) ($_GET['category'] ?? ''));
$minPrice = filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_FLOAT);
$maxPrice = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_FLOAT);
$stockFilter = trim((string) ($_GET['stock'] ?? '')); // 'all' or 'instock'
$sort = trim((string) ($_GET['sort'] ?? 'latest'));
$viewMode = trim((string) ($_GET['view'] ?? 'grid'));
if (!in_array($viewMode, ['grid', 'list'], true)) {
    $viewMode = 'grid';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

// Query categories with product counts
$catStmt = $pdo->query(
    'SELECT k.id_kategori, k.nama_kategori,
            COUNT(p.id_produk) AS total_produk
     FROM tbl_kategori k
     LEFT JOIN tbl_produk p ON p.id_kategori = k.id_kategori
     GROUP BY k.id_kategori, k.nama_kategori
     ORDER BY k.nama_kategori ASC'
);
$categories = $catStmt->fetchAll();

// Price bounds for presets
$priceStats = $pdo->query('SELECT MIN(harga) AS min_p, MAX(harga) AS max_p, COUNT(*) AS total_all FROM tbl_produk')->fetch();
$minDbPrice = (float) ($priceStats['min_p'] ?? 0);
$maxDbPrice = (float) ($priceStats['max_p'] ?? 1000000);
$totalAllProducts = (int) ($priceStats['total_all'] ?? 0);

// Building SQL WHERE
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(p.nama_produk LIKE :search OR p.deskripsi LIKE :search_description)';
    $params['search'] = '%' . $search . '%';
    $params['search_description'] = '%' . $search . '%';
}

if ($categoryId !== '' && ctype_digit($categoryId)) {
    $where[] = 'p.id_kategori = :id_kategori';
    $params['id_kategori'] = (int) $categoryId;
}

if ($minPrice !== false && $minPrice !== null && $minPrice > 0) {
    $where[] = 'p.harga >= :min_price';
    $params['min_price'] = $minPrice;
}

if ($maxPrice !== false && $maxPrice !== null && $maxPrice > 0) {
    $where[] = 'p.harga <= :max_price';
    $params['max_price'] = $maxPrice;
}

if ($stockFilter === 'instock') {
    $where[] = 'p.stok > 0';
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

// Order by builder
$sortSql = match ($sort) {
    'price_asc' => 'p.harga ASC, p.id_produk DESC',
    'price_desc' => 'p.harga DESC, p.id_produk DESC',
    'name_asc' => 'p.nama_produk ASC',
    'name_desc' => 'p.nama_produk DESC',
    'stock_desc' => 'p.stok DESC, p.id_produk DESC',
    default => 'p.id_produk DESC',
};

// Count matching products
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM tbl_produk p' . $whereSql);
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();

// Pagination calculation
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch products
$listStmt = $pdo->prepare(
    'SELECT p.id_produk, p.nama_produk, p.deskripsi, p.harga, p.stok,
            p.gambar, k.nama_kategori
     FROM tbl_produk p
     LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori'
     . $whereSql .
    ' ORDER BY ' . $sortSql . ' LIMIT :limit OFFSET :offset'
);

foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : (is_float($value) ? PDO::PARAM_STR : PDO::PARAM_STR));
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$products = $listStmt->fetchAll();

// Flash messages
$success = $_SESSION['cart_flash'] ?? '';
$error = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_flash'], $_SESSION['cart_error']);

// Helper function to build query strings preserving filters
function catalogFilterUrl(array $overrides = []): string
{
    $current = [
        'q' => $_GET['q'] ?? null,
        'category' => $_GET['category'] ?? null,
        'min_price' => $_GET['min_price'] ?? null,
        'max_price' => $_GET['max_price'] ?? null,
        'stock' => $_GET['stock'] ?? null,
        'sort' => $_GET['sort'] ?? null,
        'view' => $_GET['view'] ?? null,
        'page' => $_GET['page'] ?? null,
    ];
    $merged = array_merge($current, $overrides);
    $cleaned = array_filter($merged, static fn ($v) => $v !== null && $v !== '');
    return appUrl('customer/index.php' . ($cleaned ? '?' . http_build_query($cleaned) : ''));
}

// Current active category object
$currentCategory = null;
if ($categoryId !== '') {
    foreach ($categories as $cat) {
        if ((string) $cat['id_kategori'] === $categoryId) {
            $currentCategory = $cat;
            break;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="store-page-catalog">
    <?php customerStoreHeader('shop'); ?>

    <!-- HERO PROMOTIONAL SHOWCASE -->
    <section class="catalog-hero-banner">
        <div class="hero-banner-inner">
            <div class="hero-banner-content">
                <div class="hero-label-pill">
                    <span class="pulse-dot"></span>
                    <span>KATALOG BELANJA LENGKAP & TERSTRUKTUR</span>
                </div>
                <h1>Temukan Produk Terbaik dengan Kualitas Terpercaya</h1>
                <p>
                    Nikmati kemudahan berbelanja online dengan katalog yang tertata rapi, informasi stok akurat, dan transparansi harga untuk setiap kebutuhan Anda.
                </p>
                <div class="hero-actions-group">
                    <a href="#katalog-grid" class="btn-hero-primary">
                        <span>Jelajahi Katalog Lengkap</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                    <a href="<?= e(catalogFilterUrl(['stock' => 'instock'])) ?>" class="btn-hero-secondary">
                        <span>Lihat Ready Stock</span>
                    </a>
                </div>
            </div>

            <div class="hero-features-grid">
                <div class="hero-feat-card">
                    <div class="feat-icon-box icon-fast">⚡</div>
                    <div>
                        <strong>Pengiriman Cepat</strong>
                        <span>Pesanan segera disiapkan dengan pengemasan aman.</span>
                    </div>
                </div>
                <div class="hero-feat-card">
                    <div class="feat-icon-box icon-shield">🛡️</div>
                    <div>
                        <strong>100% Produk Original</strong>
                        <span>Jaminan kualitas teruji langsung dari mitra terpercaya.</span>
                    </div>
                </div>
                <div class="hero-feat-card">
                    <div class="feat-icon-box icon-trans">💳</div>
                    <div>
                        <strong>Pilihan Pembayaran</strong>
                        <span>Mendukung tunai kasir, transfer bank, dan QRIS instan.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- HORIZONTAL CATEGORY NAVIGATION BAR -->
    <section class="catalog-category-bar">
        <div class="category-bar-inner">
            <div class="category-bar-label">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                <span>Kategori:</span>
            </div>
            <div class="category-pills-scroll">
                <a class="cat-pill <?= $categoryId === '' ? 'active' : '' ?>" href="<?= e(catalogFilterUrl(['category' => '', 'page' => 1])) ?>">
                    <span class="cat-pill-icon">✨</span>
                    <span class="cat-pill-name">Semua Kategori</span>
                    <span class="cat-pill-count"><?= number_format($totalAllProducts) ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a class="cat-pill <?= $categoryId === (string) $cat['id_kategori'] ? 'active' : '' ?>" href="<?= e(catalogFilterUrl(['category' => (string) $cat['id_kategori'], 'page' => 1])) ?>">
                        <span class="cat-pill-icon"><?= customerCategoryIcon($cat['nama_kategori']) ?></span>
                        <span class="cat-pill-name"><?= e($cat['nama_kategori']) ?></span>
                        <span class="cat-pill-count"><?= number_format((int) $cat['total_produk']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FLASH MESSAGES -->
    <div class="catalog-alerts-container">
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
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- MAIN TWO-COLUMN STRUCTURED CATALOG -->
    <div class="catalog-main-layout" id="katalog-grid">
        <!-- LEFT: STRUCTURED FILTER SIDEBAR -->
        <aside class="catalog-sidebar-filter">
            <div class="sidebar-filter-card">
                <div class="filter-card-header">
                    <div class="filter-header-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        <h3>Filter Katalog</h3>
                    </div>
                    <?php if ($search !== '' || $categoryId !== '' || $minPrice !== false || $maxPrice !== false || $stockFilter !== ''): ?>
                        <a href="<?= e(appUrl('customer/index.php')) ?>" class="filter-reset-link" title="Bersihkan semua filter">Reset</a>
                    <?php endif; ?>
                </div>

                <form method="get" action="<?= e(appUrl('customer/index.php')) ?>" class="filter-form-structured" id="catalogFilterForm">
                    <!-- Preserve View & Sort -->
                    <input type="hidden" name="sort" value="<?= e($sort) ?>">
                    <input type="hidden" name="view" value="<?= e($viewMode) ?>">

                    <!-- 1. SEARCH INPUT -->
                    <div class="filter-section">
                        <label class="filter-section-title" for="filterSearchInput">Pencarian Produk</label>
                        <div class="filter-input-wrap">
                            <input type="search" id="filterSearchInput" name="q" value="<?= e($search) ?>" placeholder="Kata kunci...">
                        </div>
                    </div>

                    <!-- 2. CATEGORY LIST -->
                    <div class="filter-section">
                        <span class="filter-section-title">Kategori</span>
                        <div class="filter-radio-group">
                            <label class="filter-radio-item <?= $categoryId === '' ? 'is-selected' : '' ?>">
                                <input type="radio" name="category" value="" <?= $categoryId === '' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="radio-custom"></span>
                                <span class="radio-label">Semua Kategori</span>
                                <span class="radio-count">(<?= number_format($totalAllProducts) ?>)</span>
                            </label>
                            <?php foreach ($categories as $cat): ?>
                                <label class="filter-radio-item <?= $categoryId === (string) $cat['id_kategori'] ? 'is-selected' : '' ?>">
                                    <input type="radio" name="category" value="<?= (int) $cat['id_kategori'] ?>" <?= $categoryId === (string) $cat['id_kategori'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="radio-custom"></span>
                                    <span class="radio-label"><?= e($cat['nama_kategori']) ?></span>
                                    <span class="radio-count">(<?= number_format((int) $cat['total_produk']) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 3. PRICE RANGE -->
                    <div class="filter-section">
                        <span class="filter-section-title">Rentang Harga (Rp)</span>
                        <div class="price-inputs-row">
                            <div class="price-field">
                                <label for="minPrice">Min</label>
                                <input type="number" id="minPrice" name="min_price" value="<?= $minPrice !== false && $minPrice !== null ? (int) $minPrice : '' ?>" placeholder="0" min="0" step="1000">
                            </div>
                            <span class="price-separator">-</span>
                            <div class="price-field">
                                <label for="maxPrice">Maks</label>
                                <input type="number" id="maxPrice" name="max_price" value="<?= $maxPrice !== false && $maxPrice !== null ? (int) $maxPrice : '' ?>" placeholder="<?= (int) $maxDbPrice ?>" min="0" step="1000">
                            </div>
                        </div>

                        <!-- Price Presets -->
                        <div class="price-presets-chips">
                            <a href="<?= e(catalogFilterUrl(['min_price' => '', 'max_price' => 50000, 'page' => 1])) ?>" class="preset-chip <?= ($maxPrice == 50000 && !$minPrice) ? 'active' : '' ?>">
                                &lt; 50 rb
                            </a>
                            <a href="<?= e(catalogFilterUrl(['min_price' => 50000, 'max_price' => 100000, 'page' => 1])) ?>" class="preset-chip <?= ($minPrice == 50000 && $maxPrice == 100000) ? 'active' : '' ?>">
                                50 - 100 rb
                            </a>
                            <a href="<?= e(catalogFilterUrl(['min_price' => 100000, 'max_price' => '', 'page' => 1])) ?>" class="preset-chip <?= ($minPrice == 100000 && !$maxPrice) ? 'active' : '' ?>">
                                &gt; 100 rb
                            </a>
                        </div>
                    </div>

                    <!-- 4. STOCK AVAILABILITY -->
                    <div class="filter-section">
                        <span class="filter-section-title">Ketersediaan Stok</span>
                        <div class="filter-radio-group">
                            <label class="filter-radio-item <?= $stockFilter !== 'instock' ? 'is-selected' : '' ?>">
                                <input type="radio" name="stock" value="" <?= $stockFilter !== 'instock' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="radio-custom"></span>
                                <span class="radio-label">Semua Status</span>
                            </label>
                            <label class="filter-radio-item <?= $stockFilter === 'instock' ? 'is-selected' : '' ?>">
                                <input type="radio" name="stock" value="instock" <?= $stockFilter === 'instock' ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="radio-custom"></span>
                                <span class="radio-label">Hanya Ready Stock</span>
                            </label>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter-apply">Terapkan Filter</button>
                        <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-filter-clear">Reset</a>
                    </div>
                </form>
            </div>
        </aside>

        <!-- RIGHT: PRODUCTS SECTION -->
        <section class="catalog-products-area">
            <!-- TOOLBAR: COUNTER, ACTIVE FILTERS, SORTING & VIEW SWITCHER -->
            <div class="catalog-toolbar">
                <div class="toolbar-left">
                    <h2 class="catalog-heading-title">
                        <?= $currentCategory ? e($currentCategory['nama_kategori']) : 'Semua Produk' ?>
                    </h2>
                    <span class="catalog-counter-text">
                        Menampilkan <strong><?= number_format(count($products)) ?></strong> dari <strong><?= number_format($totalProducts) ?></strong> produk
                    </span>
                </div>

                <div class="toolbar-right">
                    <!-- SORT DROPDOWN -->
                    <div class="toolbar-sort-wrap">
                        <label for="catalogSortSelect" class="sort-label">Urutkan:</label>
                        <select id="catalogSortSelect" class="sort-select" onchange="window.location.href = this.value">
                            <option value="<?= e(catalogFilterUrl(['sort' => 'latest', 'page' => 1])) ?>" <?= $sort === 'latest' ? 'selected' : '' ?>>Terbaru</option>
                            <option value="<?= e(catalogFilterUrl(['sort' => 'price_asc', 'page' => 1])) ?>" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Harga: Terendah</option>
                            <option value="<?= e(catalogFilterUrl(['sort' => 'price_desc', 'page' => 1])) ?>" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Harga: Tertinggi</option>
                            <option value="<?= e(catalogFilterUrl(['sort' => 'name_asc', 'page' => 1])) ?>" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Nama: A - Z</option>
                            <option value="<?= e(catalogFilterUrl(['sort' => 'name_desc', 'page' => 1])) ?>" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Nama: Z - A</option>
                            <option value="<?= e(catalogFilterUrl(['sort' => 'stock_desc', 'page' => 1])) ?>" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Stok Terbanyak</option>
                        </select>
                    </div>

                    <!-- VIEW MODE TOGGLE -->
                    <div class="view-mode-buttons">
                        <a href="<?= e(catalogFilterUrl(['view' => 'grid'])) ?>" class="view-btn <?= $viewMode === 'grid' ? 'active' : '' ?>" title="Tampilan Kotak (Grid)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                        </a>
                        <a href="<?= e(catalogFilterUrl(['view' => 'list'])) ?>" class="view-btn <?= $viewMode === 'list' ? 'active' : '' ?>" title="Tampilan Daftar (List)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="8" y1="6" x2="21" y2="6"></line>
                                <line x1="8" y1="12" x2="21" y2="12"></line>
                                <line x1="8" y1="18" x2="21" y2="18"></line>
                                <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                <line x1="3" y1="18" x2="3.01" y2="18"></line>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- ACTIVE FILTERS BADGES BAR -->
            <?php
            $hasActiveFilters = ($search !== '' || $categoryId !== '' || ($minPrice !== false && $minPrice > 0) || ($maxPrice !== false && $maxPrice > 0) || $stockFilter === 'instock');
            ?>
            <?php if ($hasActiveFilters): ?>
                <div class="active-filter-chips-bar">
                    <span class="chips-title">Filter aktif:</span>
                    <?php if ($search !== ''): ?>
                        <a href="<?= e(catalogFilterUrl(['q' => '', 'page' => 1])) ?>" class="active-chip" title="Hapus pencarian">
                            <span>Cari: "<?= e($search) ?>"</span>
                            <span class="chip-remove">×</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($categoryId !== '' && $currentCategory): ?>
                        <a href="<?= e(catalogFilterUrl(['category' => '', 'page' => 1])) ?>" class="active-chip" title="Hapus kategori">
                            <span>Kategori: <?= e($currentCategory['nama_kategori']) ?></span>
                            <span class="chip-remove">×</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($minPrice !== false && $minPrice > 0): ?>
                        <a href="<?= e(catalogFilterUrl(['min_price' => '', 'page' => 1])) ?>" class="active-chip" title="Hapus harga min">
                            <span>Min: Rp <?= number_format($minPrice, 0, ',', '.') ?></span>
                            <span class="chip-remove">×</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($maxPrice !== false && $maxPrice > 0): ?>
                        <a href="<?= e(catalogFilterUrl(['max_price' => '', 'page' => 1])) ?>" class="active-chip" title="Hapus harga maks">
                            <span>Maks: Rp <?= number_format($maxPrice, 0, ',', '.') ?></span>
                            <span class="chip-remove">×</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($stockFilter === 'instock'): ?>
                        <a href="<?= e(catalogFilterUrl(['stock' => '', 'page' => 1])) ?>" class="active-chip" title="Tampilkan semua">
                            <span>Hanya Ready Stock</span>
                            <span class="chip-remove">×</span>
                        </a>
                    <?php endif; ?>
                    <a href="<?= e(appUrl('customer/index.php')) ?>" class="clear-all-chips">Hapus Semua</a>
                </div>
            <?php endif; ?>

            <!-- PRODUCT LIST / GRID -->
            <?php if (!$products): ?>
                <div class="catalog-empty-box">
                    <div class="empty-icon-art">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            <line x1="8" y1="11" x2="14" y2="11"></line>
                        </svg>
                    </div>
                    <h3>Produk Tidak Ditemukan</h3>
                    <p>Tidak ada produk yang cocok dengan kriteria pencarian atau filter yang Anda terapkan. Silakan sesuaikan filter atau reset pencarian.</p>
                    <div class="empty-actions">
                        <a href="<?= e(appUrl('customer/index.php')) ?>" class="btn-empty-reset">Tampilkan Semua Produk</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="<?= $viewMode === 'list' ? 'catalog-list-view' : 'catalog-grid-view' ?>">
                    <?php foreach ($products as $product): ?>
                        <?php customerProductCard($product, $viewMode); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
                <nav class="catalog-pagination-nav" aria-label="Navigasi Halaman Katalog">
                    <div class="pagination-info">
                        Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong>
                    </div>
                    <div class="pagination-pages">
                        <?php if ($page > 1): ?>
                            <a class="pagination-btn pagination-prev" href="<?= e(catalogFilterUrl(['page' => $page - 1])) ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                                <span>Sebelumnya</span>
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a class="pagination-num <?= $i === $page ? 'active' : '' ?>" href="<?= e(catalogFilterUrl(['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="pagination-btn pagination-next" href="<?= e(catalogFilterUrl(['page' => $page + 1])) ?>">
                                <span>Berikutnya</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </section>
    </div>

    <?php customerStoreFooter(); ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
